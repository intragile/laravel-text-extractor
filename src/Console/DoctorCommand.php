<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Console;

use Illuminate\Console\Command;
use IntrAgile\TextExtractor\Process\ProcessRunner;
use IntrAgile\TextExtractor\Process\ToolLocator;
use IntrAgile\TextExtractor\TextExtractor;

/**
 * Checks that the PHP extensions, external tools and OCR language packs the configuration relies
 * on are actually installed.
 *
 * A missing tool is only a warning unless the configuration names it explicitly, because many
 * applications never handle PDFs or images. A configured OCR language that is not installed is an
 * error: Tesseract refuses to run at all when any language in the list is missing.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'text-extractor:doctor';

    protected $description = 'Check the extensions, tools and OCR languages used for text extraction';

    private const EXTENSIONS = [
        'mbstring' => 'required',
        'zip' => 'DOCX, XLSX, PPTX, ODT, ODS, ODP',
        'xmlreader' => 'XML and the Office formats',
        'dom' => 'HTML',
    ];

    private bool $failed = false;

    public function handle(TextExtractor $extractor, ToolLocator $locator, ProcessRunner $runner): int
    {
        $options = $extractor->options();

        $this->components->info('PHP extensions');
        $rows = [];

        foreach (self::EXTENSIONS as $extension => $usedFor) {
            $loaded = extension_loaded($extension);

            if (! $loaded && $usedFor === 'required') {
                $this->failed = true;
            }

            $rows[] = [$extension, $loaded ? 'ok' : ($usedFor === 'required' ? 'MISSING' : 'missing'), $usedFor];
        }

        $this->table(['Extension', 'Status', 'Needed for'], $rows);

        $this->components->info('External tools');
        $pdftotext = $locator->find('pdftotext', $options->pdftotextBinary);
        $tesseract = $locator->find('tesseract', $options->tesseractBinary);

        $this->table(['Tool', 'Path', 'Version'], [
            $this->toolRow('pdftotext', $options->pdftotextBinary, $pdftotext, $runner, ['-v']),
            $this->toolRow('tesseract', $options->tesseractBinary, $tesseract, $runner, ['--version']),
        ]);

        $this->components->info('OCR languages');
        $this->checkLanguages($options->ocrLanguages, $tesseract, $runner);

        if ($this->failed) {
            $this->components->error('Some required components are missing.');

            return self::FAILURE;
        }

        $this->components->info('Text extraction is ready.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $versionArguments
     * @return list<string>
     */
    private function toolRow(string $name, ?string $configured, ?string $found, ProcessRunner $runner, array $versionArguments): array
    {
        if ($found === null) {
            if ($configured !== null) {
                $this->failed = true;

                return [$name, 'MISSING: '.$configured, '-'];
            }

            return [$name, 'not found (optional)', '-'];
        }

        $result = $runner->run([$found, ...$versionArguments], 10, 0, 65536);
        $output = trim($result->stdout) !== '' ? $result->stdout : $result->stderr;
        $firstLine = trim(strtok($output, "\n") ?: '');

        return [$name, $found, $firstLine !== '' ? $firstLine : 'unknown'];
    }

    private function checkLanguages(string $configured, ?string $tesseract, ProcessRunner $runner): void
    {
        if ($tesseract === null) {
            $this->components->warn(sprintf('Configured: %s. Tesseract was not found, so the language packs cannot be checked.', $configured));

            return;
        }

        $result = $runner->run([$tesseract, '--list-langs'], 10, 0, 1048576);
        $lines = preg_split('/\R/', trim($result->stdout.PHP_EOL.$result->stderr)) ?: [];
        $installed = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '' && preg_match('/^[A-Za-z0-9_]+$/', $line) === 1) {
                $installed[] = $line;
            }
        }

        $wanted = explode('+', $configured);
        $missing = array_values(array_diff($wanted, $installed));

        if ($missing !== []) {
            $this->failed = true;
            $this->components->error(sprintf(
                'Configured: %s. Not installed: %s. Tesseract will refuse every image until these are installed (e.g. apt install %s).',
                $configured,
                implode(', ', $missing),
                implode(' ', array_map(static fn (string $language): string => 'tesseract-ocr-'.str_replace('_', '-', strtolower($language)), $missing)),
            ));

            return;
        }

        $this->components->info(sprintf('Configured: %s. All installed.', $configured));
    }
}
