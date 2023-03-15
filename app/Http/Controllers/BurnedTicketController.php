<?php

namespace App\Http\Controllers;

use App\BurnedTicket;
use App\Event;
use App\TicketCategory;
use Illuminate\Http\Request;

class BurnedTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Event $event)
    {
	// Cantidad de elementos que se quieren paginar
	$numberItems =  $request->query('numberItems') ? $request->query('numberItems') : 10;

	// Listar por usuario
	$user_id =  $request->query('user_id') ? $request->query('user_id') : false;
	if($user_id){
	    return BurnedTicket::where("event_id", $event->_id)
		->where('user_id', $user_id)
		->latest()
		->paginate($numberItems);
	}

	return BurnedTicket::where("event_id", $event->_id)->latest()->paginate($numberItems);

    }

    /**
     * Email debe ser unico para la persona que
     * se le asignara el ticket.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TicketCategory  $ticketCategory
     * @return \Illuminate\Http\Response
     */
    public function validateUserDataToTicket(Request $request, TicketCategory $ticketCategory)
    {
	// Validar que el email sea unico en esa categoria
	$data = $request->json()->all();

	// Array de email existentes
	$emailTicketsByCategory = BurnedTicket::where('ticket_category_id', $ticketCategory->_id)->pluck('assigned_to.email')->toArray();

	if(in_array($data['email'], $emailTicketsByCategory)) {
	    return response()->json(['message' => 'Email already exists'], 400);
	}

	return response()->json(['message' => 'Email valid'], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\BurnedTicket  $burnedTicket
     * @return \Illuminate\Http\Response
     */
    public function show(BurnedTicket $burnedTicket)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\BurnedTicket  $burnedTicket
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, BurnedTicket $burnedTicket)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\BurnedTicket  $burnedTicket
     * @return \Illuminate\Http\Response
     */
    public function destroy(BurnedTicket $burnedTicket)
    {
        //
    }
}