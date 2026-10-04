<?php

namespace g4t\Printly\Support;

use g4t\Printly\Exceptions\InvalidColor;

/**
 * Validates and normalizes colors before they are written into CSS, so a
 * user-supplied value can never break out of the declaration it lands in.
 */
final class Color
{
    private const NAMED = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
        'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
        'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan',
        'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta',
        'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
        'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
        'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen',
        'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow',
        'grey', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
        'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
        'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
        'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue',
        'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine',
        'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue',
        'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream',
        'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
        'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred',
        'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple',
        'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell',
        'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen',
        'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'transparent', 'turquoise', 'violet', 'wheat',
        'white', 'whitesmoke', 'yellow', 'yellowgreen',
    ];

    /**
     * Accepts hex (with or without #, 3/4/6/8 digits), rgb()/rgba()/hsl()/hsla(),
     * a CSS color name, or an [r, g, b] / [r, g, b, a] array.
     *
     * @param  string|array<int, int|float>  $color
     */
    public static function parse(string|array $color): string
    {
        if (is_array($color)) {
            return self::fromArray($color);
        }

        $value = strtolower(trim($color));

        if (in_array($value, self::NAMED, true)) {
            return $value;
        }

        if (preg_match('/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $matches)) {
            return '#'.$matches[1];
        }

        if (preg_match('/^(?:rgb|hsl)a?\((?:[\d.,%\s\/+-]|deg)+\)$/', $value)) {
            return $value;
        }

        throw InvalidColor::make($color);
    }

    /**
     * @param  array<int, int|float>  $channels
     */
    private static function fromArray(array $channels): string
    {
        $channels = array_values($channels);
        $count = count($channels);

        if (($count !== 3 && $count !== 4) || array_filter($channels, fn ($c) => ! is_numeric($c))) {
            throw InvalidColor::make(json_encode($channels));
        }

        [$r, $g, $b] = array_map(fn ($c) => max(0, min(255, (int) round($c))), array_slice($channels, 0, 3));

        if ($count === 3) {
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        $alpha = max(0, min(1, (float) $channels[3]));

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.'));
    }
}
