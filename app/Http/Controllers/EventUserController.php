<?php

namespace App\Http\Controllers;

use App\Account;
use App\Activities;
use App\Attendee;
use App\BingoCard;
use App\evaLib\Services\AdministratorService;
use App\evaLib\Services\EvaRol;
use App\evaLib\Services\FilterQuery;
use App\evaLib\Services\UpdateRolEventUserAndSendEmail;
use App\evaLib\Services\UserEventService;
use App\Event;
use App\Http\Resources\EventUserResource;
// use App\RolEvent;
use App\Message;
use App\Order;
use App\OrganizationUser;
use App\State;
use Carbon\Carbon;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Log;
use Mail;
use Validator;
use Auth;

/**
 * @group EventUser
 *
 *
 * Handles the relation bewteeen user and event.  It handles user booking into an event
 * Account relation to an event is one of the fundamental aspects of this platform
 * Most of the user functionality is executed under "Attendee" model and not directly under Account, because is an events platform.
 *
 *
 * <p style="border: 1px solid #DDD">
 * Attendee has one user though account_id
 * <br> and one event though event_id
 * <br> This relation has states that represent the booking status of the user into the event
 * </p>
 *
 */
class EventUserController extends Controller
{

    const CREATED = 'CREATED';
    const UPDATED = 'UPDATED';
    const MESSAGE = 'OK';

    /**
     * _index_ display all the EventUsers of an event
     * @authenticated
     *
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     *
     */
    public function index(Request $request, String $event_id, FilterQuery $filterQuery)
    {

        $input = $request->all();
        $query = Attendee::where("event_id", $event_id);
        $results = $filterQuery::addDynamicQueryFiltersFromUrl($query, $input);
        return EventUserResource::collection($results);
    }

    /**
     * _meInEvent_: user information logged into the event
     * @authenticated
     *
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     *
     */

    public function meInEvent($event_id)
    {
        $query = Attendee::where("event_id", $event_id)->where("account_id", auth()->user()->_id)->first();

        $results = $query->makeHidden(['activities', 'event']);
        return new EventUserResource($results);
    }

    /**
     * ListEventUsersWithBingoCards: List the attendees of the event identifying if they have assigned bingo cards
     *
     * @urlParam event required  event_id
     *
     */
    public function ListEventUsersWithBingoCards(Request $request, $event)
    {
        // Cantidad de elementos que se quieren paginar
        $numberItems = $request->query('numberItems') ? $request->query('numberItems') : 10;

        $eventUsers = Attendee::where("event_id", $event)
            ->select('_id', 'properties.names', 'properties.email', 'account_id')
            ->paginate($numberItems);

        $attendeesist = [];
        foreach ($eventUsers as $eventUser) {
            $userImage = Account::where('email', $eventUser->properties['email'])
                ->select('picture')
                ->first();

            // estructura con datos necesarios
            $dataEventUser = [
                '_id' => $eventUser->_id,
                'properties' => [
                    'names' => $eventUser->properties['names'],
                    'email' => $eventUser->properties['email'],
                    'picture' => isset($userImage->picture) ?
                        $userImage->picture : 'https://www.gravatar.com/avatar/?d=mp&f=y',
                ],
                'bingo' => null,
            ];

            // asignar bingoCard y true si el asistente tiene carton
            $bingoCard = BingoCard::where([
                ['event_id', $event],
                ['event_user_id', $eventUser->_id],
            ])->first();

            if (isset($bingoCard)) {
                $dataEventUser['bingo'] = true;
                $dataEventUser['bingo_card'] = $bingoCard;
            } else {
                $dataEventUser['bingo'] = false;
            }

            array_push($attendeesist, $dataEventUser);
        }

        //$response = new Paginator($attendeesist, 10);

        return response()->json([
            'data' => $attendeesist,
            'current_page' => $eventUsers->currentPage(),
            //'first_page_url' => $eventUsers->firstPage(),
            'last_page_url' => $eventUsers->lastPage(),
            'next_page_url' => $eventUsers->nextPageUrl(),
            'prev_page_url' => $eventUsers->previousPageUrl(),
            'total' => $eventUsers->total()
        ]);
    }

    public function searchEventUserWithBingoCard(Request $request, string $eventID)
    {
        $name = $request->query('name');

        $eventUsers = Attendee::where('event_id', $eventID)
            ->where('properties.names', 'like', '%' . $name . '%')
            ->select('_id', 'properties.names', 'properties.email', 'account_id')
            ->get();

        $eventUserIds = $eventUsers->pluck('_id');

        $userImages = Account::whereIn('_id', $eventUsers->pluck('account_id'))
            ->select('_id', 'picture')
            ->get()
            ->keyBy('_id');

        $bingoCards = BingoCard::whereIn('event_user_id', $eventUserIds)
            ->where('event_id', $eventID)
            ->get()
            ->keyBy('event_user_id');

        $result = $eventUsers->map(function ($eventUser) use ($userImages, $bingoCards) {
            $userImage = $userImages->get($eventUser->account_id, null);

            $dataEventUser = [
                '_id' => $eventUser->_id,
                'properties' => [
                    'names' => $eventUser->properties['names'],
                    'email' => $eventUser->properties['email'],
                    'picture' => optional($userImage)->picture ?: 'https://www.gravatar.com/avatar/?d=mp&f=y',
                ],
                'bingo' => false,
            ];

            $bingoCard = $bingoCards->get($eventUser->_id);
            if ($bingoCard) {
                $dataEventUser['bingo'] = true;
                $dataEventUser['bingo_card'] = $bingoCard;
            }

            return $dataEventUser;
        });

        return $result;
    }


    /**
     * createBingoCardToAttendee: create bingo cards for a user in the event
     *
     * @urlParam event required  event_id
     * @urlParam eventuser required  event_user_id
     *
     */
    public function createBingoCardToAttendee($event, $attendee)
    {
        $bingoCard = UserEventService::generateBingoCardForAttendee($event, $attendee);
        return response()->json($bingoCard, 201);
    }

    /**
     * createBingoCardToAllAttendees: Create bingo cards for all attendees who do not have an assigned card
     *
     * @urlParam event required  event_id
     * @urlParam eventuser required  event_user_id
     *
     */
    public function createBingoCardToAllAttendees($event)
    {
        // traer todos los asistente y asignarle a los que no tengan carton
        $eventUsers = Attendee::where("event_id", $event)->select('_id')->get();

        foreach ($eventUsers as $eventUser) {
            !BingoCard::where([
                ['event_id', $event],
                ['event_user_id', $eventUser->_id],
            ])->exists()
                && UserEventService::generateBingoCardForAttendee($event, $eventUser->_id);
        }

        return response()->json(['message' => 'bingo cards created'], 201);
    }

    /**
     * BingoCardbyEventUser_: search of BingoCards by EventUser.
     *
     * @urlParam eventUser required  eventUser_id
     * @urlParam event required  event_id
     *
     */

    public function BingoCardbyEventUser(string $eventUser_id)
    {
        return BingoCard::where('event_user_id', $eventUser_id)->get();
    }

    /**
     * _meEvents:_ list of registered events of the logged in user.
     * @authenticated
     *
     */
    public function meEvents()
    {
        $meIntoEvents = Attendee::with("event")
            ->where(
                "account_id",
                auth()->user()->_id
            )->get()
            ->makeHidden(['activities']);

        foreach ($meIntoEvents as $meEvent) {
            $meEvent['is_active'] = $this->validateStatusActive($meEvent, $meEvent->event);
        }

        return EventUserResource::collection($meIntoEvents);
    }

    private function validateStatusActive(Attendee $attendee, Event $event)
    {
        $organizationUser = OrganizationUser::where(
            'account_id',
            $attendee->account_id,
        )->where(
            'organization_id',
            $event->organizer_id
        )->first();

        if (!$organizationUser) {
            return true;
        }

        // Unicamente los usuario que existan en la organizacion
        // y tengan active false no podran acceder al event
        if ($organizationUser->active === false) {
            return false;
        }

        // Usuario es activo en la organizacion
        return true;
    }

    /**
     * _bookEventUsers_: when an event is pay the attendees can do book without having to pay.
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     *
     * @bodyParam eventUsersIds array required Attendees list who book in an event
     */
    public function bookEventUsers(Request $request, Event $event)
    {
        try {
            $data = $request->json()->all();

            $eventUsersIds = $data['eventUsersIds'];

            $eventUsers = UserEventService::bookEventUsersToEvent($event, $eventUsersIds);

            //$response = EventUserResource::collection($eventUsers);
            /* $response->additional(['status' => $result->status, 'message' => $result->message]);
             */
            $response = ["msg" => "users booked " . count($eventUsers)];
        } catch (\Exception $e) {

            $response = response()->json((object) ["message" => $e->getMessage()], 500);
        }
        return $response;
    }

    /**
     * _notifications_ : notifications
     *
     * @urlParam evenUserId
     *
     * @param Request $request
     * @param [type] $evenUserId
     * @return void
     */
    public function notifications(Request $request, $evenUserId)
    {

        $data = $request->json()->all();
        $eventUser = Attendee::findOrFail($evenUserId);
        $eventUser->fill($data);
        $eventUser->save();

        $response = new EventUserResource($eventUser);
        $response->additional(['status' => UserEventService::UPDATED, 'message' => UserEventService::MESSAGE]);
        return $response;
    }

    /**
     * _createUserViaUrl_: tries to create a new user from provided data and then add that user to specified event
     *
     *
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     *
     * @bodyParam email email required
     * @bodyParam name  string required
     * @bodyParam other_params,... any other params  will be saved in user and eventUser
     *
     * @param Request $request HTTP request
     * @param String  $event_id to add the user to.
     *
     * @return EventUserResource
     */
    public function createUserViaUrl(Request $request, string $event_id)
    {
        //  data-route="https://api.evius.co/es/event/order/5d712f33d74d5c2aef354aa6/resend"
        //EventAttendeesController::postResendTicketToAttendee($datafromform, $event_id);

        $datafromform = $request->json()->all();
        $language = $request->input("language");
        $datafromform["language"] = $language;
        foreach ($datafromform["form_response"]['answers'] as $answer) {
            switch ($answer["field"]["id"]) {
                case "UHEADSVyhrBQ":
                case "fqVfNrgrLJEb":
                    $datafromform['names'] = $answer[$answer["type"]];

                    break;
                case "EiX4qlYKpQWl":
                case "rnlJ8qb0LrBZ":
                    $datafromform['email'] = $answer[$answer["type"]];
                    $datafromform['correo'] = $answer[$answer["type"]];
                    break;
                case "bQx4x4U4qhn6": //id esp
                case "vXMjPZAvAzex":
                    $datafromform['id'] = strval($answer[$answer["type"]]);
                    $datafromform['identificacion'] = strval($answer[$answer["type"]]);
                    break;
                case "jmqQSTlF0JR4": //pais esp
                case "H0WzcAI63WBQ":
                    $datafromform['pais'] = strval($answer[$answer["type"]]);
                    $datafromform['country'] = strval($answer[$answer["type"]]);
                    break;
                case "IHpvlVZ7J3MZ": //lugar de recogida esp
                case "qDxlVBBAZRuz":
                    $datafromform['lugarrecogida'] = strval($answer["choice"]["label"]);
                    $datafromform['departinglocation'] = strval($answer["choice"]["label"]);
                    break;
                case "nRPaTjeZABs0":
                case "tvQOBq0hlycC":
                    $datafromform['company'] = strval($answer[$answer["type"]]);
                    $datafromform['empresa'] = strval($answer[$answer["type"]]);

                    break;
                case "YZmj5yyJ5xu6":
                case "GmbrPQhNPJId":
                    $datafromform['charge'] = $answer[$answer["type"]];
                    $datafromform['cargo'] = $answer[$answer["type"]];
                    break;
            }
        }
        $datafromform['properties'] = [
            'charge' => $datafromform['charge'],
            'cargo' => $datafromform['cargo'],
            'email' => $datafromform['email'],
            'correo' => $datafromform['correo'],
            'company' => $datafromform['company'],
            'empresa' => $datafromform['empresa'],
            'nombres' => $datafromform['names'],
            'names' => $datafromform['names'],
            'displayName' => $datafromform['names'],
            'language' => $language,
            'departinglocation' => $datafromform['departinglocation'],
            'lugarrecogida' => $datafromform['lugarrecogida'],
            'pais' => $datafromform['pais'],
            'country' => $datafromform['country'],
            'id' => $datafromform['id'],
            'identificacion' => $datafromform['identificacion'],
        ];

        try {
            //las propiedades dinamicas del usuario se estan migrando de una propiedad directa
            //a estar dentro de un hijo llamado properties

            $field = Event::find($event_id);
            $user_properties = $field->user_properties;

            $userData = $datafromform;

            if (isset($datafromform['properties'])) {
                $userData = $datafromform['properties'];
            }
            $validations = [
                'email' => 'required|email',
                'other_fields' => 'sometimes',
            ];
            foreach ($user_properties as $user_property) {

                if ($user_property['mandatory'] !== true) {
                    continue;
                }

                $field = $user_property['name'];
                //$validations[$field] = 'required';
            }

            //este validador pronto se va a su clase de validacion
            $validator = Validator::make(
                $userData,
                $validations
            );

            if ($validator->fails()) {
                return response(
                    $validator->errors(),
                    422
                );
            }

            $event = Event::find($event_id);
            $result = UserEventService::importUserEvent($event, $userData, $userData);

            $response = new EventUserResource($result->data);

            $response->additional(['status' => $result->status, 'message' => $result->message]);
        } catch (\Exception $e) {

            $response = response()->json((object) ["message" => $e->getMessage()], 500);
        }
        $email = $datafromform['email'];
        //Mail::to($email)
        //    ->send(
        //        new BookingConfirmed($result->data)
        //    );
        return "ok"; //$response;
    }

    /**
     * _sendQrToUsers_: send Qr To Users.
     *
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     */
    public function sendQrToUsers(Request $request, string $event_id)
    {
        $eventUserData = $request->json()->all();
        $query = Attendee::where("event_id", $event_id)->get();

        $query = json_decode(json_encode($query), true);
        $emailsent = [];
        $i = 0;
        foreach ($query as $value) {
            $id = $value["_id"];
            $attendee = Attendee::find($id);
            //Mail::to($attendee->email)
            //    ->send(new BookingConfirmed($attendee));
            echo "<br> enviado a " . $attendee->email;
            array_push($emailsent, $attendee->email);
            $i++;
            // integrar RSVP con estas invitaciones a logearse
            // con registros
        }
        return $emailsent;
    }

    /**
     * _SubscribeUserToEventAndSendEmail_: register user to an event and send confirmation email
     *
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     *
     * @bodyParam properties.email email required email event user Example: evius@evius.co
     * @bodyParam properties.name  string required Example: Evius
     * @bodyParam properties.password  string  Example: *******
     */
    public function SubscribeUserToEventAndSendEmail(Request $request, string $event_id)
    {
        $request->validate([
            'properties.email' => 'required|email:rfc,dns',
            'properties.names' => 'required|string|max:250',
        ]);

        $freeAccess =
            $request->query('free_access') === 'true' ?
            true : false;

        $attendeeRestriction =
            $request->query('attendee_restriction') === 'true' ?
            true : false;

        $eventUserData = $request->json()->all();

        // Determinar si fué creado desde flujo free access
        $eventUserData['free_access'] = $freeAccess;

        unset($eventUserData['properties']['rol_id']); //eliminar el rol_id de la data
        $email = $eventUserData["properties"]["email"];

        $noSendMail = $request->query('no_send_mail');

        $event = Event::findOrFail($event_id);

        // Validar capacidad
        if ($attendeeRestriction) {
            $attendeeCapacity = UserEventService::validateAttendeeCapacity($event);
            if ($attendeeCapacity['is_completed']) {
                return response()->json(compact('attendeeCapacity'), 401);
            }
        }

        $eventUserData['event_id'] = $event_id;
        //Se buscan usuarios existentes con el correo que se está ingresando
        $userexists = Attendee::where("event_id", $event_id)->where("properties.email", $email)->first();

        //Se valida si ya hay un eventuser con el correo que se está ingresando
        if (empty($userexists)) {
            //Si es el primer registro de usuario al evento se toma la fecha del registro con formato 2021-01-01
            $date = \Carbon\Carbon::now()->format('Y-m-d');

            //Se llama al método que registra la cantidad de registros a un evento por día
            app('App\Http\Controllers\RegistrationMetricsController')->createByDay($date, $event_id);

            $user = Account::where("email", $email)->first();
            if (empty($user)) {
                $user = Account::create([
                    "email" => $email,
                    "names" => $eventUserData["properties"]["names"],
                    "password" => $email,
                ]);
            }
            $eventUserData['account_id'] = $user->_id;
        } else {
            return response()->json([
                "message" => "The user is already registered in the event",
            ], 409);
        }

        $image = null; //$event->picture;

        //Account rol assigned by default, this valor is constant because any user don't select their rol_id
        if (isset($eventUserData["rol_id"])) {
            EvaRol::createOrUpdateDefaultRolEventUser($event->_id, $eventUserData["rol_id"]);
            AdministratorService::notificationAdmin($eventUserData["rol_id"], $email, $event, $eventUserData["properties"]["names"], $request);
        }

        $eventUser = Attendee::create($eventUserData);
        //dd("create");

        // Generacion de cartones de bingo
        if ($event->bingo) {
            UserEventService::generateBingoCardForAttendee($event_id, $eventUser->_id);
        }

        // En caso de que el event posea document user
        // $document_user = isset($event->extra_config['document_user']) ?$event->extra_config['document_user'] : null ;
        // if (!empty($document_user)) {
        //     $limit = $document_user['quantity'];
        //     $eventUser = UserEventService::addDocumentUserToEventUserByEvent($event, $eventUser, $limit);
        // }

        if ($event_id === '64e8d6a2877b59d73c02e3d2') {
            Mail::to($email)
                ->send(
                    //string $message, Event $event, $eventUser, string $image = null, $footer = null, string $subject = null)
                    new \App\Mail\WOMConfirmation()
                );

            return $eventUser;
        }

        if (empty($noSendMail)) {
            $urlOrigin = $request->header('origin');
            Mail::to($email)
                ->queue(
                    //string $message, Event $event, $eventUser, string $image = null, $footer = null, string $subject = null)
                    new \App\Mail\InvitationMailSimple("", $event, $eventUser, $image, "", $event->name, $urlOrigin)
                );
        }

        if ($event_id == '64e8d6a2877b59d73c02e3d2') {
            $hubspot = self::hubspotRegister($request, $event_id, $event);
        }

        return $eventUser;
    }

    /**
     * _createUserAndAddtoEvent_: import  user and add it to an event.
     *
     *
     * When you import a user to an event, if the user does not exist, the user will be created and the record will be created in the event and
     * if the user exists, the user will not be updated, it will only create the record in the event.
     *
     * ![Screenshot](https://firebasestorage.googleapis.com/v0/b/eviusauth.appspot.com/o/evius%2Fdocumentation%2FcreateUserAndAddtoEvent.png?alt=media&token=ee03b215-85e6-49cc-9340-43ae3a00dd60)
     *
     * @authenticated
     * @urlParam event string required event id Example: 61ccd3551c821b765a312864
     *
     * @queryParam allow_edit_password Allow change user password even if the user already register. If you don't send this parameter the password of user registered in the system dond't change. Example: true
     *
     * @bodyParam email email required email event user Example: example@evius.co
     * @bodyParam name  string required Example: Evius
     * @bodyParam password  string if the password is not added, the password will be the user's email. Example: *******
     * @bodyParam other_params.city any other params  will be saved in eventUser
     */
    public function createUserAndAddtoEvent(Request $request, string $event_id)
    {
        $request->validate([
            'email' => 'required|email:rfc,dns',
            'names' => 'required|string|max:250',
            'password' => 'string|min:6',
            'rol_name' => 'exists:roles,name|string', // por default se asigna rol asistente
        ]);
        $overwritePassword = $request->query('overwritePassword') === 'true' ?
            true : false;

        $notifyAdmin = $request->query('notify_admin') === 'false' ?
            false : true;

        $attendeeRestriction =
            $request->query('attendee_restriction') === 'true' ?
            true : false;

        $eventUserData = $request->json()->all();

        $eventUserData["email"] = strtolower($eventUserData["email"]);
        $event = Event::findOrFail($event_id);
        $email = $eventUserData["email"];

        try {
            $eventUserExists = Attendee::where('event_id', $event_id)
                ->where('properties.email', $email)->exists();

            // Si no existe usuario se debe ejecutar validacion aforo
            if (!$eventUserExists && $attendeeRestriction) {
                $attendeeCapacity = UserEventService::validateAttendeeCapacity($event);
                if ($attendeeCapacity['is_completed']) {
                    return response()->json(compact('attendeeCapacity'), 401);
                }
            }
            // crear cuenta de usuario si no existe
            $user = Account::where("email", $email)->first();

            // Tomar como prioridad el campo password
            if (isset($eventUserData['password'])) {
                $pass = $eventUserData["password"];
            } elseif (isset($eventUserData["checkInField"])) {
                $pass = $eventUserData['checkInField'];
            } else {
                // Si no tiene password, se le asigna el email como password
                $pass = $eventUserData['email'];
            }

            if ($overwritePassword && isset($user)) {
                $auth = resolve('Kreait\Firebase\Auth');
                $this->auth = $auth;
                $this->auth->changeUserPassword($user['uid'], $pass);
            } elseif (!isset($user)) {
                $user = Account::create([
                    "email" => $email,
                    "names" => $eventUserData["names"],
                    "password" => $pass,
                ]);
            }

            // assign rol_id to attendee
            $rol_name = isset($eventUserData['rol_name']) ? $eventUserData['rol_name'] : null;
            $rol_id = UserEventService::asignRolToEventUser($rol_name, $event, $user);

            // Notificar admin
            if ($rol_name === "Administrator" && $notifyAdmin === true) {
                AdministratorService::notificationAdmin($rol_id, $email, $event, $eventUserData["names"], $request);
            }
            unset($eventUserData['rol_name']);
            unset($eventUserData["password"]);

            //Se buscan usuarios existentes con el correo que se está ingresando
            $eventUser = Attendee::updateOrCreate(
                [
                    'account_id' => $user->_id,
                    "event_id" => $event_id,
                ],
                [
                    'rol_id' => $rol_id,
                    "properties" => $eventUserData,
                ]
            );

            // Si el usuario es creado desde una actividad
            // se crea estructura para generar checking por actividad
            $activity_id = $request->query('activity_id');
            if ($activity_id) {
                UserEventService::assignFieldForCheckinByActivity($eventUser, $activity_id);
            }

            $result_status = ($eventUser->wasRecentlyCreated) ? self::CREATED : self::UPDATED;

            $result = (object) [
                "status" => $result_status,
                "data" => $eventUser,
                "message" => "OK",
            ];

            $response = new EventUserResource($eventUser);

            $additional = ['status' => $result->status, 'message' => $result->message];
            $response->additional($additional);
        } catch (\Exception $e) {
            return response()->json((object) ["message" => $e->getMessage()], 400);
        }
        return $response;
    }

    public function createUserToActivity(Request $request, Activities $activity)
    {
        $request->validate([
            'names' => 'required|string',
            'email' => 'required|email',
        ]);

        $data = $request->json()->all();

        $eventUserExists = Attendee::where('event_id', $activity->event_id)->where('properties.email', $data['email'])->first();
        if ($eventUserExists && $eventUserExists->activityProperties) {
            $userActivities = $eventUserExists->activityProperties;

            foreach ($userActivities as $userActivity) {
                if ($userActivity['activity_id'] === $activity->_id) {
                    return response()->json(['message' => 'User already exists in the activity'], 401);
                }
            }
        }

        // atributo query necesario para generar estructura checkin por actividad
        $request->query->set('activity_id', $activity->_id);
        $eventUser = self::createUserAndAddtoEvent($request, $activity->event_id);

        return $eventUser;
    }

    public function deleteUserToActivity(Activities $activity, Attendee $eventUser)
    {
        $userActivities = $eventUser->activityProperties;
        $newUserActivities = [];
        foreach ($userActivities as $userActivity) {
            $userActivity['activity_id'] !== $activity->_id &&
                array_push($newUserActivities, $userActivity);
        }

        $eventUser->activityProperties = $newUserActivities;
        $eventUser->save();

        return response()->json([], 204);
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
        $encryption = openssl_encrypt(
            $string,
            $ciphering,
            $encryption_key,
            $options,
            $encryption_iv
        );

        // Display the encrypted string
        return $encryption;
    }

    /**
     * _testCreateUserAndAddtoEvent_: test Create User And Add to Event
     *
     * @urlParam event_id string required
     *
     * @param Request $request
     * @param string $event_id
     * @return void
     */
    public function testCreateUserAndAddtoEvent(Request $request, string $event_id)
    {
        try {
            //las propiedades dinamicas del usuario se estan migrando de una propiedad directa
            //a estar dentro de un hijo llamado properties
            $eventUserData = $request->json()->all();

            $field = Event::find($event_id);
            $user_properties = $field->user_properties;

            $userData = $request->json()->all();

            if (isset($eventUserData['properties'])) {
                $userData = $eventUserData['properties'];
            }
            $validations = [
                'email' => 'required|email',
                'other_fields' => 'sometimes',
            ];
            foreach ($user_properties as $user_property) {

                if ($user_property['mandatory'] !== true) {
                    continue;
                }

                $field = $user_property['name'];
                //$validations[$field] = 'required';
            }

            //este validador pronto se va a su clase de validacion
            $validator = Validator::make(
                $userData,
                $validations
            );

            if ($validator->fails()) {
                return response(
                    $validator->errors(),
                    422
                );
            }

            $event = Event::find($event_id);
            $result = UserEventService::importUserEvent($event, $eventUserData, $userData);

            $response = new EventUserResource($result->data);

            if (!empty($eventUserData["rol_id"])) {
                $rol = $response["user"]["rol_id"];
                $response->rol()->attach($rol);
            }

            $response->additional(['status' => $result->status, 'message' => $result->message]);
        } catch (\Exception $e) {

            $response = response()->json((object) ["message" => $e->getMessage()], 500);
        }
        //return $response;
    }

    /**
     * _indexByEventUser_: list of events by logged in user
     *
     * @param Request $request
     * @return void
     */
    public function indexByEventUser(Request $request)
    {
        $events = Attendee::with("event")->where("account_id", auth()->user()->_id)->get();
        $events_id = [];
        foreach ($events as $key => $value) {
            array_push($events_id, $value["event_id"]);
        }
        return Event::find($events_id);
    }

    public function ByAccountId(Request $request, $account_id)
    {
        $events = Attendee::with("event")->where("account_id", $account_id)->get();
        $events_id = [];
        foreach ($events as $key => $value) {
            array_push($events_id, $value["event_id"]);
        }
        return Event::find($events_id);
    }
 

    /**
     * _ByUserInEvent_ : list of users by events
     *
     * @urlParam event_id string required
     *
     * @param Request $request
     * @param string $event_id
     * @return void
     */
    public function ByUserInEvent(Request $request, $event_id)
    {
        return EventUserResource::collection(
            Attendee::where("event_id", $event_id)->where("account_id", auth()->user()->_id)->paginate(config("app.page_size"))
        );
    }
    /**
     * _indexByUserInEvent_: list of users by events
     *
     * @urlParam event_id string required
     *
     * @param Request $request
     * @param string $event_id
     * @return void
     */
    public function indexByUserInEvent(Request $request, $event_id)
    {
        $user = auth()->user();
        //truco si no viene el usuario para que no se rompa.
        if (!$user) {
            return EventUserResource::collection(Attendee::where("event_id", "-1")->paginate(config("app.page_size")));
        }

        return EventUserResource::collection(
            Attendee::where("event_id", $event_id)->where(function ($query) {
                $query->where("account_id", auth()->user()->_id)
                    //Temporal fix for users that got different case in their email and thus firebase created different user
                    ->orWhere('email', '=', strtolower(auth()->user()->email));
            })->paginate(config("app.page_size"))
        );
    }

    /**
     * _searchInEvent_: search user within the event to verify if you are registered
     *
     * @urlParam event_id string required
     *
     * @param Request $request
     * @param string $event_id
     * @return void
     */
    public function searchInEvent(Request $request, $event_id)
    {
        $auth = resolve('Kreait\Firebase\Auth');

        $email = ($request->email) ? $request->email : $request->input("email");
        $password = $request->password;
        $check = !empty($email) ? Account::where("email", $email)->first() : null;

        if (!is_null($check)) {
            $user["nombres"] = ($check->properties["names"]) ? $check->properties["names"] : $check->properties["displayName"];
            $user["id"] = $check->id;
            $user["status"] = "Usuario existente en el evento";
            try {
                $user["account_response"] = $auth->getUserByEmail($email);
            } catch (Exception $e) {
                $user["account_response"] = "usuario existe en base de datos pero no tiene login a evius";
            }
            return $user;
        }
        return "Usuario no encontrado";
    }

    /**
     * _store:_ Store a newly Attendee  in storage.
     *
     * @urlParam event required event id
     * @bodyParam properties.email object other params  will be saved in user and eventUser each event can require aditional properties for registration.
     * @bodyParam properties.names object other params  will be saved in user and eventUser each event can require aditional properties for registration.
     * @bodyParam properties.others_properties object other params  will be saved in user and eventUser each event can require aditional properties for registration.
     *
     */
    public function store(Request $request)
    {
        $data = $request->json()->all();
        $event = Event::find($data["event_id"]);
        $image = $event->styles["banner_image"];
        $eventUser = Attendee::create($request->json()->all());
        $image = null;
        //url front dinamica
        $urlOrigin = $request->header('origin');

        // Quitar esta validacion
        if ($event->_id === '64623516d0f7f7b59e0f77e2') {
            return new EventUserResource($eventUser);
        }

        Mail::to($eventUser->properties["email"])
            ->queue(
                //string $message, Event $event, $eventUser, string $image = null, $footer = null, string $subject = null)
                new \App\Mail\InvitationMailAnonymous($event, $eventUser, $urlOrigin)
            );
        return new EventUserResource($eventUser);
    }

    /**
     * _show:_ consult an EventUser by assistant id
     * @authenticated
     * @urlParam event string required Example: 61ccd3551c821b765a312864
     * @urlParam eventuser string required id Attendee Example: 61ccd3551c821b765a312866
     *
     */
    public function show($event_id, $id)
    {
        $eventUser = Attendee::findOrFail($id);
        return new EventUserResource($eventUser);
    }

    /**
     * _update_:update a specific assistant
     *
     * @urlParam event string required Example: 61ccd3551c821b765a312864
     * @urlParam eventuser string required id Attendee Example: 61ccd3551c821b765a312866
     *
     * @bodyParam rol_id string rol id this is the role user into event
     * @bodyParam properties.other_properties  any other params  will be saved in user and eventUser
     *
     */
    public function update(Request $request, $event_id, $evenUserId)
    {
        $data = $request->json()->all();
        $rol = isset($data["rol_id"]) ? $data["rol_id"] : $data["properties"]["rol_id"];

        $eventUser = Attendee::findOrFail($evenUserId);
        if ($eventUser->anonymous && $rol == config('app.rol_admin')) {
            return response()->json(["message" => "No se puede asignar rol de administrador a un usuario anónimo"], 400);
        };

        $data['rol_id'] = $rol;
        $data['properties']['rol_id'] = $rol;
        // unset($data['properties']['rol_id']);

        $new_properties = isset($data['properties']) ? $data['properties'] : [];
        $old_properties = isset($eventUser->properties) ? $eventUser->properties : [];

        $properties_merge = array_merge($old_properties, $new_properties);
        $data['properties'] = $properties_merge;

        $event = Event::find($event_id);
        AdministratorService::notificationAdmin($rol, $data['properties']['email'], $event, $data['properties']['names'], $request);

        $eventUser->fill($data);
        $eventUser->save();
        return $eventUser;
    }

    /**
     * _updateWithStatus_: update With Status
     *
     * @urlParam event_id string required
     *
     * @param Request $request
     * @param [type] $evenUserId
     * @return void
     */
    public function updateWithStatus(Request $request, $evenUserId)
    {
        $data = $request->json()->all();
        $eventUser = Attendee::findOrFail($evenUserId);

        if (empty($data['properties'])) {
            $data['properties'] = $data;
        }
        $new_properties = $data['properties'];
        $old_properties = $eventUser->properties;
        $properties_merge = array_merge($old_properties, $new_properties);

        $data['properties'] = $properties_merge;
        $eventUser->fill($data);
        $eventUser->save();

        $response = new EventUserResource($eventUser);
        $response->additional(['status' => UserEventService::UPDATED, 'message' => UserEventService::MESSAGE]);
        return $response;
    }

    /**
     * _checkIn_: checks In an existent Attendee to the related event
     *
     * @urlParam eventuser string required id Attendee to checkin into the event
     *
     */
    public function checkIn(Request $request, $id)
    {
        $data = $request->json()->all();
        $eventUser = Attendee::findOrFail($id);
        //if (!isset($eventUser->checkedin_at) && ($eventUser->checkedin_at !== false)) {
        // Esta validacon ya la hace front
        $eventUser->checkIn();
        //}

        $printoutsHistory = [];
        $eventUser->printouts = $eventUser->printouts + 1;
        $eventUser->printouts_at = \Carbon\Carbon::now();

        $dataCheckIn = [
            'printouts' => $eventUser->printouts,
            'printouts_at' => $eventUser->printouts_at->format('Y-m-d H:i:s'),
        ];

        if (is_null($eventUser->printouts_history)) {

            $eventUser->printouts_history = array($dataCheckIn);
        } else {
            $array = $eventUser->printouts_history;
            array_push($array, $dataCheckIn);
            $eventUser->printouts_history = $array;
        }

        //checkin type
        if (isset($data['checkedin_type'])) {
            $eventUser->checkedin_type = $data['checkedin_type'];
        }

        $eventUser->save();

        return $eventUser;
    }

    /**
     * _checkInByActivity_: checks In an existent Attendee to the related activity
     *
     * @urlParam eventuser string required id Attendee to checkin into the event
     *
     */
    public function checkInByActivity(Request $request, $id, $activity_id)
    {
        $activity = Activities::find($activity_id);
        $data = $request->json()->all();
        if ($activity) {
            $eventUser = Attendee::findOrFail($id);

            $oldActivityProperties = $eventUser->activityProperties;
            //dd(isset($oldActivityProperties) && ($oldActivityProperties) > 0);
            if (isset($oldActivityProperties) && ($oldActivityProperties) > 0) {
                $newActivityProperties = [];
                foreach ($oldActivityProperties as $activityProperty) {
                    if ($activityProperty['activity_id'] != $activity_id) {
                        array_push($newActivityProperties, $activityProperty);
                    }
                }

                array_push($newActivityProperties, [
                    'activity_id' => $activity_id,
                    'checked_in' => true,
                    //'checkedin_at' => new \MongoDB\BSON\UTCDateTime(new DateTime("now")),
                    'checkedin_at' => gmdate("Y-m-d\TH:i:s\Z", time()),
                    'checkedin_type' => isset($data['checkedin_type']) ? $data['checkedin_type'] : null,
                ]);
                $eventUser->activityProperties = $newActivityProperties;
                $eventUser->save();
                return $eventUser;
            }
            $newActivityProperties = $oldActivityProperties ? $oldActivityProperties : [];
            array_push($newActivityProperties, [
                'activity_id' => $activity_id,
                'checked_in' => true,
                //'checkedin_at' => new \MongoDB\BSON\UTCDateTime(new DateTime("now")),
                'checkedin_at' => gmdate("Y-m-d\TH:i:s\Z", time()),
                'checkedin_type' => isset($data['checkedin_type']) ? $data['checkedin_type'] : null,
            ]);

            $eventUser->activityProperties = $newActivityProperties;
            $eventUser->save();
            return $eventUser;
        }
        return response()->json(['message' => 'Activity not found'], 404);
    }

    /**
     * _unCheckInByActivity_: checks In an existent Attendee to the related activity
     *
     * @urlParam eventuser string required id Attendee to checkin into the activity
     *
     */
    public function unCheckInByActivity(Request $request, $id, $activity_id)
    {
        $activity = Activities::find($activity_id);
        if ($activity) {
            $eventUser = Attendee::findOrFail($id);

            if ($eventUser->activityProperties) {
                $newActivityProperties = [];
                foreach ($eventUser->activityProperties as $activityProperty) {
                    if ($activityProperty['activity_id'] != $activity_id) {
                        array_push($newActivityProperties, $activityProperty);
                    }
                }
                array_push($newActivityProperties, [
                    'activity_id' => $activity_id,
                    'checked_in' => false,
                    'checkedin_at' => null,
                    'checkedin_type' => null,
                ]);
                $eventUser->activityProperties = $newActivityProperties;
                $eventUser->save();
                return $eventUser;
            }
            return response()->json(['message' => 'User has not checkin by activity'], 404);
        }
        return response()->json(['message' => 'Activity not found'], 404);
    }

    /**
     * _Uncheck_: uncheck an existing Attendee to related event
     *
     * @urlParam eventuser string required id Attendee to checkin into the event
     *
     */
    public function unCheck(String $eventUser)
    {
        // remove checked_in data
        $eventUser = Attendee::findOrFail($eventUser);
        $eventUser->checked_in = false;
        $eventUser->checkedin_at = null;
        $eventUser->checkedin_type = null;

        $eventUser->save();

        return $eventUser;
    }

    /**
     * __delete:__ remove a specific attendee from an event.
     * @authenticated
     * @urlParam event string required Example: 61ccd3551c821b765a312864
     * @urlParam eventuser string required id Attendee Example: 61ccd3333821b765a312866

     */
    public function destroy(Request $request, $eventId, $eventUserId)
    {
        $attendee = Attendee::findOrFail($eventUserId);
        Log::info("Anulando suscrpción del usuario  " . $attendee->account_id . " del evento " . $eventId);
        //check if the user has bingo cards
        $bingoCard = BingoCard::where('event_user_id', $eventUserId)->get();
        if ($bingoCard->count() > 0) {
            Log::info("El usuario tiene cartones de bingo");
            $bingoCard->each(function ($card) {
                $card->delete();
            });
        }
        Log::debug("eliminando suscripción del usuario  " . $attendee->account_id . " del evento " . $eventId);
        return (string) $attendee->delete();
    }

    /**
     * _transferEventuserAndEnrollToActivity_ : transfer Eventuser And Enroll To Activity
     *
     * @param Request $request
     * @param string $event_id
     * @param string $eventuser_id
     * @param Message $message
     * @return void
     */
    public function transferEventuserAndEnrollToActivity(Request $request, $event_id, $eventuser_id, Message $message)
    {
        //$event_user = Attendee::find($eventuser_id);

        $data = $request->json()->all();

        return $user_invited = self::SubscribeUserToEventAndSendEmail($request, $event_id, $message, $eventuser_id);

        //if (empty($user_invited->_id)){
        //    $user_invited = Attendee::where("event_id",$event_id)->where("properties.email", $data["properties"]["email"])->first();
        //}

        //$activity = new ActivityAssistantController();
        //$activity->activitieAssistant($request,$event_id);

        return $user_invited;

        return "usuario no encontrado, o sin invitaciones disponibles";
    }

    /**
     *
     */
    public function unsubscribe($event_id, $event_user_id)
    {
        $eventUser = Attendee::find($event_user_id);
        if (isset($eventUser)) {
            Log::info("Anulando suscrpción del usuario  " . $eventUser->account_id . " del evento " . $event_id);
            $eventUser->delete();
        }
        return view('ManageUser.unsubscribe');
    }

    /**
     * _totalMetricsByEvent_
     * @autenticathed
     *
     * @urlParam event_id
     *
     */
    public function totalMetricsByEvent(request $request, $event_id)
    {
        $data = $request->input();

        $attendes = Attendee::where('event_id', $event_id);

        if (isset($data['datetime_from']) && isset($data['datetime_to'])) {
            $attendes = $attendes->whereBetween(
                'created_at',
                array(
                    \Carbon\Carbon::parse($data['datetime_from']),
                    \Carbon\Carbon::parse($data['datetime_to']),
                )
            );
        }
        //1.Total de registros en el evento
        $attendesTotal = $attendes->count();

        //3.Visitias únicas totales
        $checkIn = $attendes->where('checked_in', '!=', false)->count();

        //2.Impresiones por evento
        $totalPrintouts = 0;
        $printouts = $attendes->where('printouts', '>', 0)->pluck('printouts');
        foreach ($printouts as $printout) {
            $totalPrintouts = $totalPrintouts + $printout;
        }

        return response()->json([
            'total_users' => $attendesTotal,
            'total_checkIn' => $checkIn,
            'total_printouts' => $totalPrintouts,
        ]);
    }

    /**
     *
     */
    public function hubspotRegister(Request $request, $event_id, $event)
    {
        $eventUserData = $request->json()->all();

        $client = new Client();
        $url = "https://api.hubapi.com/contacts/v1/contact/?hapikey=e4f2017c-357e-4f2f-99d1-0dd3929f61e0";

        $arr = array(
            'properties' => array(
                array(
                    'property' => 'firstname',
                    'value' => $eventUserData['properties']['names'],
                ),
                array(
                    'property' => 'email',
                    'value' => $eventUserData['properties']['email'],
                ),
                array(
                    'property' => 'lastname',
                    'value' => $eventUserData['properties']['apellidos'],
                ),
                array(
                    'property' => 'mobilephone',
                    'value' => $eventUserData['properties']['numerodetelefonomovil'],
                ),
                array(
                    'property' => 'company',
                    'value' => isset($eventUserData['properties']['nombredelaempresa']) ? $eventUserData['properties']['nombredelaempresa'] : "",
                ),
                array(
                    'property' => 'cedula_de_ciudadania_nit',
                    'value' => isset($eventUserData['properties']['nodecedula']) ? $eventUserData['properties']['nodecedula'] : "",
                ),
                array(
                    'property' => 'rol_cargo',
                    'value' => isset($eventUserData['properties']['rolcargo']) ? $eventUserData['properties']['rolcargo'] : "",
                ),
            ),
        );

        $response = null;
        try {
            $response = $client->request('POST', $url, [
                'body' => json_encode($arr),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (\Exception $e) {
        }
        return $response;
    }

    /**
     * _metricsEventByDate_: number of registered users and checked in for day according to event start and end dates  * or according specific dates.
     * @authenticated
     *
     * @urlParam event required event_id
     * @queryParam metrics_type required string With this parameter you can defined the type of metrics that you want to see, you can select created_at for see the registered users  or checkedin_at for see checked users. Example: created_at
     * @queryParam datetime_from date format dd-mm-yyyy
     * @queryParam datetime_to date format dd-mm-yyyy
     */
    public function metricsEventByDate(Request $request, $event_id)
    {

        $data = $request->input();
        $event = Event::findOrFail($event_id);

        $dateFrom = isset($data['datetime_from']) ? $data['datetime_from'] : $event->datetime_from;
        $dateTo = isset($data['datetime_to']) ? $data['datetime_to'] : $event->datetime_to;

        //Se realiza esta conversión a fecha: 2021-08-30 00:00
        $dateFrom = Carbon::parse($dateFrom)->format('Y-m-d H:i');
        $dateTo = Carbon::parse($dateTo)->format('Y-m-d H:i');

        $attendees = Attendee::where('event_id', $event_id)
            ->whereBetween(
                $data['metrics_type'],
                array(
                    //Aquí también se hace la conversión o no funciona
                    Carbon::parse($dateFrom),
                    Carbon::parse($dateTo),
                )
            )
            ->get([$data['metrics_type']]);

        // Se pueden consultar los registros y el checkIn, ambos aquí porque tienen la misam estructura de la consulta
        switch ($data['metrics_type']) {
            case "created_at";
                $attendees = $attendees->groupBy(function ($date) {
                    return \Carbon\Carbon::parse($date->created_at)->format('Y-m-d');
                });
                break;
            case "checkedin_at";
                $attendees = $attendees->groupBy(function ($date) {
                    return \Carbon\Carbon::parse($date->checkedin_at)->format('Y-m-d');
                });
                break;
        }

        //Este array forma un json con la fecha y la cantidad de registro o checkIn
        $totalForDate = [];
        foreach ($attendees as $key => $attendee) {

            $count = count($attendee);
            $response = response()->json([
                'date' => $key,
                'quantity' => $count,
            ]);

            array_push($totalForDate, $response->original);
        }

        return $totalForDate;
    }

    /**
     * _updateRolAndSendEmail_: change the rol of an user in a event especific.
     * This end point sends an email to the user to inform them of the change.
     * @authenticated
     *
     * @urlParam event required
     * @urlParam eventuser required
     *
     * @bodyParam rol_id string required
     */
    public function updateRolAndSendEmail(Request $request, $event_id, $eventUser_id)
    {
        return UpdateRolEventUserAndSendEmail::UpdateRolEventUserAndSendEmail($request, $event_id, $eventUser_id);
    }

    public function correosMocion(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'html' => 'required|string',
            'subject' => 'required|string',
        ]);

        $data = $request->json()->all();

        Mail::to($data['email'])
            ->queue(
                new \App\Mail\CorreoMocion($data['email'], $data['html'], $data['subject'])
            );

        return response()->json(['message' => 'Mail send'], 201);
    }

    /**
     * _validateAttendeeData_: Compare la información que se requiere en el evento con la información que tienen los asistentes.
     * Esto puede ocurrir cuando se solicitan datos después de que un evento se haya configurado previamente y requiere que los asistentes completen esta información.
     *
     * @urlParam event required
     * @urlParam eventuser required
     *
     */
    public function validateAttendeeData(Event $event, Attendee $eventuser)
    {
        $properties = $eventuser->properties;
        $fieldsRequired = $event->user_properties;

        foreach ($fieldsRequired as $field) {
            if ($field->visibleByAdmin) {
                continue;
            }

            $isRequired = $field['mandatory'];
            $fieldName = $field['name'];

            if ($isRequired && empty($properties[$fieldName])) {
                return response()->json(['message' => 'Attendee does not have all the necessary data'], 401);
            }
        }

        return response()->json(['message' => 'Ok'], 200);
    }
}
