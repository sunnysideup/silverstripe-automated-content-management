<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Allow-list based sanitiser for HTML (and URLs) that were written by an LLM.
 *
 * Only a handful of tags are kept, nearly all attributes are removed.
 * Everything else is stripped (the text content of harmless tags is kept).
 */
class LlmHtmlSanitiser
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i',
        'h2', 'h3', 'h4', 'h5', 'h6',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'a',
    ];

    private const VOID_TAGS = ['br'];

    /**
     * tags that are removed including everything inside of them.
     */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template', 'noscript',
        'svg', 'math', 'form', 'textarea', 'select', 'option', 'button', 'head', 'title', 'link', 'meta',
        'base', 'audio', 'video', 'canvas', 'xmp', 'plaintext', 'noembed', 'noframes',
    ];

    public static function to_html_field(?string $html): DBHTMLText
    {
        $field = DBHTMLText::create_field('HTMLText', self::sanitise($html));
        // no shortcodes: they could be used to inject content
        $field->setProcessShortcodes(false);

        return $field;
    }

    public static function sanitise(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div>' . $html . '</div></body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $root = $doc->getElementsByTagName('body')->item(0);
        if (! $root) {
            return '';
        }

        return trim(self::render_children($root));
    }

    /**
     * Only http(s) and relative URLs are allowed. Returns an empty string for anything else.
     */
    public static function sanitise_url(?string $url): string
    {
        // browsers ignore whitespace and control characters inside URLs - so must we
        $url = preg_replace('/[\x00-\x20\x7f-\x9f]+/u', '', (string) $url) ?? '';
        if ($url === '') {
            return '';
        }

        if (preg_match('~^https?://[^/\\\\]~i', $url)) {
            return $url;
        }

        // anything with a scheme (javascript:, data:, mailto: etc.), protocol-relative or backslash URLs are out
        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url) || str_starts_with($url, '//') || str_contains($url, '\\')) {
            return '';
        }

        return $url;
    }

    protected static function render_children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::render_node($child);
        }

        return $out;
    }

    protected static function render_node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars((string) $node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            // comments, processing instructions, CDATA, etc.
            return '';
        }

        $tag = strtolower($node->nodeName);
        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            return '';
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            // unwrap: keep the (sanitised) content only
            return self::render_children($node);
        }

        if (in_array($tag, self::VOID_TAGS, true)) {
            return '<' . $tag . '>';
        }

        $attributes = '';
        if ($tag === 'a') {
            $href = self::sanitise_url((string) $node->getAttribute('href'));
            if ($href === '') {
                return self::render_children($node);
            }

            $attributes = ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="nofollow noopener"';
        } elseif ($tag === 'td' || $tag === 'th') {
            foreach (['colspan', 'rowspan'] as $attribute) {
                $value = $node->getAttribute($attribute);
                if ($value !== '' && ctype_digit($value) && (int) $value > 0 && (int) $value <= 100) {
                    $attributes .= ' ' . $attribute . '="' . (int) $value . '"';
                }
            }
        }

        return '<' . $tag . $attributes . '>' . self::render_children($node) . '</' . $tag . '>';
    }
}
