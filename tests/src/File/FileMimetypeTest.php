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
}
