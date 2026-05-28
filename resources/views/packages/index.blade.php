@extends('layouts.admin')

@section('title', 'Package Management')
@section('page-title', 'Package Management')

@section('content')

<!-- 🔴 PROFIL ISOLIR SECTION -->
<div class="card border-danger mb-4 shadow-sm">
    <div class="card-header bg-danger bg-gradient text-white">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
            <div>
                <h5 class="mb-0 fw-bold">Profil Isolir PPPoE</h5>
                <small class="opacity-75">Profile khusus untuk customer yang terisolir/suspended</small>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form action="{{ route('packages.update-isolir-profile') }}" method="POST" id="isolirProfileForm">
            @csrf
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-speedometer2 text-danger"></i> Nama Profile
                    </label>
                    <input type="text" name="isolir_profile_name" class="form-control" 
                           value="{{ $isolirSettings['profile_name'] ?? 'PROFIL-ISOLIR' }}" 
                           placeholder="PROFIL-ISOLIR">
                    <small class="text-muted">Nama profile di Mikrotik</small>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-arrow-down-circle text-primary"></i> Download
                    </label>
                    <div class="input-group">
                        <input type="number" name="isolir_download_speed" class="form-control" 
                               value="{{ $isolirSettings['download_speed'] ?? 1 }}" 
                               min="0" step="0.1">
                        <span class="input-group-text">Mbps</span>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-arrow-up-circle text-success"></i> Upload
                    </label>
                    <div class="input-group">
                        <input type="number" name="isolir_upload_speed" class="form-control" 
                               value="{{ $isolirSettings['upload_speed'] ?? 1 }}" 
                               min="0" step="0.1">
                        <span class="input-group-text">Mbps</span>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-info-circle text-info"></i> Keterangan
                    </label>
                    <input type="text" name="isolir_description" class="form-control" 
                           value="{{ $isolirSettings['description'] ?? 'Profile untuk customer suspended' }}" 
                           placeholder="Deskripsi profile">
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-save"></i> Simpan Profile
                    </button>
                </div>
            </div>
            
            <div class="alert alert-warning mt-3 mb-0">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Info:</strong> Profile ini akan otomatis diterapkan ke customer PPPoE yang terisolir/suspended karena tagihan overdue.
                Customer akan dikembalikan ke profile paket normal setelah melakukan pembayaran.
            </div>
        </form>
    </div>
</div>

<!-- 📦 PACKAGES SECTION -->
<div class="custom-table">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold mb-1">
                <i class="bi bi-box-seam text-primary me-2"></i>Daftar Paket Internet
            </h5>
            <small class="text-muted">Kelola semua paket layanan internet Anda</small>
        </div>
        @can('create_package')
        <a href="{{ route('packages.create') }}" class="btn btn-primary btn-lg shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Tambah Paket
        </a>
        @endcan
    </div>

    <!-- Filter & Search -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light d-md-none" style="cursor: pointer;" onclick="toggleMobileFilter()">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-funnel me-2"></i>Filter & Cari</span>
                <i class="bi bi-chevron-down" id="filterToggleIcon"></i>
            </div>
        </div>
        <div class="card-body" id="filterBody">
            <form method="GET" action="{{ route('packages.index') }}">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-primary"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" 
                                   placeholder="Cari nama paket..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <select name="status" class="form-select">
                            <option value="">📊 Semua Status</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>✅ Active</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>❌ Inactive</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <select name="billing_cycle" class="form-select">
                            <option value="">📅 Semua Billing Cycle</option>
                            <option value="daily" {{ request('billing_cycle') == 'daily' ? 'selected' : '' }}>📆 Daily</option>
                            <option value="weekly" {{ request('billing_cycle') == 'weekly' ? 'selected' : '' }}>📆 Weekly</option>
                            <option value="monthly" {{ request('billing_cycle') == 'monthly' ? 'selected' : '' }}>📆 Monthly</option>
                            <option value="yearly" {{ request('billing_cycle') == 'yearly' ? 'selected' : '' }}>📆 Yearly</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <select name="connection_type" class="form-select">
                            <option value="">🔌 Semua Tipe Koneksi</option>
                            <option value="pppoe" {{ request('connection_type') == 'pppoe' ? 'selected' : '' }}>📡 PPPoE</option>
                            <option value="hotspot" {{ request('connection_type') == 'hotspot' ? 'selected' : '' }}>📶 Hotspot</option>
                            <option value="static" {{ request('connection_type') == 'static' ? 'selected' : '' }}>🌐 Static IP</option>
                            <option value="dhcp" {{ request('connection_type') == 'dhcp' ? 'selected' : '' }}>⚡ DHCP</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <select name="router_id" class="form-select">
                            <option value="">🛰️ Semua Router</option>
                            @foreach($routers ?? [] as $router)
                                <option value="{{ $router->id }}" {{ request('router_id') == $router->id ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->ip_address }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel-fill d-md-none"></i>
                            <span class="d-none d-md-inline"><i class="bi bi-funnel-fill"></i> Filter</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($packages->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle modern-table">
                <thead class="table-light">
                    <tr>
                        <th class="fw-bold">
                            <i class="bi bi-box text-primary me-1"></i> Nama Paket
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-speedometer2 text-info me-1"></i> Speed
                        </th>
                        <th class="fw-bold">
                            <i class="bi bi-currency-dollar text-success me-1"></i> Harga
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-database text-warning me-1"></i> FUP
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-calendar-check text-secondary me-1"></i> Billing
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-people text-primary me-1"></i> Users
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-router text-info me-1"></i> Router
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-toggle-on text-success me-1"></i> Status
                        </th>
                        <th class="fw-bold text-center">
                            <i class="bi bi-gear text-dark me-1"></i> Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packages as $package)
                    <tr class="package-row">
                        <td data-label="Nama Paket">
                            <div class="d-flex align-items-center">
                                <div class="package-icon me-3">
                                    <i class="bi bi-wifi fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $package->name }}</div>
                                    <small class="text-muted">{{ Str::limit($package->description, 50) }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center" data-label="Speed">
                            <div class="speed-badge">
                                <i class="bi bi-arrow-down text-primary"></i> {{ $package->download_speed }} Mbps<br>
                                <i class="bi bi-arrow-up text-success"></i> {{ $package->upload_speed }} Mbps
                            </div>
                        </td>
                        <td data-label="Harga">
                            <div class="price-tag">
                                <span class="fs-5 fw-bold text-success">{{ $package->getFormattedPrice() }}</span>
                            </div>
                        </td>
                        <td class="text-center" data-label="FUP">
                            @if($package->has_fup)
                                <span class="badge bg-warning text-dark px-3 py-2">
                                    <i class="bi bi-hdd-stack"></i> {{ $package->fup_quota }} GB
                                </span>
                            @else
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-infinity"></i> Unlimited
                                </span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Billing">
                            <span class="badge bg-secondary px-3 py-2">
                                {{ ucfirst($package->billing_cycle) }}
                            </span>
                        </td>
                        <td class="text-center" data-label="Users">
                            <span class="badge bg-primary px-3 py-2 fs-6">
                                {{ $package->customers_count }} <i class="bi bi-person-fill"></i>
                            </span>
                        </td>
                        <td class="text-center" data-label="Router">
                            @if($package->routers->count() > 0)
                                @foreach($package->routers as $router)
                                    <div class="mb-1 d-inline-block">
                                        <span class="badge bg-info px-2 py-1">
                                            <i class="bi bi-router d-md-inline d-none"></i> {{ $router->name }}
                                        </span>
                                        <span class="badge bg-secondary ms-1">{{ strtoupper($router->pivot->connection_type) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Status">
                            @if($package->is_active)
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-check-circle-fill"></i> Active
                                </span>
                            @else
                                <span class="badge bg-danger px-3 py-2">
                                    <i class="bi bi-x-circle-fill"></i> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="text-center" data-label="Action">
                            <div class="btn-group" role="group">
                                <a href="{{ route('packages.show', $package) }}" 
                                   class="btn btn-sm btn-outline-info" 
                                   title="Detail"
                                   data-bs-toggle="tooltip">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('edit_package')
                                <a href="{{ route('packages.edit', $package) }}" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Edit"
                                   data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endcan

                                @can('delete_package')
                                <form action="{{ route('packages.destroy', $package) }}" method="POST" class="d-inline delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="Delete"
                                            data-bs-toggle="tooltip">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-3">
            <div class="text-muted">
                <small>Showing <strong>{{ $packages->firstItem() ?? 0 }}</strong> to <strong>{{ $packages->lastItem() ?? 0 }}</strong> of <strong>{{ $packages->total() }}</strong> results</small>
            </div>
            @if($packages->hasPages())
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    {{-- Previous Page Link --}}
                    @if ($packages->onFirstPage())
                        <li class="page-item disabled">
                            <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $packages->previousPageUrl() }}" rel="prev">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    @endif

                    {{-- Pagination Elements --}}
                    @php
                        $currentPage = $packages->currentPage();
                        $lastPage = $packages->lastPage();
                        $leftEllipsisShown = false;
                        $rightEllipsisShown = false;
                    @endphp

                    @for ($page = 1; $page <= $lastPage; $page++)
                        @if ($page == 1 || $page == $lastPage || ($page >= $currentPage - 2 && $page <= $currentPage + 2))
                            <li class="page-item {{ $page == $currentPage ? 'active' : '' }}">
                                @if ($page == $currentPage)
                                    <span class="page-link">{{ $page }}</span>
                                @else
                                    <a class="page-link" href="{{ $packages->url($page) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @elseif ($page == $currentPage - 3 && !$leftEllipsisShown)
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            @php
                                $leftEllipsisShown = true;
                            @endphp
                        @elseif ($page == $currentPage + 3 && !$rightEllipsisShown)
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            @php
                                $rightEllipsisShown = true;
                            @endphp
                        @endif
                    @endfor

                    {{-- Next Page Link --}}
                    @if ($packages->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $packages->nextPageUrl() }}" rel="next">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    @else
                        <li class="page-item disabled">
                            <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                        </li>
                    @endif
                </ul>
            </nav>
            @endif
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-box-seam" style="font-size: 4rem; color: #ccc;"></i>
            <h5 class="mt-3">Belum Ada Paket</h5>
            <p class="text-muted">Klik tombol "Tambah Paket" untuk menambah paket baru.</p>
        </div>
    @endif
</div>

<style>
    /* Modern Package Table Styling */
    .modern-table {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .modern-table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        padding: 16px 12px;
        border: none;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    
    .package-row {
        transition: all 0.3s ease;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .package-row:hover {
        background: linear-gradient(90deg, #f8f9ff 0%, #ffffff 100%);
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    
    .package-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);
        border-radius: 12px;
    }
    
    .speed-badge {
        font-size: 0.85rem;
        line-height: 1.8;
    }
    
    .price-tag {
        background: linear-gradient(135deg, #10b98122 0%, #06875522 100%);
        padding: 8px 16px;
        border-radius: 8px;
        display: inline-block;
    }
    
    .btn-group .btn {
        transition: all 0.2s ease;
    }
    
    .btn-group .btn:hover {
        transform: translateY(-2px);
    }
    
    /* Isolir Profile Card */
    .card.border-danger {
        border-width: 2px;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .card-header.bg-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        padding: 20px;
    }
    
    .alert-warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
        border: none;
        border-left: 4px solid #ffc107;
    }
    
    /* Filter Card */
    .card.shadow-sm {
        border-radius: 12px;
    }
    
    .input-group-text.bg-white {
        border-right: none;
    }
    
    .input-group .form-control.border-start-0 {
        border-left: none;
    }
    
    .input-group .form-control:focus {
        border-left: none;
        box-shadow: none;
    }
    
    .input-group:focus-within .input-group-text {
        border-color: #86b7fe;
    }
    
    /* Badge Improvements */
    .badge {
        font-weight: 500;
        letter-spacing: 0.3px;
    }
    
    /* Pagination styling - sama seperti customers */
    .pagination-sm .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .pagination-sm .page-link i {
        font-size: 0.875rem;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
    }
    
    .empty-state i {
        opacity: 0.3;
    }
    
    /* Hide large chevron/arrow icons that shouldn't be there */
    i[style*="font-size: 100px"],
    i[style*="font-size:150px"],
    i[style*="font-size: 200px"],
    i[style*="font-size:300px"],
    i[style*="font-size:400px"] {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* Jangan sembunyikan chevron di pagination */
    .pagination .bi-chevron-left,
    .pagination .bi-chevron-right {
        display: inline-block !important;
        visibility: visible !important;
    }
    
    /* Responsive Mobile */
    @media (max-width: 768px) {
        /* Hide table header di mobile */
        .modern-table thead {
            display: none;
        }
        
        /* Convert table rows to cards */
        .package-row {
            display: block;
            margin-bottom: 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 16px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .package-row:hover {
            transform: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .package-row td {
            display: block;
            text-align: left !important;
            padding: 10px 0 !important;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .package-row td:last-child {
            border-bottom: none;
            padding-top: 12px !important;
        }
        
        .package-row td:before {
            content: attr(data-label) ":";
            font-weight: 600;
            color: #666;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 6px;
        }
        
        /* Simplify package icon di mobile */
        .package-icon {
            width: 36px;
            height: 36px;
        }
        
        .package-icon i {
            font-size: 1.2rem !important;
        }
        
        /* Simplify badges di mobile */
        .badge {
            font-size: 0.7rem !important;
            padding: 0.25rem 0.5rem !important;
            margin: 2px;
            line-height: 1.3;
        }
        
        .badge i {
            font-size: 0.7rem;
        }
        
        /* Speed badge lebih compact */
        .speed-badge {
            font-size: 0.8rem;
            line-height: 1.5;
        }
        
        .speed-badge i {
            font-size: 0.75rem;
        }
        
        /* Price tag lebih kecil */
        .price-tag {
            padding: 4px 10px;
        }
        
        .price-tag span {
            font-size: 0.9rem !important;
        }
        
        /* Action buttons lebih compact */
        .btn-group {
            display: flex;
            gap: 4px;
            justify-content: flex-start;
        }
        
        .btn-group .btn {
            padding: 0.3rem 0.45rem !important;
            font-size: 0.8rem !important;
            min-width: auto;
            width: auto;
        }
        
        .btn-group .btn i {
            font-size: 0.9rem;
        }
        
        /* Filter form - stack vertically di mobile */
        .card.shadow-sm .card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .card.shadow-sm .row.g-3 {
            margin: 0;
        }
        
        .card.shadow-sm .row.g-3 > div {
            padding: 0.5rem 0;
        }
        
        .card.shadow-sm .form-select,
        .card.shadow-sm .form-control {
            font-size: 0.9rem;
            padding: 0.65rem 0.75rem;
        }
        
        .card.shadow-sm .btn {
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
            width: 100%;
            margin-top: 0.5rem;
        }
        
        /* Header section */
        .d-flex.justify-content-between.align-items-center {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem;
        }
        
        .d-flex.justify-content-between .btn-lg {
            width: 100%;
            font-size: 0.95rem;
            padding: 0.6rem 0.75rem;
        }
        
        .btn-lg i {
            font-size: 1rem;
        }
        
        /* Isolir Profile Card - lebih compact */
        .card.border-danger {
            margin-bottom: 1rem;
        }
        
        .card.border-danger .card-header {
            padding: 1rem !important;
        }
        
        .card.border-danger .card-header h5 {
            font-size: 1rem;
            margin: 0;
        }
        
        .card.border-danger .card-body {
            padding: 1rem;
        }
        
        .card.border-danger .row > div {
            padding: 0.5rem 0;
        }
        
        /* Pagination responsive - sama seperti customers */
        .d-flex.justify-content-between.align-items-center.mt-3 {
            flex-direction: column;
            align-items: stretch !important;
            gap: 1rem;
        }
        
        .d-flex.justify-content-between.align-items-center.mt-3 > div:first-child {
            text-align: center;
        }
        
        nav {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        
        .pagination-sm .page-link {
            padding: 0.2rem 0.4rem !important;
            font-size: 0.8rem !important;
        }
        
        .pagination-sm .page-link i {
            font-size: 0.8rem !important;
        }
        
        /* Router badge lebih compact */
        .package-row td[data-label="Router"] .badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem !important;
            margin: 2px 0;
        }
        
        /* Users badge lebih kecil */
        .package-row td[data-label="Users"] .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem !important;
        }
        
        /* Status badge lebih kecil */
        .package-row td[data-label="Status"] .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem !important;
        }
        
        /* Billing badge lebih kecil */
        .package-row td[data-label="Billing"] .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem !important;
        }
        
        /* FUP badge lebih kecil */
        .package-row td[data-label="FUP"] .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem !important;
        }
    }
    
    @media (max-width: 576px) {
        /* Extra small devices - lebih compact lagi */
        .package-row {
            padding: 12px;
            margin-bottom: 12px;
        }
        
        .package-row td {
            padding: 8px 0 !important;
        }
        
        .package-row td:before {
            font-size: 0.8rem;
            margin-bottom: 4px;
        }
        
        .package-icon {
            width: 32px;
            height: 32px;
        }
        
        .package-icon i {
            font-size: 1rem !important;
        }
        
        .badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.5rem !important;
        }
        
        .speed-badge {
            font-size: 0.8rem;
        }
        
        .price-tag span {
            font-size: 0.95rem !important;
        }
        
        .btn-group .btn {
            padding: 0.25rem 0.4rem !important;
            font-size: 0.75rem !important;
        }
        
        .btn-group .btn i {
            font-size: 0.85rem;
        }
        
        .badge {
            font-size: 0.65rem !important;
            padding: 0.2rem 0.4rem !important;
        }
        
        .badge i {
            font-size: 0.65rem;
        }
        
        .speed-badge {
            font-size: 0.75rem;
        }
        
        .price-tag span {
            font-size: 0.85rem !important;
        }
        
        h5.fw-bold {
            font-size: 1rem;
        }
        
        .card-header h5 {
            font-size: 0.9rem;
        }
        
        .d-flex.justify-content-between .btn-lg {
            font-size: 0.9rem;
            padding: 0.5rem 0.65rem;
        }
        
        /* Filter dropdown text lebih kecil */
        .form-select option {
            font-size: 0.9rem;
        }
        
        /* Pagination - Extra Small */
        .pagination-sm .page-link {
            padding: 0.2rem 0.35rem !important;
            font-size: 0.75rem !important;
        }
        
        .pagination-sm .page-link i {
            font-size: 0.75rem !important;
        }
        
        .d-flex.justify-content-between.align-items-center.mt-3 > div:first-child small {
            font-size: 0.75rem;
        }
    }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Confirm delete
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (confirm('⚠️ Yakin ingin menghapus paket ini?\n\nPaket yang sudah digunakan oleh customer tidak bisa dihapus.')) {
                    this.submit();
                }
            });
        });
        
        // Isolir Profile Form Submit
        const isolirForm = document.getElementById('isolirProfileForm');
        if (isolirForm) {
            isolirForm.addEventListener('submit', function(e) {
                const profileName = document.querySelector('[name="isolir_profile_name"]').value;
                
                if (!profileName || profileName.trim() === '') {
                    e.preventDefault();
                    alert('❌ Nama profile tidak boleh kosong!');
                    return false;
                }
                
                // Optional: Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
                submitBtn.disabled = true;
            });
        }
        
        // Success message animation
        @if(session('success'))
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 3000);
        @endif
    });
</script>
@endpush
