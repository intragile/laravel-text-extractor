<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use IntrAgile\TextExtractor\Laravel\Tests\TestCase;

final class ExtractCommandTest extends TestCase
{
    public function test_extract_command_prints_status_and_text(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lte_').'.txt';
        file_put_contents($path, 'hello from a file');

        try {
            $exit = Artisan::call('text-extractor:extract', ['path' => $path]);
            $output = Artisan::output();
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('extracted', $output);
        $this->assertStringContainsString('hello from a file', $output);
    }

    public function test_extract_command_json_output(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('notes.txt', 'json text');

        $exit = Artisan::call('text-extractor:extract', ['path' => 'notes.txt', '--disk' => 'local', '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit);
        $this->assertSame([
            'status' => 'extracted',
            'reason' => null,
            'format' => 'txt',
            'truncated' => false,
            'message' => null,
            'text' => 'json text',
        ], $decoded);
    }

    public function test_extract_command_failure_exit_code(): void
    {
        $exit = Artisan::call('text-extractor:extract', ['path' => '/nonexistent/file.txt', '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exit);
        $this->assertIsArray($decoded);
        $this->assertSame('failed', $decoded['status']);
        $this->assertSame('unreadable', $decoded['reason']);
    }

    public function test_empty_file_is_a_successful_exit(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('blank.txt', "  \n ");

        $this->assertSame(0, Artisan::call('text-extractor:extract', ['path' => 'blank.txt', '--disk' => 'local']));
        $this->assertStringContainsString('empty', Artisan::output());
    }

    public function test_console_tags_in_the_document_are_printed_literally(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tags.txt', 'plain <info>green</info> and <fg=red>red</>');

        $this->assertSame(0, Artisan::call('text-extractor:extract', ['path' => 'tags.txt', '--disk' => 'local']));
        $this->assertStringContainsString('plain <info>green</info> and <fg=red>red</>', Artisan::output());
    }

    public function test_console_tags_in_the_document_stay_raw_in_json(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tags.txt', 'a <info>b</info> \\<c>');

        Artisan::call('text-extractor:extract', ['path' => 'tags.txt', '--disk' => 'local', '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('a <info>b</info> \\<c>', $decoded['text']);
    }
}
