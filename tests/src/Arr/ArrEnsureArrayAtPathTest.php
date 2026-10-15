<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport\Arr;

use Derafu\Support\Arr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arr::class)]
class ArrEnsureArrayAtPathTest extends TestCase
{
    public function testBasicConversion(): void
    {
        $data = [
            'Encabezado' => [
                'Detalle' => [
                    'Codigo' => '123',
                ],
            ],
        ];

        Arr::ensureArrayAtPath($data, 'Encabezado.Detalle');

        $this->assertSame(['Encabezado' => ['Detalle' => [['Codigo' => '123']]]], $data);
    }

    public function testAlreadyArray(): void
    {
        $data = [
            'Encabezado' => [
                'Detalle' => [
                    ['Codigo' => '123'],
                ],
            ],
        ];

        Arr::ensureArrayAtPath($data, 'Encabezado.Detalle');

        $this->assertSame(['Encabezado' => ['Detalle' => [['Codigo' => '123']]]], $data);
    }

    public function testNestedPath(): void
    {
        $data = [
            'Root' => [
                'Encabezado' => [
                    'Detalle' => [
                        'Codigo' => '456',
                    ],
                ],
            ],
        ];

        Arr::ensureArrayAtPath($data, 'Root.Encabezado.Detalle');

        $this->assertSame(['Root' => ['Encabezado' => ['Detalle' => [['Codigo' => '456']]]]], $data);
    }

    public function testEmptyPath(): void
    {
        $data = [];

        Arr::ensureArrayAtPath($data, 'Root.Encabezado.Detalle');

        $this->assertSame([], $data);
    }

    public function testEmptyArrayValue(): void
    {
        $data = [
            'Encabezado' => [
                'Detalle' => [],
            ],
        ];

        Arr::ensureArrayAtPath($data, 'Encabezado.Detalle');

        $this->assertSame(['Encabezado' => ['Detalle' => []]], $data);
    }
}
