<?php

namespace App\Mail;

use App\Models\ProjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProjectInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to '.$this->invitation->project->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.project-invitation',
            with: [
                'projectName' => $this->invitation->project->name,
                'acceptUrl' => route('app.invitations.show', ['token' => $this->token]),
            ],
        );
    }
}
