<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\Gateways\FakePaymentGateway;
use App\Services\Payments\Gateways\GenericHmacJsonGateway;
use InvalidArgumentException;

final class PaymentGatewayManager
{
    public function driver(
        ?string $name = null
    ): PaymentGateway {
        $name = $name
            ?: config(
                'payment_gateways.default'
            );

        $config = config(
            "payment_gateways.gateways.{$name}"
        );

        if (
            ! is_array($config)
            || ! ($config['enabled'] ?? false)
        ) {
            throw new InvalidArgumentException(
                "Payment gateway [{$name}] is not configured or enabled."
            );
        }

        return match (
            $config['driver'] ?? null
        ) {
            'generic_hmac_json' =>
                new GenericHmacJsonGateway(
                    $name,
                    $config
                ),

            'fake' =>
                $this->fake(
                    $name,
                    $config
                ),

            default =>
                throw new InvalidArgumentException(
                    "Unsupported payment gateway driver for [{$name}]."
                ),
        };
    }

    private function fake(
        string $name,
        array $config
    ): PaymentGateway {
        if (
            ! app()->environment(
                'local',
                'testing'
            )
        ) {
            throw new InvalidArgumentException(
                'The fake payment gateway is restricted to local/testing environments.'
            );
        }

        return new FakePaymentGateway(
            $name,
            $config
        );
    }
}
