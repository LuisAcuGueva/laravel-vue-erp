<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Auth;

use App\Modules\Auth\Models\Session;
use App\Support\Auth\UserAgentParser;
use PHPUnit\Framework\TestCase;

class UserAgentParserTest extends TestCase
{
    public function test_parses_empty_or_null_user_agent(): void
    {
        $parsedNull = UserAgentParser::parse(null);
        $this->assertSame(Session::DEVICE_UNKNOWN, $parsedNull['device_type']);
        $this->assertNull($parsedNull['browser']);
        $this->assertNull($parsedNull['platform']);

        $parsedEmpty = UserAgentParser::parse('');
        $this->assertSame(Session::DEVICE_UNKNOWN, $parsedEmpty['device_type']);
        $this->assertNull($parsedEmpty['browser']);
        $this->assertNull($parsedEmpty['platform']);
    }

    public function test_parses_desktop_browsers(): void
    {
        // Chrome on Windows
        $chromeWin = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $res = UserAgentParser::parse($chromeWin);
        $this->assertSame(Session::DEVICE_DESKTOP, $res['device_type']);
        $this->assertSame('Google Chrome', $res['browser']);
        $this->assertSame('Windows', $res['platform']);

        // Edge on Windows
        $edgeWin = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
        $res = UserAgentParser::parse($edgeWin);
        $this->assertSame(Session::DEVICE_DESKTOP, $res['device_type']);
        $this->assertSame('Microsoft Edge', $res['browser']);
        $this->assertSame('Windows', $res['platform']);

        // Firefox on Linux
        $firefoxLinux = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/119.0';
        $res = UserAgentParser::parse($firefoxLinux);
        $this->assertSame(Session::DEVICE_DESKTOP, $res['device_type']);
        $this->assertSame('Mozilla Firefox', $res['browser']);
        $this->assertSame('Linux', $res['platform']);

        // Safari on macOS
        $safariMac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
        $res = UserAgentParser::parse($safariMac);
        $this->assertSame(Session::DEVICE_DESKTOP, $res['device_type']);
        $this->assertSame('Apple Safari', $res['browser']);
        $this->assertSame('macOS', $res['platform']);
    }

    public function test_parses_mobile_and_tablet_devices(): void
    {
        // Android Mobile
        $androidMobile = 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
        $res = UserAgentParser::parse($androidMobile);
        $this->assertSame(Session::DEVICE_MOBILE, $res['device_type']);
        $this->assertSame('Google Chrome', $res['browser']);
        $this->assertSame('Android', $res['platform']);

        // iPhone
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1';
        $res = UserAgentParser::parse($iphone);
        $this->assertSame(Session::DEVICE_MOBILE, $res['device_type']);
        $this->assertSame('Apple Safari', $res['browser']);
        $this->assertSame('iOS', $res['platform']);

        // iPad
        $ipad = 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1';
        $res = UserAgentParser::parse($ipad);
        $this->assertSame(Session::DEVICE_TABLET, $res['device_type']);
        $this->assertSame('Apple Safari', $res['browser']);
        $this->assertSame('iOS', $res['platform']);
    }
}
