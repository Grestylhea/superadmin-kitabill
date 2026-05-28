@extends('layouts.admin')

@section('title', 'Selling Report - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; justify-content: center; align-items: center;">
    <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3" style="font-size: 16px; font-weight: 500;">Memuat data laporan...</p>
        <p class="text-muted" style="font-size: 12px;">Mohon tunggu, ini mungkin memakan waktu beberapa detik</p>
    </div>
</div>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fa fa-money"></i> Selling Report {{ $monthDisplay }}
                        @if($prefix)
                            <small>prefix [{{ $prefix }}]</small>
                        @endif
                    </h3>
                </div>
                <div>
                    <a href="{{ route('hotspot.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('hotspot.report.selling') }}" id="filterForm" class="row g-3" onsubmit="return handleFilterSubmit(event);">
                        <div class="col-md-2">
                            <input type="text" class="form-control" id="prefix" name="prefix" placeholder="Prefix" value="{{ $prefix }}">
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" id="day" name="day">
                                <option value="">Day</option>
                                @php
                                    $selectedDay = $date ? explode('/', $date)[1] ?? '' : '';
                                @endphp
                                @for($i = 1; $i <= 31; $i++)
                                    <option value="{{ $i }}" {{ $selectedDay == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" id="month" name="month">
                                <option value="">Month</option>
                                @php
                                    $selectedMonth = '';
                                    if ($date) {
                                        $selectedMonth = explode('/', $date)[0] ?? '';
                                    } elseif ($month) {
                                        $selectedMonth = substr($month, 0, 3);
                                    }
                                @endphp
                                <option value="jan" {{ $selectedMonth == 'jan' ? 'selected' : '' }}>January</option>
                                <option value="feb" {{ $selectedMonth == 'feb' ? 'selected' : '' }}>February</option>
                                <option value="mar" {{ $selectedMonth == 'mar' ? 'selected' : '' }}>March</option>
                                <option value="apr" {{ $selectedMonth == 'apr' ? 'selected' : '' }}>April</option>
                                <option value="may" {{ $selectedMonth == 'may' ? 'selected' : '' }}>May</option>
                                <option value="jun" {{ $selectedMonth == 'jun' ? 'selected' : '' }}>June</option>
                                <option value="jul" {{ $selectedMonth == 'jul' ? 'selected' : '' }}>July</option>
                                <option value="aug" {{ $selectedMonth == 'aug' ? 'selected' : '' }}>August</option>
                                <option value="sep" {{ $selectedMonth == 'sep' ? 'selected' : '' }}>September</option>
                                <option value="oct" {{ $selectedMonth == 'oct' ? 'selected' : '' }}>October</option>
                                <option value="nov" {{ $selectedMonth == 'nov' ? 'selected' : '' }}>November</option>
                                <option value="dec" {{ $selectedMonth == 'dec' ? 'selected' : '' }}>December</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" id="year" name="year">
                                <option value="">Year</option>
                                @php
                                    $selectedYear = '';
                                    if ($date) {
                                        $selectedYear = explode('/', $date)[2] ?? '';
                                    } elseif ($month) {
                                        $selectedYear = substr($month, 3);
                                    }
                                @endphp
                                @for($i = date('Y'); $i >= date('Y') - 5; $i--)
                                    <option value="{{ $i }}" {{ $selectedYear == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary" id="filterBtn">
                                <i class="fa fa-search"></i> Filter
                            </button>
                            <a href="{{ route('hotspot.report.selling') }}" class="btn btn-secondary">
                                <i class="fa fa-search"></i> All
                            </a>
                            <a href="{{ route('hotspot.report.selling.export', ['idhr' => $date, 'idbl' => $month, 'prefix' => $prefix]) }}" class="btn btn-success">
                                <i class="fa fa-download"></i> CSV
                            </a>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fa fa-print"></i> Print
                            </button>
                            @if($date || $month)
                                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    @if($reportData['count'] > 0)
                        <div class="alert alert-info alert-dismissible fade show">
                            <i class="fa fa-info-circle"></i> 
                            Menampilkan <strong>{{ $reportData['count'] }}</strong> data terbaru.
                            @if(!isset($date) && !isset($month))
                                <strong>Gunakan filter bulan/tanggal untuk melihat data spesifik.</strong>
                            @endif
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                        <table id="dataTable" class="table table-bordered table-hover text-nowrap">
                            <thead class="thead-light">
                                <tr>
                                    <th colspan="5">Selling Report {{ $monthDisplay }}{{ $prefix ? ' prefix [' . $prefix . ']' : '' }}</th>
                                    <th style="text-align:right;">Total</th>
                                    <th style="text-align:right;" id="total">Rp {{ number_format($reportData['total'], 0, ',', '.') }}</th>
                                </tr>
                                <tr>
                                    <th>№</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Username</th>
                                    <th>Profile</th>
                                    <th>Comment</th>
                                    <th style="text-align:right;">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reportData['reports'] as $index => $report)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $report['date'] }}</td>
                                        <td>{{ $report['time'] }}</td>
                                        <td>{{ $report['username'] }}</td>
                                        <td>{{ $report['profile'] }}</td>
                                        <td>{{ $report['comment'] }}</td>
                                        <td style="text-align:right;">{{ number_format($report['price'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <i class="fa fa-info-circle"></i> Tidak ada data laporan
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus data laporan ini?</p>
                <p class="text-danger"><strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan!</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="{{ route('hotspot.report.selling.delete') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// ✅ FIX: Handle form filter submission dengan format yang benar
function handleFilterSubmit(event) {
    event.preventDefault(); // Prevent default form submission
    
    var day = document.getElementById('day').value;
    var month = document.getElementById('month').value;
    var year = document.getElementById('year').value;
    var prefix = document.getElementById('prefix').value;
    
    var url = '{{ route("hotspot.report.selling") }}';
    var params = [];
    
    // ✅ Format sesuai Mikhmon: idhr="dec/01/2025" untuk tanggal spesifik, idbl="dec2025" untuk bulan
    if (day && month && year) {
        // Format: dec/01/2025 (month/day/year)
        var monthNames = {
            'jan': 'jan', 'feb': 'feb', 'mar': 'mar', 'apr': 'apr',
            'may': 'may', 'jun': 'jun', 'jul': 'jul', 'aug': 'aug',
            'sep': 'sep', 'oct': 'oct', 'nov': 'nov', 'dec': 'dec'
        };
        var monthShort = monthNames[month] || month;
        var dayPadded = String(day).padStart(2, '0');
        params.push('idhr=' + encodeURIComponent(monthShort + '/' + dayPadded + '/' + year));
    } else if (month && year) {
        // Format: dec2025 (month + year)
        var monthNames = {
            'jan': 'jan', 'feb': 'feb', 'mar': 'mar', 'apr': 'apr',
            'may': 'may', 'jun': 'jun', 'jul': 'jul', 'aug': 'aug',
            'sep': 'sep', 'oct': 'oct', 'nov': 'nov', 'dec': 'dec'
        };
        var monthShort = monthNames[month] || month;
        params.push('idbl=' + encodeURIComponent(monthShort + year));
    }
    
    if (prefix) {
        params.push('prefix=' + encodeURIComponent(prefix));
    }
    
    // Redirect dengan parameter yang sudah diformat
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    window.location.href = url;
    return false;
}

// Calculate total on page load
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading overlay jika masih muncul
    var loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
    
    var total = 0;
    var rows = document.querySelectorAll('#dataTable tbody tr');
    rows.forEach(function(row) {
        var priceCell = row.querySelector('td:last-child');
        if (priceCell) {
            var priceText = priceCell.textContent.trim().replace(/[^\d]/g, '');
            if (priceText) {
                total += parseInt(priceText);
            }
        }
    });
    
    var totalElement = document.getElementById('total');
    if (totalElement) {
        totalElement.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }
});

// Show loading overlay saat link diklik
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('a[href*="hotspot.report.selling"]');
    links.forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Jangan show loading jika link dengan hash atau anchor
            if (!this.getAttribute('href').includes('#')) {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }
        });
    });
});
</script>
@endsection

