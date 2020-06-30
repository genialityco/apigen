<?php

namespace App\Http\Controllers;

use App\Account;
use App\Attendee;
use App\Event;
use App\Invitation;
use App\Mailing;
use App\Mail\friendRequest;
use App\NetworkingContacts;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Response;
use Kreait\Firebase\Exception\Auth\AuthError;
use Kreait\Firebase\Exception\Auth\EmailNotFound;
// firebase errro catch
//use Kreait\Firebase\Exception\Auth\ApiConnectionFailed;
use Kreait\Firebase\Exception\Auth\InvalidPassword;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\InvalidArgumentException;
use Log;
use Mail;
use Redirect;
use Storage;

/**
 * @resource Event
 *
 *
 */

class InvitationController extends Controller
{
    public function __construct(\Kreait\Firebase\Auth $auth)
    {
        $this->auth = $auth;
    }

    public function singIn(Request $request)
    {
        $innerpath = ($request->has("innerpath")) ? $request->input("innerpath") : "";

        $correos_emproblemados = ["FCARDARELLI@SOLUCIONES.COM.AR", "JOSE.MARTINEZ@TELEFONICA.COM", "CARAUZ@VIRTUALIT.COM.EC", "CESTEBAN@ADISTEC.COM&", "JEFFERSON.CAMARGO@V2S.US", "TMORENO@OVNICOM.COM", "PABLO@ZENTEC.PE", "KZUNIGA%40FUSIONETCORP.COM", "JUANC@BLUENETSRL.COM"];
        $email = $request->input("email");

        if (in_array($email, $correos_emproblemados)) {
            $innerpath = "5ed6a74b7e2bc067381ad164";
        }

        if ($request->input("request")) {
            try {
                self::acceptOrDeclineFriendRequest($request, $innerpath, $request->input("request"), $request->input("response"));
            } catch (Exception $e) {

            }
        }
        try {

            $pass = self::decryptdata($request->input("pass"));

            $userinfo = $this->auth->getUserByEmail($request->input("email"));

            try {
                $updatedUser = $this->auth->changeUserPassword($userinfo->uid, $pass);
            } catch (InvalidArgumentException $e) {
                $pass = $request->input("pass");
                $updatedUser = $this->auth->changeUserPassword($userinfo->uid, $pass);
            } catch (AuthError $e) {
                Log::error("temp password used. " . $e->getMessage());
                $pass = "temppassw123";
                $updatedUser = $this->auth->changeUserPassword($userinfo->uid, $pass);
            }

            $singin = $this->auth->signInWithEmailAndPassword($request->input("email"), $pass);

            $save_refresh_token = Account::where("uid", $userinfo->uid)->first();
            if (!$save_refresh_token) {
                return Redirect::to("https://evius.co/" . "landing/" . $innerpath);
            }

            $refresh_token["refresh_token"] = $singin->refreshToken();
            $save_refresh_token->fill($refresh_token);
            $save_refresh_token->save();

            return Redirect::to("https://evius.co/" . "landing/" . $innerpath . "?token=" . $singin->idToken());

        } catch (EmailNotFound $e) {

            Log::error("email no encontrado. " . $e->getMessage());
            return Redirect::to("https://evius.co/" . "landing/" . $innerpath);

        } catch (UserNotFound $e) {
            Log::error("usuario no encontrado. " . $e->getMessage());
            return Redirect::to("https://evius.co/" . "landing/" . $innerpath);

        } catch (InvalidPassword $e) {
            Log::error("contrasena invalida. " . $e->getMessage());
            return Redirect::to("https://evius.co/" . "landing/" . $innerpath);

        } catch (Exception $e) {

            Log::error("Error message. " . $e->getMessage());
            return Redirect::to("https://evius.co/" . "landing/" . $innerpath);

        }
    }

    private function decryptdata($string)
    {

        $options = 0;
        $ciphering = "AES-128-CTR";
        // Non-NULL Initialization Vector for encryption
        $decryption_iv = config('app.encryption_iv');

        // Store the encryption key
        $decryption_key = config('app.encryption_key');

        // Use openssl_decrypt() function to decrypt the data
        $decryption = openssl_decrypt($string, $ciphering,
            $decryption_key, $options, $decryption_iv);

        return $decryption;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request, $event_id)
    {
        return JsonResource::collection(
            Invitation::where("event_id", $event_id)->paginate(config('app.page_size'))
        );
    }

    public function invitationsSent(Request $request, $event_id, $user_id)
    {
        return JsonResource::collection(
            Invitation::where("id_user_requested", $user_id)->where("event_id", $event_id)->paginate(config('app.page_size'))
        );
    }

    public function invitationsReceived(Request $request, $event_id, $user_id)
    {
        return JsonResource::collection(
            Invitation::where("id_user_requesting", $user_id)->where("event_id", $event_id)->paginate(config('app.page_size'))
        );
    }

    public function indexcontacts($event_id, $user_id)
    {

        $contacts = NetworkingContacts::where("user_account", $user_id)->where("event_id", $event_id)->get()->toArray();

        if (!empty($contacts[0]["contacts_id"])) {
            $contacts_id = $contacts[0]["contacts_id"];
            if (!is_null($contacts_id)) {
                return JsonResource::collection(Attendee::find($contacts_id)->makeHidden(["rol", "activities"]));

            }}
        return "aun no tienes contactos.";

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $event_id)
    {

        // end point para enviar solicitud con redireccion a evius
        $data = $request->json()->all();

        // verifica si ya son contactos
        $id_user_requesting = $data["id_user_requesting"];

        $model = NetworkingContacts::where("user_account", $data["id_user_requested"])->first();

        if ($model) {
            if (is_int(array_search($data["id_user_requesting"], $model->contacts_id))) {
                abort(409, "Ya se encuentra en tu lista de contactos");
            }
        }
        $model = Invitation::where("id_user_requesting", $data["id_user_requesting"])->where("id_user_requested", $data["id_user_requested"])->first();
        //verifica si ya se ha enviado una solicitud de amistad igual y el estado de esta
        if ($model) {
            if ($model->response) {
                return $model->response == "rejected" ? abort(409, "Solictiud ya ha sido enviada anteriormente y se encuentra rechazada") : abort(409, "Solictiud ya ha sido  enviada anteriormente y se encuentra aceptada");
            }
            return abort(409, "Solictiud ya ha sido enviada anteriormente, esperando respuesta de solicitud");
        }

        $result = new Invitation($data);
        $result->save();
        $data["request_id"] = $result->_id;
        self::buildMessage($data, $event_id);
        return "invitacion enviada";
    }

    public function sendPushNotification($push_notification)
    {
        $url = config('app.api_evius') . "/events/" . $push_notification["event_id"] . "/sendpush";
        echo var_dump($push_notification);
        $fields = array('event_id' => $push_notification["event_id"], 'title' => "Nueva solicitud", 'img' => true, 'body' => $push_notification["body"], 'User_ids' => [$push_notification["User_ids"]]);

        $headers = array('Content-Type: application/json');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function acceptOrDeclineFriendRequest(Request $request, String $event_id, String $id, $response_alt = null)
    {

        $data = $request->json()->all();
        $Invitation = Invitation::find($id);
        $data["response"] = $data ? $data["response"] : $response_alt;
        self::verifyAndAddContact($Invitation, $data);
        $resp["response"] = $data["response"] ? $data["response"] : $response_alt;

        $Invitation->fill($resp);
        $Invitation->save();
        $resp["id_user_requested"] = $Invitation->id_user_requested;
        $resp["id_user_requesting"] = $Invitation->id_user_requesting;
        return self::buildMessage($resp, $event_id);
    }

    public function verifyAndAddContact($Invitation, $data)
    {

        if ($data["response"] == "accepted" || $data["response"] == "acepted") {
            $user_requested = NetworkingContacts::where("user_account", $Invitation->id_user_requested)->where("event_id", $Invitation->event_id)->first();
            if ($user_requested) {

                $contacts_id = $user_requested->contacts_id;
                $new_contact = [$Invitation->id_user_requesting];
                $contact_merge["contacts_id"] = array_unique(array_merge($new_contact, $contacts_id));
                $user_requested->fill($contact_merge);
                $user_requested->save();

            } else {

                $data_contact["contacts_id"] = [$Invitation->id_user_requesting];
                $data_contact["event_id"] = $Invitation->event_id;
                $data_contact["user_account"] = $Invitation->id_user_requested;
                $save_contacts = new NetworkingContacts($data_contact);
                $save_contacts->save();
            }

            $user_requesting = NetworkingContacts::where("user_account", $Invitation->id_user_requesting)->where("event_id", $Invitation->event_id)->first();
            if ($user_requesting) {

                $contacts_id = $user_requesting->contacts_id;
                $new_contact = [$Invitation->id_user_requested];
                $contact_merge["contacts_id"] = array_unique(array_merge($new_contact, $contacts_id));

                $user_requesting->fill($contact_merge);
                $user_requesting->save();

            } else {

                $data_contact["contacts_id"] = [$Invitation->id_user_requested];
                $data_contact["event_id"] = $Invitation->event_id;
                $data_contact["user_account"] = $Invitation->id_user_requesting;
                $save_contacts = new NetworkingContacts($data_contact);
                $save_contacts->save();
            }

            return "contacto agregado";
        }
    }
    public function buildMessage($data, String $event_id)
    {

        $event = Event::find($event_id);
        $receiver = Attendee::find($data["id_user_requesting"]);
        $sender = Attendee::find($data["id_user_requested"]);
        $client = new Client();
        $mail["id_user_requesting"] = $data["id_user_requesting"];
        $mail["id_user_requested"] = $data["id_user_requested"];
        $mail["mails"] = $receiver->email ? [$receiver->email] : [$receiver->properties["email"]];
        $mail["subject"] = $sender->properties["displayName"] . " Te ha enviado una solicitud de amistad";
        $mail["title"] = $sender->properties["displayName"] . " Te ha enviado una solicitud de amistad";
        $mail["desc"] = "Hola " . $receiver->properties["displayName"] . ", quiero contactarte por medio del evento " . $event->name;
        $mail["sender"] = $event->name;
        $mail["event_id"] = $event_id;
        if (!empty($data["request_id"])) {
            $mail["request_id"] = $data["request_id"];
        }
        if (!empty($data["response"])) {
            $mail["mails"] = $sender->email ? [$sender->email] : [$sender->properties["email"]];

            $mail["title"] = $data["response"] == "accepted" ? $receiver->properties["displayName"] . " ha aceptado tu solicitud" : $receiver->properties["displayName"] . " Ha declinado tu solicitud de amistad";
            $mail["desc"] = $data["response"] == "accepted" ? $receiver->properties["displayName"] . " ha aceptado tu solicitud de amistad para el evento " . $event->name : " Lo sentimos " . $receiver->properties["displayName"] . " ha declinado tu solicitud de amistad para el evento " . $event->name;

            $mail["subject"] = "Respuesta a solicitud de amistad";
        }

        //echo self::sendPushNotification($push_notification);
        self::sendEmail($mail, $event_id, $receiver, $sender);
        return "Invitation send";
    }

    public function sendEmail($mail, $event_id, $receiver, $sender_user)
    {

        $mail["event_id"] = $event_id;
        $mail["type"] = "friend request sent";
        $result = new Mailing($mail);
        $title = $mail["title"];
        $desc = $mail["desc"];
        $img = "no img for now";
        $sender = $mail["sender"];
        $subject = $mail["subject"];
        $result->save();
        $response = !empty($mail["request_id"]) ? $mail["request_id"] : null;

        foreach ($mail["mails"] as $key => $email) {
            Mail::to($email)->send(
                new friendRequest($event_id, $title, $desc, $subject, $img, $sender, $response, $email, $receiver, $sender_user)
            );
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Invitation  $Invitation
     * @return \Illuminate\Http\Response
     */
    public function show(string $event_id, string $id)
    {
        // esto muestra la informacion filtrada por event user

        $Invitation = Invitation::findOrFail($id);
        $response = new JsonResource($Invitation);
        return $response;
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Invitation  $Invitation
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $event_id, string $id)
    {
        $data = $request->json()->all();
        $Invitation = Invitation::findOrFail($id);
        $Invitation->fill($data);
        $Invitation->save();
        return $data;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, string $event_id, string $id)
    {
        $Invitation = Invitation::findOrFail($id);
        return (string) $Invitation->delete();

    }

    public function SendInvitation(Request $request)
    {
        echo "hi";die;
        //     $data = $request->json()->all();
        //     if ($request->get('send') == '1') {
        //         $pdf = PDF::loadview('Public.ViewEvent.Partials.Invitation', $data);
        //         $pdf->setPaper( 'letter',  'landscape' );
        //         return $pdf->download('content.pdf');
        //         return view('Public.ViewEvent.Partials.ContentMail', $data);
        //         $data_single = "tfrdrummer@gmail.com";
        //         Mail::send("Public.ViewEvent.Partials.ContentMail",$data , function ($message) use ($data,$pdf,$data_single){
        //             $message->to($data_single,"Evento PMI")
        //             ->subject("HI¡","ni idea");
        //         });
        //
        //     }
        //     return view('Public.ViewEvent.Partials.Invitation', $data);

    }

}