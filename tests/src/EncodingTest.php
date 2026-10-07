<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use Derafu\Support\Encoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Encoding::class)]
class EncodingTest extends TestCase
{
    private const LATIN1 = "El ni\xf1o cumpli\xf3 15 a\xf1os";

    private const UTF8 = 'El niño cumplió 15 años';

    #[Test]
    public function shouldConvertIso88591ToUtf8(): void
    {
        $this->assertSame(self::UTF8, Encoding::utf8encode(self::LATIN1));
    }

    #[Test]
    public function shouldConvertUtf8ToIso88591(): void
    {
        $this->assertSame(self::LATIN1, Encoding::utf8decode(self::UTF8));
    }

    #[Test]
    #[DataProvider('provideTextsThatAreAlreadyUtf8')]
    public function shouldNotConvertAgainATextThatIsAlreadyUtf8(string $text): void
    {
        // Any sequence of bytes is valid ISO-8859-1: converting a text that is
        // already UTF-8 would write `Ã±` instead of `ñ`.
        $this->assertSame($text, Encoding::utf8encode($text));
    }

    public static function provideTextsThatAreAlreadyUtf8(): array
    {
        return [
            'accents' => ['El niño cumplió 15 años'],
            'ñ alone' => ['ñ'],
            'symbols' => ['¡Hola Señor! € ©'],
            'other alphabets' => ['Привет, 你好'],
            'ascii' => ['Hello'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function shouldGiveTheSameResultAfterConvertingTwice(): void
    {
        $once = Encoding::utf8encode(self::LATIN1);

        $this->assertSame($once, Encoding::utf8encode($once));
        $this->assertSame(self::UTF8, Encoding::utf8encode($once));
    }

    #[Test]
    public function shouldConvertBackAndForth(): void
    {
        $this->assertSame(self::LATIN1, Encoding::utf8decode(Encoding::utf8encode(self::LATIN1)));
        $this->assertSame(self::UTF8, Encoding::utf8encode(Encoding::utf8decode(self::UTF8)));
    }

    #[Test]
    public function shouldNotConvertWhatIsNotUtf8WhenDecoding(): void
    {
        $this->assertSame(self::LATIN1, Encoding::utf8decode(self::LATIN1));
        $this->assertSame("\x80\x81\x82", Encoding::utf8decode("\x80\x81\x82"));
        $this->assertSame('', Encoding::utf8decode(''));
    }

    #[Test]
    public function shouldConvertTheTextsOfArraysAtAnyDepth(): void
    {
        $latin1 = ['a' => self::LATIN1, 'b' => ['c' => "caf\xe9", 'd' => 42, 'e' => null, 'f' => true]];
        $utf8 = ['a' => self::UTF8, 'b' => ['c' => 'café', 'd' => 42, 'e' => null, 'f' => true]];

        $this->assertSame($utf8, Encoding::utf8encode($latin1));
        $this->assertSame($latin1, Encoding::utf8decode($utf8));
        $this->assertSame($utf8, Encoding::utf8encode($utf8), 'A text that is UTF-8 inside an array is not converted again.');
    }

    #[Test]
    public function shouldConvertTheTextsOfObjectsInPlace(): void
    {
        $object = new stdClass();
        $object->name = "Mu\xf1oz";
        $object->age = 30;

        $result = Encoding::utf8encode($object);

        $this->assertSame($object, $result, 'The same instance is given back.');
        $this->assertSame('Muñoz', $object->name);
        $this->assertSame(30, $object->age);
    }

    #[Test]
    public function shouldGiveBackTheValuesThatAreNotTexts(): void
    {
        $this->assertSame(42, Encoding::utf8encode(42));
        $this->assertSame(1.5, Encoding::utf8decode(1.5));
        $this->assertNull(Encoding::utf8encode(null));
        $this->assertTrue(Encoding::utf8decode(true));
    }
}
