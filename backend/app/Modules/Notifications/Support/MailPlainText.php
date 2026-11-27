<?php

namespace App\Modules\Notifications\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Plain-text alternative of a rendered e-mail (multipart/alternative, decision #124):
 * built from the final MAIL-001 HTML so it always says what the HTML says. Hidden parts
 * (preheader, `display:none`), <head>, <style> and <script> are dropped; blocks become
 * lines, table rows "cell · cell", list items "- item", links "label (url)" (the url
 * alone when the label is the url), images their alt text.
 */
final class MailPlainText
{
    private const BLOCKS = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'tbody', 'thead', 'tfoot', 'tr', 'ul', 'ol', 'li', 'blockquote', 'section', 'article', 'header', 'footer', 'hr', 'center'];

    private const SKIP = ['head', 'style', 'script', 'title', 'meta', 'link', 'noscript'];

    public static function fromHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('body')->item(0) ?? $document->documentElement;
        $text = $root instanceof DOMNode ? self::walk($root) : strip_tags($html);

        return self::tidy($text);
    }

    private static function walk(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return (string) preg_replace('/\s+/u', ' ', $node->wholeText);
        }

        if (! $node instanceof DOMElement) {
            return self::children($node);
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::SKIP, true) || self::hidden($node)) {
            return '';
        }

        return match (true) {
            $tag === 'br' => "\n",
            $tag === 'hr' => "\n----------\n",
            $tag === 'img' => trim((string) $node->getAttribute('alt')) !== '' ? ' '.trim($node->getAttribute('alt')).' ' : '',
            $tag === 'a' => self::link($node),
            $tag === 'li' => "\n- ".trim(self::children($node))."\n",
            $tag === 'tr' => "\n".self::row($node)."\n",
            in_array($tag, ['h1', 'h2', 'h3'], true) => "\n\n".trim(self::children($node))."\n\n",
            in_array($tag, self::BLOCKS, true) => "\n".self::children($node)."\n",
            default => self::children($node),
        };
    }

    private static function children(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= self::walk($child);
        }

        return $text;
    }

    private static function row(DOMElement $row): string
    {
        $cells = [];

        foreach ($row->childNodes as $cell) {
            if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true) && ! self::hidden($cell)) {
                $value = trim((string) preg_replace('/[ \t]+/u', ' ', self::children($cell)));

                if ($value !== '') {
                    $cells[] = $value;
                }
            } elseif ($cell instanceof DOMElement) {
                $cells[] = trim(self::walk($cell));
            }
        }

        $cells = array_values(array_filter($cells, static fn (string $value): bool => $value !== ''));
        $layout = array_filter($cells, static fn (string $value): bool => str_contains($value, "\n")) !== [];

        // Layout tables (cells holding blocks) stack; data rows read "label · value".
        return implode($layout ? "\n\n" : ' · ', $cells);
    }

    private static function link(DOMElement $link): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', self::children($link)));
        $href = trim((string) $link->getAttribute('href'));

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return $label;
        }

        $url = str_starts_with(strtolower($href), 'mailto:') ? substr($href, 7) : $href;

        return $label === '' || $label === $url ? ' '.$url.' ' : $label.' ('.$url.')';
    }

    private static function hidden(DOMElement $element): bool
    {
        $style = strtolower(str_replace(' ', '', (string) $element->getAttribute('style')));

        return str_contains($style, 'display:none') || $element->hasAttribute('hidden') || strtolower((string) $element->getAttribute('aria-hidden')) === 'true' && trim((string) $element->textContent) === '';
    }

    private static function tidy(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/[ \t]+/u', ' ', $line)), explode("\n", $text));
        $text = implode("\n", $lines);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
