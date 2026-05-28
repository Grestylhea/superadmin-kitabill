<template>
    <SuperAdminLayout>
        <Head title="WhatsApp Gateway Monitoring" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h3 class="page-title">WhatsApp Gateway Monitoring</h3>
                    <p class="page-subtitle">Monitor performance and manage sessions for all tenants</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="d-flex align-items-center me-3 px-3 py-1 bg-light rounded border border-warning shadow-sm" v-if="isAnyPaused">
                        <span class="badge bg-warning rounded-circle me-2 animate-ping small" style="width: 10px; height: 10px;">&nbsp;</span>
                        <span class="small fw-bold text-dark">Circuit Breaker Active</span>
                    </div>
                    <button @click="handleRestartService(-1)" class="btn btn-outline-danger" :disabled="loading" title="Restart Global Gateway Service">
                        <i class="bi bi-power"></i> Restart Service
                    </button>
                    <button @click="refreshData" class="btn btn-primary" :disabled="loading">
                        <i class="bi bi-arrow-clockwise" :class="{ 'spin': loading }"></i>
                        Refresh Status
                    </button>
                </div>
            </div>

            <!-- Status Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-server"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value">{{ totalGateways }}</div>
                            <div class="stat-label">Total Gateways</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value">{{ connectedCount }}</div>
                            <div class="stat-label">Connected Sessions</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value">{{ errorCount }}</div>
                            <div class="stat-label">Critical Issues</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gateways Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th @click="toggleSort('tenant_name')" class="sortable">Tenant Info <i :class="getSortIcon('tenant_name')"></i></th>
                                <th @click="toggleSort('success_today')" class="sortable text-center">Metrics (Today) <i :class="getSortIcon('success_today')"></i></th>
                                <th>WhatsApp Account</th>
                                <th>Gateway State</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="gateway in sortedGateways" :key="gateway.tenant_id" :class="{ 'table-warning': gateway.paused }">
                                <td>
                                    <div class="fw-bold text-primary">{{ gateway.tenant_name }}</div>
                                    <div class="small text-muted">{{ gateway.tenant_domain }}</div>
                                    <div class="badge bg-light text-dark border-0 p-0 fs-7" style="font-size: 0.7rem;">#{{ gateway.tenant_id === -1 ? 'SA' : gateway.tenant_id }}</div>
                                </td>
                                <td class="text-center">
                                    <div v-if="gateway.paused" class="mb-1">
                                        <span class="badge bg-warning text-dark animate-pulse">
                                            <i class="bi bi-pause-fill"></i> PAUSED
                                        </span>
                                        <div class="small text-danger mt-1" style="font-size: 0.65rem;">{{ gateway.pause_reason }}</div>
                                    </div>
                                    <div class="d-flex justify-content-center gap-2">
                                        <div class="metric-box bg-success-subtle">
                                            <div class="metric-val text-success">{{ gateway.success_today || 0 }}</div>
                                            <div class="metric-label">Sent</div>
                                        </div>
                                        <div class="metric-box bg-danger-subtle">
                                            <div class="metric-val text-danger">{{ gateway.failed_today || 0 }}</div>
                                            <div class="metric-label">Fail</div>
                                        </div>
                                        <div class="metric-box bg-warning-subtle">
                                            <div class="metric-val text-warning">{{ gateway.locked_today || 0 }}</div>
                                            <div class="metric-label">Lock</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div v-if="gateway.phone_number" class="d-flex flex-column">
                                        <span class="fw-bold text-success">
                                            <i class="bi bi-whatsapp"></i> {{ gateway.phone_number }}
                                        </span>
                                        <small v-if="gateway.uptime" class="text-muted" style="font-size: 0.7rem;">
                                            Up: {{ gateway.uptime }}
                                        </small>
                                    </div>
                                    <span v-else class="text-muted small italic">Not Linked</span>
                                </td>
                                <td>
                                    <span :class="getStateClass(gateway.gateway_state)">
                                        <i :class="getStateIcon(gateway.gateway_state)"></i>
                                        {{ formatState(gateway.gateway_state) }}
                                    </span>
                                    <div v-if="gateway.zombie" class="text-danger small mt-1" style="font-size: 0.65rem;">
                                        <i class="bi bi-exclamation-triangle"></i> Session Locked/Zombie
                                    </div>
                                    <div v-if="gateway.last_activity" class="text-muted small mt-1" style="font-size: 0.65rem;">
                                        Last Act: {{ formatTime(gateway.last_activity) }}
                                    </div>
                                    <div v-if="gateway.engine" class="mt-1">
                                        <span :class="gateway.engine === 'wakita' ? 'badge bg-info' : (gateway.engine === 'whatsmeow' ? 'badge bg-primary' : 'badge bg-dark')" style="font-size: 0.65rem; opacity: 0.8;">
                                            Driver: {{ gateway.engine === 'wakita' ? 'Cloud (WAKita)' : (gateway.engine === 'whatsmeow' ? 'Go (Whatsmeow)' : 'Node (Baileys)') }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button 
                                            @click="showQr(gateway.tenant_id)" 
                                            class="btn btn-sm btn-info"
                                            :disabled="gateway.gateway_state === 'connected'"
                                            title="Scan QR Code"
                                        >
                                            <i class="bi bi-qr-code"></i>
                                        </button>
                                        <button 
                                            @click="reconnectGateway(gateway.tenant_id)" 
                                            class="btn btn-sm btn-warning"
                                            :disabled="actionLoading[gateway.tenant_id]"
                                            title="Reconnect (Soft Reset)"
                                        >
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button
                                            @click="resetSession(gateway.tenant_id)"
                                            class="btn btn-sm btn-danger"
                                            :disabled="actionLoading[gateway.tenant_id]"
                                            title="Reset Session (Hard Reset / Ganti Nomor)"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button 
                                            @click="showLogs(gateway.tenant_id)" 
                                            class="btn btn-sm btn-dark"
                                            title="View Logs"
                                        >
                                            <i class="bi bi-file-text"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="sortedGateways.length === 0">
                                <td colspan="7" class="text-center text-muted py-4">
                                    No gateway instances found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- QR Code Modal -->
            <div v-if="showQrModal" class="modal show d-block" tabindex="-1" @click.self="closeQrModal">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">QR Code - Tenant {{ currentTenantId }}</h5>
                            <button type="button" class="btn-close" @click="closeQrModal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div v-if="qrLoading" class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div v-else-if="qrData && qrData.qrImage">
                                <p class="text-muted mb-3">Scan this QR code with WhatsApp to connect</p>
                                <img :src="qrData.qrImage" alt="QR Code" class="img-fluid" style="max-width: 300px;">
                                <p v-if="qrData.qrTimestamp" class="text-muted mt-2 small">
                                    Generated: {{ formatTime(qrData.qrTimestamp) }}
                                </p>
                            </div>
                            <div v-else-if="qrData && qrData.connected">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i>
                                    Already Connected
                                    <div v-if="qrData.phoneNumber" class="mt-2">
                                        Phone: {{ qrData.phoneNumber }}
                                    </div>
                                </div>
                            </div>
                            <div v-else class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                {{ qrData?.message || 'QR code not available' }}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeQrModal">Close</button>
                            <button v-if="qrData && !qrData.connected" type="button" class="btn btn-primary" @click="refreshQr">
                                Refresh QR
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Modal -->
            <div v-if="showLogsModal" class="modal show d-block" tabindex="-1" @click.self="closeLogsModal">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Logs - Tenant {{ currentTenantId }}</h5>
                            <button type="button" class="btn-close" @click="closeLogsModal"></button>
                        </div>
                        <div class="modal-body">
                            <div v-if="logsLoading" class="text-center py-4">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <pre v-else-if="logsData" class="logs-content">{{ logsData }}</pre>
                            <div v-else class="alert alert-warning">
                                No logs available
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeLogsModal">Close</button>
                            <button type="button" class="btn btn-primary" @click="refreshLogs">
                                Refresh Logs
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import axios from 'axios';

const gateways = ref([]);
const metricsData = ref({});
const loading = ref(false);
const showQrModal = ref(false);
const showLogsModal = ref(false);
const currentTenantId = ref(null);
const qrData = ref(null);
const qrLoading = ref(false);
const logsData = ref(null);
const logsLoading = ref(false);
const actionLoading = ref({});
let refreshInterval = null;

// Sorting state
const sortBy = ref('tenant_id');
const sortOrder = ref('asc');

const totalGateways = computed(() => gateways.value.length);
const connectedCount = computed(() => gateways.value.filter(g => g.gateway_state === 'connected' || g.gateway_state === 'authenticated').length);
const errorCount = computed(() => gateways.value.filter(g => g.service_status !== 'active' || g.gateway_state === 'unreachable').length);

const isAnyPaused = computed(() => gateways.value.some(g => g.paused));

const sortedGateways = computed(() => {
    return [...gateways.value].sort((a, b) => {
        let valA = a[sortBy.value];
        let valB = b[sortBy.value];
        
        // Handle undefined/null
        if (valA === undefined || valA === null) valA = 0;
        if (valB === undefined || valB === null) valB = 0;
        
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();
        
        if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const toggleSort = (field) => {
    if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortOrder.value = 'desc'; // Default to desc for metrics
    }
};

const getSortIcon = (field) => {
    if (sortBy.value !== field) return 'bi bi-arrow-down-up text-muted small';
    return sortOrder.value === 'asc' ? 'bi bi-sort-up text-primary' : 'bi bi-sort-down text-primary';
};

const refreshData = async () => {
    loading.value = true;
    try {
        const [statusRes, metricsRes] = await Promise.all([
            axios.get(route('superadmin.wa-gateways.status')),
            axios.get(route('superadmin.wa-gateways.metrics'))
        ]);
        
        if (statusRes.data.success && metricsRes.data.success) {
            const metricsMap = metricsRes.data.data.reduce((acc, m) => {
                acc[m.tenant_id] = m;
                return acc;
            }, {});

            gateways.value = statusRes.data.data.map(g => ({
                ...g,
                ...(metricsMap[g.tenant_id] || {})
            }));
        }
    } catch (error) {
        console.error('Failed to fetch gateway data:', error);
    } finally {
        loading.value = false;
    }
};

const showQr = async (tenantId) => {
    currentTenantId.value = tenantId;
    showQrModal.value = true;
    qrLoading.value = true;
    qrData.value = null;
    
    try {
        const response = await axios.get(route('superadmin.wa-gateways.qr', tenantId));
        qrData.value = response.data;
    } catch (error) {
        qrData.value = { message: 'Failed to load QR code: ' + (error.response?.data?.message || error.message) };
    } finally {
        qrLoading.value = false;
    }
};

const refreshQr = () => {
    if (currentTenantId.value) {
        showQr(currentTenantId.value);
    }
};

const closeQrModal = () => {
    showQrModal.value = false;
    qrData.value = null;
    currentTenantId.value = null;
};

const reconnectGateway = async (tenantId) => {
    if (!confirm('Are you sure you want to reconnect this gateway? This will disconnect current session.')) {
        return;
    }
    
    actionLoading.value[tenantId] = true;
    try {
        const response = await axios.post(route('superadmin.wa-gateways.reconnect', tenantId));
        if (response.data.success) {
            alert('Gateway reconnected successfully');
            await refreshData();
        } else {
            alert('Failed to reconnect: ' + (response.data.message || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + (error.response?.data?.message || error.message));
    } finally {
        actionLoading.value[tenantId] = false;
    }
};

const getStateClass = (state) => {
    const classes = {
        'connected': 'badge bg-success',
        'authenticated': 'badge bg-success',
        'waiting_qr': 'badge bg-info',
        'initializing': 'badge bg-primary',
        'disconnected': 'badge bg-danger',
    };
    return classes[state] || 'badge bg-secondary';
};

const getStateIcon = (state) => {
    const icons = {
        'connected': 'bi bi-check-circle-fill',
        'authenticated': 'bi bi-shield-check',
        'waiting_qr': 'bi bi-qr-code',
        'initializing': 'bi bi-hourglass-split',
        'disconnected': 'bi bi-x-circle-fill',
    };
    return icons[state] || 'bi bi-question-circle';
};

const formatState = (state) => {
    if (!state) return 'Unknown';
    return state.charAt(0).toUpperCase() + state.slice(1).replace('_', ' ');
};

const getServiceStatusClass = (status) => {
    if (status === 'active') return 'badge bg-success';
    if (status === 'failed' || status === 'inactive') return 'badge bg-danger';
    return 'badge bg-secondary';
};

const handleRestartService = (id) => {
    if (confirm('Are you sure you want to RESTART this systemd service? This will temporarily disconnect the gateway.')) {
        restartService(id);
    }
};

const resetSession = async (id) => {
    if (!confirm('CRITICAL ACTION: This will delete the session data and DISCONNECT the number. You will need to scan a new QR code. Proceed?')) {
        return;
    }
    
    actionLoading.value[id] = true;
    try {
        const response = await axios.post(route('superadmin.wa-gateways.reset-session', id));
        alert(response.data.message || 'Session direset.');
        await refreshData();
    } catch (error) {
        alert('Failed to reset session: ' + (error.response?.data?.message || error.message));
    } finally {
        actionLoading.value[id] = false;
    }
};

const restartService = async (id) => {
    actionLoading.value[id] = true;
    try {
        const response = await axios.post(route('superadmin.wa-gateways.restart', id));
        if (response.data.success) {
            alert('Service restart command sent.');
            await refreshData();
        } else {
            alert('Failed to restart: ' + (response.data.message || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + (error.response?.data?.message || error.message));
    } finally {
        actionLoading.value[id] = false;
    }
};

const showLogs = async (tenantId) => {
    currentTenantId.value = tenantId;
    showLogsModal.value = true;
    logsLoading.value = true;
    logsData.value = null;
    
    try {
        const response = await axios.get(route('superadmin.wa-gateways.logs', tenantId));
        if (response.data.success) {
            logsData.value = response.data.logs;
        } else {
            logsData.value = 'Failed to load logs: ' + (response.data.message || 'Unknown error');
        }
    } catch (error) {
        logsData.value = 'Error: ' + (error.response?.data?.message || error.message);
    } finally {
        logsLoading.value = false;
    }
};

const refreshLogs = () => {
    if (currentTenantId.value) {
        showLogs(currentTenantId.value);
    }
};

const closeLogsModal = () => {
    showLogsModal.value = false;
    logsData.value = null;
    currentTenantId.value = null;
};



const getServiceStatusIcon = (status) => {
    const icons = {
        'active': 'bi bi-check-circle',
        'inactive': 'bi bi-x-circle',
        'failed': 'bi bi-x-circle',
        'unknown': 'bi bi-question-circle',
    };
    return icons[status] || 'bi bi-question-circle';
};

const formatTime = (time) => {
    if (!time) return '-';
    try {
        const date = new Date(time);
        return date.toLocaleString('id-ID', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return time;
    }
};

onMounted(() => {
    refreshData();
    // Auto-refresh setiap 15 detik (sesuai cache backend)
    refreshInterval = setInterval(refreshData, 15000);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<style scoped>
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    margin-right: 15px;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 4px;
}

.logs-content {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 500px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
}

.modal.show {
    background: rgba(0,0,0,0.5);
}

.subdomain-link {
    color: #0d6efd;
    text-decoration: none;
}

.subdomain-link:hover {
    text-decoration: underline;
}

.sortable {
    cursor: pointer;
    user-select: none;
}

.sortable:hover {
    background-color: rgba(0,0,0,0.03);
}

.metric-box {
    padding: 2px 6px;
    border-radius: 4px;
    min-width: 45px;
    text-align: center;
}

.metric-val {
    font-size: 0.85rem;
    font-weight: bold;
    line-height: 1.1;
}

.metric-label {
    font-size: 0.6rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}

.animate-ping {
    animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}
</style>













