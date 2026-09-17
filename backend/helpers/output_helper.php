<?php
/** Encode at the output boundary; stored values must remain unescaped. */
function xss_html($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A complete JavaScript value, safe inside an HTML script element. */
function xss_json($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
}

/** A complete JavaScript value inside a quoted HTML event attribute. */
function xss_attr_json($value): string
{
    return xss_html(xss_json($value));
}

/** For legacy handlers that already supply their own JavaScript string quotes. */
function xss_attr_string($value): string
{
    return xss_html(substr(xss_json((string)$value), 1, -1));
}
