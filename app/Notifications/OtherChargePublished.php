<?php

namespace App\Notifications;

use App\Models\OtherCharge;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to each matching student when an Other Charge is published.
 *
 * Triggered by: Accounting/OtherChargeController::publish()
 * Recipient:    All students whose active assessment matches the charge's
 *               school_year / semester / year_level / course filters.
 */
class OtherChargePublished extends Notification
{
    use Queueable;

    public function __construct(public OtherCharge $charge) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $portalUrl = route('student.other-charges.index');

        // Build a human-readable scope label so students know who this applies to.
        $scopeParts = array_filter([
            $this->charge->course,
            $this->charge->year_level,
            $this->charge->semester,
            $this->charge->school_year,
        ]);
        $scopeLabel = implode(', ', $scopeParts) ?: 'All Students — ' . $this->charge->school_year;

        return (new MailMessage)
            ->subject('New Charge Posted: ' . $this->charge->title)
            ->greeting('Hello, ' . $notifiable->first_name . '!')
            ->line('The Accounting Office has posted a new charge to your account:')
            ->line('**' . $this->charge->title . '**')
            ->when($this->charge->description, fn ($mail) =>
                $mail->line($this->charge->description)
            )
            ->line('**Amount:** ₱' . number_format((float) $this->charge->amount, 2))
            ->line('**Applies to:** ' . $scopeLabel)
            ->line('---')
            ->line('You may pay this charge online via GCash, Maya, or card through your student portal.')
            ->action('View & Pay Now', $portalUrl)
            ->line('If you believe this charge was posted in error, please contact the Accounting Office.')
            ->salutation('CCDI Accounting Office');
    }
}
