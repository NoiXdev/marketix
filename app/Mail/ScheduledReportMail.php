<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email sent for a scheduled report run. The email body itself is one
 * of the reports.email.{project,link,site} views (rendered from the same
 * $viewData used for the PDF); the CSV/PDF are optional attachments built
 * by the caller (SendScheduledReport).
 */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $viewData
     */
    public function __construct(
        public string $emailView,
        array $viewData,
        string $subject,
        public ?string $csv = null,
        public ?string $pdf = null,
    ) {
        // $subject and $viewData are not promoted: the base Mailable class
        // already declares untyped `public $subject` / `public $viewData`
        // properties, and a typed promoted property here would fatally
        // collide with them. Assign to the inherited properties instead.
        $this->subject = $subject;
        $this->viewData = $viewData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->emailView,
            with: $this->viewData,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->csv !== null) {
            $attachments[] = Attachment::fromData(fn () => $this->csv, 'report.csv')
                ->withMime('text/csv');
        }

        if ($this->pdf !== null) {
            $attachments[] = Attachment::fromData(fn () => $this->pdf, 'report.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
