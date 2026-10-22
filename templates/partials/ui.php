<?php
/**
 * Concept UI helpers (buttons with text-roll + arrow disc). Included by layout and pages; defined once.
 * Labels are always escaped; the hover copy is aria-hidden so assistive tech reads the label once.
 */
if (!function_exists('ui_attrs')) {
    function ui_attrs(array $attr): string
    {
        $out = '';
        foreach ($attr as $k => $v) {
            if ($v === true) { $out .= ' ' . e($k); }
            elseif ($v !== false && $v !== null) { $out .= ' ' . e($k) . '="' . e($v) . '"'; }
        }
        return $out;
    }

    function ui_btn_inner(string $label, bool $disc, string $sr = ''): string
    {
        if ($disc) { $label = rtrim((string) preg_replace('/\s*[→›»]\s*$/u', '', $label)); }   // the disc already carries the arrow
        $html = '<span class="btn__roll"><span>' . e($label) . '</span><span aria-hidden="true">' . e($label) . '</span></span>';
        if ($sr !== '') { $html .= '<span class="sr-only">: ' . e($sr) . '</span>'; }
        if ($disc) {
            $html .= '<span class="btn__disc" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2.5" y1="7" x2="11.5" y2="7"/><polyline points="7.5 3 11.5 7 7.5 11"/></svg></span>';
        }
        return $html;
    }

    /** @param string $variant space separated: accent | ghost | light | sm | plain | block | .raw-class */
    function ui_cls(string $variant): string
    {
        $c = 'btn';
        foreach (array_filter(explode(' ', $variant)) as $v) { $c .= $v[0] === '.' ? ' ' . substr($v, 1) : ' btn--' . $v; }
        return $c;
    }

    function ui_link(string $href, string $label, string $variant = '', array $attr = [], string $sr = ''): string
    {
        return '<a class="' . ui_cls($variant) . '" href="' . e($href) . '"' . ui_attrs($attr) . '>' . ui_btn_inner($label, !str_contains(' ' . $variant . ' ', ' plain '), $sr) . '</a>';
    }

    function ui_button(string $label, string $variant = '', array $attr = []): string
    {
        $attr += ['type' => 'button'];
        return '<button class="' . ui_cls($variant) . '"' . ui_attrs($attr) . '>' . ui_btn_inner($label, !str_contains(' ' . $variant . ' ', ' plain ')) . '</button>';
    }

    /** Section eyebrow: decorative index + label (label comes from existing copy). */
    function ui_eyebrow(string $n, string $label): string
    {
        return '<div class="eyebrow"><span class="eyebrow__n" aria-hidden="true">' . e($n) . '</span><span class="eyebrow__label">' . e($label) . '</span></div>';
    }
}
