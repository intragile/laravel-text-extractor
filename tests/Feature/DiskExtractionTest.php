<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Tests\Feature;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use IntrAgile\TextExtractor\Format;
use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor;
use IntrAgile\TextExtractor\Laravel\Tests\TestCase;
use IntrAgile\TextExtractor\Reason;
use IntrAgile\TextExtractor\Status;
use RuntimeException;

final class DiskExtractionTest extends TestCase
{
    public function test_extract_from_disk_streams_the_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('docs/notes.txt', "first line\r\nsecond line");

        $result = TextExtractor::extractFromDisk('docs/notes.txt', 'local');

        $this->assertSame(Status::Extracted, $result->status);
        $this->assertSame("first line\nsecond line", $result->text);
        $this->assertSame(Format::Txt, $result->format);
    }

    public function test_the_stored_file_name_decides_text_flavour(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('2026/export.csv', "a;b\n1;2");

        $this->assertSame(Format::Csv, TextExtractor::extractFromDisk('2026/export.csv', 'uploads')->format);
    }

    public function test_default_disk_is_used_when_none_is_given(): void
    {
        config()->set('filesystems.default', 'fake-default');
        Storage::fake('fake-default');
        Storage::disk('fake-default')->put('a.txt', 'default disk');

        $this->assertSame('default disk', TextExtractor::extractFromDisk('a.txt')->text);
    }

    public function test_missing_disk_file_is_failed_unreadable(): void
    {
        Storage::fake('local');

        $result = TextExtractor::extractFromDisk('nope.txt', 'local');

        $this->assertSame(Status::Failed, $result->status);
        $this->assertSame(Reason::Unreadable, $result->reason);
        $this->assertTrue($result->isRetryable());
    }

    public function test_unknown_disk_is_failed_unreadable_not_an_exception(): void
    {
        $result = TextExtractor::extractFromDisk('a.txt', 'no-such-disk');

        $this->assertSame(Reason::Unreadable, $result->reason);
    }

    public function test_oversized_disk_file_is_not_extractable(): void
    {
        config()->set('text-extractor.limits.max_input_bytes', 10);
        Storage::fake('local');
        Storage::disk('local')->put('big.txt', str_repeat('x', 11));

        $this->assertSame(Reason::TooLarge, TextExtractor::extractFromDisk('big.txt', 'local')->reason);
    }

    public function test_directory_on_disk_is_failed_unreadable(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('dir');

        $result = TextExtractor::extractFromDisk('dir', 'local');

        $this->assertSame(Status::Failed, $result->status);
        $this->assertSame(Reason::Unreadable, $result->reason);
    }

    public function test_error_while_reading_the_disk_stream_is_failed_unreadable(): void
    {
        stream_wrapper_register('lte-broken', BrokenStream::class);

        try {
            $disk = \Mockery::mock(Filesystem::class);
            $disk->shouldReceive('exists')->andReturn(true);
            $disk->shouldReceive('readStream')->andReturnUsing(static fn () => fopen('lte-broken://file', 'rb'));
            Storage::set('broken', $disk);

            $result = TextExtractor::extractFromDisk('remote.txt', 'broken');
        } finally {
            stream_wrapper_unregister('lte-broken');
        }

        $this->assertSame(Status::Failed, $result->status);
        $this->assertSame(Reason::Unreadable, $result->reason);
        $this->assertTrue($result->isRetryable());
    }
}

/**
 * A stream whose reads fail the way a dropped network connection does.
 */
final class BrokenStream
{
    /** @var resource|null */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        throw new RuntimeException('Connection reset by peer');
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return [];
    }
}
