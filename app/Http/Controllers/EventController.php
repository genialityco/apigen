<?php

namespace App\Http\Controllers;

use App\evaLib\Services\EvaRol;
use App\evaLib\Services\GoogleFiles;
use App\Event;
use App\Http\Resources\EventResource;
use App\Properties;
use App\User;
use App\Category;
use Illuminate\Http\Request;
use Storage;
use Validator;

/**
 * @resource Event
 *
 *
 */
class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        return EventResource::collection(
            Event::where('visibility', '<>', '') //not null
                ->orWhere('visibility', 'IS NULL', null, 'and') //null
                ->paginate(12)
            //EventUser::where("event_id", $event_id)->paginate(50)
        );

        //$events = Event::where('visibility', $request->input('name'))->get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function currentUserindex(Request $request)
    {

        $user = $request->get('user');

        return EventResource::collection(
            Event::where('author_id', $user->id)
                ->paginate(config('app.page_size'))
        );

    }

    public function delete(Event $id)
    {
        $res = $id->delete();
        if ($res == true) {
            return 'True';
        } else {
            return 'Error';
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, GoogleFiles $gfService, EvaRol $RolService)
    {
        $user = $request->get('user');
        
        $data = $request->json()->all();

        //este validador pronto se va a su clase de validacion no pude ponerlo aún no se como se hace esta fue la manera altera que encontre
        $validator = Validator::make(
            $data, [
                'name' => 'required',
            ]
        );

        if ($validator->fails()) {
            return response(
                $validator->errors(),
                422
            );
        };

        $result = new Event($data);

        if ($request->file('picture')) {
            $result->picture = $gfService->storeFile($request->file('picture'));
        }

        $result->author()->associate($user);
        $result->save();

        if ( isset($data['category_ids'])) 
        $result->categories()->sync($data['category_ids']);

        //$RolService->createAuthorAsEventAdmin($user->id, $result->_id);

        return $result;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function show(String $id)
    {
        $event = Event::find($id);
        EventResource::withoutWrapping();
        $response = new EventResource($event);
        return $response;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function edit(Event $event)
    {
        //
    }
    /**
     * Simply testing service providers
     *
     * @param GoogleFiles $gfService
     * @return void
     */
    public function test(GoogleFiles $gfService)
    {
        echo $gfService->doSomethingUseful();
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     * 
     * How was images upload before
     * 
     * @debug post $entityBody = file_get_contents('php://input');
     * $data['picture'] =  $gfService->storeFile($request->file('picture'));
     */
    public function update(Request $request, string $id, GoogleFiles $gfService)
    {
        $data = $request->all();
        $event = Event::find($id);

        if ( isset($data['category_ids'])) 
        $event->categories()->sync($data['category_ids']);

        $event->fill($data);    
        $event->save();
        return $event;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function destroy(Event $event)
    {
        //
    }
    /**
     * AddUserProperty: Add dynamic user property to the event
     *
     * each dynamic property must be composed of following parameters:
     *
     * * name     text
     * * required boolean - this field is not yet used  for anything
     * * type     text    - this field is not yet used for anything
     *
     * Once created user dynamic event properties could be get directly from $event->userProperties.
     * Dynamic properties are returned inside each UserEvent like regular properties
     * @param Event $event
     * @param array $properties
     * @return void
     */
    public function addUserProperty(Request $request, $event_id)
    {
        $event = Event::find($event_id);
        $property = $event->userProperties()->create($request->all());
        return $property->toArray();
    }
}
