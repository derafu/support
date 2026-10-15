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

use Closure;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * A type that a value read from an array can be checked against.
 *
 * Types do not coerce: `"3"` is not an `int`. They are built from the simple
 * ones (`string()`, `int()`, `map()`, ...) and composed with `listOf()`,
 * `mapOf()`, `oneOf()` and `nullable()`, and every composition keeps the
 * generic type that `ArrayReader` (and PHPStan) will know:
 *
 *     ArrayType::mapOf(ArrayType::listOf(ArrayType::string()));
 *     // ArrayType<array<string, list<string>>>
 *
 * A custom type is built with the constructor, throwing `mismatch()` when the
 * value is not valid.
 *
 * @template-covariant T The type of the values it accepts.
 */
final readonly class ArrayType
{
    /**
     * @param string $name Name of the type, as reported in the errors.
     * @param Closure(mixed, string): T $parse Receives the value and its path,
     * returns the value as `T` or throws what `mismatch()` creates.
     */
    public function __construct(
        public string $name,
        private Closure $parse
    ) {
    }

    /**
     * Checks a value against the type.
     *
     * @param mixed $value The value to check.
     * @param string $path Path of the value, for the error ("a.b.0").
     * @return T The value.
     * @throws InvalidArgumentException If the value is not of the type.
     */
    public function parse(mixed $value, string $path = ''): mixed
    {
        return ($this->parse)($value, $path);
    }

    /**
     * Creates the error of a value that is not of the expected type.
     *
     * @param string $path Path of the value.
     * @param string $expected Name of the expected type.
     * @param mixed $given The value that was found.
     */
    public static function mismatch(
        string $path,
        string $expected,
        mixed $given
    ): InvalidArgumentException {
        return new InvalidArgumentException([
            'The key "{key}" must be of type {expected}, {given} given.',
            'key' => $path,
            'expected' => $expected,
            'given' => get_debug_type($given),
        ]);
    }

    /**
     * Joins a path with the key or index below it.
     *
     * @param string $parent Path of the parent, empty at the root.
     * @param string|int $key Key or index.
     */
    public static function path(string $parent, string|int $key): string
    {
        return $parent === '' ? (string) $key : $parent . '.' . $key;
    }

    /**
     * Any value.
     *
     * @return self<mixed>
     */
    public static function mixed(): self
    {
        return new self('mixed', static fn (mixed $value): mixed => $value);
    }

    /**
     * @return self<string>
     */
    public static function string(): self
    {
        return new self('string', static fn (mixed $value, string $path): string
            => is_string($value) ? $value : throw self::mismatch($path, 'string', $value));
    }

    /**
     * @return self<int>
     */
    public static function int(): self
    {
        return new self('int', static fn (mixed $value, string $path): int
            => is_int($value) ? $value : throw self::mismatch($path, 'int', $value));
    }

    /**
     * @return self<float>
     */
    public static function float(): self
    {
        return new self('float', static fn (mixed $value, string $path): float
            => is_float($value) ? $value : throw self::mismatch($path, 'float', $value));
    }

    /**
     * An integer or a float.
     *
     * @return self<float|int>
     */
    public static function number(): self
    {
        return new self('float|int', static fn (mixed $value, string $path): float|int
            => is_int($value) || is_float($value)
                ? $value
                : throw self::mismatch($path, 'float|int', $value));
    }

    /**
     * @return self<bool>
     */
    public static function bool(): self
    {
        return new self('bool', static fn (mixed $value, string $path): bool
            => is_bool($value) ? $value : throw self::mismatch($path, 'bool', $value));
    }

    /**
     * A boolean, a number or a string.
     *
     * @return self<bool|float|int|string>
     */
    public static function scalar(): self
    {
        return new self('bool|float|int|string', static fn (mixed $value, string $path): bool|float|int|string
            => is_scalar($value)
                ? $value
                : throw self::mismatch($path, 'bool|float|int|string', $value));
    }

    /**
     * Any array, with whatever it has inside.
     *
     * @return self<array<array-key, mixed>>
     */
    public static function array(): self
    {
        return new self('array', static fn (mixed $value, string $path): array
            => is_array($value) ? $value : throw self::mismatch($path, 'array', $value));
    }

    /**
     * An array of keys (what JSON calls an object), empty or not.
     *
     * An array that is a list (`['a', 'b']`) is not a map. The keys that look
     * like integers are integers in PHP, so the type is `array<string, mixed>`
     * only as far as PHP allows.
     *
     * @return self<array<string, mixed>>
     */
    public static function map(): self
    {
        return new self('object', static function (mixed $value, string $path): array {
            if (!is_array($value) || ($value !== [] && array_is_list($value))) {
                throw self::mismatch($path, 'object', $value);
            }

            /** @var array<string, mixed> $value */
            return $value;
        });
    }

    /**
     * An array that is a list, empty or not.
     *
     * @return self<list<mixed>>
     */
    public static function list(): self
    {
        return new self('list', static function (mixed $value, string $path): array {
            if (!is_array($value) || !array_is_list($value)) {
                throw self::mismatch($path, 'list', $value);
            }

            return $value;
        });
    }

    /**
     * A list whose items are all of a type.
     *
     * @template I
     * @param self<I> $item The type of each item.
     * @return self<list<I>>
     */
    public static function listOf(self $item): self
    {
        return new self(
            'list<' . $item->name . '>',
            static function (mixed $value, string $path) use ($item): array {
                if (!is_array($value) || !array_is_list($value)) {
                    throw self::mismatch($path, 'list<' . $item->name . '>', $value);
                }

                return array_map(
                    static fn (mixed $v, int $i): mixed => $item->parse($v, self::path($path, $i)),
                    $value,
                    array_keys($value)
                );
            }
        );
    }

    /**
     * A map whose values are all of a type.
     *
     * @template I
     * @param self<I> $item The type of each value.
     * @return self<array<string, I>>
     */
    public static function mapOf(self $item): self
    {
        return new self(
            'object<' . $item->name . '>',
            static function (mixed $value, string $path) use ($item): array {
                if (!is_array($value) || ($value !== [] && array_is_list($value))) {
                    throw self::mismatch($path, 'object<' . $item->name . '>', $value);
                }

                $result = [];
                foreach ($value as $key => $v) {
                    $result[$key] = $item->parse($v, self::path($path, $key));
                }

                /** @var array<string, I> $result */
                return $result;
            }
        );
    }

    /**
     * A value that is of any of the types. The first one that accepts it wins.
     *
     * @template I
     * @param self<I> $first
     * @param self<I> ...$others
     * @return self<I>
     */
    public static function oneOf(self $first, self ...$others): self
    {
        $types = [$first, ...$others];
        $name = implode('|', array_map(static fn (self $t): string => $t->name, $types));

        return new self(
            $name,
            static function (mixed $value, string $path) use ($types, $name): mixed {
                foreach ($types as $type) {
                    try {
                        return $type->parse($value, $path);
                    } catch (InvalidArgumentException) {
                        // Next type.
                    }
                }

                throw self::mismatch($path, $name, $value);
            }
        );
    }

    /**
     * The same type, that also accepts `null`.
     *
     * @return self<T|null>
     */
    public function nullable(): self
    {
        $type = $this;

        return new self(
            $this->name . '|null',
            static fn (mixed $value, string $path): mixed
                => $value === null ? null : $type->parse($value, $path)
        );
    }
}
