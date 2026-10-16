<?php

namespace App\Mail;

use App\Models\Outlet;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Ringkasan penjualan harian per outlet (3.8). */
class DailyReportMail extends Mailable
{
    public function __construct(public Outlet $outlet, public string $date, public array $data) {}

    public function envelope(): Envelope
    {
        $label = CarbonImmutable::parse($this->date)->locale('id')->translatedFormat('l, d F Y');

        return new Envelope(subject: "Laporan harian {$this->outlet->code} · {$label}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.daily-report');
    }
}
