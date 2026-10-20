<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The sign-in code a returning widget shopper asked for (MASTER-ONLY). Queued: never sent inside the request. */
class MerchantWidgetShopperCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $brand, public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your '.$this->brand.' sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.merchant-widget-shopper-code');
    }
}
