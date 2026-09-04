<?php

declare(strict_types=1);

namespace MailGazelle\ValueObjects;

use MailGazelle\Exceptions\ValidationException;

/**
 * An email address with an optional display name.
 */
final readonly class Address
{
    public string $email;

    public ?string $name;

    /**
     * @throws ValidationException When the address is empty or not a valid email.
     */
    public function __construct(string $email, ?string $name = null)
    {
        $normalized = trim($email);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(
                sprintf('"%s" is not a valid email address.', $email),
                'validation_error',
                422,
            );
        }

        $this->email = $normalized;
        $this->name = $name === null || trim($name) === '' ? null : trim($name);
    }

    /**
     * JSON representation used in API request bodies.
     *
     * @return array{email: string, name?: string}
     */
    public function toArray(): array
    {
        $payload = ['email' => $this->email];
        if ($this->name !== null) {
            $payload['name'] = $this->name;
        }

        return $payload;
    }
}
