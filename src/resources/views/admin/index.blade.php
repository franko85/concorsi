@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Gestione Concorsi (Admin)</h1>
    <ul class="list-group">
        @foreach($concorsi as $c)
            <li class="list-group-item">
                <strong>{{ $c->titolo }}</strong> - {{ $c->ente }}<br>
                Regione: {{ $c->regione }} | Categoria: {{ $c->categoria }}<br>
                Scadenza: {{ $c->scadenza }}<br>
                <a href="{{ $c->link_bando }}" target="_blank">Bando</a>
                <!-- Qui puoi aggiungere pulsanti modifica/elimina -->
            </li>
        @endforeach
    </ul>
</div>
@endsection

