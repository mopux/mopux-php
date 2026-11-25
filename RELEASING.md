# Releasing

Packagist reads the tags of this repository: pushing a `vX.Y.Z` tag publishes the version. There is no publish workflow and no token.

## Steps

1. Update `Mopux\Version::SDK` in `src/Version.php`.
2. In `CHANGELOG.md`, move the `Unreleased` notes under `## [X.Y.Z] - YYYY-MM-DD` and update the compare links at the bottom (`[Unreleased]` and the new `[X.Y.Z]`).
3. Check locally: `composer lint && composer analyse && composer test`.
4. Commit (`chore: versión X.Y.Z`), tag and push the branch and the tag:
   ```sh
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push --atomic origin main vX.Y.Z
   ```
5. Check that the version shows on https://packagist.org/packages/mopux/mopux-php (usually within a minute) and that the `tests` workflow is green.

## If something fails

- **Packagist does not update:** use **Update** on the package page, then check the GitHub hook in the Packagist settings.
- **CI matrix:** `setup-php` takes `php-version`; with any other input name every job silently runs the runner's default PHP.
