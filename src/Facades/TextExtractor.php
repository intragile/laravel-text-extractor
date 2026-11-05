<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use IntrAgile\TextExtractor\Laravel\LaravelTextExtractor;

/**
 * @method static \IntrAgile\TextExtractor\Result extract(string $path, ?string $filenameHint = null)
 * @method static \IntrAgile\TextExtractor\Result extractFromString(string $contents, ?string $filenameHint = null)
 * @method static \IntrAgile\TextExtractor\Result extractFromStream(mixed $stream, ?string $filenameHint = null)
 * @method static \IntrAgile\TextExtractor\Result extractFromDisk(string $path, ?string $disk = null)
 * @method static \IntrAgile\TextExtractor\TextExtractor core()
 *
 * @see LaravelTextExtractor
 */
final class TextExtractor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelTextExtractor::class;
    }
}
