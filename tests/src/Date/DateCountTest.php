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
class DateCountTest extends TestCase
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
    #[DataProvider('provideCountDaysMatchData')]
    public function shouldCountMatchingDays(
        string $from,
        string $to,
        array $days,
        bool $excludeWeekend,
        int $expected
    ): void {
        $result = Date::countDaysMatch($from, $to, $days, $excludeWeekend);
        $this->assertSame($expected, $result);
    }

    public static function provideCountDaysMatchData(): array
    {
        return [
            'simple range' => [
                '2024-01-01',
                '2024-01-05',
                ['2024-01-02', '2024-01-04'],
                false,
                2,
            ],
            'exclude weekends' => [
                '2024-01-01',
                '2024-01-07',
                ['2024-01-06', '2024-01-07'],  // Saturday and Sunday.
                true,
                0,
            ],
            'no matches' => [
                '2024-01-01',
                '2024-01-05',
                ['2024-01-10'],
                false,
                0,
            ],
            'single day' => [
                '2024-01-01',
                '2024-01-01',
                ['2024-01-01'],
                false,
                1,
            ],
            'with weekends included' => [
                '2024-01-05',
                '2024-01-08',
                ['2024-01-06', '2024-01-07'],
                false,
                2,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideCountData')]
    public function shouldCountDays(
        string $from,
        ?string $to,
        int $expected
    ): void {
        if ($to === null) {
            Carbon::setTestNow('2024-01-15');
        }

        $result = Date::countDays($from, $to);
        $this->assertSame($expected, $result);

        if ($to === null) {
            Carbon::setTestNow();
        }
    }

    public static function provideCountData(): array
    {
        return [
            'specific range' => [
                '2024-01-01',
                '2024-01-15',
                14,
            ],
            'to today' => [
                '2024-01-01',
                null,
                14,
            ],
            'same day' => [
                '2024-01-15',
                '2024-01-15',
                0,
            ],
            'one day' => [
                '2024-01-14',
                '2024-01-15',
                1,
            ],
            'across months' => [
                '2023-12-15',
                '2024-01-15',
                31,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideCountMonthsData')]
    public function shouldCountMonths(
        Carbon|string|int $from,
        Carbon|string|int|null $to,
        int $expected
    ): void {
        if ($to === null) {
            Carbon::setTestNow('2024-01-15');
        }

        $result = Date::countMonths($from, $to);
        $this->assertSame($expected, $result);

        if ($to === null) {
            Carbon::setTestNow();
        }
    }

    public static function provideCountMonthsData(): array
    {
        return [
            'string dates' => [
                '2023-01-15',
                '2024-01-15',
                12,
            ],
            'period format' => [
                202301,
                202401,
                12,
            ],
            'mixed formats' => [
                202301,
                '2024-01-15',
                12,
            ],
            'to current date' => [
                '2023-01-15',
                null,
                12,
            ],
            'same month' => [
                '2024-01-01',
                '2024-01-31',
                0,
            ],
            'partial month' => [
                '2024-01-15',
                '2024-02-14',
                1,
            ],
        ];
    }
}
