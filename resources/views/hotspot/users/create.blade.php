@extends('layouts.admin')

@section('title', 'Add User - Hotspot')

@section('content')
@include('hotspot.partials.router-selector')

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold"><i class="bi bi-person-plus"></i> Add User</h4>
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
        <!-- Form Add User -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-plus"></i> Add User</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('hotspot.users.store') }}" method="POST" id="addUserForm">
                        @csrf

                        <!-- Close & Save Buttons -->
                        <div class="mb-4">
                            <button type="button" class="btn btn-warning" onclick="window.location='{{ route('hotspot.users') }}'">
                                <i class="bi bi-x-circle"></i> Close
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save
                            </button>
                        </div>

                        <!-- Server (from MikroTik Hotspot Servers) -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Server <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="server" class="form-select" required>
                                    <option value="all">all</option>
                                    @foreach($servers as $srv)
                                        <option value="{{ $srv['name'] }}">{{ $srv['name'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Hotspot Server from MikroTik</small>
                            </div>
                        </div>

                        <!-- Name (Username) -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Name <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
                                <small class="text-muted">Username for hotspot login</small>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label fw-bold">Password <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" value="{{ old('password') }}" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
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

        <!-- Read Me Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-book"></i> Read Me</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold text-primary">Format Time Limit.</h6>
                    <p class="small mb-3">[wdhm] Example : 30d = 30days, 12h = 12hours, 4w3d = 31days.</p>
                    
                    <h6 class="fw-bold text-primary">Add User with Time Limit.</h6>
                    <p class="small mb-3">Should Time Limit < Validity.</p>

                    <hr>

                    <h6 class="fw-bold text-dark">Time Format Examples:</h6>
                    <ul class="small">
                        <li><code class="text-primary">1h</code> = 1 hour</li>
                        <li><code class="text-primary">12h</code> = 12 hours</li>
                        <li><code class="text-primary">1d</code> = 1 day</li>
                        <li><code class="text-primary">7d</code> = 7 days (1 week)</li>
                        <li><code class="text-primary">30d</code> = 30 days (1 month)</li>
                        <li><code class="text-primary">1w</code> = 1 week (7 days)</li>
                        <li><code class="text-primary">4w3d</code> = 4 weeks + 3 days (31 days)</li>
                    </ul>

                    <h6 class="fw-bold text-dark mt-3">Data Limit Examples:</h6>
                    <ul class="small mb-0">
                        <li><code class="text-primary">100 MB</code> = 100 megabytes</li>
                        <li><code class="text-primary">1 GB</code> = 1 gigabyte</li>
                        <li><code class="text-primary">5 GB</code> = 5 gigabytes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Toggle password visibility
    document.getElementById('togglePassword')?.addEventListener('click', function() {
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (password.type === 'password') {
            password.type = 'text';
            eyeIcon.classList.remove('bi-eye');
            eyeIcon.classList.add('bi-eye-slash');
        } else {
            password.type = 'password';
            eyeIcon.classList.remove('bi-eye-slash');
            eyeIcon.classList.add('bi-eye');
        }
    });

    // Validate Time Limit format
    document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
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
</script>
@endpush

