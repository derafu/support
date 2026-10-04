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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Date::class)]
class DateCalendarTest extends TestCase
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
    #[DataProvider('provideSerialNumberData')]
    public function shouldConvertFromSerialNumber(
        int $serialNumber,
        string $expected
    ): void {
        $result = Date::fromSerialNumber($serialNumber);
        $this->assertSame($expected, $result->format('Y-m-d'));
    }

    public static function provideSerialNumberData(): array
    {
        return [
            'regular date' => [
                44926,  // 2023-01-01
                '2023-01-01',
            ],
            'leap year date' => [
                45291,  // 2024-01-01
                '2024-01-01',
            ],
            'end of month' => [
                45321,  // 2024-01-31
                '2024-01-31',
            ],
            'beginning of excel' => [
                25569,  // 1970-01-01
                '1970-01-01',
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideWeekBoundariesData')]
    public function shouldGetWeekBoundaries(
        ?string $date,
        string $expectedFirst,
        string $expectedLast
    ): void {
        if ($date === null) {
            Carbon::setTestNow('2024-01-15');
        }

        $firstDay = Date::firstDayWeek($date);
        $lastDay = Date::lastDayWeek($date);

        $this->assertSame($expectedFirst, $firstDay->format('Y-m-d'));
        $this->assertSame($expectedLast, $lastDay->format('Y-m-d'));

        if ($date === null) {
            Carbon::setTestNow();
        }
    }

    public static function provideWeekBoundariesData(): array
    {
        return [
            'mid week' => [
                '2024-01-15',  // Monday.
                '2024-01-15',  // Monday.
                '2024-01-21',   // Sunday.
            ],
            'start of week' => [
                '2024-01-15',  // Monday.
                '2024-01-15',  // Monday.
                '2024-01-21',   // Sunday.
            ],
            'end of week' => [
                '2024-01-21',  // Sunday.
                '2024-01-15',  // Monday.
                '2024-01-21',   // Sunday.
            ],
            'current week' => [
                null,
                '2024-01-15',  // Monday.
                '2024-01-21',   // Sunday.
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideYearsData')]
    public function shouldGenerateYears(
        int $totalYears,
        ?int $from,
        array $expected
    ): void {
        if ($from === null) {
            Carbon::setTestNow('2024-01-15');
        }

        $result = Date::generateYears($totalYears, $from);
        $this->assertSame($expected, $result);

        if ($from === null) {
            Carbon::setTestNow();
        }
    }

    public static function provideYearsData(): array
    {
        return [
            'from current year' => [
                3,
                null,
                [2024, 2023, 2022],
            ],
            'specific range' => [
                5,
                2020,
                [2020, 2019, 2018, 2017, 2016],
            ],
            'single year' => [
                1,
                2024,
                [2024],
            ],
        ];
    }

    #[Test]
    public function shouldCalculateAge(): void
    {
        // Using fixed test date (2024-01-15).
        $this->assertSame(20, Date::calculateAge('2004-01-14'));
        $this->assertSame(20, Date::calculateAge('2004-01-15'));
        $this->assertSame(19, Date::calculateAge('2004-01-16'));
    }
}
