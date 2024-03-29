<?php

namespace App\Http\Controllers;

use App\Event;
use App\Models\Ticket;
use App\Models\Currency;
use App\Models\TicketStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Log;

/*
  Attendize.com   - Event Management & Ticketing
 */

class EventStagesController extends MyBaseController
{
    /**
     * @param Request $request
     * @param $event_id
     * @return mixed
     */
    public function showStages(Request $request, $event_id)
    {
        $allowed_sorts = [
            'created_at'    => trans("Controllers.sort.created_at"),
            'title'         => trans("Controllers.sort.title"),
            'quantity_sold' => trans("Controllers.sort.quantity_sold"),
            'sales_volume'  => trans("Controllers.sort.sales_volume"),
            'sort_order'  => trans("Controllers.sort.sort_order"),
        ];
        
        // Getting get parameters.
        $q = $request->get('q', '');
        $sort_by = $request->get('sort_by');
        if (isset($allowed_sorts[$sort_by]) === false) {
            $sort_by = 'sort_order';
        }
        
        // Find event or return 404 error.
        $event = Event::findOrFail($event_id);
        if ($event === null) {
            abort(404);
        }


        
        // Get tickets for event.
        $tickets = empty($q) === false
        ? $event->tickets()->where('title', 'like', '%' . $q . '%')->orderBy($sort_by, 'asc')->paginate()
        : $event->tickets()->orderBy($sort_by, 'asc')->paginate();
        // Return view.
        return view('ManageEvent.Tickets', compact('event', 'tickets', 'sort_by', 'q', 'allowed_sorts'));
    }

    /**
     * Show the edit ticket modal
     *
     * @param $event_id
     * @param $ticket_id
     * @return mixed
     */
    public function showEditTicket($event_id, $ticket_id)
    {
        $data = [
            'event'  => Event::findOrFail($event_id),
            'ticket' => Ticket::findOrFail($ticket_id),
        ];

        return view('ManageEvent.Modals.EditTicket', $data);
    }

    /**
     * Show the create stage modal
     *
     * @param $event_id
     * @return \Illuminate\Contracts\View\View
     */
    public function showCreateStage(String $event_id)
    {
        return view('ManageEvent.Modals.CreateStage', [
            'event' => Event::findOrFail($event_id),
        ]);
    }

    /**
     * Creates a stage
     *
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postCreateStage(Request $request, $event_id)
    {
        $count = 0;
        $id = time();
        $event = Event::findOrFail($event_id);
        $event_stages = $event->event_stages;

        if ($event_stages) { 
            $count = count($event_stages);
        }

            $stages = [$count => 
            [   "title" => $request->title, 
                "start_sale_date" => $request->start_sale_date, 
                "end_sale_date" => $request->end_sale_date,
                "stage_id" => $id]
            ];
            
            if ($event_stages) { 
                $event->event_stages += $stages;
            } else {
                $event->event_stages = $stages;
            }
        
            $event->save();

            return response()->json([
                'status'      => 'success',
                'message'     => trans("Controllers.refreshing"),
                'redirectUrl' => route('showEventTickets', [
                    'event_id' => $event_id,
                ]),
            ]);
    }

    /**
     * Show the update stage modal
     *
     * @param $event_id
     * @param $event_id
     * @return mixed
     */
    public function showUpdateStage(String $event_id, String $stage_id)
    {
        $event = Event::findOrFail($event_id);
        $event_stages = $event->event_stages;
        
        foreach ($event_stages as $key => $event_stage) {
            
            if ($event_stage['stage_id'] != $stage_id) continue;
            $stage = $event_stage;
        }
        $data = [
            'event'  => $event,
            'stage' => (Object)$stage,
        ];

        return view('ManageEvent.Modals.EditStage', $data);
    }

    /**
     * Update a stage
     *
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postUpdateStage(Request $request, $event_id, $stage_id)
    {
        $event = Event::findOrFail($event_id);
        $event_stages = $event->event_stages;
        
        foreach ($event_stages as $key => $event_stage) {
            
            if ($event_stage['stage_id'] != $stage_id) continue;
            $event_stages[$key]['title'] = $request->title;
            $event_stages[$key]['start_sale_date'] = $request->start_sale_date;
            $event_stages[$key]['end_sale_date'] = $request->end_sale_date;
        }
        $event->event_stages = $event_stages;
        $event->save();

            return response()->json([
                'status'      => 'success',
                'message'     => trans("Controllers.refreshing"),
                'redirectUrl' => route('showEventTickets', [
                    'event_id' => $event_id,
                ]),
            ]);
    }

    /**
     * Pause ticket / take it off sale
     *
     * @param Request $request
     * @return mixed
     */
    public function postPauseTicket(Request $request)
    {
        $ticket_id = $request->get('ticket_id');

        $ticket = Ticket::findOrFail($ticket_id);

        $ticket->is_paused = ($ticket->is_paused == 1) ? 0 : 1;

        if ($ticket->save()) {
            return response()->json([
                'status'  => 'success',
                'message' => trans("Controllers.ticket_successfully_updated"),
                'id'      => $ticket->id,
            ]);
        }

        Log::error('Ticket Failed to pause/resume', [
            'ticket' => $ticket,
        ]);

        return response()->json([
            'status'  => 'error',
            'id'      => $ticket->id,
            'message' => trans("Controllers.whoops"),
        ]);
    }

    /**
     * Deleted a ticket
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postDeleteTicket(Request $request)
    {
        $ticket_id = $request->get('ticket_id');

        $ticket = Ticket::findOrFail($ticket_id);

        /*
         * Don't allow deletion of tickets which have been sold already.
         */
        if ($ticket->quantity_sold > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => trans("Controllers.cant_delete_ticket_when_sold"),
                'id'      => $ticket->id,
            ]);
        }

        if ($ticket->delete()) {
            return response()->json([
                'status'  => 'success',
                'message' => trans("Controllers.ticket_successfully_deleted"),
                'id'      => $ticket->id,
            ]);
        }

        Log::error('Ticket Failed to delete', [
            'ticket' => $ticket,
        ]);

        return response()->json([
            'status'  => 'error',
            'id'      => $ticket->id,
            'message' => trans("Controllers.whoops"),
        ]);
    }

    /**
     * Edit a ticket
     *
     * @param Request $request
     * @param $event_id
     * @param $ticket_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postEditTicket(Request $request, $event_id, $ticket_id)
    {
        $ticket = Ticket::findOrFail($ticket_id);
        /*
         * Add validation message
         */
        $validation_messages['quantity_available.min'] = trans("Controllers.quantity_min_error");
        $ticket->messages = $validation_messages;

        if (!$ticket->validate($request->all())) {
            return response()->json([
                'status'   => 'error',
                'messages' => $ticket->errors(),
            ]);
        } 

        $ticket->title = $request->get('title');
        $ticket->quantity_available = !$request->get('quantity_available') ? null : $request->get('quantity_available');
        $ticket->price = $request->get('price');
        $ticket->start_sale_date = $request->get('start_sale_date');
        $ticket->end_sale_date = $request->get('end_sale_date');
        $ticket->description = $request->get('description');
        $ticket->min_per_person = $request->get('min_per_person');
        $ticket->max_per_person = $request->get('max_per_person');
        $ticket->is_hidden = $request->get('is_hidden') ? 1 : 0;


        $ticket->save();

        return response()->json([
            'status'      => 'success',
            'id'          => $ticket->id,
            'message'     => trans("Controllers.refreshing"),
            'redirectUrl' => route('showEventTickets', [
                'event_id' => $event_id,
            ]),
        ]);
    }

    /**
     * Updates the sort order of tickets
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postUpdateTicketsOrder(Request $request)
    {
        $ticket_ids = $request->get('ticket_ids');
        $sort = 1;

        foreach ($ticket_ids as $ticket_id) {
            $ticket = Ticket::findOrFail($ticket_id);
            $ticket->sort_order = $sort;
            $ticket->save();
            $sort++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => trans("Controllers.ticket_order_successfully_updated"),
        ]);
    }
}
