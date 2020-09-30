@component('mail::message')


{{ $title }}
<div style="text-align: center">
	<span>
		{!!$desc !!}

	</span>

</div>
@if ($response)
@component('mail::button', ['url' => $link . "&response=accepted" , 'color' => 'evius'])
Aceptar solicitud
@endcomponent

@component('mail::button', ['url' => $link . "&response=rejected" , 'color' => 'evius'])
Rechazar solicitud
@endcomponent
@endif

<br />
@component('mail::button', ['url' => $link_authenticated, 'color' => 'evius'])
Ver Solicitudes en el Evento
@endcomponent




<!-- Click aqui
@component('mail::button', ['url' => url('/api/rsvp/confirmrsvp/5bb64a02c065863d470263a8'), 'color' => 'evius'])
Confirmar Cuenta
@endcomponent -->

[PolÃ­ticas de privacidad](https://evius.co/privacy) |
[TÃ©rminos y Condiciones](https://evius.co/terms)

<div style="text-align: center">
	<span>
		Recibiste este correo porque estÃ¡s inscrito y/o invitado en un
		evento gestionado a travÃ©s de Evius.co o te has
		registrado en el portal de Evius.co
	</span>
</div>
<div style="text-align: center">
	<span>

	</span>
	<span></span>
</div>
@slot('footer')
@component('mail::footer')
Â© 2001-2020. All Rights Reserved - Evius.co
@endcomponent
@endslot
@endcomponent