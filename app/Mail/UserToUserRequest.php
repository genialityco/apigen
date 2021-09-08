<?php

namespace App\Mail;

use App\evaLib\Services\GoogleFiles;
use App\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App;
class UserToUserRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels; //, Dispatchable, InteractsWithQueue;
    public $event;
    public $event_address;
    public $event_city;
    public $event_state;
    public $logo;
    public $attach;
    public $description;
    public $principal_title;
    public $img;
    public $link;
    public $title;
    public $sender;
    public $desc;
    public $subject;
    public $response;
    public $email;
    public $link_authenticated;
    public $linkalevento;
    public $link_authenticatedalevento;
    public $request_type;
    public $status;
    public $linkUnsubscribe;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($event_id, $request_type, $title, $desc, $subject, $img, $sender, $response, $email, $receiver, $sender_user,$status=null)
    {
        //$response es el request_id
        Log::debug("recibiendo event_user");
        $request_type = ($request_type) ? $request_type : "friendship";
        $event = Event::find($event_id);
        $event_address = isset($event["location"]["FormattedAddress"]) ? ($event["location"]["FormattedAddress"]) : " ";
        $event_city = isset($event["location"]["City"]) ? ($event["location"]["City"]) : " ";
        $event_state = isset($event["location"]["state"]) ? ($event["location"]["state"]) : " ";

        $password = isset($receiver["properties"]["password"]) ? $receiver["properties"]["password"] : "evius.2040";
        $pass = self::encryptdata($password);

        Log::debug("cargando datos event_user al correo");

        $principal_title = $title;
        $description = $desc;
        $link_authenticated = "";
        //response es el id de la petición ya sea reunión o solicitud de amistad
        if ($response) {
            switch($request_type){
                case "meeting":
                    $link = config('app.api_evius') . "/event/{$event_id}/meeting/{$response}";
                    $link_authenticated = config('app.api_evius') . "/event/{$event_id}/meeting/{$response}";
                break;
                case "friendship":
                default:
                   $link = config('app.api_evius') . "/singinwithemail?email=" . urlencode($email) . '&innerpath=' . $event_id . "&request_type=" . $request_type . "&request=" . $response . "&pass=" . $pass;
            break;
            }       
       
        }else{
            $link = config('app.api_evius') . "/singinwithemail?email=" . urlencode($subject) . '&innerpath=' . $event_id . "&pass=" . $pass;
            $link_authenticated = config('app.api_evius') . "/singinwithemail?email=" . urlencode($email) . '&innerpath=' . $event_id . "&pass=" . $pass;
        }
        $linkalevento = config('app.api_evius') . "/singinwithemail?email=" . urlencode($subject) . '&innerpath=' . $event_id . "&pass=" . $pass;
        $link_authenticatedalevento = config('app.api_evius') . "/singinwithemail?email=" . urlencode($email) . '&innerpath=' . $event_id . "/networking&pass=" . $pass;
        $linkUnsubscribe =config('app.api_evius'). '/events/' .$event->_id . '/eventusers/' . $event_id .'/unsubscribe';
        var_dump($link);
        var_dump($link_authenticatedalevento);die;


        $this->response = $response;
        $this->email = $email;
        $this->link = $link;
        $this->link_authenticated = $link_authenticated;
        $this->linkalevento = $linkalevento;
        $this->link_authenticatedalevento = $link_authenticatedalevento;        
        $this->$principal_title = $principal_title;
        $this->$img = $img;
        $this->$description = $description;
        $this->event_address = $event_address;
        $this->event_city = $event_city;
        $this->event_state = $event_state;
        $this->event = $event;
        $this->title = $title;
        $this->desc = $desc;
        $this->sender = $sender;
        $this->request_type = $request_type;
        $this->status = $status;
        $this->linkUnsubscribe = $linkUnsubscribe;
        $this->subject = $subject;
        $gfService = new GoogleFiles();

        Log::debug("pasando a crear correo");
    }

    private function encryptdata($string)
    {

        // Store the cipher method
        $ciphering = "AES-128-CTR"; //config(app.chiper);

        // Use OpenSSl Encryption method
        $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;

        // Non-NULL Initialization Vector for encryption
        $encryption_iv = config('app.encryption_iv');

        // Store the encryption key
        $encryption_key = config('app.encryption_key');

        // Use openssl_encrypt() function to encrypt the data
        $encryption = openssl_encrypt($string, $ciphering,
            $encryption_key, $options, $encryption_iv);

        // Display the encrypted string
        return $encryption;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {   

        Log::debug("Construyendo el correo de ticket");
        $gfService = new GoogleFiles();
        $from = $this->event->organizer->name;
        $logo_evius = 'images/logo.png';
        $event = $this->event;
        $sender = $this->sender;
        $event_address = $this->event_address;
        $event_city = $this->event_city;
        $event_state = $this->event_state;
        $principal_title = $this->principal_title;
        $description = $this->description;
        $title = $this->title;
        $desc = $this->desc;
        $subject = $this->subject;
        $img = $this->img;

        $locale = isset($event->language) ? $event->language : 'es';
        App::setLocale($locale);
        // var_dump($desc);die;
        return $this
        // ->attach($attachPath,[
        //     'as' => 'checkin',
        //     'mime' => 'image/png',
        // ])
        // ->attachData($pdf->download(),'boleta.pdf')
            ->subject($subject)
            ->markdown('usertouser_request');

    }

}