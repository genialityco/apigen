<?php

namespace App\Http\Controllers;

use App\Boleteria;
use App\Event;
use App\TicketCategory;
use Illuminate\Http\Request;
use App\Http\Resources\TicketCategoryResource;
use App\Models\Ticket;
use Log;
use App\Http\Controllers\BoleteriaController;

/**
 * @group TicketCategory
 *
 */
class TicketCategoryController extends Controller
{
    /**
     * Validate tickets capacity on ticket Category.
     *
     * 1.El aforo debe ser igual o mayor a la cantidad
     * de tickets que ya esten vendidos para esa categoria.
     *
     * 2.El aforo no puede ser mayor a la cantidad de tickets
     * disponibles a asignar de la boleteria.
     */
    public static function _validateTicketCapacityCategory($ticketCategory, $ticketCapacity)
    {
	$isValid  = ['correct' => true];

	// 1
	$allTicketsSold =  Ticket::where('ticket_category_id', $ticketCategory->_id)->count();
	if($ticketCapacity < $allTicketsSold) {
	    $isValid['correct']  = false;
	    $isValid['message'] = 'Invalid ticket_capacity: Must be greater than or equal to the number of total tickets sold';
	}

	// 2
	$event = Event::findOrFail($ticketCategory->event_id);
	$boleteria = BoleteriaController::index($event);
	if($ticketCapacity > $boleteria->available_tickets){
	    $isValid['correct']  = false;
	    $isValid['message'] = 'Invalid ticket_capacity: Must be less than or equal to the available capacity of the box office';
	}

	return $isValid;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Boleteria $boleteria)
    {
        return TicketCategoryResource::collection(
            TicketCategory::where('boleteria_id', $boleteria->_id)
                ->latest()
                ->paginate(config('app.page_size'))
        );
    }

    /**
     * _store_: Create new TicketCategory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * @bodyParam name string required name to ticketCategory Example: Category one
     * @bodyParam color string required color of ticketCategory Example: #FFFFFF
     * @bodyParam price double required price related to ticketCategory Example: 25.000
     * @bodyParam amount numeric required amount related to ticketCategory Example: 20
     * @bodyParam event_id string required event id related to ticketCategory Example: 628fdc698b89a10b9d464793
     */
    public function store(Request $request, Boleteria $boleteria)
    {
        $request->validate([
            'name' => 'required|string',
            'color' => 'required|string',
            'price' => 'required|numeric',
            'ticket_capacity' => 'required|numeric',
        ]);

        $data = $request->json()->all();
	$data = array_merge($data, [
	    'boleteria_id' => $boleteria->_id,
	    'event_id' => $boleteria->event_id
	]);
        $ticketCategory = TicketCategory::create($data);

        return response()->json(compact('ticketCategory'), 201);
    }

    /**
     * TicketCategorybyUser_: search of ticketCategories by event.
     * 
     * @urlParam event required  event_id
     *
     */
    //public function TicketCategorybyEvent(string $event_id)
    //{
        //return TicketCategoryResource::collection(
            //TicketCategory::where('event_id', $event_id)
                //->latest()
                //->paginate(config('app.page_size'))
        //);
    //}

    /**
     * _show_: display information about a specific ticketCategory.
     *
     * @authenticated
     * @urlParam ticketCategory required id of the ticketCategory you want to consult. Example: 6298cb08f8d427d2570e8382
     * @response{
     *   "_id": "6298cb08f8d427d2570e8382",
	 *   "name": "Test",
	 *   "color": "$FFFFFF",
	 *   "price": 25.000,
	 *   "amount": 20,
	 *   "event_id": "628fdc698b89a10b9d464793",
	 *   "updated_at": "2022-06-02 14:39:27",
	 *   "created_at": "2022-06-02 14:36:56"
     * }
     */
    public function show($boleteria, TicketCategory$ticketCategory)
    {
	return compact('ticketCategory');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TicketCategory  $ticketCategory
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $boleteria_id, TicketCategory $ticketCategory)
    {
	$data = $request->json()->all();

	// Validar cantidad de aforo
	if($data[ 'ticket_capacity' ]) {
	    $isValid = self::_validateTicketCapacityCategory($ticketCategory, $data['ticket_capacity']);
	    if(!$isValid['correct']) {
		return response()->json([
		    'message' => $isValid[ 'message' ]
		], 400);
	    }
	}

	$ticketCategory->fill($data);
	$ticketCategory->save();

	return compact('ticketCategory');
    }

    /**
     * _destroy_: delete ticketCategory and related data.
     * @authenticated
     * @urlParam ticketCategory required id of the ticketCategory to be eliminated
     * 
     */
    public function destroy($boleteria_id, TicketCategory $ticketCategory)
    {
        $ticketCategory->delete();

        return response()->json([], 204);
    }
}
