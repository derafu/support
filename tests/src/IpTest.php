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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ip::class)]
class IpTest extends TestCase
{
    #[Test]
    #[DataProvider('provideIsPrivateIpData')]
    public function shouldDetectPrivateIp(string $ip, bool $expected): void
    {
        $this->assertSame($expected, Ip::isPrivateIp($ip));
    }

    public static function provideIsPrivateIpData(): array
    {
        return [
            'class A private 10.0.0.1' => ['10.0.0.1', true],
            'class A private 10.255.255.255' => ['10.255.255.255', true],
            'class B private 172.16.0.1' => ['172.16.0.1', true],
            'class B private 172.31.255.255' => ['172.31.255.255', true],
            'class C private 192.168.0.1' => ['192.168.0.1', true],
            'class C private 192.168.255.255' => ['192.168.255.255', true],
            'public IP 8.8.8.8' => ['8.8.8.8', false],
            'public IP 1.1.1.1' => ['1.1.1.1', false],
            'loopback is not private range' => ['127.0.0.1', false],
        ];
    }

    #[Test]
    #[DataProvider('provideIsReservedIpData')]
    public function shouldDetectReservedIp(string $ip, bool $expected): void
    {
        $this->assertSame($expected, Ip::isReservedIp($ip));
    }

    public static function provideIsReservedIpData(): array
    {
        return [
            'loopback 127.0.0.1' => ['127.0.0.1', true],
            'loopback 127.255.255.255' => ['127.255.255.255', true],
            'this host 0.0.0.0' => ['0.0.0.0', true],
            'public IP 8.8.8.8' => ['8.8.8.8', false],
            'public IP 1.1.1.1' => ['1.1.1.1', false],
            'private 192.168.1.1 is not in reserved range' => ['192.168.1.1', false],
        ];
    }

    #[Test]
    public function getClientIpShouldReturnSameAsGetRealClientIp(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        unset(
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_CLIENT_IP']
        );

        try {
            $this->assertSame(Ip::getRealClientIp(), Ip::getClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldReturnRemoteAddrWhenNoProxyHeaders(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        unset(
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_CLIENT_IP'],
            $_SERVER['HTTP_X_FORWARDED'],
            $_SERVER['HTTP_FORWARDED_FOR'],
            $_SERVER['HTTP_FORWARDED']
        );

        try {
            $this->assertSame('203.0.113.42', Ip::getRealClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldPreferXForwardedForOverRemoteAddr(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.100';
        unset(
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_CLIENT_IP']
        );

        try {
            $this->assertSame('203.0.113.100', Ip::getRealClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldTakeFirstIpFromXForwardedForList(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 10.0.0.2, 192.168.1.1';
        unset(
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_CLIENT_IP']
        );

        try {
            $this->assertSame('203.0.113.1', Ip::getRealClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldSkipPrivateIpInXForwardedForAndUseNextPublic(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.99';
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_CLIENT_IP']);

        try {
            $this->assertSame('203.0.113.99', Ip::getRealClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldFallbackToRemoteAddrWhenNoServerVars(): void
    {
        $backup = $_SERVER;
        $_SERVER = [];

        try {
            $this->assertSame('0.0.0.0', Ip::getRealClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldReturnClientIpInfoWithExpectedStructure(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.20';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.30';
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_CLIENT_IP']);

        try {
            $info = Ip::getClientIpInfo();

            $this->assertArrayHasKey('real_ip', $info);
            $this->assertArrayHasKey('remote_addr', $info);
            $this->assertArrayHasKey('is_private', $info);
            $this->assertArrayHasKey('is_reserved', $info);
            $this->assertArrayHasKey('headers', $info);

            $this->assertSame('203.0.113.20', $info['real_ip']);
            $this->assertSame('203.0.113.10', $info['remote_addr']);
            $this->assertFalse($info['is_private']);
            $this->assertFalse($info['is_reserved']);

            $this->assertSame('203.0.113.20', $info['headers']['X-Forwarded-For']);
            $this->assertSame('203.0.113.30', $info['headers']['X-Real-IP']);
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function shouldMarkPrivateIpInClientIpInfo(): void
    {
        $backup = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        unset(
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_CLIENT_IP']
        );

        try {
            $info = Ip::getClientIpInfo();

            $this->assertSame('10.0.0.5', $info['real_ip']);
            $this->assertTrue($info['is_private']);
            // 10.0.0.0/8 is private but not in PHP's reserved range (0/8, 127/8, 169.254/16, 240/4)
            $this->assertFalse($info['is_reserved']);
        } finally {
            $_SERVER = $backup;
        }
    }
}
