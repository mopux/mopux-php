<?php

declare(strict_types=1);

namespace Mopux\Testing;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * PSR-18 network failure (timeout, DNS, connection reset) to simulate with MockClient.
 */
final class NetworkError extends RuntimeException implements NetworkExceptionInterface
{
    private ?RequestInterface $request = null;

    public function __construct(string $message = 'Connection timed out')
    {
        parent::__construct($message);
    }

    public function forRequest(RequestInterface $request): self
    {
        $error = new self($this->getMessage());
        $error->request = $request;

        return $error;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request ?? throw new \LogicException('The network error is not bound to a request.');
    }
}
