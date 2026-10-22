<?php
declare(strict_types=1);

namespace Pixelite;

/**
 * Minimal, strict ZIP reader in plain PHP (no ZipArchive needed – it is not guaranteed on the host).
 * It NEVER extracts to disk: it lists entries and returns the bytes of one entry at a time, so the caller decides where (and whether)
 * anything is written. Refused outright: encryption, ZIP64, multi-disk archives, methods other than stored/deflate, symlinks and special files,
 * oversized entries, too many entries and mismatching sizes/CRCs.
 */
final class ZipReader
{
    public const MAX_ENTRIES = 1500;

    /** @return list<array{name:string,method:int,csize:int,usize:int,crc:int,offset:int,mode:int,dir:bool}>
     *  @throws \RuntimeException with a short, user-safe message */
    public static function entries(string $file): array
    {
        $size = filesize($file);
        if ($size === false || $size < 22) {
            throw new \RuntimeException('Not a valid ZIP archive.');
        }
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Could not read the archive.');
        }
        try {
            $tail = min($size, 22 + 65535);
            fseek($fh, $size - $tail);
            $buf = (string) fread($fh, $tail);
            $pos = strrpos($buf, "PK\x05\x06");
            if ($pos === false || strlen($buf) - $pos < 22) {
                throw new \RuntimeException('Not a valid ZIP archive.');
            }
            $eocd = unpack('vdisk/vcdisk/vthis/vtotal/Vcdsize/Vcdoff/vclen', substr($buf, $pos + 4, 18));
            if ($eocd['disk'] !== 0 || $eocd['cdisk'] !== 0 || $eocd['this'] !== $eocd['total'] || $eocd['total'] === 0xFFFF || $eocd['cdoff'] === 0xFFFFFFFF || $eocd['cdsize'] === 0xFFFFFFFF) {
                throw new \RuntimeException('Multi-part and ZIP64 archives are not supported.');
            }
            if ($eocd['total'] > self::MAX_ENTRIES) {
                throw new \RuntimeException('The archive contains too many entries.');
            }
            if ($eocd['cdsize'] > 2 * 1024 * 1024 || $eocd['cdoff'] + $eocd['cdsize'] > $size) {
                throw new \RuntimeException('The archive directory is damaged or too large.');
            }
            fseek($fh, $eocd['cdoff']);
            $cd = (string) fread($fh, $eocd['cdsize']);
            $out = [];
            $p = 0;
            for ($i = 0; $i < $eocd['total']; $i++) {
                if (substr($cd, $p, 4) !== "PK\x01\x02" || strlen($cd) - $p < 46) {
                    throw new \RuntimeException('The archive directory is damaged.');
                }
                $h = unpack('vmadeby/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/viattr/Vxattr/Voffset', substr($cd, $p + 4, 42));
                $name = substr($cd, $p + 46, $h['nlen']);
                $p += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
                if ($p > strlen($cd)) {
                    throw new \RuntimeException('The archive directory is damaged.');
                }
                if (($h['flags'] & 0x1) !== 0 || ($h['flags'] & 0x40) !== 0) {
                    throw new \RuntimeException('Encrypted archives are not supported.');
                }
                if ($h['csize'] === 0xFFFFFFFF || $h['usize'] === 0xFFFFFFFF || $h['offset'] === 0xFFFFFFFF) {
                    throw new \RuntimeException('ZIP64 archives are not supported.');
                }
                $mode = ($h['madeby'] >> 8) === 3 ? ($h['xattr'] >> 16) & 0xFFFF : 0;   // unix permission bits, only meaningful when made on a unix host
                $type = $mode & 0170000;
                $dir = str_ends_with($name, '/');
                if ($type !== 0 && $type !== 0100000 && $type !== 0040000) {
                    throw new \RuntimeException('The archive contains links or special files, which are not allowed.');
                }
                if (!$dir && !in_array($h['method'], [0, 8], true)) {
                    throw new \RuntimeException('The archive uses an unsupported compression method.');
                }
                $out[] = ['name' => $name, 'method' => $h['method'], 'csize' => $h['csize'], 'usize' => $h['usize'], 'crc' => $h['crc'], 'offset' => $h['offset'], 'mode' => $mode, 'dir' => $dir];
            }
            return $out;
        } finally {
            fclose($fh);
        }
    }

    /** Bytes of one (non-directory) entry, verified against the declared size and CRC-32. @param array{method:int,csize:int,usize:int,crc:int,offset:int} $e */
    public static function read(string $file, array $e, int $maxBytes): string
    {
        if ($e['usize'] > $maxBytes || $e['csize'] > $maxBytes + 1024) {
            throw new \RuntimeException('A file in the archive is too large.');
        }
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Could not read the archive.');
        }
        try {
            fseek($fh, $e['offset']);
            $lh = (string) fread($fh, 30);
            if (strlen($lh) < 30 || substr($lh, 0, 4) !== "PK\x03\x04") {
                throw new \RuntimeException('The archive is damaged.');
            }
            $l = unpack('vnlen/velen', substr($lh, 26, 4));
            fseek($fh, $e['offset'] + 30 + $l['nlen'] + $l['elen']);
            $comp = $e['csize'] > 0 ? (string) fread($fh, $e['csize']) : '';
            if (strlen($comp) !== $e['csize']) {
                throw new \RuntimeException('The archive is truncated.');
            }
            $data = $e['method'] === 0 ? $comp : @gzinflate($comp, $e['usize'] + 1);
            if (!is_string($data) || strlen($data) !== $e['usize'] || (crc32($data) & 0xFFFFFFFF) !== $e['crc']) {
                throw new \RuntimeException('The archive is damaged (size or checksum mismatch).');
            }
            return $data;
        } finally {
            fclose($fh);
        }
    }
}
