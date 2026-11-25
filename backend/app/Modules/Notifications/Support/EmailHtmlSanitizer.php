<?php

namespace App\Modules\Notifications\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitiser for e-mail template bodies written in the admin editor:
 * formatting tags only, links/images restricted to http(s)/mailto URLs or a
 * `{{ variable }}`, no attributes besides href/src/alt/title/colspan/rowspan.
 * Disallowed elements are unwrapped (text kept); script/style/iframe… are dropped.
 */
final class EmailHtmlSanitizer
{
    /**
     * Tag => allowed attributes.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'a' => ['href', 'title'], 'img' => ['src', 'alt', 'title'], 'hr' => [], 'span' => [], 'div' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /**
     * Removed together with their content.
     *
     * @var list<string>
     */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base', 'head', 'title', 'template', 'noscript'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><div id="nogrgr-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('nogrgr-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::walk($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        // DOMDocument URL-encodes attribute values; restore {{ variable }} links.
        $output = (string) preg_replace_callback('/(href|src)="%7B%7B(?:%20)*([a-z0-9_.]+)(?:%20)*%7D%7D"/i', static fn (array $match): string => $match[1].'="{{ '.$match[2].' }}"', $output);

        return trim($output);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);

                if (! in_array($name, self::ALLOWED[$tag], true) || (in_array($name, ['href', 'src'], true) && ! self::safeUrl($attribute->nodeValue ?? '', $name === 'href'))) {
                    $child->removeAttribute($attribute->nodeName);
                }
            }
        }
    }

    private static function safeUrl(string $url, bool $allowMailto): bool
    {
        $url = trim($url);

        if (preg_match('/^\{\{\s*[a-z_.]+\s*\}\}$/i', $url) === 1) {
            return true;
        }

        return preg_match($allowMailto ? '#^(https?://|mailto:)#i' : '#^https?://#i', $url) === 1;
    }
}
