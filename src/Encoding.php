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

use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use ReflectionProperty;

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
     * An object is converted in place and the same instance is returned. Only
     * its public properties are converted: the protected and private ones are
     * not touched, and an object that is only iterable (`ArrayObject`) is not
     * converted by its elements.
     *
     * @param mixed $input
     * @return ($input is string ? string : mixed) A string if a string was given.
     * @throws LogicException If an object has a public property that is
     * readonly, which can not be converted.
     */
    public static function utf8decode(mixed $input): mixed
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

        if (is_object($input)) {
            return self::convertProperties($input, self::utf8decode(...));
        }

        return $input;
    }

    /**
     * Converts a string (or recursively an array/object) from ISO-8859-1 to UTF-8.
     *
     * Only converts strings that are not already UTF-8: a text that is valid
     * UTF-8 is returned as it is. A text of ISO-8859-1 whose bytes also make a
     * valid UTF-8 sequence (`Ã±`, for example) can not be told from UTF-8, so it
     * is not converted either.
     *
     * An object is converted in place and the same instance is returned. Only
     * its public properties are converted: the protected and private ones are
     * not touched, and an object that is only iterable (`ArrayObject`) is not
     * converted by its elements.
     *
     * @param mixed $input
     * @return ($input is string ? string : mixed) A string if a string was given.
     * @throws LogicException If an object has a public property that is
     * readonly, which can not be converted.
     */
    public static function utf8encode(mixed $input): mixed
    {
        if (is_string($input)) {
            // A text that is already UTF-8 is not converted again: any sequence of
            // bytes is valid ISO-8859-1, so this is the only way to know that it
            // is not (converting it would write `Ã±` instead of `ñ`).
            if (
                empty($input)
                || mb_check_encoding($input, 'UTF-8')
                || !mb_detect_encoding($input, 'ISO-8859-1', true)
            ) {
                return $input;
            }

            $result = mb_convert_encoding($input, 'UTF-8', 'ISO-8859-1');

            // $result always is a string. Maybe just return $result in the future.
            return $result ?: $input;
        }

        if (is_array($input)) {
            return array_map([self::class, 'utf8encode'], $input);
        }

        if (is_object($input)) {
            return self::convertProperties($input, self::utf8encode(...));
        }

        return $input;
    }

    /**
     * Converts in place the public properties of an object.
     *
     * @template T of object
     * @param T $object The object.
     * @param callable(mixed): mixed $convert Converts the value of a property.
     * @return T The same object.
     * @throws LogicException If a public property is readonly.
     */
    private static function convertProperties(object $object, callable $convert): object
    {
        foreach (get_object_vars($object) as $property => $value) {
            if ((new ReflectionProperty($object, (string) $property))->isReadOnly()) {
                throw new LogicException([
                    'Cannot convert the property "{property}" of {class}: it is readonly.',
                    'property' => $property,
                    'class' => $object::class,
                ]);
            }

            $object->$property = $convert($value);
        }

        return $object;
    }
}
