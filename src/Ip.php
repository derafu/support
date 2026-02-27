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

/**
 * IP utilities.
 *
 * Provides functionality to get the real client IP address from various headers
 * and validate if it is private or reserved.
 */
final class Ip
{
    /**
     * Gets the real client IP address from various headers.
     *
     * @return string The real client IP address.
     */
    public static function getRealClientIp(): string
    {
        // Headers to check in order of priority.
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP', // Cloudflare.
            'HTTP_X_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR_IP',
            'HTTP_VIA',
            'HTTP_X_VIA',
            'HTTP_X_COMING_FROM',
            'HTTP_COMING_FROM',
            'HTTP_X_COMING_FROM_IP',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // If there are multiple IPs (separated by comma), take the first one.
                if (str_contains($ip, ',')) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validate that it is a valid IP address.
                $isValid = filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
                if ($isValid !== false) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR.
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Gets the real client IP address (alias for compatibility).
     *
     * @return string The real client IP address.
     */
    public static function getClientIp(): string
    {
        return self::getRealClientIp();
    }

    /**
     * Checks if the IP is private.
     *
     * @param string $ip The IP address to check.
     * @return bool True if the IP is private.
     */
    public static function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);
    }

    /**
     * Checks if the IP is reserved.
     *
     * @param string $ip The IP address to check.
     * @return bool True if the IP is reserved.
     */
    public static function isReservedIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Gets detailed information about the client IP address.
     *
     * Formatted as:
     *
     * ```php
     * [
     *     'real_ip' => string,
     *     'remote_addr' => string,
     *     'is_private' => bool,
     *     'is_reserved' => bool,
     *     'headers' => array<string,string|null>,
     * ]
     * ```
     *
     * @return array<string,mixed> Detailed information about the client IP address.
     */
    public static function getClientIpInfo(): array
    {
        $realIp = self::getRealClientIp();
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return [
            'real_ip' => $realIp,
            'remote_addr' => $remoteAddr,
            'is_private' => self::isPrivateIp($realIp),
            'is_reserved' => self::isReservedIp($realIp),
            'headers' => [
                'X-Forwarded-For' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
                'X-Real-IP' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
                'CF-Connecting-IP' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
                'X-Client-IP' => $_SERVER['HTTP_X_CLIENT_IP'] ?? null,
            ],
        ];
    }
}
