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

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Reads the keys of an array (a decoded JSON or YAML, a configuration) as
 * typed values, instead of trusting its shape.
 *
 * A key that is not there, or is `null`, is absent: the reader returns the
 * default (`null` if none). A key that is there with another type is an error
 * that says where (`dependentRequired.name.0`) and what was expected. Nothing
 * is coerced.
 *
 *     $reader = ArrayReader::from($definition);
 *
 *     $reader->string('title');                     // ?string
 *     $reader->int('minLength', 0);                 // int|null
 *     $reader->require('type', ArrayType::string()); // string, or an error
 *     $reader->mapOf('dependentRequired', ArrayType::listOf(ArrayType::string()));
 *                                                  // ?array<string, list<string>>
 *
 * Types are described by `ArrayType` and can be composed to any depth.
 */
final readonly class ArrayReader
{
    /**
     * @param array<array-key, mixed> $data The array to read.
     * @param string $path Path of the array inside a bigger one, for the errors.
     */
    public function __construct(
        private array $data,
        private string $path = ''
    ) {
    }

    /**
     * Creates a reader of an array.
     *
     * @param array<array-key, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self($data);
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

        return $type->parse($this->data[$key], ArrayType::path($this->path, $key));
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
            throw new InvalidArgumentException([
                'The key "{key}" is required.',
                'key' => ArrayType::path($this->path, $key),
            ]);
        }

        return $type->parse($this->data[$key], ArrayType::path($this->path, $key));
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
            : new self($value, ArrayType::path($this->path, $key));
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
     * @throws InvalidArgumentException If the value is not a string.
     */
    public function string(string $key, ?string $default = null): ?string
    {
        return $this->get($key, ArrayType::string(), $default);
    }

    /**
     * @throws InvalidArgumentException If the value is not an integer.
     */
    public function int(string $key, ?int $default = null): ?int
    {
        return $this->get($key, ArrayType::int(), $default);
    }

    /**
     * @throws InvalidArgumentException If the value is not a float.
     */
    public function float(string $key, ?float $default = null): ?float
    {
        return $this->get($key, ArrayType::float(), $default);
    }

    /**
     * Reads an integer or a float.
     *
     * @throws InvalidArgumentException If the value is not a number.
     */
    public function number(string $key, float|int|null $default = null): float|int|null
    {
        return $this->get($key, ArrayType::number(), $default);
    }

    /**
     * @throws InvalidArgumentException If the value is not a boolean.
     */
    public function bool(string $key, ?bool $default = null): ?bool
    {
        return $this->get($key, ArrayType::bool(), $default);
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
}
