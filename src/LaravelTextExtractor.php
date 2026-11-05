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
     * failure because they say nothing about the file's content.
     */
    public function extractFromDisk(string $path, ?string $disk = null): Result
    {
        try {
            $filesystem = $this->filesystems->disk($disk);
            $stream = $filesystem->exists($path) ? $filesystem->readStream($path) : null;
        } catch (Throwable $exception) {
            return Result::failed(Reason::Unreadable, null, 'The file cannot be read from the disk: '.$exception->getMessage());
        }

        if (! is_resource($stream)) {
            return Result::failed(Reason::Unreadable, null, sprintf('The file "%s" does not exist or cannot be read.', $path));
        }

        try {
            return $this->core->extractFromStream($stream, basename($path));
        } finally {
            fclose($stream);
        }
    }

    public function core(): TextExtractor
    {
        return $this->core;
    }
}
