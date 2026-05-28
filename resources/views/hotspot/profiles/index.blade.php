@extends('layouts.admin')

@section('title', 'Hotspot Profiles - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-list"></i> Hotspot Profiles
                        <span class="badge bg-primary">{{ $router->name }}</span>
                        <span class="badge bg-info">{{ count($profiles) }}</span>
                    </h3>
                </div>
                <div>
                    <a href="{{ route('hotspot.profiles.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Profile
                    </a>
                    <a href="{{ route('hotspot.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Profiles Cards -->
    <div class="row">
        @php
            $profilesToShow = isset($profilesWithDetails) && !empty($profilesWithDetails) ? $profilesWithDetails : $profiles;
        @endphp
        @forelse($profilesToShow as $profile)
            <div class="col-md-4 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-certificate"></i> {{ $profile['name'] }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="50%"><strong>Rate Limit:</strong></td>
                                <td>
                                    @if(!empty($profile['rate_limit']))
                                        <span class="badge bg-success">{{ $profile['rate_limit'] }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Session Timeout:</strong></td>
                                <td>
                                    @if(!empty($profile['session_timeout']))
                                        {{ $profile['session_timeout'] }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Idle Timeout:</strong></td>
                                <td>
                                    @if(!empty($profile['idle_timeout']))
                                        {{ $profile['idle_timeout'] }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Shared Users:</strong></td>
                                <td>
                                    @if(!empty($profile['shared_users']))
                                        {{ $profile['shared_users'] }}
                                    @else
                                        <span class="text-muted">Unlimited</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Address Pool:</strong></td>
                                <td>
                                    @if(!empty($profile['address_pool']))
                                        {{ $profile['address_pool'] }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Keepalive Timeout:</strong></td>
                                <td>
                                    @if(!empty($profile['keepalive_timeout']))
                                        {{ $profile['keepalive_timeout'] }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Status Autorefresh:</strong></td>
                                <td>
                                    @if(!empty($profile['status_autorefresh']))
                                        {{ $profile['status_autorefresh'] }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-tag"></i> ID: {{ $profile['id'] }}
                            </small>
                            <div>
                                <a href="{{ route('hotspot.profiles.edit', $profile['id']) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteProfile('{{ $profile['id'] }}', '{{ $profile['name'] }}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-md-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i>
                    Tidak ada profile hotspot ditemukan.
                </div>
            </div>
        @endforelse
    </div>

    <!-- Info Box -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <h5><i class="fas fa-info-circle"></i> Tentang Hotspot Profiles</h5>
                    <p class="mb-0">
                        Profile hotspot menentukan parameter koneksi untuk user, termasuk bandwidth limit (rate limit), 
                        timeout session, dan address pool. Profile ini dikelola di MikroTik RouterOS dan digunakan 
                        saat membuat user hotspot baru.
                    </p>
                    <hr>
                    <p class="mb-0 small">
                        <strong>Rate Limit Format:</strong> upload/download (contoh: 512k/1M = upload 512kbps, download 1Mbps)<br>
                        <strong>Timeout Format:</strong> Waktu dalam detik atau format waktu (contoh: 1h, 1d, 1w)
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .bg-gradient-primary {
        background: linear-gradient(87deg, #5e72e4 0, #825ee4 100%) !important;
    }
    .border-left-info {
        border-left: 4px solid #36b9cc !important;
    }
</style>
@endpush

@push('scripts')
<script>
function deleteProfile(id, name) {
    if (confirm('Apakah Anda yakin ingin menghapus profile "' + name + '"?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("hotspot.profiles.delete", ":id") }}'.replace(':id', id);
        
        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        
        var method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';
        form.appendChild(method);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endpush

