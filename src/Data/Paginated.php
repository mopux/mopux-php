<?php

declare(strict_types=1);

namespace Mopux\Data;

use Closure;
use Generator;
use Mopux\Support\Payload;

/**
 * One page of a list.
 *
 * The API uses three shapes, all mapped here:
 *
 * - Laravel pagination (`projects`, `projects/{id}/activity`, `team/invitations`):
 *   `{data: [...], links: {...}, meta: {current_page, last_page, per_page, total}}`.
 * - Page fields (`team`, `tasks/{id}/attachments`): `{<items>: [...], page, last_page, total}`.
 * - Plain lists (`tasks/{id}/comments`, `notifications`): no metadata; `total` and
 *   `lastPage` are null and there are more pages while a page comes back full.
 *
 * @template T
 */
final class Paginated
{
    /**
     * @param  list<T>  $items
     * @param  array<array-key, mixed>  $links
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage = 1,
        public readonly ?int $lastPage = null,
        public readonly ?int $perPage = null,
        public readonly ?int $total = null,
        public readonly array $links = [],
    ) {}

    /**
     * @template TItem
     *
     * @param  array<array-key, mixed>  $data
     * @param  callable(array<array-key, mixed>): TItem  $factory
     * @return self<TItem>
     */
    public static function fromLaravel(array $data, callable $factory): self
    {
        $payload = Payload::of($data);
        $meta = $payload->nullableObject('meta') ?? Payload::of([], 'meta');

        return new self(
            items: $payload->list('data', $factory),
            currentPage: $meta->nullableInt('current_page') ?? 1,
            lastPage: $meta->nullableInt('last_page'),
            perPage: $meta->nullableInt('per_page'),
            total: $meta->nullableInt('total'),
            links: $payload->array('links'),
        );
    }

    /**
     * @template TItem
     *
     * @param  array<array-key, mixed>  $data
     * @param  callable(array<array-key, mixed>): TItem  $factory
     * @return self<TItem>
     */
    public static function fromPageFields(array $data, string $itemsKey, callable $factory, ?int $perPage = null): self
    {
        $payload = Payload::of($data);

        return new self(
            items: $payload->list($itemsKey, $factory),
            currentPage: $payload->nullableInt('page') ?? 1,
            lastPage: $payload->nullableInt('last_page'),
            perPage: $perPage,
            total: $payload->nullableInt('total'),
        );
    }

    /**
     * @template TItem
     *
     * @param  array<array-key, mixed>  $items
     * @param  callable(array<array-key, mixed>): TItem  $factory
     * @return self<TItem>
     */
    public static function fromList(array $items, callable $factory, int $page = 1, ?int $perPage = null): self
    {
        return new self(
            items: Payload::of(['items' => $items])->list('items', $factory),
            currentPage: $page,
            perPage: $perPage,
        );
    }

    /**
     * Lazily walks every page, starting at $firstPage, yielding the items one by one.
     * Pages are requested only as the generator advances.
     *
     * @template TItem
     *
     * @param  Closure(int): self<TItem>  $fetch  Receives the page number.
     * @return Generator<int, TItem>
     */
    public static function iterate(Closure $fetch, int $firstPage = 1): Generator
    {
        $page = $firstPage;

        do {
            $result = $fetch($page);

            foreach ($result->items as $item) {
                yield $item;
            }

            $next = $result->nextPage();

            // Guard against an API that keeps answering the same page.
            if ($next !== null && $next <= $page) {
                break;
            }

            $page = $next;
        } while ($page !== null);
    }

    public function hasMorePages(): bool
    {
        if ($this->items === []) {
            return false;
        }

        if ($this->lastPage !== null) {
            return $this->currentPage < $this->lastPage;
        }

        return $this->perPage !== null && count($this->items) >= $this->perPage;
    }

    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @template TResult
     *
     * @param  callable(T): TResult  $callback
     * @return list<TResult>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }
}
