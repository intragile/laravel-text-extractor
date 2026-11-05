# Laravel Text Extractor

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

[![Tests](https://github.com/intragile/laravel-text-extractor/actions/workflows/tests.yml/badge.svg)](https://github.com/intragile/laravel-text-extractor/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/intragile/laravel-text-extractor.svg)](https://packagist.org/packages/intragile/laravel-text-extractor)
[![PHP](https://img.shields.io/packagist/php-v/intragile/laravel-text-extractor.svg)](https://packagist.org/packages/intragile/laravel-text-extractor)
[![License](https://img.shields.io/packagist/l/intragile/laravel-text-extractor.svg)](LICENSE)

**Extract plain text from uploads on any Storage disk** (PDF, Word, Excel, PowerPoint,
OpenDocument, RTF, HTML, CSV and images), for search indexes, RAG pipelines and archives.

[Magyar nyelvű leírás → README.hu.md](README.hu.md)

This is the Laravel integration of
[intragile/php-text-extractor](https://github.com/intragile/php-text-extractor): a facade, a
config file, extraction straight from a filesystem disk and a `doctor` command. Everything the
core promises applies here too: no Composer dependencies beyond Laravel, bounded memory, safe
handling of untrusted files and a result that tells "no text" apart from "failed".

```php
use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor;

$result = TextExtractor::extractFromDisk($document->path, 's3');

if ($result->isSuccessful()) {
    $document->update(['content' => $result->text]);   // '' when there is no text
} elseif ($result->isRetryable()) {
    $this->release(300);                               // timeout, missing tool, storage error
} else {
    $document->update(['extraction_error' => $result->reason->value]);
}
```

## Requirements

- PHP 8.2+, Laravel 12 or 13.
- `ext-mbstring`; `ext-zip` and `ext-xmlreader` for Office and OpenDocument files; `ext-dom` for HTML.
- `pdftotext` (poppler-utils) for PDF and `tesseract` for images, if you need those formats.

```bash
composer require intragile/laravel-text-extractor

# Debian / Ubuntu, with English and Hungarian OCR
apt-get install poppler-utils tesseract-ocr tesseract-ocr-eng tesseract-ocr-hun

php artisan text-extractor:doctor
```

The service provider and the `TextExtractor` alias are registered automatically.

## Usage

```php
use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor;

TextExtractor::extractFromDisk('invoices/2026/0042.pdf');          // default disk
TextExtractor::extractFromDisk('uploads/scan.png', 's3');
TextExtractor::extract($request->file('document')->getRealPath(),
    $request->file('document')->getClientOriginalName());
TextExtractor::extractFromString($bytes, 'notes.txt');
TextExtractor::extractFromStream($stream, 'report.docx');
```

`extractFromDisk()` streams the object to a local temporary file, so a large file on S3 never has
to fit in PHP memory. A missing file or a storage error is reported as `Failed` with reason
`unreadable`, which is retryable.

The facade resolves `IntrAgile\TextExtractor\Laravel\LaravelTextExtractor`; inject that class, or
the core `IntrAgile\TextExtractor\TextExtractor` singleton, wherever you prefer dependency
injection. In tests, mock it with `TextExtractor::shouldReceive('extractFromDisk')`.

### The result

Every call returns an `IntrAgile\TextExtractor\Result` and never throws for problems with the file
or the disk: a directory, a missing object or a failing S3 read is `Failed/unreadable`.

| Status | Meaning | Retry? |
|---|---|---|
| `Extracted` | text was found | – |
| `Empty` | processed successfully, the file has no text | no |
| `NotExtractable` | the file can never be processed as it is (encrypted, corrupt, too large, unsupported) | no |
| `Failed` | a technical problem (timeout, missing tool, storage error) | yes |

`$result->reason` tells you why, e.g. `encrypted`, `no_text_layer` or `missing_tool`. The
complete table of reasons and the per-format output are in the
[core documentation](https://github.com/intragile/php-text-extractor#results).

### In a queued job

```php
public function handle(): void
{
    $result = TextExtractor::extractFromDisk($this->document->path, $this->document->disk);

    if ($result->isRetryable()) {
        $this->release(600);

        return;
    }

    $this->document->update([
        'content' => $result->text,
        'extraction_status' => $result->status->value,
        'extraction_reason' => $result->reason?->value,
    ]);
}
```

Keep the job's `$timeout` above `limits.max_parse_seconds` and `process.timeout`. The parse limit is
checked between parser steps, so a single long libxml call can overrun it; the job timeout is the
hard stop.

## Configuration

```bash
php artisan vendor:publish --tag=text-extractor-config
```

| Key | Env | Default |
|---|---|---|
| `limits.max_input_bytes` | `TEXT_EXTRACTOR_MAX_INPUT_BYTES` | 100 MiB |
| `limits.max_output_bytes` | `TEXT_EXTRACTOR_MAX_OUTPUT_BYTES` | 10 MiB |
| `limits.max_archive_entry_bytes` | `TEXT_EXTRACTOR_MAX_ARCHIVE_ENTRY_BYTES` | 256 MiB |
| `limits.max_archive_total_bytes` | `TEXT_EXTRACTOR_MAX_ARCHIVE_TOTAL_BYTES` | 512 MiB |
| `limits.max_archive_entries` | `TEXT_EXTRACTOR_MAX_ARCHIVE_ENTRIES` | 10 000 |
| `limits.max_image_pixels` | `TEXT_EXTRACTOR_MAX_IMAGE_PIXELS` | 16 000 000 |
| `limits.max_html_bytes` | `TEXT_EXTRACTOR_MAX_HTML_BYTES` | 10 MiB |
| `limits.max_parse_seconds` | `TEXT_EXTRACTOR_MAX_PARSE_SECONDS` | 60 s (parsing done in PHP; over it the result is `Failed` / `parse_timeout`) |
| `process.timeout` | `TEXT_EXTRACTOR_TIMEOUT` | 60 s |
| `process.memory_mb` | `TEXT_EXTRACTOR_MEMORY_MB` | 512 (Linux only; `0` disables) |
| `tools.pdftotext` | `TEXT_EXTRACTOR_PDFTOTEXT` | search the `PATH` |
| `tools.tesseract` | `TEXT_EXTRACTOR_TESSERACT` | search the `PATH` |
| `ocr.languages` | `TEXT_EXTRACTOR_OCR_LANGUAGES` | `eng` |
| `fallback_encoding` | `TEXT_EXTRACTOR_FALLBACK_ENCODING` | `Windows-1252` |
| `extractors` | – | `[]` |

An empty or non-numeric value means the default, so `TEXT_EXTRACTOR_TIMEOUT=` in `.env` is safe.

For Hungarian content, set `TEXT_EXTRACTOR_OCR_LANGUAGES=hun+eng` and
`TEXT_EXTRACTOR_FALLBACK_ENCODING=Windows-1250`.

### Custom extractors

List classes implementing `IntrAgile\TextExtractor\Contracts\Extractor` under `extractors`. They
are resolved from the container and tried before the built-in extractors, in the order listed.

```php
'extractors' => [
    App\TextExtraction\CloudOcrExtractor::class,
],
```

## Artisan commands

```bash
php artisan text-extractor:doctor
```

Lists the PHP extensions, the `pdftotext` and `tesseract` binaries with their versions, and checks
that every configured OCR language is installed. It exits with status 1 when something the
configuration relies on is missing, so it can run as a deployment or container health check.
A tool that is neither configured nor installed is reported as optional.

```bash
php artisan text-extractor:extract storage/app/sample.pdf
php artisan text-extractor:extract invoices/0042.pdf --disk=s3 --json
```

Shows the status, reason, format and text of one file, printed literally. The exit status is 0
for `Extracted` and `Empty`, 1 for any other result and 2 when no path is given.

## License

MIT, see [LICENSE](LICENSE).

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)
