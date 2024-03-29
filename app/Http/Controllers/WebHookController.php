<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mail;

// Models
use App\BurnedTicket;
use App\Billing;
use App\Attendee;

class WebHookController extends Controller
{
    private function generateCode()
    {
        $randomCode = substr(str_shuffle(str_repeat($x = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(5 / strlen($x)))), 1, 5);
        //verificar que el codigo no se repita
        $burnedTicket = BurnedTicket::where('code', $randomCode)->first();
        if (!empty($burnedTicket)) {
            $this->generateCode();
        }

        return $randomCode;
    }

    private function validateStatus($transaction)
    {
        $status = $transaction['status'];

        return $status === "APPROVED" ? true : false;
    }

    private function createBurnedTicket($transaction, $params, $billing)
    {
        $ticketData = [
            'billing_id' => $billing->_id,
            'user_id' => $params['user_id'],
            'code' => $this->generateCode(),
            'state' => 'ACTIVE',
            'event_id' => $params['event_id'],
            'ticket_category_id' => $params['category_id'],
            'price_usd' => intval($params['price']),
            'amount_in_cents' => $transaction['amount_in_cents'],
            'assigned_to' => [
                'name' => urldecode($params['assigned_to_name']),
                'email' => $params['assigned_to_email'],
                'country' => $params['country'] ? $params['country'] : null,
                'document' => [
                    'type_doc' => $params['assigned_to_doc_type'],
                    'value' => $params['assigned_to_doc_number']
                ]
            ]
        ];

        $burnedTicket = BurnedTicket::create($ticketData);

        // Enviar ticket
        // Si la persona que compra es la misma
        // que se le asignara el ticket solo se envia un correo
        $emails = $params['assigned_to_email'] != $transaction['customer_email'] ?
            [$params['assigned_to_email'], $transaction['customer_email']] :
            $params['assigned_to_email'];

        // Idioma en cual se enviara el correo
        $getLang = !empty($params['lang']) ? $params['lang'] : 'en';

        $lang = in_array($getLang, ['es', 'en']) ? // Idiomas permitidos
            $getLang : 'en'; // Si no es valido el Idioma entonces ingles por default

        Mail::to($emails)
            ->queue(
                new \App\Mail\BurnedTicketMail($burnedTicket, $lang)
            );
    }

    public function createEventUser($params, $billing)
    {
        // Sacar la data del usuario
        $eventUserData = [
            'billing_id' => $billing->_id,
            'account_id' => $params['user_id'],
            'event_id' => $params['event_id'],
            'properties' => [
                'names' => urldecode($params['assigned_to_names']),
                'email' => $params['assigned_to_email']
            ]
        ];

        // Crear event user
        Attendee::create($eventUserData);
    }

    public function mainHandler(Request $request)
    {
        $data = $request->json()->all();
        $transactionStatus = $this->validateStatus($data['data']['transaction']);

        if (!$transactionStatus) {
            return response()->json(['message' => 'error'], 400);
        }

        // Para boleteria quemada
        // Los datos los devuelve el webhook en redirect_url
        $url = $data['data']['transaction']['redirect_url'];

        // Obtener la cadena de consulta de la URL
        $queryString = parse_url($url, PHP_URL_QUERY);

        // Obtener query params
        parse_str($queryString, $params);

        // Crear Billing asocido al usuario
        // Este user_id es sacado de user que tiene
        // cuenta iniciada en evius cuando hace el pago
        $data['user_id'] = $params['user_id'];
        $billing = Billing::create($data);

        $burned = !empty($params['burned']) ?
            filter_var($params['burned'], FILTER_VALIDATE_BOOLEAN) : false;

        if ($burned) {
            $this->createBurnedTicket($data['data']['transaction'], $params, $billing);
        } else {
            // Solicitud Juan Lopez
            $this->createEventUser($params, $billing);
        }

        return response()->json(['message' => 'ok']);
    }
}
