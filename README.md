# Mopux PHP

Official PHP client for the [Mopux](https://www.mopux.com) API (`/api/v1`), with first-class Laravel integration.

> **Status:** under active development, not yet released.

## Requirements

- PHP 8.2+
- Any PSR-18 HTTP client (Guzzle is used automatically when installed)
- Laravel 11, 12 or 13 (optional)

## Installation

```bash
composer require mopux/mopux-php
```

## Development

```bash
composer install
composer test       # Pest
composer analyse    # PHPStan (level max)
composer lint       # Pint (check only)
composer check      # all of the above
composer sync-spec  # refresh resources/openapi.yaml from the Mopux repository
```

## License

MIT. See [LICENSE](LICENSE).
