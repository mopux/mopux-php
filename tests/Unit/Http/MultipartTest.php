<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Utils;
use Mopux\Data\Input\Upload;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Http\Multipart;

it('builds a multipart body with fields, arrays and files', function (): void {
    $body = (new Multipart('BOUNDARY'))
        ->field('comment', 'Hola')
        ->field('mentions', [5, 7])
        ->field('skipped', null)
        ->field('flag', true)
        ->file('files[]', Upload::fromString('a,b', 'data.csv', 'text/csv'))
        ->build(new HttpFactory);

    expect((string) $body)->toBe(
        "--BOUNDARY\r\nContent-Disposition: form-data; name=\"comment\"\r\n\r\nHola\r\n"
        ."--BOUNDARY\r\nContent-Disposition: form-data; name=\"mentions[]\"\r\n\r\n5\r\n"
        ."--BOUNDARY\r\nContent-Disposition: form-data; name=\"mentions[]\"\r\n\r\n7\r\n"
        ."--BOUNDARY\r\nContent-Disposition: form-data; name=\"flag\"\r\n\r\n1\r\n"
        ."--BOUNDARY\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"data.csv\"\r\nContent-Type: text/csv\r\n\r\na,b\r\n"
        ."--BOUNDARY--\r\n"
    )->and($body->isSeekable())->toBeTrue();
});

it('generates a random boundary and content type', function (): void {
    $multipart = new Multipart;

    expect($multipart->boundary)->toStartWith('mopux-')
        ->and($multipart->contentType())->toBe('multipart/form-data; boundary='.$multipart->boundary)
        ->and((new Multipart)->boundary)->not->toBe($multipart->boundary);
});

it('reads uploads from paths, resources and psr streams', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'mopux');
    file_put_contents($path, '%PDF-1.4 test');
    $resource = fopen('php://memory', 'r+b');
    fwrite($resource, 'from resource');

    $body = (string) (new Multipart('B'))
        ->file('a', Upload::fromPath($path, 'spec.pdf', 'application/pdf'))
        ->file('b', Upload::fromStream($resource, 'r.txt', 'text/plain'))
        ->file('c', Upload::fromStream(Utils::streamFor('from psr'), 'p.txt'))
        ->build(new HttpFactory);
    unlink($path);

    expect($body)->toContain("filename=\"spec.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.4 test\r\n")
        ->and($body)->toContain("filename=\"r.txt\"\r\nContent-Type: text/plain\r\n\r\nfrom resource\r\n")
        ->and($body)->toContain("filename=\"p.txt\"\r\nContent-Type: application/octet-stream\r\n\r\nfrom psr\r\n");
});

it('detects the file name and mimetype from a path', function (): void {
    $path = sys_get_temp_dir().'/mopux-notes-'.bin2hex(random_bytes(4)).'.txt';
    file_put_contents($path, 'plain text');

    $upload = Upload::fromPath($path);
    unlink($path);

    expect($upload->filename)->toBe(basename($path))
        ->and($upload->mimetype)->toBe('text/plain');
});

it('sanitizes file names', function (): void {
    expect(Upload::fromString('x', "evil\"\r\nname/../a.txt")->filename)->toBe("evil'name_.._a.txt")
        ->and(fn () => Upload::fromString('x', '  '))->toThrow(InvalidInputException::class, 'cannot be empty')
        ->and(fn () => Upload::fromPath('/nope/missing.pdf'))->toThrow(InvalidInputException::class, 'is not a readable file')
        ->and(fn () => Upload::fromStream('not a stream', 'a.txt'))->toThrow(InvalidInputException::class);
});
