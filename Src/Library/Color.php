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

    public static function generateColorFromText($text, $minBrightness = 100, $spec = 10)
    {
        if (!is_int($minBrightness)) {
            throw new Exception("$minBrightness is not an integer");
        }
        if (!is_int($spec)) {
            throw new Exception("$spec is not an integer");
        }
        if ($spec < 2 || $spec > 10) {
            throw new Exception("$spec is out of range");
        }
        if ($minBrightness < 0 || $minBrightness > 255) {
            throw new Exception("$minBrightness is out of range");
        }
    
        $hash = md5($text);
        $colors = array();
        for ($i = 0; $i < 3; $i++) {
            $current = round(((hexdec(substr($hash, $spec * $i, $spec))) / hexdec(str_pad('', $spec, 'F'))) * 255);
            $colors[$i] = max(array($current, $minBrightness));
        }
    
        if ($minBrightness > 0) {
            while (array_sum($colors) / 3 < $minBrightness) {
                for ($i = 0; $i < 3; $i++) {
                    $colors[$i] += 10;
                }
            }
        }
    
        $output = '';
    
        for ($i = 0; $i < 3; $i++) {
            $output .= str_pad(dechex($colors[$i]), 2, 0, STR_PAD_LEFT);
        }
    
        return $output;
    }
}
