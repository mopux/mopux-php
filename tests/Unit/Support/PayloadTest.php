<?php

declare(strict_types=1);

use Mopux\Exceptions\InvalidPayloadException;
use Mopux\Support\Payload;

it('reads typed values leniently', function (): void {
    $payload = Payload::of([
        'id' => '12',
        'name' => 'Web',
        'count' => 3,
        'ratio' => '0.5',
        'flag' => 1,
        'off' => '0',
        'date' => '2026-11-20 10:30:00',
        'day' => '2026-11-20',
        'iso' => '2026-11-20T10:30:00+00:00',
        'nothing' => null,
        'ids' => [1, '2'],
        'tags' => ['a', 'b'],
        'child' => ['id' => 5],
        'children' => [['id' => 1], ['id' => 2]],
    ]);

    expect($payload->int('id'))->toBe(12)
        ->and($payload->string('name'))->toBe('Web')
        ->and($payload->string('count'))->toBe('3')
        ->and($payload->float('ratio'))->toBe(0.5)
        ->and($payload->bool('flag'))->toBeTrue()
        ->and($payload->bool('off'))->toBeFalse()
        ->and($payload->date('date')->format('Y-m-d H:i:s'))->toBe('2026-11-20 10:30:00')
        ->and($payload->date('day')->format('Y-m-d H:i:s'))->toBe('2026-11-20 00:00:00')
        ->and($payload->date('iso')->format('H:i'))->toBe('10:30')
        ->and($payload->nullableInt('nothing'))->toBeNull()
        ->and($payload->nullableString('missing'))->toBeNull()
        ->and($payload->nullableDate('nothing'))->toBeNull()
        ->and($payload->nullableObject('nothing'))->toBeNull()
        ->and($payload->intList('ids'))->toBe([1, 2])
        ->and($payload->stringList('tags'))->toBe(['a', 'b'])
        ->and($payload->object('child')->int('id'))->toBe(5)
        ->and($payload->list('children', fn (array $item) => $item['id']))->toBe([1, 2])
        ->and($payload->list('missing', fn (array $item) => $item))->toBe([])
        ->and($payload->array('nothing'))->toBe([])
        ->and($payload->has('nothing'))->toBeTrue()
        ->and($payload->has('missing'))->toBeFalse();
});

it('reports the full path of invalid fields', function (callable $read, string $message): void {
    $payload = Payload::of(['task' => ['id' => 'abc', 'title' => ['x'], 'tags' => [1, ['bad']], 'date' => 'not a date']], 'data');

    expect(fn () => $read($payload))->toThrow(InvalidPayloadException::class, $message);
})->with([
    'int' => [fn (Payload $p) => $p->object('task')->int('id'), '"data.task.id" should be an integer, string given'],
    'string' => [fn (Payload $p) => $p->object('task')->string('title'), '"data.task.title" should be a string, array given'],
    'missing' => [fn (Payload $p) => $p->object('task')->int('missing'), '"data.task.missing" should be an integer, null given'],
    'list item' => [fn (Payload $p) => $p->object('task')->stringList('tags'), '"data.task.tags.1" should be a string, array given'],
    'date' => [fn (Payload $p) => $p->object('task')->date('date'), '"data.task.date" should be a date'],
    'object' => [fn (Payload $p) => $p->object('task')->object('id'), '"data.task.id" should be an object'],
]);
