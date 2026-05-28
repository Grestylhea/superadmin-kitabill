<template>
    <SuperAdminLayout>
        <Head title="Bulk Messages" />

        <div class="content-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 text-primary fw-bold">Bulk Messages</h2>
                <p class="text-muted mb-0">Send announcements to tenants via WhatsApp</p>
            </div>
            <Link :href="route('superadmin.bulk-messages.create')" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-plus-lg"></i>
                <span>New Message</span>
            </Link>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">Title</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Progress</th>
                                <th class="px-4 py-3">Created At</th>
                                <th class="px-4 py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="campaign in campaigns.data" :key="campaign.id">
                                <td class="px-4">
                                    <div class="fw-bold text-dark">{{ campaign.title }}</div>
                                    <small class="text-muted">By: {{ campaign.creator?.name }}</small>
                                </td>
                                <td class="px-4">
                                    <span class="badge bg-info bg-opacity-10 text-info">
                                        {{ campaign.total_targets }} Tenants
                                    </span>
                                </td>
                                <td class="px-4">
                                    <span class="badge" :class="getStatusClass(campaign.status)">
                                        {{ campaign.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4">
                                    <div class="d-flex align-items-center gap-2" style="width: 150px;">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar" 
                                                :class="getProgressBarClass(campaign.status)"
                                                :style="{ width: getProgress(campaign) + '%' }">
                                            </div>
                                        </div>
                                        <small class="text-muted" style="width: 35px; text-align: right;">
                                            {{ getProgress(campaign) }}%
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2 mt-1" style="font-size: 11px;">
                                        <span class="text-success">{{ campaign.sent_count }} sent</span>
                                        <span class="text-danger" v-if="campaign.failed_count > 0">{{ campaign.failed_count }} failed</span>
                                    </div>
                                </td>
                                <td class="px-4 text-muted">
                                    {{ formatDate(campaign.created_at) }}
                                </td>
                                <td class="px-4 text-end">
                                    <Link :href="route('superadmin.bulk-messages.show', campaign.id)" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="campaigns.data.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-chat-square-text fs-1 d-block mb-3 opacity-25"></i>
                                    No bulk messages found. start by creating one.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top-0 py-3" v-if="campaigns.data.length > 0">
                <!-- Pagination component could be added here -->
                 <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ campaigns.from }} to {{ campaigns.to }} of {{ campaigns.total }} results
                    </div>
                    
                    <div class="d-flex gap-1" v-if="campaigns.links.length > 3">
                        <template v-for="(link, k) in campaigns.links" :key="k">
                            <Link v-if="link.url" 
                                :href="link.url" 
                                class="btn btn-sm"
                                :class="{'btn-primary': link.active, 'btn-outline-light text-dark': !link.active}"
                                v-html="link.label">
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { format } from 'date-fns';

const props = defineProps({
    campaigns: Object
});

const getStatusClass = (status) => {
    switch(status) {
        case 'done': return 'bg-success bg-opacity-10 text-success';
        case 'processing': return 'bg-warning bg-opacity-10 text-warning';
        case 'failed': return 'bg-danger bg-opacity-10 text-danger';
        default: return 'bg-secondary bg-opacity-10 text-secondary';
    }
};

const getProgressBarClass = (status) => {
    switch(status) {
        case 'done': return 'bg-success';
        case 'failed': return 'bg-danger';
        default: return 'bg-primary';
    }
};

const getProgress = (campaign) => {
    if (campaign.total_targets === 0) return 0;
    const processed = campaign.sent_count + campaign.failed_count;
    return Math.round((processed / campaign.total_targets) * 100);
};

const formatDate = (date) => {
    if (!date) return '-';
    return format(new Date(date), 'dd MMM yyyy HH:mm');
};
</script>
