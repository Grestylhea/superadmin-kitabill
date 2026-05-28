@extends('layouts.admin')

@section('title', 'Edit Paket')
@section('page-title', 'Edit Paket')

@section('content')
<form action="{{ route('packages.update', $package) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Informasi Dasar</h5>

                <div class="mb-3">
                    <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $package->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                              rows="3">{{ old('description', $package->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Download Speed (Mbps) <span class="text-danger">*</span></label>
                        <input type="number" name="download_speed" class="form-control @error('download_speed') is-invalid @enderror"
                               value="{{ old('download_speed', $package->download_speed) }}" min="1" required>
                        @error('download_speed')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Upload Speed (Mbps) <span class="text-danger">*</span></label>
                        <input type="number" name="upload_speed" class="form-control @error('upload_speed') is-invalid @enderror"
                               value="{{ old('upload_speed', $package->upload_speed) }}" min="1" required>
                        @error('upload_speed')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                    <input type="number" name="price" class="form-control @error('price') is-invalid @enderror"
                           value="{{ old('price', $package->price) }}" min="0" step="1000" required>
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
                           value="1" {{ old('has_fup', $package->has_fup) ? 'checked' : '' }}>
                    <label class="form-check-label" for="has_fup">
                        Aktifkan FUP
                    </label>
                </div>

                <div id="fup_settings" style="display: {{ old('has_fup', $package->has_fup) ? 'block' : 'none' }};">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kuota FUP (GB)</label>
                            <input type="number" name="fup_quota" class="form-control"
                                   value="{{ old('fup_quota', $package->fup_quota) }}" min="1">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Speed Setelah FUP (Mbps)</label>
                            <input type="number" name="fup_speed" class="form-control"
                                   value="{{ old('fup_speed', $package->fup_speed) }}" min="1">
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
                               value="{{ old('burst_limit', $package->burst_limit) }}" min="1">
                        <small class="text-muted">Kecepatan maksimal sesaat</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority (1-10) <span class="text-danger">*</span></label>
                        <input type="number" name="priority" class="form-control @error('priority') is-invalid @enderror"
                               value="{{ old('priority', $package->priority) }}" min="1" max="10" required>
                        <small class="text-muted">QoS Priority</small>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Connection Limit</label>
                    <input type="number" name="connection_limit" class="form-control"
                           value="{{ old('connection_limit', $package->connection_limit) }}" min="1">
                    <small class="text-muted">Maksimal device yang bisa terhubung</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tersedia Untuk</label>
                    @php
                        $availableFor = old('available_for', $package->available_for ?? []);
                    @endphp
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="pppoe" id="av_pppoe"
                               {{ in_array('pppoe', $availableFor) ? 'checked' : '' }}>
                        <label class="form-check-label" for="av_pppoe">PPPoE</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="static" id="av_static"
                               {{ in_array('static', $availableFor) ? 'checked' : '' }}>
                        <label class="form-check-label" for="av_static">Static IP</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="hotspot" id="av_hotspot"
                               {{ in_array('hotspot', $availableFor) ? 'checked' : '' }}>
                        <label class="form-check-label" for="av_hotspot">Hotspot</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="available_for[]" value="dhcp" id="av_dhcp"
                               {{ in_array('dhcp', $availableFor) ? 'checked' : '' }}>
                        <label class="form-check-label" for="av_dhcp">DHCP</label>
                    </div>
                </div>
            </div>

            <!-- Router & Connection Type Selection -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Router & Tipe Koneksi</h5>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Info:</strong> Pilih router spesifik untuk package ini. Jika router diubah, profile akan dihapus dari router lama dan ditambahkan ke router baru.
                </div>

                @php
                    $currentRouter = $package->routers->first();
                    $currentConnectionType = $currentRouter ? $currentRouter->pivot->connection_type : 'pppoe';
                @endphp

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Router <span class="text-danger">*</span></label>
                        <select name="router_id" class="form-select @error('router_id') is-invalid @enderror" required>
                            <option value="">Pilih Router</option>
                            @foreach($routers ?? [] as $router)
                                <option value="{{ $router->id }}" 
                                    {{ old('router_id', $currentRouter ? $currentRouter->id : '') == $router->id ? 'selected' : '' }}>
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
                            <option value="pppoe" {{ old('connection_type', $currentConnectionType) == 'pppoe' ? 'selected' : '' }}>PPPoE</option>
                            <option value="hotspot" {{ old('connection_type', $currentConnectionType) == 'hotspot' ? 'selected' : '' }}>Hotspot</option>
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
            <!-- Billing Settings -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Billing</h5>

                <div class="mb-3">
                    <label class="form-label">Billing Cycle <span class="text-danger">*</span></label>
                    <select name="billing_cycle" class="form-select @error('billing_cycle') is-invalid @enderror" required>
                        <option value="daily" {{ old('billing_cycle', $package->billing_cycle) == 'daily' ? 'selected' : '' }}>Daily (Harian)</option>
                        <option value="weekly" {{ old('billing_cycle', $package->billing_cycle) == 'weekly' ? 'selected' : '' }}>Weekly (Mingguan)</option>
                        <option value="monthly" {{ old('billing_cycle', $package->billing_cycle) == 'monthly' ? 'selected' : '' }}>Monthly (Bulanan)</option>
                        <option value="yearly" {{ old('billing_cycle', $package->billing_cycle) == 'yearly' ? 'selected' : '' }}>Yearly (Tahunan)</option>
                    </select>
                    @error('billing_cycle')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Bagian Setting Expired --}}
                <div class="alert alert-info border-0">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-2"></i>Cara Kerja System Expired:</h6>
                    <small>
                        1. <strong>Tanggal & Waktu Expired</strong>: Kapan invoice di-generate (misal: tanggal 29 jam 14:59)<br>
                        2. <strong>Grace Period</strong>: Tenggang waktu setelah jatuh tempo sebelum customer di-suspend<br>
                        3. Customer akan di-suspend otomatis jika tidak bayar setelah grace period habis<br>
                        4. WhatsApp reminder akan dikirim H-7, H-3, H-1 sebelum jatuh tempo
                    </small>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="bi bi-calendar-event me-1"></i>Tanggal Expired (1–31)
                        </label>
                        <input
                            type="number"
                            name="custom_expire_day"
                            class="form-control @error('custom_expire_day') is-invalid @enderror"
                            min="1" max="31"
                            value="{{ old('custom_expire_day', $package->custom_expire_day) }}"
                            placeholder="Contoh: 29"
                        >
                        <small class="text-muted">Tanggal berapa invoice di-generate setiap bulan</small>
                        @error('custom_expire_day')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="bi bi-clock me-1"></i>Waktu Expired
                        </label>
                        <input
                            type="time"
                            name="custom_expire_time"
                            class="form-control @error('custom_expire_time') is-invalid @enderror"
                            value="{{ old('custom_expire_time', $package->custom_expire_time ? \Illuminate\Support\Str::substr($package->custom_expire_time, 0, 5) : '23:59') }}"
                        >
                        <small class="text-muted">Jam berapa invoice di-generate</small>
                        @error('custom_expire_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-hourglass-split me-1"></i>Grace Period (hari) <span class="text-danger">*</span>
                    </label>
                    <input type="number" name="grace_period" class="form-control @error('grace_period') is-invalid @enderror"
                           value="{{ old('grace_period', $package->grace_period) }}" min="0" max="30" required
                           placeholder="Contoh: 3">
                    <small class="text-muted">
                        <strong>Contoh:</strong> Jika due date tanggal 29 dan grace period 3 hari, maka customer akan di-suspend tanggal 2 bulan berikutnya
                    </small>
                    @error('grace_period')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Timeline Example --}}
                <div class="alert alert-success border-0 bg-light">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-calendar-check me-2"></i>Contoh Timeline:</h6>
                    <small class="text-muted">
                        <strong>Setting:</strong> Expired tgl 29 jam 14:59, Grace 3 hari<br>
                        <div class="mt-2">
                            📅 <strong>29 Des 14:59</strong> → Invoice generated, Due date: 29 Des<br>
                            🔔 <strong>22 Des</strong> → Reminder H-7 (WhatsApp)<br>
                            🔔 <strong>26 Des</strong> → Reminder H-3 (WhatsApp)<br>
                            🔔 <strong>28 Des</strong> → Reminder H-1 (WhatsApp)<br>
                            ⏰ <strong>29-31 Des & 1-2 Jan</strong> → Customer masih aktif (grace period)<br>
                            🔴 <strong>2 Jan</strong> → Customer di-suspend + WhatsApp notif
                        </div>
                    </small>
                </div>

            </div>

            <!-- Status -->
            <div class="custom-table mb-4">
                <h5 class="fw-bold mb-4">Status & Info</h5>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                           value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">
                        Aktif
                    </label>
                </div>

                <div class="alert alert-info">
                    <strong>Total Customer:</strong> {{ $package->customers_count ?? 0 }} users
                </div>
            </div>

            <!-- Actions -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Update Paket
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
