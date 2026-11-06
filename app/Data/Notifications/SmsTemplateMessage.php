<?php

namespace App\Data\Notifications;

use InvalidArgumentException;

/** Parameters are explicit strings: never infer codes or amounts from message text. */
final readonly class SmsTemplateMessage
{
    public function __construct(
        public string $purpose,
        public array $parameters,
    ) {
        if (trim($purpose) === '' || $parameters === [] || count($parameters) > 3) {
            throw new InvalidArgumentException('SMS purpose and one to three parameters are required.');
        }
        foreach ($parameters as $key => $value) {
            if (! in_array($key, ['param1', 'param2', 'param3'], true)
                || ! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('SMS parameters must be named, non-empty strings.');
            }
        }
    }
}
