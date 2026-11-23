<?php

declare(strict_types=1);

namespace Mopux\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mopux\Exceptions\InvalidPayloadException;
use Mopux\Exceptions\InvalidWebhookSignatureException;
use Mopux\Laravel\Facades\Mopux;
use Mopux\Webhooks\WebhookEvent;
use Symfony\Component\HttpFoundation\Response;

final class VerifyMopuxWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = Mopux::webhook($request);
        } catch (InvalidWebhookSignatureException) {
            return new Response('Invalid Mopux webhook signature.', Response::HTTP_UNAUTHORIZED);
        } catch (InvalidPayloadException) {
            return new Response('Invalid Mopux webhook payload.', Response::HTTP_BAD_REQUEST);
        }

        $request->attributes->set(WebhookEvent::class, $event);

        return $next($request);
    }
}
