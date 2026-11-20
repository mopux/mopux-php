# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Package scaffold: Composer metadata, Pest, PHPStan (level max), Pint and CI matrix.
- `Config` and `Http\Connector`: PSR-18 transport against `/api/v1/`, envelope unwrapping and query building.
- Exception hierarchy mapped from HTTP statuses (401, 403, 404, 422, 429, 4xx, 5xx) with `UncertainWriteException` for failed writes.
