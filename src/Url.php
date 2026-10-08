<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Support;

/**
 * Utilities to work with the paths of URLs.
 *
 * A path can be written in many ways that mean the same thing for whoever reads
 * it: `/api//index`, `/api/./index`, `/api/%69ndex` and `/api/index/` are
 * `/api/index`. When a component decides by the text of the path (a rule that
 * protects `/api/index`) and another one reads its meaning (a router, a
 * program that serves files), they must agree on which is the path, or what the
 * first one does not recognize gets to the second one. These functions give one
 * canonical form for each path, so every component can use the same.
 *
 * They are pure and they work on a **path**, not on a whole URL: a `?` or a `#`
 * in it is a character of the path, as any other. A path that has no safe
 * canonical form (it climbs a directory, it has an encoded separator, a control
 * character or an escape that is not valid) is a question that has an answer:
 * the functions say `null` or `false`, they do not throw.
 */
final class Url
{
    /**
     * Gives the canonical form of a path.
     *
     * The canonical form:
     *
     *   - starts with `/`, and has no empty segments (`//`), no `.` segments and
     *     no `/` at the end (except for the root, `/`);
     *   - has the escapes of the characters that need no escape (letters, numbers
     *     and `-`, `.`, `_`, `~`) decoded (`%69` is `i`), and the hexadecimal
     *     digits of the rest in capitals (`%c3%b1` is `%C3%B1`), as RFC 3986
     *     says in section 6.2.2;
     *   - keeps the case of the letters, and everything else as it is (`%20`
     *     stays encoded and `%25` is not decoded again, so `%252F` is not `/`).
     *
     * It has no safe form, and the result is `null`, if the path:
     *
     *   - has a `..` segment (also as `%2e%2e`);
     *   - has a separator hidden in an escape (`%2F`, `%5C`) or a backslash: a
     *     component that decodes them would see more segments than this one;
     *   - has a control character (also as `%00`), the null byte among them;
     *   - has an escape that is not valid (`%zz`, `%4`, a `%` at the end).
     *
     * @param string $path The path.
     * @return string|null The canonical path, or null if it has no safe form.
     */
    public static function normalizePath(string $path): ?string
    {
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $path)) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = self::normalizeSegment($segment);
            if ($segment === null || $segment === '..') {
                return null;
            }

            if ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }

        return '/' . implode('/', $segments);
    }

    /**
     * Checks that a path is in its canonical form (see `normalizePath()`).
     *
     * @param string $path The path.
     * @return bool True if it is the canonical form of itself. False if it is
     * not, or if it has no safe form.
     */
    public static function isNormalizedPath(string $path): bool
    {
        return self::normalizePath($path) === $path;
    }

    /**
     * Gives the segments of the canonical form of a path.
     *
     * @param string $path The path.
     * @return list<string>|null The segments (none for the root), or null if the
     * path has no safe form.
     */
    public static function pathSegments(string $path): ?array
    {
        $path = self::normalizePath($path);
        if ($path === null) {
            return null;
        }

        return $path === '/' ? [] : explode('/', substr($path, 1));
    }

    /**
     * Checks that a path is the prefix or is below it, by segments: `/api` is
     * the prefix of `/api`, `/api/index` and `/api/index/x`, and not of
     * `/apiary`. Both are compared in their canonical form, so the way a path is
     * written does not matter. The root, `/`, is the prefix of every path.
     *
     * @param string $path The path.
     * @param string $prefix The prefix.
     * @param bool $caseSensitive Whether `/API` is not `/api`. It is for most
     * servers, but not for the ones that read files from a file system that does
     * not tell them apart: a rule that protects must not depend on it.
     * @return bool True if the path is the prefix or is below it. False if it is
     * not, or if the path or the prefix has no safe form.
     */
    public static function pathStartsWith(string $path, string $prefix, bool $caseSensitive = true): bool
    {
        $path = self::pathSegments($path);
        $prefix = self::pathSegments($prefix);
        if ($path === null || $prefix === null || count($prefix) > count($path)) {
            return false;
        }

        foreach ($prefix as $index => $segment) {
            $same = $caseSensitive
                ? $segment === $path[$index]
                : mb_strtolower($segment, 'UTF-8') === mb_strtolower($path[$index], 'UTF-8')
            ;
            if (!$same) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalizes a segment: decodes the escapes of the characters that need no
     * escape and writes the others in capitals.
     *
     * @return string|null The segment, or null if it has an escape that is not
     * valid or that hides a separator or a control character.
     */
    private static function normalizeSegment(string $segment): ?string
    {
        // Every % is the start of an escape.
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $segment)) {
            return null;
        }

        $normalized = preg_replace_callback(
            '/%([0-9A-Fa-f]{2})/',
            static function (array $match): string {
                $byte = hexdec($match[1]);

                // A separator or a control character that is hidden.
                if ($byte < 0x20 || $byte === 0x7f || $byte === 0x2f || $byte === 0x5c) {
                    return "\0";
                }

                // Letters, numbers and - . _ ~
                if (
                    ($byte >= 0x30 && $byte <= 0x39)
                    || ($byte >= 0x41 && $byte <= 0x5a)
                    || ($byte >= 0x61 && $byte <= 0x7a)
                    || in_array($byte, [0x2d, 0x2e, 0x5f, 0x7e], true)
                ) {
                    return chr((int) $byte);
                }

                return '%' . strtoupper($match[1]);
            },
            $segment
        );

        // The marker of what is not allowed (the raw path was already checked, so
        // it can only come from an escape).
        return str_contains((string) $normalized, "\0") ? null : $normalized;
    }
}
