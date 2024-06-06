<?php

namespace GuiBranco\ProjectsMonitor\Library;

class Color
{
    public static function luminance($color)
    {
        $rgb = self::hex2rgb($color);

        return ($rgb['red'] * 0.2126) + ($rgb['green'] * 0.7152) + ($rgb['blue'] * 0.0722);
    }

    public static function hex2rgb($color)
    {
        if (strlen($color) == 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }
        return array(
            'red' => hexdec($color[0] . $color[1]),
            'green' => hexdec($color[2] . $color[3]),
            'blue' => hexdec($color[4] . $color[5]),
        );
    }
}