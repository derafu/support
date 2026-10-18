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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(File::class)]
class FileMimetypeFromContentTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 2) . '/fixtures';
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents($this->fixturesDir . '/files/' . $name);
        $this->assertIsString($content);

        return $content;
    }

    #[Test]
    public function shouldDetectTheTypeOfTheContent(): void
    {
        $this->assertSame('image/jpeg', File::mimetypeFromContent($this->fixture('image.jpeg')));
        $this->assertSame('application/pdf', File::mimetypeFromContent($this->fixture('document.pdf')));
        $this->assertSame('text/plain', File::mimetypeFromContent($this->fixture('text.txt')));
    }

    #[Test]
    public function shouldDetectAPng(): void
    {
        // An image of 1x1 pixel.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        $this->assertSame('image/png', File::mimetypeFromContent($png));
    }

    #[Test]
    public function shouldDetectTheTypeWithOnlyTheStartOfTheContent(): void
    {
        $this->assertSame('image/jpeg', File::mimetypeFromContent(substr($this->fixture('image.jpeg'), 0, 512)));
        $this->assertSame('application/pdf', File::mimetypeFromContent(substr($this->fixture('document.pdf'), 0, 64)));
    }

    #[Test]
    public function shouldGiveTheSameTypeAsTheFile(): void
    {
        foreach (['image.jpeg', 'document.pdf', 'text.txt'] as $name) {
            $this->assertSame(
                File::mimetype($this->fixturesDir . '/files/' . $name),
                File::mimetypeFromContent($this->fixture($name))
            );
        }
    }

    #[Test]
    public function shouldDetectAnEmptyContent(): void
    {
        $this->assertSame('application/x-empty', File::mimetypeFromContent(''));
    }

    #[Test]
    public function shouldDetectBinaryContentThatHasNoKnownType(): void
    {
        $this->assertSame(
            'application/octet-stream',
            File::mimetypeFromContent("\x00\x01\x02\x03\xff\xfe\xfd\xfc")
        );
    }
}
