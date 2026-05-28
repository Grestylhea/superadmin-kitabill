@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Hasil Import</h3>

    @if(isset($results['error']) && $results['error'])
        <div class="alert alert-danger">{{ $results['message'] ?? 'Terjadi kesalahan' }}</div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <p><strong>Tipe:</strong> {{ $results['type'] ?? '-' }}</p>
                <p><strong>Router:</strong> {{ $results['router'] ?? '-' }}</p>
                <p><strong>Imported:</strong> {{ $results['imported'] ?? 0 }}</p>
                <p><strong>Updated:</strong> {{ $results['updated'] ?? 0 }}</p>
                <p><strong>Total:</strong> {{ $results['total'] ?? 0 }}</p>
            </div>
        </div>
    @endif

    <a href="{{ route('mikrotik.import') }}" class="btn btn-secondary">Kembali</a>
</div>
@endsection
