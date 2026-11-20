<?php

declare(strict_types=1);

use Mopux\Version;

it('exposes the sdk and api versions', function (): void {
    expect(Version::SDK)->toBeString()->not->toBeEmpty()
        ->and(Version::API)->toBe('v1');
});

it('ships the public openapi specification', function (): void {
    $spec = file_get_contents(__DIR__.'/../../resources/openapi.yaml');

    expect($spec)->toBeString()->toContain('openapi: 3.1.0');
});
