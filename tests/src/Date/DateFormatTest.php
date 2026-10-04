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
class DateFormatTest extends TestCase
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
    #[DataProvider('provideAgoData')]
    public function shouldFormatTimeAgo(
        string $datetime,
        bool $full,
        string $expected
    ): void {
        // Fix the "now" time for testing.
        Carbon::setTestNow('2024-01-15 12:00:00');

        $result = Date::agoSpanish($datetime, $full);
        $this->assertSame($expected, $result);

        Carbon::setTestNow(); // Reset time.
    }

    public static function provideAgoData(): array
    {
        return [
            'just now' => [
                '2024-01-15 12:00:00',
                false,
                'recién',
            ],
            'minutes ago' => [
                '2024-01-15 11:45:00',
                false,
                'hace 15 minutos',
            ],
            'hours and minutes' => [
                '2024-01-15 10:45:00',
                true,
                'hace 1 hora, 15 minutos',
            ],
            'one day' => [
                '2024-01-14 12:00:00',
                false,
                'hace 1 día',
            ],
            'days and hours' => [
                '2024-01-13 10:00:00',
                true,
                'hace 2 días, 2 horas',
            ],
            'one week' => [
                '2024-01-08 12:00:00',
                false,
                'hace 1 semana',
            ],
            'one month' => [
                '2023-12-15 12:00:00',
                false,
                'hace 1 mes',
            ],
            'multiple months' => [
                '2023-11-15 12:00:00',
                false,
                'hace 2 meses',
            ],
            'one year' => [
                '2023-01-15 12:00:00',
                false,
                'hace 1 año',
            ],
            'full details' => [
                '2023-01-10 10:30:00',
                true,
                'hace 1 año, 5 días, 1 hora, 30 minutos',
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideSpanishFormatData')]
    public function shouldFormatDatesInSpanish(
        string $date,
        bool $includeDay,
        string $expected
    ): void {
        $result = Date::formatSpanish($date, $includeDay);
        $this->assertSame($expected, $result);
    }

    public static function provideSpanishFormatData(): array
    {
        return [
            'with day' => [
                '2024-01-15',
                true,
                'Lunes, 15 de Enero del 2024',
            ],
            'without day' => [
                '2024-01-15',
                false,
                '15 de Enero del 2024',
            ],
            'special month' => [
                '2024-02-15',
                true,
                'Jueves, 15 de Febrero del 2024',
            ],
        ];
    }
}
