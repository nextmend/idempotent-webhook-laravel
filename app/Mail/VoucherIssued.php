<?php

namespace App\Mail;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;

// Retried by the queue worker, not by a Midtrans retry.
#[Tries(5)]
#[Backoff(60)]
class VoucherIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Voucher $voucher) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kode voucher untuk '.$this->voucher->order->order_id);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.voucher');
    }
}
