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
 * Checks on a value that was received, as from a form or a request.
 */
final class Value
{
    /**
     * Tells if a value is blank: nothing was received in it.
     *
     * These are the three ways in which an input says that it has nothing: it
     * is missing (`null`), it is a text with no characters (`''`) or it is a
     * list with no elements (`[]`).
     *
     * It is not the `empty()` of PHP: `0`, `0.0`, `'0'` and `false` are values,
     * so they are not blank, and neither is a text of spaces, that has
     * characters.
     *
     * @param mixed $value The value.
     * @return bool `true` for `null`, `''` and `[]`, `false` for any other
     * value.
     */
    public static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
