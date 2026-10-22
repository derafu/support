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
use DateTimeInterface;
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

    #[Test]
    public function shouldGiveTheDefaultThatIsNotNullAsTheTypeItHas(): void
    {
        $reader = ArrayReader::from(['present' => 'a']);

        $this->assertSame('GET', $reader->string('method', 'GET'));
        $this->assertSame('a', $reader->string('present', 'GET'));
        $this->assertSame(80, $reader->int('port', 80));
        $this->assertSame(1.5, $reader->float('ratio', 1.5));
        $this->assertSame(2, $reader->number('size', 2));
        $this->assertTrue($reader->bool('debug', true));
        $this->assertNull($reader->string('method'));
    }

    #[Test]
    public function shouldReadANonEmptyStringAndADate(): void
    {
        $reader = ArrayReader::from(['path' => '/api', 'blank' => ' ', 'date' => '2026-01-15', 'time' => 1768435200]);

        $this->assertSame('/api', $reader->nonEmptyString('path'));
        $this->assertNull($reader->nonEmptyString('missing'));
        $date = $reader->dateTime('date');
        $this->assertInstanceOf(DateTimeInterface::class, $date);
        $this->assertSame('2026-01-15', $date->format('Y-m-d'));
        $this->assertSame(1768435200, $reader->dateTime('time')?->getTimestamp());
        $this->assertNull($reader->dateTime('missing'));

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "blank" must be of type non-empty-string, string given.');

        $reader->nonEmptyString('blank');
    }

    #[Test]
    public function shouldReadAListOfObjectsAsReadersThatReportTheirPosition(): void
    {
        $reader = ArrayReader::from([
            'parameters' => [['name' => 'id', 'in' => 'path'], ['name' => 5]],
        ]);

        $readers = $reader->readers('parameters');

        $this->assertNotNull($readers);
        $this->assertCount(2, $readers);
        $this->assertSame('id', $readers[0]->string('name'));
        $this->assertSame('path', $readers[0]->string('in'));

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "parameters.1.name" must be of type string, int given.');

        $readers[1]->string('name');
    }

    #[Test]
    public function shouldReturnNullAndRejectWhatIsNotAListOfObjectsInReaders(): void
    {
        $reader = ArrayReader::from(['one' => ['a' => 1], 'items' => ['a', 'b']]);

        $this->assertNull($reader->readers('missing'));
        $this->assertSame([], ArrayReader::from(['empty' => []])->readers('empty'));

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "items.0" must be of type object, string given.');

        $reader->readers('items');
    }

    #[Test]
    public function shouldAllowOnlyTheKeysThatAreSaid(): void
    {
        $reader = ArrayReader::from(['path' => '/', 'maxAge' => 10]);

        $this->assertSame($reader, $reader->onlyKeys('path', 'maxAge', 'other'));
        $this->assertSame('/', $reader->onlyKeys('path', 'maxAge')->string('path'));
    }

    #[Test]
    public function shouldRejectAKeyThatIsNotAllowed(): void
    {
        $reader = ArrayReader::from(['path' => '/', 'allowedOrgins' => ['*']]);

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "allowedOrgins" is not allowed; the allowed keys are: path, allowedOrigins.');

        $reader->onlyKeys('path', 'allowedOrigins');
    }

    #[Test]
    public function shouldRejectAKeyThatIsNotAllowedWithItsPathAndItsSource(): void
    {
        $rule = ArrayReader::from(['rules' => [['path' => '/', 'typo' => 1]]], 'config/cors.yaml')->readers('rules');

        $this->assertNotNull($rule);
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The key "rules.0.typo" of config/cors.yaml is not allowed; the allowed keys are: path.');

        $rule[0]->onlyKeys('path');
    }

    #[Test]
    public function shouldSayWhereTheArrayComesFromInTheErrorsOfTheReaderAndOfTheOnesItCreates(): void
    {
        $reader = ArrayReader::from(['title' => 5, 'meta' => ['count' => 'x'], 'items' => [['id' => 'y']]], 'docs/guide.md');
        $items = $reader->readers('items');
        $this->assertNotNull($items);

        $checks = [
            fn () => $reader->string('title'),
            fn () => $reader->require('missing', ArrayType::string()),
            fn () => $reader->reader('meta')?->int('count'),
            fn () => $items[0]->int('id'),
        ];
        $messages = [
            'The key "title" of docs/guide.md must be of type string, int given.',
            'The key "missing" of docs/guide.md is required.',
            'The key "meta.count" of docs/guide.md must be of type int, string given.',
            'The key "items.0.id" of docs/guide.md must be of type int, string given.',
        ];

        foreach ($checks as $i => $check) {
            try {
                $check();
                $this->fail('The check should fail.');
            } catch (TranslatableInvalidArgumentException $e) {
                $this->assertSame($messages[$i], $e->getMessage());
            }
        }
    }
}
