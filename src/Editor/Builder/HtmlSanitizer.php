<?php
declare(strict_types=1);

namespace Pixelite\Editor\Builder;

/**
 * EXPERIMENTAL site builder – the security boundary for customer-authored markup.
 *
 * The page a customer builds is HTML + CSS only. Whatever the browser sends is parsed, filtered through allowlists and RE-SERIALISED by this
 * class (the submitted text is never stored or echoed). Properties of the output:
 *   - only allowlisted elements; raw-text / foreign-content / embedding elements (script, style, svg, math, template, noscript, iframe, object, embed,
 *     textarea-like parser switches, base, link, meta, title …) are removed WITH their content, unknown elements are unwrapped;
 *   - only allowlisted attributes; every value is escaped; no on*, srcdoc, formaction, form action/method, srcset, style except through CssSanitizer;
 *   - links: https/http/mailto/tel/#anchor only; images: this project's own uploaded images or the bundled placeholder only;
 *   - id/class tokens are [A-Za-z0-9_-] only (they end up in the editor UI);
 *   - the result is a FIXPOINT: sanitising it again yields the same bytes. If that cannot be reached the save is refused (mXSS tripwire for the
 *     libxml parser vs. a browser's parser).
 * This is defence in depth: the documents that show the result are additionally served with "sandbox" and script-src 'none'.
 */
final class HtmlSanitizer
{
    public const MAX_BYTES = 300_000;
    public const MAX_NODES = 6000;
    public const MAX_DEPTH = 40;

    private const ALLOWED = [
        'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'button', 'caption', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'i', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'li', 'main', 'mark', 'nav',
        'ol', 'optgroup', 'option', 'p', 'pre', 'q', 's', 'samp', 'section', 'select', 'small', 'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'tr', 'u', 'ul', 'var', 'wbr',
    ];
    private const VOID = ['br', 'col', 'hr', 'img', 'input', 'wbr'];
    /** removed together with everything inside them */
    private const DROP = [
        'script', 'style', 'svg', 'math', 'template', 'noscript', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'link', 'meta', 'title', 'head', 'xmp', 'plaintext', 'noembed', 'noframes',
        'audio', 'video', 'source', 'track', 'canvas', 'map', 'area', 'dialog', 'slot', 'portal', 'param', 'picture', 'select-menu', 'marquee', 'blink', 'listing', 'isindex', 'bgsound', 'keygen', 'menuitem', 'image',
    ];
    private const GLOBAL_ATTRS = ['id', 'class', 'title', 'lang', 'dir', 'role', 'hidden', 'tabindex', 'style'];
    private const TAG_ATTRS = [
        'a' => ['href', 'target'], 'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'button' => ['type', 'name', 'value', 'disabled'],
        'input' => ['type', 'name', 'value', 'placeholder', 'required', 'disabled', 'checked', 'readonly', 'min', 'max', 'step', 'maxlength', 'minlength', 'autocomplete', 'size'],
        'textarea' => ['name', 'rows', 'cols', 'placeholder', 'required', 'disabled', 'maxlength', 'readonly'],
        'select' => ['name', 'multiple', 'required', 'disabled', 'size'], 'option' => ['value', 'selected', 'disabled', 'label'], 'optgroup' => ['label', 'disabled'],
        'label' => ['for'], 'fieldset' => ['disabled'], 'details' => ['open'],
        'td' => ['colspan', 'rowspan', 'scope'], 'th' => ['colspan', 'rowspan', 'scope'], 'col' => ['span'], 'colgroup' => ['span'],
        'ol' => ['start', 'reversed', 'type'], 'li' => ['value'], 'time' => ['datetime'], 'bdo' => [],
    ];
    private const BOOL_ATTRS = ['hidden', 'disabled', 'checked', 'readonly', 'required', 'multiple', 'selected', 'reversed', 'open'];
    private const INPUT_TYPES = ['text', 'email', 'tel', 'url', 'number', 'password', 'search', 'checkbox', 'radio', 'date', 'time', 'range', 'color', 'submit', 'reset', 'button', 'datetime-local', 'month', 'week'];

    /** @var list<string> things that were removed, for the customer */
    public array $notes = [];
    private int $nodes = 0;

    /** @param int|string $media a project id (images live at /editor/projects/<id>/media/…) or the exact URL prefix of the workspace's images (e.g. /builder/w/media) */
    public function __construct(int|string $media)
    {
        $this->mediaPrefix = is_int($media) ? '/editor/projects/' . $media . '/media' : rtrim($media, '/');
    }

    private string $mediaPrefix;

    public static function available(): bool
    {
        return class_exists(\DOMDocument::class) && function_exists('libxml_use_internal_errors') && function_exists('mb_check_encoding');
    }

    /** Can $url be used for an image / url() in this project? Own uploads and the bundled placeholders only. */
    public function imageUrlOk(string $u): bool
    {
        return (bool) preg_match('#^' . preg_quote($this->mediaPrefix, '#') . '/m[a-f0-9]{16}\z#', $u)
            || (bool) preg_match('#^/assets/editor/placeholders/[a-z0-9-]{1,40}\.(?:svg|jpg)\z#', $u);
    }

    /** @return array{html:string,notes:list<string>}|null null = refuse (too big / cannot be made stable) */
    public function body(string $html): ?array
    {
        $this->notes = [];
        if (strlen($html) > self::MAX_BYTES || !mb_check_encoding($html, 'UTF-8')) {
            return null;
        }
        $html = str_replace("\0", '', $html);
        $cur = $html;
        for ($i = 0; $i < 4; $i++) {
            $next = $this->once($cur);
            if ($next === null) {
                return null;
            }
            if ($next === $cur) {
                return ['html' => $next, 'notes' => array_values(array_unique($this->notes))];
            }
            $cur = $next;
        }
        return null;   // never converged: refuse rather than store something a different parser might read differently
    }

    private function once(string $html): ?string
    {
        $this->nodes = 0;
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $ok ? $dom->getElementsByTagName('body')->item(0) : null;
        if ($body === null) {
            return null;
        }
        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $s = $this->node($child, 0);
            if ($s === null) {
                return null;
            }
            $out .= $s;
        }
        return trim($out);
    }

    private function node(\DOMNode $n, int $depth): ?string
    {
        if (++$this->nodes > self::MAX_NODES || $depth > self::MAX_DEPTH) {
            return null;
        }
        if ($n instanceof \DOMText) {
            return $n instanceof \DOMCdataSection ? '' : htmlspecialchars($n->data, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!($n instanceof \DOMElement)) {
            return '';   // comments, processing instructions, entity references …
        }
        $tag = strtolower($n->localName ?? $n->nodeName);
        if (in_array($tag, self::DROP, true)) {
            $this->notes[] = "A <$tag> element was removed.";
            return '';
        }
        $inner = '';
        foreach (iterator_to_array($n->childNodes) as $c) {
            $s = $this->node($c, $depth + 1);
            if ($s === null) {
                return null;
            }
            $inner .= $s;
        }
        if (!in_array($tag, self::ALLOWED, true)) {
            $this->notes[] = "An unsupported <$tag> element was unwrapped.";
            return $inner;   // keep the (already sanitised) children
        }
        $attrs = $this->attributes($n, $tag);
        if (in_array($tag, self::VOID, true)) {
            return '<' . $tag . $attrs . '>';
        }
        if (in_array($tag, ['pre', 'textarea'], true) && str_starts_with($inner, "\n")) {
            $inner = "\n" . $inner;   // browsers drop one leading newline there; keep the original
        }
        return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
    }

    private function attributes(\DOMElement $el, string $tag): string
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);
        $out = [];
        $targetBlank = false;
        foreach (iterator_to_array($el->attributes) as $a) {
            $name = strtolower($a->name);
            $value = $a->value;
            if (str_contains($name, ':') || preg_match('/[^a-z0-9_-]/', $name)) {
                continue;
            }
            $kept = null;
            if (str_starts_with($name, 'aria-') && strlen($name) <= 40) {
                $kept = $this->plain($value, 200);
                if ($kept !== null && preg_match('/[<>`]/', $kept)) { $kept = null; }
            } elseif (str_starts_with($name, 'data-') && strlen($name) <= 44 && !str_starts_with($name, 'data-bs-')) {
                $kept = $this->plain($value, 200);
                if ($kept !== null && preg_match('/[<>`]/', $kept)) { $kept = null; }   // never carry markup-looking text in custom attributes (editor UI reads them)
            } elseif (in_array($name, $allowed, true)) {
                $kept = $this->attr($tag, $name, $value);
                if ($tag === 'a' && $name === 'target' && $kept === '_blank') {
                    $targetBlank = true;
                }
            }
            if ($kept === null) {
                if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'srcset', 'formaction', 'action', 'method', 'xlink:href'], true)) {
                    $this->notes[] = "The \"$name\" attribute is not allowed and was removed.";
                }
                continue;
            }
            $out[$name] = $kept;
        }
        if ($targetBlank) {
            $out['rel'] = 'noopener noreferrer';
        }
        $s = '';
        foreach ($out as $k => $v) {
            $s .= in_array($k, self::BOOL_ATTRS, true) ? ' ' . $k : ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        return $s;
    }

    private function plain(string $v, int $max): ?string
    {
        $v = preg_replace('/[\x00-\x08\x0B-\x1F\x7F\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $v);
        return $v === null || mb_strlen($v) > $max ? null : $v;
    }

    private function attr(string $tag, string $name, string $v): ?string
    {
        $v = trim($v);
        switch ($name) {
            case 'id':
                return preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $v) ? $v : null;
            case 'class':
                $t = preg_split('/\s+/', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($t as $x) { if (!preg_match('/^[A-Za-z0-9_-]{1,64}\z/', $x)) { return null; } }
                return $t && count($t) <= 40 ? implode(' ', $t) : null;
            case 'title': case 'alt': case 'placeholder': case 'label': case 'name': case 'value':
                return $this->plain($v, 300);
            case 'lang':
                return preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,2}\z/', $v) ? $v : null;
            case 'dir':
                return in_array(strtolower($v), ['ltr', 'rtl', 'auto'], true) ? strtolower($v) : null;
            case 'role':
                return preg_match('/^[a-z]{3,20}\z/', $v) ? $v : null;
            case 'tabindex':
                return in_array($v, ['-1', '0'], true) ? $v : null;
            case 'for':
                return preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $v) ? $v : null;
            case 'hidden': case 'disabled': case 'checked': case 'readonly': case 'required': case 'multiple': case 'selected': case 'reversed': case 'open':
                return '';
            case 'style':
                $s = CssSanitizer::declarations($v, fn(string $u): bool => $this->imageUrlOk($u));
                return $s === '' ? null : $s;
            case 'href':
                return $this->linkOk($v) ? $v : null;
            case 'target':
                return in_array($v, ['_blank', '_self'], true) ? $v : null;
            case 'src':
                return $tag === 'img' && $this->imageUrlOk($v) ? $v : null;
            case 'width': case 'height':
                return preg_match('/^\d{1,4}(?:%|px)?\z/', $v) ? $v : null;
            case 'loading':
                return in_array($v, ['lazy', 'eager'], true) ? $v : null;
            case 'type':
                if ($tag === 'button') { return in_array($v, ['button', 'submit', 'reset'], true) ? $v : null; }
                if ($tag === 'input') { return in_array(strtolower($v), self::INPUT_TYPES, true) ? strtolower($v) : null; }
                if ($tag === 'ol') { return in_array($v, ['1', 'a', 'A', 'i', 'I'], true) ? $v : null; }
                return null;
            case 'min': case 'max': case 'step': case 'datetime':
                return preg_match('/^[0-9A-Za-z:.\-+ ]{1,40}\z/', $v) ? $v : null;
            case 'maxlength': case 'minlength': case 'rows': case 'cols': case 'size': case 'colspan': case 'rowspan': case 'span': case 'start': case 'value_n':
                return preg_match('/^\d{1,4}\z/', $v) ? $v : null;
            case 'autocomplete':
                return in_array($v, ['on', 'off'], true) ? $v : null;
            case 'scope':
                return in_array($v, ['row', 'col', 'rowgroup', 'colgroup'], true) ? $v : null;
        }
        return null;
    }

    /** https/http URLs with a real host, mailto:, tel: and in-page #anchors. No javascript:, data:, relative or protocol-relative URLs. */
    public function linkOk(string $s): bool
    {
        if ($s === '' || strlen($s) > 300 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $s) || !mb_check_encoding($s, 'UTF-8')) {
            return false;
        }
        if (preg_match('/^#[A-Za-z0-9_-]{0,64}\z/', $s)) {
            return true;
        }
        if (preg_match('#^https?://#i', $s)) {
            $p = parse_url($s);
            return is_array($p) && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) && (bool) preg_match('/^[A-Za-z0-9.-]+\z/', $p['host']) && str_contains($p['host'], '.');
        }
        if (preg_match('/^mailto:([^?]+)\z/i', $s, $m)) {
            return (bool) filter_var($m[1], FILTER_VALIDATE_EMAIL);
        }
        return (bool) preg_match('/^tel:\+?[0-9]{5,20}\z/', $s);
    }
}
