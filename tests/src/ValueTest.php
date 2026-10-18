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

use Derafu\Support\Value;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Value::class)]
class ValueTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function valueProvider(): array
    {
        return [
            'null' => [null, true],
            'empty string' => ['', true],
            'empty array' => [[], true],
            'zero' => [0, false],
            'zero float' => [0.0, false],
            'zero string' => ['0', false],
            'false' => [false, false],
            'true' => [true, false],
            'space' => [' ', false],
            'string' => ['a', false],
            'array with a null' => [[null], false],
            'object' => [new stdClass(), false],
        ];
    }

    #[Test]
    #[DataProvider('valueProvider')]
    public function shouldTellIfAValueIsBlank(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Value::isBlank($value));
    }
}
