<?php

namespace App\Mail;

use App\Enums\ReportFrequency;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Project $project,
        public ReportFrequency $frequency,
        public string $periodLabel,
        public array $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->frequency->label()} report for {$this->project->name} — {$this->periodLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scheduled-report',
            with: [
                ...$this->payload,
                'projectName' => $this->project->name,
                'settingsUrl' => route('app.project.settings.notifications', ['project' => $this->project->id]),
            ],
        );
    }
}
