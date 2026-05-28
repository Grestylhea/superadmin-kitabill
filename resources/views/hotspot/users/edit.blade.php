@extends('layouts.admin')

@section('title', 'Edit User Hotspot - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-edit"></i> Edit User Hotspot
                        <span class="badge bg-primary">{{ $router->name }}</span>
                    </h3>
                    <small class="text-muted">Username: <strong>{{ $user->username }}</strong></small>
                </div>
                <div>
                    <a href="{{ route('hotspot.users') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke List
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form -->
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-edit"></i> Form Edit User</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('hotspot.users.update', $user->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Username (Read-only) -->
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="{{ $user->username }}" readonly>
                            <small class="text-muted">Username tidak bisa diubah</small>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password Baru</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" value="{{ old('password', $user->password) }}">
                                <button class="btn btn-outline-secondary" type="button" id="generatePassword">
                                    <i class="fas fa-random"></i> Generate
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                        </div>

                        <!-- Profile -->
                        <div class="mb-3">
                            <label for="profile" class="form-label">Profile <span class="text-danger">*</span></label>
                            <select class="form-select @error('profile') is-invalid @enderror" 
                                    id="profile" name="profile" required>
                                <option value="">-- Pilih Profile --</option>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile['name'] }}" 
                                            {{ old('profile', $user->profile) == $profile['name'] ? 'selected' : '' }}>
                                        {{ $profile['name'] }}
                                        @if(!empty($profile['rate_limit']))
                                            ({{ $profile['rate_limit'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('profile')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Server -->
                        <div class="mb-3">
                            <label for="server" class="form-label">Server</label>
                            <select class="form-select @error('server') is-invalid @enderror" id="server" name="server">
                                <option value="all" {{ old('server', $user->server) == 'all' ? 'selected' : '' }}>all</option>
                                @foreach($servers as $server)
                                    <option value="{{ $server['name'] }}" 
                                            {{ old('server', $user->server) == $server['name'] ? 'selected' : '' }}>
                                        {{ $server['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('server')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Comment -->
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comment / Keterangan</label>
                            <input type="text" class="form-control @error('comment') is-invalid @enderror" 
                                   id="comment" name="comment" value="{{ old('comment', $user->comment) }}">
                            @error('comment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Limit Uptime -->
                        <div class="mb-3">
                            <label for="limit_uptime" class="form-label">Limit Uptime (detik)</label>
                            <input type="number" class="form-control @error('limit_uptime') is-invalid @enderror" 
                                   id="limit_uptime" name="limit_uptime" 
                                   value="{{ old('limit_uptime', $user->limit_uptime) }}">
                            @error('limit_uptime')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Waktu maksimal user bisa online (0 = unlimited)</small>
                        </div>

                        <!-- Limit Bytes Total -->
                        <div class="mb-3">
                            <label for="limit_bytes_total" class="form-label">Limit Bytes Total (bytes)</label>
                            <input type="number" class="form-control @error('limit_bytes_total') is-invalid @enderror" 
                                   id="limit_bytes_total" name="limit_bytes_total" 
                                   value="{{ old('limit_bytes_total', $user->limit_bytes_total) }}">
                            @error('limit_bytes_total')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Kuota data maksimal (0 = unlimited)</small>
                        </div>

                        <!-- Price -->
                        <div class="mb-3">
                            <label for="price" class="form-label">Harga (Rp)</label>
                            <input type="number" class="form-control @error('price') is-invalid @enderror" 
                                   id="price" name="price" value="{{ old('price', $user->price) }}">
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="disabled" name="disabled" 
                                       value="1" {{ old('disabled', $user->disabled) ? 'checked' : '' }}>
                                <label class="form-check-label" for="disabled">
                                    Disable User (user tidak bisa login)
                                </label>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Update User
                            </button>
                            <a href="{{ route('hotspot.users') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- User Info -->
        <div class="col-md-4">
            <div class="card shadow mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Info User</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>{{ $user->username }}</td>
                        </tr>
                        <tr>
                            <td><strong>Voucher Code:</strong></td>
                            <td>{{ $user->voucher_code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Batch ID:</strong></td>
                            <td>{{ $user->batch_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td>{{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Last Sync:</strong></td>
                            <td>{{ $user->synced_at ? $user->synced_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                @if($user->disabled)
                                    <span class="badge bg-danger">Disabled</span>
                                @else
                                    <span class="badge bg-success">Enabled</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Perhatian</h5>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Perubahan akan langsung disinkronkan ke MikroTik</li>
                        <li>Username tidak bisa diubah</li>
                        <li>Password kosong = tidak diubah</li>
                        <li>Disable user akan memutuskan koneksi aktif</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Generate random password
    document.getElementById('generatePassword').addEventListener('click', function() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let password = '';
        for (let i = 0; i < 8; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('password').value = password;
    });
</script>
@endpush

