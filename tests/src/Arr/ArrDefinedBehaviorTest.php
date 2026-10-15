<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport\Arr;

use Derafu\Support\Arr;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * What the methods of Arr do with the values that they can not process, which
 * is what their documentation says.
 */
#[CoversClass(Arr::class)]
final class ArrDefinedBehaviorTest extends TestCase
{
    #[Test]
    public function shouldKeepTheKeysThatAreIntegersInNested(): void
    {
        $this->assertSame(
            [0 => 'a', 1 => 'b', 'x' => ['y' => 1]],
            Arr::nested([0 => 'a', 1 => 'b', 'x.y' => 1])
        );
    }

    #[Test]
    public function shouldReplaceAValueThatIsNotAnArrayInNested(): void
    {
        $this->assertSame(
            ['a' => ['b' => 2]],
            Arr::nested(['a' => 1, 'a.b' => 2])
        );
        $this->assertSame(
            ['a' => ['b' => 1, 'c' => 2]],
            Arr::nested(['a.b' => 1, 'a.c' => 2])
        );
    }

    #[Test]
    public function shouldSetAValueAtAPathWithoutModifyingTheArray(): void
    {
        $array = ['a' => ['b' => 1]];

        $this->assertSame(['a' => ['b' => 1, 'c' => 2]], Arr::setAtPath($array, 'a.c', 2));
        $this->assertSame(['a' => ['b' => 1, 'c' => ['d' => 2]]], Arr::setAtPath($array, ['a', 'c', 'd'], 2));
        $this->assertSame(['a' => ['b' => 1], 'x' => 3], Arr::setAtPath($array, 'x', 3));
        $this->assertSame(['a' => ['b' => 1]], $array);
    }

    #[Test]
    public function shouldWrapAValueAtAPathWithoutModifyingTheArray(): void
    {
        $array = ['a' => ['b' => 'x'], 'c' => ['d', 'e']];

        $this->assertSame(['a' => ['b' => ['x']], 'c' => ['d', 'e']], Arr::wrapAtPath($array, 'a.b'));
        $this->assertSame(['a' => ['b' => ['x']], 'c' => ['d', 'e']], Arr::wrapAtPath($array, ['a', 'b']));
        $this->assertSame($array, Arr::wrapAtPath($array, 'c'));
        $this->assertSame($array, Arr::wrapAtPath($array, 'x.y'));
        $this->assertSame(['a' => ['b' => 'x'], 'c' => ['d', 'e']], $array);
    }

    #[Test]
    public function shouldLeaveTheArrayAsItIsWhenALevelIsNotAnArray(): void
    {
        $data = ['a' => 'text'];
        Arr::ensureArrayAtPath($data, 'a.b');
        $this->assertSame(['a' => 'text'], $data);

        $data = ['a' => ['b' => 1]];
        Arr::ensureArrayAtPath($data, 'a.x');
        $this->assertSame(['a' => ['b' => 1]], $data);
    }

    #[Test]
    public function shouldReturnTheKeyThatDoesNotExistAsNullInGroupToTable(): void
    {
        $this->assertSame(
            [
                ['key1' => 1, 'key2' => null],
                ['key1' => 2, 'key2' => null],
                ['key1' => 3, 'key2' => null],
            ],
            Arr::groupToTable(['key1' => [1, 2, 3]], ['key1', 'key2'])
        );
    }

    #[Test]
    public function shouldReturnNoRowsWhenNoneOfTheKeysExists(): void
    {
        $this->assertSame([], Arr::groupToTable(['a' => [1]], ['x', 'y']));
        $this->assertSame([], Arr::groupToTable([]));
    }

    #[Test]
    public function shouldTurnTheKeyOfATableIntoAnArrayKey(): void
    {
        $this->assertSame(
            ['' => 'null', 1 => 'true', '2.7' => 'float', 3 => 'int', 'a' => 'string'],
            Arr::tableToAssociative([
                [null, 'null'],
                [true, 'true'],
                [2.7, 'float'],
                [3, 'int'],
                ['a', 'string'],
            ])
        );
    }

    #[Test]
    public function shouldRejectAKeyThatIsNotScalar(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The first column of row 1 must be a scalar or null to be a key.');

        Arr::tableToAssociative([['a', 1], [new stdClass(), 2]]);
    }

    #[Test]
    public function shouldWriteTheValuesThatHaveTextInXml(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return 'a<b';
            }
        };

        $xml = Arr::toXml(['s' => 'x&y', 'i' => 1, 'f' => 1.5, 't' => true, 'n' => null, 'o' => $text, 'list' => [1]]);

        $this->assertSame(
            '<root><s>x&amp;y</s><i>1</i><f>1.5</f><t>1</t><n/><o>a&lt;b</o><list><item>1</item></list></root>',
            str_replace(["<?xml version=\"1.0\"?>", "\n"], '', $xml)
        );
    }

    #[Test]
    public function shouldRejectAValueThatHasNoTextInXml(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The value of "a" can not be written as XML text, stdClass given.');

        Arr::toXml(['a' => new stdClass()]);
    }

    #[Test]
    public function shouldSkipTheItemsWithoutTheFieldOfTheParentInToTree(): void
    {
        $items = [
            1 => ['name' => 'root', 'parent' => null],
            2 => ['name' => 'no parent field'],
            3 => ['name' => 'child of the one that is skipped', 'parent' => 2],
        ];

        $this->assertSame(
            [1 => ['name' => 'root', 'children' => []]],
            Arr::toTree($items, 'parent', 'children')
        );
    }

    #[Test]
    public function shouldOmitTheChildrenThatAreNotAnArray(): void
    {
        $tree = [
            1 => ['name' => 'root', 'children' => [
                2 => ['name' => 'child'],
                3 => 'not an item',
            ]],
            4 => ['name' => 'text', 'children' => 'x'],
            5 => 'not an item',
            6 => ['other' => 'no name'],
        ];

        $this->assertSame(
            [
                1 => ['name' => 'root', 'level' => 0],
                2 => ['name' => 'child', 'level' => 1],
                4 => ['name' => 'text', 'level' => 0],
            ],
            Arr::treeToList($tree, 'name', 'children')
        );
    }
}
