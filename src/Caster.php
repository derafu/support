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
 * Value casting utility class.
 *
 * Provides functionality to cast values to the most appropriate scalar type.
 */
final class Caster
{
    /**
     * Casts a mixed value to the most appropriate scalar type.
     *
     * @param mixed $value The value to cast.
     * @return int|float|bool|string|null The casted value.
     */
    public static function cast(mixed $value): int|float|bool|string|null
    {
        // Null is returned as is.
        if (is_null($value)) {
            return null;
        }

        // Booleans are returned as is.
        if (is_bool($value)) {
            return $value;
        }

        // Integers and floats are returned as is.
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        // Arrays, objects, resources, etc. → force to string.
        if (!is_string($value)) {
            return (string) $value;
        }

        // Parse string to the most appropriate scalar type.
        return self::parseString($value);
    }

    /**
     * Casts a value to the most appropriate scalar type and returns the type.
     *
     * @param mixed $value The value to cast.
     * @return array{value: int|float|bool|string|null, type: string} The casted
     * value and type.
     */
    public static function castWithType(mixed $value): array
    {
        $casted = self::cast($value);

        return [
            'value' => $casted,
            'type'  => gettype($casted),
        ];
    }

    /**
     * Parses a string to the most appropriate scalar type.
     *
     * @param string $value The string to parse.
     * @return int|float|bool|string|null The parsed value.
     */
    private static function parseString(string $value): int|float|bool|string|null
    {
        $trimmed = trim($value);

        // Null literal.
        if (self::isNullLiteral($trimmed)) {
            return null;
        }

        // Boolean literal.
        $bool = self::parseBool($trimmed);
        if ($bool !== null) {
            return $bool;
        }

        // Integer (includes negative and underscore notation: 1_000).
        if (self::isInteger($trimmed)) {
            return (int) str_replace('_', '', $trimmed);
        }

        // Float (includes scientific notation: 1.5e3).
        if (self::isFloat($trimmed)) {
            return (float) str_replace('_', '', $trimmed);
        }

        return $value; // Remains as string.
    }

    /**
     * Checks if a string is a null literal.
     *
     * @param string $v The string to check.
     * @return bool True if the string is a null literal.
     */
    private static function isNullLiteral(string $v): bool
    {
        return in_array(strtolower($v), ['null', 'nil', ''], true);
    }

    /**
     * Parses a string to a boolean.
     *
     * @param string $v The string to parse.
     * @return bool|null The parsed boolean or null if not a boolean literal.
     */
    private static function parseBool(string $v): ?bool
    {
        return match (strtolower($v)) {
            'true',  'yes', 'on',  '1' => true,
            'false', 'no',  'off', '0' => false,
            default                    => null,
        };
    }

    /**
     * Checks if a string is an integer.
     *
     * @param string $v The string to check.
     * @return bool True if the string is an integer.
     */
    private static function isInteger(string $v): bool
    {
        // Optional sign + digits (with _ as visual separator).
        return (bool) preg_match('/^[+-]?[0-9][0-9_]*$/', $v);
    }

    /**
     * Checks if a string is a float.
     *
     * @param string $v The string to check.
     * @return bool True if the string is a float.
     */
    private static function isFloat(string $v): bool
    {
        // Covers: 3.14 | .5 | 3. | 1_000.50 | 1.5e3 | -2.8E-10.
        return (bool) preg_match(
            '/^[+-]?(?:[0-9][0-9_]*\.?[0-9_]*|[0-9_]*\.[0-9][0-9_]*)(?:[eE][+-]?[0-9]+)?$/',
            $v
        );
    }
}
