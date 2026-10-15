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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
        $this->expectOutputString((string) file_get_contents($file));

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

    #[Test]
    #[RunInSeparateProcess]
    public function shouldSendTheHeadersOfTheDownloadAndDeleteTheFile(): void
    {
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('xdebug is needed to read the headers that were sent.');
        }

        $file = $this->tempDir . '/download.txt';
        file_put_contents($file, 'content');

        ob_start();
        File::send($file, delete: true);
        $output = ob_get_clean();

        $headers = xdebug_get_headers();
        $this->assertSame('content', $output);
        $this->assertCount(
            1,
            array_filter($headers, static fn (string $h): bool => stripos($h, 'Content-Type: text/plain') === 0),
            'PHP adds the charset to the header, so only its start is compared.'
        );
        $this->assertContains('Content-Disposition: attachment; filename="download.txt"', $headers);
        $this->assertContains('Content-Length: 7', $headers);
        $this->assertContains('Pragma: no-cache', $headers);
        $this->assertFileDoesNotExist($file);
    }

    #[Test]
    public function shouldNotSendTheDownloadWhenTheHeadersWereSent(): void
    {
        // The headers are sent by the first output of the process, which is
        // not possible to cause inside PHPUnit, so it is a process of its own.
        $file = $this->tempDir . '/download.txt';
        file_put_contents($file, 'content');
        $script = $this->tempDir . '/send.php';
        file_put_contents($script, sprintf(
            '<?php require %s; echo "x\n"; try { %s::send(%s); } catch (Throwable $e) { echo $e->getMessage(); }',
            var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true),
            File::class,
            var_export($file, true)
        ));

        $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');

        $this->assertSame("x\nHeaders have already been sent.", $output);
    }
}
