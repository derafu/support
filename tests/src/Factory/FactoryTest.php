<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport\Factory;

use Derafu\Support\Factory;
use Derafu\Support\Hydrator;
use Derafu\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Factory::class)]
#[CoversClass(Hydrator::class)]
#[CoversClass(Str::class)]
class FactoryTest extends TestCase
{
    #[Test]
    public function shouldCreateStdClass(): void
    {
        $data = ['name' => 'Test', 'value' => 42];

        $result = Factory::create($data);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('Test', $result->name);
        $this->assertSame(42, $result->value);
    }

    #[Test]
    public function shouldCreateCustomClass(): void
    {
        $data = ['name' => 'Test Name'];

        $class = new class () {
            public string $name;
        };

        $result = Factory::create($data, get_class($class));

        $this->assertInstanceOf(get_class($class), $result);
        $this->assertSame('Test Name', $result->name);
    }

    #[Test]
    public function shouldCreateMultipleInstances(): void
    {
        $dataSet = [
            ['name' => 'First'],
            ['name' => 'Second'],
        ];

        $class = new class () {
            public string $name;
        };

        $results = Factory::createMany($dataSet, get_class($class));

        $this->assertCount(2, $results);
        $this->assertSame('First', $results[0]->name);
        $this->assertSame('Second', $results[1]->name);
    }

    #[Test]
    public function shouldCreateAndEnsureType(): void
    {
        $data = ['value' => 42];

        $baseClass = new class () {
            public int $value;
        };

        $result = Factory::createAndEnsureType(
            $data,
            get_class($baseClass),
            get_class($baseClass)
        );

        $this->assertInstanceOf(get_class($baseClass), $result);
        $this->assertSame(42, $result->value);
    }

    #[Test]
    public function shouldFailOnTypeMismatch(): void
    {
        $data = ['value' => 42];

        $baseClass = new class () {
            public int $value;
        };

        $expectedClass = new class () {
            public string $different;
        };

        $this->expectException(LogicException::class);
        Factory::createAndEnsureType(
            $data,
            get_class($baseClass),
            get_class($expectedClass)
        );
    }

    #[Test]
    public function shouldCreateWithNestedDataArray(): void
    {
        $data = [
            'name' => 'Parent',
            'child' => [
                'name' => 'Child',
            ],
        ];

        $result = Factory::create($data);

        $this->assertSame('Parent', $result->name);
        $this->assertIsArray($result->child);
        $this->assertSame('Child', $result->child['name']);
    }

    #[Test]
    public function shouldCreateWithNestedDataObject(): void
    {
        $data = [
            'name' => 'Parent',
            'child' => Factory::create([
                'name' => 'Child',
            ]),
        ];

        $result = Factory::create($data);

        $this->assertSame('Parent', $result->name);
        $this->assertIsObject($result->child);
        $this->assertSame('Child', $result->child->name);
    }

    #[Test]
    public function shouldCreateWithArrayData(): void
    {
        $data = [
            'name' => 'Test',
            'values' => [1, 2, 3],
        ];

        $result = Factory::create($data);

        $this->assertSame('Test', $result->name);
        $this->assertSame([1, 2, 3], $result->values);
    }

    #[Test]
    public function shouldHandleEmptyData(): void
    {
        $result = Factory::create([]);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame(json_encode(new stdClass()), json_encode($result));
    }

    #[Test]
    public function shouldCreateManyWithEmptyDataSet(): void
    {
        $results = Factory::createMany([]);

        $this->assertEmpty($results);
    }

    #[Test]
    public function shouldHandleNullValues(): void
    {
        $data = ['name' => null];

        $class = new class () {
            public ?string $name = null;
        };

        $result = Factory::create($data, get_class($class));

        $this->assertNull($result->name);
    }

    #[Test]
    public function shouldUseSetAttributeAsFallback(): void
    {
        $data = ['name' => 'John', 'age' => 30];

        $instance = Factory::create($data, FactoryHelperFallbackClass::class);

        $this->assertInstanceOf(FactoryHelperFallbackClass::class, $instance);
        $this->assertSame('John', $instance->getAttribute('name'));
        $this->assertSame(30, $instance->getAttribute('age'));
    }
}

class FactoryHelperFallbackClass
{
    private array $attributes = [];

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
}
