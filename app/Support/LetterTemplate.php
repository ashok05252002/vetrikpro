<?php

namespace App\Support;

/**
 * The small text format every letter template uses: {placeholders} filled in
 * from a list of values, blank lines between paragraphs, and **text** for
 * bold. Shared by the offer, welcome, internship and promotion letters.
 */
final class LetterTemplate
{
    /**
     * @param  array<string, string>  $values
     */
    public static function fill(string $text, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $values[$m[1]] ?? $m[0], $text);
    }

    /**
     * Blank lines separate paragraphs, single line breaks are kept, and
     * **text** is bold. Everything is escaped before any markup is added, so
     * nothing in the template or an employee's name can inject HTML.
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public static function paragraphs(string $body, array $values): array
    {
        $blocks = preg_split('/\R\s*\R/', trim($body)) ?: [];

        return array_values(array_filter(array_map(function (string $block) use ($values) {
            $html = e(self::fill(trim($block), $values));
            $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

            return nl2br($html, false);
        }, $blocks)));
    }

    /**
     * Placeholders in a template that are not in the allowed list, so a typo
     * like {joinig_date} is caught when saving, not printed on a letter.
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function unknownPlaceholders(string $text, array $allowed): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $m);

        return array_values(array_unique(array_diff($m[1], $allowed)));
    }
}
