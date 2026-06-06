<?php

namespace App\Notifications;

use App\Models\StudentRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the applicant when the Registrar rejects their registration.
 * This is a terminal rejection — they must visit the Registrar's office
 * for guidance. Unlike Finance rejection, re-application is not self-serve.
 */
class RegistrationRejectedByRegistrar extends Notification
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
            ->subject('CCDI Registration — Academic Review Result')
            ->greeting('Hello, ' . $this->registration->first_name . '.')
            ->line('We regret to inform you that the Registrar\'s Office was unable to approve your registration at this time.')
            ->line('**Reason provided by the Registrar:**')
            ->line($this->registration->registrar_rejection_reason ?? 'No reason provided. Please contact the Registrar\'s Office.')
            ->line('---')
            ->line('**Next steps:** Please visit the Registrar\'s Office in person for further assistance. Bring a copy of your registration documents and this email.')
            ->action('View My Registration Status', $statusUrl)
            ->line('We apologize for any inconvenience. The Registrar\'s staff will be happy to guide you through the correct process.')
            ->salutation('CCDI Registrar\'s Office');
    }
}