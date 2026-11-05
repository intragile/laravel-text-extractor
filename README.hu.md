# Laravel Text Extractor

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

[![Tests](https://github.com/intragile/laravel-text-extractor/actions/workflows/tests.yml/badge.svg)](https://github.com/intragile/laravel-text-extractor/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/intragile/laravel-text-extractor.svg)](https://packagist.org/packages/intragile/laravel-text-extractor)
[![PHP](https://img.shields.io/packagist/php-v/intragile/laravel-text-extractor.svg)](https://packagist.org/packages/intragile/laravel-text-extractor)
[![License](https://img.shields.io/packagist/l/intragile/laravel-text-extractor.svg)](LICENSE)

**Szöveg kinyerése bármelyik Storage diskre feltöltött fájlból** (PDF, Word, Excel, PowerPoint,
OpenDocument, RTF, HTML, CSV és képek), keresőindexhez, RAG-pipeline-hoz, archiváláshoz.

[English README → README.md](README.md)

Ez az [intragile/php-text-extractor](https://github.com/intragile/php-text-extractor) Laravel-integrációja.
Facade-ot, config fájlt, közvetlen kinyerést filesystem diskről és egy `doctor` parancsot ad.

A mag minden ígérete itt is érvényes:
- a Laravelen kívül nincs Composer-függőség;
- korlátos a memóriahasználat;
- az ismeretlen forrásból származó fájlokat biztonságosan kezeli;
- az eredményből kiderül, hogy a fájlban nem volt szöveg, vagy a kinyerés hibára futott.

```php
use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor;

$result = TextExtractor::extractFromDisk($document->path, 's3');

if ($result->isSuccessful()) {
    $document->update(['content' => $result->text]);   // '' ha nincs benne szöveg
} elseif ($result->isRetryable()) {
    $this->release(300);                               // timeout, hiányzó eszköz, tárhelyhiba
} else {
    $document->update(['extraction_error' => $result->reason->value]);
}
```

## Követelmények

- PHP 8.2+, Laravel 12 vagy 13.
- Kötelező az `ext-mbstring`. Az Office- és OpenDocument-fájlokhoz `ext-zip` és `ext-xmlreader`,
  a HTML-hez `ext-dom` kell.
- PDF-hez `pdftotext` (poppler-utils), képekhez `tesseract`, ha ezekre a formátumokra szükséged van.

```bash
composer require intragile/laravel-text-extractor

# Debian / Ubuntu, magyar és angol OCR-rel
apt-get install poppler-utils tesseract-ocr tesseract-ocr-hun tesseract-ocr-eng

php artisan text-extractor:doctor
```

A service provider és a `TextExtractor` alias automatikusan regisztrálódik.

## Használat

```php
use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor;

TextExtractor::extractFromDisk('szamlak/2026/0042.pdf');          // alapértelmezett disk
TextExtractor::extractFromDisk('feltoltesek/szken.png', 's3');
TextExtractor::extract($request->file('document')->getRealPath(),
    $request->file('document')->getClientOriginalName());
TextExtractor::extractFromString($bytes, 'jegyzet.txt');
TextExtractor::extractFromStream($stream, 'riport.docx');
```

Az `extractFromDisk()` streamelve másolja a fájlt egy helyi átmeneti fájlba, így egy nagy S3-objektumnak
soha nem kell beférnie a PHP memóriájába. Hiányzó fájl vagy tárhelyhiba esetén az eredmény `Failed`,
`unreadable` okkal, és újrapróbálható.

A facade az `IntrAgile\TextExtractor\Laravel\LaravelTextExtractor` osztályt oldja fel. Dependency
injectionnel ezt az osztályt vagy a mag `IntrAgile\TextExtractor\TextExtractor` singletonját kérheted. Tesztben a `TextExtractor::shouldReceive('extractFromDisk')` hívással mockolható.

### Az eredmény

Minden hívás `IntrAgile\TextExtractor\Result`-ot ad vissza, és a fájllal vagy a diskkel kapcsolatos
hiba miatt soha nem dob kivételt: egy könyvtár, egy hiányzó objektum vagy egy sikertelen S3-olvasás
`Failed/unreadable`.

| Status | Jelentés | Újrapróbálás? |
|---|---|---|
| `Extracted` | van szöveg | – |
| `Empty` | sikeres feldolgozás, a fájlban nincs szöveg | nem |
| `NotExtractable` | a fájl ebben a formában soha nem dolgozható fel (titkosított, sérült, túl nagy, nem támogatott) | nem |
| `Failed` | technikai hiba (timeout, hiányzó eszköz, tárhelyhiba) | igen |

A `$result->reason` megmondja az okot, például `encrypted`, `no_text_layer` vagy `missing_tool`. Az
okok teljes táblázata és a formátumonkénti kimenet a
[mag dokumentációjában](https://github.com/intragile/php-text-extractor/blob/main/README.hu.md#az-eredmény) található.

### Queue jobban

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

## Beállítások

```bash
php artisan vendor:publish --tag=text-extractor-config
```

| Kulcs | Env | Alapérték |
|---|---|---|
| `limits.max_input_bytes` | `TEXT_EXTRACTOR_MAX_INPUT_BYTES` | 100 MiB |
| `limits.max_output_bytes` | `TEXT_EXTRACTOR_MAX_OUTPUT_BYTES` | 10 MiB |
| `limits.max_archive_entry_bytes` | `TEXT_EXTRACTOR_MAX_ARCHIVE_ENTRY_BYTES` | 256 MiB |
| `limits.max_archive_total_bytes` | `TEXT_EXTRACTOR_MAX_ARCHIVE_TOTAL_BYTES` | 512 MiB |
| `limits.max_archive_entries` | `TEXT_EXTRACTOR_MAX_ARCHIVE_ENTRIES` | 10 000 |
| `limits.max_image_pixels` | `TEXT_EXTRACTOR_MAX_IMAGE_PIXELS` | 16 000 000 |
| `limits.max_html_bytes` | `TEXT_EXTRACTOR_MAX_HTML_BYTES` | 10 MiB |
| `process.timeout` | `TEXT_EXTRACTOR_TIMEOUT` | 60 s |
| `process.memory_mb` | `TEXT_EXTRACTOR_MEMORY_MB` | 512 (csak Linuxon; `0` kikapcsolja) |
| `tools.pdftotext` | `TEXT_EXTRACTOR_PDFTOTEXT` | keresés a `PATH`-ban |
| `tools.tesseract` | `TEXT_EXTRACTOR_TESSERACT` | keresés a `PATH`-ban |
| `ocr.languages` | `TEXT_EXTRACTOR_OCR_LANGUAGES` | `eng` |
| `fallback_encoding` | `TEXT_EXTRACTOR_FALLBACK_ENCODING` | `Windows-1252` |
| `extractors` | – | `[]` |

Az üres vagy nem szám érték az alapértelmezést jelenti, így a `.env`-ben a `TEXT_EXTRACTOR_TIMEOUT=` sem
okoz hibát.

Magyar tartalomhoz állítsd be: `TEXT_EXTRACTOR_OCR_LANGUAGES=hun+eng` és
`TEXT_EXTRACTOR_FALLBACK_ENCODING=Windows-1250`.

### Saját extractorok

Az `extractors` kulcs alatt sorold fel az `IntrAgile\TextExtractor\Contracts\Extractor`-t implementáló
osztályokat. A konténerből oldódnak fel, és a felsorolás sorrendjében a beépített extractorok előtt
kerülnek sorra.

```php
'extractors' => [
    App\TextExtraction\CloudOcrExtractor::class,
],
```

## Artisan parancsok

```bash
php artisan text-extractor:doctor
```

Kilistázza a PHP-bővítményeket, a `pdftotext` és a `tesseract` útvonalát és verzióját, és ellenőrzi,
hogy minden beállított OCR-nyelv telepítve van-e. Ha valami hiányzik, amire a konfiguráció épít,
1-es kóddal lép ki, így deploy vagy konténer health check is használhatja. A sem beállított, sem
telepített eszközt opcionálisként jelzi.

```bash
php artisan text-extractor:extract storage/app/minta.pdf
php artisan text-extractor:extract szamlak/0042.pdf --disk=s3 --json
```

Egy fájl státuszát, okát, formátumát és szövegét mutatja, szó szerint. A kilépési kód `Extracted` és
`Empty` esetén 0, más eredménynél 1, hiányzó útvonalnál 2.

## Licenc

MIT, lásd [LICENSE](LICENSE).

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)
