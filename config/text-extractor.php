<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Bounds that keep a single oversized or hostile file from exhausting a
    | worker. Sizes are in bytes. Inputs over max_input_bytes are refused,
    | output over max_output_bytes is truncated and flagged. An empty or
    | invalid value falls back to the default.
    |
    */

    'limits' => [
        'max_input_bytes' => env('TEXT_EXTRACTOR_MAX_INPUT_BYTES', 104857600),
        'max_output_bytes' => env('TEXT_EXTRACTOR_MAX_OUTPUT_BYTES', 10485760),
        'max_archive_entry_bytes' => env('TEXT_EXTRACTOR_MAX_ARCHIVE_ENTRY_BYTES', 268435456),
        'max_archive_total_bytes' => env('TEXT_EXTRACTOR_MAX_ARCHIVE_TOTAL_BYTES', 536870912),
        'max_archive_entries' => env('TEXT_EXTRACTOR_MAX_ARCHIVE_ENTRIES', 10000),
        'max_image_pixels' => env('TEXT_EXTRACTOR_MAX_IMAGE_PIXELS', 16000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | External tools
    |--------------------------------------------------------------------------
    |
    | pdftotext (poppler-utils) handles PDF and tesseract handles images. Leave
    | a path null to search the PATH. memory_mb caps each tool's address space
    | on Linux; 0 disables the cap.
    |
    */

    'process' => [
        'timeout' => env('TEXT_EXTRACTOR_TIMEOUT', 60),
        'memory_mb' => env('TEXT_EXTRACTOR_MEMORY_MB', 512),
    ],

    'tools' => [
        'pdftotext' => env('TEXT_EXTRACTOR_PDFTOTEXT'),
        'tesseract' => env('TEXT_EXTRACTOR_TESSERACT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR
    |--------------------------------------------------------------------------
    |
    | Tesseract language list joined with "+", e.g. "hun+eng". Every language
    | must be installed (tesseract-ocr-<lang>); run "php artisan
    | text-extractor:doctor" to check.
    |
    */

    'ocr' => [
        'languages' => env('TEXT_EXTRACTOR_OCR_LANGUAGES', 'eng'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback encoding
    |--------------------------------------------------------------------------
    |
    | Assumed encoding of text files that are not valid UTF-8. Use
    | "Windows-1250" for Central European (e.g. Hungarian) exports.
    |
    */

    'fallback_encoding' => env('TEXT_EXTRACTOR_FALLBACK_ENCODING', 'Windows-1252'),

    /*
    |--------------------------------------------------------------------------
    | Custom extractors
    |--------------------------------------------------------------------------
    |
    | Classes implementing IntrAgile\TextExtractor\Contracts\Extractor,
    | resolved from the container. They are tried before the built-in ones,
    | in the order listed.
    |
    */

    'extractors' => [],

];
