<?php

namespace App\Notifications;

use App\Models\OtherCharge;
use App\Models\OtherChargePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student when their Other Charge payment is confirmed.
 *
 * Covers two paths:
 *   1. OTC (cash/bank)   — triggered by Accounting/OtherChargeController::recordPayment()
 *   2. Online (PayMongo) — triggered by OtherChargeService::handleWebhookPaid()
 */
class OtherChargePaymentConfirmed extends Notification
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
        $portalUrl     = route('student.other-charges.index');
        $paymentMethod = match ($this->payment->payment_method) {
            'otc'    => 'Over-the-Counter (Cash / Bank)',
            'online' => 'Online Payment',
            default  => ucfirst($this->payment->payment_method ?? 'N/A'),
        };
        $paidAt    = $this->payment->paid_at?->format('F d, Y g:i A') ?? now()->format('F d, Y g:i A');
        $reference = $this->payment->reference ?? $this->payment->or_number ?? 'N/A';

        $mail = (new MailMessage)
            ->subject('Payment Confirmed: ' . $this->charge->title)
            ->greeting('Hello, ' . $notifiable->first_name . '!')
            ->line('Your payment for the following charge has been confirmed:')
            ->line('**Charge:** ' . $this->charge->title)
            ->line('**Amount Paid:** ₱' . number_format((float) $this->payment->amount_paid, 2))
            ->line('**Payment Method:** ' . $paymentMethod)
            ->line('**Date:** ' . $paidAt)
            ->line('**Reference / OR#:** ' . $reference)
            ->line('**Status: PAID ✓**')
            ->line('---')
            ->action('View My Charges', $portalUrl)
            ->line('Please keep this email as your official receipt for this charge.')
            ->salutation('CCDI Accounting Office');

        return $mail;
    }
}
