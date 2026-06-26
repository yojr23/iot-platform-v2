<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DangerAlertMail extends Mailable
{
    public $emailData;

    public function __construct($emailData)
    {
        $this->emailData = $emailData;
    }

    public function build()
    {
        return $this->view('emails.alert')
                    ->subject('Alerta de Peligro Detectada')
                    ->with($this->emailData);
    }
}
