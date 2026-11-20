<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Exceptions\InvalidInputException;
use Psr\Http\Message\StreamInterface;

/**
 * A file to upload (task documents and comment attachments).
 *
 *     Upload::fromPath('/tmp/spec.pdf');
 *     Upload::fromString($csv, 'report.csv', 'text/csv');
 *     Upload::fromStream(fopen('php://memory', 'r+'), 'notes.txt');
 */
final class Upload
{
    /**
     * @param  resource|StreamInterface|string  $source  A path (fromPath), contents (fromString) or a stream.
     */
    private function __construct(
        private readonly mixed $source,
        private readonly bool $isPath,
        public readonly string $filename,
        public readonly string $mimetype,
    ) {}

    public static function fromPath(string $path, ?string $filename = null, ?string $mimetype = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidInputException('file', sprintf('"%s" is not a readable file.', $path));
        }

        return new self($path, true, self::filename($filename ?? basename($path)), $mimetype ?? self::detectMimetype($path));
    }

    public static function fromString(string $contents, string $filename, string $mimetype = 'application/octet-stream'): self
    {
        return new self($contents, false, self::filename($filename), $mimetype);
    }

    /**
     * @param  resource|StreamInterface  $stream
     */
    public static function fromStream(mixed $stream, string $filename, string $mimetype = 'application/octet-stream'): self
    {
        if (! $stream instanceof StreamInterface && ! is_resource($stream)) {
            throw new InvalidInputException('file', 'expected a PHP stream resource or a PSR-7 stream.');
        }

        return new self($stream, false, self::filename($filename), $mimetype);
    }

    /**
     * Copies the file contents into $target (a writable PHP stream).
     *
     * @param  resource  $target
     *
     * @internal
     */
    public function writeTo(mixed $target): void
    {
        if ($this->isPath) {
            $handle = fopen((string) $this->source, 'rb');

            if ($handle === false) {
                throw new InvalidInputException('file', sprintf('"%s" could not be opened.', (string) $this->source));
            }

            stream_copy_to_stream($handle, $target);
            fclose($handle);

            return;
        }

        if (is_string($this->source)) {
            fwrite($target, $this->source);

            return;
        }

        if ($this->source instanceof StreamInterface) {
            if ($this->source->isSeekable()) {
                $this->source->rewind();
            }

            while (! $this->source->eof()) {
                fwrite($target, $this->source->read(1_048_576));
            }

            return;
        }

        if (is_resource($this->source)) {
            if (stream_get_meta_data($this->source)['seekable']) {
                rewind($this->source);
            }

            stream_copy_to_stream($this->source, $target);
        }
    }

    private static function filename(string $filename): string
    {
        $filename = trim(str_replace(["\r", "\n", '"', '\\', '/'], ['', '', "'", '_', '_'], $filename));

        if ($filename === '') {
            throw new InvalidInputException('file', 'the file name cannot be empty.');
        }

        return $filename;
    }

    private static function detectMimetype(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mimetype = mime_content_type($path);

            if (is_string($mimetype) && $mimetype !== '') {
                return $mimetype;
            }
        }

        return 'application/octet-stream';
    }
}
