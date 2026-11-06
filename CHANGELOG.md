# Changelog

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-06

### Added

- Auto-discovered service provider binding `IntrAgile\TextExtractor\TextExtractor` and
  `LaravelTextExtractor` as singletons built from `config/text-extractor.php`.
- `TextExtractor` facade with `extract()`, `extractFromString()`, `extractFromStream()`,
  `extractFromDisk()` and `core()`.
- `extractFromDisk()`, which streams a file from any filesystem disk to a temporary file and
  reports storage errors as retryable failures.
- Configuration for limits, timeouts, tool paths, OCR languages, the fallback encoding and custom
  extractors, with environment variables for each setting.
- `text-extractor:doctor`, which checks extensions, tools and OCR language packs and fails when a
  configured component is missing.
- `text-extractor:extract`, which shows the result for one file, optionally as JSON.

[1.0.0]: https://github.com/intragile/laravel-text-extractor/releases/tag/v1.0.0

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu).
