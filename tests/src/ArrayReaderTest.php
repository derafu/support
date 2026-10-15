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

use Closure;
use Derafu\Support\ArrayReader;
use Derafu\Support\ArrayType;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayReader::class)]
#[UsesClass(ArrayType::class)]
final class ArrayReaderTest extends TestCase
{
    #[Test]
    public function shouldReadEveryKindOfValue(): void
    {
        $reader = ArrayReader::from([
            'string' => 'a',
            'int' => 3,
            'float' => 1.5,
            'number' => 2,
            'bool' => false,
            'array' => [1, 'a' => 2],
            'map' => ['a' => 1],
            'list' => ['a', 1],
            'stringList' => ['a', 'b'],
            'intList' => [1, 2],
            'scalarList' => ['a', 1, true, 1.5],
            'listOf' => [['a' => 1], ['b' => 2]],
            'mapOf' => ['x' => ['a'], 'y' => []],
        ]);

        $this->assertSame('a', $reader->string('string'));
        $this->assertSame(3, $reader->int('int'));
        $this->assertSame(1.5, $reader->float('float'));
        $this->assertSame(2, $reader->number('number'));
        $this->assertFalse($reader->bool('bool'));
        $this->assertSame([1, 'a' => 2], $reader->array('array'));
        $this->assertSame(['a' => 1], $reader->map('map'));
        $this->assertSame(['a', 1], $reader->list('list'));
        $this->assertSame(['a', 'b'], $reader->stringList('stringList'));
        $this->assertSame([1, 2], $reader->intList('intList'));
        $this->assertSame(['a', 1, true, 1.5], $reader->scalarList('scalarList'));
        $this->assertSame([['a' => 1], ['b' => 2]], $reader->listOf('listOf', ArrayType::map()));
        $this->assertSame(
            ['x' => ['a'], 'y' => []],
            $reader->mapOf('mapOf', ArrayType::listOf(ArrayType::string()))
        );
    }

    /**
     * @return array<string, array{Closure(ArrayReader): mixed}>
     */
    public static function readersProvider(): array
    {
        return [
            'string' => [fn (ArrayReader $r) => $r->string('k')],
            'int' => [fn (ArrayReader $r) => $r->int('k')],
            'float' => [fn (ArrayReader $r) => $r->float('k')],
            'number' => [fn (ArrayReader $r) => $r->number('k')],
            'bool' => [fn (ArrayReader $r) => $r->bool('k')],
            'array' => [fn (ArrayReader $r) => $r->array('k')],
            'map' => [fn (ArrayReader $r) => $r->map('k')],
            'list' => [fn (ArrayReader $r) => $r->list('k')],
            'stringList' => [fn (ArrayReader $r) => $r->stringList('k')],
            'intList' => [fn (ArrayReader $r) => $r->intList('k')],
            'scalarList' => [fn (ArrayReader $r) => $r->scalarList('k')],
            'listOf' => [fn (ArrayReader $r) => $r->listOf('k', ArrayType::string())],
            'mapOf' => [fn (ArrayReader $r) => $r->mapOf('k', ArrayType::string())],
            'reader' => [fn (ArrayReader $r) => $r->reader('k')],
        ];
    }

    /**
     * @param Closure(ArrayReader): mixed $read
     */
    #[Test]
    #[DataProvider('readersProvider')]
    public function shouldReturnNullWhenTheKeyIsAbsentOrNull(Closure $read): void
    {
        $this->assertNull($read(ArrayReader::from([])));
        $this->assertNull($read(ArrayReader::from(['k' => null])));
    }

    /**
     * @param Closure(ArrayReader): mixed $read
     */
    #[Test]
    #[DataProvider('readersProvider')]
    public function shouldRejectAValueOfAnotherType(Closure $read): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "k" must be of type ');
        $read(ArrayReader::from(['k' => new \stdClass()]));
    }

    #[Test]
    public function shouldReturnTheDefaultWhenTheKeyIsAbsent(): void
    {
        $reader = ArrayReader::from(['present' => 'x', 'null' => null]);

        $this->assertSame('d', $reader->string('missing', 'd'));
        $this->assertSame('d', $reader->string('null', 'd'));
        $this->assertSame('x', $reader->string('present', 'd'));
        $this->assertSame(7, $reader->int('missing', 7));
        $this->assertSame(0.5, $reader->float('missing', 0.5));
        $this->assertSame(2, $reader->number('missing', 2));
        $this->assertTrue($reader->bool('missing', true));
        $this->assertSame('fallback', $reader->get('missing', ArrayType::int(), 'fallback'));
    }

    #[Test]
    public function shouldKeepAFalseValueInsteadOfTheDefault(): void
    {
        $reader = ArrayReader::from(['bool' => false, 'int' => 0, 'string' => '']);

        $this->assertFalse($reader->bool('bool', true));
        $this->assertSame(0, $reader->int('int', 7));
        $this->assertSame('', $reader->string('string', 'd'));
    }

    #[Test]
    public function shouldTellIfAKeyIsThere(): void
    {
        $reader = ArrayReader::from(['a' => 1, 'null' => null, 'false' => false]);

        $this->assertTrue($reader->has('a'));
        $this->assertTrue($reader->has('false'));
        $this->assertFalse($reader->has('null'));
        $this->assertFalse($reader->has('missing'));
    }

    #[Test]
    public function shouldRequireAKey(): void
    {
        $reader = ArrayReader::from(['type' => 'string', 'bad' => 1]);

        $this->assertSame('string', $reader->require('type', ArrayType::string()));

        try {
            $reader->require('missing', ArrayType::string());
            $this->fail('A key that is not there should be an error.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The key "missing" is required.', $e->getMessage());
        }

        $this->expectExceptionMessage('The key "bad" must be of type string, int given.');
        $reader->require('bad', ArrayType::string());
    }

    #[Test]
    public function shouldReadANestedObjectReportingTheFullPath(): void
    {
        $reader = ArrayReader::from(['options' => ['size' => 3, 'deep' => ['x' => 'a']]]);

        $options = $reader->reader('options');
        $this->assertInstanceOf(ArrayReader::class, $options);
        $this->assertSame(3, $options->int('size'));

        $deep = $options->reader('deep');
        $this->assertInstanceOf(ArrayReader::class, $deep);
        $this->assertSame(['x' => 'a'], $deep->toArray());

        try {
            $deep->int('x');
            $this->fail('The value is not an integer.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The key "options.deep.x" must be of type int, string given.', $e->getMessage());
        }

        try {
            $options->require('missing', ArrayType::int());
            $this->fail('A key that is not there should be an error.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The key "options.missing" is required.', $e->getMessage());
        }
    }

    #[Test]
    public function shouldReportThePathOfAnItemThatFails(): void
    {
        $reader = ArrayReader::from(['dependentRequired' => ['a' => ['x', 2]]]);

        $this->expectExceptionMessage('The key "dependentRequired.a.1" must be of type string, int given.');
        $reader->mapOf('dependentRequired', ArrayType::listOf(ArrayType::string()));
    }

    #[Test]
    public function shouldReturnTheWholeArray(): void
    {
        $this->assertSame(['a' => 1], ArrayReader::from(['a' => 1])->toArray());
    }
}
