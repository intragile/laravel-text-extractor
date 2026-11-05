<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use IntrAgile\TextExtractor\Reason;
use IntrAgile\TextExtractor\Result;
use IntrAgile\TextExtractor\TextExtractor;
use Throwable;

/**
 * The core extractor plus extraction straight from a Storage disk.
 */
final class LaravelTextExtractor
{
    public function __construct(
        private readonly TextExtractor $core,
        private readonly FilesystemFactory $filesystems,
    ) {}

    public function extract(string $path, ?string $filenameHint = null): Result
    {
        return $this->core->extract($path, $filenameHint);
    }

    public function extractFromString(string $contents, ?string $filenameHint = null): Result
    {
        return $this->core->extractFromString($contents, $filenameHint);
    }

    /**
     * @param  mixed  $stream  An open stream resource.
     */
    public function extractFromStream($stream, ?string $filenameHint = null): Result
    {
        return $this->core->extractFromStream($stream, $filenameHint);
    }

    /**
     * Extracts a file stored on a filesystem disk.
     *
     * The file is streamed to a local temporary file rather than loaded with get(), so a large
     * object on S3 never has to fit in PHP memory. Storage errors are reported as a retryable
     * failure because they say nothing about the file's content. That includes errors raised
     * while the stream is being read: under Laravel's error handler a read warning from a
     * network driver becomes an ErrorException, and it must not escape as an exception.
     */
    public function extractFromDisk(string $path, ?string $disk = null): Result
    {
        $stream = null;

        try {
            $filesystem = $this->filesystems->disk($disk);

            // exists() is also true for a directory, which cannot be read as a file.
            $isFile = method_exists($filesystem, 'fileExists')
                ? (bool) $filesystem->fileExists($path)
                : $filesystem->exists($path);

            $stream = $isFile ? $filesystem->readStream($path) : null;

            if (! is_resource($stream)) {
                return Result::failed(Reason::Unreadable, null, sprintf('The file "%s" does not exist or cannot be read.', $path));
            }

            return $this->core->extractFromStream($stream, basename($path));
        } catch (Throwable $exception) {
            return Result::failed(Reason::Unreadable, null, 'The file cannot be read from the disk: '.$exception->getMessage());
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function core(): TextExtractor
    {
        return $this->core;
    }
}
