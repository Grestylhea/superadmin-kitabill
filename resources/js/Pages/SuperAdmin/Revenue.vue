<template>
    <SuperAdminLayout>
        <Head title="Revenue & Billing" />
        
        <div class="container-fluid py-4">
            <!-- Header with Scope Toggle -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Revenue & Billing</h2>
                    <p class="text-muted mb-0">Track revenue and billing analytics</p>
                </div>
                
                <!-- Scope Toggle -->
                <div class="btn-group" role="group">
                    <Link 
                        :href="route('superadmin.revenue', { scope: 'subscriptions' })"
                        class="btn"
                        :class="scope === 'subscriptions' ? 'btn-primary' : 'btn-outline-primary'"
                    >
                        <i class="bi bi-building me-2"></i>Subscriptions
                    </Link>
                    <Link 
                        :href="route('superadmin.revenue', { scope: 'customers' })"
                        class="btn"
                        :class="scope === 'customers' ? 'btn-primary' : 'btn-outline-primary'"
                    >
                        <i class="bi bi-people me-2"></i>Customers
                    </Link>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Revenue (This Month)</div>
                            <div class="stat-value">{{ formatCurrency(stats.totalRevenueMonth) }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-calendar-day"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Revenue (Today)</div>
                            <div class="stat-value">{{ formatCurrency(stats.totalRevenueToday) }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Paid Invoices</div>
                            <div class="stat-value">{{ stats.totalPaidInvoices }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Unpaid Invoices</div>
                            <div class="stat-value">{{ stats.totalUnpaidInvoices }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" v-if="scope === 'subscriptions'">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Active Subscriptions</div>
                            <div class="stat-value">{{ stats.activeSubscriptions }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" v-if="scope === 'subscriptions'">
                    <div class="stat-card">
                        <div class="stat-icon bg-secondary">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Trial Subscriptions</div>
                            <div class="stat-value">{{ stats.trialSubscriptions }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Trend Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Revenue Trend (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <div class="revenue-chart">
                        <div v-for="item in monthlyRevenue" :key="item.month" class="chart-bar">
                            <div class="bar-wrapper">
                                <div 
                                    class="bar" 
                                    :style="{ height: getBarHeight(item.revenue) + '%' }"
                                    :title="formatCurrency(item.revenue)"
                                ></div>
                            </div>
                            <div class="bar-label">{{ item.month }}</div>
                            <div class="bar-value">{{ formatCurrency(item.revenue, true) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Breakdown -->
            <div class="row g-4 mb-4">
                <!-- Revenue by Plan/Package -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                Revenue by {{ scope === 'subscriptions' ? 'Plan' : 'Package' }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div v-if="revenueByPlan.length === 0" class="text-center text-muted py-4">
                                No data available
                            </div>
                            <div v-else class="breakdown-list">
                                <div v-for="item in revenueByPlan" :key="item.name" class="breakdown-item">
                                    <div class="breakdown-name">
                                        {{ item.name }}
                                        <span class="text-muted ms-2">({{ item.count }} invoices)</span>
                                    </div>
                                    <div class="breakdown-value">{{ formatCurrency(item.revenue) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Methods</h5>
                        </div>
                        <div class="card-body">
                            <div v-if="paymentMethods.length === 0" class="text-center text-muted py-4">
                                No data available
                            </div>
                            <div v-else class="breakdown-list">
                                <div v-for="item in paymentMethods" :key="item.payment_method" class="breakdown-item">
                                    <div class="breakdown-name">
                                        {{ item.payment_method || 'Unknown' }}
                                        <span class="text-muted ms-2">({{ item.count }} payments)</span>
                                    </div>
                                    <div class="breakdown-value">{{ formatCurrency(item.total) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Tenants (Subscriptions only) -->
            <div v-if="scope === 'subscriptions' && topTenants.length > 0" class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Top Tenants by Revenue</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tenant</th>
                                    <th>Subdomain</th>
                                    <th>Plan</th>
                                    <th class="text-end">Invoices</th>
                                    <th class="text-end">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tenant in topTenants" :key="tenant.id">
                                    <td>{{ tenant.name }}</td>
                                    <td>
                                        <code>{{ tenant.subdomain }}</code>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ tenant.subscription_plan }}</span>
                                    </td>
                                    <td class="text-end">{{ tenant.invoice_count }}</td>
                                    <td class="text-end fw-bold">{{ formatCurrency(tenant.total_revenue) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Invoices</h5>
                </div>
                <div class="card-body">
                    <div v-if="recentInvoices.length === 0" class="text-center text-muted py-4">
                        No invoices found
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice #</th>
                                    <th>{{ scope === 'subscriptions' ? 'Tenant' : 'Customer' }}</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="invoice in recentInvoices" :key="invoice.id">
                                    <td>{{ formatDate(invoice.paid_at || invoice.issue_date) }}</td>
                                    <td><code>{{ invoice.invoice_number }}</code></td>
                                    <td>
                                        <div>{{ invoice.entity_name }}</div>
                                        <small class="text-muted">{{ invoice.entity_code }}</small>
                                    </td>
                                    <td class="fw-bold">{{ formatCurrency(invoice.total) }}</td>
                                    <td>
                                        <span v-if="invoice.payment_method" class="badge bg-info">
                                            {{ invoice.payment_method }}
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span 
                                            class="badge"
                                            :class="{
                                                'bg-success': invoice.status === 'paid',
                                                'bg-warning': invoice.status === 'unpaid',
                                                'bg-danger': invoice.status === 'overdue',
                                                'bg-secondary': invoice.status === 'cancelled'
                                            }"
                                        >
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    scope: String,
    stats: Object,
    monthlyRevenue: Array,
    revenueByPlan: Array,
    topTenants: Array,
    paymentMethods: Array,
    recentInvoices: Array,
    startDate: String,
    endDate: String,
});

const maxRevenue = computed(() => {
    const revenues = props.monthlyRevenue.map(item => item.revenue);
    return Math.max(...revenues, 1);
});

function getBarHeight(revenue) {
    return (revenue / maxRevenue.value) * 100;
}

function formatCurrency(amount, short = false) {
    if (!amount) return 'Rp 0';
    
    if (short && amount >= 1000000) {
        return 'Rp ' + (amount / 1000000).toFixed(1) + 'M';
    }
    
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
</script>

<style scoped>
/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 13px;
    color: #64748B;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1E293B;
}

/* Revenue Chart */
.revenue-chart {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 300px;
    padding: 20px 0;
}

.chart-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.bar-wrapper {
    width: 100%;
    height: 240px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.bar {
    width: 100%;
    max-width: 40px;
    background: linear-gradient(180deg, #10B981 0%, #059669 100%);
    border-radius: 6px 6px 0 0;
    transition: all 0.3s;
    cursor: pointer;
}

.bar:hover {
    background: linear-gradient(180deg, #059669 0%, #047857 100%);
    transform: scaleY(1.05);
}

.bar-label {
    font-size: 11px;
    color: #64748B;
    font-weight: 500;
    white-space: nowrap;
}

.bar-value {
    font-size: 10px;
    color: #94A3B8;
    font-weight: 600;
}

/* Breakdown List */
.breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #F8FAFC;
    border-radius: 8px;
    transition: background 0.2s;
}

.breakdown-item:hover {
    background: #F1F5F9;
}

.breakdown-name {
    font-weight: 500;
    color: #1E293B;
}

.breakdown-value {
    font-weight: 700;
    color: #10B981;
    font-size: 16px;
}

/* Dark Mode */
:global(.dark) .stat-card {
    background: #1E293B;
}

:global(.dark) .stat-value {
    color: #F1F5F9;
}

:global(.dark) .breakdown-item {
    background: #0F172A;
}

:global(.dark) .breakdown-item:hover {
    background: #1E293B;
}

:global(.dark) .breakdown-name {
    color: #F1F5F9;
}

:global(.dark) .card {
    background: #1E293B;
    border-color: #334155;
}

:global(.dark) .card-header {
    background: #0F172A;
    border-color: #334155;
}

:global(.dark) .table {
    color: #F1F5F9;
}

:global(.dark) .table thead th {
    border-color: #334155;
}

:global(.dark) .table tbody td {
    border-color: #334155;
}
</style>
