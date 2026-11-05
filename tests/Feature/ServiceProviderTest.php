<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use IntrAgile\TextExtractor\Contracts\Extractor;
use IntrAgile\TextExtractor\Format;
use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor as TextExtractorFacade;
use IntrAgile\TextExtractor\Laravel\LaravelTextExtractor;
use IntrAgile\TextExtractor\Laravel\Tests\TestCase;
use IntrAgile\TextExtractor\Options;
use IntrAgile\TextExtractor\Result;
use IntrAgile\TextExtractor\Status;
use IntrAgile\TextExtractor\TextExtractor;
use InvalidArgumentException;

final class ServiceProviderTest extends TestCase
{
    public function test_config_values_reach_the_core_options(): void
    {
        config()->set('text-extractor.limits.max_output_bytes', 1234);
        config()->set('text-extractor.limits.max_image_pixels', 99);
        config()->set('text-extractor.process.timeout', 7);
        config()->set('text-extractor.process.memory_mb', 0);
        config()->set('text-extractor.tools.pdftotext', '/opt/tools/pdftotext');
        config()->set('text-extractor.ocr.languages', 'hun+eng');
        config()->set('text-extractor.fallback_encoding', 'Windows-1250');

        $options = resolve(TextExtractor::class)->options();

        $this->assertSame(1234, $options->maxOutputBytes);
        $this->assertSame(99, $options->maxImagePixels);
        $this->assertSame(7, $options->processTimeout);
        $this->assertSame(0, $options->processMemoryMb);
        $this->assertSame('/opt/tools/pdftotext', $options->pdftotextBinary);
        $this->assertNull($options->tesseractBinary);
        $this->assertSame('hun+eng', $options->ocrLanguages);
        $this->assertSame('Windows-1250', $options->fallbackEncoding);
    }

    public function test_default_config_matches_the_core_defaults(): void
    {
        $this->assertEquals(new Options, resolve(TextExtractor::class)->options());
    }

    public function test_numeric_strings_from_env_are_accepted(): void
    {
        config()->set('text-extractor.limits.max_input_bytes', '2048');

        $this->assertSame(2048, resolve(TextExtractor::class)->options()->maxInputBytes);
    }

    public function test_empty_tool_paths_mean_search_the_path(): void
    {
        config()->set('text-extractor.tools.tesseract', '');

        $this->assertNull(resolve(TextExtractor::class)->options()->tesseractBinary);
    }

    public function test_custom_extractor_from_config_is_used_first(): void
    {
        config()->set('text-extractor.extractors', [UpperCaseTextExtractor::class]);
        $path = tempnam(sys_get_temp_dir(), 'lte_').'.txt';
        file_put_contents($path, 'hello');

        try {
            $this->assertSame('HELLO', resolve(TextExtractor::class)->extract($path)->text);
        } finally {
            @unlink($path);
        }
    }

    public function test_configured_class_that_is_not_an_extractor_is_rejected(): void
    {
        config()->set('text-extractor.extractors', [\stdClass::class]);

        $this->expectException(InvalidArgumentException::class);

        resolve(TextExtractor::class);
    }

    public function test_facade_resolves(): void
    {
        $this->assertInstanceOf(LaravelTextExtractor::class, TextExtractorFacade::getFacadeRoot());
        $this->assertSame(Status::Extracted, TextExtractorFacade::extractFromString('from the facade', 'a.txt')->status);
        $this->assertSame(resolve(TextExtractor::class), TextExtractorFacade::core());
    }

    public function test_bindings_are_singletons(): void
    {
        $this->assertSame(resolve(LaravelTextExtractor::class), resolve(LaravelTextExtractor::class));
    }

    public function test_config_can_be_published(): void
    {
        $this->assertSame(0, Artisan::call('vendor:publish', ['--tag' => 'text-extractor-config']));

        $this->assertFileExists(config_path('text-extractor.php'));
        @unlink(config_path('text-extractor.php'));
    }
}

final class UpperCaseTextExtractor implements Extractor
{
    public function supports(Format $format): bool
    {
        return $format === Format::Txt;
    }

    public function extract(string $path, Format $format, Options $options): Result
    {
        return Result::extracted(strtoupper((string) file_get_contents($path)), $format);
    }
}
