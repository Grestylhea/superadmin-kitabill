@extends('layouts.admin')

@section('title', 'Pilih Router - Hotspot Management')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-wifi"></i> Hotspot Management
                    </h4>
                </div>
                <div class="card-body">
                    @if(session('warning'))
                        <div class="alert alert-warning">
                            {{ session('warning') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <h5 class="mb-4">Pilih MikroTik Router:</h5>

                    @if($routers->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Tidak ada router yang tersedia. Silakan tambahkan router terlebih dahulu.
                        </div>
                        <a href="{{ route('routers.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Tambah Router
                        </a>
                    @else
                        <form action="{{ route('hotspot.set-session') }}" method="POST">
                            @csrf
                            <div class="list-group mb-4">
                                @foreach($routers as $router)
                                    <label class="list-group-item list-group-item-action cursor-pointer">
                                        <div class="d-flex align-items-center">
                                            <input type="radio" name="router_id" value="{{ $router->id }}" required class="me-3">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $router->name }}</h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-network-wired"></i> {{ $router->ip_address }}
                                                    @if($router->location)
                                                        | <i class="fas fa-map-marker-alt"></i> {{ $router->location }}
                                                    @endif
                                                </small>
                                            </div>
                                            <span class="badge bg-success">Online</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right"></i> Connect ke Router
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .cursor-pointer {
        cursor: pointer;
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
</style>
@endpush

