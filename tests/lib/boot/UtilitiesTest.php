<?php

namespace Symphony\Boot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::idn_to_utf8_safe
 * @covers ::idn_to_ascii_safe
 */
final class UtilitiesTest extends TestCase
{
    public function testIdn_to_utf8_safe()
    {
        $utf8 = 'accent-aigu-é.com';
        $idn = 'xn--accent-aigu--meb.com';
        $test = idn_to_utf8_safe($idn);
        $this->assertContains($test, [$utf8, $idn], 'test is either utf8 or the original');
    }

    public function testIdn_to_ascii_safe()
    {
        $utf8 = 'accent-aigu-é.com';
        $idn = 'xn--accent-aigu--meb.com';
        $test = idn_to_ascii_safe($utf8);
        $this->assertContains($test, [$utf8, $idn], 'test is either idn or the original');
    }
}
