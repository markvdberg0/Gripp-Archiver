<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** E-mail met een document (offerte, factuur, herinnering, opdrachtbevestiging, inkoopopdracht) als PDF-bijlage. */
class DocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $body,
        public ?string $pdfBytes = null,
        public ?string $pdfName = null,
        public ?string $replyToAddress = null,
        public ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->replyToName)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.document', text: 'mail.document-text', with: ['body' => $this->body]);
    }

    public function attachments(): array
    {
        return $this->pdfBytes ? [Attachment::fromData(fn () => $this->pdfBytes, $this->pdfName)->withMime('application/pdf')] : [];
    }
}
