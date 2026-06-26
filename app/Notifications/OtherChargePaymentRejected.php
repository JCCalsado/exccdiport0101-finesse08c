<?php

namespace App\Notifications;

use App\Models\OtherCharge;
use App\Models\OtherChargePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student when their Other Charge bank-transfer proof is rejected
 * by the Disbursing Officer.
 *
 * The payment row is sent back to 'awaiting_proof' (not cancelled) — this
 * notification links the student straight back to the same proof-upload
 * page so they can re-submit without re-entering their reference number.
 */
class OtherChargePaymentRejected extends Notification
{
    use Queueable;

    public function __construct(
        public OtherCharge        $charge,
        public OtherChargePayment $payment,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $reuploadUrl = route('student.other-charges.proof.show', $this->payment->id);
        $reason      = $this->payment->rejection_reason ?? 'No reason provided.';

        return (new MailMessage)
            ->subject('Action Needed: Proof of Payment Rejected — ' . $this->charge->title)
            ->greeting('Hello, ' . $notifiable->first_name . '!')
            ->line('Your uploaded proof of payment for the following charge could not be verified:')
            ->line('**Charge:** ' . $this->charge->title)
            ->line('**Amount:** ₱' . number_format((float) $this->payment->amount_paid, 2))
            ->line('**Reference Number:** ' . ($this->payment->reference ?? 'N/A'))
            ->line('**Reason:** ' . $reason)
            ->line('Please upload a clear photo or scan of your bank transfer receipt to continue.')
            ->action('Re-upload Proof of Payment', $reuploadUrl)
            ->salutation('CCDI Accounting Office');
    }
}
