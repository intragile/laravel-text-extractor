<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Console;

use Illuminate\Console\Command;
use IntrAgile\TextExtractor\Laravel\LaravelTextExtractor;

final class ExtractCommand extends Command
{
    protected $signature = 'text-extractor:extract
        {path : A local path, or a path on the disk given with --disk}
        {--disk= : Read the file from this filesystem disk}
        {--json : Print the result as JSON}';

    protected $description = 'Extract the text of a file and show the result';

    public function handle(LaravelTextExtractor $extractor): int
    {
        $path = $this->argument('path');

        if ($path === '') {
            $this->components->error('A path is required.');

            return self::INVALID;
        }

        $disk = $this->option('disk');

        $result = is_string($disk) && $disk !== ''
            ? $extractor->extractFromDisk($path, $disk)
            : $extractor->extract($path);

        if ($this->option('json') === true) {
            $this->line((string) json_encode([
                'status' => $result->status->value,
                'reason' => $result->reason?->value,
                'format' => $result->format?->value,
                'truncated' => $result->truncated,
                'message' => $result->message,
                'text' => $result->text,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } else {
            $this->table(['Status', 'Reason', 'Format', 'Truncated'], [[
                $result->status->value,
                $result->reason->value ?? '-',
                $result->format->value ?? '-',
                $result->truncated ? 'yes' : 'no',
            ]]);

            if ($result->message !== null) {
                $this->line($result->message);
            }

            if ($result->text !== '') {
                $this->newLine();
                $this->line($result->text);
            }
        }

        return $result->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
