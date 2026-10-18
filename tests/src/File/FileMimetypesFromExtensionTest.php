<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport\File;

use Derafu\Support\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
class FileMimetypesFromExtensionTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function knownExtensionProvider(): array
    {
        return [
            'csv' => ['csv', 'text/csv'],
            'jpg' => ['jpg', 'image/jpeg'],
            'jpeg' => ['jpeg', 'image/jpeg'],
            'png' => ['png', 'image/png'],
            'pdf' => ['pdf', 'application/pdf'],
            'txt' => ['txt', 'text/plain'],
        ];
    }

    #[Test]
    #[DataProvider('knownExtensionProvider')]
    public function shouldGiveTheTypesOfAKnownExtension(string $extension, string $expected): void
    {
        $this->assertContains($expected, File::mimetypesFromExtension($extension));
    }

    #[Test]
    public function shouldGiveTheAliasesOfAnExtension(): void
    {
        $types = File::mimetypesFromExtension('jpg');

        $this->assertContains('image/jpeg', $types);
        $this->assertContains('image/pjpeg', $types);
    }

    #[Test]
    public function shouldNotCareAboutTheCaseOrTheLeadingPoint(): void
    {
        $expected = File::mimetypesFromExtension('csv');

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, File::mimetypesFromExtension('CSV'));
        $this->assertSame($expected, File::mimetypesFromExtension('.csv'));
        $this->assertSame($expected, File::mimetypesFromExtension('.CSV'));
    }

    #[Test]
    public function shouldGiveAnEmptyListOfAnExtensionThatIsNotKnown(): void
    {
        $this->assertSame([], File::mimetypesFromExtension('thisisnotanextension'));
    }

    #[Test]
    public function shouldGiveAnEmptyListOfAnEmptyExtension(): void
    {
        $this->assertSame([], File::mimetypesFromExtension(''));
        $this->assertSame([], File::mimetypesFromExtension('.'));
    }

    #[Test]
    public function shouldOnlyTakeOffTheFirstLeadingPoint(): void
    {
        $this->assertSame([], File::mimetypesFromExtension('..csv'));
    }
}
