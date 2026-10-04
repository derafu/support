<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport\Date;

use Carbon\Carbon;
use Derafu\Support\Date;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Date::class)]
class DateValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 15));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    #[DataProvider('provideValidationData')]
    public function shouldValidateDates(string $date, string $format, bool $expected): void
    {
        $result = Date::validate($date, $format);
        $this->assertSame($expected, $result);
    }

    public static function provideValidationData(): array
    {
        return [
            'valid date standard format' => [
                '2024-01-15',
                'Y-m-d',
                true,
            ],
            'valid date custom format' => [
                '15/01/2024',
                'd/m/Y',
                true,
            ],
            'invalid day' => [
                '2024-02-30',
                'Y-m-d',
                false,
            ],
            'invalid month' => [
                '2024-13-01',
                'Y-m-d',
                false,
            ],
            'invalid format' => [
                '2024-01-15',
                'd/m/Y',
                false,
            ],
            'non-date string' => [
                'not a date',
                'Y-m-d',
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideValidateAndConvertData')]
    public function shouldValidateAndConvertDate(
        string $date,
        string $format,
        ?string $expected
    ): void {
        $result = Date::validateAndConvert($date, $format);
        $this->assertSame($expected, $result);
    }

    public static function provideValidateAndConvertData(): array
    {
        return [
            'valid date' => [
                '2024-01-15',
                'd/m/Y',
                '15/01/2024',
            ],
            'invalid format' => [
                '2024-13-45',
                'd/m/Y',
                null,
            ],
            'invalid date string' => [
                'not-a-date',
                'd/m/Y',
                null,
            ],
            'custom format' => [
                '2024-01-15',
                'Y.m.d',
                '2024.01.15',
            ],
        ];
    }

    #[Test]
    public function shouldValidateAndConvertWithDefaultOutputFormat(): void
    {
        $this->assertSame('12/03/2023', Date::validateAndConvert('2023-03-12'));
        $this->assertSame('31/12/2022', Date::validateAndConvert('2022-12-31'));
        $this->assertSame('01/01/2020', Date::validateAndConvert('2020-01-01'));
    }

    #[Test]
    public function shouldNotValidateAndConvertWhatIsNotInYearMonthDayFormat(): void
    {
        // Invalid month.
        $this->assertNull(Date::validateAndConvert('2023-13-12'));

        // Not a date.
        $this->assertNull(Date::validateAndConvert('not-a-date'));

        // Wrong separators.
        $this->assertNull(Date::validateAndConvert('2023/03/12'));
        $this->assertNull(Date::validateAndConvert('2023.03.12'));

        // Day first instead of year.
        $this->assertNull(Date::validateAndConvert('12/03/2023'));
    }

    #[Test]
    public function shouldHandleInvalidDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Date::create('invalid-date');
    }
}
