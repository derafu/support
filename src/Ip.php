<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Support;

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Utilities to work with IP addresses (IPv4 and IPv6) and with ranges of them
 * (CIDR).
 *
 * They are pure: they depend on nothing but their arguments (never on
 * `$_SERVER`), so they give the same answer anywhere and are easy to test.
 * Deciding **which** address is the one of the client of a request is not done
 * here: it is a decision of the deployment (which proxies to trust), not a
 * property of an address.
 *
 * Two rules apply to the whole class:
 *
 *   - **An address that is not valid is a question that has an answer**, not an
 *     error: the functions that ask something about an address (`isPrivate()`,
 *     `inRange()`...) say `false`, and the ones that give an address
 *     (`normalize()`, `network()`...) say `null`.
 *   - **A range or a prefix that is not valid is an error of the
 *     configuration**, and it is reported with an `InvalidArgumentException`: a
 *     range that silently matches nothing is a hole that nobody sees.
 *
 * An IPv4 address written as IPv6 (`::ffff:192.0.2.1`, which is what a socket
 * of IPv6 gives for a client of IPv4) is the IPv4 address: `normalize()` gives
 * it as IPv4, and the questions are asked about the IPv4 address (a range of
 * IPv4 matches it).
 */
final class Ip
{
    /**
     * The first bytes of an IPv4 address written as IPv6 (`::ffff:0:0/96`).
     */
    private const MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    /**
     * Checks that a text is an IP address (IPv4 or IPv6).
     *
     * It is the address alone: a port, brackets, a zone (`fe80::1%eth0`), a
     * prefix (`/24`) or an IPv4 with leading zeros (`010.0.0.1`, which could be
     * read as octal) is not an IP address; see `parse()` to take the address
     * out of a text that has them.
     *
     * @param string $ip The text.
     * @return bool True if it is an IP address.
     */
    public static function isValid(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Gets the version of the protocol of an IP address.
     *
     * @param string $ip The IP address.
     * @return int|null 4 or 6, or null if it is not an IP address.
     */
    public static function version(string $ip): ?int
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return 4;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 6 : null;
    }

    /**
     * Checks that a text is an IPv4 address. An IPv4 written as IPv6 is not.
     *
     * @param string $ip The text.
     * @return bool True if it is an IPv4 address.
     */
    public static function isIpv4(string $ip): bool
    {
        return self::version($ip) === 4;
    }

    /**
     * Checks that a text is an IPv6 address. An IPv4 written as IPv6 is.
     *
     * @param string $ip The text.
     * @return bool True if it is an IPv6 address.
     */
    public static function isIpv6(string $ip): bool
    {
        return self::version($ip) === 6;
    }

    /**
     * Gives an IP address in its canonical form, so the same address is always
     * the same text: IPv6 in lowercase and compressed (`2001:DB8:0:0::1` is
     * `2001:db8::1`), and an IPv4 written as IPv6 as IPv4 (`::ffff:192.0.2.1`
     * is `192.0.2.1`).
     *
     * @param string $ip The IP address.
     * @return string|null The address, or null if it is not an IP address.
     */
    public static function normalize(string $ip): ?string
    {
        $binary = self::binary($ip);
        if ($binary === null) {
            return null;
        }

        return self::text(self::isMapped($binary) ? substr($binary, 12) : $binary);
    }

    /**
     * Takes the IP address out of an address as it is written in headers and
     * logs: with a port (`192.0.2.1:8080`, `[2001:db8::1]:443`), in brackets
     * (`[2001:db8::1]`), with a zone (`fe80::1%eth0`) or quoted (`"[::1]:80"`,
     * as the header `Forwarded` does). The result is normalized (see
     * `normalize()`).
     *
     * An IPv6 address without brackets has no port: `::1:80` is an IPv6
     * address.
     *
     * @param string $address The address.
     * @return string|null The IP address, or null if there is none.
     */
    public static function parse(string $address): ?string
    {
        $address = trim($address, " \t\"'");
        if ($address === '') {
            return null;
        }

        if ($address[0] === '[') {
            // [IPv6] or [IPv6]:port.
            $end = strpos($address, ']');
            if ($end === false || !self::isPort(substr($address, $end + 1), true)) {
                return null;
            }
            $host = substr($address, 1, $end - 1);
        } elseif (substr_count($address, ':') === 1 && str_contains($address, '.')) {
            // IPv4:port.
            [$host, $port] = explode(':', $address, 2);
            if (!self::isPort($port)) {
                return null;
            }
        } else {
            $host = $address;
        }

        // The zone is only a thing of IPv6.
        if (str_contains($host, ':') && ($zone = strpos($host, '%')) !== false) {
            $host = substr($host, 0, $zone);
        }

        return self::normalize($host);
    }

    /**
     * Gives an IP address in its long form: the IPv6 with its eight groups of
     * four digits (`2001:db8::1` is `2001:0db8:0000:0000:0000:0000:0000:0001`).
     * An IPv4 (or an IPv4 written as IPv6) is given as IPv4, normalized.
     *
     * @param string $ip The IP address.
     * @return string|null The address, or null if it is not an IP address.
     */
    public static function expand(string $ip): ?string
    {
        $binary = self::binary($ip);
        if ($binary === null) {
            return null;
        }

        if (strlen($binary) === 4 || self::isMapped($binary)) {
            return self::normalize($ip);
        }

        return implode(':', str_split(bin2hex($binary), 4));
    }

    /**
     * Checks that an address is in a private network: the ones that are not
     * routed on the Internet (RFC 1918: `10.0.0.0/8`, `172.16.0.0/12` and
     * `192.168.0.0/16`, and the unique local addresses of IPv6, `fc00::/7`).
     *
     * The loopback (`127.0.0.1`) is not in a private network, it is reserved
     * (see `isReserved()`).
     *
     * @param string $ip The IP address.
     * @return bool True if it is in a private network. False if it is not, or
     * if it is not an IP address.
     */
    public static function isPrivate(string $ip): bool
    {
        $ip = self::normalize($ip);

        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    /**
     * Checks that an address is in a range that is reserved by the IANA, as PHP
     * defines it: the loopback (`127.0.0.0/8`, `::1`), "this network"
     * (`0.0.0.0/8`, `::`), the link-local ones (`169.254.0.0/16`, `fe80::/10`),
     * the future use ones (`240.0.0.0/4`) and the others that PHP knows.
     *
     * @param string $ip The IP address.
     * @return bool True if it is in a reserved range. False if it is not, or
     * if it is not an IP address.
     */
    public static function isReserved(string $ip): bool
    {
        $ip = self::normalize($ip);

        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Checks that an address is the loopback of the host: `127.0.0.0/8` or
     * `::1`.
     *
     * @param string $ip The IP address.
     * @return bool True if it is a loopback. False if it is not, or if it is
     * not an IP address.
     */
    public static function isLoopback(string $ip): bool
    {
        return self::inAnyRange($ip, ['127.0.0.0/8', '::1']);
    }

    /**
     * Checks that an address is a link-local one: only valid in the network
     * that the host is connected to (`169.254.0.0/16`, `fe80::/10`).
     *
     * @param string $ip The IP address.
     * @return bool True if it is a link-local one. False if it is not, or if
     * it is not an IP address.
     */
    public static function isLinkLocal(string $ip): bool
    {
        return self::inAnyRange($ip, ['169.254.0.0/16', 'fe80::/10']);
    }

    /**
     * Checks that an address is a public one: it is an IP address that is
     * neither in a private network nor in a reserved range (see `isPrivate()`
     * and `isReserved()`).
     *
     * The addresses that are only for documentation (`192.0.2.0/24`,
     * `203.0.113.0/24`, `2001:db8::/32`) are not excluded by PHP, so they are
     * public here.
     *
     * @param string $ip The IP address.
     * @return bool True if it is a public address. False if it is not, or if
     * it is not an IP address.
     */
    public static function isPublic(string $ip): bool
    {
        $ip = self::normalize($ip);

        return $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        ;
    }

    /**
     * Checks that an address is in a range.
     *
     * The range is written in CIDR notation (`10.0.0.0/8`, `2001:db8::/32`), or
     * it is an address alone (`192.0.2.1`, which is its range of one). Bits that
     * the prefix does not use are ignored (`10.1.2.3/8` is `10.0.0.0/8`). An
     * address of a version is never in a range of the other, except that an
     * IPv4 written as IPv6 is in the ranges of IPv4 (and in the ones of IPv6
     * that include it, like `::ffff:0:0/96`).
     *
     * @param string $ip The IP address.
     * @param string $range The range.
     * @return bool True if the address is in the range. False if it is not, or
     * if it is not an IP address.
     * @throws InvalidArgumentException If the range is not valid.
     */
    public static function inRange(string $ip, string $range): bool
    {
        // The range is checked even if the address is not an IP address, so a
        // wrong range does not wait for an address to be found.
        [$network, $prefix] = self::parseRange($range);

        $binary = self::binary($ip);
        if ($binary === null) {
            return false;
        }

        // An IPv4 written as IPv6 is both: the IPv4 address and the IPv6 one.
        $forms = self::isMapped($binary) ? [substr($binary, 12), $binary] : [$binary];
        foreach ($forms as $form) {
            if (self::sameNetwork($form, $network, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks that an address is in some of the ranges (see `inRange()`).
     *
     * @param string $ip The IP address.
     * @param iterable<string> $ranges The ranges.
     * @return bool True if the address is in some of them. False if it is in
     * none, or if it is not an IP address.
     * @throws InvalidArgumentException If a range is not valid (all of them are
     * checked, not only the ones before the one that matches).
     */
    public static function inAnyRange(string $ip, iterable $ranges): bool
    {
        $found = false;
        foreach ($ranges as $range) {
            $found = self::inRange($ip, $range) || $found;
        }

        return $found;
    }

    /**
     * Checks that a text is a range: in CIDR notation, or an address alone.
     *
     * @param string $range The text.
     * @return bool True if it is a valid range.
     */
    public static function isRange(string $range): bool
    {
        try {
            self::parseRange($range);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * Gives the first and the last address of a range.
     *
     * @param string $range The range, in CIDR notation or an address alone.
     * @return array{0: string, 1: string} The first and the last address.
     * @throws InvalidArgumentException If the range is not valid.
     */
    public static function range(string $range): array
    {
        [$network, $prefix] = self::parseRange($range);

        $mask = self::mask(strlen($network), $prefix);

        return [
            self::text($network),
            self::text($network | ~$mask),
        ];
    }

    /**
     * Gives the network that an address belongs to: the address with the bits
     * of the host set to zero. It is what identifies who controls the
     * address, so it is the key to count or to limit by network instead of by
     * address: a client of IPv6 has a whole `/64` (or more), and changing
     * within it costs nothing.
     *
     * @param string $ip The IP address.
     * @param int $ipv4Prefix The bits of the network for an IPv4 address, from
     * 0 to 32. By default, 32: the address.
     * @param int $ipv6Prefix The bits of the network for an IPv6 address, from
     * 0 to 128. By default, 64.
     * @return string|null The address of the network (without the prefix), or
     * null if it is not an IP address.
     * @throws InvalidArgumentException If the prefix that applies to the
     * address is not valid for its version.
     */
    public static function network(string $ip, int $ipv4Prefix = 32, int $ipv6Prefix = 64): ?string
    {
        $address = self::normalize($ip);
        if ($address === null) {
            return null;
        }

        $binary = (string) self::binary($address);
        $prefix = strlen($binary) === 4 ? $ipv4Prefix : $ipv6Prefix;
        self::checkPrefix($prefix, strlen($binary));

        return self::text($binary & self::mask(strlen($binary), $prefix));
    }

    /**
     * Gives the network that an address belongs to, written as a range (CIDR
     * notation): the network address and its prefix, like `203.0.113.0/24` or
     * `2001:db8:1:2::/64`. An IPv4 address with the prefix 32 is its own network,
     * a range of one: `203.0.113.77/32`.
     *
     * It is the same as `network()`, with the prefix that was used, so the result
     * says what it is and can be used as a range (`inRange()`, `range()`).
     *
     * @param string $ip The IP address.
     * @param int $ipv4Prefix The bits of the network for an IPv4 address, from
     * 0 to 32. By default, 32: the address.
     * @param int $ipv6Prefix The bits of the network for an IPv6 address, from
     * 0 to 128. By default, 64.
     * @return string|null The network (`network/prefix`), or null if it is not
     * an IP address.
     * @throws InvalidArgumentException If the prefix that applies to the
     * address is not valid for its version.
     */
    public static function cidr(string $ip, int $ipv4Prefix = 32, int $ipv6Prefix = 64): ?string
    {
        $network = self::network($ip, $ipv4Prefix, $ipv6Prefix);
        if ($network === null) {
            return null;
        }

        return $network . '/' . (self::version($network) === 4 ? $ipv4Prefix : $ipv6Prefix);
    }

    /**
     * Compares two IP addresses by their value, to sort them: an IPv4 goes
     * before an IPv6, and in the same version the lower number goes first. The
     * same address written in two ways is equal.
     *
     * @param string $a The first address.
     * @param string $b The second address.
     * @return int -1 if the first goes before the second, 1 if it goes after, 0
     * if they are the same address.
     * @throws InvalidArgumentException If any of them is not an IP address.
     */
    public static function compare(string $a, string $b): int
    {
        $binaryA = self::binary((string) self::normalize($a));
        $binaryB = self::binary((string) self::normalize($b));
        if ($binaryA === null) {
            throw new InvalidArgumentException(['The value "{ip}" is not an IP address.', 'ip' => $a]);
        }
        if ($binaryB === null) {
            throw new InvalidArgumentException(['The value "{ip}" is not an IP address.', 'ip' => $b]);
        }

        if (strlen($binaryA) !== strlen($binaryB)) {
            return strlen($binaryA) <=> strlen($binaryB);
        }

        return $binaryA <=> $binaryB;
    }

    /**
     * Gives the bytes of an IP address (4 for IPv4, 16 for IPv6): the form in
     * which it is stored or compared.
     *
     * @param string $ip The IP address.
     * @return string|null The bytes, or null if it is not an IP address.
     */
    public static function toBinary(string $ip): ?string
    {
        return self::binary($ip);
    }

    /**
     * Gives the IP address that some bytes are (4 for IPv4, 16 for IPv6), in its
     * canonical form (see `normalize()`), except that an IPv4 written as IPv6
     * stays IPv6 (`::ffff:c000:201`): it is the bytes that say what it is.
     *
     * @param string $binary The bytes.
     * @return string|null The address, or null if the bytes are not an address
     * (they are not 4 or 16).
     */
    public static function fromBinary(string $binary): ?string
    {
        return in_array(strlen($binary), [4, 16], true) ? self::text($binary) : null;
    }

    /**
     * Reads a range.
     *
     * @return array{0: string, 1: int} The bytes of the network (with the bits
     * of the host set to zero) and the prefix.
     * @throws InvalidArgumentException If it is not a valid range.
     */
    private static function parseRange(string $range): array
    {
        $parts = explode('/', trim($range), 2);

        $binary = self::binary($parts[0]);
        $prefix = -1;
        if ($binary !== null) {
            $prefix = strlen($binary) * 8;
            if (isset($parts[1])) {
                $prefix = ctype_digit($parts[1]) && strlen($parts[1]) <= 3 ? (int) $parts[1] : -1;
            }
        }

        if ($binary === null || $prefix < 0 || $prefix > strlen($binary) * 8) {
            throw new InvalidArgumentException(['The range "{range}" is not valid.', 'range' => $range]);
        }

        return [$binary & self::mask(strlen($binary), $prefix), $prefix];
    }

    /**
     * Checks that a prefix is valid for an address of that many bytes.
     *
     * @throws InvalidArgumentException If it is not.
     */
    private static function checkPrefix(int $prefix, int $bytes): void
    {
        if ($prefix < 0 || $prefix > $bytes * 8) {
            throw new InvalidArgumentException([
                'The prefix {prefix} is not valid for an IPv{version} address.',
                'prefix' => $prefix,
                'version' => $bytes === 4 ? 4 : 6,
            ]);
        }
    }

    /**
     * The mask of a prefix: that many bits set to one, in that many bytes.
     */
    private static function mask(int $bytes, int $prefix): string
    {
        $mask = str_repeat("\xff", intdiv($prefix, 8));
        if ($prefix % 8 !== 0) {
            $mask .= chr((0xFF << (8 - $prefix % 8)) & 0xFF);
        }

        return str_pad($mask, $bytes, "\0");
    }

    /**
     * Whether an address and a network have the same first `prefix` bits. An
     * address and a network of different sizes (IPv4 and IPv6) are never the
     * same.
     */
    private static function sameNetwork(string $address, string $network, int $prefix): bool
    {
        return ($address & self::mask(strlen($address), $prefix)) === $network;
    }

    /**
     * The bytes of an IP address.
     */
    private static function binary(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return (string) inet_pton($ip);
    }

    /**
     * The text of the bytes of an address (4 or 16 of them).
     *
     * It is not made with `inet_ntop()`, because what that function writes
     * depends on the system (`::1:80` is `::0.1.0.128` in some of them): IPv6
     * is written as RFC 5952 says, in lowercase hexadecimal, with the longest
     * run of zero groups (two or more, the first if there are two of the same
     * size) as `::`.
     */
    private static function text(string $binary): string
    {
        if (strlen($binary) === 4) {
            return implode('.', array_map('ord', str_split($binary)));
        }

        /** @var list<int> $groups */
        $groups = array_values((array) unpack('n8', $binary));

        // The longest run of zero groups.
        $start = -1;
        $length = 0;
        for ($i = 0; $i < 8; $i++) {
            if ($groups[$i] !== 0) {
                continue;
            }
            $run = 1;
            while ($i + $run < 8 && $groups[$i + $run] === 0) {
                $run++;
            }
            if ($run > $length) {
                [$start, $length] = [$i, $run];
            }
        }

        $hex = array_map(static fn (int $group): string => dechex($group), $groups);
        if ($length < 2) {
            return implode(':', $hex);
        }

        return implode(':', array_slice($hex, 0, $start))
            . '::'
            . implode(':', array_slice($hex, $start + $length))
        ;
    }

    /**
     * Whether the bytes are an IPv4 address written as IPv6.
     */
    private static function isMapped(string $binary): bool
    {
        return strlen($binary) === 16 && str_starts_with($binary, self::MAPPED_PREFIX);
    }

    /**
     * Whether a text is a port. With `$withColon` it is what comes after a
     * closing bracket: nothing, or `:` and the port.
     */
    private static function isPort(string $port, bool $withColon = false): bool
    {
        if ($withColon) {
            if ($port === '') {
                return true;
            }
            if ($port[0] !== ':') {
                return false;
            }
            $port = substr($port, 1);
        }

        return $port !== '' && ctype_digit($port) && strlen($port) <= 5 && (int) $port <= 65535;
    }
}
