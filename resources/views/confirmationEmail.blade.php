@component('mail::message')


 Hola {{$user_id}}!
 
<div style="text-align: center">
    <span>
        ¡Te damos la bienvenida a Evius.co! Antes de empezar, 
        debes confirmar tu dirección de correo electrónico. 
    </span>
</div>

@component('mail::button', ['url' => url('/api/confirmEmail/'.$user_id), 'color' => 'evius'])
Confirmar Cuenta
@endcomponent

@component('mail::subcopy')
@endcomponent
<div style="text-align: center">
    <span>
        Gracias,
        El equipo de Evius.co.
    </span>
</div>
![Evius]({{$logo}})
@slot('footer')
@component('mail::footer')
        © 2001-2018. All Rights Reserved - Evius.co
        is a registered trademark of MOCION
@endcomponent
@endslot
@endcomponent
