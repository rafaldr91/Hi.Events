<?php

namespace HiEvents\Mail\Gdpr;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/gdpr/data-export-request.blade.php
 */
class GdprDataExportEmail extends BaseMail
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Personal Data Export'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.gdpr.data-export-request',
            with: [
                'email' => $this->email,
                'exportUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::GDPR_EXPORT),
                    $this->token,
                ),
            ]
        );
    }
}
