<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CapacityAlertMail extends Mailable
{
    public function __construct(public string $metric, public string $level, public float $pct, public string $detail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[IoT] Capacidad {$this->level}: {$this->metric} al ".round($this->pct, 1).'%');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e("Métrica {$this->metric}: {$this->level} ({$this->detail}). Libera espacio o revisa la retención antes de que el servidor se llene.").'</p>');
    }
}
