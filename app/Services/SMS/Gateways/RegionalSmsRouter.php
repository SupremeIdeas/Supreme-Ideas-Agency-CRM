<?php

namespace App\Services\SMS\Gateways;

/**
 * Picks a send-only gateway for a destination, or none. Off by default: nothing is routed until the admin enables
 * sms.regional.enabled AND lists dial codes. It is NOT called by MessageSenderService (a Naara Line message is sent from the
 * user's own number by the provider that owns it, and a gateway cannot do that or receive replies). It exists for traffic that
 * is sent from a sender ID by design: platform notices and verification messages.
 */
class RegionalSmsRouter
{
    /** @return list<string> container ids of the gateways, in preference order */
    private const GATEWAYS = ['sms.africastalking', 'sms.montymobile'];

    public function gatewayFor(string $e164): ?OutboundSmsGateway
    {
        foreach (self::GATEWAYS as $id) {
            /** @var OutboundSmsGateway $gateway */
            $gateway = app($id);
            if ($gateway->coversDestination($e164)) {
                return $gateway;
            }
        }

        return null;
    }
}
