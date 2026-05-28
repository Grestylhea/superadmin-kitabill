<template>
    <SuperAdminLayout>
        <Head title="Database Backups" />
        
        <div class="container-fluid">
            <!-- Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2 class="welcome-title">Database Backups 💾</h2>
                    <p class="welcome-subtitle">Manage and download your automated database backups. Backups are created daily at 22:00 and kept for 7 days.</p>
                </div>
            </div>

            <!-- Backups Table -->
            <div class="row g-4">
                <div class="col-12">
                    <div class="table-card">
                        <div class="card-header">
                            <h5 class="card-title">Available Backups</h5>
                            <div class="card-actions">
                                <span class="badge bg-info text-dark">Retention: 7 Days</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="backups.length === 0">
                                            <td colspan="4" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="bi bi-cloud-slash fs-2 d-block mb-3"></i>
                                                    No backups found yet. They will appear here once the scheduled task runs.
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-for="backup in backups" :key="backup.filename">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-clock-history me-2 text-primary"></i>
                                                    <span>{{ backup.date }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-dark">{{ backup.filename }}</code>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ backup.size }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a :href="route('superadmin.backups.download', { filename: backup.filename })" 
                                                   class="btn btn-sm btn-outline-primary download-btn">
                                                    <i class="bi bi-download me-1"></i>
                                                    Download
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light px-4 py-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Backups are stored securely on the VPS. Only the last 7 daily backups are retained.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    backups: Array,
});
</script>

<style scoped>
.welcome-banner {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 32px;
    color: white;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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

.table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .table-card {
    background: #1e293b;
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
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
    margin: 0;
}

.table thead th {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    padding: 12px 16px;
}

:global(.dark) .table thead th {
    background: #0f172a;
    border-bottom-color: #334155;
    color: #94a3b8;
}

.table tbody td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

:global(.dark) .table tbody td {
    border-bottom-color: #334155;
    color: #f1f5f9;
}

.download-btn {
    border-radius: 8px;
    padding: 6px 16px;
    font-weight: 600;
    transition: all 0.2s;
}

.download-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
}

code {
    padding: 4px 8px;
    background: #f1f5f9;
    border-radius: 6px;
    font-size: 0.9em;
}

:global(.dark) code {
    background: #0f172a;
    color: #e2e8f0 !important;
}
</style>
