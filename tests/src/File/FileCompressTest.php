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
use ZipArchive;

#[CoversClass(File::class)]
class FileCompressTest extends TestCase
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
    public function shouldZipSingleFile(): void
    {
        $source = $this->fixturesDir . '/zip/single/file.txt';
        $dest = $this->tempDir . '/single.zip';

        File::zip($source, $dest);

        $this->assertTrue(file_exists($dest));
        $this->assertGreaterThan(0, filesize($dest));

        // Verify ZIP content.
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($dest));
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('file.txt', $zip->getNameIndex(0));
        $zip->close();
    }

    #[Test]
    public function shouldZipDirectory(): void
    {
        $source = $this->fixturesDir . '/zip/multiple';
        $dest = $this->tempDir . '/multiple.zip';

        File::zip($source, $dest);

        $this->assertTrue(file_exists($dest));
        $this->assertGreaterThan(0, filesize($dest));

        // Verify ZIP content.
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($dest));
        $this->assertSame(2, $zip->numFiles);
        $this->assertContains('file1.txt', $this->getZipEntries($zip));
        $this->assertContains('file2.txt', $this->getZipEntries($zip));
        $zip->close();
    }

    #[Test]
    public function shouldCompressFileWithoutDownload(): void
    {
        $source = $this->tempDir . '/testFile.txt';
        file_put_contents($source, 'Test content');

        File::compress($source, download: false);

        $this->assertFileExists($source . '.zip');
    }

    #[Test]
    public function shouldCompressDirectoryWithoutDownload(): void
    {
        $source = $this->tempDir . '/to-compress';
        mkdir($source);
        file_put_contents($source . '/test.txt', 'test content');

        File::compress($source, download: false);

        $this->assertFileExists($source . '.zip');
        $this->assertDirectoryExists($source);
    }

    #[Test]
    public function shouldCompressAndDelete(): void
    {
        // Create test directory with content.
        $source = $this->tempDir . '/to-delete';
        mkdir($source);
        file_put_contents($source . '/test.txt', 'test content');

        File::compress($source, false, true);

        $this->assertFalse(is_dir($source));
        $this->assertTrue(file_exists($source . '.zip'));
    }

    #[Test]
    public function shouldFailToCompressNonexistentFile(): void
    {
        $this->expectException(RuntimeException::class);

        File::compress('/path/to/nonexistent/file.txt', download: false);
    }

    /**
     * Helper method to get all entries in a ZIP file.
     *
     * @return list<string>
     */
    private function getZipEntries(ZipArchive $zip): array
    {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = (string) $zip->getNameIndex($i);
        }

        return $entries;
    }
}
