<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Console;

use Illuminate\Console\Command;
use IntrAgile\TextExtractor\Laravel\LaravelTextExtractor;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

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

        // Document text and messages are untrusted: written through the formatter, a "<info>"
        // or "<fg=red>" inside a document would be interpreted as console markup.
        if ($this->option('json') === true) {
            $this->output->writeln((string) json_encode([
                'status' => $result->status->value,
                'reason' => $result->reason?->value,
                'format' => $result->format?->value,
                'truncated' => $result->truncated,
                'message' => $result->message,
                'text' => $result->text,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);
        } else {
            $this->table(['Status', 'Reason', 'Format', 'Truncated'], [[
                $result->status->value,
                $result->reason->value ?? '-',
                $result->format->value ?? '-',
                $result->truncated ? 'yes' : 'no',
            ]]);

            if ($result->message !== null) {
                $this->line(OutputFormatter::escape($result->message));
            }

            if ($result->text !== '') {
                $this->newLine();
                $this->line(OutputFormatter::escape($result->text));
            }
        }

        return $result->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
