<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Support;

/**
 * Encoding utilities.
 *
 * Provides functionality to convert strings between UTF-8 and ISO-8859-1.
 */
final class Encoding
{
    /**
     * Converts a string (or recursively an array/object) from UTF-8 to ISO-8859-1.
     *
     * Only converts strings that are actually UTF-8 encoded.
     *
     * @param string|array|object $input
     * @return string|array|object
     */
    public static function utf8decode(string|array|object $input): string|array|object
    {
        if (is_string($input)) {
            if (empty($input) || !mb_detect_encoding($input, 'UTF-8', true)) {
                return $input;
            }

            $result = mb_convert_encoding($input, 'ISO-8859-1', 'UTF-8');

            // $result always is a string. Maybe just return $result in the future.
            return $result ?: $input;
        }

        if (is_array($input)) {
            return array_map([self::class, 'utf8decode'], $input);
        }

        foreach ($input as $key => $value) {
            $input->$key = self::utf8decode($value);
        }
        return $input;
    }

    /**
     * Converts a string (or recursively an array/object) from ISO-8859-1 to UTF-8.
     *
     * Only converts strings that are actually ISO-8859-1 encoded.
     *
     * @param string|array|object $input
     * @return string|array|object
     */
    public static function utf8encode(string|array|object $input): string|array|object
    {
        if (is_string($input)) {
            if (empty($input) || !mb_detect_encoding($input, 'ISO-8859-1', true)) {
                return $input;
            }

            $result = mb_convert_encoding($input, 'UTF-8', 'ISO-8859-1');

            // $result always is a string. Maybe just return $result in the future.
            return $result ?: $input;
        }

        if (is_array($input)) {
            return array_map([self::class, 'utf8encode'], $input);
        }

        foreach ($input as $key => $value) {
            $input->$key = self::utf8encode($value);
        }
        return $input;
    }
}
