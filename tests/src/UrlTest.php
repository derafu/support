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

use Derafu\Support\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Url::class)]
final class UrlTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function providePaths(): array
    {
        return [
            // What is already canonical.
            'the root' => ['/', '/'],
            'a path' => ['/api/index', '/api/index'],
            'a deeper path' => ['/a/b/c/d', '/a/b/c/d'],
            'the case is kept' => ['/API/Index', '/API/Index'],
            'characters that need no escape' => ['/a-b_c.d~e', '/a-b_c.d~e'],
            'a segment with a dot in it' => ['/index.html', '/index.html'],
            'a segment that starts with a dot' => ['/.well-known/acme', '/.well-known/acme'],
            'a plus is a plus' => ['/a+b', '/a+b'],

            // What means the same and is written another way.
            'nothing is the root' => ['', '/'],
            'no slash at the start' => ['api/index', '/api/index'],
            'a slash at the end' => ['/api/index/', '/api/index'],
            'several slashes at the end' => ['/api/index///', '/api/index'],
            'a double slash at the start' => ['//api/index', '/api/index'],
            'a double slash in the middle' => ['/api//index', '/api/index'],
            'many slashes' => ['///api////index//', '/api/index'],
            'only slashes' => ['////', '/'],
            'a dot segment' => ['/api/./index', '/api/index'],
            'a dot segment at the start' => ['/./api', '/api'],
            'a dot segment at the end' => ['/api/.', '/api'],
            'an escaped letter' => ['/api/%69ndex', '/api/index'],
            'an escaped capital letter' => ['/api/%49ndex', '/api/Index'],
            'an escaped number' => ['/v%31', '/v1'],
            'an escaped tilde' => ['/%7Euser', '/~user'],
            'an escaped hyphen, dot and underscore' => ['/a%2Db%2Ec%5Fd', '/a-b.c_d'],
            'an escaped dot segment is a dot segment' => ['/a/%2E/b', '/a/b'],
            'an escaped dot in a name' => ['/index%2Ehtml', '/index.html'],
            'the first and the last of each kind that needs no escape' => ['/%30%39%41%5A%61%7A', '/09AZaz'],
            'the characters next to them stay escaped' => ['/%2C%3a%40%5b%60%7b', '/%2C%3A%40%5B%60%7B'],
            'a reserved character stays escaped' => ['/a%3Fb%23c%7Cd', '/a%3Fb%23c%7Cd'],
            'the escapes that stay are in capitals' => ['/ma%c3%b1ana', '/ma%C3%B1ana'],
            'an escaped space stays' => ['/a%20b', '/a%20b'],
            'an escaped percent is not decoded again' => ['/a%25b', '/a%25b'],
            'an escaped separator that was escaped again is text' => ['/a%252Fb', '/a%252Fb'],
            'a raw space stays' => ['/a b', '/a b'],
            'a raw unicode character stays' => ['/mañana', '/mañana'],
            'a question mark is a character of the path' => ['/a?b', '/a?b'],
            'a hash is a character of the path' => ['/a#b', '/a#b'],

            // What has no safe form.
            'a parent segment' => ['/a/../b', null],
            'a parent segment at the start' => ['/../a', null],
            'a parent segment at the end' => ['/a/..', null],
            'only a parent segment' => ['..', null],
            'an escaped parent segment' => ['/a/%2e%2e/b', null],
            'a half escaped parent segment' => ['/a/.%2E/b', null],
            'an escaped slash' => ['/a%2Fb', null],
            'an escaped slash in lowercase' => ['/a%2fb', null],
            'an escaped backslash' => ['/a%5Cb', null],
            'an escaped backslash in lowercase' => ['/a%5cb', null],
            'a backslash' => ['/a\\b', null],
            'a backslash that climbs' => ['/a\\..\\b', null],
            'a null byte' => ["/a\0b", null],
            'an escaped null byte' => ['/a%00b', null],
            'a control character' => ["/a\x01b", null],
            'a new line' => ["/a\nb", null],
            'an escaped control character' => ['/a%1Fb', null],
            'an escaped delete' => ['/a%7Fb', null],
            'a delete' => ["/a\x7fb", null],
            'an escape that is not valid' => ['/a%zzb', null],
            'an escape with one digit' => ['/a%4', null],
            'an escape with one digit before a segment' => ['/a%4/b', null],
            'a percent at the end' => ['/a%', null],
            'a percent alone' => ['%', null],
            'an escape with a letter that is not a digit' => ['/a%G1', null],
        ];
    }

    #[Test]
    #[DataProvider('providePaths')]
    public function aPathHasOneCanonicalFormOrNone(string $path, ?string $expected): void
    {
        $this->assertSame($expected, Url::normalizePath($path));
    }

    #[Test]
    #[DataProvider('providePaths')]
    public function theCanonicalFormOfAPathIsItsOwnCanonicalForm(string $path, ?string $expected): void
    {
        if ($expected === null) {
            $this->assertFalse(Url::isNormalizedPath($path));

            return;
        }

        $this->assertTrue(Url::isNormalizedPath($expected), $expected);
        $this->assertSame($path === $expected, Url::isNormalizedPath($path), $path);
    }

    #[Test]
    public function theWaysOfWritingAPathThatMeanTheSameHaveTheSameForm(): void
    {
        $forms = ['/api/index', '//api/index', '/api//index', '/api/./index', '/api/%69ndex', '/api/index/', 'api/index', '/api/x/../index'];

        $normalized = array_map(Url::normalizePath(...), $forms);

        // The one with the parent segment has no form: the others are one.
        $this->assertSame(['/api/index', '/api/index', '/api/index', '/api/index', '/api/index', '/api/index', '/api/index', null], $normalized);
    }

    /**
     * @return array<string, array{string, list<string>|null}>
     */
    public static function provideSegments(): array
    {
        return [
            'the root has none' => ['/', []],
            'nothing has none' => ['', []],
            'a path' => ['/api/index', ['api', 'index']],
            'a path written another way' => ['//api/./%69ndex/', ['api', 'index']],
            'a path without a safe form' => ['/a/../b', null],
        ];
    }

    /**
     * @param list<string>|null $expected
     */
    #[Test]
    #[DataProvider('provideSegments')]
    public function aPathHasSegments(string $path, ?array $expected): void
    {
        $this->assertSame($expected, Url::pathSegments($path));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function providePrefixes(): array
    {
        // Path, prefix, whether it is the prefix or is below it.
        return [
            'the same path' => ['/api', '/api', true],
            'below it' => ['/api/index', '/api', true],
            'much below it' => ['/api/index/x/y', '/api', true],
            'a prefix of two segments' => ['/api/human_resources/x', '/api/human_resources', true],
            'the root is the prefix of everything' => ['/api/index', '/', true],
            'the root is the prefix of the root' => ['/', '/', true],
            'the root is not below a path' => ['/', '/api', false],
            'not a segment boundary' => ['/apiary', '/api', false],
            'not a segment boundary, below it' => ['/apiary/x', '/api', false],
            'a longer prefix' => ['/api', '/api/index', false],
            'another path' => ['/other', '/api', false],
            'the same start of another segment' => ['/api/index2', '/api/index', false],
            'a slash at the end of the path' => ['/api/', '/api', true],
            'a slash at the end of the prefix' => ['/api/index', '/api/', true],
            'a double slash' => ['/api//index', '/api/index', true],
            'a double slash at the start' => ['//api/index', '/api', true],
            'a dot segment' => ['/api/./index', '/api/index', true],
            'an escaped letter' => ['/api/%69ndex', '/api/index', true],
            'an escaped letter in the prefix' => ['/api/index', '/%61pi', true],
            'a path that has no safe form' => ['/api/../x', '/api', false],
            'a path that hides a separator' => ['/api%2Findex', '/api', false],
            'a prefix that has no safe form' => ['/api/index', '/api/..', false],
            'a prefix that has no safe form, the root' => ['/', '/..', false],
            'the case is different' => ['/API/index', '/api', false],
        ];
    }

    #[Test]
    #[DataProvider('providePrefixes')]
    public function aPathIsBelowAPrefixBySegments(string $path, string $prefix, bool $expected): void
    {
        $this->assertSame($expected, Url::pathStartsWith($path, $prefix));
    }

    #[Test]
    public function theCaseCanBeIgnored(): void
    {
        $this->assertTrue(Url::pathStartsWith('/API/Index', '/api', caseSensitive: false));
        $this->assertTrue(Url::pathStartsWith('/api/index', '/API', caseSensitive: false));
        $this->assertTrue(Url::pathStartsWith('/ÁRBOL/x', '/árbol', caseSensitive: false));
        $this->assertTrue(Url::pathStartsWith('/API/index', '/', caseSensitive: false));

        // Ignoring the case does not ignore the boundary of the segments.
        $this->assertFalse(Url::pathStartsWith('/APIARY', '/api', caseSensitive: false));
        $this->assertFalse(Url::pathStartsWith('/other', '/api', caseSensitive: false));
        $this->assertFalse(Url::pathStartsWith('/API/../x', '/api', caseSensitive: false));
    }

    #[Test]
    public function theCaseIsKeptByDefault(): void
    {
        $this->assertFalse(Url::pathStartsWith('/API/x', '/api'));
        $this->assertFalse(Url::pathStartsWith('/ÁRBOL/x', '/árbol'));
        $this->assertTrue(Url::pathStartsWith('/ÁRBOL/x', '/ÁRBOL'));
    }
}
