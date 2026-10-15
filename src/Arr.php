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

use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use SimpleXMLElement;
use Stringable;

/**
 * Array manipulation and utility class.
 *
 * This class provides a collection of static methods for array manipulation,
 * including advanced merging, type casting, tree operations, and data structure
 * transformations.
 */
final class Arr
{
    /**
     * Flattens a multi-dimensional array into a single level array with dot
     * notation keys.
     *
     * @param array<array-key, mixed> $array The array to flatten.
     * @param string $prefix The prefix to use for flattened keys.
     * @return array<array-key, mixed> The flattened array.
     */
    public static function dot(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, self::dot($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Converts a flat array with dot notation keys back to a multi-dimensional
     * array.
     *
     * Keys that are integers have no dots, so they are kept as they are. If a
     * key needs a level that already has a value that is not an array, that
     * value is replaced by the new array.
     *
     * @param array<array-key, mixed> $array The flat array with dot notation keys.
     * @return array<array-key, mixed> The multi-dimensional array.
     */
    public static function nested(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (str_contains((string) $key, '.')) {
                $result = self::setAtPath($result, (string) $key, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Gets the value that is in an array at a path of keys.
     *
     * A key whose value is `null` is a value that is there, and `null` is what
     * is returned. The default is returned when the path does not exist: a key
     * that is not there, or a level of the path that is not an array.
     *
     * @param array<array-key, mixed> $array The array.
     * @param string|non-empty-list<string> $path Dot notation path ("a.b.c") or
     * the list of keys.
     * @param mixed $default What is returned if the path does not exist.
     * @return mixed The value at the path, or the default.
     */
    public static function getAtPath(array $array, string|array $path, mixed $default = null): mixed
    {
        $keys = is_string($path) ? explode('.', $path) : $path;
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Sets a value in an array, at a path of keys, creating the levels.
     *
     * The array is not modified: the array with the value set is returned. A
     * level of the path that exists and is not an array is replaced by an
     * array.
     *
     * @param array<array-key, mixed> $array The array.
     * @param string|non-empty-list<string> $path Dot notation path ("a.b.c") or
     * the list of keys.
     * @param mixed $value The value to set at the end of the path.
     * @return array<array-key, mixed> The array with the value set.
     */
    public static function setAtPath(array $array, string|array $path, mixed $value): array
    {
        $keys = is_string($path) ? explode('.', $path) : $path;
        $key = $keys[0];
        $rest = array_slice($keys, 1);

        if ($rest === []) {
            $array[$key] = $value;

            return $array;
        }

        $child = isset($array[$key]) && is_array($array[$key]) ? $array[$key] : [];
        $array[$key] = self::setAtPath($child, $rest, $value);

        return $array;
    }

    /**
     * Recursively casts array values automatically based on their content.
     *
     * Rules applied:
     *
     *   - Trims strings.
     *   - Converts numeric strings to their appropriate type (int or float),
     *     when the number is the one that the string says: a string that does
     *     not fit in an int, that is not finite or that has more digits than its
     *     float can keep stays as the string (trimmed).
     *   - Can replace empty strings with a specified value.
     *
     * With `$preserveFormat` a numeric string is only converted if it is written
     * the way that its number is: `'42'`, `'-7'`, `'0.5'`, `'19.90'` (the zeros
     * at the end of the decimals do not count). The ones that the number would
     * not keep as they are written stay as the string: `'007'`, `'+5'`, `'-0'`,
     * `'.5'`, `'1.'` and the ones with an exponent (`'1e3'`).
     *
     * @param array<array-key, mixed> $array The array to process.
     * @param mixed $emptyValue Value to use for empty strings.
     * @param bool $preserveFormat Convert only the numeric strings that are
     * written like their number.
     * @return array<array-key, mixed> The processed array with cast values.
     */
    public static function cast(array $array, mixed $emptyValue = '', bool $preserveFormat = false): array
    {
        array_walk_recursive($array, function (&$value, $key, $emptyValue) use ($preserveFormat) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = $emptyValue;
                } elseif (is_numeric($value) && (!$preserveFormat || self::isWrittenAsNumber($value))) {
                    $value = self::toNumber($value);
                }
            }
        }, $emptyValue);

        return $array;
    }

    /**
     * Whether a numeric string is written the way that its number is: no sign
     * of plus, no zeros at the beginning, no point at the beginning nor at the
     * end, no exponent, and not `-0`. The zeros at the end of the decimals do
     * not count, because the number is the same.
     *
     * @param string $text A string that is numeric (see `is_numeric()`).
     */
    private static function isWrittenAsNumber(string $text): bool
    {
        return $text !== '-0' && preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $text) === 1;
    }

    /**
     * Gives the number that a numeric string says, or the string if the number
     * would not be the same.
     *
     * An integer is an int if it fits in one. A number with a decimal point is
     * a float, and an exponent gives an int when the number is a whole one that
     * an int can hold and a float if not. A float is only given when it keeps all
     * the digits of the string and the number is finite.
     *
     * @param string $text A string that is numeric (see `is_numeric()`).
     * @return int|float|string The number, or the string.
     */
    private static function toNumber(string $text): int|float|string
    {
        // A whole number, with no point nor exponent: an int if it fits.
        if (preg_match('/^([+-]?)(\d+)$/', $text, $parts) === 1) {
            $integer = filter_var($parts[1] . (ltrim($parts[2], '0') ?: '0'), FILTER_VALIDATE_INT);

            return $integer === false ? $text : $integer;
        }

        $float = (float) $text;
        if (!is_finite($float) || self::digits($text) !== self::digits(self::shortest($float))) {
            return $text;
        }

        // With an exponent and no point (`1e3`), a whole number is an int, if it
        // can hold it without losing anything (the floats are whole numbers up
        // to 2^53 exactly).
        if (!str_contains($text, '.') && floor($float) === $float && abs($float) < 9007199254740992.0) {
            return (int) $float;
        }

        return $float;
    }

    /**
     * The digits that a number says, with no sign, no point, no exponent and no
     * zeros at the beginning nor at the end: they are the ones that must be the
     * same in a number and in its float.
     *
     * @param string $number The number written as text.
     */
    private static function digits(string $number): string
    {
        $mantissa = explode('e', strtolower($number))[0];

        return trim(str_replace(['+', '-', '.'], '', $mantissa), '0');
    }

    /**
     * The number written with the least digits that give the same float back.
     */
    private static function shortest(float $float): string
    {
        for ($precision = 0; $precision < 17; $precision++) {
            $text = sprintf('%.' . $precision . 'e', $float);
            if ((float) $text === $float) {
                break;
            }
        }

        return $text;
    }

    /**
     * Ensures that the value at a specific path in an array is an array with
     * numeric indexes (not an associative array).
     *
     * If any key of the path does not exist, or a level of the path is not an
     * array (so it can not have the next key), the array is left as it is.
     *
     * @param array<array-key, mixed> $array The input array.
     * @param string $path Dot notation path to the target location.
     * @return void
     */
    public static function ensureArrayAtPath(array &$array, string $path): void
    {
        $array = self::wrapAtPath($array, $path);
    }

    /**
     * Wraps in an array the value at a path of keys, if it is not a list.
     *
     * The array is not modified: the array with the value wrapped is returned.
     * If any key of the path does not exist, or a level of the path is not an
     * array, the array is returned as it is.
     *
     * @param array<array-key, mixed> $array The array.
     * @param string|non-empty-list<string> $path Dot notation path ("a.b.c") or
     * the list of keys.
     * @return array<array-key, mixed> The array.
     */
    public static function wrapAtPath(array $array, string|array $path): array
    {
        $keys = is_string($path) ? explode('.', $path) : $path;
        $key = $keys[0];
        if (!isset($array[$key])) {
            return $array;
        }

        $rest = array_slice($keys, 1);
        $value = $array[$key];

        if ($rest !== []) {
            if (is_array($value)) {
                $array[$key] = self::wrapAtPath($value, $rest);
            }

            return $array;
        }

        if (!is_array($value) || ($value !== [] && !isset($value[0]))) {
            $array[$key] = [$value];
        }

        return $array;
    }

    /**
     * Ensure an ID attribute in each array element using the array key.
     *
     * @param array<int|string, array<array-key, mixed>> $data Array of arrays.
     * @param string $idField Name of the field to store the ID.
     * @return array<int|string, array<array-key, mixed>> The modified array with IDs added.
     */
    public static function ensureIdInElements(array $data, string $idField): array
    {
        return array_combine(
            array_keys($data),
            array_map(
                fn ($id, $item) => array_merge(
                    [$idField => $id],
                    $item
                ),
                array_keys($data),
                array_values($data)
            )
        );
    }

    /**
     * Generates all possible subsets of an array with a minimum length.
     *
     * @param list<mixed> $array Input array.
     * @param int $minLength Minimum length for subsets.
     * @return list<list<mixed>> Array of all possible subsets.
     */
    public static function subsets(array $array, int $minLength = 1): array
    {
        $count = count($array);
        $members = pow(2, $count);
        $subsets = [];

        for ($i = 0; $i < $members; $i++) {
            $subset = [];
            for ($j = 0; $j < $count; $j++) {
                if ($i & (1 << $j)) {
                    $subset[] = $array[$j];
                }
            }
            if (count($subset) >= $minLength) {
                $subsets[] = $subset;
            }
        }

        return $subsets;
    }

    /**
     * Creates a hierarchical tree from a flat array using parent references.
     *
     * The **keys** of the array are the identifiers of the items: the field
     * `$parentField` of an item has the key of its parent. The keys are kept in
     * the tree. The field of the parent is removed from each item and the
     * children go in `$childrenField` (an empty array if there are none). An
     * item that does not have the field of the parent is skipped, with
     * everything below it.
     *
     * @param array<array-key, array<array-key, mixed>> $items Array of items, by their identifier.
     * @param string $parentField Name of the field containing the key of the parent.
     * @param string $childrenField Name of the field to store children.
     * @param mixed $parentId Value of the parent field for root level items.
     * @return array<array-key, array<array-key, mixed>> The hierarchical tree.
     */
    public static function toTree(
        array $items,
        string $parentField,
        string $childrenField,
        mixed $parentId = null
    ): array {
        $tree = [];

        foreach ($items as $key => $item) {
            if (!array_key_exists($parentField, $item)) {
                continue;
            }

            if ($item[$parentField] === $parentId) {
                $current = $item;
                unset($current[$parentField]);
                $current[$childrenField] = self::toTree(
                    $items,
                    $parentField,
                    $childrenField,
                    $key
                );
                $tree[$key] = $current;
            }
        }

        return $tree;
    }

    /**
     * Converts a hierarchical tree to a flat list with level indicators.
     *
     * The elements go in depth order, with the keys that they had in the tree.
     * Each one has only its name (`name`, from `$nameField`) and its `level`
     * (0 for the root).
     *
     * An item that is not an array, or that does not have the name field (or has
     * it as `null`), is skipped, with everything below it. If `$childrenField` of an item is
     * not an array (a string, for example) the item is listed but its
     * children are silently omitted.
     *
     * @param array<array-key, mixed> $tree The tree structure.
     * @param string $nameField Field containing the item name.
     * @param string $childrenField Field containing child items.
     * @param int $level Current level (used internally).
     * @param array<array-key, array{name: mixed, level: int}> $result Accumulated result (used internally).
     * @return array<array-key, array{name: mixed, level: int}> The flattened list with level information.
     */
    public static function treeToList(
        array $tree,
        string $nameField,
        string $childrenField,
        int $level = 0,
        array &$result = []
    ): array {
        foreach ($tree as $key => $item) {
            if (!is_array($item) || !isset($item[$nameField])) {
                continue;
            }

            $result[$key] = [
                'name' => $item[$nameField],
                'level' => $level,
            ];

            $children = $item[$childrenField] ?? null;
            if (is_array($children) && $children !== []) {
                self::treeToList(
                    $children,
                    $nameField,
                    $childrenField,
                    $level + 1,
                    $result
                );
            }
        }

        return $result;
    }

    /**
     * Transforms a grouped array into a table format.
     *
     * Converts an array like:
     *
     *   [
     *     'key1' => [1,2,3],
     *     'key2' => [4,5,6]
     *   ]
     *
     * into:
     *
     *   [
     *     ['key1' => 1, 'key2' => 4],
     *     ['key1' => 2, 'key2' => 5],
     *     ['key1' => 3, 'key2' => 6],
     *   ]
     *
     * A key that is requested and is not in the array is in every row with the
     * value `null`. The number of rows is the one of the longest group that is
     * there: if none of the keys is there, there are no rows.
     *
     * @param array<array-key, array<int, mixed>> $array The grouped array to transform.
     * @param list<array-key>|null $keys Specific keys to extract (null for all keys).
     * @return list<array<array-key, mixed>> The resulting table array.
     */
    public static function groupToTable(array $array, ?array $keys = null): array
    {
        $keys = $keys ?? array_keys($array);
        if (empty($keys)) {
            return [];
        }

        // Find the maximum length among all groups.
        $lengths = array_map(
            'count',
            array_intersect_key($array, array_flip($keys))
        );
        $maxLength = $lengths === [] ? 0 : max($lengths);

        $result = [];
        for ($i = 0; $i < $maxLength; $i++) {
            $row = [];
            foreach ($keys as $key) {
                $row[$key] = $array[$key][$i] ?? null;
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Converts a table of N rows and 2 columns into an associative array.
     *
     * The first column of each row is the key and the second one the value. The
     * key can be any scalar or `null`: an integer is kept, and any other one is
     * turned into a string (`null` is `""`, `true` is `"1"`, `2.7` is `"2.7"`),
     * which PHP then uses as a key as it does with any array (`"3"` is `3`). A
     * key that is an array or an object can not be a key and is an error.
     *
     * @param array<array-key, mixed> $table The table to convert, where each row has exactly 2 columns.
     * @return array<array-key, mixed> The resulting associative array.
     * @throws InvalidArgumentException If any row doesn't have exactly 2 columns
     * or its first column can not be a key.
     */
    public static function tableToAssociative(array $table): array
    {
        $result = [];
        foreach ($table as $index => $row) {
            if (!is_array($row) || count($row) !== 2) {
                throw new InvalidArgumentException([
                    'Row {index} must have exactly 2 columns.',
                    'index' => $index,
                ]);
            }
            $key = array_shift($row);
            if (!is_scalar($key) && $key !== null) {
                throw new InvalidArgumentException([
                    'The first column of row {index} must be a scalar or null to be a key.',
                    'index' => $index,
                ]);
            }
            $result[is_int($key) ? $key : (string) $key] = array_shift($row);
        }

        return $result;
    }

    /**
     * Converts an array to XML.
     *
     * @param array<array-key, mixed> $array The array to convert.
     * @param string $rootElement The name of the root XML element.
     * @return string The XML string.
     * @throws InvalidArgumentException If a value can not be written as XML text.
     * @throws RuntimeException If the XML can not be generated.
     */
    public static function toXml(array $array, string $rootElement = 'root'): string
    {
        $xml = new SimpleXMLElement(
            "<?xml version=\"1.0\"?><{$rootElement}></{$rootElement}>"
        );
        self::arrayToXml($array, $xml);

        return $xml->asXML() ?: throw new RuntimeException('Failed to generate the XML.');
    }

    /**
     * Helper method to convert array to XML recursively.
     *
     * A value that is an array is a child element, with its own children. Any
     * other value is the text of the element: strings, integers and floats as
     * they are, `true` as `1`, `false` and `null` as an empty element, and an
     * object that is `Stringable` as its text. A value that has no text (any
     * other object, a resource) is an error.
     *
     * @param array<array-key, mixed> $array Array to convert.
     * @param \SimpleXMLElement $xml XML element being built.
     * @return void
     * @throws InvalidArgumentException If a value can not be written as text.
     */
    private static function arrayToXml(array $array, \SimpleXMLElement $xml): void
    {
        foreach ($array as $key => $value) {
            $key = is_numeric($key) ? 'item' : $key;

            if (is_array($value)) {
                $child = $xml->addChild($key);
                self::arrayToXml($value, $child);
            } elseif (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $xml->addChild($key, htmlspecialchars((string) $value));
            } else {
                throw new InvalidArgumentException([
                    'The value of "{key}" can not be written as XML text, {given} given.',
                    'key' => $key,
                    'given' => get_debug_type($value),
                ]);
            }
        }
    }
}
