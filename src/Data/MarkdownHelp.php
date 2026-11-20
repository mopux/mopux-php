<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Markdown supported in task descriptions and comments (`GET tasks/markdown-help`).
 */
final class MarkdownHelp implements DataObject
{
    /**
     * @param  list<array{input: string, output: string}>  $examples
     */
    public function __construct(
        public readonly string $guideUrl,
        public readonly string $mentionHint,
        public readonly array $examples,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'markdown_help');

        return new self(
            guideUrl: $payload->nullableString('guide_url') ?? '',
            mentionHint: $payload->nullableString('mention_hint') ?? '',
            examples: $payload->list('examples', static function (array $example): array {
                $item = Payload::of($example, 'example');

                return ['input' => $item->nullableString('input') ?? '', 'output' => $item->nullableString('output') ?? ''];
            }),
        );
    }

    public function toArray(): array
    {
        return ['guide_url' => $this->guideUrl, 'mention_hint' => $this->mentionHint, 'examples' => $this->examples];
    }
}
