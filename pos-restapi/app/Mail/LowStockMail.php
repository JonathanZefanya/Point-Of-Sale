<?php

namespace App\Mail;

use App\Models\Outlet;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Peringatan stok menipis/habis per outlet (3.8). */
class LowStockMail extends Mailable
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(public Outlet $outlet, public array $items) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: count($this->items)." produk perlu diisi ulang di {$this->outlet->code}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.low-stock');
    }
}
