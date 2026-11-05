<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use IntrAgile\TextExtractor\Contracts\Extractor;
use IntrAgile\TextExtractor\Laravel\Console\DoctorCommand;
use IntrAgile\TextExtractor\Laravel\Console\ExtractCommand;
use IntrAgile\TextExtractor\Options;
use IntrAgile\TextExtractor\TextExtractor;
use InvalidArgumentException;

final class TextExtractorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/text-extractor.php', 'text-extractor');

        $this->app->singleton(TextExtractor::class, static function (Application $app): TextExtractor {
            $config = $app->make(Repository::class);
            $extractor = new TextExtractor(self::options($config));

            /** @var list<class-string<Extractor>> $custom */
            $custom = (array) $config->get('text-extractor.extractors', []);

            foreach (array_reverse($custom) as $class) {
                $instance = $app->make($class);

                if (! $instance instanceof Extractor) {
                    throw new InvalidArgumentException(sprintf('Configured extractor "%s" must implement %s.', $class, Extractor::class));
                }

                $extractor = $extractor->withExtractor($instance);
            }

            return $extractor;
        });

        $this->app->singleton(LaravelTextExtractor::class, static fn (Application $app): LaravelTextExtractor => new LaravelTextExtractor(
            $app->make(TextExtractor::class),
            $app->make(FilesystemFactory::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/text-extractor.php' => $this->app->configPath('text-extractor.php'),
            ], 'text-extractor-config');

            $this->commands([DoctorCommand::class, ExtractCommand::class]);
        }
    }

    private static function options(Repository $config): Options
    {
        $defaults = new Options;

        return new Options(
            maxInputBytes: self::int($config, 'limits.max_input_bytes', $defaults->maxInputBytes),
            maxOutputBytes: self::int($config, 'limits.max_output_bytes', $defaults->maxOutputBytes),
            maxArchiveEntryBytes: self::int($config, 'limits.max_archive_entry_bytes', $defaults->maxArchiveEntryBytes),
            maxArchiveTotalBytes: self::int($config, 'limits.max_archive_total_bytes', $defaults->maxArchiveTotalBytes),
            maxArchiveEntries: self::int($config, 'limits.max_archive_entries', $defaults->maxArchiveEntries),
            maxImagePixels: self::int($config, 'limits.max_image_pixels', $defaults->maxImagePixels),
            processTimeout: self::int($config, 'process.timeout', $defaults->processTimeout),
            processMemoryMb: self::int($config, 'process.memory_mb', $defaults->processMemoryMb, 0),
            pdftotextBinary: self::nullableString($config, 'tools.pdftotext'),
            tesseractBinary: self::nullableString($config, 'tools.tesseract'),
            ocrLanguages: self::nullableString($config, 'ocr.languages') ?? $defaults->ocrLanguages,
            fallbackEncoding: self::nullableString($config, 'fallback_encoding') ?? $defaults->fallbackEncoding,
        );
    }

    /**
     * Reads an integer setting, falling back to the core default for anything unusable.
     *
     * Values usually come straight from env(), so an unset or empty variable arrives as null
     * or "", and a typo as an arbitrary string. Rejecting those here keeps a bad environment
     * value from making every resolve of the extractor throw.
     */
    private static function int(Repository $config, string $key, int $default, int $minimum = 1): int
    {
        $value = $config->get('text-extractor.'.$key);

        if (is_string($value)) {
            $value = trim($value);
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($int) && $int >= $minimum ? $int : $default;
    }

    private static function nullableString(Repository $config, string $key): ?string
    {
        $value = $config->get('text-extractor.'.$key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
