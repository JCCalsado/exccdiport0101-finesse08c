<?php

namespace App\Notifications;

use App\Models\StudentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the applicant when either:
 *   - The Registrar requests a revision (revision_stage = 'registrar')
 *   - The Finance (Disbursing Officer) requests a revision (revision_stage = 'finance' or null)
 *
 * BUG FIX: The original class always read $registration->revision_notes (the Finance field)
 * regardless of revision_stage. When the Registrar triggers a revision, the notes are
 * written to registrar_revision_notes — revision_notes is null — so the student received
 * a blank "What to revise" line.
 *
 * Fix: select the correct notes field based on revision_stage, and adapt the email body
 * and salutation to identify the correct department.
 */
class RegistrationNeedsRevision extends Notification
{
    use Queueable;

    public function __construct(public StudentRegistration $registration) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // Signed URL expires in 72 hours — student must use this to access the revision form.
        $revisionUrl = URL::temporarySignedRoute(
            'registration.edit',
            now()->addHours(72),
            ['token' => $this->registration->tracking_token]
        );

        // ── Stage-aware content ───────────────────────────────────────────────
        //
        // revision_stage tells us WHICH department sent this revision request.
        // Each stage has its own notes column and its own department identity.
        //
        //   'registrar' → Registrar's Office sent this; notes in registrar_revision_notes
        //   'finance'   → Finance (Accounting) sent this; notes in revision_notes
        //   null        → Legacy path; treat as finance
        //
        $isRegistrarStage = $this->registration->revision_stage === 'registrar';

        $revisionNotes = $isRegistrarStage
            ? $this->registration->registrar_revision_notes
            : $this->registration->revision_notes;

        $department  = $isRegistrarStage ? "Registrar's Office" : 'Accounting Department';
        $salutation  = $isRegistrarStage ? "CCDI Registrar's Office" : 'CCDI Accounting Department';
        $resubmitMsg = $isRegistrarStage
            ? 'Once you resubmit, your registration will be reviewed again by the Registrar.'
            : 'Once you resubmit, your registration will be reviewed again by the Accounting Department.';

        return (new MailMessage)
            ->subject('Action Required: Your CCDI Registration Needs Revision')
            ->greeting('Hello, ' . $this->registration->first_name . '.')
            ->line("The {$department} has reviewed your registration and has requested some corrections.")
            ->line('**What to revise:**')
            ->line($revisionNotes ?? 'Please review your registration details and make the necessary corrections.')
            ->line('---')
            ->line('Please click the button below to update your registration. The link is valid for **72 hours**.')
            ->action('Update My Registration', $revisionUrl)
            ->line($resubmitMsg)
            ->salutation($salutation);
    }
}