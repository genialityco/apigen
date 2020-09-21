<?php

namespace App\Http\Controllers;

use App\Event;
use App\Activities;
use App\Attendee;
use Mail;
use Auth;
use App\ActivityAssistant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Validator;
use App\State;
use App\Account;
use Illuminate\Http\Response;
use App\evaLib\Services\FilterQuery;
use App\Http\Requests\EventUserRequest;
use App\Http\Resources\EventUserResource;
use App\evaLib\Services\UserEventService;


/**
 * @resource Event
 *
 *
 */
class ActivityAssistantController extends Controller
{


    /**
     * Display the specified resource.
     *
     * @param  \App\Inscription  $Inscription
     * @return \Illuminate\Http\Response
     */
    public function fillassitantsbug($id)
    {
       // $ActivityAssistant = ActivityAssistant::all();
       //Esta activityassistant se perdio 5dbc99bad74d5c5822693842
       $ActivityAssistant = ActivityAssistant::find($id);
       if ($ActivityAssistant)
       $ActivityAssistant->save();

        var_dump($ActivityAssistant->user_ids);
        $response = new JsonResource($ActivityAssistant);
        var_dump($response);
        die;

    }    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $event_id)
    {
        $activity_id = $request->input("activity_id");
        $user_id = $request->input("user_id");

        $query = ActivityAssistant::where("event_id", $event_id);

        //Filtro por actividad
        if ($activity_id){
            $query->where("activity_id",$activity_id);       
        }

        //Filtro por usuario
        if ($user_id){
            $query->where("user_id",$user_id);       
        }

        return JsonResource::collection($query->paginate(config('app.page_size')));
    }
    public function meIndex(Request $request, $event_id)
    {   
        $user = auth()->user();      
        return JsonResource::collection(ActivityAssistant::where("user_id", $user->_id)->paginate(config('app.page_size')));
    }
    /**<
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request, $event_id)
    {
        $data = $request->json()->all();
        //$data["user_id"] $data["activity_id"]  $data["event_id"]
        $data["event_id"] = $event_id;
        $rules = [
            'user_id' => 'required',
            'activity_id' => 'required'
        ];
        $validator = Validator::make($data, $rules);
        if (!$validator->passes()) {
            return response()->json(['errors' => $validator->errors()->all()], 400);
        }
        
        //reduceAvailability   
        $activityAssistant = new ActivityAssistant($data);   
        $activityAssistant->save();
       
        
        $user     = Account::find($data["user_id"]);
        $activity = Activities::find($data["activity_id"]);
        

        $subject = "Confirmación de regsitro ".$activity->name;
        Mail::to($user->email)
        ->queue(
            //string $message, Event $event, $eventUser, string $image = null, $footer = null, string $subject = null)
            new \App\Mail\ActivityRegistration($subject,$activityAssistant,$activity)
        );



        return $activityAssistant;
    }

    private function  reduceAvailability(){
        $activity_id      = $data["activity_id"];
        $model = ActivityAssistant::where("activity_id",$activity_id)->first();
       
        if(!is_null($model)){
            
            $user_ids = $model->user_ids;
            $activity = Activities::find($activity_id);        
            $capacity = $activity->capacity; 
            
            if(sizeof($user_ids) < $capacity){ 
                
                if(ActivityAssistant::where("user_ids",$data["user_id"])->first()){
                    return "Usuario ya se encuentra inscrito a la actividad";
                }

                $new_user = [$data["user_id"]];
                $users_merge["user_ids"] = array_merge($new_user,$user_ids);
                $model->fill($users_merge);
                $model->save();

                $activity->remaining_capacity = $capacity - sizeof($users_merge["user_ids"]);
                $activity->save(); //guarda el resultado            
                return $model;
            }else{
                return "Capacidad completada, le invitamos a visitar otras actividades";
            }
        }
    }
    
    public function deleteAssistant(Request $request, $event_id,$activity_id)
    {
        $data = $request->json()->all();
        //$activitysize = $data["acitivity_id"];

        $activityname = Activities::find($data["activity_id"])->name;
        $data["name"] = $activityname;

        $useremail = $data["user_email"];

        $activityname = str_replace(" ", "%20", $activityname);
        $data["url"] = "https://docs.google.com/forms/d/e/1FAIpQLSeKIA54wmkCOL38EZ8rUpEJWN86xqqQuHDDsYfW1_WoHwWtLg/viewform?usp=pp_url&entry.230442346=".$activityname;
        echo $data["url"];
        $data["activity_name"] = $activityname;
        Mail::send("Public.ViewEvent.Partials.SuggestedSchedule",$data , function ($message) use ($useremail,$activityname){    
            $message->to($useremail,"Asistente")
            ->subject("Encuesta de satisfacción MEC 2019","");
        });
        
        
        
        
        
        
        
       // 
       /* 
        //$users = Attendee::find();
        $data = $request->json()->all();
        
        $models = ActivityAssistant::find("5dc60295d74d5c74ff2d4af2")->user_ids;
        $modelreplace = ActivityAssistant::find("5dc60295d74d5c74ff2d4af2");
        $activitysize = Activities::find($modelreplace->activity_id)->capacity;
        
        $arrayusers = $models;
        $x = 0;
        $activitysize = $activitysize-1 ;
        foreach($arrayusers as $arrayuser){
                    
                    $useremail = Attendee::find($arrayuser)->email;
                    $firebase = $this->auth->getUserByEmail($useremail);
                    echo $firebase;
                    
                    echo "correo enviado".$useremail.$x;
                    $data["activity_name"] = Activities::find($modelreplace->activity_id)->name;
                    $firebase = ($useremail);
                    
                    /* Mail::send("Public.ViewEvent.Partials.SuggestedSchedule",$data , function ($message) use ($useremail){
                        $message->to($useremail,"Asistente")
                        ->subject("Aforo completado, te invitamos a estas actividades","");
                    }); */

                    //unset($arrayusers[$x]);
            //$x++;
        

        /*
        $modelreplace->user_ids = $arrayusers;
        $modelreplace->push();

        $actualUsers = $modelreplace["user_ids"]; //extrae los usuarios
        $actualUsers = sizeof($actualUsers); //mide el array de usuarios 
        $totalCapacity = Activities::find($modelreplace->activity_id)->capacity; // capacidad actual de la actividad 
        $remaining = $totalCapacity - $actualUsers;  //calculos                
        $remainingCapacity = Activities::find($modelreplace->activity_id); 
        $remainingCapacity->remaining_capacity = $remaining;
        $remainingCapacity->save(); //guarda el resultado   
        */
        /* LO INICIAL NO ELIMINAR PERO USAR 
        $data = $request->json()->all();
        $activity_id =$data["activity_id"];
        $user = $data["user_id"];
        $model = ActivityAssistant::where("activity_id",$activity_id)->get();
        $modelreplace = ActivityAssistant::find($model[0]["_id"]);
        $model = ActivityAssistant::find($model[0]["_id"])->user_ids;
       
        $arrayUsers = $model;
        //mapea el array para detectar el usuario que se le parezca
        foreach($arrayUsers as $arrayUser){
            if($arrayUser == $user){
                unset($arrayUsers[$x]);
            }
            $x++;
        }
        $modelreplace->user_ids = $arrayUsers;
        $modelreplace->push();*/
         /*
            * calcular cupos restantes
            */
         /*
            $actualUsers = $modelreplace["user_ids"]; //extrae los usuarios
            $actualUsers = sizeof($actualUsers); //mide el array de usuarios 
            $totalCapacity = Activities::find($activity_id)->capacity; // capacidad actual de la actividad 
            $remaining = $totalCapacity - $actualUsers;  //calculos                
            $remainingCapacity = Activities::find($activity_id); 
            $remainingCapacity->remaining_capacity = $remaining;
            $remainingCapacity->save(); //guarda el resultado   

        return $modelreplace;*/
     }

    /**
     * Display the specified resource.
     *
     * @param  \App\Inscription  $Inscription
     * @return \Illuminate\Http\Response
     */
    public function show($event_id,$id)
    {
        $ActivityAssistant = ActivityAssistant::findOrFail($id);
        $response = new JsonResource($ActivityAssistant);
        //if ($Inscription["event_id"] = $event_id) {
        return $response;

    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Inscription  $Inscription
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $event_id, $id)
    {
        $data = $request->json()->all();
        $ActivityAssistant = ActivityAssistant::findOrFail($id);
        //if($Inscription["event_id"]= $event_id){
        $ActivityAssistant->fill($data);
        $ActivityAssistant->save();
        return $data;

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $event_id, $id)
    {
        $ActivityAssistant = ActivityAssistant::findOrFail($id);
        return (string) $ActivityAssistant->delete();
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param [type] $event_id
     * @param [type] $id
     * @return void
     */
    public function checkIn(Request $request , $event_id, $id)
    {

        $ActivityAssistant = ActivityAssistant::findOrFail($id);
        $date = new \DateTime();
        $ActivityAssistant->fill(['checkedin_at' => $date]); 
        $ActivityAssistant->save();

        return $ActivityAssistant;
    }

}
