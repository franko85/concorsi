@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Ricerca Concorsi</h1>
    <form method="get" action="{{ route('home') }}">
        <div class="row mb-3">
            <div class="col">
                <input type="text" name="q" class="form-control" placeholder="Titolo o ente" value="{{ $q }}">
            </div>
            <div class="col">
                <select name="regione" class="form-control">
                    <option value="">Tutte le regioni</option>
                    @foreach($regioni as $r)
                        <option value="{{ $r }}" @if($regione == $r) selected @endif>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <select name="categoria" class="form-control">
                    <option value="">Tutte le categorie</option>
                    @foreach($categorie as $c)
                        <option value="{{ $c }}" @if($categoria == $c) selected @endif>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col">
                <label>Requisiti:</label>
                <select name="requisiti[]" class="form-control" multiple>
                    @foreach($allReq as $req)
                        <option value="{{ $req }}" @if(in_array($req, $requisiti)) selected @endif>{{ $req }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>Match All:</label>
                <input type="checkbox" name="match_all" value="1" @if($matchAll) checked @endif>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Cerca</button>
    </form>

    <hr>
    <h2>Risultati</h2>
    <ul class="list-group">
        @foreach($concorsi as $c)
            <li class="list-group-item">
                <strong>{{ $c->titolo }}</strong> - {{ $c->ente }}<br>
                Regione: {{ $c->regione }} | Categoria: {{ $c->categoria }}<br>
                Scadenza: {{ $c->scadenza }}<br>
                <a href="{{ $c->link_bando }}" target="_blank">Bando</a>
            </li>
        @endforeach
    </ul>
    <div class="mt-3">
        {{ $concorsi->links() }}
    </div>
</div>
@endsection

