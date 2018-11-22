@component('mail::message')

Hola @if ($eventUser->user) {{$eventUser->user->name}}, @endif has sido invitado a  

{{$event->name}}
----------------

{{$message}}

@component('mail::promotion')

<<<<<<< HEAD
![Logo]({{$image}})

@component('mail::button', ['url' => url('/api/rsvp/confirmrsvp/'.$eventUser->id), 'color' => 'evius'])
=======
@component('mail::button', ['url' => 'https://api.evius.co/api/rsvp/confirmrsvp/'.$eventUser->id, 'color' => 'evius'])
>>>>>>> develop
Confirmar asistencia
@endcomponent

@endcomponent

@component('mail::panel')
{{$event->description}}
-----------------------
@endcomponent
@component('mail::table')
|                       |                                                                                        | 
| --------------------  |:--------------------------------------------------------------------------------------:| 
| **Fecha:**            | **Hora:**                                                                              | 
| {{ date('l, F j Y ', strtotime($event->datetime_from)) }} | {{date('H:s', strtotime($event->datetime_from)) }} |
|<br>                   |<br>  
@if($event->datetime_to)
| **Hasta:**            | **Hora:**                                                                              | 
| {{ date('l, F j Y ', strtotime($event->datetime_from)) }} | {{date('H:s', strtotime($event->datetime_to)) }}   | 
@endif

@endcomponent

@component('mail::panel')
Ubicación del evento  <br>

{{$event_location}}
-----------------------
@endcomponent

<<<<<<< HEAD
@component('mail::button', ['url' => url('/api/rsvp/confirmrsvp/'.$eventUser->id), 'color' => 'evius'])
=======
![Logo]({{$image}})

@component('mail::button', ['url' => 'https://api.evius.co/api/rsvp/confirmrsvp/'.$eventUser->id, 'color' => 'evius'])
>>>>>>> develop
Confirmar asistencia
@endcomponent

@component('mail::subcopy')
{{$footer}}
@endcomponent


@endcomponent



