<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSupport;

use Derafu\Support\Ip;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ip::class)]
final class IpTest extends TestCase
{
    /**
     * @return array<string, array{string, int|null}>
     */
    public static function provideVersions(): array
    {
        return [
            'ipv4' => ['192.0.2.1', 4],
            'ipv4 zero' => ['0.0.0.0', 4],
            'ipv6' => ['2001:db8::1', 6],
            'ipv6 loopback' => ['::1', 6],
            'ipv6 uppercase' => ['2001:DB8::A', 6],
            'ipv4 mapped is ipv6' => ['::ffff:192.0.2.1', 6],
            'leading zeros can be octal' => ['010.0.0.1', null],
            'out of range' => ['256.0.0.1', null],
            'missing octet' => ['1.2.3', null],
            'with port' => ['192.0.2.1:80', null],
            'with prefix' => ['192.0.2.0/24', null],
            'in brackets' => ['[::1]', null],
            'with zone' => ['fe80::1%eth0', null],
            'with spaces' => [' 192.0.2.1', null],
            'a name' => ['localhost', null],
            'empty' => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('provideVersions')]
    public function theVersionIsTheOneOfTheAddress(string $ip, ?int $version): void
    {
        $this->assertSame($version, Ip::version($ip));
        $this->assertSame($version !== null, Ip::isValid($ip));
        $this->assertSame($version === 4, Ip::isIpv4($ip));
        $this->assertSame($version === 6, Ip::isIpv6($ip));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function provideNormalize(): array
    {
        return [
            'ipv4' => ['192.0.2.1', '192.0.2.1'],
            'ipv6 compressed and lowercase' => ['2001:DB8:0:0:0:0:0:1', '2001:db8::1'],
            'ipv6 with zeros' => ['2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1'],
            'ipv6 loopback long' => ['0:0:0:0:0:0:0:1', '::1'],
            'ipv4 mapped is ipv4' => ['::ffff:192.0.2.1', '192.0.2.1'],
            'ipv4 mapped in hex is ipv4' => ['::ffff:c000:201', '192.0.2.1'],
            'ipv4 mapped long is ipv4' => ['0:0:0:0:0:ffff:c000:201', '192.0.2.1'],
            'ipv4 compatible is not mapped' => ['::192.0.2.1', '::c000:201'],
            'not an address' => ['192.0.2.256', null],
            'a port is not part of it' => ['192.0.2.1:80', null],
        ];
    }

    #[Test]
    #[DataProvider('provideNormalize')]
    public function anAddressHasOneCanonicalForm(string $ip, ?string $expected): void
    {
        $this->assertSame($expected, Ip::normalize($ip));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function provideParse(): array
    {
        return [
            'ipv4' => ['192.0.2.1', '192.0.2.1'],
            'ipv4 with port' => ['192.0.2.1:8080', '192.0.2.1'],
            'ipv4 with port 65535' => ['192.0.2.1:65535', '192.0.2.1'],
            'ipv4 with a port that is too big' => ['192.0.2.1:65536', null],
            'ipv4 with a port that is not a number' => ['192.0.2.1:http', null],
            'ipv4 with an empty port' => ['192.0.2.1:', null],
            'ipv6' => ['2001:db8::1', '2001:db8::1'],
            'ipv6 ending in numbers is not a port' => ['::1:80', '::1:80'],
            'ipv6 with a single zero group is not compressed' => ['2001:db8:0:1:1:1:1:1', '2001:db8:0:1:1:1:1:1'],
            'ipv6 compresses the longest run of zeros' => ['2001:0:0:1:0:0:0:1', '2001:0:0:1::1'],
            'ipv6 compresses the first of two runs of the same size' => ['2001:0:0:1:0:0:1:1', '2001::1:0:0:1:1'],
            'ipv6 unspecified' => ['0:0:0:0:0:0:0:0', '::'],
            'ipv6 ending in zeros' => ['2001:db8:0:0:0:0:0:0', '2001:db8::'],
            'ipv6 in brackets' => ['[2001:DB8::1]', '2001:db8::1'],
            'ipv6 in brackets with port' => ['[2001:db8::1]:443', '2001:db8::1'],
            'ipv6 in brackets with a bad port' => ['[2001:db8::1]:x', null],
            'ipv6 in brackets with something else' => ['[2001:db8::1]x', null],
            'ipv6 with an open bracket' => ['[2001:db8::1', null],
            'ipv6 with zone' => ['fe80::1%eth0', 'fe80::1'],
            'ipv6 in brackets with zone and port' => ['[fe80::1%eth0]:80', 'fe80::1'],
            'quoted, as in Forwarded' => ['"[2001:db8::1]:4711"', '2001:db8::1'],
            'with spaces around' => ['  192.0.2.1  ', '192.0.2.1'],
            'ipv4 mapped' => ['[::ffff:192.0.2.1]:80', '192.0.2.1'],
            'ipv4 mapped without brackets' => ['::ffff:192.0.2.1', '192.0.2.1'],
            'ipv6 with an ipv4 at the end' => ['64:ff9b::192.0.2.1', '64:ff9b::c000:201'],
            'ipv4 with a zone is not an address' => ['192.0.2.1%eth0', null],
            'a name' => ['example.com', null],
            'a name with port' => ['example.com:80', null],
            'unknown, as proxies write it' => ['unknown', null],
            'obfuscated, as Forwarded allows' => ['_hidden', null],
            'empty' => ['', null],
            'only quotes' => ['""', null],
        ];
    }

    #[Test]
    #[DataProvider('provideParse')]
    public function theAddressIsTakenOutOfTheTextThatHasIt(string $address, ?string $expected): void
    {
        $this->assertSame($expected, Ip::parse($address));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function provideExpand(): array
    {
        return [
            'ipv6' => ['2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001'],
            'ipv6 loopback' => ['::1', '0000:0000:0000:0000:0000:0000:0000:0001'],
            'ipv6 unspecified' => ['::', '0000:0000:0000:0000:0000:0000:0000:0000'],
            'ipv6 uppercase' => ['FE80::ABCD', 'fe80:0000:0000:0000:0000:0000:0000:abcd'],
            'ipv4' => ['192.0.2.1', '192.0.2.1'],
            'ipv4 mapped is ipv4' => ['::ffff:192.0.2.1', '192.0.2.1'],
            'not an address' => ['nope', null],
        ];
    }

    #[Test]
    #[DataProvider('provideExpand')]
    public function anAddressCanBeGivenInItsLongForm(string $ip, ?string $expected): void
    {
        $this->assertSame($expected, Ip::expand($ip));
    }

    /**
     * @return array<string, array{string, bool, bool, bool, bool, bool}>
     */
    public static function provideClasses(): array
    {
        // Address, private, reserved, loopback, link-local, public.
        return [
            'class A private 10.0.0.1' => ['10.0.0.1', true, false, false, false, false],
            'class A private 10.255.255.255' => ['10.255.255.255', true, false, false, false, false],
            'class B private 172.16.0.1' => ['172.16.0.1', true, false, false, false, false],
            'class B private 172.31.255.255' => ['172.31.255.255', true, false, false, false, false],
            'just outside class B 172.32.0.1' => ['172.32.0.1', false, false, false, false, true],
            'class C private 192.168.0.1' => ['192.168.0.1', true, false, false, false, false],
            'unique local ipv6' => ['fd00::1', true, false, false, false, false],
            'loopback' => ['127.0.0.1', false, true, true, false, false],
            'loopback end' => ['127.255.255.255', false, true, true, false, false],
            'ipv6 loopback' => ['::1', false, true, true, false, false],
            'this network' => ['0.0.0.0', false, true, false, false, false],
            'ipv6 unspecified' => ['::', false, true, false, false, false],
            'link-local' => ['169.254.10.1', false, true, false, true, false],
            'ipv6 link-local' => ['fe80::1', false, true, false, true, false],
            'future use' => ['240.0.0.1', false, true, false, false, false],
            'public 8.8.8.8' => ['8.8.8.8', false, false, false, false, true],
            'public 1.1.1.1' => ['1.1.1.1', false, false, false, false, true],
            'public ipv6' => ['2606:4700::1111', false, false, false, false, true],
            'documentation is public for PHP' => ['203.0.113.7', false, false, false, false, true],
            'private as ipv6 mapped' => ['::ffff:10.0.0.1', true, false, false, false, false],
            'loopback as ipv6 mapped' => ['::ffff:127.0.0.1', false, true, true, false, false],
            'public as ipv6 mapped' => ['::ffff:8.8.8.8', false, false, false, false, true],
            'not an address' => ['not-an-ip', false, false, false, false, false],
            'empty' => ['', false, false, false, false, false],
            'with a port is not an address' => ['10.0.0.1:80', false, false, false, false, false],
        ];
    }

    #[Test]
    #[DataProvider('provideClasses')]
    public function anAddressIsPrivateReservedLoopbackLinkLocalOrPublic(
        string $ip,
        bool $private,
        bool $reserved,
        bool $loopback,
        bool $linkLocal,
        bool $public
    ): void {
        $this->assertSame($private, Ip::isPrivate($ip), 'private');
        $this->assertSame($reserved, Ip::isReserved($ip), 'reserved');
        $this->assertSame($loopback, Ip::isLoopback($ip), 'loopback');
        $this->assertSame($linkLocal, Ip::isLinkLocal($ip), 'link-local');
        $this->assertSame($public, Ip::isPublic($ip), 'public');
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function provideInRange(): array
    {
        return [
            'in a network' => ['10.1.2.3', '10.0.0.0/8', true],
            'out of a network' => ['11.0.0.1', '10.0.0.0/8', false],
            'first of a network' => ['192.168.1.0', '192.168.1.0/24', true],
            'last of a network' => ['192.168.1.255', '192.168.1.0/24', true],
            'just after a network' => ['192.168.2.0', '192.168.1.0/24', false],
            'prefix that is not a multiple of eight' => ['172.31.255.255', '172.16.0.0/12', true],
            'just after a prefix that is not a multiple of eight' => ['172.32.0.0', '172.16.0.0/12', false],
            'bits of the host in the range are ignored' => ['10.9.9.9', '10.1.2.3/8', true],
            'prefix zero is everything' => ['203.0.113.7', '0.0.0.0/0', true],
            'prefix 32 is one address' => ['192.0.2.1', '192.0.2.1/32', true],
            'prefix 32 is not the next one' => ['192.0.2.2', '192.0.2.1/32', false],
            'an address alone is a range of one' => ['192.0.2.1', '192.0.2.1', true],
            'an address alone is not another one' => ['192.0.2.2', '192.0.2.1', false],
            'ipv6 in a network' => ['2001:db8:1::5', '2001:db8::/32', true],
            'ipv6 out of a network' => ['2001:db9::1', '2001:db8::/32', false],
            'ipv6 prefix that is not a multiple of eight' => ['fd12:3456::1', 'fc00::/7', true],
            'ipv6 prefix 128' => ['::1', '::1/128', true],
            'ipv6 alone' => ['2001:db8::1', '2001:DB8:0:0:0:0:0:1', true],
            'ipv6 prefix zero is every ipv6' => ['2001:db8::1', '::/0', true],
            'an ipv4 is not in a range of ipv6' => ['192.0.2.1', '::/0', false],
            'an ipv6 is not in a range of ipv4' => ['2001:db8::1', '0.0.0.0/0', false],
            'an ipv4 mapped is in the ranges of ipv4' => ['::ffff:10.1.2.3', '10.0.0.0/8', true],
            'an ipv4 mapped is not in the others' => ['::ffff:11.1.2.3', '10.0.0.0/8', false],
            'an ipv4 mapped is in the range of the mapped ones' => ['::ffff:10.1.2.3', '::ffff:0:0/96', true],
            'an ipv4 is in the range of the mapped ones only as ipv6' => ['10.1.2.3', '::ffff:0:0/96', false],
            'spaces around the range' => ['10.1.2.3', ' 10.0.0.0/8 ', true],
            'not an address is in no range' => ['nope', '10.0.0.0/8', false],
            'not an address is not in everything' => ['nope', '0.0.0.0/0', false],
        ];
    }

    #[Test]
    #[DataProvider('provideInRange')]
    public function anAddressIsInARangeOrIsNot(string $ip, string $range, bool $expected): void
    {
        $this->assertSame($expected, Ip::inRange($ip, $range));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideWrongRanges(): array
    {
        return [
            'empty' => [''],
            'a name' => ['localhost'],
            'a prefix without address' => ['/24'],
            'no prefix after the bar' => ['10.0.0.0/'],
            'prefix too big for ipv4' => ['10.0.0.0/33'],
            'prefix too big for ipv6' => ['2001:db8::/129'],
            'negative prefix' => ['10.0.0.0/-1'],
            'prefix that is not a number' => ['10.0.0.0/a'],
            'prefix with a sign' => ['10.0.0.0/+8'],
            'prefix with a netmask' => ['10.0.0.0/255.0.0.0'],
            'prefix with decimals' => ['10.0.0.0/8.5'],
            'address that is not valid' => ['10.0.0.256/8'],
            'two bars' => ['10.0.0.0/8/9'],
        ];
    }

    #[Test]
    #[DataProvider('provideWrongRanges')]
    public function aRangeThatIsNotValidIsAnErrorEvenIfTheAddressIsNotOne(string $range): void
    {
        $this->assertFalse(Ip::isRange($range));

        foreach (['192.0.2.1', 'not-an-ip'] as $ip) {
            try {
                Ip::inRange($ip, $range);
                $this->fail('The range "' . $range . '" is not valid.');
            } catch (TranslatableInvalidArgumentException $e) {
                $this->assertInstanceOf(TranslatableInterface::class, $e);
                $this->assertSame('The range "' . $range . '" is not valid.', $e->getMessage());
            }
        }
    }

    #[Test]
    public function theRangesThatAreValidAreRanges(): void
    {
        foreach (['10.0.0.0/8', '10.0.0.0/0', '10.0.0.0/32', '192.0.2.1', '::/0', '2001:db8::/128', '::1'] as $range) {
            $this->assertTrue(Ip::isRange($range), $range);
        }
    }

    #[Test]
    public function anAddressIsInAnyOfTheRanges(): void
    {
        $this->assertTrue(Ip::inAnyRange('192.168.1.1', ['10.0.0.0/8', '192.168.0.0/16']));
        $this->assertTrue(Ip::inAnyRange('10.0.0.1', ['10.0.0.0/8', '192.168.0.0/16']));
        $this->assertFalse(Ip::inAnyRange('8.8.8.8', ['10.0.0.0/8', '192.168.0.0/16']));
        $this->assertFalse(Ip::inAnyRange('8.8.8.8', []));
        $this->assertFalse(Ip::inAnyRange('nope', ['0.0.0.0/0']));
        $this->assertTrue(Ip::inAnyRange('10.0.0.1', (static function () {
            yield '10.0.0.0/8';
        })()));
    }

    #[Test]
    public function aWrongRangeIsAnErrorEvenAfterTheOneThatMatches(): void
    {
        // A configuration that is wrong does not wait for an address that falls
        // after it: it is found the first time.
        $this->expectException(TranslatableInvalidArgumentException::class);

        Ip::inAnyRange('10.0.0.1', ['10.0.0.0/8', '10.0.0.0/99']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function provideRanges(): array
    {
        return [
            'class A' => ['10.0.0.0/8', '10.0.0.0', '10.255.255.255'],
            'class C' => ['192.168.1.0/24', '192.168.1.0', '192.168.1.255'],
            'bits of the host are ignored' => ['192.168.1.77/24', '192.168.1.0', '192.168.1.255'],
            'prefix that is not a multiple of eight' => ['172.16.0.0/12', '172.16.0.0', '172.31.255.255'],
            'one address' => ['192.0.2.1/32', '192.0.2.1', '192.0.2.1'],
            'an address alone' => ['192.0.2.1', '192.0.2.1', '192.0.2.1'],
            'everything' => ['0.0.0.0/0', '0.0.0.0', '255.255.255.255'],
            'ipv6' => ['2001:db8::/32', '2001:db8::', '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff'],
            'ipv6 prefix 64' => ['2001:db8:1:2::/64', '2001:db8:1:2::', '2001:db8:1:2:ffff:ffff:ffff:ffff'],
            'ipv6 unique local' => ['fc00::/7', 'fc00::', 'fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            'ipv6 one address' => ['::1/128', '::1', '::1'],
        ];
    }

    #[Test]
    #[DataProvider('provideRanges')]
    public function aRangeHasAFirstAndALastAddress(string $range, string $first, string $last): void
    {
        $this->assertSame([$first, $last], Ip::range($range));
    }

    #[Test]
    public function theRangeThatIsAskedToBeGivenMustBeValid(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        Ip::range('10.0.0.0/40');
    }

    /**
     * @return array<string, array{string, int, int, string|null}>
     */
    public static function provideNetworks(): array
    {
        // Address, prefix of ipv4, prefix of ipv6, network.
        return [
            'ipv4 by default is the address' => ['192.0.2.77', 32, 64, '192.0.2.77'],
            'ipv4 with a prefix' => ['192.0.2.77', 24, 64, '192.0.2.0'],
            'ipv4 with a prefix that is not a multiple of eight' => ['172.20.5.5', 12, 64, '172.16.0.0'],
            'ipv4 with prefix zero' => ['192.0.2.77', 0, 64, '0.0.0.0'],
            'ipv6 by default is the /64' => ['2001:db8:1:2:3:4:5:6', 32, 64, '2001:db8:1:2::'],
            'ipv6 with another prefix' => ['2001:db8:1:2:3:4:5:6', 32, 48, '2001:db8:1::'],
            'ipv6 with a prefix that is not a multiple of eight' => ['2001:db8:ffff::1', 32, 36, '2001:db8:f000::'],
            'ipv6 with prefix 128' => ['2001:DB8::1', 32, 128, '2001:db8::1'],
            'the prefix of the other version is not used' => ['2001:db8::1', 99, 32, '2001:db8::'],
            'ipv4 mapped is ipv4' => ['::ffff:192.0.2.77', 24, 64, '192.0.2.0'],
            'not an address' => ['nope', 32, 64, null],
        ];
    }

    #[Test]
    #[DataProvider('provideNetworks')]
    public function anAddressBelongsToANetwork(string $ip, int $ipv4Prefix, int $ipv6Prefix, ?string $expected): void
    {
        $this->assertSame($expected, Ip::network($ip, $ipv4Prefix, $ipv6Prefix));
    }

    #[Test]
    public function theNetworkOfAnAddressIsTheSameForTheHostsOfThatNetwork(): void
    {
        $this->assertSame(Ip::network('2001:db8:1:2::1'), Ip::network('2001:db8:1:2:ffff::9'));
        $this->assertNotSame(Ip::network('2001:db8:1:2::1'), Ip::network('2001:db8:1:3::1'));
    }

    /**
     * @return array<string, array{string, int, int, string|null}>
     */
    public static function provideCidrs(): array
    {
        // Address, prefix of ipv4, prefix of ipv6, network in CIDR notation.
        return [
            'ipv4 by default is a range of one' => ['100.100.100.100', 32, 64, '100.100.100.100/32'],
            'ipv4 with a prefix' => ['203.0.113.77', 24, 64, '203.0.113.0/24'],
            'ipv4 with a prefix that is not a multiple of eight' => ['172.20.5.5', 12, 64, '172.16.0.0/12'],
            'ipv4 with prefix zero' => ['203.0.113.77', 0, 64, '0.0.0.0/0'],
            'ipv6 by default is the /64' => ['2001:db8:1:2:3:4:5:6', 32, 64, '2001:db8:1:2::/64'],
            'ipv6 with another prefix' => ['2001:db8:1:2:3:4:5:6', 32, 48, '2001:db8:1::/48'],
            'ipv6 with prefix 128' => ['2001:DB8::1', 32, 128, '2001:db8::1/128'],
            'ipv4 written as ipv6 is ipv4' => ['::ffff:203.0.113.77', 24, 64, '203.0.113.0/24'],
            'an address of the shared range is its own network' => ['100.100.100.100', 10, 64, '100.64.0.0/10'],
            'not an address' => ['nope', 32, 64, null],
        ];
    }

    #[Test]
    #[DataProvider('provideCidrs')]
    public function aNetworkCanBeWrittenAsARange(string $ip, int $ipv4Prefix, int $ipv6Prefix, ?string $expected): void
    {
        $this->assertSame($expected, Ip::cidr($ip, $ipv4Prefix, $ipv6Prefix));
    }

    #[Test]
    public function theNetworkAsARangeContainsTheAddressAndIsAValidRange(): void
    {
        foreach (['203.0.113.77', '2001:db8:1:2:3:4:5:6', '::ffff:10.1.2.3'] as $ip) {
            $cidr = (string) Ip::cidr($ip, 24, 48);

            $this->assertTrue(Ip::isRange($cidr), $cidr);
            $this->assertTrue(Ip::inRange($ip, $cidr), $cidr);
        }
    }

    #[Test]
    public function theNetworkAsARangeWithTheDefaultsIsTheNetworkWithItsPrefix(): void
    {
        $this->assertSame(Ip::network('203.0.113.77') . '/32', Ip::cidr('203.0.113.77'));
        $this->assertSame(Ip::network('2001:db8:1:2:3:4:5:6') . '/64', Ip::cidr('2001:db8:1:2:3:4:5:6'));
    }

    #[Test]
    #[DataProvider('provideWrongPrefixes')]
    public function aPrefixThatIsNotValidForTheVersionIsAnErrorAlsoWhenWritingTheNetworkAsARange(string $ip, int $ipv4Prefix, int $ipv6Prefix): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        Ip::cidr($ip, $ipv4Prefix, $ipv6Prefix);
    }

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function provideWrongPrefixes(): array
    {
        return [
            'ipv4 too big' => ['192.0.2.1', 33, 64],
            'ipv4 negative' => ['192.0.2.1', -1, 64],
            'ipv6 too big' => ['2001:db8::1', 32, 129],
            'ipv6 negative' => ['2001:db8::1', 32, -1],
        ];
    }

    #[Test]
    #[DataProvider('provideWrongPrefixes')]
    public function aPrefixThatIsNotValidForTheVersionIsAnError(string $ip, int $ipv4Prefix, int $ipv6Prefix): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        Ip::network($ip, $ipv4Prefix, $ipv6Prefix);
    }

    #[Test]
    public function theMessageOfAWrongPrefixSaysThePrefixAndTheVersion(): void
    {
        try {
            Ip::network('2001:db8::1', 32, 200);
            $this->fail('The prefix is not valid.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The prefix 200 is not valid for an IPv6 address.', $e->getMessage());
        }

        try {
            Ip::network('192.0.2.1', 40);
            $this->fail('The prefix is not valid.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The prefix 40 is not valid for an IPv4 address.', $e->getMessage());
        }
    }

    #[Test]
    public function addressesAreSortedByTheirValue(): void
    {
        $this->assertSame(-1, Ip::compare('10.0.0.1', '10.0.0.2'));
        $this->assertSame(1, Ip::compare('10.0.0.10', '10.0.0.2'), 'By number, not by text.');
        $this->assertSame(0, Ip::compare('10.0.0.1', '10.0.0.1'));
        $this->assertSame(-1, Ip::compare('9.255.255.255', '10.0.0.0'));
        $this->assertSame(-1, Ip::compare('255.255.255.255', '::'), 'IPv4 before IPv6.');
        $this->assertSame(1, Ip::compare('::', '255.255.255.255'));
        $this->assertSame(0, Ip::compare('2001:db8::1', '2001:0DB8:0:0:0:0:0:1'));
        $this->assertSame(-1, Ip::compare('2001:db8::1', '2001:db8::2'));
        $this->assertSame(0, Ip::compare('::ffff:192.0.2.1', '192.0.2.1'));

        $ips = ['2001:db8::2', '10.0.0.10', '10.0.0.2', '2001:db8::1', '9.9.9.9'];
        usort($ips, Ip::compare(...));
        $this->assertSame(['9.9.9.9', '10.0.0.2', '10.0.0.10', '2001:db8::1', '2001:db8::2'], $ips);
    }

    #[Test]
    public function comparingSomethingThatIsNotAnAddressIsAnError(): void
    {
        try {
            Ip::compare('nope', '10.0.0.1');
            $this->fail('The first is not an address.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The value "nope" is not an IP address.', $e->getMessage());
        }

        try {
            Ip::compare('10.0.0.1', 'also-nope');
            $this->fail('The second is not an address.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The value "also-nope" is not an IP address.', $e->getMessage());
        }
    }

    #[Test]
    public function anAddressCanBeGivenAsBytesAndBack(): void
    {
        $this->assertSame("\xc0\x00\x02\x01", Ip::toBinary('192.0.2.1'));
        $this->assertSame(16, strlen((string) Ip::toBinary('2001:db8::1')));
        $this->assertNull(Ip::toBinary('nope'));

        $this->assertSame('192.0.2.1', Ip::fromBinary("\xc0\x00\x02\x01"));
        $this->assertSame('2001:db8::1', Ip::fromBinary((string) Ip::toBinary('2001:db8::1')));
        $this->assertSame('::ffff:c000:201', Ip::fromBinary((string) Ip::toBinary('::ffff:192.0.2.1')));
        $this->assertNull(Ip::fromBinary('abc'));
        $this->assertNull(Ip::fromBinary(''));
    }
}
