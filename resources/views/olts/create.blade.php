@extends('layouts.admin')

@section('title', 'Add OLT')
@section('page-title', 'Add New OLT')

@section('content')
<form action="{{ route('olts.store') }}" method="POST">
    @csrf

    <div class="row">
        <div class="col-lg-8">
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">OLT Information</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">IP Address <span class="text-danger">*</span></label>
                        <input type="text" name="ip_address" class="form-control @error('ip_address') is-invalid @enderror"
                               value="{{ old('ip_address') }}" placeholder="192.168.1.1" required>
                        @error('ip_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">OLT Type <span class="text-danger">*</span></label>
                        <select name="olt_type" class="form-select @error('olt_type') is-invalid @enderror" required>
                            <option value="">-- Select Type --</option>
                            <option value="huawei" {{ old('olt_type') == 'huawei' ? 'selected' : '' }}>Huawei</option>
                            <option value="zte" {{ old('olt_type') == 'zte' ? 'selected' : '' }}>ZTE</option>
                            <option value="fiberhome" {{ old('olt_type') == 'fiberhome' ? 'selected' : '' }}>FiberHome</option>
                            <option value="bdcom" {{ old('olt_type') == 'bdcom' ? 'selected' : '' }}>BDCOM</option>
                            <option value="other" {{ old('olt_type') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('olt_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="{{ old('model') }}" placeholder="e.g., MA5608T">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Telnet Port <span class="text-danger">*</span></label>
                        <input type="number" name="telnet_port" class="form-control" value="{{ old('telnet_port', 23) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">SSH Port <span class="text-danger">*</span></label>
                        <input type="number" name="ssh_port" class="form-control" value="{{ old('ssh_port', 22) }}" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Ports <span class="text-danger">*</span></label>
                        <input type="number" name="total_ports" class="form-control" value="{{ old('total_ports', 16) }}" min="1" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control @error('username') is-invalid @enderror"
                               value="{{ old('username') }}" required>
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Location (Optional)</h5>

                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="0.00000001" name="latitude" class="form-control"
                        value="{{ old('latitude', $olt->latitude ?? '') }}"
                        placeholder="-8.67050000">
                    <small class="text-muted">Range: -90 to 90</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="0.00000001" name="longitude" class="form-control"
                        value="{{ old('longitude', $olt->longitude ?? '') }}"
                        placeholder="115.21260000">
                    <small class="text-muted">Range: -180 to 180</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Read Me / Panduan -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">
                    <i class="bi bi-info-circle text-info me-2"></i>Read Me
                </h5>
                <div class="small">
                    <div class="mb-3">
                        <h6 class="fw-bold text-primary mb-2">
                            <i class="bi bi-server me-2"></i>OLT Type
                        </h6>
                        <p class="mb-2">Pilih jenis OLT yang digunakan.</p>
                        <ul class="mb-0 ps-3">
                            <li><strong>Huawei:</strong> MA5608T, MA5600, dll</li>
                            <li><strong>ZTE:</strong> ZXA10, ZXAN, dll</li>
                            <li><strong>FiberHome:</strong> AN5516, AN6000, dll</li>
                            <li><strong>BDCOM:</strong> GPON OLT series</li>
                            <li><strong>Other:</strong> OLT lainnya</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-2">
                            <i class="bi bi-lightbulb me-2"></i>Tips Konfigurasi
                        </h6>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>Total Ports:</strong><br>
                            • Jumlah PON port yang tersedia<br>
                            • Contoh: 16, 32, 64 port<br>
                            • Untuk tracking kapasitas
                        </div>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>Telnet/SSH Port:</strong><br>
                            • Default Telnet: 23<br>
                            • Default SSH: 22<br>
                            • Pastikan port terbuka
                        </div>
                        <div class="alert alert-light border mb-0 p-2">
                            <strong>Location (GPS):</strong><br>
                            • Untuk tracking lokasi OLT<br>
                            • Bisa ditampilkan di map<br>
                            • Format: decimal degrees
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-warning mb-2">
                            <i class="bi bi-exclamation-triangle me-2"></i>Catatan Penting
                        </h6>
                        <ul class="mb-0 ps-3">
                            <li>Username & Password harus memiliki akses ke OLT</li>
                            <li>IP Address harus bisa diakses dari server</li>
                            <li>Total Ports digunakan untuk tracking kapasitas</li>
                            <li>Setelah ditambahkan, bisa assign ke customer</li>
                        </ul>
                    </div>

                    <div>
                        <h6 class="fw-bold text-info mb-2">
                            <i class="bi bi-link-45deg me-2"></i>Fungsi OLT
                        </h6>
                        <ul class="mb-0 ps-3">
                            <li>✅ Tracking ONT/ONU yang terpasang</li>
                            <li>✅ Manage PON port allocation</li>
                            <li>✅ Monitor fiber network</li>
                            <li>✅ Assign ke customer fiber</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="custom-table mb-4">
                <h6 class="fw-bold mb-3">Status</h6>
                <div class="form-check form-switch">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Save OLT
                </button>
                <a href="{{ route('olts.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </div>
</form>
@endsection
