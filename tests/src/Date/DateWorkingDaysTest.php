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
class DateWorkingDaysTest extends TestCase
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
    #[DataProvider('provideWorkingDayData')]
    public function shouldFindWorkingDay(
        int $year,
        int $month,
        int $workingDay,
        array $holidays,
        string|false $expected
    ): void {
        $result = Date::getWorkingDay($year, $month, $workingDay, $holidays);

        if ($expected === false) {
            $this->assertFalse($result);
        } else {
            $this->assertInstanceOf(Carbon::class, $result);
            $this->assertSame($expected, $result->format('Y-m-d'));
        }
    }

    public static function provideWorkingDayData(): array
    {
        return [
            'first working day' => [
                2024,
                1,
                1,
                [],
                '2024-01-01',
            ],
            'with weekend' => [
                2024,
                1,
                2,
                [],
                '2024-01-02',
            ],
            'with holiday' => [
                2024,
                1,
                2,
                ['2024-01-02'],
                '2024-01-03',
            ],
            'invalid working day' => [
                2024,
                1,
                50,  // Too many working days for the month.
                [],
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideWorkingDaysData')]
    public function shouldAddWorkingDays(
        string $startDate,
        int $days,
        array $holidays,
        string $expected
    ): void {
        $result = Date::addWorkingDays($startDate, $days, $holidays);
        $this->assertSame($expected, $result->format('Y-m-d'));
    }

    public static function provideWorkingDaysData(): array
    {
        return [
            'add one day' => [
                '2024-01-15',  // Monday.
                1,
                [],
                '2024-01-16',   // Tuesday.
            ],
            'skip weekend' => [
                '2024-01-19',  // Friday.
                1,
                [],
                '2024-01-22',   // Monday.
            ],
            'skip holiday' => [
                '2024-01-15',
                1,
                ['2024-01-16'],
                '2024-01-17',
            ],
            'skip multiple holidays' => [
                '2024-01-15',
                2,
                ['2024-01-16', '2024-01-17'],
                '2024-01-19',
            ],
            'skip multiple holidays and weekend' => [
                '2024-01-16',
                2,
                ['2024-01-17', '2024-01-18'],
                '2024-01-22',
            ],
            'no days to add' => [
                '2024-01-15',
                0,
                [],
                '2024-01-15',
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideLastWorkingDayData')]
    public function shouldCheckLastWorkingDay(
        string $date,
        array $holidays,
        bool $expected
    ): void {
        $result = Date::isLastWorkingDay($date, $holidays);
        $this->assertSame($expected, $result);
    }

    public static function provideLastWorkingDayData(): array
    {
        return [
            'last day is working' => [
                '2024-01-31',
                [],
                true,
            ],
            'last day is weekend' => [
                '2024-02-29',  // February 2024 ends on a Thursday.
                [],
                true,
            ],
            'not last working day' => [
                '2024-01-15',
                [],
                false,
            ],
            'last working day with holiday' => [
                '2024-01-30',
                ['2024-01-31'],
                true,
            ],
            'weekend day' => [
                '2024-01-27',  // Saturday.
                [],
                false,
            ],
        ];
    }
}
