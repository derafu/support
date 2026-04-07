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

use Derafu\Support\Caster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Caster::class)]
class CasterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // cast()
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldReturnNullAsIs(): void
    {
        $this->assertNull(Caster::cast(null));
    }

    #[Test]
    public function shouldReturnBoolTrueAsIs(): void
    {
        $this->assertTrue(Caster::cast(true));
    }

    #[Test]
    public function shouldReturnBoolFalseAsIs(): void
    {
        $this->assertFalse(Caster::cast(false));
    }

    #[Test]
    public function shouldReturnIntAsIs(): void
    {
        $this->assertSame(42, Caster::cast(42));
        $this->assertSame(-7, Caster::cast(-7));
        $this->assertSame(0, Caster::cast(0));
    }

    #[Test]
    public function shouldReturnFloatAsIs(): void
    {
        $this->assertSame(3.14, Caster::cast(3.14));
        $this->assertSame(-0.5, Caster::cast(-0.5));
    }

    #[Test]
    public function shouldCastObjectWithToStringToString(): void
    {
        $obj = new class () {
            public function __toString(): string
            {
                return 'hello object';
            }
        };

        $this->assertSame('hello object', Caster::cast($obj));
    }

    // -------------------------------------------------------------------------
    // parseString() — null literals
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('provideNullLiteralStrings')]
    public function shouldCastNullLiteralStringsToNull(string $value): void
    {
        $this->assertNull(Caster::cast($value));
    }

    public static function provideNullLiteralStrings(): array
    {
        return [
            'empty string'  => [''],
            'null lowercase' => ['null'],
            'null uppercase' => ['NULL'],
            'null mixed case' => ['Null'],
            'nil lowercase'  => ['nil'],
            'nil uppercase'  => ['NIL'],
            'nil mixed case' => ['Nil'],
            'null with spaces' => ['  null  '],
            'empty with spaces' => ['   '],
        ];
    }

    // -------------------------------------------------------------------------
    // parseString() — boolean literals
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('provideTrueLiteralStrings')]
    public function shouldCastTrueLiteralStringsToBoolTrue(string $value): void
    {
        $this->assertTrue(Caster::cast($value));
    }

    public static function provideTrueLiteralStrings(): array
    {
        return [
            'true lowercase' => ['true'],
            'true uppercase' => ['TRUE'],
            'true mixed case' => ['True'],
            'yes lowercase'  => ['yes'],
            'yes uppercase'  => ['YES'],
            'on lowercase'   => ['on'],
            'on uppercase'   => ['ON'],
            'one string'     => ['1'],
            'true with spaces' => ['  true  '],
        ];
    }

    #[Test]
    #[DataProvider('provideFalseLiteralStrings')]
    public function shouldCastFalseLiteralStringsToBoolFalse(string $value): void
    {
        $this->assertFalse(Caster::cast($value));
    }

    public static function provideFalseLiteralStrings(): array
    {
        return [
            'false lowercase' => ['false'],
            'false uppercase' => ['FALSE'],
            'false mixed case' => ['False'],
            'no lowercase'    => ['no'],
            'no uppercase'    => ['NO'],
            'off lowercase'   => ['off'],
            'off uppercase'   => ['OFF'],
            'zero string'     => ['0'],
            'false with spaces' => ['  false  '],
        ];
    }

    // -------------------------------------------------------------------------
    // parseString() — integer strings
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('provideIntegerStrings')]
    public function shouldCastIntegerStringsToInt(string $value, int $expected): void
    {
        $result = Caster::cast($value);

        $this->assertIsInt($result);
        $this->assertSame($expected, $result);
    }

    public static function provideIntegerStrings(): array
    {
        return [
            'positive integer'        => ['42', 42],
            'negative integer'        => ['-5', -5],
            'positive with plus sign' => ['+10', 10],
            'zero'                    => ['00', 0],
            'large integer'           => ['1000000', 1000000],
            'underscore separator'    => ['1_000', 1000],
            'underscore large'        => ['1_000_000', 1000000],
            'integer with spaces'     => ['  99  ', 99],
        ];
    }

    // -------------------------------------------------------------------------
    // parseString() — float strings
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('provideFloatStrings')]
    public function shouldCastFloatStringsToFloat(string $value, float $expected): void
    {
        $result = Caster::cast($value);

        $this->assertIsFloat($result);
        $this->assertSame($expected, $result);
    }

    public static function provideFloatStrings(): array
    {
        return [
            'simple float'          => ['3.14', 3.14],
            'negative float'        => ['-2.8', -2.8],
            'leading dot'           => ['.5', 0.5],
            'trailing dot'          => ['3.', 3.0],
            'scientific notation'   => ['1.5e3', 1500.0],
            'scientific uppercase'  => ['1.5E3', 1500.0],
            'negative exponent'     => ['-2.8E-10', -2.8E-10],
            'positive exponent'     => ['2.5E+2', 250.0],
            'underscore separator'  => ['1_000.50', 1000.50],
            'float with spaces'     => ['  2.71  ', 2.71],
        ];
    }

    // -------------------------------------------------------------------------
    // parseString() — plain strings (no cast)
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('providePlainStrings')]
    public function shouldReturnPlainStringsAsString(string $value): void
    {
        $result = Caster::cast($value);

        $this->assertIsString($result);
        $this->assertSame($value, $result);
    }

    public static function providePlainStrings(): array
    {
        return [
            'simple word'            => ['hello'],
            'sentence'               => ['Hello World'],
            'alphanumeric'           => ['abc123'],
            'mixed special chars'    => ['foo@bar.com'],
            'looks like version'     => ['1.2.3'],
            'partial bool-like word' => ['truthy'],
            'partial null-like word' => ['nullable'],
        ];
    }

    // -------------------------------------------------------------------------
    // castWithType()
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('provideCastWithTypeData')]
    public function shouldReturnValueAndType(
        mixed $value,
        int|float|bool|string|null $expectedValue,
        string $expectedType
    ): void {
        $result = Caster::castWithType($value);

        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertSame($expectedValue, $result['value']);
        $this->assertSame($expectedType, $result['type']);
    }

    public static function provideCastWithTypeData(): array
    {
        return [
            'null value'    => [null,    null,    'NULL'],
            'bool true'     => [true,    true,    'boolean'],
            'bool false'    => [false,   false,   'boolean'],
            'integer'       => [42,      42,      'integer'],
            'float'         => [3.14,    3.14,    'double'],
            'string int'    => ['42',    42,      'integer'],
            'string float'  => ['3.14',  3.14,    'double'],
            'string bool'   => ['true',  true,    'boolean'],
            'string null'   => ['null',  null,    'NULL'],
            'plain string'  => ['hello', 'hello', 'string'],
        ];
    }
}
