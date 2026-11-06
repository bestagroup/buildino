<?php

namespace App\Services\Notifications;

use App\Data\Notifications\SmsTemplateMessage;
use InvalidArgumentException;

final class SmsTemplateRegistry
{
    public function configured(string $purpose): bool
    {
        $definition = config('notifications.sms_templates.'.$purpose);

        return is_array($definition) && is_string($definition['template'] ?? null)
            && trim($definition['template']) !== '';
    }

    public function payload(SmsTemplateMessage $message): array
    {
        if (! $this->configured($message->purpose)) {
            throw new InvalidArgumentException('SMS template is not configured for this purpose.');
        }
        $definition = config('notifications.sms_templates.'.$message->purpose);
        $expected = $definition['parameters'] ?? [];
        $actual = array_keys($message->parameters);
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new InvalidArgumentException('SMS parameters do not match the configured template.');
        }

        return ['template' => $definition['template']] + $message->parameters;
    }
}
