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

use DateTimeImmutable;
use Derafu\Support\JsonSerializer;
use JsonException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;

#[CoversClass(JsonSerializer::class)]
final class JsonSerializerTest extends TestCase
{
    #[Test]
    public function shouldEncodeWithTheDefaultFlags(): void
    {
        $this->assertSame(
            "{\n    \"name\": \"Muñoz\",\n    \"url\": \"a/b\"\n}",
            JsonSerializer::serialize(['name' => 'Muñoz', 'url' => 'a/b'])
        );
    }

    #[Test]
    public function shouldReplaceInvalidUtf8WithTheDefaultFlags(): void
    {
        $this->assertSame('"a�b"', JsonSerializer::serialize("a\xffb"));
    }

    #[Test]
    public function shouldEncodeWithTheFlagsThatAreGiven(): void
    {
        $this->assertSame('{"url":"a\/b"}', JsonSerializer::serialize(['url' => 'a/b'], 0));
        $this->assertSame('{"url":"a/b"}', JsonSerializer::serialize(['url' => 'a/b'], JSON_UNESCAPED_SLASHES));
    }

    #[Test]
    public function shouldTransformJsonSerializableObjects(): void
    {
        $object = new class () implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['when' => new DateTimeImmutable('2026-01-02 03:04:05+00:00')];
            }
        };

        $this->assertSame('{"when":"2026-01-02T03:04:05+00:00"}', JsonSerializer::serialize($object, 0));
    }

    #[Test]
    public function shouldTransformObjectsWithToArray(): void
    {
        $object = new class () {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['text' => new class () implements Stringable {
                    public function __toString(): string
                    {
                        return 'S';
                    }
                }];
            }
        };

        $this->assertSame('{"text":"S"}', JsonSerializer::serialize($object, 0));
    }

    #[Test]
    public function shouldTransformDatesAndStringableObjects(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return 'text';
            }
        };

        $this->assertSame(
            '["2026-01-02T03:04:05+00:00","text"]',
            JsonSerializer::serialize([new DateTimeImmutable('2026-01-02 03:04:05+00:00'), $text], 0)
        );
    }

    #[Test]
    public function shouldEncodeOtherObjectsWithTheirPublicProperties(): void
    {
        $object = new class () {
            public int $public = 1;

            protected int $protected = 2;
        };

        $this->assertSame('{"public":1}', JsonSerializer::serialize($object, 0));
    }

    #[Test]
    public function shouldTransformObjectsThatAreInsideArrays(): void
    {
        $this->assertSame(
            '{"a":{"b":["2026-01-02T03:04:05+00:00",1]}}',
            JsonSerializer::serialize(['a' => ['b' => [new DateTimeImmutable('2026-01-02 03:04:05+00:00'), 1]]], 0)
        );
    }

    #[Test]
    public function shouldThrowWhenTheValueCanNotBeEncodedEvenWithoutTheFlag(): void
    {
        $this->expectException(JsonException::class);

        JsonSerializer::serialize(NAN, 0);
    }

    #[Test]
    public function shouldThrowWhenTheDepthIsExceeded(): void
    {
        $this->expectException(JsonException::class);

        JsonSerializer::serialize([[[1]]], null, 2);
    }
}
