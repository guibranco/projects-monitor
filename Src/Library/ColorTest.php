<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Color;

class ColorTest extends TestCase
{
    public function testLuminance()
    {
        $this->assertEquals(0, Color::luminance('000000'));
        $this->assertEquals(255, Color::luminance('FFFFFF'));
        $this->assertEquals(54.213, round(Color::luminance('FF0000'), 3));
    }

    public function testHex2Rgb()
    {
        $reflection = new ReflectionClass(Color::class);
        $method = $reflection->getMethod('hex2rgb');
        $method->setAccessible(true);

        $this->assertEquals(['red' => 255, 'green' => 0, 'blue' => 0], $method->invoke(null, 'FF0000'));
        $this->assertEquals(['red' => 0, 'green' => 255, 'blue' => 0], $method->invoke(null, '00FF00'));
        $this->assertEquals(['red' => 0, 'green' => 0, 'blue' => 255], $method->invoke(null, '0000FF'));
    }

    public function testGenerateColorFromText()
    {
        $color = Color::generateColorFromText('test');
        $this->assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $color);

        $color = Color::generateColorFromText('test', 200);
        $this->assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $color);

        $color = Color::generateColorFromText('test', 100, 5);
        $this->assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $color);
    }

    public function testGenerateColorFromTextExceptions()
    {
        $this->expectException(\Exception::class);
        Color::generateColorFromText('test', 'invalid');

        $this->expectException(\Exception::class);
        Color::generateColorFromText('test', 100, 'invalid');

        $this->expectException(\Exception::class);
        Color::generateColorFromText('test', 100, 1);

        $this->expectException(\Exception::class);
        Color::generateColorFromText('test', 100, 11);

        $this->expectException(\Exception::class);
        Color::generateColorFromText('test', -1);

        $this->expectException(\Exception::class);
        Color::generateColorFromText('test', 256);
    }
}