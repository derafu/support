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
    public function shouldNameTheEntriesByTheirPathInsideADirectoryReachedThroughASymbolicLink(): void
    {
        // The real path of the files is not the path of the directory that was
        // given: the names must not depend on it.
        $real = $this->tempDir . '/the-real-directory-with-a-long-name';
        mkdir($real . '/sub', 0777, true);
        file_put_contents($real . '/file.txt', 'a');
        file_put_contents($real . '/sub/inner.txt', 'b');
        $link = $this->tempDir . '/link';
        symlink($real, $link);

        File::zip($link, $this->tempDir . '/link.zip');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->tempDir . '/link.zip'));
        $entries = $this->getZipEntries($zip);
        sort($entries);
        $this->assertSame(['file.txt', 'sub/inner.txt'], $entries);
        $zip->close();
    }

    #[Test]
    public function shouldNameTheEntriesTheSameWithATrailingSlash(): void
    {
        $source = $this->tempDir . '/with-slash';
        mkdir($source . '/sub', 0777, true);
        file_put_contents($source . '/file.txt', 'a');
        file_put_contents($source . '/sub/inner.txt', 'b');

        File::zip($source . '/', $this->tempDir . '/with-slash.zip');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->tempDir . '/with-slash.zip'));
        $entries = $this->getZipEntries($zip);
        sort($entries);
        $this->assertSame(['file.txt', 'sub/inner.txt'], $entries);
        $zip->close();
    }

    #[Test]
    public function shouldNotSendHttpHeadersWhenTheArchiveGoesToAFile(): void
    {
        $source = $this->tempDir . '/quiet';
        mkdir($source);
        file_put_contents($source . '/file.txt', 'a');

        // In the command line the headers are not sent, but Xdebug keeps the ones
        // that were asked for (also the ones of the tests that came before).
        $this->assertTrue(function_exists('xdebug_get_headers'), 'Xdebug is needed to see the headers.');
        $before = xdebug_get_headers();

        File::zip($source, $this->tempDir . '/quiet.zip');

        $this->assertSame($before, xdebug_get_headers());
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
