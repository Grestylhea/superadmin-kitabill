@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Import PPPoE dari Mikrotik</h4>
    <form method="POST" action="{{ route('mikrotik.import.post') }}">
        @csrf
        <div class="mb-3">
            <label>Host Mikrotik</label>
            <input type="text" name="host" class="form-control" placeholder="192.168.88.1" required>
        </div>
        <div class="mb-3">
            <label>User</label>
            <input type="text" name="user" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="pass" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Port (default 8728)</label>
            <input type="number" name="port" class="form-control" value="8728">
        </div>
        <button class="btn btn-primary">Import Sekarang</button>
    </form>
</div>
@endsection
