<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class TransactionalEmail extends Mailable
{
    /** @param array<string, string> $messageData */
    public function __construct(public string $deliveryId, public array $messageData) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pyesio · '.$this->messageData['title']);
    }

    public function headers(): Headers
    {
        return new Headers(messageId: strtolower($this->deliveryId).'@'.parse_url(config('app.url'), PHP_URL_HOST));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.transactional', text: 'mail.transactional-text');
    }
}
