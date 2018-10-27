<?php

namespace App\Mail;

use App\evaLib\Services\GoogleFiles;
use App\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use QRCode;
use Storage;

class BookingConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable, InteractsWithQueue;
    public $event;
    public $event_location;
    public $eventuser_name;
    public $eventuser_id;
    public $qr;
    public $imgqr ="xxxx";
    public $qrdos;
    public $logo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        $eventUser) {
        $event = Event::find($eventUser->event_id);
        $event_location = ($event["location"]["FormattedAddress"]);
        $eventUser_name = ($eventUser["properties"]["name"]);
        $eventUser_id = $eventUser->id;

        $this->event = $event;
        $this->event_location = $event_location;
        $this->eventuser_name = $eventUser_name;
        $this->eventuser_id = $eventUser_id;
        $this->subject = "[Tu Ticket - " . $event->name . "]";
        $this->build();

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $gfService = new GoogleFiles();

        $from = $this->event->organizer->name;
        $logo_evius = 'images/logo.png';
        $file = $this->eventuser_id . '_qr.png';
        $fullpath = storage_path('app/public/' . $file);

        try {
            $image = QRCode::text($this->eventuser_id)
                ->setSize(8)
                ->setMargin(4)
                ->setOutfile($fullpath)
                ->png();

            $img = Storage::get("public/" . $file);

            $url = $gfService->storeFile($img, $file);
            $this->qr =  (string)$url;
            $this->qrdos = "https://storage.googleapis.com/herba-images/evius/events/5bd375f972b12700e76ed592_qr.png";
            
            Log::debug("url: " . (string)$url);
            Log::debug("url type: " . (gettype($url)));
            $this->imgqr ="hhhh".$gfService->storeFile($img, $file)."iirraa";
            //$img = Storage::delete("public/".$file);
            $this->logo = url($logo_evius);
            Log::debug("logo: " . (string)$url);

        } catch (\Exception $e) {
            Log::debug("error: " . $e->getMessage());
            var_dump($e->getMessage());
        }

        return $this
            ->from("apps@mocionsoft.com", $from)
            ->subject($this->subject)
            ->markdown('bookingConfirmed');
    }
}
