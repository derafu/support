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

use DateTimeInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Reads the keys of an array (a decoded JSON or YAML, a configuration) as
 * typed values, instead of trusting its shape.
 *
 * A key that is not there, or is `null`, is absent: the reader returns the
 * default (`null` if none). A key that is there with another type is an error
 * that says where (`dependentRequired.name.0`) and what was expected. Nothing
 * is coerced (`dateTime()` is the one type that converts, and its name says so).
 *
 * The array can say where it comes from (`source`: a file, a request, anything
 * that tells whoever reads the error which array it is), and the errors of the
 * readers that are made from it (`reader()`, `readers()`) say it too.
 *
 *     $reader = ArrayReader::from($definition);
 *
 *     $reader->string('title');                     // ?string
 *     $reader->int('minLength', 0);                 // int
 *     $reader->requireString('type');               // string, or an error
 *     $reader->mapOf('dependentRequired', ArrayType::listOf(ArrayType::string()));
 *                                                  // ?array<string, list<string>>
 *
 *     $reader->string('method', 'GET');            // string: it has a default
 *     $reader->onlyKeys('title', 'minLength');     // an error for any other key
 *     $reader->readers('parameters');              // ?list<ArrayReader>
 *
 * Types are described by `ArrayType` and can be composed to any depth.
 */
final readonly class ArrayReader
{
    /**
     * @param array<array-key, mixed> $data The array to read.
     * @param string $path Path of the array inside a bigger one, for the errors.
     * @param string|null $source Where the array comes from (a file, a request,
     * anything that says it), for the errors.
     */
    public function __construct(
        private array $data,
        private string $path = '',
        private ?string $source = null
    ) {
    }

    /**
     * Creates a reader of an array.
     *
     * @param array<array-key, mixed> $data
     * @param string|null $source Where the array comes from, for the errors.
     */
    public static function from(array $data, ?string $source = null): self
    {
        return new self($data, '', $source);
    }

    /**
     * Tells if the key is there and is not `null`.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Reads a key as a type.
     *
     * @template T
     * @template D
     * @param string $key
     * @param ArrayType<T> $type
     * @param D $default What is returned when the key is absent.
     * @return T|D
     * @throws InvalidArgumentException If the value is not of the type.
     */
    public function get(string $key, ArrayType $type, mixed $default = null): mixed
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        return $type->parse($this->data[$key], ArrayType::path($this->path, $key), $this->source);
    }

    /**
     * Reads a key as a type, that must be there.
     *
     * @template T
     * @param string $key
     * @param ArrayType<T> $type
     * @return T
     * @throws InvalidArgumentException If the key is absent or not of the type.
     */
    public function require(string $key, ArrayType $type): mixed
    {
        if (!isset($this->data[$key])) {
            throw $this->source === null
                ? new InvalidArgumentException([
                    'The key "{key}" is required.',
                    'key' => ArrayType::path($this->path, $key),
                ])
                : new InvalidArgumentException([
                    'The key "{key}" of {source} is required.',
                    'key' => ArrayType::path($this->path, $key),
                    'source' => $this->source,
                ]);
        }

        return $type->parse($this->data[$key], ArrayType::path($this->path, $key), $this->source);
    }

    /**
     * Reads a key that is an object as another reader, which reports its
     * errors with the full path.
     *
     * @throws InvalidArgumentException If the value is not an object.
     */
    public function reader(string $key): ?self
    {
        $value = $this->get($key, ArrayType::map());

        return $value === null
            ? null
            : new self($value, ArrayType::path($this->path, $key), $this->source);
    }

    /**
     * Reads a key that is a list of objects as a list of readers, each one
     * reporting its errors with the path of its position (`parameters.2.name`).
     *
     * @return list<self>|null
     * @throws InvalidArgumentException If the value is not a list of objects.
     */
    public function readers(string $key): ?array
    {
        $items = $this->get($key, ArrayType::listOf(ArrayType::map()));

        if ($items === null) {
            return null;
        }

        $path = ArrayType::path($this->path, $key);

        return array_map(
            fn (array $item, int $index): self => new self($item, ArrayType::path($path, $index), $this->source),
            $items,
            array_keys($items)
        );
    }

    /**
     * Says that the array can only have some keys: a key that is not one of
     * them is an error, as a typo in a configuration would be ignored otherwise.
     *
     * @param string ...$allowed The keys that the array can have.
     * @return self The same reader, to go on reading.
     * @throws InvalidArgumentException If the array has another key.
     */
    public function onlyKeys(string ...$allowed): self
    {
        foreach (array_keys($this->data) as $key) {
            if (in_array((string) $key, $allowed, true)) {
                continue;
            }

            throw $this->source === null
                ? new InvalidArgumentException([
                    'The key "{key}" is not allowed; the allowed keys are: {allowed}.',
                    'key' => ArrayType::path($this->path, $key),
                    'allowed' => implode(', ', $allowed),
                ])
                : new InvalidArgumentException([
                    'The key "{key}" of {source} is not allowed; the allowed keys are: {allowed}.',
                    'key' => ArrayType::path($this->path, $key),
                    'source' => $this->source,
                    'allowed' => implode(', ', $allowed),
                ]);
        }

        return $this;
    }

    /**
     * Returns the whole array.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param string|null $default What is returned when the key is absent.
     * @return ($default is null ? string|null : string)
     * @throws InvalidArgumentException If the value is not a string.
     */
    public function string(string $key, ?string $default = null): ?string
    {
        return $this->get($key, ArrayType::string(), $default);
    }

    /**
     * @param int|null $default What is returned when the key is absent.
     * @return ($default is null ? int|null : int)
     * @throws InvalidArgumentException If the value is not an integer.
     */
    public function int(string $key, ?int $default = null): ?int
    {
        return $this->get($key, ArrayType::int(), $default);
    }

    /**
     * @param float|null $default What is returned when the key is absent.
     * @return ($default is null ? float|null : float)
     * @throws InvalidArgumentException If the value is not a float.
     */
    public function float(string $key, ?float $default = null): ?float
    {
        return $this->get($key, ArrayType::float(), $default);
    }

    /**
     * Reads an integer or a float.
     *
     * @param float|int|null $default What is returned when the key is absent.
     * @return ($default is null ? float|int|null : float|int)
     * @throws InvalidArgumentException If the value is not a number.
     */
    public function number(string $key, float|int|null $default = null): float|int|null
    {
        return $this->get($key, ArrayType::number(), $default);
    }

    /**
     * @param bool|null $default What is returned when the key is absent.
     * @return ($default is null ? bool|null : bool)
     * @throws InvalidArgumentException If the value is not a boolean.
     */
    public function bool(string $key, ?bool $default = null): ?bool
    {
        return $this->get($key, ArrayType::bool(), $default);
    }

    /**
     * Reads a string that is not empty, nor only spaces.
     *
     * @return non-empty-string|null
     * @throws InvalidArgumentException If the value is not that string.
     */
    public function nonEmptyString(string $key): ?string
    {
        return $this->get($key, ArrayType::nonEmptyString());
    }

    /**
     * Reads a date (see `ArrayType::dateTime()`).
     *
     * @throws InvalidArgumentException If the value is not a date.
     */
    public function dateTime(string $key): ?DateTimeInterface
    {
        return $this->get($key, ArrayType::dateTime());
    }

    /**
     * @return array<array-key, mixed>|null
     * @throws InvalidArgumentException If the value is not an array.
     */
    public function array(string $key): ?array
    {
        return $this->get($key, ArrayType::array());
    }

    /**
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException If the value is not an object.
     */
    public function map(string $key): ?array
    {
        return $this->get($key, ArrayType::map());
    }

    /**
     * @return list<mixed>|null
     * @throws InvalidArgumentException If the value is not a list.
     */
    public function list(string $key): ?array
    {
        return $this->get($key, ArrayType::list());
    }

    /**
     * @return list<string>|null
     * @throws InvalidArgumentException If the value is not a list of strings.
     */
    public function stringList(string $key): ?array
    {
        return $this->get($key, ArrayType::listOf(ArrayType::string()));
    }

    /**
     * @return list<int>|null
     * @throws InvalidArgumentException If the value is not a list of integers.
     */
    public function intList(string $key): ?array
    {
        return $this->get($key, ArrayType::listOf(ArrayType::int()));
    }

    /**
     * @return list<bool|float|int|string>|null
     * @throws InvalidArgumentException If the value is not a list of scalars.
     */
    public function scalarList(string $key): ?array
    {
        return $this->get($key, ArrayType::listOf(ArrayType::scalar()));
    }

    /**
     * Reads a list whose items are all of a type.
     *
     * @template T
     * @param ArrayType<T> $item
     * @return list<T>|null
     * @throws InvalidArgumentException If the value is not that list.
     */
    public function listOf(string $key, ArrayType $item): ?array
    {
        return $this->get($key, ArrayType::listOf($item));
    }

    /**
     * Reads an object whose values are all of a type.
     *
     * @template T
     * @param ArrayType<T> $item
     * @return array<string, T>|null
     * @throws InvalidArgumentException If the value is not that object.
     */
    public function mapOf(string $key, ArrayType $item): ?array
    {
        return $this->get($key, ArrayType::mapOf($item));
    }

    /**
     * Reads a required value (see `string()`).
     *
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireString(string $key): string
    {
        return $this->require($key, ArrayType::string());
    }

    /**
     * Reads a required value (see `int()`).
     *
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireInt(string $key): int
    {
        return $this->require($key, ArrayType::int());
    }

    /**
     * Reads a required value (see `float()`).
     *
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireFloat(string $key): float
    {
        return $this->require($key, ArrayType::float());
    }

    /**
     * Reads a required value (see `number()`).
     *
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireNumber(string $key): float|int
    {
        return $this->require($key, ArrayType::number());
    }

    /**
     * Reads a required value (see `bool()`).
     *
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireBool(string $key): bool
    {
        return $this->require($key, ArrayType::bool());
    }

    /**
     * Reads a required value (see `nonEmptyString()`).
     *
     * @return non-empty-string
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireNonEmptyString(string $key): string
    {
        return $this->require($key, ArrayType::nonEmptyString());
    }

    /**
     * Reads a required value (see `dateTime()`).
     *
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireDateTime(string $key): DateTimeInterface
    {
        return $this->require($key, ArrayType::dateTime());
    }

    /**
     * Reads a required value (see `array()`).
     *
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireArray(string $key): array
    {
        return $this->require($key, ArrayType::array());
    }

    /**
     * Reads a required value (see `map()`).
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireMap(string $key): array
    {
        return $this->require($key, ArrayType::map());
    }

    /**
     * Reads a required value (see `list()`).
     *
     * @return list<mixed>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireList(string $key): array
    {
        return $this->require($key, ArrayType::list());
    }

    /**
     * Reads a required value (see `stringList()`).
     *
     * @return list<string>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireStringList(string $key): array
    {
        return $this->require($key, ArrayType::listOf(ArrayType::string()));
    }

    /**
     * Reads a required value (see `intList()`).
     *
     * @return list<int>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireIntList(string $key): array
    {
        return $this->require($key, ArrayType::listOf(ArrayType::int()));
    }

    /**
     * Reads a required value (see `scalarList()`).
     *
     * @return list<bool|float|int|string>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireScalarList(string $key): array
    {
        return $this->require($key, ArrayType::listOf(ArrayType::scalar()));
    }

    /**
     * Reads a required value (see `listOf()`).
     *
     * @template T
     * @param ArrayType<T> $item
     * @return list<T>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireListOf(string $key, ArrayType $item): array
    {
        return $this->require($key, ArrayType::listOf($item));
    }

    /**
     * Reads a required value (see `mapOf()`).
     *
     * @template T
     * @param ArrayType<T> $item
     * @return array<string, T>
     * @throws InvalidArgumentException If the key is absent, null, or has an invalid type.
     */
    public function requireMapOf(string $key, ArrayType $item): array
    {
        return $this->require($key, ArrayType::mapOf($item));
    }

    /**
     * Reads a required object as a reader, preserving its path and source.
     *
     * @throws InvalidArgumentException If the key is absent, null, or is not an object.
     */
    public function requireReader(string $key): self
    {
        $value = $this->require($key, ArrayType::map());

        return new self($value, ArrayType::path($this->path, $key), $this->source);
    }

    /**
     * Reads a required list of objects as readers, preserving their paths and source.
     *
     * @return list<self>
     * @throws InvalidArgumentException If the key is absent, null, or is not a list of objects.
     */
    public function requireReaders(string $key): array
    {
        $items = $this->require($key, ArrayType::listOf(ArrayType::map()));
        $path = ArrayType::path($this->path, $key);

        return array_map(
            fn (array $item, int $index): self => new self($item, ArrayType::path($path, $index), $this->source),
            $items,
            array_keys($items)
        );
    }
}
