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
        // The numbers are the ones that Excel gives to those dates.
        return [
            'first day of excel' => [
                1,      // 1900-01-01
                '1900-01-01',
            ],
            'last day before the one that does not exist' => [
                59,     // 1900-02-28
                '1900-02-28',
            ],
            'the day that does not exist (February 29, 1900)' => [
                60,
                '1900-02-28',
            ],
            'first day after the one that does not exist' => [
                61,     // 1900-03-01
                '1900-03-01',
            ],
            'unix epoch' => [
                25569,  // 1970-01-01
                '1970-01-01',
            ],
            'year 2000' => [
                36526,  // 2000-01-01
                '2000-01-01',
            ],
            'regular date' => [
                44927,  // 2023-01-01
                '2023-01-01',
            ],
            'leap year date' => [
                45292,  // 2024-01-01
                '2024-01-01',
            ],
            'leap day' => [
                45351,  // 2024-02-29
                '2024-02-29',
            ],
            'end of month' => [
                45322,  // 2024-01-31
                '2024-01-31',
            ],
        ];
    }

    #[Test]
    public function shouldGiveTheStartOfTheDayForASerialNumber(): void
    {
        $this->assertSame('00:00:00', Date::fromSerialNumber(45292)->format('H:i:s'));
        $this->assertSame('00:00:00', Date::fromSerialNumber(25569)->format('H:i:s'));
    }

    #[Test]
    #[DataProvider('provideNextDateData')]
    public function shouldMoveForwardByUnits(string $date, string $unit, int $steps, string $expected): void
    {
        $this->assertSame($expected, Date::nextDate($date, $unit, $steps)->format('Y-m-d'));
    }

    public static function provideNextDateData(): array
    {
        return [
            'days' => ['2024-01-15', 'D', 10, '2024-01-25'],
            'weeks' => ['2024-01-15', 'W', 1, '2024-01-22'],
            'months' => ['2024-01-15', 'M', 1, '2024-02-15'],
            'several months' => ['2024-01-15', 'M', 13, '2025-02-15'],
            'quarters' => ['2024-01-15', 'Q', 1, '2024-04-15'],
            'semesters' => ['2024-01-15', 'S', 1, '2024-07-15'],
            'years' => ['2024-01-15', 'Y', 1, '2025-01-15'],
            'the unit in lowercase' => ['2024-01-15', 'm', 1, '2024-02-15'],
            'no steps' => ['2024-01-15', 'M', 0, '2024-01-15'],
            'negative steps go back' => ['2024-03-15', 'M', -1, '2024-02-15'],

            // The day that the month does not have is the last day of the month.
            'january 31 plus a month, leap year' => ['2024-01-31', 'M', 1, '2024-02-29'],
            'january 31 plus a month' => ['2023-01-31', 'M', 1, '2023-02-28'],
            'january 31 plus two months' => ['2024-01-31', 'M', 2, '2024-03-31'],
            'may 31 plus a month' => ['2024-05-31', 'M', 1, '2024-06-30'],
            'january 31 plus a quarter' => ['2024-01-31', 'Q', 1, '2024-04-30'],
            'august 31 plus a semester' => ['2024-08-31', 'S', 1, '2025-02-28'],
            'february 29 plus a year' => ['2024-02-29', 'Y', 1, '2025-02-28'],
            'february 29 plus four years' => ['2024-02-29', 'Y', 4, '2028-02-29'],
            'march 31 minus a month with negative steps' => ['2024-03-31', 'M', -1, '2024-02-29'],
        ];
    }

    #[Test]
    #[DataProvider('providePreviousDateData')]
    public function shouldMoveBackByUnits(string $date, string $unit, int $steps, string $expected): void
    {
        $this->assertSame($expected, Date::previousDate($date, $unit, $steps)->format('Y-m-d'));
    }

    public static function providePreviousDateData(): array
    {
        return [
            'days' => ['2024-01-15', 'D', 10, '2024-01-05'],
            'weeks' => ['2024-01-15', 'W', 2, '2024-01-01'],
            'months' => ['2024-03-15', 'M', 1, '2024-02-15'],
            'quarters' => ['2024-07-15', 'Q', 1, '2024-04-15'],
            'semesters' => ['2024-07-15', 'S', 1, '2024-01-15'],
            'years' => ['2024-01-15', 'Y', 1, '2023-01-15'],
            'the unit in lowercase' => ['2024-03-15', 'm', 1, '2024-02-15'],

            // The day that the month does not have is the last day of the month.
            'march 31 minus a month, leap year' => ['2024-03-31', 'M', 1, '2024-02-29'],
            'march 31 minus a month' => ['2023-03-31', 'M', 1, '2023-02-28'],
            'may 31 minus a quarter' => ['2024-05-31', 'Q', 1, '2024-02-29'],
            'august 31 minus a semester' => ['2024-08-31', 'S', 1, '2024-02-29'],
            'february 29 minus a year' => ['2024-02-29', 'Y', 1, '2023-02-28'],
            'december 31 minus a month' => ['2024-12-31', 'M', 1, '2024-11-30'],
        ];
    }

    #[Test]
    public function shouldMoveTheCurrentDateWhenNoDateIsGiven(): void
    {
        $this->assertSame('2024-02-15', Date::nextDate()->format('Y-m-d'));
        $this->assertSame('2023-12-15', Date::previousDate()->format('Y-m-d'));
        $this->assertSame('2024-01-16', Date::nextDate(null, 'D')->format('Y-m-d'));
    }

    #[Test]
    public function shouldKeepTheTimeOfTheDate(): void
    {
        $this->assertSame('2024-02-29 10:30:15', Date::nextDate('2024-01-31 10:30:15')->format('Y-m-d H:i:s'));
        $this->assertSame('2024-02-29 10:30:15', Date::previousDate('2024-03-31 10:30:15')->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function shouldNotChangeTheDateThatIsGiven(): void
    {
        $date = Carbon::create(2024, 1, 31, 10, 30);

        $next = Date::nextDate($date, 'M');
        $previous = Date::previousDate($date, 'Y', 2);

        $this->assertSame('2024-01-31 10:30:00', $date->format('Y-m-d H:i:s'));
        $this->assertNotSame($date, $next);
        $this->assertNotSame($date, $previous);
        $this->assertSame('2024-02-29', $next->format('Y-m-d'));
        $this->assertSame('2022-01-31', $previous->format('Y-m-d'));
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
