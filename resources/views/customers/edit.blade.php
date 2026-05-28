@extends('layouts.admin')

@section('title', 'Edit Customer')
@section('page-title', 'Edit Customer')

@section('content')
<form action="{{ route('customers.update', $customer) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Informasi Personal</h5>

                <div class="alert alert-info">
                    <strong>Customer Code:</strong> {{ $customer->customer_code }}
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $customer->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $customer->email) }}">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $customer->phone) }}" required>
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. KTP</label>
                        <input type="text" name="id_card_number" class="form-control @error('id_card_number') is-invalid @enderror"
                               value="{{ old('id_card_number', $customer->id_card_number) }}">
                        @error('id_card_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                    <textarea name="address" class="form-control @error('address') is-invalid @enderror"
                              rows="3" required>{{ old('address', $customer->address) }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" class="form-control @error('latitude') is-invalid @enderror"
                               value="{{ old('latitude', $customer->latitude) }}">
                        @error('latitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-control @error('longitude') is-invalid @enderror"
                               value="{{ old('longitude', $customer->longitude) }}">
                        @error('longitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group mt-3">
                        <label>Lokasi di Peta</label>
                        <div id="customer-map" style="height: 400px; border-radius: 8px; overflow: hidden;"></div>
                        <small class="text-muted d-block mt-2">
                            Klik pada peta untuk mengatur lokasi customer. Latitude & Longitude akan otomatis terisi.
                        </small>
                    </div>

                </div>
            </div>

            <!-- Connection Configuration -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Konfigurasi Koneksi</h5>

                <div class="mb-3">
                    <label class="form-label">Tipe Koneksi <span class="text-danger">*</span></label>
                    <select name="connection_type" id="connection_type" class="form-select @error('connection_type') is-invalid @enderror" required>
                        <option value="">Pilih Tipe Koneksi</option>
                        <option value="pppoe_direct" {{ old('connection_type', $customer->connection_type) == 'pppoe_direct' ? 'selected' : '' }}>PPPoE Direct</option>
                        <option value="pppoe_mikrotik" {{ old('connection_type', $customer->connection_type) == 'pppoe_mikrotik' ? 'selected' : '' }}>PPPoE via Customer MikroTik</option>
                        <option value="static_ip" {{ old('connection_type', $customer->connection_type) == 'static_ip' ? 'selected' : '' }}>Static IP</option>
                        <option value="hotspot" {{ old('connection_type', $customer->connection_type) == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                        <option value="dhcp" {{ old('connection_type', $customer->connection_type) == 'dhcp' ? 'selected' : '' }}>DHCP</option>
                    </select>
                    @error('connection_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- PPPoE Config -->
                <div id="pppoe_config" class="connection-config" style="display: none;">
                    <div class="alert alert-info">
                        Konfigurasi PPPoE
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PPPoE Username</label>
                            <input type="text" name="pppoe_username" class="form-control"
                                   value="{{ old('pppoe_username', $customer->connection_config['username'] ?? $customer->customer_mikrotik_username ?? '') }}">
                            <small class="text-muted">Diambil dari Mikrotik saat import</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PPPoE Password</label>
                            <input type="text" name="pppoe_password" class="form-control"
                                   value="{{ old('pppoe_password', $customer->connection_config['password'] ?? $customer->customer_mikrotik_password ?? '') }}">
                            <small class="text-muted">Diambil dari Mikrotik saat import</small>
                        </div>
                    </div>
                </div>

                <!-- Static IP Config -->
                <div id="static_ip_config" class="connection-config" style="display: none;">
                    <div class="alert alert-info">
                        Konfigurasi Static IP
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">IP Address</label>
                            <input type="text" name="static_ip" class="form-control"
                                   value="{{ old('static_ip', $customer->connection_config['ip'] ?? '') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Subnet Mask</label>
                            <input type="text" name="static_subnet" class="form-control"
                                   value="{{ old('static_subnet', $customer->connection_config['subnet'] ?? '') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gateway</label>
                            <input type="text" name="static_gateway" class="form-control"
                                   value="{{ old('static_gateway', $customer->connection_config['gateway'] ?? '') }}">
                        </div>
                    </div>
                </div>

                <!-- Customer MikroTik Config -->
                <div id="customer_mikrotik_config" class="connection-config" style="display: none;">
                    <div class="alert alert-warning">
                        Customer menggunakan MikroTik sendiri
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IP MikroTik Customer</label>
                            <input type="text" name="customer_mikrotik_ip" class="form-control"
                                   value="{{ old('customer_mikrotik_ip', $customer->customer_mikrotik_ip) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username MikroTik</label>
                            <input type="text" name="customer_mikrotik_username" class="form-control"
                                   value="{{ old('customer_mikrotik_username', $customer->customer_mikrotik_username) }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Password MikroTik</label>
                            <input type="password" name="customer_mikrotik_password" class="form-control"
                                   value="{{ old('customer_mikrotik_password', $customer->customer_mikrotik_password) }}">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Paket <span class="text-danger">*</span></label>
                        <select name="package_id" class="form-select @error('package_id') is-invalid @enderror" required>
                            <option value="">Pilih Paket</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}" {{ old('package_id', $customer->package_id) == $package->id ? 'selected' : '' }}>
                                    {{ $package->name }} - {{ $package->getSpeedLabel() }}
                                </option>
                            @endforeach
                        </select>
                        @error('package_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Router</label>
                        <select name="router_id" class="form-select">
                            <option value="">Pilih Router</option>
                            @foreach($routers as $router)
                                <option value="{{ $router->id }}" {{ old('router_id', $customer->router_id) == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->ip_address }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Fiber/OLT Configuration -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Konfigurasi Fiber (Opsional)</h5>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">OLT</label>
                        <select name="olt_id" class="form-select">
                            <option value="">Tidak Pakai OLT</option>
                            @foreach($olts as $olt)
                                <option value="{{ $olt->id }}" {{ old('olt_id', $customer->olt_id) == $olt->id ? 'selected' : '' }}>
                                    {{ $olt->name }} ({{ $olt->getOltTypeLabel() }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">ONT Serial Number</label>
                        <input type="text" name="ont_serial_number" class="form-control"
                               value="{{ old('ont_serial_number', $customer->ont_serial_number) }}">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">PON Port</label>
                        <input type="text" name="pon_port" class="form-control"
                               value="{{ old('pon_port', $customer->pon_port) }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Status & Info -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Status & Informasi</h5>

                <div class="mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="active" {{ old('status', $customer->status) == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ old('status', $customer->status) == 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="terminated" {{ old('status', $customer->status) == 'terminated' ? 'selected' : '' }}>Terminated</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal Instalasi <span class="text-danger">*</span></label>
                    <input type="date" name="installation_date" class="form-control @error('installation_date') is-invalid @enderror"
                           value="{{ old('installation_date', $customer->installation_date?->format('Y-m-d')) }}" required>
                    @error('installation_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- CUSTOM ISOLIR DATE --}}
                <div class="card border-warning mb-3">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0">
                            <i class="bi bi-calendar-event text-warning me-2"></i>
                            <strong>Jadwal Isolir (Otomatis dari Paket)</strong>
                        </h6>
                    </div>
                    <div class="card-body">
                        @php
                            $package = $customer->package;
                            $hasCustomExpire = $package && $package->custom_expire_day;
                        @endphp
                        
                        @if($hasCustomExpire)
                            {{-- Paket punya custom_expire_day - isolir otomatis dari paket --}}
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-x text-danger"></i> Tanggal Isolir
                                    </label>
                                    <input type="date" 
                                           name="custom_isolir_date_only" 
                                           id="custom_isolir_date" 
                                           class="form-control @error('custom_isolir_date') is-invalid @enderror"
                                           value="{{ old('custom_isolir_date_only', $customer->custom_isolir_date?->format('Y-m-d')) }}"
                                           placeholder="Otomatis dari paket">
                                    @error('custom_isolir_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Otomatis: {{ $customer->next_billing_date ? $customer->next_billing_date->format('d M Y') : 'Belum di-set' }}
                                        @if($customer->custom_isolir_date && !$customer->custom_isolir_executed)
                                            <br>Field ini akan otomatis dikosongkan setelah isolir berhasil
                                        @endif
                                    </small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-clock text-danger"></i> Jam Isolir
                                    </label>
                                    <input type="time" 
                                           name="custom_isolir_time" 
                                           class="form-control"
                                           value="{{ old('custom_isolir_time', $customer->custom_isolir_date?->format('H:i')) }}"
                                           placeholder="Otomatis dari paket">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Otomatis: {{ $package->custom_expire_time ? \Carbon\Carbon::parse($package->custom_expire_time)->format('H:i') : '23:59' }}
                                    </small>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Sistem Otomatis:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Isolir date <strong>otomatis terisi</strong> sesuai paket yang dipakai</li>
                                    <li>Tanggal isolir = <strong>next_billing_date</strong> (tanggal {{ $package->custom_expire_day }})</li>
                                    <li>Jam isolir = <strong>{{ $package->custom_expire_time ? \Carbon\Carbon::parse($package->custom_expire_time)->format('H:i') : '23:59' }}</strong> (dari paket)</li>
                                    <li>Profile Mikrotik akan berubah ke <strong>PROFIL-ISOLIR</strong> saat tanggal isolir tiba</li>
                                    <li>Setelah isolir berhasil, isolir date akan <strong>di-update otomatis</strong> ke billing date berikutnya</li>
                                    <li><strong>Override manual:</strong> Isi field di atas jika ingin mengubah tanggal isolir (untuk trial period atau kontrak khusus)</li>
                                </ul>
                            </div>
                        @else
                            {{-- Paket tidak punya custom_expire_day - isolir manual --}}
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-x text-danger"></i> Tanggal Isolir
                                    </label>
                                    <input type="date" 
                                           name="custom_isolir_date_only" 
                                           id="custom_isolir_date" 
                                           class="form-control @error('custom_isolir_date') is-invalid @enderror"
                                           value="{{ old('custom_isolir_date_only', $customer->custom_isolir_date?->format('Y-m-d')) }}">
                                    @error('custom_isolir_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($customer->custom_isolir_date && !$customer->custom_isolir_executed)
                                        <small class="text-muted">Field ini akan otomatis dikosongkan setelah isolir berhasil</small>
                                    @endif
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-clock text-danger"></i> Jam Isolir
                                    </label>
                                    <input type="time" 
                                           name="custom_isolir_time" 
                                           class="form-control"
                                           value="{{ old('custom_isolir_time', $customer->custom_isolir_date?->format('H:i')) }}">
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Paket tidak punya custom_expire_day:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Isolir date harus diisi <strong>manual</strong></li>
                                    <li>Jika diisi, customer akan <strong>otomatis diisolir</strong> pada tanggal & jam yang ditentukan</li>
                                    <li>Profile Mikrotik akan berubah ke <strong>PROFIL-ISOLIR</strong></li>
                                    <li>Isolir custom <strong>tidak bergantung</strong> pada due date invoice</li>
                                    <li>Setelah isolir berhasil, field tanggal & jam akan <strong>dikosongkan otomatis</strong></li>
                                    <li>Cocok untuk: Trial period, kontrak khusus, atau perpanjangan manual</li>
                                </ul>
                            </div>
                        @endif
                        
                        @if($customer->custom_isolir_executed && !$customer->custom_isolir_date)
                            <div class="alert alert-success mt-2 mb-0">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Status:</strong> Isolir sudah dijalankan pada {{ $customer->updated_at?->format('d M Y H:i') }}
                                <br><small class="text-muted">Tanggal isolir sudah di-update otomatis ke billing date berikutnya</small>
                            </div>
                        @elseif($customer->custom_isolir_date)
                            <div class="alert alert-warning mt-2 mb-0">
                                <i class="bi bi-clock-fill me-2"></i>
                                <strong>Terjadwal:</strong> Isolir akan dijalankan otomatis pada {{ $customer->custom_isolir_date->format('d M Y H:i') }}
                                @if($hasCustomExpire && $customer->next_billing_date)
                                    <br><small class="text-muted">Sesuai dengan next_billing_date: {{ $customer->next_billing_date->format('d M Y H:i') }}</small>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Assign Teknisi</label>
                    <select name="assigned_teknisi_id" class="form-select">
                        <option value="">Tidak Ada</option>
                        @foreach($teknisis as $teknisi)
                            <option value="{{ $teknisi->id }}" {{ old('assigned_teknisi_id', $customer->assigned_teknisi_id) == $teknisi->id ? 'selected' : '' }}>
                                {{ $teknisi->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="4">{{ old('notes', $customer->notes) }}</textarea>
                </div>
            </div>

            <!-- Action Buttons - Modern Design -->
            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary btn-lg flex-grow-1 shadow-sm" id="update-customer-btn">
                    <i class="bi bi-save"></i> Update Customer
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-x-circle"></i> Batal
                </a>
            </div>
        </div>
    </div>
</form>

<!-- Delete Button (Separate form, di luar form update) -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="border-top pt-4">
            <div class="alert alert-warning border-0 shadow-sm mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Peringatan:</strong> Menghapus customer akan menghapus data dari database dan Mikrotik secara permanen.
            </div>
            <form action="{{ route('customers.destroy', $customer) }}" method="POST" id="delete-form" onsubmit="return confirmDelete()">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-lg w-100 shadow-sm" id="delete-customer-btn">
                    <i class="bi bi-trash"></i> Hapus Customer
                </button>
            </form>
        </div>
    </div>
</div>
            
<script>
    function confirmDelete() {
        const customerName = "{{ $customer->name }}";
        const customerCode = "{{ $customer->customer_code }}";
        
        return confirm(
            `⚠️ HAPUS CUSTOMER: ${customerName} (${customerCode})\n\n` +
            `Customer ini akan dihapus dari:\n` +
            `- Database sistem\n` +
            `- Mikrotik (PPPoE/Hotspot/Static IP)\n\n` +
            `Apakah Anda yakin ingin menghapus customer ini?`
        );
    }
    
    // ✅ Pastikan form update tidak ter-trigger saat klik delete
    document.addEventListener('DOMContentLoaded', function() {
        const updateForm = document.querySelector('form[action*="customers.update"]');
        const deleteForm = document.getElementById('delete-form');
        const updateBtn = document.getElementById('update-customer-btn');
        const deleteBtn = document.getElementById('delete-customer-btn');
        
        if (deleteBtn && updateForm) {
            deleteBtn.addEventListener('click', function(e) {
                // ✅ Stop event propagation untuk mencegah form update ter-trigger
                e.stopPropagation();
            });
        }
        
        if (updateBtn && deleteForm) {
            updateBtn.addEventListener('click', function(e) {
                // ✅ Pastikan hanya form update yang di-submit
                e.stopPropagation();
            });
        }
        
        // ✅ Pastikan form delete tidak ter-trigger saat submit form update
        if (updateForm) {
            updateForm.addEventListener('submit', function(e) {
                // ✅ Pastikan ini adalah form update, bukan delete
                if (e.target.id === 'delete-form') {
                    e.preventDefault();
                    return false;
                }
            });
        }
    });
</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const connectionTypeSelect = document.getElementById('connection_type');
    const pppoeConfig = document.getElementById('pppoe_config');
    const staticIpConfig = document.getElementById('static_ip_config');
    const customerMikrotikConfig = document.getElementById('customer_mikrotik_config');

    function toggleConnectionConfig() {
        const selectedType = connectionTypeSelect.value;

        pppoeConfig.style.display = 'none';
        staticIpConfig.style.display = 'none';
        customerMikrotikConfig.style.display = 'none';

        if (selectedType === 'pppoe_direct') {
            pppoeConfig.style.display = 'block';
        } else if (selectedType === 'pppoe_mikrotik') {
            pppoeConfig.style.display = 'block';
            customerMikrotikConfig.style.display = 'block';
        } else if (selectedType === 'static_ip') {
            staticIpConfig.style.display = 'block';
        }
    }

    connectionTypeSelect.addEventListener('change', toggleConnectionConfig);
    toggleConnectionConfig(); // Trigger on load
});
</script>
<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const latInput = document.querySelector('input[name="latitude"]');
    const lngInput = document.querySelector('input[name="longitude"]');

    // 🔹 Gunakan nilai dari input (kalau ada)
    const defaultLat = latInput.value ? parseFloat(latInput.value) : -7.327525;
    const defaultLng = lngInput.value ? parseFloat(lngInput.value) : 108.220742;
    const defaultZoom = latInput.value && lngInput.value ? 15 : 12;

    // 🔹 Inisialisasi peta
    const map = L.map('customer-map').setView([defaultLat, defaultLng], defaultZoom);

    // 🔹 Tambahkan layer OSM
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // 🔹 Tambahkan marker
    let marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    // 🔹 Update input saat marker digeser
    marker.on('dragend', function (e) {
        const { lat, lng } = e.target.getLatLng();
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
    });

    // 🔹 Klik peta untuk pindahkan marker
    map.on('click', function (e) {
        const { lat, lng } = e.latlng;
        marker.setLatLng(e.latlng);
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
    });
});
</script>

@endpush
