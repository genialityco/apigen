<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Event;
use App\Rol;
use App\Organization;
use App;

class UserRolChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $organization;
    public $event;
    public $eventUser;
    public $rol;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($event_id, $eventUser, $rol_id)
    {
        $event = Event::find($event_id);
        $rol = Rol::find($rol_id);        
        $organization = Organization::find($event->organizer_id);

        $this->eventUser = $eventUser;
        $this->event = $event;
        $this->rol = $rol;
        $this->organization = $organization;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()    
    {   
        $locale = isset($this->event->language) ? $this->event->language : 'es';
        App::setLocale($locale);

        return $this
                ->from("alerts@evius.co" , $this->organization->name)
                ->subject('Actualización de usuario')
                ->markdown('Mailers.userRolChange');
    }
}
