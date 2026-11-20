<?php

declare(strict_types=1);

namespace Mopux\Http;

use Mopux\Data\Input\Upload;
use Mopux\Exceptions\MopuxException;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Builds `multipart/form-data` bodies. Files are streamed into a `php://temp`
 * buffer (kept in memory up to 2 MB, then on disk), so the body can be rewound
 * and large files are not held in memory.
 *
 * @internal
 */
final class Multipart
{
    public readonly string $boundary;

    /** @var list<array{name: string, value: string}|array{name: string, file: Upload}> */
    private array $parts = [];

    public function __construct(?string $boundary = null)
    {
        $this->boundary = $boundary ?? 'mopux-'.bin2hex(random_bytes(16));
    }

    /**
     * Adds a text field; arrays become repeated `name[]` fields, null values are skipped
     * and booleans are sent as 1/0.
     */
    public function field(string $name, mixed $value): self
    {
        if ($value === null) {
            return $this;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->field($name.'[]', $item);
            }

            return $this;
        }

        $this->parts[] = ['name' => $name, 'value' => match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => throw new MopuxException(sprintf('Multipart field "%s" must be scalar.', $name)),
        }];

        return $this;
    }

    public function file(string $name, Upload $upload): self
    {
        $this->parts[] = ['name' => $name, 'file' => $upload];

        return $this;
    }

    public function contentType(): string
    {
        return 'multipart/form-data; boundary='.$this->boundary;
    }

    public function build(StreamFactoryInterface $streamFactory): StreamInterface
    {
        $buffer = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($buffer === false) {
            throw new MopuxException('Could not allocate a buffer for the multipart body.');
        }

        foreach ($this->parts as $part) {
            fwrite($buffer, '--'.$this->boundary."\r\n");

            if (isset($part['file'])) {
                fwrite($buffer, sprintf(
                    "Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n",
                    self::escape($part['name']),
                    $part['file']->filename,
                    $part['file']->mimetype,
                ));
                $part['file']->writeTo($buffer);
            } else {
                fwrite($buffer, sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", self::escape($part['name'])));
                fwrite($buffer, $part['value']);
            }

            fwrite($buffer, "\r\n");
        }

        fwrite($buffer, '--'.$this->boundary."--\r\n");
        rewind($buffer);

        return $streamFactory->createStreamFromResource($buffer);
    }

    private static function escape(string $name): string
    {
        return str_replace(["\r", "\n", '"'], ['', '', '%22'], $name);
    }
}
