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
class FileMimetypeTest extends TestCase
{
    private string $fixturesDir;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 2) . '/fixtures';
        $this->tempDir = sys_get_temp_dir() . '/derafu-support-' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::rmdir($this->tempDir);
    }

    #[Test]
    public function shouldDetectMimeType(): void
    {
        $fixturesDir = dirname(__DIR__, 2) . '/fixtures';

        $this->assertSame('text/plain', File::mimetype($fixturesDir . '/files/text.txt'));
        $this->assertSame('image/jpeg', File::mimetype($fixturesDir . '/files/image.jpeg'));
        $this->assertSame('application/pdf', File::mimetype($fixturesDir . '/files/document.pdf'));
    }

    #[Test]
    public function shouldReturnFalseOnNonexistentFile(): void
    {
        $this->assertFalse(File::mimetype('nonexistent.file'));
    }

    #[Test]
    public function shouldDetectTheTypeByTheContentAndNotByTheName(): void
    {
        $image = $this->tempDir . '/image-with-the-name-of-a-text.txt';
        copy($this->fixturesDir . '/files/image.jpeg', $image);

        $text = $this->tempDir . '/text-with-the-name-of-an-image.jpeg';
        copy($this->fixturesDir . '/files/text.txt', $text);

        $this->assertSame('image/jpeg', File::mimetype($image));
        $this->assertSame('text/plain', File::mimetype($text));
    }

    #[Test]
    public function shouldDetectTheTypeOfAFileWithoutExtension(): void
    {
        $file = $this->tempDir . '/document';
        copy($this->fixturesDir . '/files/document.pdf', $file);

        $this->assertSame('application/pdf', File::mimetype($file));
    }

    #[Test]
    public function shouldDetectAnEmptyFile(): void
    {
        $file = $this->tempDir . '/empty.txt';
        touch($file);

        $this->assertSame('application/x-empty', File::mimetype($file));
    }

    #[Test]
    public function shouldDetectBinaryContentThatHasNoKnownType(): void
    {
        $file = $this->tempDir . '/data.bin';
        file_put_contents($file, "\x00\x01\x02\x03\xff\xfe\xfd\xfc");

        $this->assertSame('application/octet-stream', File::mimetype($file));
    }

    #[Test]
    public function shouldFollowASymbolicLink(): void
    {
        $link = $this->tempDir . '/link.txt';
        symlink($this->fixturesDir . '/files/image.jpeg', $link);

        $this->assertSame('image/jpeg', File::mimetype($link));
    }

    #[Test]
    public function shouldReturnFalseOnABrokenSymbolicLink(): void
    {
        $link = $this->tempDir . '/broken.txt';
        symlink($this->tempDir . '/nonexistent.file', $link);

        $this->assertFalse(File::mimetype($link));
    }

    #[Test]
    public function shouldReturnFalseOnADirectory(): void
    {
        $this->assertFalse(File::mimetype($this->tempDir));
    }

    #[Test]
    public function shouldReturnFalseOnAFileThatCannotBeRead(): void
    {
        $file = $this->tempDir . '/unreadable.txt';
        file_put_contents($file, 'text');
        chmod($file, 0000);

        // The user that runs the tests can read it anyway (root, for example).
        if (is_readable($file)) {
            chmod($file, 0644);
            $this->markTestSkipped('The user can read files without permission.');
        }

        try {
            $this->assertFalse(File::mimetype($file));
        } finally {
            chmod($file, 0644);
        }
    }

    #[Test]
    public function shouldReturnFalseOnAnEmptyPath(): void
    {
        $this->assertFalse(File::mimetype(''));
    }
}
