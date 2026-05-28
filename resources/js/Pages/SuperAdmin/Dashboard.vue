<template>
    <SuperAdminLayout>
        <Head title="Super Admin Dashboard" />
        
        <div class="container-fluid">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2 class="welcome-title">Welcome back, {{ $page.props.auth?.user?.name }}! 👋</h2>
                    <p class="welcome-subtitle">Here's what's happening with your KitaBill platform today.</p>
                </div>
                <div class="welcome-actions">
                    <Link :href="route('superadmin.tenants.create')" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i>
                        Add New Tenant
                    </Link>
                </div>
            </div>

            <!-- Stats Cards Row 1 -->
            <div class="row g-4 mb-4">
                <!-- Total Tenants -->
                <div class="col-6 col-md-3">
                    <div class="stats-card gradient-blue">
                        <div class="stats-content">
                            <div class="stats-header">
                                <span class="stats-label">Total Tenants</span>
                                <i class="bi bi-building stats-icon-bg"></i>
                            </div>
                            <div class="stats-value">{{ totalTenants }}</div>
                            <div class="stats-footer">
                                <span class="badge badge-success">
                                    <i class="bi bi-arrow-up"></i> {{ activeTenants }} Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="col-6 col-md-3">
                    <div class="stats-card gradient-green">
                        <div class="stats-content">
                            <div class="stats-header">
                                <span class="stats-label">Monthly Revenue</span>
                                <i class="bi bi-currency-dollar stats-icon-bg"></i>
                            </div>
                            <div class="stats-value">Rp {{ formatCurrency(monthlyRevenue) }}</div>
                            <div class="stats-footer">
                                <span class="badge badge-success">
                                    <i class="bi bi-arrow-up"></i> +12.5% vs last month
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Subscriptions -->
                <div class="col-6 col-md-3">
                    <div class="stats-card gradient-purple">
                        <div class="stats-content">
                            <div class="stats-header">
                                <span class="stats-label">Active Subscriptions</span>
                                <i class="bi bi-credit-card stats-icon-bg"></i>
                            </div>
                            <div class="stats-value">{{ activeSubscriptions }}</div>
                            <div class="stats-footer">
                                <span class="badge badge-info">
                                    {{ trialSubscriptions }} on trial
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Users -->
                <div class="col-6 col-md-3">
                    <div class="stats-card gradient-orange">
                        <div class="stats-content">
                            <div class="stats-header">
                                <span class="stats-label">Total Users</span>
                                <i class="bi bi-people stats-icon-bg"></i>
                            </div>
                            <div class="stats-value">{{ totalUsers }}</div>
                            <div class="stats-footer">
                                <span class="badge badge-warning">
                                    Across all tenants
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards Row 2 -->
            <div class="row g-4 mb-4">
                <!-- Subscription Plans -->
                <div class="col-6 col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-star"></i>
                        </div>
                        <div class="stat-info">
                            <h6>Starter Plan</h6>
                            <h4>{{ starterPlanCount }}</h4>
                            <small class="text-muted">Tenants</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-trophy"></i>
                        </div>
                        <div class="stat-info">
                            <h6>Professional Plan</h6>
                            <h4>{{ professionalPlanCount }}</h4>
                            <small class="text-muted">Tenants</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-gem"></i>
                        </div>
                        <div class="stat-info">
                            <h6>Enterprise Plan</h6>
                            <h4>{{ enterprisePlanCount }}</h4>
                            <small class="text-muted">Tenants</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="stat-box">
                        <div class="stat-icon bg-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h6>Expired Trials</h6>
                            <h4>{{ expiredTrials }}</h4>
                            <small class="text-muted">Needs attention</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NOC Jaringan Core & Mitra -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-cpu text-primary me-2"></i>NOC Router (Mikrotik) Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="mb-0 fw-bold">{{ nocStats.routers_online }} <span class="fs-6 text-muted">/ {{ nocStats.routers_total }} Online</span></h3>
                                </div>
                                <span class="badge bg-success" v-if="nocStats.routers_offline === 0">All Online</span>
                                <span class="badge bg-danger" v-else>{{ nocStats.routers_offline }} Offline</span>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" :style="{ width: (nocStats.routers_total ? (nocStats.routers_online / nocStats.routers_total) * 100 : 0) + '%' }"></div>
                                <div class="progress-bar bg-danger" role="progressbar" :style="{ width: (nocStats.routers_total ? (nocStats.routers_offline / nocStats.routers_total) * 100 : 0) + '%' }"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted fs-7">
                                <span><i class="bi bi-circle-fill text-success me-1"></i>{{ nocStats.routers_online }} Online</span>
                                <span><i class="bi bi-circle-fill text-danger me-1"></i>{{ nocStats.routers_offline }} Offline</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-hdd-rack text-info me-2"></i>NOC OLT Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3 class="mb-0 fw-bold">{{ nocStats.olts_online }} <span class="fs-6 text-muted">/ {{ nocStats.olts_total }} Online</span></h3>
                                </div>
                                <span class="badge bg-success" v-if="nocStats.olts_offline === 0">All Online</span>
                                <span class="badge bg-danger" v-else>{{ nocStats.olts_offline }} Offline</span>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-info" role="progressbar" :style="{ width: (nocStats.olts_total ? (nocStats.olts_online / nocStats.olts_total) * 100 : 0) + '%' }"></div>
                                <div class="progress-bar bg-danger" role="progressbar" :style="{ width: (nocStats.olts_total ? (nocStats.olts_offline / nocStats.olts_total) * 100 : 0) + '%' }"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted fs-7">
                                <span><i class="bi bi-circle-fill text-info me-1"></i>{{ nocStats.olts_online }} Online</span>
                                <span><i class="bi bi-circle-fill text-danger me-1"></i>{{ nocStats.olts_offline }} Offline</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts & Tables Row -->
            <div class="row g-4 mb-4">
                <!-- Revenue Chart -->
                <div class="col-lg-8">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title">Revenue Trend</h5>
                            <div class="card-actions">
                                <button class="btn btn-sm btn-outline-secondary">Last 6 Months</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas ref="revenueChartRef"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Status -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title">Tenant Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container-small">
                                <canvas ref="tenantStatusChartRef"></canvas>
                            </div>
                            <div class="status-legend">
                                <div class="legend-item">
                                    <span class="legend-dot bg-success"></span>
                                    <span>Active</span>
                                    <strong>{{ activeTenants }}</strong>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot bg-warning"></span>
                                    <span>Trial</span>
                                    <strong>{{ trialTenants }}</strong>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot bg-danger"></span>
                                    <span>Suspended</span>
                                    <strong>{{ suspendedTenants }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tenants & Activity -->
            <div class="row g-4">
                <!-- Detailed Tenant Monitoring -->
                <div class="col-lg-8">
                    <div class="table-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="bi bi-shield-check text-success me-2"></i>Monitoring Transaksi & Perangkat Mitra</h5>
                            <Link :href="route('superadmin.tenants.index')" class="btn btn-sm btn-link">
                                View All →
                            </Link>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nama Mitra / Subdomain</th>
                                            <th>Total Tagihan Lunas</th>
                                            <th>Total Tagihan Tertunggak</th>
                                            <th>NOC Router (Online)</th>
                                            <th>NOC OLT (Online)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="tenant in tenantList" :key="tenant.id">
                                            <td>
                                                <div class="tenant-info">
                                                    <strong>{{ tenant.name }}</strong>
                                                    <small class="text-muted d-block">{{ tenant.subdomain }}.kitabill.site</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-success fw-bold">Rp {{ formatCurrency(tenant.total_paid) }}</span>
                                                <small class="text-muted d-block">{{ tenant.count_paid }} Invoice</small>
                                            </td>
                                            <td>
                                                <span class="text-danger fw-bold">Rp {{ formatCurrency(tenant.total_unpaid) }}</span>
                                                <small class="text-muted d-block">{{ tenant.count_unpaid }} Invoice</small>
                                            </td>
                                            <td>
                                                <span class="badge" :class="tenant.routers_online === tenant.routers_total && tenant.routers_total > 0 ? 'bg-success' : 'bg-warning'">
                                                    {{ tenant.routers_online }} / {{ tenant.routers_total }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" :class="tenant.olts_online === tenant.olts_total && tenant.olts_total > 0 ? 'bg-success' : 'bg-warning'">
                                                    {{ tenant.olts_online }} / {{ tenant.olts_total }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-4">
                    <div class="activity-card">
                        <div class="card-header">
                            <h5 class="card-title">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <div v-for="activity in recentActivity" :key="activity.id" class="activity-item">
                                    <div class="activity-icon" :class="getActivityIconClass(activity.type)">
                                        <i :class="getActivityIcon(activity.type)"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p class="activity-text">{{ activity.description }}</p>
                                        <small class="activity-time">{{ formatTime(activity.created_at) }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    totalTenants: Number,
    activeTenants: Number,
    trialTenants: Number,
    suspendedTenants: Number,
    monthlyRevenue: Number,
    activeSubscriptions: Number,
    trialSubscriptions: Number,
    totalUsers: Number,
    starterPlanCount: Number,
    professionalPlanCount: Number,
    enterprisePlanCount: Number,
    expiredTrials: Number,
    revenueData: Array,
    recentTenants: Array,
    recentActivity: Array,
    nocStats: Object,
    tenantList: Array,
});

const revenueChartRef = ref(null);
const tenantStatusChartRef = ref(null);

onMounted(() => {
    initCharts();
});

const initCharts = () => {
    // Revenue Chart
    if (revenueChartRef.value) {
        new Chart(revenueChartRef.value, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue',
                    data: props.revenueData || [1200000, 1900000, 1500000, 2100000, 2400000, 2800000],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => 'Rp ' + (value / 1000000).toFixed(1) + 'M'
                        }
                    }
                }
            }
        });
    }

    // Tenant Status Chart
    if (tenantStatusChartRef.value) {
        new Chart(tenantStatusChartRef.value, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Trial', 'Suspended'],
                datasets: [{
                    data: [props.activeTenants, props.trialTenants, props.suspendedTenants],
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatTime = (date) => {
    const now = new Date();
    const past = new Date(date);
    const diff = Math.floor((now - past) / 1000); // seconds

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    return Math.floor(diff / 86400) + ' days ago';
};

const getPlanBadgeClass = (plan) => {
    const classes = {
        'Starter': 'bg-primary',
        'Professional': 'bg-success',
        'Enterprise': 'bg-warning'
    };
    return classes[plan] || 'bg-secondary';
};

const getStatusBadgeClass = (status) => {
    const classes = {
        'active': 'bg-success',
        'trial': 'bg-info',
        'suspended': 'bg-danger',
        'expired': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
};

const getActivityIconClass = (type) => {
    const classes = {
        'new_tenant': 'bg-primary',
        'subscription': 'bg-success',
        'payment': 'bg-info',
        'suspension': 'bg-danger'
    };
    return classes[type] || 'bg-secondary';
};

const getActivityIcon = (type) => {
    const icons = {
        'new_tenant': 'bi bi-building',
        'subscription': 'bi bi-credit-card',
        'payment': 'bi bi-cash',
        'suspension': 'bi bi-exclamation-triangle'
    };
    return icons[type] || 'bi bi-info-circle';
};
</script>

<style scoped>
/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 32px;
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

.welcome-actions .btn {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    backdrop-filter: blur(10px);
    font-weight: 600;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.welcome-actions .btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
}

/* Stats Cards - Gradient Style */
.stats-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
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

.stats-card.gradient-purple::before {
    background: linear-gradient(90deg, #8B5CF6 0%, #7C3AED 100%);
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

.badge-success {
    background: #D1FAE5;
    color: #065F46;
}

.badge-info {
    background: #DBEAFE;
    color: #1E40AF;
}

.badge-warning {
    background: #FEF3C7;
    color: #92400E;
}

/* Stat Box - Simple Style */
.stat-box {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.stat-box:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

:global(.dark) .stat-box {
    background: #1E293B;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-info h6 {
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    margin-bottom: 8px;
}

:global(.dark) .stat-info h6 {
    color: #94A3B8;
}

.stat-info h4 {
    font-size: 24px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

:global(.dark) .stat-info h4 {
    color: #F1F5F9;
}

.stat-info small {
    font-size: 12px;
}

/* Chart Card */
.chart-card,
.table-card,
.activity-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .chart-card,
:global(.dark) .table-card,
:global(.dark) .activity-card {
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

.card-body {
    padding: 24px;
}

.chart-container {
    height: 300px;
    position: relative;
}

.chart-container-small {
    height: 200px;
    position: relative;
}

/* Status Legend */
.status-legend {
    margin-top: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.legend-item span:nth-child(2) {
    flex: 1;
    color: #64748B;
}

:global(.dark) .legend-item span:nth-child(2) {
    color: #94A3B8;
}

.legend-item strong {
    color: #1E293B;
}

:global(.dark) .legend-item strong {
    color: #F1F5F9;
}

/* Table */
.table {
    color: #1E293B;
}

:global(.dark) .table {
    color: #F1F5F9;
}

.table thead th {
    background: #F8FAFC;
    border-bottom: 2px solid #E2E8F0;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    padding: 12px 16px;
}

:global(.dark) .table thead th {
    background: #0F172A;
    border-bottom-color: #334155;
    color: #94A3B8;
}

.table tbody td {
    padding: 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
}

:global(.dark) .table tbody td {
    border-bottom-color: #334155;
}

.tenant-info strong {
    display: block;
    font-size: 14px;
    color: #1E293B;
}

:global(.dark) .tenant-info strong {
    color: #F1F5F9;
}

.tenant-info small {
    font-size: 12px;
}

/* Activity Timeline */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.activity-item {
    display: flex;
    gap: 16px;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-size: 18px;
}

.activity-content {
    flex: 1;
}

.activity-text {
    font-size: 14px;
    color: #1E293B;
    margin-bottom: 4px;
}

:global(.dark) .activity-text {
    color: #E2E8F0;
}

.activity-time {
    font-size: 12px;
    color: #64748B;
}

:global(.dark) .activity-time {
    color: #94A3B8;
}

/* Responsive */
@media (max-width: 768px) {
    .welcome-banner {
        flex-direction: column;
        align-items: stretch;
        text-align: left;
        gap: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .welcome-title {
        font-size: 20px;
    }

    .welcome-subtitle {
        font-size: 13px;
    }

    .welcome-actions .btn {
        width: 100%;
        justify-content: center;
    }

    .stats-card {
        padding: 16px;
    }

    .stats-value {
        font-size: 18px;
        margin-bottom: 4px;
    }

    .stats-label {
        font-size: 11px;
    }

    .stats-icon-bg {
        font-size: 22px;
    }

    .stats-footer {
        font-size: 10px;
    }

    .stats-footer .badge {
        font-size: 10px;
        padding: 2px 6px;
    }

    .stat-box {
        padding: 12px;
        gap: 10px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
        border-radius: 8px;
    }

    .stat-info h6 {
        font-size: 11px;
        margin-bottom: 4px;
    }

    .stat-info h4 {
        font-size: 16px;
    }

    .card-header {
        padding: 16px;
    }

    .card-body {
        padding: 16px;
    }

    .chart-container {
        height: 220px;
    }

    .chart-container-small {
        height: 180px;
    }
}
</style>



