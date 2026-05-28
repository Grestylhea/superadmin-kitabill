@extends('layouts.admin')

@section('title', 'Detail Customer')
@section('page-title', 'Detail Customer')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-person-circle text-primary me-2"></i>Informasi Customer
                        </h5>
                        <div class="d-flex gap-2">
                            @can('edit_customer')
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            @endcan
                            @if($customer->package && $customer->package->custom_expire_day)
                            <form action="{{ route('customers.sync-billing-date', $customer) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-outline-info btn-sm" onclick="return confirm('Sync billing date ke tanggal {{ $customer->package->custom_expire_day }} sesuai paket?')">
                                    <i class="bi bi-arrow-repeat"></i> Sync Billing Date
                                </button>
                            </form>
                            @endif
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </form>
                            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body px-4 py-4">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td width="180" class="fw-semibold text-muted">Customer Code</td>
                                    <td><span class="badge bg-secondary">{{ $customer->customer_code }}</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Nama Lengkap</td>
                                    <td class="fw-medium">{{ $customer->name }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Email</td>
                                    <td>{{ $customer->email ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">No. Telepon</td>
                                    <td>{{ $customer->phone }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">No. KTP</td>
                                    <td>{{ $customer->id_card_number ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Status</td>
                                    <td><span class="badge {{ $customer->getStatusBadgeClass() }}">{{ ucfirst($customer->status) }}</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td width="180" class="fw-semibold text-muted">Alamat</td>
                                    <td>{{ $customer->address ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Koordinat GPS</td>
                                    <td><code class="text-muted">{{ $customer->latitude && $customer->longitude ? $customer->latitude . ', ' . $customer->longitude : '-' }}</code></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Tanggal Instalasi</td>
                                    <td>{{ $customer->installation_date?->format('d M Y') ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Next Billing</td>
                                    <td><span class="badge bg-info text-white">{{ $customer->next_billing_date?->format('d M Y') ?? '-' }}</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-muted">Teknisi</td>
                                    <td>{{ $customer->teknisi?->name ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold mb-2">
                            <i class="bi bi-geo-alt text-primary me-2"></i>Lokasi di Peta
                        </label>
                        <div id="mapView" style="height: 400px; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold mb-3 text-dark">
                        <i class="bi bi-router text-primary me-2"></i>Konfigurasi Koneksi
                    </h6>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td width="200" class="fw-semibold text-muted">Tipe Koneksi</td>
                            <td><span class="badge bg-info">{{ $customer->getConnectionTypeLabel() }}</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Paket</td>
                            <td class="fw-medium">{{ $customer->package?->name ?? '-' }} <small class="text-muted">({{ $customer->package?->getSpeedLabel() ?? '-' }})</small></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Router</td>
                            <td>{{ $customer->router?->name ?? '-' }} <small class="text-muted">({{ $customer->router?->ip_address ?? '-' }})</small></td>
                        </tr>

                        @if($customer->connection_type == 'pppoe_direct' || $customer->connection_type == 'pppoe_mikrotik')
                        <tr>
                            <td class="fw-semibold text-muted">PPPoE Username</td>
                            <td><code>{{ $customer->connection_config['username'] ?? $customer->customer_mikrotik_username ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">PPPoE Password</td>
                            <td><code>{{ $customer->connection_config['password'] ?? $customer->customer_mikrotik_password ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Status</td>
                            <td><span id="pppoe-status" class="text-muted">Memuat...</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Uptime</td>
                            <td><span id="pppoe-uptime" class="text-muted">Memuat...</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">IP Remote</td>
                            <td><code id="pppoe-remote-ip" class="text-muted">Memuat...</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Last Link Up</td>
                            <td><span id="pppoe-last-up" class="text-muted">Memuat...</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Last Link Down</td>
                            <td><span id="pppoe-last-down" class="text-muted">Memuat...</span></td>
                        </tr>
                        @endif

                        @if($customer->connection_type == 'static_ip')
                        <tr>
                            <td class="fw-semibold text-muted">IP Address</td>
                            <td><code>{{ $customer->connection_config['ip'] ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Subnet / Gateway</td>
                            <td><code>{{ $customer->connection_config['subnet'] ?? '-' }}</code> / <code>{{ $customer->connection_config['gateway'] ?? '-' }}</code></td>
                        </tr>
                        @endif

                        @if($customer->connection_type == 'pppoe_mikrotik')
                        <tr>
                            <td class="fw-semibold text-muted">Customer MikroTik IP</td>
                            <td><code>{{ $customer->customer_mikrotik_ip ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">MikroTik Username</td>
                            <td><code>{{ $customer->customer_mikrotik_username ?? '-' }}</code></td>
                        </tr>
                        @endif
                    </table>

                    @if($customer->connection_type == 'pppoe_direct' || $customer->connection_type == 'pppoe_mikrotik')
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3 text-dark">
                        <i class="bi bi-graph-up text-primary me-2"></i>Internet Usage
                    </h6>
                    <div id="pppoe-traffic-box" class="card border">
                        <div class="card-body">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Memuat...</span>
                                </div>
                                <p class="mt-2 text-muted">Memuat data penggunaan internet...</p>
                            </div>
                        </div>
                    </div>
                    <div id="pppoe-traffic-content" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Transmit (Tx)</h6>
                                        <div class="mb-2">
                                            <small class="text-muted">Bytes:</small>
                                            <div class="fw-bold" id="traffic-tx-bytes">-</div>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted">Packets:</small>
                                            <div class="fw-bold" id="traffic-tx-packets">-</div>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted">Drops:</small>
                                            <div class="fw-bold text-danger" id="traffic-tx-drops">-</div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Errors:</small>
                                            <div class="fw-bold text-danger" id="traffic-tx-errors">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-3">Receive (Rx)</h6>
                                        <div class="mb-2">
                                            <small class="text-muted">Bytes:</small>
                                            <div class="fw-bold" id="traffic-rx-bytes">-</div>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted">Packets:</small>
                                            <div class="fw-bold" id="traffic-rx-packets">-</div>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted">Drops:</small>
                                            <div class="fw-bold text-danger" id="traffic-rx-drops">-</div>
                                        </div>
                                        <div>
                                            <small class="text-muted">Errors:</small>
                                            <div class="fw-bold text-danger" id="traffic-rx-errors">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($customer->olt)
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3 text-dark">
                        <i class="bi bi-fiber-optic text-primary me-2"></i>Konfigurasi Fiber
                    </h6>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td width="200" class="fw-semibold text-muted">OLT</td>
                            <td>{{ $customer->olt->name }} <small class="text-muted">({{ $customer->olt->getOltTypeLabel() }})</small></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">ONT Serial Number</td>
                            <td><code>{{ $customer->ont_serial_number ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">PON Port</td>
                            <td><code>{{ $customer->pon_port ?? '-' }}</code></td>
                        </tr>
                    </table>
                    @endif

                    @if($customer->notes)
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3 text-dark">
                        <i class="bi bi-sticky text-primary me-2"></i>Catatan
                    </h6>
                    <div class="alert alert-light border">
                        <p class="mb-0">{{ $customer->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-lightning-charge text-primary me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @can('suspend_customer')
                        @if($customer->status === 'active')
                        <form action="{{ route('customers.suspend', $customer) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning w-100" onclick="return confirm('Suspend customer ini?')">
                                <i class="bi bi-ban"></i> Suspend Customer
                            </button>
                        </form>
                        @endif
                        @endcan

                        @can('activate_customer')
                        @if($customer->status === 'suspended')
                        <form action="{{ route('customers.activate', $customer) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-check-circle"></i> Activate Customer
                            </button>
                        </form>
                        @endif
                        @endcan

                        <button class="btn btn-outline-secondary w-100" disabled>
                            <i class="bi bi-graph-up"></i> View Usage
                            <small class="d-block text-muted" style="font-size: 0.7rem;">Coming Soon</small>
                        </button>
                        <button class="btn btn-outline-secondary w-100" disabled>
                            <i class="bi bi-receipt"></i> Create Invoice
                            <small class="d-block text-muted" style="font-size: 0.7rem;">Coming Soon</small>
                        </button>
                        <button class="btn btn-outline-secondary w-100" disabled>
                            <i class="bi bi-ticket"></i> Create Ticket
                            <small class="d-block text-muted" style="font-size: 0.7rem;">Coming Soon</small>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-clock-history text-primary me-2"></i>Timeline
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start mb-3">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                <i class="bi bi-plus-circle text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-0 fw-semibold">Terdaftar</p>
                            <small class="text-muted">{{ $customer->created_at->format('d M Y H:i') }}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                <i class="bi bi-pencil-square text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-0 fw-semibold">Last Update</p>
                            <small class="text-muted">{{ $customer->updated_at->format('d M Y H:i') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@push('scripts')
<!-- ✅ Load Pusher JS untuk WebSocket -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
function confirmDelete() {
    return confirm(
        `⚠️ HAPUS CUSTOMER: {{ $customer->name }} ({{ $customer->customer_code }})\n\n` +
        `Customer ini akan dihapus dari:\n` +
        `- Database sistem\n` +
        `- Mikrotik (PPPoE/Hotspot/Static IP)\n\n` +
        `Apakah Anda yakin ingin menghapus customer ini?`
    );
}

document.addEventListener('DOMContentLoaded', function () {
    const lat = {{ $customer->latitude ?? '-6.200000' }};
    const lon = {{ $customer->longitude ?? '106.816666' }};
    const map = L.map('mapView').setView([lat, lon], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Marker posisi customer
    L.marker([lat, lon]).addTo(map)
        .bindPopup("{{ $customer->name }}<br>{{ $customer->address ?? 'Belum diatur' }}")
        .openPopup();

    @if($customer->connection_type == 'pppoe_direct' || $customer->connection_type == 'pppoe_mikrotik')
    // Load PPPoE interface detail
    loadPPPoEInterfaceDetail();
    
    // Setup WebSocket untuk real-time traffic monitoring
    setupTrafficWebSocket();
    
    // Start traffic monitoring
    startTrafficMonitoring();
    @endif
});

@if($customer->connection_type == 'pppoe_direct' || $customer->connection_type == 'pppoe_mikrotik')
function formatBytes(bytes) {
    if (!bytes || bytes == 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function formatRate(bitsPerSecond) {
    if (!bitsPerSecond || bitsPerSecond == 0 || isNaN(bitsPerSecond)) return '0 bps';
    const k = 1000; // Rate menggunakan 1000 bukan 1024
    const sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
    if (bitsPerSecond < k) return bitsPerSecond + ' ' + sizes[0];
    const i = Math.floor(Math.log(bitsPerSecond) / Math.log(k));
    const idx = Math.min(i, sizes.length - 1);
    return Math.round(bitsPerSecond / Math.pow(k, idx) * 100) / 100 + ' ' + sizes[idx];
}

function formatNumber(num) {
    if (!num || num == 0) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function formatUptime(uptime) {
    if (!uptime) return '-';
    // Uptime format dari Mikrotik: "1d 2h 3m 4s" atau "2h 3m 4s"
    return uptime;
}

function formatDateTime(dateTime) {
    if (!dateTime || dateTime === '') return '-';
    // Format dari Mikrotik biasanya: "dec/12/2025 20:54:39"
    return dateTime;
}

function loadPPPoEInterfaceDetail() {
    const customerId = {{ $customer->id }};
    
    fetch(`/customers/${customerId}/pppoe-interface`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const detail = data.data;
                
                // Update konfigurasi koneksi
                // Update status online/offline
                const statusElement = document.getElementById('pppoe-status');
                if (detail.is_online || detail.running) {
                    statusElement.innerHTML = '<span class="badge bg-success">Online</span>';
                } else {
                    statusElement.innerHTML = '<span class="badge bg-danger">Offline</span>';
                }
                
                document.getElementById('pppoe-uptime').textContent = formatUptime(detail.uptime) || '-';
                document.getElementById('pppoe-remote-ip').textContent = detail.remote_address || '-';
                document.getElementById('pppoe-last-up').textContent = formatDateTime(detail.last_link_up_time) || '-';
                document.getElementById('pppoe-last-down').textContent = formatDateTime(detail.last_link_down_time) || '-';
                
                // Update traffic box jika ada data traffic
                if (detail.traffic) {
                    const traffic = detail.traffic;
                    
                    // Debug: log traffic data dengan detail
                    console.log('📊 Traffic data received:', traffic);
                    
                    // Update data (Rate sudah dihilangkan)
                    document.getElementById('traffic-tx-bytes').textContent = formatBytes(parseInt(traffic.tx_bytes || 0));
                    document.getElementById('traffic-tx-packets').textContent = formatNumber(parseInt(traffic.tx_packets || 0));
                    document.getElementById('traffic-tx-drops').textContent = formatNumber(parseInt(traffic.tx_drops || 0));
                    document.getElementById('traffic-tx-errors').textContent = formatNumber(parseInt(traffic.tx_errors || 0));
                    
                    document.getElementById('traffic-rx-bytes').textContent = formatBytes(parseInt(traffic.rx_bytes || 0));
                    document.getElementById('traffic-rx-packets').textContent = formatNumber(parseInt(traffic.rx_packets || 0));
                    document.getElementById('traffic-rx-drops').textContent = formatNumber(parseInt(traffic.rx_drops || 0));
                    document.getElementById('traffic-rx-errors').textContent = formatNumber(parseInt(traffic.rx_errors || 0));
                    
                    // Show traffic content, hide loading box
                    document.getElementById('pppoe-traffic-box').style.display = 'none';
                    document.getElementById('pppoe-traffic-content').style.display = 'block';
                } else {
                    // Jika tidak ada traffic data, tampilkan pesan
                    document.getElementById('pppoe-traffic-box').style.display = 'block';
                    document.getElementById('pppoe-traffic-box').querySelector('.card-body').innerHTML = 
                        '<p class="text-muted mb-0">Data penggunaan internet tidak tersedia. Customer mungkin sedang offline atau interface tidak running.</p>';
                    document.getElementById('pppoe-traffic-content').style.display = 'none';
                }
            } else {
                // Jika tidak ada data atau error
                document.getElementById('pppoe-status').innerHTML = '<span class="badge bg-danger">Offline</span>';
                document.getElementById('pppoe-uptime').textContent = '-';
                document.getElementById('pppoe-remote-ip').textContent = '-';
                document.getElementById('pppoe-last-up').textContent = '-';
                document.getElementById('pppoe-last-down').textContent = '-';
                
                document.getElementById('pppoe-traffic-box').style.display = 'block';
                document.getElementById('pppoe-traffic-box').querySelector('.card-body').innerHTML = 
                    '<p class="text-muted mb-0">' + (data.message || 'Customer sedang offline atau interface tidak ditemukan') + '</p>';
                document.getElementById('pppoe-traffic-content').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading PPPoE interface detail:', error);
            document.getElementById('pppoe-status').innerHTML = '<span class="badge bg-secondary">Error</span>';
            document.getElementById('pppoe-uptime').textContent = 'Error';
            document.getElementById('pppoe-remote-ip').textContent = 'Error';
            document.getElementById('pppoe-last-up').textContent = 'Error';
            document.getElementById('pppoe-last-down').textContent = 'Error';
            
            document.getElementById('pppoe-traffic-box').style.display = 'block';
            document.getElementById('pppoe-traffic-box').querySelector('.card-body').innerHTML = 
                '<p class="text-danger mb-0">Gagal memuat data penggunaan internet. Silakan refresh halaman.</p>';
        });
}

// ✅ WebSocket untuk Real-time Traffic Monitoring
let trafficMonitoringInterval = null;
let trafficWebSocketConnected = false;

function setupTrafficWebSocket() {
    try {
        // Cek apakah Pusher config tersedia
        const pusherKey = '{{ config("broadcasting.connections.pusher.key") }}';
        const pusherCluster = '{{ config("broadcasting.connections.pusher.options.cluster", "mt1") }}';
        
        // Jika Pusher tidak dikonfigurasi, skip WebSocket
        if (!pusherKey || pusherKey === '' || pusherKey === 'null' || pusherKey === null) {
            console.log('⚠️ WebSocket tidak dikonfigurasi untuk traffic monitoring. Menggunakan polling saja.');
            return;
        }
        
        // Wait untuk Pusher tersedia
        if (typeof Pusher === 'undefined') {
            console.log('⏳ Menunggu Pusher JS untuk traffic monitoring...');
            setTimeout(setupTrafficWebSocket, 500);
            return;
        }
        
        // ✅ Setup Laravel Echo dengan Pusher
        if (typeof window.echo === 'undefined') {
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
            
            window.echo = new window.Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: pusherCluster,
            });
        }
        
        console.log('✅ WebSocket initialized untuk traffic monitoring');
        
        // ✅ Listen untuk Traffic Updates
        window.echo.channel('pppoe-traffic-updates')
            .listen('pppoe.traffic.updated', (e) => {
                console.log('🔄 Traffic updated via WebSocket:', e);
                if (e.customer_id === {{ $customer->id }}) {
                    updateTrafficData(e.traffic);
                }
            });
        
        // Connection status
        window.echo.pusher.connection.bind('connected', () => {
            console.log('✅ WebSocket connected - Real-time traffic monitoring aktif');
            trafficWebSocketConnected = true;
        });
        
        window.echo.pusher.connection.bind('disconnected', () => {
            console.warn('⚠️ WebSocket disconnected untuk traffic monitoring');
            trafficWebSocketConnected = false;
        });
        
        window.echo.pusher.connection.bind('error', (err) => {
            console.error('❌ WebSocket error untuk traffic monitoring:', err);
            trafficWebSocketConnected = false;
        });
        
    } catch (error) {
        console.error('❌ Error setting up WebSocket untuk traffic monitoring:', error);
        trafficWebSocketConnected = false;
    }
}

// ✅ Update traffic data di UI (via WebSocket)
function updateTrafficData(traffic) {
    if (!traffic) {
        console.warn('⚠️ updateTrafficData called with no traffic data');
        return;
    }
    
    console.log('🔄 Updating traffic data via WebSocket:', traffic);
    
    // Update data (Rate sudah dihilangkan)
    document.getElementById('traffic-tx-bytes').textContent = formatBytes(parseInt(traffic.tx_bytes || 0));
    document.getElementById('traffic-tx-packets').textContent = formatNumber(parseInt(traffic.tx_packets || 0));
    document.getElementById('traffic-tx-drops').textContent = formatNumber(parseInt(traffic.tx_drops || 0));
    document.getElementById('traffic-tx-errors').textContent = formatNumber(parseInt(traffic.tx_errors || 0));
    
    document.getElementById('traffic-rx-bytes').textContent = formatBytes(parseInt(traffic.rx_bytes || 0));
    document.getElementById('traffic-rx-packets').textContent = formatNumber(parseInt(traffic.rx_packets || 0));
    document.getElementById('traffic-rx-drops').textContent = formatNumber(parseInt(traffic.rx_drops || 0));
    document.getElementById('traffic-rx-errors').textContent = formatNumber(parseInt(traffic.rx_errors || 0));
    
    // Show traffic content
    document.getElementById('pppoe-traffic-box').style.display = 'none';
    document.getElementById('pppoe-traffic-content').style.display = 'block';
    
    // Highlight update dengan animasi
    const txCard = document.getElementById('traffic-tx-bytes').closest('.card');
    const rxCard = document.getElementById('traffic-rx-bytes').closest('.card');
    if (txCard) {
        txCard.style.transition = 'background-color 0.3s';
        txCard.style.backgroundColor = '#e3f2fd';
        setTimeout(() => {
            txCard.style.backgroundColor = '';
        }, 500);
    }
    if (rxCard) {
        rxCard.style.transition = 'background-color 0.3s';
        rxCard.style.backgroundColor = '#e8f5e9';
        setTimeout(() => {
            rxCard.style.backgroundColor = '';
        }, 500);
    }
}

// ✅ Start traffic monitoring (polling untuk trigger broadcast)
function startTrafficMonitoring() {
    const customerId = {{ $customer->id }};
    
    // Start monitoring via API
    fetch(`/customers/${customerId}/traffic-monitoring/start`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Traffic monitoring dimulai:', data.message);
            
            // Polling untuk trigger broadcast setiap 2 detik
            // Server akan broadcast via WebSocket
            trafficMonitoringInterval = setInterval(() => {
                // Trigger fetch untuk mendapatkan data terbaru dan broadcast
                fetch(`/customers/${customerId}/pppoe-interface`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data && data.data.traffic) {
                            // Data akan di-broadcast oleh server via WebSocket
                            // Frontend akan menerima update via WebSocket listener
                        }
                    })
                    .catch(error => {
                        console.error('Error polling traffic data:', error);
                    });
            }, 2000); // Poll setiap 2 detik
            
        } else {
            console.warn('⚠️ Gagal memulai traffic monitoring:', data.message);
        }
    })
    .catch(error => {
        console.error('Error starting traffic monitoring:', error);
    });
}

// ✅ Stop traffic monitoring saat halaman ditutup
window.addEventListener('beforeunload', function() {
    if (trafficMonitoringInterval) {
        clearInterval(trafficMonitoringInterval);
    }
    
    const customerId = {{ $customer->id }};
    // Stop monitoring via API
    fetch(`/customers/${customerId}/traffic-monitoring/stop`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    }).catch(error => {
        console.error('Error stopping traffic monitoring:', error);
    });
});
@endif
</script>
@endpush

<style>
    .card {
        border-radius: 10px;
    }
    
    .card-header {
        border-radius: 10px 10px 0 0 !important;
    }
    
    code {
        background-color: #f8f9fa;
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-size: 0.9em;
    }
    
    .table-borderless td {
        padding: 0.75rem 0.5rem;
        border: none;
    }
    
    .table-borderless tr:hover {
        background-color: #f8f9fa;
        border-radius: 6px;
    }
</style>

@endsection
