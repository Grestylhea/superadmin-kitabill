@extends('layouts.admin')

@section('content')
<style>
/* Custom responsive styles */
@media (max-width: 768px) {
    .card-body .row {
        flex-direction: column;
    }
    .accordion-button {
        font-size: 14px;
        padding: 10px;
    }
    .btn-sm {
        font-size: 11px;
        padding: 4px 8px;
    }
    #wa-qr-img {
        max-width: 200px !important;
    }
    .list-group-item {
        padding: 8px;
        font-size: 13px;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4"><i class="bi bi-gear-fill me-2"></i>Pengaturan Sistem</h2>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf

        {{-- ===================== COMPANY INFO ===================== --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Informasi Perusahaan</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Perusahaan</label>
                        <input type="text" name="company_name" class="form-control" 
                               value="{{ old('company_name', $settings['company_name'] ?? '') }}" 
                               placeholder="PT. Internet Service Provider">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Perusahaan</label>
                        <input type="email" name="company_email" class="form-control" 
                               value="{{ old('company_email', $settings['company_email'] ?? '') }}" 
                               placeholder="admin@isp.com">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">
                            <i class="bi bi-clock-history text-primary"></i> 
                            <strong>Zona Waktu (Timezone)</strong>
                        </label>
                        <select name="app_timezone" class="form-select">
                            @foreach($timezones as $value => $label)
                                <option value="{{ $value }}" 
                                    {{ old('app_timezone', $settings['app_timezone'] ?? 'Asia/Jakarta') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> 
                            Zona waktu ini akan digunakan untuk semua jadwal sistem (isolir otomatis, reminder, invoice, dll).
                            <br>
                            <strong>Waktu server saat ini:</strong> 
                            <span class="badge bg-info">{{ now()->format('d M Y H:i:s') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== WHATSAPP GATEWAY ===================== --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-whatsapp me-2"></i>WhatsApp Gateway</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="text-muted mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Scan QR untuk menghubungkan WhatsApp ke gateway. QR akan auto-refresh setiap 1 menit jika belum terkoneksi.
                        </p>

                        <div class="d-flex align-items-center mb-3">
                            <span id="wa-status-badge" class="badge bg-danger me-2 px-3 py-2">
                                <i class="bi bi-x-circle me-1"></i>DISCONNECTED
                            </span>
                            <span id="wa-status-text" class="text-muted">
                                Belum terhubung.
                            </span>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-primary btn-sm me-2" id="btnScanQr">
                                <i class="bi bi-arrow-clockwise me-1"></i>Scan QR Baru
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnReconnect">
                                <i class="bi bi-plug me-1"></i>Reconnect
                            </button>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nomor WhatsApp (Opsional)</label>
                            <input type="text" name="wa_number" class="form-control" 
                                   value="{{ old('wa_number', $settings['wa_number'] ?? '') }}" 
                                   placeholder="628xxxxxxxxxx">
                            <small class="text-muted">Nomor yang akan digunakan untuk notifikasi</small>
                        </div>
                    </div>

                    <div class="col-md-6" id="qr-box-container">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <p class="mb-2"><strong>Scan QR dengan WhatsApp</strong></p>
                                <div id="qr-container" style="min-height: 250px; display: flex; align-items: center; justify-content: center;">
                                    <img id="wa-qr-img"
                                         src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='250' height='250'%3E%3Crect width='250' height='250' fill='%23f0f0f0'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='Arial' font-size='14' fill='%23999'%3ELoading QR...%3C/text%3E%3C/svg%3E"
                                         alt="QR WhatsApp"
                                         class="img-fluid rounded"
                                         style="max-width: 250px; height: auto;">
                                </div>
                                <p class="text-muted small mt-2 mb-0">
                                    <i class="bi bi-phone me-1"></i>Buka WhatsApp → Settings → Linked Devices → Link a Device
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                {{-- Test WhatsApp --}}
                <h6 class="mb-3"><i class="bi bi-send me-2"></i>Test Kirim Pesan</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nomor Tujuan (628...)</label>
                        <input type="text" id="wa_test_number" class="form-control" 
                               placeholder="628xxxxxxxxxx">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Pesan Test</label>
                        <input type="text" id="wa_test_message" class="form-control" 
                               value="Pesan test dari sistem">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-success w-100" id="btnSendTest">
                            <i class="bi bi-send me-1"></i>Kirim Test
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== TEMPLATE PESAN WHATSAPP ===================== --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-chat-square-text me-2"></i>Template Pesan WhatsApp</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Edit template pesan yang akan dikirim otomatis ke customer. Gunakan placeholder untuk data dinamis.
                </p>

                {{-- Template 1: Expired Notification --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-exclamation-triangle text-danger me-2"></i>1. Expired Notification Message
                    </label>
                    <textarea name="wa_expired_template" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_expired_template'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim saat customer di-suspend karena tagihan belum dibayar</small>
                </div>

                {{-- Template 2: Reminder 7 Days --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-bell text-warning me-2"></i>2. Reminder 7 Days
                    </label>
                    <textarea name="wa_reminder_h7" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_reminder_h7'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim 7 hari sebelum jatuh tempo</small>
                </div>

                {{-- Template 3: Reminder 3 Days --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-bell-fill text-warning me-2"></i>3. Reminder 3 Days
                    </label>
                    <textarea name="wa_reminder_h3" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_reminder_h3'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim 3 hari sebelum jatuh tempo</small>
                </div>

                {{-- Template 4: Reminder 1 Day --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-alarm text-danger me-2"></i>4. Reminder 1 Day
                    </label>
                    <textarea name="wa_reminder_h1" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_reminder_h1'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim 1 hari sebelum jatuh tempo</small>
                </div>

                {{-- Template 5: Invoice Notification --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-file-text text-primary me-2"></i>5. Invoice Notification Payment
                    </label>
                    <textarea name="wa_invoice_notification" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_invoice_notification'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim saat invoice baru dibuat</small>
                </div>

                {{-- Template 6: Reactivation/Balance Payment --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-check-circle text-success me-2"></i>6. Balance Notification Payment
                    </label>
                    <textarea name="wa_reactivation_template" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_reactivation_template'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim saat customer bayar dan diaktifkan kembali</small>
                </div>

                {{-- Template 7: Welcome Message --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-hand-thumbs-up text-info me-2"></i>7. Welcome Message
                    </label>
                    <textarea name="wa_welcome_message" class="form-control font-monospace" rows="8" 
                              style="font-size: 13px;">{{ $settings['wa_welcome_message'] ?? '' }}</textarea>
                    <small class="text-muted">Pesan yang dikirim saat customer baru registrasi</small>
                </div>

                {{-- Placeholder Guide --}}
                <div class="alert alert-light border">
                    <h6 class="mb-2"><i class="bi bi-code-square me-2"></i>Placeholder yang Bisa Digunakan:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <small>
                                <strong>Customer:</strong><br>
                                <code>{customer_name}</code> - Nama customer<br>
                                <code>{customer_code}</code> - Kode customer<br>
                                <code>{username}</code> - Username<br>
                                <code>{phone}</code> - No HP<br>
                            </small>
                        </div>
                        <div class="col-md-4">
                            <small>
                                <strong>Invoice:</strong><br>
                                <code>{invoice_number}</code> - Nomor invoice<br>
                                <code>{total}</code> - Total tagihan<br>
                                <code>{due_date}</code> - Tanggal jatuh tempo<br>
                                <code>{issue_date}</code> - Tanggal invoice<br>
                                <code>{period}</code> - Periode<br>
                            </small>
                        </div>
                        <div class="col-md-4">
                            <small>
                                <strong>Package & Date:</strong><br>
                                <code>{package_name}</code> - Nama paket<br>
                                <code>{isolir_date}</code> - Tanggal isolir (untuk custom isolir)<br>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== PAYMENT GATEWAY ===================== --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Gateway</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    <i class="bi bi-info-circle me-1"></i>
                    Konfigurasi payment gateway untuk terima pembayaran dari customer. Pilih gateway yang aktif di bawah.
                </p>

                {{-- Active Gateway Selection --}}
                <div class="mb-4">
                    <label class="form-label"><strong>Payment Gateway Aktif</strong></label>
                    <select name="active_payment_gateway" class="form-select">
                        <option value="">-- Pilih Gateway --</option>
                        <option value="xendit" {{ ($settings['active_payment_gateway'] ?? '') === 'xendit' ? 'selected' : '' }}>Xendit</option>
                        <option value="tripay" {{ ($settings['active_payment_gateway'] ?? '') === 'tripay' ? 'selected' : '' }}>Tripay</option>
                        <option value="midtrans" {{ ($settings['active_payment_gateway'] ?? '') === 'midtrans' ? 'selected' : '' }}>Midtrans</option>
                        <option value="duitku" {{ ($settings['active_payment_gateway'] ?? '') === 'duitku' ? 'selected' : '' }}>Duitku</option>
                    </select>
                    <small class="text-muted">Gateway yang dipilih akan digunakan untuk generate payment</small>
                </div>

                {{-- Accordion Payment Gateway Configs --}}
                <div class="accordion" id="paymentGatewayAccordion">
                    
                    {{-- XENDIT --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseXendit">
                                <i class="bi bi-credit-card-2-front me-2"></i><strong>Xendit</strong>
                                @if(!empty($settings['xendit_secret_key']))
                                    <span class="badge bg-success ms-2">Configured</span>
                                @endif
                            </button>
                        </h2>
                        <div id="collapseXendit" class="accordion-collapse collapse show" data-bs-parent="#paymentGatewayAccordion">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Secret Key</label>
                                            <input type="text" name="xendit_secret_key" class="form-control" 
                                                   value="{{ old('xendit_secret_key', $settings['xendit_secret_key'] ?? '') }}"
                                                   placeholder="xnd_...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mode</label>
                                            <select name="xendit_mode" class="form-select">
                                                <option value="sandbox" {{ ($settings['xendit_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                                <option value="production" {{ ($settings['xendit_mode'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light p-3 rounded">
                                            <h6><i class="bi bi-book me-1"></i>Panduan</h6>
                                            <small class="text-muted">
                                                <ul class="ps-3 mb-0">
                                                    <li>Login ke <a href="https://dashboard.xendit.co" target="_blank">Xendit Dashboard</a></li>
                                                    <li>Buka Settings → API Keys</li>
                                                    <li>Copy Secret Key</li>
                                                    <li>Support: VA, E-Wallet, QRIS</li>
                                                </ul>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TRIPAY --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTripay">
                                <i class="bi bi-wallet2 me-2"></i><strong>Tripay</strong>
                                @if(!empty($settings['tripay_api_key']))
                                    <span class="badge bg-success ms-2">Configured</span>
                                @endif
                            </button>
                        </h2>
                        <div id="collapseTripay" class="accordion-collapse collapse" data-bs-parent="#paymentGatewayAccordion">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">API Key</label>
                                            <input type="text" name="tripay_api_key" class="form-control" 
                                                   value="{{ old('tripay_api_key', $settings['tripay_api_key'] ?? '') }}"
                                                   placeholder="DEV-...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Private Key</label>
                                            <input type="text" name="tripay_private_key" class="form-control" 
                                                   value="{{ old('tripay_private_key', $settings['tripay_private_key'] ?? '') }}"
                                                   placeholder="Private Key">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Merchant Code</label>
                                            <input type="text" name="tripay_merchant_code" class="form-control" 
                                                   value="{{ old('tripay_merchant_code', $settings['tripay_merchant_code'] ?? '') }}"
                                                   placeholder="T1234">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mode</label>
                                            <select name="tripay_mode" class="form-select">
                                                <option value="sandbox" {{ ($settings['tripay_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                                <option value="production" {{ ($settings['tripay_mode'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light p-3 rounded">
                                            <h6><i class="bi bi-book me-1"></i>Panduan</h6>
                                            <small class="text-muted">
                                                <ul class="ps-3 mb-0">
                                                    <li>Login ke <a href="https://tripay.co.id" target="_blank">Tripay Dashboard</a></li>
                                                    <li>Buka Settings → API</li>
                                                    <li>Copy API Key & Private Key</li>
                                                    <li>Support: VA, E-Wallet, Retail</li>
                                                </ul>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- MIDTRANS --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMidtrans">
                                <i class="bi bi-cash-coin me-2"></i><strong>Midtrans</strong>
                                @if(!empty($settings['midtrans_server_key']))
                                    <span class="badge bg-success ms-2">Configured</span>
                                @endif
                            </button>
                        </h2>
                        <div id="collapseMidtrans" class="accordion-collapse collapse" data-bs-parent="#paymentGatewayAccordion">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Server Key</label>
                                            <input type="text" name="midtrans_server_key" class="form-control" 
                                                   value="{{ old('midtrans_server_key', $settings['midtrans_server_key'] ?? '') }}"
                                                   placeholder="SB-Mid-server-...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Client Key</label>
                                            <input type="text" name="midtrans_client_key" class="form-control" 
                                                   value="{{ old('midtrans_client_key', $settings['midtrans_client_key'] ?? '') }}"
                                                   placeholder="SB-Mid-client-...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mode</label>
                                            <select name="midtrans_mode" class="form-select">
                                                <option value="sandbox" {{ ($settings['midtrans_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                                <option value="production" {{ ($settings['midtrans_mode'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light p-3 rounded">
                                            <h6><i class="bi bi-book me-1"></i>Panduan</h6>
                                            <small class="text-muted">
                                                <ul class="ps-3 mb-0">
                                                    <li>Login ke <a href="https://dashboard.midtrans.com" target="_blank">Midtrans Dashboard</a></li>
                                                    <li>Buka Settings → Access Keys</li>
                                                    <li>Copy Server Key & Client Key</li>
                                                    <li>Support: VA, E-Wallet, Credit Card</li>
                                                </ul>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DUITKU --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDuitku">
                                <i class="bi bi-qr-code me-2"></i><strong>Duitku (QRIS)</strong>
                                @if(!empty($settings['duitku_merchant_code']))
                                    <span class="badge bg-success ms-2">Configured</span>
                                @endif
                            </button>
                        </h2>
                        <div id="collapseDuitku" class="accordion-collapse collapse" data-bs-parent="#paymentGatewayAccordion">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Merchant Code</label>
                                            <input type="text" name="duitku_merchant_code" class="form-control" 
                                                   value="{{ old('duitku_merchant_code', $settings['duitku_merchant_code'] ?? '') }}"
                                                   placeholder="D1234">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">API Key</label>
                                            <input type="text" name="duitku_api_key" class="form-control" 
                                                   value="{{ old('duitku_api_key', $settings['duitku_api_key'] ?? '') }}"
                                                   placeholder="API Key">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mode</label>
                                            <select name="duitku_mode" class="form-select">
                                                <option value="sandbox" {{ ($settings['duitku_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                                <option value="production" {{ ($settings['duitku_mode'] ?? '') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light p-3 rounded">
                                            <h6><i class="bi bi-book me-1"></i>Panduan</h6>
                                            <small class="text-muted">
                                                <ul class="ps-3 mb-0">
                                                    <li>Login ke <a href="https://duitku.com" target="_blank">Duitku Dashboard</a></li>
                                                    <li>Buka Settings → API</li>
                                                    <li>Copy Merchant Code & API Key</li>
                                                    <li>Support: QRIS, VA, E-Wallet</li>
                                                </ul>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- SAVE BUTTON --}}
        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-success btn-lg px-5">
                    <i class="bi bi-save me-2"></i>Simpan Semua Pengaturan
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Modal Tambah Template WA --}}
<div class="modal fade" id="modalAddWaTemplate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('settings.whatsapp.templates.store') }}" class="modal-content">
            @csrf
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Template WhatsApp</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           placeholder="Contoh: Pengingat Tagihan Jatuh Tempo">
                </div>
                <div class="mb-3">
                    <label class="form-label">Isi Pesan <span class="text-danger">*</span></label>
                    <textarea name="content" class="form-control" rows="6" required
                              placeholder="Halo {customer_name}, tagihan internet Anda jatuh tempo pada {due_date}..."></textarea>
                    <small class="text-muted">
                        <i class="bi bi-lightbulb me-1"></i>
                        Gunakan placeholder seperti <code>{customer_name}</code>, <code>{invoice_number}</code>, dll.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Simpan Template
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Edit Template WA --}}
<div class="modal fade" id="modalEditWaTemplate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="formEditTemplate" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Template WhatsApp</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_template_id" name="template_id">
                <div class="mb-3">
                    <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                    <input type="text" id="edit_template_name" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Isi Pesan <span class="text-danger">*</span></label>
                    <textarea id="edit_template_content" name="content" class="form-control" rows="6" required></textarea>
                    <small class="text-muted">
                        <i class="bi bi-lightbulb me-1"></i>
                        Gunakan placeholder seperti <code>{customer_name}</code>, <code>{invoice_number}</code>, dll.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Template
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    
    // ==================== TEMPLATE WHATSAPP ====================
    let selectedTemplateContent = '';

    // Select template (klik template item)
    document.querySelectorAll('.template-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Jangan trigger jika klik button edit/delete
            if (e.target.closest('.btn-edit-template') || e.target.closest('.btn-delete-template')) {
                return;
            }
            
            // Remove active dari semua
            document.querySelectorAll('.template-item').forEach(el => {
                el.classList.remove('active');
            });
            
            // Add active ke yang dipilih
            this.classList.add('active');
            
            // Tampilkan content
            const content = this.getAttribute('data-content');
            selectedTemplateContent = content;
            document.getElementById('wa_template_preview').value = content;
        });
    });

    // Use template untuk test
    const btnUseTemplate = document.getElementById('btnUseTemplate');
    if (btnUseTemplate) {
        btnUseTemplate.addEventListener('click', function() {
            if (!selectedTemplateContent) {
                alert('Pilih template terlebih dahulu');
                return;
            }
            
            // Copy ke field test message
            document.getElementById('wa_test_message').value = selectedTemplateContent;
            
            // Scroll ke section test
            document.getElementById('wa_test_message').scrollIntoView({ behavior: 'smooth', block: 'center' });
            document.getElementById('wa_test_message').focus();
            
            // Show success feedback
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Template Digunakan!';
            this.classList.replace('btn-primary', 'btn-success');
            
            setTimeout(() => {
                this.innerHTML = originalText;
                this.classList.replace('btn-success', 'btn-primary');
            }, 2000);
        });
    }

    // Edit template
    document.querySelectorAll('.btn-edit-template').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const content = this.getAttribute('data-content');
            
            document.getElementById('edit_template_id').value = id;
            document.getElementById('edit_template_name').value = name;
            document.getElementById('edit_template_content').value = content;
            
            // Update form action
            const form = document.getElementById('formEditTemplate');
            form.action = '{{ route("settings.whatsapp.templates.update", ":id") }}'.replace(':id', id);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('modalEditWaTemplate'));
            modal.show();
        });
    });

    // Delete template
    document.querySelectorAll('.btn-delete-template').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            if (!confirm(`Yakin ingin menghapus template "${name}"?`)) {
                return;
            }
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("settings.whatsapp.templates.delete", ":id") }}'.replace(':id', id);
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            
            const methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            
            form.appendChild(csrfToken);
            form.appendChild(methodField);
            document.body.appendChild(form);
            form.submit();
        });
    });

    // ==================== SEND TEST MESSAGE ====================
    const btnSendTest = document.getElementById('btnSendTest');
    if (btnSendTest) {
        btnSendTest.addEventListener('click', async function() {
            const phone = document.getElementById('wa_test_number').value;
            const message = document.getElementById('wa_test_message').value;
            
            if (!phone || !message) {
                alert('Nomor dan pesan harus diisi');
                return;
            }
            
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengirim...';
            
            try {
                const response = await fetch('{{ route('settings.whatsapp.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        wa_test_number: phone,
                        wa_test_message: message
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Terkirim!';
                    alert('✅ Pesan berhasil dikirim ke ' + phone);
                } else {
                    throw new Error(result.message || 'Gagal mengirim pesan');
                }
            } catch (error) {
                alert('❌ Error: ' + error.message);
            } finally {
                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = originalText;
                }, 3000);
            }
        });
    }

    // ==================== WA GATEWAY QR ====================
    let autoRefreshInterval = null;
    const AUTO_REFRESH_INTERVAL = 60000; // 1 menit

    async function fetchJsonSafe(url) {
        try {
            const res = await fetch(url, {headers: {'Accept': 'application/json'}});
            if (!res.ok) {
                throw new Error('HTTP ' + res.status + ' ' + res.statusText);
            }
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return await res.json();
            }
            throw new Error('Response bukan JSON');
        } catch (error) {
            console.error('fetchJsonSafe error:', error);
            throw error;
        }
    }

    function showStatus(data) {
        const badge = document.getElementById('wa-status-badge');
        const text = document.getElementById('wa-status-text');
        const qrBoxContainer = document.getElementById('qr-box-container');

        if (!badge || !text) return;

        if (!data || data.connected === false) {
            badge.className = 'badge bg-danger me-2 px-3 py-2';
            badge.innerHTML = '<i class="bi bi-x-circle me-1"></i>DISCONNECTED';
            text.textContent = 'Belum terhubung.';
            
            // Show QR box when disconnected
            if (qrBoxContainer) {
                qrBoxContainer.style.display = 'block';
            }
            
            startAutoRefresh();
            return;
        }

        badge.className = 'badge bg-success me-2 px-3 py-2';
        badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>CONNECTED';
        text.textContent = 'Terhubung dan siap digunakan.';
        
        // Hide entire QR box when connected
        if (qrBoxContainer) {
            qrBoxContainer.style.display = 'none';
        }
        
        stopAutoRefresh();
    }

    function showQr(res) {
        const img = document.getElementById('wa-qr-img');
        if (!img) return;

        if (!res || !res.success || !res.dataUrl) {
            // Show placeholder
            img.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='250' height='250'%3E%3Crect width='250' height='250' fill='%23f0f0f0'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='Arial' font-size='14' fill='%23999'%3ENo QR Available%3C/text%3E%3C/svg%3E";
            return;
        }

        img.style.display = 'block';
        img.src = res.dataUrl;
    }

    function startAutoRefresh() {
        if (autoRefreshInterval) return;
        
        console.log('🔄 Auto refresh QR dimulai (setiap 1 menit)');
        autoRefreshInterval = setInterval(async () => {
            console.log('♻️ Auto refresh QR code...');
            await reloadQr(true);
        }, AUTO_REFRESH_INTERVAL);
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            console.log('⏹️ Auto refresh QR dihentikan (sudah connected)');
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }

    async function reloadQr(silent = false) {
        try {
            const status = await fetchJsonSafe('{{ route('wa-gateway.status') }}');
            showStatus(status);

            if (status.connected) {
                stopAutoRefresh();
                return;
            }

            const qr = await fetchJsonSafe('{{ route('wa-gateway.qr') }}');
            if (!qr.success) {
                throw new Error(qr.message || 'Gagal mengambil QR dari gateway.');
            }
            showQr(qr);
        } catch (e) {
            console.error('Error reload QR:', e);
            if (!silent) {
                alert('Gagal mengambil QR dari gateway. Pastikan WhatsApp Gateway berjalan dengan baik.');
            }
        }
    }

    // ==================== RECONNECT FUNCTION ====================
    async function reconnectWhatsApp() {
        const btn = document.getElementById('btnReconnect');
        const originalHtml = btn.innerHTML;
        
        try {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Reconnecting...';
            
            console.log('🔄 Calling reconnect API...');
            const res = await fetch('/wa-gateway/reconnect', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            
            const data = await res.json();
            
            if (data.success) {
                alert('✅ WhatsApp Gateway berhasil di-reconnect! Silakan tunggu beberapa detik dan scan QR baru.');
                console.log('✅ Reconnect success');
                
                // Wait 3 seconds then reload QR
                setTimeout(() => {
                    reloadQr();
                }, 3000);
            } else {
                throw new Error(data.message || 'Reconnect failed');
            }
            
        } catch (e) {
            console.error('❌ Reconnect error:', e);
            alert('❌ Gagal reconnect: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // Button handlers
    const btnScanQr = document.getElementById('btnScanQr');
    const btnReconnect = document.getElementById('btnReconnect');
    
    if (btnScanQr) {
        btnScanQr.addEventListener('click', async function() {
            const originalHtml = this.innerHTML;
            try {
                this.disabled = true;
                this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Loading...';
                await reloadQr();
            } catch(e) {
                console.error('Error scan QR:', e);
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    }
    
    if (btnReconnect) {
        btnReconnect.addEventListener('click', async function() {
            if (confirm('🔄 Reconnect akan memutuskan sesi WhatsApp saat ini dan membuat QR baru.\n\nLanjutkan?')) {
                await reconnectWhatsApp();
            }
        });
    }

    // Init on page load
    reloadQr();

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        stopAutoRefresh();
    });
})();
</script>
@endpush
