<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | "chrome" renders with a headless Chrome/Chromium installed on the machine.
    | Register your own with Printly::extend().
    |
    */

    'driver' => env('PRINTLY_DRIVER', 'chrome'),

    'chrome' => [
        // Path to the Chrome/Chromium binary. Null auto-detects it
        // (also honours the CHROME_PATH environment variable).
        'binary' => env('PRINTLY_CHROME_BINARY'),

        // Required when running as root, e.g. inside most Docker containers.
        'no_sandbox' => env('PRINTLY_CHROME_NO_SANDBOX', false),

        // Seconds to wait for Chrome to start, load the page and print it.
        'timeout' => env('PRINTLY_CHROME_TIMEOUT', 30),

        // Extra command line flags, e.g. ['--disable-gpu'].
        'flags' => [],

        // Where the HTML is written before Chrome opens it. Null uses the system temp directory.
        'temp_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every PDF and overridable per document. Bare-number margins
    | are millimetres; font sizes are points. Colors accept hex, rgb()/rgba(),
    | hsl()/hsla() and CSS color names.
    |
    */

    'defaults' => [
        'format' => 'A4',               // A3, A4, A5, A6, Letter, Legal, Tabloid
        'orientation' => 'portrait',    // portrait, landscape
        'margins' => 15,                // 15 or [top, right, bottom, left]
        'direction' => 'auto',          // auto (from the language), rtl, ltr
        'lang' => null,                 // null uses the application locale
        'font' => null,                 // a registered font or a system font
        'font_size' => null,
        'color' => null,
        'background' => null,
    ],

    // Fonts tried, in order, after the document font.
    'fallback_fonts' => ['Tahoma', 'Arial', 'sans-serif'],

    // Languages that make direction "auto" resolve to right-to-left.
    'rtl_locales' => ['ar', 'ckb', 'dv', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'yi'],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | Font files (ttf, otf, woff, woff2) available to every PDF, given as a
    | path or as an http(s) URL of the font file. They are embedded into the
    | document, so the server needs nothing installed. URLs are downloaded
    | on first use and cached in "font_cache_path".
    |
    | 'fonts' => [
    |     'Cairo' => [
    |         'regular' => resource_path('fonts/Cairo-Regular.ttf'),
    |         'bold' => resource_path('fonts/Cairo-Bold.ttf'),
    |     ],
    |     'Amiri' => resource_path('fonts/Amiri-Regular.ttf'),
    |     'Tajawal' => 'https://example.com/fonts/Tajawal-Regular.ttf',
    |     'Vazirmatn' => ['variable' => resource_path('fonts/Vazirmatn[wght].ttf')],
    | ],
    |
    */

    'fonts' => [],

    'font_cache_path' => storage_path('framework/cache/printly-fonts'),

    // Seconds to wait when downloading a font registered by URL.
    'font_download_timeout' => 15,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Families fetched from Google Fonts while rendering (needs network access).
    |
    | 'google_fonts' => ['Cairo' => [400, 700], 'Tajawal'],
    |
    */

    'google_fonts' => [],

];
