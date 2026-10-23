<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder;

/**
 * EXPERIMENTAL site builder – CSS allowlist filter. Customer CSS is rendered inside a <style> element of a sandboxed, script-free document, so the
 * goals are: (1) it can never end the <style> element or smuggle markup, (2) it cannot load anything except this project's own images,
 * (3) only ordinary presentational properties survive. Everything is re-serialised from parsed tokens: the original text is never echoed.
 *
 * Dropped, never repaired: @import/@charset/@namespace/@font-face/@property/…, any backslash escape (used to hide keywords), "<", "{" "}" inside values,
 * expression()/behavior/-moz-binding/javascript:, url() to anything but an allowed target, unknown properties, unbalanced input.
 */
final class CssSanitizer
{
    public const MAX_BYTES = 100_000;
    private const MAX_RULES = 2000;

    /** Properties a page may set (plus custom properties --name). */
    private const PROPS = [
        'align-content', 'align-items', 'align-self', 'animation', 'animation-delay', 'animation-direction', 'animation-duration', 'animation-fill-mode', 'animation-iteration-count', 'animation-name', 'animation-play-state', 'animation-timing-function',
        'aspect-ratio', 'backdrop-filter', 'backface-visibility', 'background', 'background-attachment', 'background-blend-mode', 'background-clip', 'background-color', 'background-image', 'background-origin', 'background-position', 'background-position-x', 'background-position-y', 'background-repeat', 'background-size',
        'border', 'border-bottom', 'border-bottom-color', 'border-bottom-left-radius', 'border-bottom-right-radius', 'border-bottom-style', 'border-bottom-width', 'border-collapse', 'border-color', 'border-left', 'border-left-color', 'border-left-style', 'border-left-width', 'border-radius', 'border-right', 'border-right-color', 'border-right-style', 'border-right-width', 'border-spacing', 'border-style', 'border-top', 'border-top-color', 'border-top-left-radius', 'border-top-right-radius', 'border-top-style', 'border-top-width', 'border-width',
        'bottom', 'box-shadow', 'box-sizing', 'caption-side', 'clear', 'clip-path', 'color', 'column-count', 'column-gap', 'column-rule', 'column-width', 'columns', 'content', 'cursor', 'direction', 'display', 'empty-cells', 'filter',
        'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow', 'flex-shrink', 'flex-wrap', 'float', 'font', 'font-family', 'font-feature-settings', 'font-kerning', 'font-size', 'font-stretch', 'font-style', 'font-variant', 'font-weight',
        'gap', 'grid', 'grid-area', 'grid-auto-columns', 'grid-auto-flow', 'grid-auto-rows', 'grid-column', 'grid-column-end', 'grid-column-gap', 'grid-column-start', 'grid-gap', 'grid-row', 'grid-row-end', 'grid-row-gap', 'grid-row-start', 'grid-template', 'grid-template-areas', 'grid-template-columns', 'grid-template-rows',
        'height', 'hyphens', 'isolation', 'justify-content', 'justify-items', 'justify-self', 'left', 'letter-spacing', 'line-height', 'list-style', 'list-style-image', 'list-style-position', 'list-style-type',
        'margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top', 'max-height', 'max-width', 'min-height', 'min-width', 'mix-blend-mode', 'object-fit', 'object-position', 'opacity', 'order', 'outline', 'outline-color', 'outline-offset', 'outline-style', 'outline-width',
        'overflow', 'overflow-wrap', 'overflow-x', 'overflow-y', 'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top', 'perspective', 'place-content', 'place-items', 'place-self', 'pointer-events', 'position', 'quotes', 'resize', 'right', 'row-gap',
        'scroll-behavior', 'table-layout', 'text-align', 'text-decoration', 'text-decoration-color', 'text-decoration-line', 'text-decoration-style', 'text-indent', 'text-overflow', 'text-shadow', 'text-transform', 'top', 'transform', 'transform-origin', 'transform-style', 'transition', 'transition-delay', 'transition-duration', 'transition-property', 'transition-timing-function',
        'unicode-bidi', 'user-select', 'vertical-align', 'visibility', 'white-space', 'width', 'word-break', 'word-spacing', 'word-wrap', 'writing-mode', 'z-index',
    ];
    private const BANNED_IN_VALUE = ['expression(', 'javascript:', 'vbscript:', 'behavior', 'binding', 'image-set(', '-webkit-image-set(', 'src(', 'element(', 'paint(', 'cross-fade(', '@', 'data:text', 'data:application', 'data:image/svg'];

    /** @var list<string> human-readable notes about what was removed (shown to the customer); callers reset it before a run */
    public static array $notes = [];

    /**
     * @param callable(string):bool $urlOk decides whether a url() target is acceptable
     * @return string canonical stylesheet ('' when nothing survives)
     */
    public static function stylesheet(string $css, callable $urlOk): string
    {
        if (strlen($css) > self::MAX_BYTES) {
            self::$notes[] = 'The stylesheet is too large and was removed.';
            return '';
        }
        if (!mb_check_encoding($css, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $css)) {
            self::$notes[] = 'The stylesheet contained invalid characters and was removed.';
            return '';
        }
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';
        $count = 0;
        $out = self::rules($css, $urlOk, 0, $count);
        return $out;
    }

    /** A style="" attribute value → "prop:value;prop:value" (only allowed declarations). */
    public static function declarations(string $s, callable $urlOk): string
    {
        if (strlen($s) > 4000 || !mb_check_encoding($s, 'UTF-8')) {
            return '';
        }
        return self::declList(preg_replace('#/\*.*?\*/#s', '', $s) ?? '', $urlOk);
    }

    private static function rules(string $css, callable $urlOk, int $depth, int &$count): string
    {
        $out = '';
        $i = 0;
        $n = strlen($css);
        while ($i < $n) {
            while ($i < $n && ctype_space($css[$i])) { $i++; }
            if ($i >= $n) { break; }
            // prelude up to "{" or ";"
            $start = $i;
            $paren = 0; $quote = '';
            while ($i < $n) {
                $c = $css[$i];
                if ($quote !== '') { if ($c === $quote) { $quote = ''; } }
                elseif ($c === '"' || $c === "'") { $quote = $c; }
                elseif ($c === '(') { $paren++; }
                elseif ($c === ')') { $paren = max(0, $paren - 1); }
                elseif ($paren === 0 && ($c === '{' || $c === ';' || $c === '}')) { break; }
                $i++;
            }
            $prelude = trim(substr($css, $start, $i - $start));
            if ($i >= $n || $css[$i] === ';' || $css[$i] === '}') {   // at-rule statement (e.g. @import …;) or stray token
                self::$notes[] = 'Some CSS was not understood and was removed.';
                $i++;
                continue;
            }
            // block: find the matching "}"
            $depthBraces = 1; $j = $i + 1; $quote = '';
            while ($j < $n && $depthBraces > 0) {
                $c = $css[$j];
                if ($quote !== '') { if ($c === $quote) { $quote = ''; } }
                elseif ($c === '"' || $c === "'") { $quote = $c; }
                elseif ($c === '{') { $depthBraces++; }
                elseif ($c === '}') { $depthBraces--; }
                $j++;
            }
            if ($depthBraces !== 0) {
                self::$notes[] = 'Unbalanced CSS was removed.';
                break;
            }
            $body = substr($css, $i + 1, $j - $i - 2);
            $i = $j;
            if (++$count > self::MAX_RULES) {
                self::$notes[] = 'Too many CSS rules; the rest were removed.';
                break;
            }
            if ($prelude !== '' && $prelude[0] === '@') {
                if ($depth >= 2) { continue; }
                if (preg_match('/^@(media)\s+([A-Za-z0-9\s:()\-.,\/>=]{1,200})$/i', $prelude, $m)) {
                    $inner = self::rules($body, $urlOk, $depth + 1, $count);
                    if ($inner !== '') { $out .= '@media ' . preg_replace('/\s+/', ' ', trim($m[2])) . '{' . $inner . '}'; }
                } elseif (preg_match('/^@(?:-webkit-)?keyframes\s+([A-Za-z_][A-Za-z0-9_-]{0,60})$/i', $prelude, $m)) {
                    $frames = '';
                    foreach (self::splitRules($body) as [$sel, $decl]) {
                        if (preg_match('/^(?:from|to|\d{1,3}(?:\.\d+)?%)(?:\s*,\s*(?:from|to|\d{1,3}(?:\.\d+)?%))*$/i', trim($sel))) {
                            $d = self::declList($decl, $urlOk);
                            if ($d !== '') { $frames .= preg_replace('/\s+/', '', trim($sel)) . '{' . $d . '}'; }
                        }
                    }
                    if ($frames !== '') { $out .= '@keyframes ' . $m[1] . '{' . $frames . '}'; }
                } else {
                    self::$notes[] = 'An unsupported @-rule was removed.';
                }
                continue;
            }
            $sel = self::selector($prelude);
            if ($sel === null) {
                self::$notes[] = 'A CSS selector was not accepted and its rule was removed.';
                continue;
            }
            $d = self::declList($body, $urlOk);
            if ($d !== '') { $out .= $sel . '{' . $d . '}'; }
        }
        return $out;
    }

    /** @return list<array{0:string,1:string}> flat "selector {declarations}" pairs (for @keyframes bodies) */
    private static function splitRules(string $body): array
    {
        $out = [];
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $body, $m, PREG_SET_ORDER)) {
            foreach ($m as $r) { $out[] = [$r[1], $r[2]]; }
        }
        return $out;
    }

    private static function selector(string $s): ?string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        if ($s === '' || strlen($s) > 400 || !preg_match('/^[A-Za-z0-9_\-\s.#,>+~*:\[\]="\'()^$|%]+$/', $s) || str_contains($s, '</')) {
            return null;
        }
        // balanced brackets / parentheses
        if (substr_count($s, '[') !== substr_count($s, ']') || substr_count($s, '(') !== substr_count($s, ')') || substr_count($s, '"') % 2 || substr_count($s, "'") % 2) {
            return null;
        }
        return $s;
    }

    private static function declList(string $body, callable $urlOk): string
    {
        $out = [];
        foreach (self::splitDecls($body) as $decl) {
            $p = strpos($decl, ':');
            if ($p === false) { continue; }
            $name = strtolower(trim(substr($decl, 0, $p)));
            $value = trim(substr($decl, $p + 1));
            $important = false;
            if (preg_match('/\s*!\s*important\s*$/i', $value)) {
                $important = true;
                $value = trim((string) preg_replace('/\s*!\s*important\s*$/i', '', $value));
            }
            $custom = (bool) preg_match('/^--[a-z0-9_-]{1,60}$/', $name);
            if (!$custom && !in_array($name, self::PROPS, true)) {
                continue;
            }
            if ($value === '' || !self::valueOk($value, $urlOk)) {
                self::$notes[] = 'A CSS value was not accepted and was removed.';
                continue;
            }
            $out[] = $name . ':' . preg_replace('/\s+/', ' ', $value) . ($important ? '!important' : '');
        }
        return implode(';', $out);
    }

    /** @return list<string> */
    private static function splitDecls(string $s): array
    {
        $parts = []; $cur = ''; $paren = 0; $quote = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if ($quote !== '') { if ($c === $quote) { $quote = ''; } }
            elseif ($c === '"' || $c === "'") { $quote = $c; }
            elseif ($c === '(') { $paren++; }
            elseif ($c === ')') { $paren = max(0, $paren - 1); }
            elseif ($c === ';' && $paren === 0) { $parts[] = $cur; $cur = ''; continue; }
            $cur .= $c;
        }
        $parts[] = $cur;
        return array_values(array_filter(array_map('trim', $parts), static fn(string $p) => $p !== ''));
    }

    private static function valueOk(string $v, callable $urlOk): bool
    {
        if (strlen($v) > 1500 || preg_match('/[\\\\<>{}\x00-\x1F\x7F]/', $v) || substr_count($v, '(') !== substr_count($v, ')') || substr_count($v, '"') % 2 || substr_count($v, "'") % 2) {
            return false;
        }
        $rest = $v;
        // every url(...) must point at an allowed target; nothing else may contain "url("
        if (preg_match_all('/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s"\']*))\s*\)/i', $v, $m, PREG_SET_ORDER)) {
            foreach ($m as $u) {
                $target = $u[1] !== '' ? $u[1] : ($u[2] !== '' ? $u[2] : ($u[3] ?? ''));
                if (!$urlOk($target)) { return false; }
                $rest = str_replace($u[0], '', $rest);
            }
        }
        $low = strtolower($rest);
        if (preg_match('/url\s*\(/', $low)) { return false; }
        foreach (self::BANNED_IN_VALUE as $bad) {
            if (str_contains($low, $bad)) { return false; }
        }
        return true;
    }
}
