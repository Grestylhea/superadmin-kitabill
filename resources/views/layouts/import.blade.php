@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Import PPPoE Users dari Mikrotik</h2>

    @if(session('success'))
        <div class="alert alert-success mt-2">{{ session('success') }}</div>
    @endif

    <form action="{{ route('mikrotik.import.post') }}" method="POST">
        @csrf
        <div class="form-group mt-3">
            <label>Host / IP Mikrotik</label>
            <input type="text" name="host" class="form-control" placeholder="Contoh: 192.168.88.1" required>
        </div>

        <div class="form-group mt-3">
            <label>Username</label>
            <input type="text" name="user" class="form-control" value="admin" required>
        </div>

        <div class="form-group mt-3">
            <label>Password</label>
            <input type="password" name="pass" class="form-control" required>
        </div>

        <div class="form-group mt-3">
            <label>Port (Opsional)</label>
            <input type="number" name="port" class="form-control" value="8728">
        </div>

        <button type="submit" class="btn btn-primary mt-3">Import Sekarang</button>
    </form>
</div>
@endsection
