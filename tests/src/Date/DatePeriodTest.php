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
class DatePeriodTest extends TestCase
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
    #[DataProvider('providePeriodValidationData')]
    public function shouldValidatePeriod(
        int $period,
        int $yearFrom,
        int $yearTo,
        ?int $length,
        bool $expected
    ): void {
        $result = Date::validPeriod($period, $yearFrom, $yearTo, $length);
        $this->assertSame($expected, $result);
    }

    public static function providePeriodValidationData(): array
    {
        return [
            'valid year' => [
                2024,
                2000,
                2100,
                4,
                true,
            ],
            'valid month' => [
                202401,
                2000,
                2100,
                6,
                true,
            ],
            'invalid year range' => [
                1999,
                2000,
                2100,
                4,
                false,
            ],
            'invalid month' => [
                202413,
                2000,
                2100,
                6,
                false,
            ],
            'invalid length' => [
                2024,
                2000,
                2100,
                6,
                false,
            ],
            'any length year' => [
                2024,
                2000,
                2100,
                null,
                true,
            ],
            'any length month' => [
                202401,
                2000,
                2100,
                null,
                true,
            ],
        ];
    }

    #[Test]
    #[DataProvider('providePeriod4Data')]
    public function shouldValidatePeriod4(
        int $period,
        int $yearFrom,
        int $yearTo,
        bool $expected
    ): void {
        $result = Date::validPeriod4($period, $yearFrom, $yearTo);
        $this->assertSame($expected, $result);
    }

    public static function providePeriod4Data(): array
    {
        return [
            'valid year' => [
                2024,
                2000,
                2100,
                true,
            ],
            'year too early' => [
                1999,
                2000,
                2100,
                false,
            ],
            'year too late' => [
                2101,
                2000,
                2100,
                false,
            ],
            'invalid format' => [
                202401,
                2000,
                2100,
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('providePeriod6Data')]
    public function shouldValidatePeriod6(
        int $period,
        int $yearFrom,
        int $yearTo,
        bool $expected
    ): void {
        $result = Date::validPeriod6($period, $yearFrom, $yearTo);
        $this->assertSame($expected, $result);
    }

    public static function providePeriod6Data(): array
    {
        return [
            'valid period' => [
                202401,
                2000,
                2100,
                true,
            ],
            'invalid month' => [
                202413,
                2000,
                2100,
                false,
            ],
            'year too early' => [
                199912,
                2000,
                2100,
                false,
            ],
            'year too late' => [
                210101,
                2000,
                2100,
                false,
            ],
            'invalid format' => [
                2024,
                2000,
                2100,
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('providePeriodData')]
    public function shouldFormatPeriods(int $period, ?string $expected): void
    {
        if ($expected === null) {
            $this->expectException(InvalidArgumentException::class);
        }

        $result = Date::formatPeriodSpanish($period);

        if ($expected !== null) {
            $this->assertSame($expected, $result);
        }
    }

    public static function providePeriodData(): array
    {
        return [
            'january' => [202401, 'Enero de 2024'],
            'december' => [202412, 'Diciembre de 2024'],
            'invalid month' => [202413, null],
        ];
    }

    #[Test]
    #[DataProvider('provideNextPeriodData')]
    public function shouldCalculateNextPeriod(?int $period, int $steps, int $expected): void
    {
        // Fix the "now" time for testing.
        Carbon::setTestNow('2024-01-01 00:00:00');

        $result = Date::nextPeriod($period, $steps);
        $this->assertSame($expected, $result);

        Carbon::setTestNow(); // Reset time.
    }

    public static function provideNextPeriodData(): array
    {
        return [
            'one month' => [202401, 1, 202402],
            'multiple months' => [202401, 3, 202404],
            'year change' => [202412, 1, 202501],
            'null period' => [null, 1, 202402], // Assuming current date is 2024-01
            'no movement' => [202401, 0, 202401],
        ];
    }

    #[Test]
    #[DataProvider('providePreviousPeriodData')]
    public function shouldCalculatePreviousPeriod(?int $period, int $steps, int $expected): void
    {
        // Fix the "now" time for testing.
        Carbon::setTestNow('2024-01-01 00:00:00');

        $result = Date::previousPeriod($period, $steps);
        $this->assertSame($expected, $result);

        Carbon::setTestNow(); // Reset time.
    }

    public static function providePreviousPeriodData(): array
    {
        return [
            'one month' => [202402, 1, 202401],
            'multiple months' => [202404, 3, 202401],
            'year change' => [202401, 1, 202312],
            'null period' => [null, 1, 202312], // Assuming current date is 2024-01
            'no movement' => [202401, 0, 202401],
        ];
    }

    #[Test]
    public function shouldGetFirstDayOfPeriod(): void
    {
        $this->assertSame('2024-01-01', Date::firstDayPeriod(202401));
        $this->assertSame('2024-02-01', Date::firstDayPeriod(202402));
        $this->assertSame('2024-04-01', Date::firstDayPeriod(202404));
    }

    #[Test]
    public function shouldGetLastDayOfPeriod(): void
    {
        $this->assertSame('2024-01-31', Date::lastDayPeriod(202401));
        $this->assertSame('2024-02-29', Date::lastDayPeriod(202402)); // Leap year.
        $this->assertSame('2024-04-30', Date::lastDayPeriod(202404));
    }

    #[Test]
    #[DataProvider('provideDaysInMonthData')]
    public function shouldGetDaysInMonth(int $period, int $expected): void
    {
        $result = Date::daysInPeriod($period);
        $this->assertSame($expected, $result);
    }

    public static function provideDaysInMonthData(): array
    {
        return [
            'January' => [202401, 31],
            'February normal' => [202302, 28],
            'February leap' => [202402, 29],
            'April' => [202404, 30],
            'December' => [202412, 31],
        ];
    }

    #[Test]
    public function shouldHandleInvalidPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Date::formatPeriodSpanish(202413);
    }
}
