<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * HTTP 422: the request payload failed validation.
 */
class ValidationException extends ApiException
{
    /**
     * Validation messages keyed by field (`data.errors` in the response).
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $data = $this->body['data'] ?? null;
        $errors = is_array($data) ? ($data['errors'] ?? null) : null;

        if (! is_array($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $field => $messages) {
            $messages = is_array($messages) ? $messages : [$messages];
            $normalized[(string) $field] = array_values(array_filter($messages, 'is_string'));
        }

        return $normalized;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors()[$field][0] ?? null;
    }
}
