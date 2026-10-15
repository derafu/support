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
final class FileWriteTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/derafu-support-' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // A directory that was made read-only must be writable to be removed.
        foreach (glob($this->tempDir . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            chmod($directory, 0755);
        }
        File::rmdir($this->tempDir);
    }

    #[Test]
    public function shouldWriteTheContentOfAFile(): void
    {
        $file = $this->tempDir . '/file.txt';

        File::write($file, 'content');

        $this->assertSame('content', file_get_contents($file));
        $this->assertSame(0666 & ~umask(), fileperms($file) & 0777);
    }

    #[Test]
    public function shouldReplaceTheContentOfAFileThatExists(): void
    {
        $file = $this->tempDir . '/file.txt';
        file_put_contents($file, 'old');

        File::write($file, 'new');

        $this->assertSame('new', file_get_contents($file));
        $this->assertSame([$file], glob($this->tempDir . '/*'), 'No temporary file is left.');
    }

    #[Test]
    public function shouldCreateTheDirectoriesThatAreMissing(): void
    {
        $file = $this->tempDir . '/a/b/file.txt';

        File::write($file, 'content');

        $this->assertSame('content', file_get_contents($file));
    }

    #[Test]
    public function shouldSetThePermissionsThatAreGiven(): void
    {
        $file = $this->tempDir . '/file.txt';

        File::write($file, 'content', 0640);

        $this->assertSame(0640, fileperms($file) & 0777);
    }

    #[Test]
    public function shouldFailWhenTheDirectoryCanNotBeCreated(): void
    {
        file_put_contents($this->tempDir . '/is-a-file', 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create directory (' . $this->tempDir . '/is-a-file/sub).');

        File::write($this->tempDir . '/is-a-file/sub/file.txt', 'content');
    }

    #[Test]
    public function shouldFailAndLeaveNoTemporaryFileWhenTheFileCanNotBeMoved(): void
    {
        $directory = $this->tempDir . '/read-only';
        mkdir($directory);
        chmod($directory, 0555);

        // PHP puts the temporary file in the system directory (and notices it)
        // when it can not be in the one that was asked, so the move fails.
        set_error_handler(static fn (): bool => true);

        try {
            File::write($directory . '/file.txt', 'content');
            $this->fail('The file can not be moved to a directory that is read-only.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Unable to move temporary file to target location (' . $directory . '/file.txt).',
                $e->getMessage()
            );
        } finally {
            restore_error_handler();
        }

        $this->assertFileDoesNotExist($directory . '/file.txt');
    }
}
