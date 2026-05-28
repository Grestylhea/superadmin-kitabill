@extends('layouts.admin')

@section('title', 'Customer Management')

@section('content')
<div class="container-fluid px-4 py-4">
    
    {{-- Statistics Cards - Modern Design --}}
    <div class="row g-3 mb-4" id="stats-cards">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small fw-semibold text-uppercase">Total Customers</p>
                            <h2 class="mb-0 fw-bold" id="stat-total">{{ $customers->total() }}</h2>
                        </div>
                        <div class="opacity-75">
                            <i class="bi bi-people fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small fw-semibold text-uppercase">Online</p>
                            <h2 class="mb-0 fw-bold">
                                <span id="stat-online">0</span>
                                <small class="fs-6 text-white-50">(<span id="stat-online-pct">0</span>%)</small>
                            </h2>
                        </div>
                        <div class="opacity-75">
                            <i class="bi bi-wifi fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small fw-semibold text-uppercase">Offline</p>
                            <h2 class="mb-0 fw-bold">
                                <span id="stat-offline">0</span>
                                <small class="fs-6 text-white-50">(<span id="stat-offline-pct">0</span>%)</small>
                            </h2>
                        </div>
                        <div class="opacity-75">
                            <i class="bi bi-wifi-off fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small fw-semibold text-uppercase">Isolir</p>
                            <h2 class="mb-0 fw-bold">
                                <span id="stat-suspended">0</span>
                                <small class="fs-6 text-white-50">(<span id="stat-suspended-pct">0</span>%)</small>
                            </h2>
                        </div>
                        <div class="opacity-75">
                            <i class="bi bi-shield-exclamation fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Card - Modern Design --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-people text-primary me-2"></i>Daftar Customer
                    </h5>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-success btn-sm" id="btn-manual-sync" title="Sync sekarang dari Mikrotik">
                        <i class="bi bi-arrow-clockwise"></i> Sync Sekarang
                    </button>
                    <span id="sync-countdown" class="text-muted small ms-2" style="min-width: 30px; font-weight: 600;">
                        <span id="countdown-value">5</span>s
                    </span>
                    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Tambah Customer
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            {{-- Filters --}}
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <input type="text" class="form-control" id="search" placeholder="🔍 Cari customer...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filter-status">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="suspended">Isolir/Belum Bayar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filter-online">
                        <option value="">Online/Offline</option>
                        <option value="1">Online</option>
                        <option value="0">Offline</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filter-connection">
                        <option value="">Semua Tipe Koneksi</option>
                        <option value="pppoe_direct">PPPoE Direct</option>
                        <option value="pppoe_mikrotik">PPPoE via MikroTik</option>
                        <option value="static_ip">Static IP</option>
                        <option value="hotspot">Hotspot</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filter-package">
                        <option value="">Semua Package</option>
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm w-100" id="btn-auto-refresh" style="position: relative; padding-right: 2rem;">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        <span id="auto-refresh-label">Auto Sync</span>
                        <span class="position-absolute top-50 end-0 translate-middle-y me-2" id="auto-refresh-indicator">
                            <i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                        </span>
                    </button>
                </div>
            </div>

            {{-- Loading Overlay --}}
            <div id="loading-overlay" style="display: none; position: relative; min-height: 300px;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                    <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted">Memuat data...</p>
                </div>
            </div>

            {{-- Customer Table --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="customers-table">
                    <thead class="table-light">
                        <tr>
                            <th>Customer Code</th>
                            <th style="width: 100px;">Koneksi</th>
                            <th>Nama</th>
                            <th>Phone</th>
                            <th>Package</th>
                            <th>Tipe Koneksi</th>
                            <th style="width: 120px;">Status</th>
                            <th>Billing Date</th>
                            <th class="text-center" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="customers-tbody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Memuat data customer...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div id="pagination-info" class="text-muted">
                    <small>Showing 0 to 0 of 0 results</small>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination-links"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

{{-- Toast Notification --}}
<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div id="toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toast-message"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<!-- ✅ Load Pusher JS dari CDN - HARUS di-load PERTAMA -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
    // ✅ Pastikan Pusher tersedia secara global
    console.log('✅ Pusher JS loaded:', typeof Pusher !== 'undefined');
</script>
@endpush

@push('styles')
<style>
    /* Modern Action Buttons */
    .btn-action {
        padding: 0.35rem 0.5rem !important;
        font-size: 0.875rem !important;
        min-width: 36px;
        border-radius: 6px;
        transition: all 0.2s ease;
        border-width: 1.5px;
    }
    
    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .btn-action:active {
        transform: translateY(0);
    }
    
    /* Modern Table with Zebra Striping */
    .table-hover tbody tr {
        transition: all 0.2s ease;
    }
    
    /* Zebra striping - alternating row colors */
    .table-hover tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    
    .table-hover tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }
    
    .table-hover tbody tr:hover {
        background-color: #e3f2fd !important;
        transform: scale(1.001);
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    
    /* Modern Badges */
    .badge {
        padding: 0.4em 0.75em;
        font-size: 0.8em;
        font-weight: 500;
        border-radius: 6px;
        letter-spacing: 0.3px;
    }
    
    #loading-overlay {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(3px);
        z-index: 10;
    }
    
    /* ✅ ONLINE BADGE - Hijau dengan animasi */
    .status-online {
        animation: pulse-green 2s infinite;
        background-color: #28a745 !important;
    }

    @keyframes pulse-green {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    /* ✅ OFFLINE BADGE - MERAH */
    .status-offline {
        background-color: #dc3545 !important;
    }
    
    /* 🔄 AUTO SYNC BUTTON - Seperti Saklar Lampu */
    #btn-auto-refresh {
        transition: all 0.3s ease;
        font-weight: 600;
        border-width: 2px;
    }
    
    #btn-auto-refresh:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    #btn-auto-refresh:active {
        transform: scale(0.98);
    }
    
    /* Indicator Circle - Seperti LED pada saklar */
    #auto-refresh-indicator {
        font-size: 10px;
    }
    
    #auto-refresh-indicator.text-danger {
        animation: pulse-indicator 1.5s ease-in-out infinite;
    }
    
    @keyframes pulse-indicator {
        0%, 100% {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }
        50% {
            opacity: 0.4;
            transform: translateY(-50%) scale(1.3);
        }
    }
    
    /* Glow effect saat ON */
    #btn-auto-refresh.btn-success {
        box-shadow: 0 0 15px rgba(40, 167, 69, 0.4);
    }
    
    /* Manual Sync Button */
    #btn-manual-sync {
        font-weight: 600;
    }
    
    #btn-manual-sync:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Spin animation untuk loading */
    .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    /* Label emphasis */
    #auto-refresh-label strong {
        font-weight: 700;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    console.log('✅ Customer Management Loaded!');
    
    let autoRefreshInterval = null;
    let animationFrameId = null; // ✅ Store animationFrameId di scope global
    let countdownInterval = null; // ✅ Countdown timer interval
    let countdownValue = 5; // ✅ Countdown value (5 detik)
    let currentPage = 1;
    let isFirstLoad = true;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Load data on first load
    if (isFirstLoad) {
        isFirstLoad = false;
    }

    // Load data
    loadCustomers();
    loadStats();

    let searchTimeout;
    $('#search').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadCustomers(1, true); // Reset ke page 1 dan scroll ke atas
        }, 500);
    });

    $('#filter-status, #filter-online, #filter-connection, #filter-package').on('change', function() {
        currentPage = 1;
        loadCustomers(1, true); // Reset ke page 1 dan scroll ke atas
    });


    // Auto-refresh toggle state
    let autoRefreshActive = localStorage.getItem('autoRefreshEnabled') === 'true';
    updateAutoRefreshButton();
    
    if (autoRefreshActive) {
        startAutoRefresh();
        startCountdown(); // ✅ Start countdown timer
    }
    
    // ✅ FIX: Handle visibility change untuk desktop
    // ✅ Browser throttle timer ketika tab tidak aktif, restart ketika tab aktif kembali
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && autoRefreshActive) {
            // Tab kembali aktif, restart auto refresh
            console.log('🔄 Tab aktif kembali, restart auto sync...');
            startAutoRefresh();
            startCountdown(); // ✅ Restart countdown juga
        }
    });
    
    // ✅ FIX: Handle window focus untuk desktop
    $(window).on('focus', function() {
        if (autoRefreshActive) {
            console.log('🔄 Window focus, restart auto sync...');
            startAutoRefresh();
            startCountdown(); // ✅ Restart countdown juga
        }
    });

    // ✅ Manual Sync Button
    $('#btn-manual-sync').on('click', function() {
        const btn = $(this);
        const originalHtml = btn.html();
        
        // Disable button dan show loading
        btn.prop('disabled', true);
        btn.html('<i class="bi bi-arrow-clockwise spin"></i> Syncing...');
        
        triggerSyncStatus(false).then((response) => {
            if (response && response.success) {
                showToast('✅ Sync berhasil! ' + (response.message || ''), 'success');
                // Refresh data setelah sync
                loadCustomers(currentPage, false);
                loadStats();
            } else {
                showToast('⚠️ Sync: ' + (response?.message || 'Gagal'), 'warning');
            }
        }).catch((error) => {
            showToast('❌ Sync gagal: ' + (error.responseJSON?.message || error.statusText || 'Error'), 'danger');
        }).always(() => {
            // Re-enable button
            btn.prop('disabled', false);
            btn.html(originalHtml);
        });
    });

    $('#btn-auto-refresh').on('click', function() {
        autoRefreshActive = !autoRefreshActive;
        
        if (autoRefreshActive) {
            localStorage.setItem('autoRefreshEnabled', 'true');
            startAutoRefresh();
            startCountdown(); // ✅ Start countdown timer
            updateAutoRefreshButton();
            showToast('✅ Auto Sync AKTIF - Data akan disinkronkan otomatis setiap 5 detik', 'success');
        } else {
            localStorage.setItem('autoRefreshEnabled', 'false');
            stopAutoRefresh();
            stopCountdown(); // ✅ Stop countdown timer
            updateAutoRefreshButton();
            showToast('⏸️ Auto Sync NONAKTIF - Sinkronisasi otomatis dihentikan', 'info');
        }
    });
    
    function updateAutoRefreshButton() {
        const btn = $('#btn-auto-refresh');
        const indicator = $('#auto-refresh-indicator');
        const label = $('#auto-refresh-label');
        
        if (autoRefreshActive) {
            // ON state - Hijau dengan indicator merah beranimasi
            btn.removeClass('btn-outline-secondary').addClass('btn-success text-white');
            indicator.removeClass('text-secondary').addClass('text-danger');
            indicator.css('animation', 'pulse-indicator 1.5s ease-in-out infinite');
            label.html('<strong>Auto Sync</strong>');
            btn.attr('title', 'Klik untuk MATIKAN auto sync (setiap 5 detik)');
        } else {
            // OFF state - Abu-abu tanpa animasi
            btn.removeClass('btn-success text-white').addClass('btn-outline-secondary');
            indicator.removeClass('text-danger').addClass('text-secondary');
            indicator.css('animation', 'none');
            label.html('Auto Sync');
            btn.attr('title', 'Klik untuk AKTIFKAN auto sync (setiap 15 detik)');
        }
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        let lastSyncTime = 0;
        const minSyncInterval = 3000; // Minimum 3 detik antar sync (throttle)
        
        // ✅ FIX: Gunakan requestAnimationFrame untuk konsistensi di desktop dan mobile
        // ✅ Browser tidak akan throttle requestAnimationFrame seperti setInterval
        // Note: animationFrameId sudah dideklarasikan di scope global
        let lastRunTime = 0;
        const syncInterval = 5000; // 5 detik
        
        function runAutoSync() {
            if (!autoRefreshActive) {
                return;
            }
            
            const now = Date.now();
            const timeSinceLastSync = now - lastSyncTime;
            const timeSinceLastRun = now - lastRunTime;
            
            // ✅ THROTTLE: Skip jika sync baru saja berjalan
            if (timeSinceLastSync < minSyncInterval) {
                const remaining = Math.ceil((minSyncInterval - timeSinceLastSync) / 1000);
                // console.log(`⏳ Sync terlalu cepat, tunggu ${remaining} detik lagi...`);
            } else if (timeSinceLastRun >= syncInterval) {
                // ✅ JALANKAN SYNC jika sudah waktunya
                console.log('🔄 Auto sync dari Mikrotik (setiap 5 detik)...');
                lastSyncTime = now;
                lastRunTime = now;
                countdownValue = 5; // ✅ Reset countdown saat sync berjalan
                updateCountdownDisplay();
                
                // ✅ TRIGGER SYNC dari Mikrotik (/ppp/active) dan update database
                // ✅ Updates akan diterima via WebSocket (tidak perlu refresh manual)
                triggerSyncStatus(true).then((response) => {
                    // Jika throttled, tidak update lastSyncTime
                    if (response && response.throttled) {
                        lastSyncTime = 0; // Reset untuk retry
                    }
                }).catch(() => {
                    // Jika sync gagal, tetap refresh data yang ada sebagai fallback
                    console.warn('⚠️ Sync gagal, refresh data sebagai fallback');
                    loadCustomers(currentPage, false);
                    loadStats();
                });
            }
            
            // ✅ Continue dengan requestAnimationFrame
            animationFrameId = requestAnimationFrame(runAutoSync);
        }
        
        // ✅ Start dengan requestAnimationFrame
        animationFrameId = requestAnimationFrame(runAutoSync);
        
        // ✅ Fallback: Tetap gunakan setInterval sebagai backup
        autoRefreshInterval = setInterval(() => {
            if (!autoRefreshActive) {
                return;
            }
            
            const now = Date.now();
            const timeSinceLastSync = now - lastSyncTime;
            
            // ✅ THROTTLE: Skip jika sync baru saja berjalan
            if (timeSinceLastSync < minSyncInterval) {
                return;
            }
            
            console.log('🔄 Auto sync dari Mikrotik (setiap 5 detik) [fallback]...');
            lastSyncTime = now;
            countdownValue = 5; // ✅ Reset countdown saat sync berjalan
            updateCountdownDisplay();
            
            triggerSyncStatus(true).then((response) => {
                if (response && response.throttled) {
                    lastSyncTime = 0;
                }
            }).catch(() => {
                console.warn('⚠️ Sync gagal, refresh data sebagai fallback');
                loadCustomers(currentPage, false);
                loadStats();
            });
        }, 5000);
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
        // ✅ Cancel requestAnimationFrame jika ada
        if (animationFrameId !== null) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
    }
    
    // ✅ Countdown timer functions
    function startCountdown() {
        stopCountdown();
        countdownValue = 5; // Reset ke 5
        updateCountdownDisplay();
        
        countdownInterval = setInterval(() => {
            countdownValue--;
            updateCountdownDisplay();
            
            if (countdownValue <= 0) {
                countdownValue = 5; // Reset ke 5
            }
        }, 1000); // Update setiap 1 detik
    }
    
    function stopCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        countdownValue = 5;
        updateCountdownDisplay();
    }
    
    function updateCountdownDisplay() {
        const countdownElement = $('#countdown-value');
        if (countdownElement.length) {
            countdownElement.text(countdownValue);
        }
    }

    // ✅ Fungsi untuk trigger sync status (background, tidak blocking)
    // ✅ Return Promise untuk bisa di-chain
    function triggerSyncStatus(silent = false) {
        return $.ajax({
            url: '{{ route("customers.syncStatus") }}',
            type: 'GET',
            timeout: 90000, // 90 detik timeout (sync mungkin butuh waktu untuk membaca data dari Mikrotik)
            success: function(response) {
                if (response.success) {
                    if (!silent) {
                        showToast('✅ Sync status berhasil', 'success');
                    }
                    console.log('✅ Sync status completed', response);
                    
                    // Update stats jika ada di response
                    if (response.stats) {
                        updateStats(response.stats);
                    }
                } else {
                    if (!silent) {
                        showToast('⚠️ Sync status: ' + (response.message || 'Gagal'), 'warning');
                    }
                    console.warn('⚠️ Sync status failed:', response);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Sync gagal';
                if (status === 'timeout') {
                    errorMessage = 'Sync gagal: timeout (membaca data dari Mikrotik membutuhkan waktu lebih lama)';
                } else if (xhr.status === 0) {
                    errorMessage = 'Sync gagal: koneksi terputus atau timeout';
                } else {
                    errorMessage = 'Sync gagal: ' + (error || 'Error tidak diketahui');
                }
                
                if (!silent) {
                    showToast('❌ ' + errorMessage, 'danger');
                }
                console.warn('⚠️ Sync status failed (non-blocking)', {
                    status: status,
                    error: error,
                    xhr: xhr
                });
            }
        });
    }

    function loadCustomers(page = null, scrollToTop = true) {
        // ✅ Jika page tidak di-set, gunakan currentPage (untuk auto sync)
        const targetPage = page !== null ? page : currentPage;
        
        showLoading(true);

        const params = {
            page: targetPage,
            search: $('#search').val(),
            status: $('#filter-status').val(),
            is_online: $('#filter-online').val(),
            connection_type: $('#filter-connection').val(),
            package_id: $('#filter-package').val()
        };

        $.ajax({
            url: '{{ route("customers.ajax.list") }}',
            type: 'GET',
            data: params,
            success: function(response) {
                console.log('AJAX Response:', response); // Debug
                if (response.success) {
                    // ✅ Simpan scroll position sebelum update
                    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
                    
                    console.log('Customers data:', response.data); // Debug
                    console.log('Customers count:', response.data.length); // Debug
                    
                    renderCustomers(response.data);
                    renderPagination(response.pagination, response.links);
                    currentPage = response.pagination.current_page;
                    
                    // ✅ Restore scroll position jika auto sync (tidak scroll ke atas)
                    if (!scrollToTop) {
                        window.scrollTo(0, scrollPosition);
                    }
                } else {
                    console.error('Response success is false:', response);
                    showToast('Gagal memuat data customer', 'danger');
                }
            },
            error: function(xhr) {
                console.error('AJAX Error:', xhr);
                console.error('Response Text:', xhr.responseText);
                showToast('Gagal memuat data customer', 'danger');
            },
            complete: function() {
                showLoading(false);
            }
        });
    }

    function renderCustomers(customers) {
        const tbody = $('#customers-tbody');
        tbody.empty();

        console.log('renderCustomers called with:', customers); // Debug

        if (!customers || customers.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="bi bi-inbox text-muted mb-3 d-block" style="font-size: 4rem;"></i>
                        <p class="text-muted mb-0">Tidak ada customer ditemukan</p>
                    </td>
                </tr>
            `);
            return;
        }

        let renderedCount = 0;
        customers.forEach(customer => {
            renderedCount++;

            // ✅ KONEKSI BADGE - Online/Offline/Isolir
            // ✅ GUNAKAN STATUS SAJA: active = Online, terminated = Offline, suspended = Isolir
            let onlineIndicator;
            if (customer.status === 'suspended') {
                // Customer isolir - tampilkan badge Isolir
                onlineIndicator = '<span class="badge bg-warning text-dark"><i class="bi bi-shield-exclamation" style="font-size: 12px;"></i> Isolir</span>';
            } else if (customer.status === 'terminated') {
                // Customer terminated - tampilkan sebagai Offline
                onlineIndicator = '<span class="badge status-offline"><i class="bi bi-wifi-off" style="font-size: 12px;"></i> Offline</span>';
            } else {
                // Customer active - tampilkan Online
                onlineIndicator = '<span class="badge status-online"><i class="bi bi-wifi" style="font-size: 12px;"></i> Online</span>';
            }

            // ✅ STATUS BADGE - Aktif/Isolir (untuk kolom Status)
            // ✅ TERMINATED ditampilkan sebagai "Aktif" (tidak ada badge terminated)
            const statusBadge = customer.status === 'suspended'
                ? '<span class="badge bg-warning text-dark">Isolir</span>'
                : '<span class="badge bg-success">Aktif</span>';

            // ✅ ACTION BUTTONS - Modern Design
            const actionButtons = `
                <div class="d-flex gap-1 justify-content-center">
                    <a href="/customers/${customer.id}" class="btn btn-sm btn-outline-info btn-action" title="Detail" style="min-width: 36px;">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a href="/customers/${customer.id}/edit" class="btn btn-sm btn-outline-warning btn-action" title="Edit" style="min-width: 36px;">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-danger btn-action btn-delete-customer" data-id="${customer.id}" data-name="${customer.name}" title="Hapus Customer" style="min-width: 36px;">
                        <i class="bi bi-trash"></i>
                    </button>
                    ${customer.status === 'active' ? `
                        <button class="btn btn-sm btn-outline-secondary btn-action btn-suspend" data-id="${customer.id}" title="Isolir" style="min-width: 36px;">
                            <i class="bi bi-ban"></i>
                        </button>
                    ` : ''}
                    ${customer.status === 'suspended' ? `
                        <button class="btn btn-sm btn-outline-success btn-action btn-activate" data-id="${customer.id}" title="Aktifkan" style="min-width: 36px;">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    ` : ''}
                </div>
            `;

            const packageName = customer.package ? customer.package.name : '-';
            const billingDate = formatDate(customer.next_billing_date);

            tbody.append(`
                <tr>
                    <td><strong class="text-primary">${customer.customer_code}</strong></td>
                    <td>${onlineIndicator}</td>
                    <td>
                        <div class="fw-semibold">${customer.name}</div>
                        <small class="text-muted">${customer.email}</small>
                    </td>
                    <td>${customer.phone || '-'}</td>
                    <td><span class="badge bg-info text-dark">${packageName}</span></td>
                    <td><small>${getConnectionTypeLabel(customer.connection_type)}</small></td>
                    <td>${statusBadge}</td>
                    <td><small>${billingDate}</small></td>
                    <td class="text-center">${actionButtons}</td>
                </tr>
            `);
        });

        console.log(`Rendered ${renderedCount} customers out of ${customers.length} total`); // Debug

        $('.btn-suspend').on('click', function() {
            if (confirm('Isolir customer ini?')) {
                suspendCustomer($(this).data('id'));
            }
        });

        $('.btn-activate').on('click', function() {
            if (confirm('Aktifkan customer ini?')) {
                activateCustomer($(this).data('id'));
            }
        });

        $('.btn-delete-customer').on('click', function() {
            const customerId = $(this).data('id');
            const customerName = $(this).data('name');
            
            if (confirm(`⚠️ HAPUS CUSTOMER: ${customerName}\n\nCustomer ini akan dihapus dari:\n- Database sistem\n- Mikrotik (PPPoE/Hotspot/Static IP)\n\nApakah Anda yakin ingin menghapus customer ini?`)) {
                deleteCustomer(customerId);
            }
        });
    }

    function renderPagination(pagination, links) {
        $('#pagination-info').html(
            `<small>Showing <strong>${pagination.from || 0}</strong> to <strong>${pagination.to || 0}</strong> of <strong>${pagination.total}</strong> results</small>`
        );

        const paginationLinks = $('#pagination-links');
        paginationLinks.empty();

        if (pagination.last_page <= 1) return;

        paginationLinks.append(`
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `);

        for (let i = 1; i <= pagination.last_page; i++) {
            if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                paginationLinks.append(`
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `);
            } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                paginationLinks.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        paginationLinks.append(`
            <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `);

        $('.page-link').on('click', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && !$(this).parent().hasClass('disabled')) {
                // ✅ Manual pagination click = scroll ke atas
                loadCustomers(page, true);
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
        });
    }

    function loadStats() {
        $.ajax({
            url: '{{ route("customers.ajax.stats") }}',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    $('#stat-total').text(stats.total);
                    $('#stat-online').text(stats.online);
                    $('#stat-online-pct').text(stats.online_percentage);
                    $('#stat-offline').text(stats.offline);
                    $('#stat-offline-pct').text(stats.offline_percentage);
                    $('#stat-suspended').text(stats.suspended);
                    $('#stat-suspended-pct').text(stats.suspended_percentage);
                }
            }
        });
    }


    function suspendCustomer(customerId) {
        $.ajax({
            url: `/customers/${customerId}/ajax/suspend`,
            type: 'POST',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    // ✅ Pertahankan halaman & scroll setelah suspend
                    loadCustomers(currentPage, false);
                    loadStats();
                }
            },
            error: function(xhr) {
                showToast(xhr.responseJSON?.message || 'Gagal suspend', 'danger');
            }
        });
    }

    function activateCustomer(customerId) {
        $.ajax({
            url: `/customers/${customerId}/ajax/activate`,
            type: 'POST',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    // ✅ Pertahankan halaman & scroll setelah activate
                    loadCustomers(currentPage, false);
                    loadStats();
                }
            },
            error: function(xhr) {
                showToast(xhr.responseJSON?.message || 'Gagal aktivasi', 'danger');
            }
        });
    }

    function deleteCustomer(customerId) {
        $.ajax({
            url: `/customers/${customerId}`,
            type: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            beforeSend: function() {
                showToast('🗑️ Menghapus customer...', 'info');
            },
            success: function(response) {
                showToast('✅ Customer berhasil dihapus!', 'success');
                // ✅ Pertahankan halaman & scroll setelah delete
                loadCustomers(currentPage, false);
                loadStats();
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'Gagal menghapus customer';
                showToast('❌ ' + errorMsg, 'danger');
            }
        });
    }

    function showLoading(show) {
        if (show) {
            $('#loading-overlay').show();
            $('#customers-table').css('opacity', '0.4');
        } else {
            $('#loading-overlay').hide();
            $('#customers-table').css('opacity', '1');
        }
    }

    function showToast(message, type = 'info') {
        const toast = $('#toast');
        const toastMessage = $('#toast-message');
        
        toast.removeClass('bg-success bg-danger bg-warning bg-info');
        toast.addClass(`bg-${type}`);
        toastMessage.text(message);
        
        new bootstrap.Toast(toast[0]).show();
    }

    function getConnectionTypeLabel(type) {
        const types = {
            'pppoe_direct': 'PPPoE Direct',
            'pppoe_mikrotik': 'PPPoE MikroTik',
            'static_ip': 'Static IP',
            'hotspot': 'Hotspot',
            'dhcp': 'DHCP'
        };
        return types[type] || type;
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    }

    // ✅ Update stats secara real-time
    function updateStats(stats) {
        const total = stats.total || 0;
        const online = stats.online || 0;
        const offline = stats.offline || 0;
        const suspended = stats.suspended || 0;

        $('#stat-total').text(total);
        $('#stat-online').text(online);
        $('#stat-offline').text(offline);
        $('#stat-suspended').text(suspended);

        if (total > 0) {
            $('#stat-online-pct').text(Math.round((online / total) * 100));
            $('#stat-offline-pct').text(Math.round((offline / total) * 100));
            $('#stat-suspended-pct').text(Math.round((suspended / total) * 100));
        }
    }

    // ✅ WEBSOCKET REAL-TIME SYNC - Laravel Echo + Pusher
    // ✅ Setup Laravel Echo untuk real-time updates
    function setupWebSocket() {
        // Cek apakah Pusher config tersedia
        const pusherKey = '{{ config("broadcasting.connections.pusher.key") }}';
        const pusherCluster = '{{ config("broadcasting.connections.pusher.options.cluster", "mt1") }}';

        // Jika Pusher tidak dikonfigurasi, skip WebSocket
        if (!pusherKey || pusherKey === '' || pusherKey === 'null' || pusherKey === null) {
            console.log('⚠️ WebSocket tidak dikonfigurasi. Menggunakan polling saja.');
            return;
        }

        // Wait untuk Pusher tersedia
        if (typeof Pusher === 'undefined') {
            console.log('⏳ Menunggu Pusher JS...');
            setTimeout(setupWebSocket, 500);
            return;
        }

        try {
            // ✅ Setup Laravel Echo dengan Pusher
            if (typeof Echo === 'undefined') {
                // Define Echo class jika belum ada
                window.Echo = function(options) {
                    this.broadcaster = options.broadcaster;
                    this.key = options.key;
                    this.cluster = options.cluster;
                    
                    // Initialize Pusher
                    this.pusher = new Pusher(options.key, {
                        cluster: options.cluster,
                        forceTLS: true,
                        encrypted: true,
                        disableStats: true,
                        enabledTransports: ['ws', 'wss'],
                    });
                    
                    this.connector = {
                        pusher: this.pusher
                    };
                    
                    // Channel method
                    this.channel = (channelName) => {
                        const channel = this.pusher.subscribe(channelName);
                        return {
                            listen: (eventName, callback) => {
                                channel.bind(eventName, callback);
                            }
                        };
                    };
                };
            }

            window.echo = new window.Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: pusherCluster,
            });

            console.log('✅ WebSocket initialized via Laravel Echo');

            // ✅ Listen untuk Customer Status Update
            window.echo.channel('customer-status-updates')
                .listen('customer.status.updated', (e) => {
                    console.log('🔄 Customer status updated via WebSocket:', e);
                    updateCustomerStatusInTable(e.customer, e.old_status, e.new_status);
                });

            // ✅ Listen untuk Customer Stats Update
            window.echo.channel('customer-stats-updates')
                .listen('customer.stats.updated', (e) => {
                    console.log('📊 Customer stats updated via WebSocket:', e);
                    updateStats(e.stats);
                });

            // ✅ Connection status monitoring
            window.echo.pusher.connection.bind('connected', () => {
                console.log('✅ WebSocket connected - Real-time updates aktif');
            });

            window.echo.pusher.connection.bind('disconnected', () => {
                console.warn('⚠️ WebSocket disconnected');
            });

            window.echo.pusher.connection.bind('error', (err) => {
                console.error('❌ WebSocket error:', err);
            });

        } catch (error) {
            console.error('❌ Error setting up WebSocket:', error);
        }
    }

    // ✅ Update customer status di table secara real-time via WebSocket
    function updateCustomerStatusInTable(customer, oldStatus, newStatus) {
        const row = $(`tr:has(td:contains('${customer.customer_code}'))`);
        
        if (row.length === 0) {
            // Customer tidak ada di halaman saat ini, refresh stats saja
            loadStats();
            return;
        }

        // ✅ Update status badge
        let onlineIndicator;
        if (customer.status === 'suspended') {
            onlineIndicator = '<span class="badge bg-warning text-dark"><i class="bi bi-shield-exclamation" style="font-size: 12px;"></i> Isolir</span>';
        } else if (customer.status === 'terminated') {
            onlineIndicator = '<span class="badge status-offline"><i class="bi bi-wifi-off" style="font-size: 12px;"></i> Offline</span>';
        } else {
            onlineIndicator = '<span class="badge status-online"><i class="bi bi-wifi" style="font-size: 12px;"></i> Online</span>';
        }

        const statusBadge = customer.status === 'suspended'
            ? '<span class="badge bg-warning text-dark">Isolir</span>'
            : '<span class="badge bg-success">Aktif</span>';

        // Update row dengan animasi
        row.find('td:eq(1)').html(onlineIndicator).css('transition', 'all 0.3s ease');
        row.find('td:eq(6)').html(statusBadge).css('transition', 'all 0.3s ease');
        
        // Highlight row untuk menunjukkan perubahan
        row.css('background-color', '#fff3cd');
        setTimeout(() => {
            row.css('background-color', '');
        }, 2000);

        // ✅ Update action buttons jika perlu
        const actionCell = row.find('td:last-child');
        const customerId = customer.id;
        
        // Rebuild action buttons berdasarkan status baru
        let actionButtons = `
            <div class="d-flex gap-1 justify-content-center">
                <a href="/customers/${customerId}" class="btn btn-sm btn-outline-info btn-action" title="Detail" style="min-width: 36px;">
                    <i class="bi bi-eye"></i>
                </a>
                <a href="/customers/${customerId}/edit" class="btn btn-sm btn-outline-warning btn-action" title="Edit" style="min-width: 36px;">
                    <i class="bi bi-pencil"></i>
                </a>
                <button class="btn btn-sm btn-outline-danger btn-action btn-delete-customer" data-id="${customerId}" data-name="${customer.name}" title="Hapus Customer" style="min-width: 36px;">
                    <i class="bi bi-trash"></i>
                </button>
        `;
        
        if (customer.status === 'active') {
            actionButtons += `
                <button class="btn btn-sm btn-outline-secondary btn-action btn-suspend" data-id="${customerId}" title="Isolir" style="min-width: 36px;">
                    <i class="bi bi-ban"></i>
                </button>
            `;
        } else if (customer.status === 'suspended') {
            actionButtons += `
                <button class="btn btn-sm btn-outline-success btn-action btn-activate" data-id="${customerId}" title="Aktifkan" style="min-width: 36px;">
                    <i class="bi bi-check-lg"></i>
                </button>
            `;
        }
        
        actionButtons += `</div>`;
        actionCell.html(actionButtons);

        // Re-attach event handlers
        $('.btn-suspend').off('click').on('click', function() {
            if (confirm('Isolir customer ini?')) {
                suspendCustomer($(this).data('id'));
            }
        });

        $('.btn-activate').off('click').on('click', function() {
            if (confirm('Aktifkan customer ini?')) {
                activateCustomer($(this).data('id'));
            }
        });

        $('.btn-delete-customer').off('click').on('click', function() {
            const customerId = $(this).data('id');
            const customerName = $(this).data('name');
            if (confirm(`⚠️ HAPUS CUSTOMER: ${customerName}\n\nCustomer ini akan dihapus dari:\n- Database sistem\n- Mikrotik (PPPoE/Hotspot/Static IP)\n\nApakah Anda yakin ingin menghapus customer ini?`)) {
                deleteCustomer(customerId);
            }
        });

        // ✅ Update stats
        loadStats();

        // ✅ Show notification
        const statusText = {
            'active': 'Online',
            'terminated': 'Offline',
            'suspended': 'Isolir'
        };
        showToast(`Customer ${customer.customer_code} status berubah: ${statusText[oldStatus]} → ${statusText[newStatus]}`, 'info');
    }

    // ✅ Initialize WebSocket saat halaman load
    function initWebSocketAfterPusher() {
        if (typeof Pusher !== 'undefined') {
            setupWebSocket();
        } else {
            // Retry setelah 500ms jika Pusher belum ter-load
            setTimeout(initWebSocketAfterPusher, 500);
        }
    }

    $(document).ready(function() {
        // Delay sedikit untuk memastikan semua script sudah ter-load
        setTimeout(initWebSocketAfterPusher, 100);
    });
});
</script>

@endpush
