@extends('layouts.admin')

@section('title', 'Hotspot Users - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-users"></i> Hotspot Users
                        <span class="badge bg-primary">{{ $router->name }}</span>
                    </h3>
                </div>
                <div>
                    <a href="{{ route('hotspot.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="{{ route('hotspot.change-router') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-exchange-alt"></i> Ganti Router
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters & Actions -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('hotspot.users') }}" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search username or comment..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="profile" class="form-select">
                                <option value="">All Profiles</option>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile }}" {{ request('profile') == $profile ? 'selected' : '' }}>
                                        {{ $profile }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="disabled" class="form-select">
                                <option value="">All Status</option>
                                <option value="false" {{ request('disabled') == 'false' ? 'selected' : '' }}>Enabled</option>
                                <option value="true" {{ request('disabled') == 'true' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-3 text-end">
                            <div class="btn-group" role="group">
                                <span class="me-2 align-self-center">Print:</span>
                                <button type="button" class="btn btn-primary btn-sm" id="btnPrintDefault" disabled>
                                    <i class="fas fa-print"></i> Default
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" id="btnPrintQR" disabled>
                                    <i class="fas fa-qrcode"></i> QR
                                </button>
                                <button type="button" class="btn btn-info btn-sm" id="btnPrintSmall" disabled>
                                    <i class="fas fa-receipt"></i> Small
                                </button>
                            </div>
                            <a href="{{ route('hotspot.users.create') }}" class="btn btn-success ms-2">
                                <i class="fas fa-plus"></i> + Tambah User
                            </a>
                            <a href="{{ route('hotspot.users', ['sync' => 1]) }}" class="btn btn-info">
                                <i class="fas fa-sync"></i> Sync
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> User List
                        <span class="badge bg-secondary">{{ $users->total() }} users</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($users->isEmpty())
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>
                            Tidak ada user hotspot. <a href="{{ route('hotspot.users.create') }}">Tambah user baru</a> atau 
                            <a href="{{ route('hotspot.users', ['sync' => 1]) }}">sync dari MikroTik</a>.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" title="Select All">
                                        </th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Profile</th>
                                        <th>Server</th>
                                        <th>Comment</th>
                                        <th>Limit Uptime</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $user)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="user-checkbox" value="{{ $user->id }}" data-username="{{ $user->username }}">
                                            </td>
                                            <td><strong>{{ $user->username }}</strong></td>
                                            <td>
                                                <span class="password-hidden">******</span>
                                                <span class="password-visible" style="display:none;">{{ $user->password }}</span>
                                                <button class="btn btn-sm btn-link toggle-password" type="button">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                            <td><span class="badge bg-info">{{ $user->profile }}</span></td>
                                            <td>{{ $user->server }}</td>
                                            <td>{{ $user->comment ?? '-' }}</td>
                                            <td>
                                                @if($user->limit_uptime)
                                                    {{ gmdate('H:i:s', $user->limit_uptime) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->price)
                                                    Rp {{ number_format($user->price, 0, ',', '.') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->disabled)
                                                    <span class="badge bg-danger">Disabled</span>
                                                @else
                                                    <span class="badge bg-success">Enabled</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('hotspot.users.print-voucher', ['router' => $router->id, 'users' => [$user->id], 'qr' => 'no']) }}" 
                                                       class="btn btn-sm btn-primary" 
                                                       target="_blank"
                                                       title="Print Voucher">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    <a href="{{ route('hotspot.users.print-voucher', ['router' => $router->id, 'users' => [$user->id], 'qr' => 'yes']) }}" 
                                                       class="btn btn-sm btn-danger" 
                                                       target="_blank"
                                                       title="Print Voucher QR">
                                                        <i class="fas fa-qrcode"></i>
                                                    </a>
                                                    <a href="{{ route('hotspot.users.edit', $user->id) }}" class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-secondary toggle-disable" 
                                                            data-user-id="{{ $user->id }}" 
                                                            data-enabled="{{ $user->disabled ? 'false' : 'true' }}"
                                                            title="{{ $user->disabled ? 'Enable' : 'Disable' }}">
                                                        <i class="fas fa-{{ $user->disabled ? 'check' : 'ban' }}"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="resetUser({{ $user->id }})"
                                                            title="Reset">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                    <form action="{{ route('hotspot.users.destroy', $user->id) }}" method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Hapus user {{ $user->username }}?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('td');
            const hiddenSpan = row.querySelector('.password-hidden');
            const visibleSpan = row.querySelector('.password-visible');
            const icon = this.querySelector('i');
            
            if (hiddenSpan.style.display === 'none') {
                hiddenSpan.style.display = 'inline';
                visibleSpan.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                hiddenSpan.style.display = 'none';
                visibleSpan.style.display = 'inline';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        });
    });

    // Select All checkbox
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updatePrintButtons();
    });

    // User checkbox change
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updatePrintButtons);
    });

    function updatePrintButtons() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        const hasSelection = checked.length > 0;
        
        document.getElementById('btnPrintDefault').disabled = !hasSelection;
        document.getElementById('btnPrintQR').disabled = !hasSelection;
        document.getElementById('btnPrintSmall').disabled = !hasSelection;
    }

    // Print buttons
    document.getElementById('btnPrintDefault')?.addEventListener('click', function() {
        printSelectedUsers('no');
    });

    document.getElementById('btnPrintQR')?.addEventListener('click', function() {
        printSelectedUsers('yes');
    });

    document.getElementById('btnPrintSmall')?.addEventListener('click', function() {
        const checked = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        const url = '{{ route("hotspot.users.print-voucher", ["router" => $router->id]) }}' + 
                    '?users[]=' + checked.join('&users[]=') + '&small=yes';
        window.open(url, '_blank');
    });

    function printSelectedUsers(qr) {
        const checked = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (checked.length === 0) {
            alert('Pilih user terlebih dahulu!');
            return;
        }
        const url = '{{ route("hotspot.users.print-voucher", ["router" => $router->id]) }}' + 
                    '?users[]=' + checked.join('&users[]=') + '&qr=' + qr;
        window.open(url, '_blank');
    }

    function resetUser(userId) {
        if (confirm('Reset user ini?')) {
            fetch('{{ route("hotspot.users.reset", ":id") }}'.replace(':id', userId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Gagal reset user');
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    }
</script>
@endpush

