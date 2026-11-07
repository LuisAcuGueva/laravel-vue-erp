<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Modules\Auth\Models\Session;

final class UserAgentParser
{
    /**
     * @return array{device_type: string, browser: string|null, platform: string|null}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = strtolower((string) $userAgent);

        return [
            'device_type' => self::deviceType($ua),
            'browser' => self::browser($ua),
            'platform' => self::platform($ua),
        ];
    }

    private static function deviceType(string $ua): string
    {
        if ($ua === '') {
            return Session::DEVICE_UNKNOWN;
        }

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return Session::DEVICE_TABLET;
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return Session::DEVICE_MOBILE;
        }

        return Session::DEVICE_DESKTOP;
    }

    private static function browser(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }

        return match (true) {
            str_contains($ua, 'edg/') => 'Microsoft Edge',
            str_contains($ua, 'firefox') => 'Mozilla Firefox',
            str_contains($ua, 'chrome') => 'Google Chrome',
            str_contains($ua, 'safari') => 'Apple Safari',
            default => 'Navegador web',
        };
    }

    private static function platform(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'mac os') => 'macOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'linux') => 'Linux',
            default => null,
        };
    }
}
