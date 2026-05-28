@extends('layouts.admin')

@section('title', 'Generate User - Hotspot')

@section('content')
@include('hotspot.partials.router-selector')

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold"><i class="bi bi-ticket-perforated"></i> Generate User</h4>
        </div>
        <a href="{{ route('hotspot.users') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Users
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Error!</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Form Generate User -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-gear"></i> Generate User</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('hotspot.generate.store') }}" method="POST" id="generateUserForm">
                        @csrf

                        <!-- Action Buttons -->
                        <div class="mb-4">
                            <button type="button" class="btn btn-warning" onclick="window.location='{{ route('hotspot.users') }}'">
                                <i class="bi bi-x-circle"></i> Close
                            </button>
                            <button type="button" class="btn btn-pink" onclick="window.location='{{ route('hotspot.users') }}'">
                                <i class="bi bi-list-ul"></i> User List
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat"></i> Generate
                            </button>
                            @if(session('last_generate_comment'))
                                <a href="{{ route('hotspot.users.print-voucher', ['router' => $router->id ?? 1, 'comment' => session('last_generate_comment'), 'qr' => 'no']) }}" 
                                   class="btn btn-secondary" target="_blank">
                                    <i class="bi bi-printer"></i> Print
                                </a>
                                <a href="{{ route('hotspot.users.print-voucher', ['router' => $router->id ?? 1, 'comment' => session('last_generate_comment'), 'qr' => 'yes']) }}" 
                                   class="btn btn-danger" target="_blank">
                                    <i class="bi bi-qr-code"></i> QR
                                </a>
                                <a href="{{ route('hotspot.users.print-voucher', ['router' => $router->id ?? 1, 'comment' => session('last_generate_comment'), 'qr' => 'no', 'small' => 'yes']) }}" 
                                   class="btn btn-info" target="_blank">
                                    <i class="bi bi-receipt"></i> Small
                                </a>
                            @else
                                <button type="button" class="btn btn-secondary" disabled>
                                    <i class="bi bi-printer"></i> Print
                                </button>
                                <button type="button" class="btn btn-danger" disabled>
                                    <i class="bi bi-qr-code"></i> QR
                                </button>
                                <button type="button" class="btn btn-info" disabled>
                                    <i class="bi bi-receipt"></i> Small
                                </button>
                            @endif
                        </div>

                        <!-- Qty -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Qty <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="number" name="qty" class="form-control" value="{{ old('qty', 1) }}" min="1" max="1000" required>
                                <small class="text-muted">Number of vouchers to generate (1-1000)</small>
                            </div>
                        </div>

                        <!-- Server (from MikroTik Hotspot Servers) -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Server <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="server" class="form-select" required>
                                    <option value="all">all</option>
                                    @foreach($servers as $srv)
                                        <option value="{{ $srv['name'] }}" {{ old('server') == $srv['name'] ? 'selected' : '' }}>
                                            {{ $srv['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Hotspot Server from MikroTik</small>
                            </div>
                        </div>

                        <!-- User Mode -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">User Mode <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="user_mode" class="form-select" required>
                                    <option value="up" {{ old('user_mode') == 'up' ? 'selected' : '' }}>Username & Password</option>
                                    <option value="ueqp" {{ old('user_mode') == 'ueqp' ? 'selected' : '' }}>Username = Password</option>
                                </select>
                            </div>
                        </div>

                        <!-- Name Length -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Name Length <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="name_length" class="form-select" required>
                                    @for($i = 3; $i <= 8; $i++)
                                        <option value="{{ $i }}" {{ old('name_length', 4) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <!-- Prefix -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Prefix</label>
                            <div class="col-sm-9">
                                <input type="text" name="prefix" class="form-control" value="{{ old('prefix') }}" placeholder="Optional prefix (e.g., VCR)">
                            </div>
                        </div>

                        <!-- Character -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Character <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="character" class="form-select" required>
                                    <option value="random-abcd" {{ old('character', 'random-abcd') == 'random-abcd' ? 'selected' : '' }}>Random abcd</option>
                                    <option value="random-1234" {{ old('character') == 'random-1234' ? 'selected' : '' }}>Random 1234</option>
                                    <option value="random-ABCD" {{ old('character') == 'random-ABCD' ? 'selected' : '' }}>Random ABCD</option>
                                    <option value="random-abcd1234" {{ old('character') == 'random-abcd1234' ? 'selected' : '' }}>Random abcd1234</option>
                                    <option value="random-ABCD1234" {{ old('character') == 'random-ABCD1234' ? 'selected' : '' }}>Random ABCD1234</option>
                                </select>
                            </div>
                        </div>

                        <!-- Profile -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Profile <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="profile" class="form-select" required>
                                    <option value="default">default</option>
                                    @foreach($profiles as $profile)
                                        <option value="{{ $profile['name'] }}" {{ old('profile') == $profile['name'] ? 'selected' : '' }}>
                                            {{ $profile['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Time Limit -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Time Limit</label>
                            <div class="col-sm-9">
                                <input type="text" name="limit_uptime" class="form-control" value="{{ old('limit_uptime') }}" placeholder="Example: 1d, 12h, 4w3d">
                                <small class="text-muted">Format: [wdhm] Example: 30d = 30days, 12h = 12hours, 4w3d = 31days</small>
                            </div>
                        </div>

                        <!-- Data Limit -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Data Limit</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input type="number" name="limit_bytes_total" class="form-control" value="{{ old('limit_bytes_total') }}" placeholder="0">
                                    <select name="data_unit" class="form-select" style="max-width: 100px;">
                                        <option value="MB" {{ old('data_unit') == 'MB' ? 'selected' : '' }}>MB</option>
                                        <option value="GB" {{ old('data_unit') == 'GB' ? 'selected' : '' }}>GB</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Comment -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Comment</label>
                            <div class="col-sm-9">
                                <input type="text" name="comment" class="form-control" value="{{ old('comment') }}" placeholder="Optional note">
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Last Generate Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Last Generate</h5>
                </div>
                <div class="card-body">
                    @if(session('last_generate'))
                        @php
                            $lastGen = session('last_generate');
                        @endphp
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="fw-bold">Generate Code</td>
                                <td>{{ $lastGen['code'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Date</td>
                                <td>{{ $lastGen['date'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Profile</td>
                                <td><span class="badge bg-info">{{ $lastGen['profile'] ?? '-' }}</span></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Validity</td>
                                <td>{{ $lastGen['validity'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Time Limit</td>
                                <td>{{ $lastGen['time_limit'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Data Limit</td>
                                <td>{{ $lastGen['data_limit'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Price</td>
                                <td>{{ $lastGen['price'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Selling Price</td>
                                <td>{{ $lastGen['selling_price'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Lock User</td>
                                <td><span class="badge bg-{{ $lastGen['lock_user'] == 'Enable' ? 'success' : 'secondary' }}">{{ $lastGen['lock_user'] ?? 'Disable' }}</span></td>
                            </tr>
                        </table>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No generation history yet</p>
                        </div>
                    @endif

                    <hr class="my-3">

                    <h6 class="fw-bold text-primary">Format Time Limit.</h6>
                    <p class="small mb-3">[wdhm] Example : 30d = 30days, 12h = 12hours, 4w3d = 31days.</p>
                    
                    <h6 class="fw-bold text-primary">Add User with Time Limit.</h6>
                    <p class="small mb-0">Should Time Limit < Validity.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-pink {
        background-color: #ec4899;
        color: #fff;
        border-color: #ec4899;
    }
    
    .btn-pink:hover {
        background-color: #db2777;
        color: #fff;
        border-color: #db2777;
    }

    .table-borderless td {
        padding: 0.5rem;
    }
</style>
@endpush

@push('scripts')
<script>
    // Validate Time Limit format
    document.getElementById('generateUserForm')?.addEventListener('submit', function(e) {
        const timeLimitInput = document.querySelector('input[name="limit_uptime"]');
        const timeLimit = timeLimitInput?.value.trim();
        
        if (timeLimit && !validateTimeLimit(timeLimit)) {
            e.preventDefault();
            alert('Invalid Time Limit format! Use [wdhm] format. Example: 1d, 12h, 4w3d');
            timeLimitInput.focus();
            return false;
        }
    });

    function validateTimeLimit(value) {
        // Match format: [number][w|d|h|m]
        const regex = /^(\d+[wdhm])+$/;
        return regex.test(value);
    }

    function printVoucher() {
        alert('Please generate vouchers first before printing!');
    }

    function printQR() {
        alert('QR Print feature coming soon');
    }

    function printSmall() {
        alert('Small Print feature coming soon');
    }
</script>
@endpush

