<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use IntrAgile\TextExtractor\Laravel\Tests\TestCase;
use IntrAgile\TextExtractor\Process\ToolLocator;

final class DoctorCommandTest extends TestCase
{
    /** @var list<string> */
    private array $scripts = [];

    protected function tearDown(): void
    {
        foreach ($this->scripts as $script) {
            @unlink($script);
        }

        parent::tearDown();
    }

    private function fakeTool(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lte_tool_');
        $this->assertIsString($path);
        file_put_contents($path, "#!/bin/sh\n".$body."\n");
        chmod($path, 0755);
        $this->scripts[] = $path;

        return $path;
    }

    private function fakeTesseract(string $languages): string
    {
        return $this->fakeTool(<<<SH
            case "\$1" in
              --version) echo "tesseract 5.5.0"; echo " leptonica-1.85.0" ;;
              --list-langs) echo 'List of available languages in "/usr/share/tessdata/" (2):'; printf '%s\n' {$languages} ;;
            esac
            SH);
    }

    private function fakePdftotext(): string
    {
        return $this->fakeTool('echo "pdftotext version 24.02.0" >&2');
    }

    public function test_doctor_passes_when_everything_is_present(): void
    {
        config()->set('text-extractor.tools.tesseract', $this->fakeTesseract('eng hun'));
        config()->set('text-extractor.tools.pdftotext', $this->fakePdftotext());
        config()->set('text-extractor.ocr.languages', 'hun+eng');

        $exit = Artisan::call('text-extractor:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('pdftotext version 24.02.0', $output);
        $this->assertStringContainsString('tesseract 5.5.0', $output);
        $this->assertStringContainsString('hun+eng', $output);
    }

    public function test_doctor_reports_missing_configured_language(): void
    {
        config()->set('text-extractor.tools.tesseract', $this->fakeTesseract('eng'));
        config()->set('text-extractor.tools.pdftotext', $this->fakePdftotext());
        config()->set('text-extractor.ocr.languages', 'zzz+eng');

        $exit = Artisan::call('text-extractor:doctor');
        $output = Artisan::output();

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('zzz', $output);
    }

    public function test_doctor_fails_when_a_configured_tool_is_missing(): void
    {
        config()->set('text-extractor.tools.pdftotext', '/nonexistent/pdftotext');
        config()->set('text-extractor.tools.tesseract', $this->fakeTesseract('eng'));

        $exit = Artisan::call('text-extractor:doctor');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('/nonexistent/pdftotext', Artisan::output());
    }

    public function test_tools_missing_from_the_path_are_only_warnings(): void
    {
        $locator = new ToolLocator;

        if ($locator->find('pdftotext') !== null || $locator->find('tesseract') !== null) {
            $this->markTestSkipped('Needs a machine without pdftotext and tesseract.');
        }

        $exit = Artisan::call('text-extractor:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('not found', $output);
    }
}
