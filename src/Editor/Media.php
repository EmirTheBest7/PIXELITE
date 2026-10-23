<?php
declare(strict_types=1);

namespace Pixelite\Editor;

/**
 * EXPERIMENTAL visual editor. Images a customer adds to THEIR draft: storage/projects/<uid>/editor/media/m<16 hex>.(jpg|png).
 * Every upload is decoded and re-encoded with GD, which discards metadata (EXIF/GPS, ICC, comments) and anything appended to the file;
 * the original bytes are never stored or served. Fails closed when GD is unavailable.
 */
final class Media
{
    public const MAX_UPLOAD_BYTES = 4 * 1024 * 1024;
    public const MAX_SOURCE_PIXELS = 12_000_000;   // decoded bitmaps are ~4 bytes/pixel: keeps one upload far below memory_limit
    public const MAX_EDGE = 1600;                  // longest side after re-encoding
    public const MAX_FILES = 20;
    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;
    private const TYPES = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagepng');
    }

    public static function dir(string $uid): string
    {
        return DraftStore::dir($uid) . '/media';
    }

    /** Resolve an id to its file inside this project, or null. The id is matched against a fixed pattern; it is never a path. */
    public static function path(string $uid, string $id): ?string
    {
        if (!preg_match('/^m[a-f0-9]{16}\z/', $id)) {
            return null;
        }
        foreach (['jpg', 'png'] as $ext) {
            $f = self::dir($uid) . "/$id.$ext";
            if (is_file($f) && !is_link($f)) {
                return $f;
            }
        }
        return null;
    }

    public static function exists(string $uid, string $id): bool
    {
        return self::path($uid, $id) !== null;
    }

    public static function mime(string $file): string
    {
        return str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg';
    }

    /**
     * @param array{files?:int,bytes?:int}|null $limits per-workspace quota (default: this class's project limits)
     * @return array{ok:true,id:string}|array{ok:false,error:string}
     */
    public static function ingest(string $uid, string $bytes, ?array $limits = null): array
    {
        $maxFiles = (int) ($limits['files'] ?? self::MAX_FILES);
        $maxBytes = (int) ($limits['bytes'] ?? self::MAX_TOTAL_BYTES);
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Image processing is not available on this server.'];
        }
        $len = strlen($bytes);
        if ($len < 100 || $len > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'error' => 'Images must be at most ' . intdiv(self::MAX_UPLOAD_BYTES, 1048576) . ' MB.'];
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            return ['ok' => false, 'error' => 'Use a JPEG, PNG or WebP image.'];
        }
        [$w, $h] = [(int) $info[0], (int) $info[1]];
        if ($w < 16 || $h < 16 || $w * $h > self::MAX_SOURCE_PIXELS || max($w, $h) > 8000) {
            return ['ok' => false, 'error' => 'That image is too small or too large (up to ~12 megapixels).'];
        }
        // Decoding needs ~4 bytes/pixel and rotating (EXIF) briefly holds a second copy: refuse what would not fit in this host's memory_limit
        // instead of dying with a fatal "memory exhausted" (measured: 12 MP + rotation peaks at ~103 MB).
        $limit = self::memoryLimitBytes();
        if ($limit > 0 && $w * $h * 4 * 2.6 + memory_get_usage() > $limit) {
            return ['ok' => false, 'error' => 'That image is too large for this server. Try one under about 4 megapixels.'];
        }
        $existing = self::inventory($uid);
        if (count($existing) >= $maxFiles || array_sum($existing) > $maxBytes) {
            return ['ok' => false, 'error' => 'This draft has reached its image limit.'];
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return ['ok' => false, 'error' => 'That file could not be read as an image.'];
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $src = self::orient($src, self::jpegOrientation($bytes));
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1.0, self::MAX_EDGE / max($w, $h));
        $out = $scale < 1.0 ? imagescale($src, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC) : $src;
        if ($out === false) {
            return ['ok' => false, 'error' => 'That image could not be processed.'];
        }
        $alpha = $info[2] !== IMAGETYPE_JPEG && self::hasAlpha($out);
        ob_start();
        if ($alpha) {
            imagesavealpha($out, true);
            imagepng($out, null, 6);
        } else {
            $flat = imagecreatetruecolor(imagesx($out), imagesy($out));
            imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $out, 0, 0, 0, 0, imagesx($out), imagesy($out));
            imagejpeg($flat, null, 82);
        }
        $data = (string) ob_get_clean();
        if ($data === '') {
            return ['ok' => false, 'error' => 'That image could not be processed.'];
        }
        $dir = self::dir($uid);
        if (is_link($dir) || (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir))) {
            return ['ok' => false, 'error' => 'The image could not be stored.'];
        }
        // quota check + write under an exclusive lock, so parallel uploads cannot all pass the check and overshoot the limit
        $lock = @fopen("$dir/.media.lock", 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return ['ok' => false, 'error' => 'The image could not be stored.'];
        }
        try {
            $existing = self::inventory($uid);
            if (count($existing) >= $maxFiles || array_sum($existing) + strlen($data) > $maxBytes) {
                return ['ok' => false, 'error' => 'This draft has reached its image limit.'];
            }
            $id = 'm' . bin2hex(random_bytes(8));
            $file = "$dir/$id." . ($alpha ? 'png' : 'jpg');
            $tmp = "$dir/.up-" . bin2hex(random_bytes(6));
            if (file_put_contents($tmp, $data) !== strlen($data) || !@rename($tmp, $file)) {
                @unlink($tmp);
                return ['ok' => false, 'error' => 'The image could not be stored.'];
            }
            @chmod($file, 0644);
            return ['ok' => true, 'id' => $id];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function memoryLimitBytes(): int
    {
        $v = trim((string) ini_get('memory_limit'));
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }

    /** @return list<array{id:string,bytes:int}> every uploaded image of the project, newest first */
    public static function all(string $uid): array
    {
        $out = [];
        foreach (glob(self::dir($uid) . '/m*.{jpg,png}', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f) && !is_link($f) && preg_match('/^(m[a-f0-9]{16})\.(?:jpg|png)\z/', basename($f), $m)) {
                $out[] = ['id' => $m[1], 'bytes' => (int) filesize($f), 'at' => (int) filemtime($f)];
            }
        }
        usort($out, static fn(array $a, array $b) => $b['at'] <=> $a['at']);
        return array_map(static fn(array $r) => ['id' => $r['id'], 'bytes' => $r['bytes']], $out);
    }

    public static function delete(string $uid, string $id): bool
    {
        $f = self::path($uid, $id);
        return $f !== null && @unlink($f);
    }

    /** @return array<string,int> file name => bytes */
    private static function inventory(string $uid): array
    {
        $out = [];
        foreach (glob(self::dir($uid) . '/m*.{jpg,png}', GLOB_BRACE) ?: [] as $f) {
            $out[basename($f)] = (int) @filesize($f);
        }
        return $out;
    }

    private static function hasAlpha(\GdImage $im): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $step = max(1, (int) floor(max($w, $h) / 64));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if ((imagecolorat($im, $x, $y) >> 24) & 0x7F) {
                    return true;
                }
            }
        }
        return false;
    }

    /** EXIF orientation (1–8) of a JPEG, read from the APP1 segment; 1 when absent. Phone photos rely on it, and re-encoding drops the tag. */
    private static function jpegOrientation(string $b): int
    {
        $pos = 2;
        $n = strlen($b);
        while ($pos + 4 < $n && $b[$pos] === "\xFF") {
            $marker = ord($b[$pos + 1]);
            $len = (ord($b[$pos + 2]) << 8) | ord($b[$pos + 3]);
            if ($marker === 0xE1 && substr($b, $pos + 4, 6) === "Exif\0\0") {
                $t = substr($b, $pos + 10, $len - 8);
                if (strlen($t) < 12) {
                    return 1;
                }
                $le = substr($t, 0, 2) === 'II';
                $u16 = static fn(string $s, int $o): int => $le ? (ord($s[$o + 1]) << 8) | ord($s[$o]) : (ord($s[$o]) << 8) | ord($s[$o + 1]);
                $u32 = static fn(string $s, int $o): int => $le ? unpack('V', substr($s, $o, 4))[1] : unpack('N', substr($s, $o, 4))[1];
                $ifd = $u32($t, 4);
                if ($ifd + 2 > strlen($t)) {
                    return 1;
                }
                $count = min($u16($t, $ifd), 64);
                for ($i = 0; $i < $count; $i++) {
                    $e = $ifd + 2 + $i * 12;
                    if ($e + 12 <= strlen($t) && $u16($t, $e) === 0x0112) {
                        $o = $u16($t, $e + 8);
                        return $o >= 1 && $o <= 8 ? $o : 1;
                    }
                }
                return 1;
            }
            if ($marker === 0xDA || $len < 2) {
                break;
            }
            $pos += 2 + $len;
        }
        return 1;
    }

    private static function orient(\GdImage $im, int $o): \GdImage
    {
        $flip = static function (\GdImage $i, int $mode): \GdImage {
            imageflip($i, $mode);
            return $i;
        };
        $rot = static fn(\GdImage $i, float $deg): \GdImage => imagerotate($i, $deg, 0) ?: $i;
        return match ($o) {
            2 => $flip($im, IMG_FLIP_HORIZONTAL),
            3 => $rot($im, 180),
            4 => $flip($im, IMG_FLIP_VERTICAL),
            5 => $flip($rot($im, -90), IMG_FLIP_HORIZONTAL),
            6 => $rot($im, -90),
            7 => $flip($rot($im, 90), IMG_FLIP_HORIZONTAL),
            8 => $rot($im, 90),
            default => $im,
        };
    }
}
