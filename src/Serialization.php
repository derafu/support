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

use ReflectionReference;
use Serializable;
use Throwable;

/**
 * Serialization utilities.
 *
 * Provides methods to work with PHP serialization.
 */
final class Serialization
{
    /**
     * Determines if a value can be serialized.
     *
     * It answers what PHP can do. A scalar and `null` can be serialized. An
     * array or an object can, if PHP can serialize it **and** what it has inside
     * can be too. These are not serializable:
     *
     *   - Resources (open or closed): PHP would write them as `0`, so what is
     *     read back is not what was serialized.
     *   - Anything that PHP does not allow to serialize: closures, generators,
     *     anonymous classes, reflections, and the objects whose class forbids
     *     it or whose `__serialize()` or `__sleep()` fails.
     *   - An array or an object that has any of them inside (in a property, at
     *     any depth). For an object that decides by itself what is serialized,
     *     what is searched is what it gives: the result of `__serialize()`. The
     *     ones that use `__sleep()` or `Serializable` are not searched, only
     *     PHP is asked.
     *
     * Circular references are fine, PHP serializes them.
     *
     * @param mixed $value Value to check.
     * @return bool True if the value can be serialized.
     */
    public static function isSerializable(mixed $value): bool
    {
        $seenObjects = [];
        $seenReferences = [];

        return self::canBeSerialized($value, $seenObjects, $seenReferences);
    }

    /**
     * Checks a value and what it has inside.
     *
     * @param mixed $value The value.
     * @param array<int, true> $seenObjects The objects that were already looked at.
     * @param array<int, true> $seenReferences The references of arrays that were
     * already looked at.
     * @return bool True if it can be serialized.
     */
    private static function canBeSerialized(mixed $value, array &$seenObjects, array &$seenReferences): bool
    {
        // An open resource, or a closed one (that `is_resource()` says it is not).
        if (is_resource($value) || gettype($value) === 'resource (closed)') {
            return false;
        }

        if (is_array($value)) {
            foreach (array_keys($value) as $key) {
                // A reference to an array that contains it would be looked at
                // forever.
                $reference = ReflectionReference::fromArrayElement($value, $key);
                if ($reference !== null) {
                    if (isset($seenReferences[(int) $reference->getId()])) {
                        continue;
                    }
                    $seenReferences[(int) $reference->getId()] = true;
                }

                if (!self::canBeSerialized($value[$key], $seenObjects, $seenReferences)) {
                    return false;
                }
            }

            return true;
        }

        if (!is_object($value)) {
            return true;
        }

        $id = spl_object_id($value);
        if (isset($seenObjects[$id])) {
            return true;
        }
        $seenObjects[$id] = true;

        if (!self::phpSerializes($value)) {
            return false;
        }

        // An object that says by itself what is serialized: what PHP serializes
        // is what `__serialize()` gives, so that is what is searched (the
        // properties of the object are not part of it). The ones that use
        // `__sleep()` or `Serializable` are not searched, PHP was already asked.
        if (method_exists($value, '__serialize')) {
            return self::canBeSerialized($value->__serialize(), $seenObjects, $seenReferences);
        }

        if (method_exists($value, '__sleep') || $value instanceof Serializable) {
            return true;
        }

        // The properties of any visibility.
        foreach ((array) $value as $property) {
            if (!self::canBeSerialized($property, $seenObjects, $seenReferences)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Asks PHP to serialize the object.
     *
     * A warning or a notice of PHP (the ones of `__sleep()` that names a property
     * that does not exist, for example) is a failure.
     *
     * @param object $object The object.
     * @return bool True if PHP serialized it.
     */
    private static function phpSerializes(object $object): bool
    {
        $warned = false;
        set_error_handler(static function () use (&$warned): bool {
            $warned = true;

            return true;
        });

        try {
            serialize($object);
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }

        return !$warned;
    }

    /**
     * Determines if data is serialized.
     *
     * @param mixed $data Data to check.
     * @return bool True if the data is serialized.
     */
    public static function isSerialized(mixed $data): bool
    {
        if (!is_string($data)) {
            return false;
        }

        if ($data === 'N;') {
            return true;
        }

        if (preg_match('/^([adObis]):/', $data, $matches)) {
            switch ($matches[1]) {
                case 'a':
                case 'O':
                    return (bool)preg_match("/^{$matches[1]}:[0-9]+:/s", $data);
                case 's':
                    return (bool)preg_match("/^{$matches[1]}:[0-9]+:\".*\";$/s", $data);
                case 'b':
                case 'i':
                case 'd':
                    return (bool)preg_match("/^{$matches[1]}:[0-9.E-]+;$/", $data);
            }
        }

        return false;
    }
}
