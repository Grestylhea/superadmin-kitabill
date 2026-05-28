@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Import User & Paket dari Mikrotik</h3>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('mikrotik.import.post') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Tipe Import</label><br>
            <input type="radio" name="type" value="Hotspot"> Hotspot
            <input type="radio" name="type" value="PPPOE" class="ms-3" checked> PPPoE
        </div>

        <div class="mb-3">
            <label class="form-label">Pilih Router</label>
            <select name="router" class="form-control" required>
                <option value="">-- Pilih Router --</option>
                @foreach($routers as $r)
                    <option value="{{ $r->name }}">{{ $r->name }} ({{ $r->ip_address }}:{{ $r->api_port ?? '8728' }})</option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-success">Mulai Import</button>
    </form>

    <hr>
    <h4>Sinkronisasi Status</h4>
    <form method="POST" action="{{ route('mikrotik.sync') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Router</label>
            <select name="router_id" class="form-control" required>
                @foreach($routers as $r)
                    <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->ip_address }})</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Sinkron Status Online/Offline</button>
    </form>
</div>
@endsection
