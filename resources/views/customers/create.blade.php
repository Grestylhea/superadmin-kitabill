@extends('layouts.admin')

@section('title', 'Tambah Customer')
@section('page-title', 'Tambah Customer Baru')

@section('content')
<form action="{{ route('customers.store') }}" method="POST">
    @csrf

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Informasi Personal</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. KTP</label>
                        <input type="text" name="id_card_number" class="form-control @error('id_card_number') is-invalid @enderror"
                               value="{{ old('id_card_number') }}">
                        @error('id_card_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                    <textarea name="address" class="form-control @error('address') is-invalid @enderror"
                              rows="3" required>{{ old('address') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" class="form-control @error('latitude') is-invalid @enderror"
                               value="{{ old('latitude') }}" placeholder="-8.670458">
                        <small class="text-muted">GPS Coordinates (opsional)</small>
                        @error('latitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-control @error('longitude') is-invalid @enderror"
                               value="{{ old('longitude') }}" placeholder="115.212629">
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
                        <option value="pppoe_direct" {{ old('connection_type') == 'pppoe_direct' ? 'selected' : '' }}>PPPoE Direct</option>
                        <option value="pppoe_mikrotik" {{ old('connection_type') == 'pppoe_mikrotik' ? 'selected' : '' }}>PPPoE via Customer MikroTik</option>
                        <option value="static_ip" {{ old('connection_type') == 'static_ip' ? 'selected' : '' }}>Static IP</option>
                        <option value="hotspot" {{ old('connection_type') == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                        <option value="dhcp" {{ old('connection_type') == 'dhcp' ? 'selected' : '' }}>DHCP</option>
                    </select>
                    @error('connection_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- PPPoE Direct & PPPoE Mikrotik -->
                <!-- ✅ Hidden inputs untuk memastikan field terkirim meskipun section hidden -->
                <input type="hidden" name="pppoe_username_backup" id="pppoe_username_backup" value="{{ old('customer_mikrotik_username') }}">
                <input type="hidden" name="pppoe_password_backup" id="pppoe_password_backup" value="{{ old('customer_mikrotik_password') }}">
                
                <div id="pppoe_config" class="connection-config" style="display: none;">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Konfigurasi PPPoE
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" id="pppoe_username_label">PPPoE Username <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="customer_mikrotik_username"
                                id="pppoe_username"
                                class="form-control @error('customer_mikrotik_username') is-invalid @enderror"
                                value="{{ old('customer_mikrotik_username') }}"
                            >
                            @error('customer_mikrotik_username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Akan diambil dari Mikrotik saat import, atau isi manual</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" id="pppoe_password_label">PPPoE Password <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="customer_mikrotik_password"
                                id="pppoe_password"
                                class="form-control @error('customer_mikrotik_password') is-invalid @enderror"
                                value="{{ old('customer_mikrotik_password') }}"
                            >
                            @error('customer_mikrotik_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Akan diambil dari Mikrotik saat import, atau isi manual</small>
                        </div>
                    </div>
                </div>

                <!-- Hotspot Configuration -->
                <div id="hotspot_config" class="connection-config" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="bi bi-wifi"></i> Konfigurasi Hotspot
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hotspot Username <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="customer_mikrotik_username"
                                id="hotspot_username"
                                class="form-control"
                                value="{{ old('customer_mikrotik_username') }}"
                            >
                            <small class="text-muted">Username untuk login hotspot</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hotspot Password <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="customer_mikrotik_password"
                                id="hotspot_password"
                                class="form-control"
                                value="{{ old('customer_mikrotik_password') }}"
                            >
                            <small class="text-muted">Password untuk login hotspot</small>
                        </div>
                    </div>
                </div>

                <!-- Static IP -->
                <div id="static_ip_config" class="connection-config" style="display: none;">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Konfigurasi Static IP
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="static_ip" class="form-control" value="{{ old('static_ip') }}" placeholder="192.168.1.100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Subnet Mask <span class="text-danger">*</span></label>
                            <input type="text" name="static_subnet" class="form-control" value="{{ old('static_subnet') }}" placeholder="255.255.255.0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gateway <span class="text-danger">*</span></label>
                            <input type="text" name="static_gateway" class="form-control" value="{{ old('static_gateway') }}" placeholder="192.168.1.1">
                        </div>
                    </div>
                </div>

                <!-- DHCP -->
                <div id="dhcp_config" class="connection-config" style="display: none;">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Konfigurasi DHCP (Static Binding)
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">MAC Address <span class="text-danger">*</span></label>
                            <input type="text" name="mac_address" class="form-control" value="{{ old('mac_address') }}" placeholder="AA:BB:CC:DD:EE:FF">
                            <small class="text-muted">Format: XX:XX:XX:XX:XX:XX</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="dhcp_ip" class="form-control" value="{{ old('dhcp_ip') }}" placeholder="192.168.1.100">
                        </div>
                    </div>
                </div>

                <!-- Customer MikroTik -->
                <div id="customer_mikrotik_config" class="connection-config" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="bi bi-router"></i> Customer menggunakan MikroTik sendiri
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IP MikroTik Customer</label>
                            <input type="text" name="customer_mikrotik_ip" class="form-control" value="{{ old('customer_mikrotik_ip') }}" placeholder="192.168.88.1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username MikroTik</label>
                            <input type="text" name="customer_mikrotik_username" class="form-control" value="{{ old('customer_mikrotik_username') }}" placeholder="admin">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Password MikroTik</label>
                            <input type="password" name="customer_mikrotik_password" class="form-control" value="{{ old('customer_mikrotik_password') }}">
                            <small class="text-muted">Untuk remote management (opsional)</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Paket <span class="text-danger">*</span></label>
                        <select name="package_id" id="package_id" class="form-select @error('package_id') is-invalid @enderror" required>
                            <option value="">Pilih Paket</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}" {{ old('package_id') == $package->id ? 'selected' : '' }}>
                                    {{ $package->name }} - {{ $package->getSpeedLabel() }} - {{ $package->getFormattedPrice() }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Router dan Tipe Koneksi akan terisi otomatis</small>
                        @error('package_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Router <span class="text-danger">*</span></label>
                        <select name="router_id" id="router_id" class="form-select @error('router_id') is-invalid @enderror" required>
                            <option value="">Pilih Router</option>
                            @foreach($routers as $router)
                                <option value="{{ $router->id }}" {{ old('router_id') == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->ip_address }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Akan terisi otomatis saat memilih paket</small>
                        @error('router_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="alert alert-info mt-3" id="package_router_info" style="display: none;">
                    <i class="bi bi-info-circle me-2"></i>
                    <span id="package_router_text"></span>
                </div>
            </div>

            <!-- Fiber/OLT Configuration (Optional) -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Konfigurasi Fiber (Opsional)</h5>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">OLT</label>
                        <select name="olt_id" class="form-select">
                            <option value="">Tidak Pakai OLT</option>
                            @foreach($olts as $olt)
                                <option value="{{ $olt->id }}" {{ old('olt_id') == $olt->id ? 'selected' : '' }}>
                                    {{ $olt->name }} ({{ $olt->vendor ?? 'Unknown Vendor' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">ONT Serial Number</label>
                        <input type="text" name="ont_serial_number" class="form-control" value="{{ old('ont_serial_number') }}">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">PON Port</label>
                        <input type="text" name="pon_port" class="form-control" value="{{ old('pon_port') }}" placeholder="0/1/1">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Read Me / Panduan -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">
                    <i class="bi bi-info-circle text-info me-2"></i>Read Me
                </h5>
                <div class="small">
                    <div class="mb-3">
                        <h6 class="fw-bold text-primary mb-2">
                            <i class="bi bi-router me-2"></i>Tipe Koneksi
                        </h6>
                        <ul class="mb-0 ps-3">
                            <li><strong>PPPoE Direct:</strong> Koneksi langsung ke router utama Anda</li>
                            <li><strong>PPPoE via Customer MikroTik:</strong> Customer punya router MikroTik sendiri</li>
                            <li><strong>Static IP:</strong> IP statis untuk customer</li>
                            <li><strong>Hotspot:</strong> Koneksi via hotspot (untuk WiFi)</li>
                            <li><strong>DHCP:</strong> Static binding berdasarkan MAC Address</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-2">
                            <i class="bi bi-lightbulb me-2"></i>Tips Konfigurasi
                        </h6>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>PPPoE Direct:</strong><br>
                            • Username & Password akan ditambahkan otomatis ke MikroTik<br>
                            • Profile akan disesuaikan dengan paket yang dipilih
                        </div>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>Static IP:</strong><br>
                            • Pastikan IP tidak conflict dengan yang sudah ada<br>
                            • Subnet & Gateway harus sesuai dengan network router
                        </div>
                        <div class="alert alert-light border mb-0 p-2">
                            <strong>DHCP Static:</strong><br>
                            • Format MAC: XX:XX:XX:XX:XX:XX<br>
                            • IP akan selalu sama untuk MAC tersebut
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-warning mb-2">
                            <i class="bi bi-exclamation-triangle me-2"></i>Catatan Penting
                        </h6>
                        <ul class="mb-0 ps-3">
                            <li>Username PPPoE harus unik (tidak boleh duplikat)</li>
                            <li>Billing date akan otomatis disesuaikan dengan expired day di paket</li>
                            <li>Customer akan otomatis mendapat WhatsApp notifikasi registrasi</li>
                            <li>Jika paket punya expired day, billing akan otomatis dihitung</li>
                        </ul>
                    </div>

                    <div>
                        <h6 class="fw-bold text-info mb-2">
                            <i class="bi bi-calendar-check me-2"></i>Billing & Expired
                        </h6>
                        <div class="small">
                            <p class="mb-2">Billing date otomatis disesuaikan dengan:</p>
                            <ul class="mb-0 ps-3">
                                <li>Tanggal expired yang diset di paket</li>
                                <li>Jika tidak ada, default 30 hari dari instalasi</li>
                                <li>Customer akan dapat reminder H-7, H-3, H-1</li>
                                <li>Jika expired, otomatis diisolir dengan profile ISOLIR</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Installation Info -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Informasi Instalasi</h5>

                <div class="mb-3">
                    <label class="form-label">Tanggal Instalasi <span class="text-danger">*</span></label>
                    <input type="date" name="installation_date" class="form-control @error('installation_date') is-invalid @enderror"
                           value="{{ old('installation_date', date('Y-m-d')) }}" required>
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
                        <div id="isolir_section">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-x text-danger"></i> Tanggal Isolir
                                    </label>
                                    <input type="date" 
                                           name="custom_isolir_date_only" 
                                           id="custom_isolir_date" 
                                           class="form-control @error('custom_isolir_date') is-invalid @enderror"
                                           value="{{ old('custom_isolir_date_only') }}"
                                           placeholder="Otomatis dari paket">
                                    @error('custom_isolir_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted" id="isolir_date_hint">
                                        <i class="bi bi-info-circle"></i> 
                                        Otomatis: Akan terisi setelah paket dipilih
                                    </small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-clock text-danger"></i> Jam Isolir
                                    </label>
                                    <input type="time" 
                                           name="custom_isolir_time" 
                                           class="form-control"
                                           value="{{ old('custom_isolir_time') }}"
                                           placeholder="Otomatis dari paket">
                                    <small class="text-muted" id="isolir_time_hint">
                                        <i class="bi bi-info-circle"></i> 
                                        Otomatis: Akan terisi setelah paket dipilih
                                    </small>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mb-0" id="isolir_info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Sistem Otomatis:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Isolir date <strong>otomatis terisi</strong> sesuai paket yang dipakai</li>
                                    <li>Tanggal isolir = <strong>next_billing_date</strong> (tanggal sesuai paket)</li>
                                    <li>Jam isolir = <strong>23:59</strong> atau sesuai paket</li>
                                    <li>Profile Mikrotik akan berubah ke <strong>PROFIL-ISOLIR</strong> saat tanggal isolir tiba</li>
                                    <li>Setelah isolir berhasil, isolir date akan <strong>di-update otomatis</strong> ke billing date berikutnya</li>
                                    <li><strong>Override manual:</strong> Isi field di atas jika ingin mengubah tanggal isolir (untuk trial period atau kontrak khusus)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Assign Teknisi</label>
                    <select name="assigned_teknisi_id" class="form-select">
                        <option value="">Tidak Ada</option>
                        @foreach($teknisis as $teknisi)
                            <option value="{{ $teknisi->id }}" {{ old('assigned_teknisi_id') == $teknisi->id ? 'selected' : '' }}>
                                {{ $teknisi->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="4">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Simpan Customer
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Batal
                </a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const connectionTypeSelect = document.getElementById('connection_type');
    const pppoeConfig = document.getElementById('pppoe_config');
    const hotspotConfig = document.getElementById('hotspot_config');
    const staticIpConfig = document.getElementById('static_ip_config');
    const customerMikrotikConfig = document.getElementById('customer_mikrotik_config');
    const dhcpConfig = document.getElementById('dhcp_config');

    function toggleConnectionConfig() {
        const selectedType = connectionTypeSelect.value;
        const pppoeUsername = document.getElementById('pppoe_username');
        const pppoePassword = document.getElementById('pppoe_password');

        // Hide all configs
        if (pppoeConfig) pppoeConfig.style.display = 'none';
        if (hotspotConfig) hotspotConfig.style.display = 'none';
        if (staticIpConfig) staticIpConfig.style.display = 'none';
        if (customerMikrotikConfig) customerMikrotikConfig.style.display = 'none';
        if (dhcpConfig) dhcpConfig.style.display = 'none';

        // ✅ Set required attribute berdasarkan connection type
        if (pppoeUsername) {
            if (selectedType === 'pppoe_direct' || selectedType === 'pppoe_mikrotik') {
                pppoeUsername.setAttribute('required', 'required');
            } else {
                pppoeUsername.removeAttribute('required');
            }
        }
        if (pppoePassword) {
            if (selectedType === 'pppoe_direct' || selectedType === 'pppoe_mikrotik') {
                pppoePassword.setAttribute('required', 'required');
            } else {
                pppoePassword.removeAttribute('required');
            }
        }

        // Show relevant config
        if (selectedType === 'pppoe_direct') {
            if (pppoeConfig) {
                pppoeConfig.style.display = 'block';
                // Update label untuk PPPoE
                const pppoeAlert = document.getElementById('pppoe_alert');
                const pppoeAlertText = document.getElementById('pppoe_alert_text');
                const pppoeUsernameLabel = document.getElementById('pppoe_username_label');
                const pppoePasswordLabel = document.getElementById('pppoe_password_label');
                if (pppoeAlert) pppoeAlert.className = 'alert alert-info';
                if (pppoeAlertText) pppoeAlertText.textContent = 'Konfigurasi PPPoE';
                if (pppoeUsernameLabel) pppoeUsernameLabel.innerHTML = 'PPPoE Username <span class="text-danger">*</span>';
                if (pppoePasswordLabel) pppoePasswordLabel.innerHTML = 'PPPoE Password <span class="text-danger">*</span>';
            }
        } else if (selectedType === 'pppoe_mikrotik') {
            if (pppoeConfig) {
                pppoeConfig.style.display = 'block';
                // Update label untuk PPPoE
                const pppoeAlert = document.getElementById('pppoe_alert');
                const pppoeAlertText = document.getElementById('pppoe_alert_text');
                const pppoeUsernameLabel = document.getElementById('pppoe_username_label');
                const pppoePasswordLabel = document.getElementById('pppoe_password_label');
                if (pppoeAlert) pppoeAlert.className = 'alert alert-info';
                if (pppoeAlertText) pppoeAlertText.textContent = 'Konfigurasi PPPoE';
                if (pppoeUsernameLabel) pppoeUsernameLabel.innerHTML = 'PPPoE Username <span class="text-danger">*</span>';
                if (pppoePasswordLabel) pppoePasswordLabel.innerHTML = 'PPPoE Password <span class="text-danger">*</span>';
            }
            if (customerMikrotikConfig) customerMikrotikConfig.style.display = 'block';
        } else if (selectedType === 'hotspot') {
            // Tampilkan config khusus Hotspot, bukan PPPoE
            if (hotspotConfig) {
                hotspotConfig.style.display = 'block';
            }
            // ✅ Hapus required dari PPPoE fields jika bukan PPPoE
            if (pppoeUsername) {
                pppoeUsername.removeAttribute('required');
            }
            if (pppoePassword) {
                pppoePassword.removeAttribute('required');
            }
        } else if (selectedType === 'static_ip') {
            if (staticIpConfig) staticIpConfig.style.display = 'block';
            // ✅ Hapus required dari PPPoE fields jika bukan PPPoE
            if (pppoeUsername) {
                pppoeUsername.removeAttribute('required');
            }
            if (pppoePassword) {
                pppoePassword.removeAttribute('required');
            }
        } else if (selectedType === 'dhcp') {
            if (dhcpConfig) dhcpConfig.style.display = 'block';
            // ✅ Hapus required dari PPPoE fields jika bukan PPPoE
            if (pppoeUsername) {
                pppoeUsername.removeAttribute('required');
            }
            if (pppoePassword) {
                pppoePassword.removeAttribute('required');
            }
        }
    }

    connectionTypeSelect.addEventListener('change', function() {
        toggleConnectionConfig();
        // ✅ Filter package saat connection type berubah
        filterPackages();
    });

    // Trigger on load if old value exists
    if (connectionTypeSelect.value) {
        toggleConnectionConfig();
    }
    
    // ✅ Sync nilai PPPoE fields ke hidden backup inputs
    const pppoeUsername = document.getElementById('pppoe_username');
    const pppoePassword = document.getElementById('pppoe_password');
    const pppoeUsernameBackup = document.getElementById('pppoe_username_backup');
    const pppoePasswordBackup = document.getElementById('pppoe_password_backup');
    
    if (pppoeUsername && pppoeUsernameBackup) {
        pppoeUsername.addEventListener('input', function() {
            pppoeUsernameBackup.value = this.value;
        });
        // Sync initial value
        if (pppoeUsername.value) {
            pppoeUsernameBackup.value = pppoeUsername.value;
        }
    }
    if (pppoePassword && pppoePasswordBackup) {
        pppoePassword.addEventListener('input', function() {
            pppoePasswordBackup.value = this.value;
        });
        // Sync initial value
        if (pppoePassword.value) {
            pppoePasswordBackup.value = pppoePassword.value;
        }
    }
    
    // ✅ Pastikan nilai terkirim saat form submit
    const form = document.querySelector('form[action*="customers.store"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const connectionType = connectionTypeSelect ? connectionTypeSelect.value : '';
            if (['pppoe_direct', 'pppoe_mikrotik'].includes(connectionType)) {
                // Jika PPPoE, pastikan nilai dari visible field atau backup
                if (pppoeUsername && !pppoeUsername.value && pppoeUsernameBackup && pppoeUsernameBackup.value) {
                    // Gunakan nilai dari backup jika visible field kosong
                    pppoeUsername.value = pppoeUsernameBackup.value;
                }
                if (pppoePassword && !pppoePassword.value && pppoePasswordBackup && pppoePasswordBackup.value) {
                    // Gunakan nilai dari backup jika visible field kosong
                    pppoePassword.value = pppoePasswordBackup.value;
                }
            }
        });
    }

    // ✅ Auto-fill Router dan Connection Type saat Package dipilih
    const packageSelect = document.getElementById('package_id');
    const routerSelect = document.getElementById('router_id');
    const packageRouterInfo = document.getElementById('package_router_info');
    const packageRouterText = document.getElementById('package_router_text');

    // ✅ Fungsi untuk filter packages berdasarkan router dan connection_type
    function filterPackages() {
        const routerId = routerSelect ? routerSelect.value : '';
        const connectionType = connectionTypeSelect.value;
        
        // Map connection_type customer ke package connection_type
        let packageConnectionType = null;
        if (connectionType === 'pppoe_direct' || connectionType === 'pppoe_mikrotik') {
            packageConnectionType = 'pppoe';
        } else if (connectionType === 'hotspot') {
            packageConnectionType = 'hotspot';
        }
        
        // Jika tidak ada router atau connection_type yang dipilih, tampilkan semua
        if (!routerId || !packageConnectionType) {
            return;
        }

        // Fetch packages via AJAX
        const url = new URL('/api/packages', window.location.origin);
        url.searchParams.append('router_id', routerId);
        url.searchParams.append('connection_type', packageConnectionType);

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && packageSelect) {
                    // Simpan nilai yang sedang dipilih
                    const currentValue = packageSelect.value;
                    
                    // Clear dan isi ulang options
                    packageSelect.innerHTML = '<option value="">Pilih Paket</option>';
                    
                    if (data.packages.length === 0) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Tidak ada paket untuk router dan tipe koneksi ini';
                        option.disabled = true;
                        packageSelect.appendChild(option);
                    } else {
                        data.packages.forEach(pkg => {
                            const option = document.createElement('option');
                            option.value = pkg.id;
                            option.textContent = `${pkg.name} - ${pkg.speed_label} - ${pkg.price}`;
                            packageSelect.appendChild(option);
                        });
                        
                        // Jika nilai sebelumnya masih valid, set kembali
                        if (currentValue) {
                            const stillExists = Array.from(packageSelect.options).some(opt => opt.value === currentValue);
                            if (stillExists) {
                                packageSelect.value = currentValue;
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching packages:', error);
            });
    }

    // ✅ Event listener untuk router
    if (routerSelect) {
        routerSelect.addEventListener('change', function() {
            // Reset package saat router berubah
            if (packageSelect) {
                packageSelect.value = '';
            }
            // Filter packages berdasarkan router dan connection_type
            filterPackages();
        });
    }

    if (packageSelect && routerSelect) {
        packageSelect.addEventListener('change', function() {
            const packageId = this.value;
            
            if (!packageId) {
                routerSelect.value = '';
                if (packageRouterInfo) packageRouterInfo.style.display = 'none';
                return;
            }

            // Fetch package info via AJAX
            fetch(`/api/packages/${packageId}/info`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.router_id) {
                        // Auto-fill router
                        routerSelect.value = data.router_id;
                        
                        // Auto-fill connection type sesuai dengan package
                        // Map connection_type dari package (pppoe/hotspot) ke connection_type customer
                        if (data.connection_type === 'pppoe') {
                            connectionTypeSelect.value = 'pppoe_direct';
                            toggleConnectionConfig();
                        } else if (data.connection_type === 'hotspot') {
                            connectionTypeSelect.value = 'hotspot';
                            toggleConnectionConfig();
                        }
                        
                        // Show info
                        if (packageRouterText) {
                            packageRouterText.textContent = `Router: ${data.router_name} | Tipe Koneksi: ${data.connection_type_display}`;
                        }
                        if (packageRouterInfo) {
                            packageRouterInfo.style.display = 'block';
                        }
                    } else {
                        // Package tidak punya router, reset
                        routerSelect.value = '';
                        if (packageRouterInfo) packageRouterInfo.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching package info:', error);
                    if (packageRouterInfo) packageRouterInfo.style.display = 'none';
                });
        });
        
        // ✅ Event listener untuk router - filter packages
        if (routerSelect) {
            routerSelect.addEventListener('change', function() {
                // Reset package saat router berubah (kecuali jika package dipilih dulu)
                if (packageSelect && !packageSelect.dataset.autoSelecting) {
                    packageSelect.value = '';
                }
                // Filter packages berdasarkan router dan connection_type
                filterPackages();
            });
        }

        // Trigger on load if package already selected
        if (packageSelect.value) {
            packageSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // ✅ Update isolir date hint berdasarkan paket yang dipilih
    function updateIsolirHint(packageId) {
        if (!packageId) {
            document.getElementById('isolir_date_hint').innerHTML = '<i class="bi bi-info-circle"></i> Otomatis: Akan terisi setelah paket dipilih';
            document.getElementById('isolir_time_hint').innerHTML = '<i class="bi bi-info-circle"></i> Otomatis: Akan terisi setelah paket dipilih';
            return;
        }
        
        // Fetch package info untuk mendapatkan custom_expire_day dan custom_expire_time
        fetch(`/api/packages/${packageId}/info`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const isolirDateHint = document.getElementById('isolir_date_hint');
                    const isolirTimeHint = document.getElementById('isolir_time_hint');
                    const isolirInfo = document.getElementById('isolir_info');
                    
                    if (data.custom_expire_day) {
                        // Paket punya custom_expire_day
                        isolirDateHint.innerHTML = `<i class="bi bi-info-circle"></i> Otomatis: Tanggal ${data.custom_expire_day} setiap bulan`;
                        isolirTimeHint.innerHTML = `<i class="bi bi-info-circle"></i> Otomatis: ${data.custom_expire_time ? data.custom_expire_time : '23:59'}`;
                        
                        isolirInfo.innerHTML = `
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Sistem Otomatis:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Isolir date <strong>otomatis terisi</strong> sesuai paket yang dipakai</li>
                                <li>Tanggal isolir = <strong>next_billing_date</strong> (tanggal ${data.custom_expire_day})</li>
                                <li>Jam isolir = <strong>${data.custom_expire_time ? data.custom_expire_time : '23:59'}</strong> (dari paket)</li>
                                <li>Profile Mikrotik akan berubah ke <strong>PROFIL-ISOLIR</strong> saat tanggal isolir tiba</li>
                                <li>Setelah isolir berhasil, isolir date akan <strong>di-update otomatis</strong> ke billing date berikutnya</li>
                                <li><strong>Override manual:</strong> Isi field di atas jika ingin mengubah tanggal isolir (untuk trial period atau kontrak khusus)</li>
                            </ul>
                        `;
                    } else {
                        // Paket tidak punya custom_expire_day
                        isolirDateHint.innerHTML = '<i class="bi bi-info-circle"></i> Paket tidak punya custom_expire_day - isi manual jika diperlukan';
                        isolirTimeHint.innerHTML = '<i class="bi bi-info-circle"></i> Default: 23:59';
                        
                        isolirInfo.innerHTML = `
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
                        `;
                        isolirInfo.className = 'alert alert-warning mb-0';
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching package info for isolir hint:', error);
            });
    }
    
    // ✅ Update isolir hint saat package berubah
    if (packageSelect) {
        packageSelect.addEventListener('change', function() {
            updateIsolirHint(this.value);
        });
        
        // Update hint saat load jika package sudah dipilih
        if (packageSelect.value) {
            updateIsolirHint(packageSelect.value);
        }
    }
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
