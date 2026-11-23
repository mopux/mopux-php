<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mopux\Laravel\Facades\Mopux;
use Mopux\Laravel\Middleware\VerifyMopuxWebhook;
use Mopux\Laravel\Testing\MopuxFake;
use Mopux\Webhooks\WebhookEvent;
use Mopux\Webhooks\WebhookEventType;
use Mopux\Webhooks\WebhookSignature;

beforeEach(function () {
    config(['mopux.webhook_secret' => 'whsec_laravel_test', 'mopux.url' => null]);
});

it('parses a fake request through the facade using only the endpoint secret', function () {
    $request = MopuxFake::webhook(WebhookEventType::Ping, ['message' => 'Mopux webhook test']);
    $event = Mopux::webhook($request);

    expect($event->event)->toBe(WebhookEventType::Ping)
        ->and($event->data)->toBe(['message' => 'Mopux webhook test'])
        ->and($event->deliveryId)->toBe($request->headers->get('X-Mopux-Delivery'))
        ->and($event->deliveryId)->not->toBe($event->id);
});

it('passes verified events through middleware and rejects bad requests', function () {
    Route::post('/webhooks/mopux', function (Request $request) {
        $event = $request->attributes->get(WebhookEvent::class);

        expect($event)->toBeInstanceOf(WebhookEvent::class)
            ->and($event->event)->toBe(WebhookEventType::Ping);

        return response()->noContent();
    })->middleware(VerifyMopuxWebhook::class);

    $request = MopuxFake::webhook('ping', ['message' => 'Mopux webhook test']);
    $body = $request->getContent();
    $this->call('POST', $request->getRequestUri(), server: $request->server->all(), content: $body)->assertNoContent();
    $this->call('POST', $request->getRequestUri(), server: $request->server->all(), content: $body."\n")->assertUnauthorized();

    $request->server->set('HTTP_X_MOPUX_SIGNATURE', WebhookSignature::sign('{', 'whsec_laravel_test'));
    $this->call('POST', $request->getRequestUri(), server: $request->server->all(), content: '{')->assertBadRequest();
});
