<?php

namespace App\Mail;

use App\Event;
use App\DiscountCode;
use App\Order;
use App\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Spatie\IcalendarGenerator\Components\Calendar as iCalCalendar;
use Spatie\IcalendarGenerator\Components\Event as iCalEvent;

class PointsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $order; 
    public $item; 
    public $organizer;   
    public $orderSpecification;
   
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($order , $user , $organization)
    {   
        $organization =  Organization::findOrFail($order->organization_id);              
        $this->user = $user;
        $this->order = $order;
        $this->organizer = $organization;
       
        $orderSpecification = [];
        foreach($order->properties as $key => $value)
        {
            $mykey = '<strong>'.ucwords($key).':  </strong>' . $value . '<br/>';
            array_push($orderSpecification , $mykey);
        }
        
        $this->orderSpecification = implode("-",$orderSpecification);       
    }


    /**
     * Build the message.
     *
     * @return $this
     */

    public function build()
    {   
        $organizer = $this->organizer;        
    
        return $this
        ->from("alerts@evius.co", $organizer->name)
        ->subject($organizer->name)
        ->markdown('Mailers.Points');
        
    }
}