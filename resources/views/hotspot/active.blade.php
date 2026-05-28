@extends('layouts.admin')

@section('title', 'Active Sessions - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-user-check"></i> Active Sessions
                        <span class="badge bg-primary">{{ $router->name }}</span>
                        <span class="badge bg-success" id="active-count">{{ count($activeUsers) }}</span>
                    </h3>
                </div>
                <div>
                    <button class="btn btn-info" id="refreshBtn">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <a href="{{ route('hotspot.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Refresh Toggle -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-clock"></i> Auto Refresh: 
                            <span id="countdown" class="badge bg-info">10s</span>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                            <label class="form-check-label" for="autoRefresh">
                                Enable Auto Refresh (setiap 10 detik)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Sessions Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Real-time Active Sessions
                    </h5>
                </div>
                <div class="card-body">
                    <div id="loading" class="text-center py-3" style="display: none;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading...</p>
                    </div>

                    <div id="activeTable">
                        @if(empty($activeUsers))
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i>
                                Tidak ada user yang sedang online.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Username</th>
                                            <th>IP Address</th>
                                            <th>MAC Address</th>
                                            <th>Server</th>
                                            <th>Uptime</th>
                                            <th>Download</th>
                                            <th>Upload</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($activeUsers as $user)
                                            <tr>
                                                <td><strong>{{ $user['username'] }}</strong></td>
                                                <td>{{ $user['address'] }}</td>
                                                <td><small>{{ $user['mac_address'] }}</small></td>
                                                <td>{{ $user['server'] }}</td>
                                                <td>{{ $user['uptime'] }}</td>
                                                <td>
                                                    <small>{{ formatBytes($user['bytes_in']) }}</small>
                                                </td>
                                                <td>
                                                    <small>{{ formatBytes($user['bytes_out']) }}</small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger kick-user" 
                                                            data-session-id="{{ $user['id'] }}"
                                                            data-username="{{ $user['username'] }}">
                                                        <i class="fas fa-sign-out-alt"></i> Kick
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Total Active Users
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-total">
                        {{ count($activeUsers) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Total Download
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-download">
                        {{ formatBytes(array_sum(array_column($activeUsers, 'bytes_in'))) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Total Upload
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-upload">
                        {{ formatBytes(array_sum(array_column($activeUsers, 'bytes_out'))) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Last Update
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-lastupdate">
                        Just now
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .text-xs { font-size: 0.7rem; }
</style>
@endpush

@push('scripts')
<script>
    let autoRefreshEnabled = true;
    let refreshInterval;
    let countdownInterval;
    let secondsLeft = 10;

    // Auto refresh toggle
    document.getElementById('autoRefresh').addEventListener('change', function() {
        autoRefreshEnabled = this.checked;
        if (autoRefreshEnabled) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Manual refresh button
    document.getElementById('refreshBtn').addEventListener('click', function() {
        loadActiveUsers();
        secondsLeft = 10;
    });

    // Load active users via AJAX
    function loadActiveUsers() {
        document.getElementById('loading').style.display = 'block';
        
        fetch('{{ route("hotspot.active") }}', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            updateTable(data.active_users);
            updateStats(data.active_users);
            updateLastUpdate();
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('loading').style.display = 'none';
        });
    }

    // Update table with new data
    function updateTable(users) {
        document.getElementById('active-count').textContent = users.length;
        
        if (users.length === 0) {
            document.getElementById('activeTable').innerHTML = 
                '<div class="alert alert-info text-center"><i class="fas fa-info-circle"></i> Tidak ada user yang sedang online.</div>';
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-striped">';
        html += '<thead class="table-dark"><tr>';
        html += '<th>Username</th><th>IP Address</th><th>MAC Address</th><th>Server</th>';
        html += '<th>Uptime</th><th>Download</th><th>Upload</th><th>Action</th>';
        html += '</tr></thead><tbody>';

        users.forEach(user => {
            html += '<tr>';
            html += '<td><strong>' + user.username + '</strong></td>';
            html += '<td>' + user.address + '</td>';
            html += '<td><small>' + user.mac_address + '</small></td>';
            html += '<td>' + user.server + '</td>';
            html += '<td>' + user.uptime + '</td>';
            html += '<td><small>' + formatBytes(user.bytes_in) + '</small></td>';
            html += '<td><small>' + formatBytes(user.bytes_out) + '</small></td>';
            html += '<td><button class="btn btn-sm btn-danger kick-user" data-session-id="' + user.id + '" data-username="' + user.username + '">';
            html += '<i class="fas fa-sign-out-alt"></i> Kick</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        document.getElementById('activeTable').innerHTML = html;

        // Reattach kick event listeners
        attachKickListeners();
    }

    // Update statistics
    function updateStats(users) {
        const totalDownload = users.reduce((sum, user) => sum + parseInt(user.bytes_in || 0), 0);
        const totalUpload = users.reduce((sum, user) => sum + parseInt(user.bytes_out || 0), 0);

        document.getElementById('stat-total').textContent = users.length;
        document.getElementById('stat-download').textContent = formatBytes(totalDownload);
        document.getElementById('stat-upload').textContent = formatBytes(totalUpload);
    }

    // Update last update time
    function updateLastUpdate() {
        document.getElementById('stat-lastupdate').textContent = 'Just now';
    }

    // Format bytes
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Attach kick event listeners
    function attachKickListeners() {
        document.querySelectorAll('.kick-user').forEach(button => {
            button.addEventListener('click', function() {
                const sessionId = this.dataset.sessionId;
                const username = this.dataset.username;
                
                if (confirm('Kick user ' + username + '?')) {
                    kickUser(sessionId);
                }
            });
        });
    }

    // Kick user
    function kickUser(sessionId) {
        fetch('{{ route("hotspot.active.kick") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ session_id: sessionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadActiveUsers();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal kick user');
        });
    }

    // Start auto refresh
    function startAutoRefresh() {
        secondsLeft = 10;
        refreshInterval = setInterval(() => {
            loadActiveUsers();
            secondsLeft = 10;
        }, 10000);

        countdownInterval = setInterval(() => {
            secondsLeft--;
            document.getElementById('countdown').textContent = secondsLeft + 's';
            if (secondsLeft <= 0) {
                secondsLeft = 10;
            }
        }, 1000);
    }

    // Stop auto refresh
    function stopAutoRefresh() {
        clearInterval(refreshInterval);
        clearInterval(countdownInterval);
        document.getElementById('countdown').textContent = 'Off';
    }

    // Initialize
    attachKickListeners();
    startAutoRefresh();
</script>
@endpush

@php
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
@endphp

