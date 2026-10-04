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
use RuntimeException;

#[CoversClass(File::class)]
class FileSendTest extends TestCase
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
    public function shouldSendFile(): void
    {
        $file = $this->fixturesDir . '/files/text.txt';

        // Start output buffering to capture content.
        $this->expectOutputString(file_get_contents($file));

        File::send($file, sendHeaders: false);
    }

    #[Test]
    public function shouldFailOnNonexistentFile(): void
    {
        $this->expectException(RuntimeException::class);

        File::send('nonexistent.file');
    }

    #[Test]
    public function shouldFailOnUnreadableFile(): void
    {
        $file = $this->tempDir . '/unreadable.txt';
        file_put_contents($file, 'test');
        chmod($file, 0000);

        $this->expectException(RuntimeException::class);

        File::send($file);
    }
}
