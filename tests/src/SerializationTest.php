<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use ArrayObject;
use DateTimeImmutable;
use Derafu\Support\Serialization;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use stdClass;

#[CoversClass(Serialization::class)]
class SerializationTest extends TestCase
{
    #[Test]
    #[DataProvider('provideSerializableData')]
    public function shouldDetermineIfValueIsSerializable(
        mixed $value,
        bool $expected
    ): void {
        $result = Serialization::isSerializable($value);
        $this->assertSame($expected, $result);
    }

    public static function provideSerializableData(): array
    {
        return [
            'array' => [
                ['test'],
                true,
            ],
            'empty array' => [
                [],
                true,
            ],
            'object' => [
                new stdClass(),
                true,
            ],
            'null' => [
                null,
                true,
            ],
            'integer' => [
                42,
                true,
            ],
            'string' => [
                'test',
                true,
            ],
            'empty string' => [
                '',
                true,
            ],
            'boolean' => [
                true,
                true,
            ],
            'float' => [
                3.14,
                true,
            ],
            'float that is not a number' => [
                NAN,
                true,
            ],
            'float that is infinite' => [
                INF,
                true,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideSerializedData')]
    public function shouldDetermineIfDataIsSerialized(
        mixed $data,
        bool $expected
    ): void {
        $result = Serialization::isSerialized($data);
        $this->assertSame($expected, $result);
    }

    public static function provideSerializedData(): array
    {
        $obj = new stdClass();
        $obj->test = 'value';

        return [
            'null serialized' => [
                'N;',
                true,
            ],
            'string serialized' => [
                's:4:"test";',
                true,
            ],
            'integer serialized' => [
                'i:42;',
                true,
            ],
            'array serialized' => [
                serialize(['test']),
                true,
            ],
            'object serialized' => [
                serialize($obj),
                true,
            ],
            'boolean serialized' => [
                'b:1;',
                true,
            ],
            'float serialized' => [
                'd:3.14;',
                true,
            ],
            'not serialized string' => [
                'test',
                false,
            ],
            'not serialized array' => [
                ['test'],
                false,
            ],
            'not serialized number' => [
                42,
                false,
            ],
            'invalid serialized format' => [
                's:4:"test"',  // Missing semicolon.
                false,
            ],
            'malformed serialized data' => [
                'x:1:y;',  // Invalid type indicator.
                false,
            ],
            'empty string' => [
                '',
                false,
            ],
            'non-string type' => [
                null,
                false,
            ],
            'complex serialized array' => [
                serialize(['key' => ['nested' => 'value']]),
                true,
            ],
            'complex serialized object' => [
                serialize((object)['key' => ['nested' => 'value']]),
                true,
            ],
        ];
    }

    #[Test]
    public function shouldNotSerializeResources(): void
    {
        $open = fopen('php://memory', 'r');
        $this->assertFalse(Serialization::isSerializable($open));

        // A resource that was closed is not one for PHP functions, and it is not
        // serializable either.
        $closed = fopen('php://memory', 'r');
        fclose($closed);
        $this->assertFalse(Serialization::isSerializable($closed));

        fclose($open);
    }

    #[Test]
    #[DataProvider('provideValuesThatPhpDoesNotSerialize')]
    public function shouldNotSerializeWhatPhpDoesNotSerialize(object $value): void
    {
        $this->assertFalse(Serialization::isSerializable($value));
    }

    public static function provideValuesThatPhpDoesNotSerialize(): array
    {
        return [
            'closure' => [fn () => true],
            'static closure' => [static fn () => true],
            'first class callable' => [strlen(...)],
            'generator' => [(static fn () => yield 1)()],
            'anonymous class' => [new class () {
                public int $a = 1;
            }],
            'reflection' => [new ReflectionClass(stdClass::class)],
            'object whose serialization throws' => [new class () {
                public function __serialize(): array
                {
                    throw new RuntimeException('No.');
                }
            }],
        ];
    }

    #[Test]
    public function shouldNotSerializeWhatHasSomethingThatIsNotSerializableInside(): void
    {
        $resource = fopen('php://memory', 'r');

        $this->assertFalse(Serialization::isSerializable(['a' => fn () => true]));
        $this->assertFalse(Serialization::isSerializable(['a' => ['b' => ['c' => $resource]]]));

        $withClosure = new stdClass();
        $withClosure->callback = fn () => true;
        $this->assertFalse(Serialization::isSerializable($withClosure));

        $withResource = new stdClass();
        $withResource->stream = $resource;
        $this->assertFalse(Serialization::isSerializable($withResource));
        $this->assertFalse(Serialization::isSerializable([new ArrayObject([$resource])]));

        // The properties of any visibility.
        $withPrivate = new class () {
            private mixed $stream;

            public function __construct()
            {
                $this->stream = fopen('php://memory', 'r');
            }

            public function close(): void
            {
                fclose($this->stream);
            }
        };
        $this->assertFalse(Serialization::isSerializable($withPrivate));

        $withPrivate->close();
        fclose($resource);
    }

    #[Test]
    public function shouldSerializeWhatPhpSerializesWithOnlySerializableValuesInside(): void
    {
        $this->assertTrue(Serialization::isSerializable(new DateTimeImmutable('2024-01-01')));
        $this->assertTrue(Serialization::isSerializable(new ArrayObject([1, 2])));
        $this->assertTrue(Serialization::isSerializable(['a' => [1, 'b', null, 2.5, true], 'o' => new stdClass()]));
        $this->assertTrue(Serialization::isSerializable([]));
    }

    #[Test]
    public function shouldSerializeCircularReferences(): void
    {
        $recursive = [];
        $recursive[] = &$recursive;
        $this->assertTrue(Serialization::isSerializable($recursive));

        $first = new stdClass();
        $second = new stdClass();
        $first->other = $second;
        $second->other = $first;
        $this->assertTrue(Serialization::isSerializable($first));

        // And a circular reference does not hide what is inside.
        $second->callback = fn () => true;
        $this->assertFalse(Serialization::isSerializable($first));
    }

    #[Test]
    public function shouldLetAnObjectThatSerializesItselfDecide(): void
    {
        // Its resource is not part of what it serializes: it is still
        // serializable.
        $this->assertTrue(Serialization::isSerializable(new SelfSerializingFixture()));
        $this->assertFalse(Serialization::isSerializable(new SelfSerializingFixture(failing: true)));
        $this->assertFalse(Serialization::isSerializable(new SelfSerializingFixture(withClosure: true)));
    }

    #[Test]
    public function shouldAskPhpForTheObjectsThatSerializeWithSleep(): void
    {
        $this->assertTrue(Serialization::isSerializable(new SleepFixture()));
        $this->assertFalse(Serialization::isSerializable(new SleepWithMissingPropertyFixture()));
    }
}

/**
 * An object that says by itself what is serialized: its resource is not part of
 * it.
 */
final class SelfSerializingFixture
{
    /** @var resource */
    public $stream;

    public function __construct(
        private readonly bool $failing = false,
        private readonly bool $withClosure = false
    ) {
        $this->stream = fopen('php://memory', 'r');
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    public function __serialize(): array
    {
        if ($this->failing) {
            throw new RuntimeException('No.');
        }

        return ['name' => $this->withClosure ? fn () => true : 'value'];
    }

    public function __unserialize(array $data): void
    {
    }
}

/**
 * An object that uses `__sleep()`.
 */
final class SleepFixture
{
    public int $a = 1;

    /** @return list<string> */
    public function __sleep(): array
    {
        return ['a'];
    }
}

/**
 * An object whose `__sleep()` names a property that does not exist: PHP warns
 * and serializes something else, so it is not serializable.
 */
final class SleepWithMissingPropertyFixture
{
    public int $a = 1;

    /** @return list<string> */
    public function __sleep(): array
    {
        return ['a', 'missing'];
    }
}
