<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use Derafu\Support\ArrayType;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ArrayType::class)]
final class ArrayTypeTest extends TestCase
{
    /**
     * @return array<string, array{ArrayType<mixed>, mixed}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'mixed' => [ArrayType::mixed(), new stdClass()],
            'string' => [ArrayType::string(), 'a'],
            'int' => [ArrayType::int(), 3],
            'float' => [ArrayType::float(), 1.5],
            'number with an int' => [ArrayType::number(), 3],
            'number with a float' => [ArrayType::number(), 1.5],
            'bool' => [ArrayType::bool(), false],
            'scalar' => [ArrayType::scalar(), 'a'],
            'array' => [ArrayType::array(), [1, 'a' => 2]],
            'map' => [ArrayType::map(), ['a' => 1]],
            'empty map' => [ArrayType::map(), []],
            'map with integer keys that are not a list' => [ArrayType::map(), [1 => 'a']],
            'list' => [ArrayType::list(), ['a', 1]],
            'empty list' => [ArrayType::list(), []],
            'list of strings' => [ArrayType::listOf(ArrayType::string()), ['a', 'b']],
            'empty list of strings' => [ArrayType::listOf(ArrayType::string()), []],
            'map of ints' => [ArrayType::mapOf(ArrayType::int()), ['a' => 1]],
            'empty map of ints' => [ArrayType::mapOf(ArrayType::int()), []],
            'one of, first' => [ArrayType::oneOf(ArrayType::bool(), ArrayType::map()), true],
            'one of, last' => [ArrayType::oneOf(ArrayType::bool(), ArrayType::map()), ['a' => 1]],
            'nullable with a value' => [ArrayType::string()->nullable(), 'a'],
            'nullable with null' => [ArrayType::string()->nullable(), null],
        ];
    }

    /**
     * @param ArrayType<mixed> $type
     */
    #[Test]
    #[DataProvider('acceptedProvider')]
    public function shouldReturnTheValueThatIsOfTheType(ArrayType $type, mixed $value): void
    {
        $this->assertSame($value, $type->parse($value, 'key'));
    }

    /**
     * @return array<string, array{ArrayType<mixed>, mixed, string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'string' => [ArrayType::string(), 1, 'string'],
            'int with a numeric string' => [ArrayType::int(), '3', 'int'],
            'int with a float' => [ArrayType::int(), 3.0, 'int'],
            'float with an int' => [ArrayType::float(), 3, 'float'],
            'number' => [ArrayType::number(), '3', 'float|int'],
            'bool' => [ArrayType::bool(), 0, 'bool'],
            'scalar' => [ArrayType::scalar(), [], 'bool|float|int|string'],
            'array' => [ArrayType::array(), 'a', 'array'],
            'map with a string' => [ArrayType::map(), 'a', 'object'],
            'map with a list' => [ArrayType::map(), ['a', 'b'], 'object'],
            'list with a string' => [ArrayType::list(), 'a', 'list'],
            'list with a map' => [ArrayType::list(), ['a' => 1], 'list'],
            'list of strings with a string' => [ArrayType::listOf(ArrayType::string()), 'a', 'list<string>'],
            'list of strings with a map' => [ArrayType::listOf(ArrayType::string()), ['a' => 'b'], 'list<string>'],
            'map of ints with a string' => [ArrayType::mapOf(ArrayType::int()), 'a', 'object<int>'],
            'map of ints with a list' => [ArrayType::mapOf(ArrayType::int()), [1, 2], 'object<int>'],
            'one of' => [ArrayType::oneOf(ArrayType::bool(), ArrayType::map()), 'a', 'bool|object'],
            'nullable' => [ArrayType::string()->nullable(), 1, 'string'],
        ];
    }

    /**
     * @param ArrayType<mixed> $type
     */
    #[Test]
    #[DataProvider('rejectedProvider')]
    public function shouldRejectTheValueThatIsNotOfTheType(ArrayType $type, mixed $value, string $expected): void
    {
        try {
            $type->parse($value, 'key');
            $this->fail('The value should be rejected.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertStringContainsString(
                'The key "key" must be of type ' . $expected . ', ',
                $e->getMessage()
            );
        }
    }

    #[Test]
    public function shouldCreateEveryType(): void
    {
        $this->assertSame(
            ['mixed', 'string', 'int', 'float', 'float|int', 'bool', 'bool|float|int|string', 'array', 'object', 'list'],
            array_map(static fn (ArrayType $t): string => $t->name, [
                ArrayType::mixed(),
                ArrayType::string(),
                ArrayType::int(),
                ArrayType::float(),
                ArrayType::number(),
                ArrayType::bool(),
                ArrayType::scalar(),
                ArrayType::array(),
                ArrayType::map(),
                ArrayType::list(),
            ])
        );
    }

    #[Test]
    public function shouldNameTheTypes(): void
    {
        $this->assertSame('list<object<int>>', ArrayType::listOf(ArrayType::mapOf(ArrayType::int()))->name);
        $this->assertSame('bool|object', ArrayType::oneOf(ArrayType::bool(), ArrayType::map())->name);
        $this->assertSame('string|null', ArrayType::string()->nullable()->name);
    }

    #[Test]
    public function shouldReportThePathOfTheItemThatFails(): void
    {
        $type = ArrayType::mapOf(ArrayType::listOf(ArrayType::string()));

        try {
            $type->parse(['a' => ['x', 2]], 'dependentRequired');
            $this->fail('The value should be rejected.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame(
                'The key "dependentRequired.a.1" must be of type string, int given.',
                $e->getMessage()
            );
        }
    }

    #[Test]
    public function shouldReportTheTypeOfTheGivenValue(): void
    {
        $error = ArrayType::mismatch('k', 'string', new stdClass());

        $this->assertInstanceOf(TranslatableInterface::class, $error);
        $this->assertSame('The key "k" must be of type string, stdClass given.', $error->getMessage());
    }

    #[Test]
    public function shouldJoinPaths(): void
    {
        $this->assertSame('a', ArrayType::path('', 'a'));
        $this->assertSame('0', ArrayType::path('', 0));
        $this->assertSame('a.b', ArrayType::path('a', 'b'));
        $this->assertSame('a.0', ArrayType::path('a', 0));
    }

    #[Test]
    public function shouldAcceptACustomType(): void
    {
        $even = new ArrayType('even', static fn (mixed $v, string $p): int
            => is_int($v) && $v % 2 === 0 ? $v : throw ArrayType::mismatch($p, 'even', $v));

        $this->assertSame(4, $even->parse(4));
        $this->assertSame([2, 4], ArrayType::listOf($even)->parse([2, 4]));

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "0" must be of type even, int given.');
        ArrayType::listOf($even)->parse([3]);
    }
}
