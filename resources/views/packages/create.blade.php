@extends('layouts.admin')

@section('title', 'Tambah Paket')
@section('page-title', 'Tambah Paket Baru')

@section('content')
<form action="{{ route('packages.store') }}" method="POST">
    @csrf

    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Informasi Dasar</h5>

                <div class="mb-3">
                    <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="Contoh: 20 Mbps Unlimited" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                              rows="3" placeholder="Deskripsi paket...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Download Speed (Mbps) <span class="text-danger">*</span></label>
                        <input type="number" name="download_speed" class="form-control @error('download_speed') is-invalid @enderror"
                               value="{{ old('download_speed') }}" min="1" required>
                        @error('download_speed')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Upload Speed (Mbps) <span class="text-danger">*</span></label>
                        <input type="number" name="upload_speed" class="form-control @error('upload_speed') is-invalid @enderror"
                               value="{{ old('upload_speed') }}" min="1" required>
                        @error('upload_speed')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                    <input type="number" name="price" class="form-control @error('price') is-invalid @enderror"
                           value="{{ old('price') }}" min="0" step="1000" required>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- FUP Settings -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">FUP (Fair Usage Policy)</h5>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="has_fup" id="has_fup"
                           value="1" {{ old('has_fup') ? 'checked' : '' }}>
                    <label class="form-check-label" for="has_fup">
                        Aktifkan FUP
                    </label>
                </div>

                <div id="fup_settings" style="display: {{ old('has_fup') ? 'block' : 'none' }};">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kuota FUP (GB)</label>
                            <input type="number" name="fup_quota" class="form-control"
                                   value="{{ old('fup_quota') }}" min="1">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Speed Setelah FUP (Mbps)</label>
                            <input type="number" name="fup_speed" class="form-control"
                                   value="{{ old('fup_speed') }}" min="1">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Pengaturan Lanjutan</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Burst Limit (Mbps)</label>
                        <input type="number" name="burst_limit" class="form-control"
                               value="{{ old('burst_limit') }}" min="1">
                        <small class="text-muted">Kecepatan maksimal sesaat (opsional)</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority (1-10) <span class="text-danger">*</span></label>
                        <input type="number" name="priority" class="form-control @error('priority') is-invalid @enderror"
                               value="{{ old('priority', 5) }}" min="1" max="10" required>
                        <small class="text-muted">QoS Priority (1=terendah, 10=tertinggi)</small>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Connection Limit</label>
                    <input type="number" name="connection_limit" class="form-control"
                           value="{{ old('connection_limit') }}" min="1">
                    <small class="text-muted">Maksimal device yang bisa terhubung (opsional)</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tersedia Untuk</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="pppoe" id="av_pppoe" checked>
                        <label class="form-check-label" for="av_pppoe">PPPoE</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="static" id="av_static" checked>
                        <label class="form-check-label" for="av_static">Static IP</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="hotspot" id="av_hotspot">
                        <label class="form-check-label" for="av_hotspot">Hotspot</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="dhcp" id="av_dhcp" checked>
                        <label class="form-check-label" for="av_dhcp">DHCP</label>
                    </div>
                </div>
            </div>

            <!-- Router & Connection Type Selection -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Router & Tipe Koneksi</h5>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Info:</strong> Pilih router spesifik untuk package ini agar tidak duplikat. Profile akan dibuat di router yang dipilih sesuai tipe koneksi.
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Router <span class="text-danger">*</span></label>
                        <select name="router_id" class="form-select @error('router_id') is-invalid @enderror" required>
                            <option value="">Pilih Router</option>
                            @foreach($routers ?? [] as $router)
                                <option value="{{ $router->id }}" {{ old('router_id') == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->ip_address }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Router tempat profile package akan dibuat</small>
                        @error('router_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipe Koneksi <span class="text-danger">*</span></label>
                        <select name="connection_type" class="form-select @error('connection_type') is-invalid @enderror" required>
                            <option value="pppoe" {{ old('connection_type', 'pppoe') == 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                            <option value="hotspot" {{ old('connection_type') == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
                        </select>
                        <small class="text-muted">Tipe connection untuk profile di Mikrotik</small>
                        @error('connection_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
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
                            <i class="bi bi-calendar-check me-2"></i>Custom Expire Day
                        </h6>
                        <p class="mb-2">Tanggal expired otomatis untuk semua customer paket ini.</p>
                        <ul class="mb-0 ps-3">
                            <li>Range: 1-31 (tanggal dalam bulan)</li>
                            <li>Semua customer paket ini akan expired di tanggal yang sama</li>
                            <li>Billing date otomatis disesuaikan</li>
                            <li>Jika kosong, billing mengikuti tanggal instalasi</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-2">
                            <i class="bi bi-lightbulb me-2"></i>Tips Konfigurasi
                        </h6>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>FUP (Fair Usage Policy):</strong><br>
                            • Batasi penggunaan setelah kuota habis<br>
                            • Speed turun otomatis setelah FUP tercapai
                        </div>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>Burst Limit:</strong><br>
                            • Kecepatan sesaat maksimal<br>
                            • Berguna untuk aplikasi yang butuh boost
                        </div>
                        <div class="alert alert-light border mb-0 p-2">
                            <strong>Priority:</strong><br>
                            • 1-10 (1 terendah, 10 tertinggi)<br>
                            • Semakin tinggi, semakin prioritas bandwidth
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-warning mb-2">
                            <i class="bi bi-exclamation-triangle me-2"></i>Catatan Penting
                        </h6>
                        <ul class="mb-0 ps-3">
                            <li>Grace Period: Tenggang waktu setelah jatuh tempo sebelum diisolir</li>
                            <li>Custom Expire Day: Otomatis mengatur billing date semua customer</li>
                            <li>Tersedia Untuk: Pilih tipe koneksi yang bisa pakai paket ini</li>
                            <li>Connection Limit: Batasi jumlah device yang bisa terhubung</li>
                        </ul>
                    </div>

                    <div>
                        <h6 class="fw-bold text-info mb-2">
                            <i class="bi bi-speedometer2 me-2"></i>Contoh Paket
                        </h6>
                        <div class="alert alert-light border mb-2 p-2">
                            <strong>Paket Unlimited 20 Mbps:</strong><br>
                            Download: 20 Mbps<br>
                            Upload: 10 Mbps<br>
                            FUP: Off<br>
                            Custom Expire: 5 (tiap tanggal 5)
                        </div>
                        <div class="alert alert-light border mb-0 p-2">
                            <strong>Paket FUP 50 Mbps:</strong><br>
                            Download: 50 Mbps<br>
                            FUP: 100 GB → 10 Mbps<br>
                            Custom Expire: 1 (tiap tanggal 1)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Billing Settings -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Billing</h5>

                <div class="mb-3">
                    <label class="form-label">Billing Cycle <span class="text-danger">*</span></label>
                    <select name="billing_cycle" class="form-select @error('billing_cycle') is-invalid @enderror" required>
                        <option value="daily" {{ old('billing_cycle') == 'daily' ? 'selected' : '' }}>Daily (Harian)</option>
                        <option value="weekly" {{ old('billing_cycle') == 'weekly' ? 'selected' : '' }}>Weekly (Mingguan)</option>
                        <option value="monthly" {{ old('billing_cycle', 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly (Bulanan)</option>
                        <option value="yearly" {{ old('billing_cycle') == 'yearly' ? 'selected' : '' }}>Yearly (Tahunan)</option>
                    </select>
                    @error('billing_cycle')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Grace Period (hari) <span class="text-danger">*</span></label>
                    <input type="number" name="grace_period" class="form-control @error('grace_period') is-invalid @enderror"
                           value="{{ old('grace_period', 3) }}" min="0" max="30" required>
                    <small class="text-muted">Tenggang waktu setelah jatuh tempo</small>
                    @error('grace_period')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Tanggal Expired (1–31)
                            <small class="text-muted">(opsional)</small>
                        </label>
                        <input
                            type="number"
                            name="custom_expire_day"
                            class="form-control @error('custom_expire_day') is-invalid @enderror"
                            min="1" max="31"
                            value="{{ old('custom_expire_day') }}"
                            placeholder="Contoh: 5"
                        >
                        @error('custom_expire_day')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">
                            Jika diisi, semua pelanggan paket ini akan jatuh tempo tiap tanggal ini.
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Waktu Expired
                            <small class="text-muted">(opsional)</small>
                        </label>
                        <input
                            type="time"
                            name="custom_expire_time"
                            class="form-control @error('custom_expire_time') is-invalid @enderror"
                            value="{{ old('custom_expire_time', '23:59') }}"
                        >
                        @error('custom_expire_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">
                            Kosongkan jika tidak perlu, default 23:59.
                        </small>
                    </div>
                </div>

            </div>


            <!-- Status -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Status</h5>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                           value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">
                        Aktif (customer bisa pilih paket ini)
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Simpan Paket
                </button>
                <a href="{{ route('packages.index') }}" class="btn btn-secondary">
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
    const hasFupCheckbox = document.getElementById('has_fup');
    const fupSettings = document.getElementById('fup_settings');

    hasFupCheckbox.addEventListener('change', function() {
        fupSettings.style.display = this.checked ? 'block' : 'none';
    });
});
</script>
@endpush
