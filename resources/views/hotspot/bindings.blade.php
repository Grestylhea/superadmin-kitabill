@extends('layouts.admin')

@section('title', 'IP Bindings - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-link"></i> IP Bindings
                        <span class="badge bg-primary">{{ $router->name }}</span>
                        <span class="badge bg-info">{{ count($bindings) }}</span>
                    </h3>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBindingModal">
                        <i class="fas fa-plus"></i> Add Binding
                    </button>
                    <a href="{{ route('hotspot.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Dashboard
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

    <!-- Bindings Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> IP-MAC Address Bindings
                    </h5>
                </div>
                <div class="card-body">
                    @if(empty($bindings))
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>
                            Tidak ada IP binding. Klik "Add Binding" untuk menambahkan.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>MAC Address</th>
                                        <th>IP Address</th>
                                        <th>To Address</th>
                                        <th>Server</th>
                                        <th>Type</th>
                                        <th>Comment</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bindings as $binding)
                                        <tr>
                                            <td><code>{{ $binding['mac_address'] ?? '-' }}</code></td>
                                            <td><strong>{{ $binding['address'] ?? '-' }}</strong></td>
                                            <td>{{ $binding['to_address'] ?? '-' }}</td>
                                            <td>{{ $binding['server'] }}</td>
                                            <td>
                                                <span class="badge bg-{{ $binding['type'] == 'bypassed' ? 'success' : ($binding['type'] == 'blocked' ? 'danger' : 'info') }}">
                                                    {{ $binding['type'] }}
                                                </span>
                                            </td>
                                            <td>{{ $binding['comment'] ?? '-' }}</td>
                                            <td>
                                                @if(isset($binding['disabled']) && $binding['disabled'])
                                                    <span class="badge bg-danger">Disabled</span>
                                                @else
                                                    <span class="badge bg-success">Enabled</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form action="{{ route('hotspot.bindings.remove', $binding['id']) }}" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Hapus binding ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <!-- Info Box -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Tentang IP Bindings</h5>
                <ul class="mb-0">
                    <li><strong>Regular:</strong> Binding normal IP-MAC address</li>
                    <li><strong>Bypassed:</strong> Device bypass hotspot authentication (langsung konek)</li>
                    <li><strong>Blocked:</strong> Device diblokir tidak bisa akses hotspot</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add Binding Modal -->
<div class="modal fade" id="addBindingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add IP Binding</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('hotspot.bindings.add') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mac_address" class="form-label">MAC Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mac_address" name="mac_address" 
                               placeholder="00:11:22:33:44:55" required>
                        <small class="text-muted">Format: XX:XX:XX:XX:XX:XX</small>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="address" name="address" 
                               placeholder="192.168.1.100" required>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="regular">Regular</option>
                            <option value="bypassed">Bypassed (Skip Authentication)</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="server" class="form-label">Server</label>
                        <input type="text" class="form-control" id="server" name="server" value="all">
                    </div>

                    <div class="mb-3">
                        <label for="comment" class="form-label">Comment</label>
                        <input type="text" class="form-control" id="comment" name="comment" 
                               placeholder="Device description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Binding
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

