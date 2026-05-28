<template>
  <SuperAdminLayout>
    <Head title="Global Monitoring" />
    
    <div class="container-fluid">
      <!-- Header banner style matching dashboard -->
      <div class="welcome-banner mb-4">
        <div class="welcome-content">
          <h2 class="welcome-title">Global Monitoring</h2>
          <p class="welcome-subtitle">Pantau performa pelanggan dan keuangan dari seluruh Mitra/Tenant secara real-time.</p>
        </div>
        <div class="welcome-actions">
          <div class="d-flex gap-2">
            <select 
              v-model="filterForm.month" 
              @change="submitFilter"
              class="filter-select shadow-sm"
              aria-label="Pilih Bulan"
            >
              <option v-for="(name, index) in monthNames" :key="index" :value="String(index + 1).padStart(2, '0')" class="text-dark">
                {{ name }}
              </option>
            </select>

            <select 
              v-model="filterForm.year" 
              @change="submitFilter"
              class="filter-select shadow-sm"
              aria-label="Pilih Tahun"
            >
              <option v-for="y in years" :key="y" :value="y" class="text-dark">{{ y }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Metrics Row matching dashboard cards style -->
      <div class="row g-4 mb-4">
        <!-- Total Customers Card -->
        <div class="col-12 col-md-4">
          <div class="stats-card gradient-blue">
            <div class="stats-content">
              <div class="stats-header">
                <span class="stats-label">Total Pelanggan Global</span>
                <i class="bi bi-people stats-icon-bg"></i>
              </div>
              <div class="stats-value">{{ formatNumber(grandTotal.total_customers) }}</div>
              <div class="stats-footer">
                <span class="badge badge-info">
                  Aktif di seluruh mitra
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Revenue Card -->
        <div class="col-12 col-md-4">
          <div class="stats-card gradient-green">
            <div class="stats-content">
              <div class="stats-header">
                <span class="stats-label">Pemasukan Lunas</span>
                <i class="bi bi-cash-stack stats-icon-bg"></i>
              </div>
              <div class="stats-value">Rp {{ formatNumber(grandTotal.total_revenue) }}</div>
              <div class="stats-footer">
                <span class="badge badge-success">
                  Periode Terpilih
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Receivable Card -->
        <div class="col-12 col-md-4">
          <div class="stats-card gradient-orange">
            <div class="stats-content">
              <div class="stats-header">
                <span class="stats-label">Total Piutang (Belum Bayar)</span>
                <i class="bi bi-exclamation-circle stats-icon-bg"></i>
              </div>
              <div class="stats-value">Rp {{ formatNumber(grandTotal.total_receivable) }}</div>
              <div class="stats-footer">
                <span class="badge badge-warning">
                  Belum dibayar oleh mitra
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table Section matching dashboard table style -->
      <div class="row">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">
              <h5 class="card-title mb-0">
                <i class="bi bi-grid-3x3-gap text-primary me-2"></i>Performa Lintas Mitra
              </h5>
              <div class="d-flex flex-column flex-sm-row gap-2">
                <!-- Search Input -->
                <div class="input-group" style="min-width: 250px;">
                  <span class="input-group-text bg-light border-slate-300 text-muted"><i class="bi bi-search"></i></span>
                  <input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Cari nama mitra/subdomain..." 
                    class="form-control border-slate-300"
                    aria-label="Cari Mitra"
                  />
                </div>

                <!-- Status Filter -->
                <select 
                  v-model="statusFilter" 
                  class="form-select border-slate-300"
                  style="width: 150px;"
                  aria-label="Filter Status"
                >
                  <option value="all">Semua Status</option>
                  <option value="active">Active</option>
                  <option value="trial">Trial</option>
                  <option value="suspended">Suspended</option>
                </select>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead>
                    <tr>
                      <th class="ps-4 cursor-pointer select-none" @click="sortBy('tenant_name')">
                        Nama Mitra
                        <i v-if="sortField === 'tenant_name'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                      </th>
                      <th>Subdomain</th>
                      <th class="cursor-pointer select-none" @click="sortBy('tenant_status')">
                        Status
                        <i v-if="sortField === 'tenant_status'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                      </th>
                      <th class="text-center cursor-pointer select-none" @click="sortBy('customers.active')">
                        Pelanggan Aktif
                        <i v-if="sortField === 'customers.active'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                      </th>
                      <th class="text-center">Terisolir</th>
                      <th class="text-center cursor-pointer select-none" @click="sortBy('customers.total')">
                        Total Pelanggan
                        <i v-if="sortField === 'customers.total'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                      </th>
                      <th class="text-end cursor-pointer select-none" @click="sortBy('finances.total_revenue')">
                        Pemasukan Lunas
                        <i v-if="sortField === 'finances.total_revenue'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                      </th>
                      <th class="text-end pe-4 cursor-pointer select-none" @click="sortBy('finances.total_receivable')">
                        Piutang
                        <i v-if="sortField === 'finances.total_receivable'" :class="sortDirection === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down'" class="ms-1"></i>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="data in filteredAndSortedData" :key="data.tenant_id">
                      <td class="ps-4">
                        <strong class="text-dark dark:text-white">{{ data.tenant_name }}</strong>
                      </td>
                      <td>
                        <a :href="'https://' + data.tenant_subdomain + '.kitabill.site'" target="_blank" class="text-primary text-decoration-none">
                          {{ data.tenant_subdomain }}.kitabill.site
                        </a>
                      </td>
                      <td>
                        <span class="badge" :class="getStatusBadgeClass(data.tenant_status)">
                          {{ data.tenant_status }}
                        </span>
                      </td>
                      <td class="text-center font-medium">{{ formatNumber(data.customers.active) }}</td>
                      <td class="text-center text-warning fw-semibold">{{ formatNumber(data.customers.suspended) }}</td>
                      <td class="text-center fw-bold">{{ formatNumber(data.customers.total) }}</td>
                      <td class="text-end text-success fw-bold">Rp {{ formatNumber(data.finances.total_revenue) }}</td>
                      <td class="text-end text-danger fw-bold pe-4">Rp {{ formatNumber(data.finances.total_receivable) }}</td>
                    </tr>
                    <tr v-if="filteredAndSortedData.length === 0">
                      <td colspan="8" class="text-center py-5 text-muted">
                        Tidak ada data performa tenant yang cocok dengan filter.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
  monitoringData: Array,
  grandTotal: Object,
  filters: Object,
});

const searchQuery = ref('');
const statusFilter = ref('all');
const sortField = ref('finances.total_revenue');
const sortDirection = ref('desc');

const sortBy = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortField.value = field;
    sortDirection.value = 'desc';
  }
};

const filteredAndSortedData = computed(() => {
  let result = [...props.monitoringData];

  // Search filter
  if (searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase();
    result = result.filter(item => 
      item.tenant_name.toLowerCase().includes(q) || 
      item.tenant_subdomain.toLowerCase().includes(q)
    );
  }

  // Status filter
  if (statusFilter.value !== 'all') {
    result = result.filter(item => item.tenant_status === statusFilter.value);
  }

  // Sorting
  result.sort((a, b) => {
    let valA, valB;

    if (sortField.value === 'tenant_name') {
      valA = a.tenant_name.toLowerCase();
      valB = b.tenant_name.toLowerCase();
    } else if (sortField.value === 'tenant_status') {
      valA = a.tenant_status;
      valB = b.tenant_status;
    } else if (sortField.value === 'customers.active') {
      valA = a.customers.active;
      valB = b.customers.active;
    } else if (sortField.value === 'customers.total') {
      valA = a.customers.total;
      valB = b.customers.total;
    } else if (sortField.value === 'finances.total_revenue') {
      valA = a.finances.total_revenue;
      valB = b.finances.total_revenue;
    } else if (sortField.value === 'finances.total_receivable') {
      valA = a.finances.total_receivable;
      valB = b.finances.total_receivable;
    }

    if (valA === undefined) return 1;
    if (valB === undefined) return -1;

    if (valA < valB) return sortDirection.value === 'asc' ? -1 : 1;
    if (valA > valB) return sortDirection.value === 'asc' ? 1 : -1;
    return 0;
  });

  return result;
});

const filterForm = ref({
  month: props.filters.month,
  year: props.filters.year,
});

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const selectedYear = parseInt(props.filters.year) || new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => String(selectedYear - i));

const submitFilter = () => {
  router.get(route('superadmin.monitoring.index'), filterForm.value, {
    preserveState: true,
    replace: true,
  });
};

const formatNumber = (num) => {
  return new Intl.NumberFormat('id-ID').format(num || 0);
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'active':
      return 'bg-success';
    case 'trial':
      return 'bg-info';
    case 'suspended':
      return 'bg-danger';
    default:
      return 'bg-secondary';
  }
};
</script>

<style scoped>
/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 32px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

:global(.dark) .welcome-banner {
    background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
}

.welcome-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.welcome-subtitle {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
}

.filter-select {
    font-weight: 600;
    padding: 8px 32px 8px 16px;
    min-width: 140px;
    background-color: rgba(255, 255, 255, 0.15);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 8px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    transition: all 0.2s ease;
}

.filter-select:hover {
    background-color: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.4);
}

.filter-select:focus {
    outline: none;
    background-color: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.25);
}

.filter-select option {
    background-color: #ffffff;
    color: #1e293b;
}

:global(.dark) .filter-select option {
    background-color: #1e293b;
    color: #f1f5f9;
}

/* Stats Cards - Gradient Style matching Dashboard */
.stats-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.stats-card.gradient-blue::before {
    background: linear-gradient(90deg, #3B82F6 0%, #2563EB 100%);
}

.stats-card.gradient-green::before {
    background: linear-gradient(90deg, #10B981 0%, #059669 100%);
}

.stats-card.gradient-orange::before {
    background: linear-gradient(90deg, #F59E0B 0%, #D97706 100%);
}

.stats-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

:global(.dark) .stats-card {
    background: #1E293B;
}

.stats-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.stats-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

:global(.dark) .stats-label {
    color: #94A3B8;
}

.stats-icon-bg {
    font-size: 32px;
    color: rgba(0, 0, 0, 0.05);
}

:global(.dark) .stats-icon-bg {
    color: rgba(255, 255, 255, 0.05);
}

.stats-value {
    font-size: 32px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 12px;
}

:global(.dark) .stats-value {
    color: #F1F5F9;
}

.stats-footer .badge {
    font-size: 12px;
    padding: 4px 8px;
    font-weight: 600;
}

/* Table Card matching Dashboard */
.table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .table-card {
    background: #1E293B;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

:global(.dark) .card-header {
    border-bottom-color: #334155;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
}

:global(.dark) .card-title {
    color: #F1F5F9;
}

.table th {
    background: #F8FAFC;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 24px;
    border-bottom: 1.5px solid #E2E8F0;
}

:global(.dark) .table th {
    background: #1e293b;
    color: #94A3B8;
    border-bottom-color: #334155;
}

.table td {
    padding: 18px 24px;
    font-size: 14px;
    border-bottom: 1px solid #F1F5F9;
    color: #1e293b;
}

:global(.dark) .table td {
    border-bottom-color: #334155;
    color: #cbd5e1;
}

:global(.dark) .table-hover tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.02) !important;
}

.cursor-pointer {
    cursor: pointer;
}
.select-none {
    user-select: none;
}
.table th.cursor-pointer:hover {
    color: #4f46e5 !important;
    background-color: rgba(0, 0, 0, 0.02);
}
:global(.dark) .table th.cursor-pointer:hover {
    background-color: rgba(255, 255, 255, 0.02);
}
</style>
