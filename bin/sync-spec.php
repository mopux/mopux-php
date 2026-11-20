<?php

declare(strict_types=1);

/*
 * Copies the public OpenAPI spec of the Mopux API into resources/openapi.yaml.
 *
 *     composer sync-spec
 *     composer sync-spec -- /path/to/openapi-v1-public.yaml
 *     MOPUX_SPEC=/path/to/openapi-v1-public.yaml composer sync-spec
 */

$source = $argv[1] ?? (getenv('MOPUX_SPEC') ?: __DIR__.'/../../../mopux-com/docs/api/openapi-v1-public.yaml');
$target = __DIR__.'/../resources/openapi.yaml';

if (! is_file($source)) {
    fwrite(STDERR, "Spec not found: {$source}\nPass the path as an argument (composer sync-spec -- /path) or set MOPUX_SPEC.\n");
    exit(1);
}

if (! copy($source, $target)) {
    fwrite(STDERR, "Could not write {$target}\n");
    exit(1);
}

echo "Copied {$source}\nRun `composer test:contract` to check the SDK still covers every operation.\n";
