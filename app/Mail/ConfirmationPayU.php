<?php

namespace App\Mail;

use App\Event;
use App\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Spatie\IcalendarGenerator\Components\Calendar as iCalCalendar;
use Spatie\IcalendarGenerator\Components\Event as iCalEvent;
use Spatie\IcalendarGenerator\PropertyTypes\TextPropertyType as TextPropertyType;

class ConfirmationPayU extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $order;
    public $payU;
    public $payUData;
    public $referencePayu;
    public $transaction_id;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($order, $payU, $test = null)
    {        
        $this->order = $order;
        $this->payU = $payU; 
        $this->payUData = $test;   

        $this->referencePayu = $payU['reference_pol'];
        $this->transaction_id = $payU['transaction_id'];
    }
    /**
     * Build the message.
     *
     * @return $this
     */

    public function build()
    {
        if($this->payUData == "Prueba")
        {
            return $this
            ->from("alerts@evius.co", "Ucronio")
            ->subject("Pago exitoso Test")
            ->markdown('rsvp.payUTest');
        }
        return $this
            ->from("alerts@evius.co", "Ucronio")
            ->subject("Pago exitoso")
            ->markdown('rsvp.payU');
        
    }
}