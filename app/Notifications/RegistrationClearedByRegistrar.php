<?php

namespace App\Notifications;

use App\Models\StudentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the applicant when the Registrar clears their registration
 * academically. Informs them that the next step is Finance approval.
 */
class RegistrationClearedByRegistrar extends Notification
{
    use Queueable;

    public function __construct(public StudentRegistration $registration) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $statusUrl = route('registration.status', ['token' => $this->registration->tracking_token]);

        return (new MailMessage)
            ->subject('Academic Clearance Granted — Awaiting Finance Approval')
            ->greeting('Hello, ' . $this->registration->first_name . '!')
            ->line('Good news! The Registrar\'s Office has reviewed your registration and granted academic clearance.')
            ->line('**Your registration has moved to the next stage: Finance Department approval.**')
            ->line('---')
            ->line('The Finance Department (Accounting Office) will review your registration details and process your enrollment fees.')
            ->line('You will receive another email once that review is complete.')
            ->action('Track My Registration Status', $statusUrl)
            ->line('If you have questions, please contact the Registrar\'s Office directly.')
            ->salutation('CCDI Registrar\'s Office');
    }
}