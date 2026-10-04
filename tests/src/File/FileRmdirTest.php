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
class FileRmdirTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/derafu-support-' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::rmdir($this->tempDir);
    }

    #[Test]
    public function shouldRemoveDirectory(): void
    {
        $testDir = $this->tempDir . '/rmdir-test';
        mkdir($testDir);
        file_put_contents($testDir . '/file1.txt', 'test');
        mkdir($testDir . '/subdir');
        file_put_contents($testDir . '/subdir/file2.txt', 'test');

        $this->assertTrue(is_dir($testDir));

        File::rmdir($testDir);

        $this->assertFalse(is_dir($testDir));
    }

    /**
     * A symbolic link inside the directory is removed, never followed: what it
     * points to, outside of the directory, must stay.
     */
    #[Test]
    public function shouldNotFollowSymbolicLinksWhenRemovingADirectory(): void
    {
        $outside = $this->tempDir . '/rmdir-outside';
        mkdir($outside);
        file_put_contents($outside . '/precious.txt', 'keep');

        $testDir = $this->tempDir . '/rmdir-links';
        mkdir($testDir);
        file_put_contents($testDir . '/file.txt', 'test');
        symlink($outside, $testDir . '/link-to-directory');
        symlink($outside . '/precious.txt', $testDir . '/link-to-file');

        File::rmdir($testDir);

        $this->assertFalse(file_exists($testDir));
        $this->assertFileExists($outside . '/precious.txt');
        $this->assertSame('keep', file_get_contents($outside . '/precious.txt'));
    }
}
