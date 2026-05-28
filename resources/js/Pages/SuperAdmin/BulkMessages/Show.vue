<template>
    <SuperAdminLayout>
        <Head title="Bulk Message Details" />

        <div class="content-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <Link :href="route('superadmin.bulk-messages.index')" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="bi bi-arrow-left"></i> Back to List
                </Link>
                <h2 class="mb-0 text-primary fw-bold">{{ campaign?.title }}</h2>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <span class="badge" :class="getStatusClass(campaign?.status)">{{ campaign?.status?.toUpperCase() }}</span>
                    <small class="text-muted">Created by {{ campaign?.creator?.name }} on {{ formatDate(campaign?.created_at) }}</small>
                </div>
            </div>
            <div>
                <div v-if="canProcess" class="d-inline-block me-2">
                    <button v-if="!isProcessing" class="btn btn-success" @click="startProcessing">
                        <i class="bi bi-play-fill"></i> Start Sending (Browser)
                    </button>
                    <button v-else class="btn btn-warning text-white" @click="stopProcessing">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        <i class="bi bi-pause-fill"></i> Pause Sending
                    </button>
                </div>
                
                <button class="btn btn-outline-secondary" @click="refreshPage" :disabled="isProcessing">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Status
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <!-- Summary Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Campaign Summary</h6>
                        
                        <div class="mb-4">
                            <label class="small text-muted text-uppercase fw-bold">Progress</label>
                            <div class="progress mt-1" style="height: 10px;">
                                <div class="progress-bar" 
                                    :class="getProgressBarClass(campaign?.status)"
                                    :style="{ width: progress + '%' }">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1 small">
                                <span>{{ progress }}% Complete</span>
                                <span v-if="isProcessing" class="text-primary fw-bold">
                                    <span class="spinner-grow spinner-grow-sm me-1" style="width: 0.5rem; height: 0.5rem"></span>
                                    {{ processingMessage }}
                                </span>
                                <span v-else>{{ campaign?.total_targets }} Total</span>
                            </div>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <rs-stat-box
                                    label="Sent"
                                    :value="campaign?.sent_count"
                                    text-class="text-success"
                                />
                            </div>
                            <div class="col-4">
                                <rs-stat-box
                                    label="Failed"
                                    :value="campaign?.failed_count"
                                    text-class="text-danger"
                                />
                            </div>
                            <div class="col-4">
                                <rs-stat-box
                                    label="Pending"
                                    :value="campaign?.pending_count"
                                    text-class="text-warning"
                                />
                            </div>
                        </div>

                        <hr>

                        <div class="mb-0">
                            <label class="small text-muted text-uppercase fw-bold mb-2">Message Content</label>
                            <div class="p-3 bg-light rounded text-break" style="white-space: pre-wrap; font-size: 14px;">{{ campaign.message_body }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Recipients List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Recipients List</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-4 py-3">Tenant</th>
                                    <th class="px-4 py-3">Phone</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="recipient in recipients?.data" :key="recipient.id">
                                    <td class="px-4">
                                        <div class="fw-bold">{{ recipient.tenant_name_snapshot }}</div>
                                        <small class="text-muted">{{ recipient.tenant?.subdomain }}</small>
                                    </td>
                                    <td class="px-4">{{ recipient.phone }}</td>
                                    <td class="px-4">{{ recipient.email || '-' }}</td>
                                    <td class="px-4">
                                        <span class="badge" :class="getRecipientStatusClass(recipient.status)">
                                            {{ recipient.status.toUpperCase() }}
                                        </span>
                                        <div v-if="recipient.error_message" class="text-danger small mt-1" style="max-width: 200px; font-size: 10px;">
                                            {{ recipient.error_message }}
                                        </div>
                                    </td>
                                    <td class="px-4 text-muted small">
                                        {{ formatDate(recipient.sent_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!recipients?.data || recipients?.data?.length === 0">
                                    <td colspan="4" class="text-center py-4 text-muted">No recipients found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-top-0 py-3" v-if="recipients?.data?.length > 0">
                        <!-- Simple Pagination -->
                         <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Page {{ recipients.current_page }} of {{ recipients.last_page }}
                            </div>
                            
                            <div class="d-flex gap-1" v-if="recipients.links.length > 3">
                                <template v-for="(link, k) in recipients.links" :key="k">
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
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { format } from 'date-fns';
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    campaign: Object,
    recipients: Object
});

const isProcessing = ref(false);
const localProcessed = ref(0);
const localTotal = ref(props.campaign.total_targets);
const processingMessage = ref('');

// Sync local state with props on mount
onMounted(() => {
    localProcessed.value = props.campaign.sent_count + props.campaign.failed_count;
    localTotal.value = props.campaign.total_targets;
});

const progress = computed(() => {
    if (localTotal.value === 0) return 0;
    return Math.round((localProcessed.value / localTotal.value) * 100);
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

const getRecipientStatusClass = (status) => {
    switch(status) {
        case 'sent': return 'bg-success bg-opacity-10 text-success';
        case 'failed': return 'bg-danger bg-opacity-10 text-danger';
        default: return 'bg-warning bg-opacity-10 text-warning';
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    try {
        return format(new Date(date), 'dd MMM yyyy HH:mm');
    } catch (e) {
        return date;
    }
};

const refreshPage = () => {
    router.reload({
        preserveScroll: true,
        only: ['campaign', 'recipients'],
        onSuccess: () => {
             localProcessed.value = props.campaign.sent_count + props.campaign.failed_count;
        }
    });
};

// Batch Processing Logic
const startProcessing = async () => {
    if (isProcessing.value) return;
    
    // Confirm if starting
    if (!confirm('Start sending messages via browser? Please keep this tab open.')) {
        return;
    }

    isProcessing.value = true;
    processNextBatch();
};

const stopProcessing = () => {
    isProcessing.value = false;
    processingMessage.value = 'Paused by user.';
};

const processNextBatch = async () => {
    if (!isProcessing.value) return;

    processingMessage.value = 'Sending next message...';

    try {
        const response = await axios.post(route('superadmin.bulk-messages.send-batch', props.campaign.id), {
            limit: 1 // Process 1 at a time to stay under timeout and granular control
        });

        const data = response.data;

        if (data.processed > 0) {
            localProcessed.value += data.processed;
            processingMessage.value = `Sent ${data.processed} messages...`;
            
            // Refresh recipients list occasionally to show updates
            if (localProcessed.value % 5 === 0) {
                router.reload({ only: ['recipients', 'campaign'], preserveScroll: true });
            }
        }

        if (data.completed || data.remain === 0) {
            isProcessing.value = false;
            processingMessage.value = 'Campaign Completed!';
            refreshPage();
            alert('Campaign finished successfully!');
        } else {
            // Frontend Rate Limiting: Wait 30-60 seconds before next call
            const delayInSeconds = Math.floor(Math.random() * (60 - 30 + 1)) + 30;
            let countdown = delayInSeconds;
            
            const timer = setInterval(() => {
                if (!isProcessing.value) {
                    clearInterval(timer);
                    return;
                }
                
                countdown--;
                processingMessage.value = `Waiting ${countdown}s for next message...`;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    processNextBatch();
                }
            }, 1000);
        }

    } catch (error) {
        console.error('Batch error:', error);
        processingMessage.value = 'Error: ' + (error.response?.data?.message || error.message);
        isProcessing.value = false;
        alert('An error occurred. Processing paused.');
    }
};

// Computed property to check if we can start/resume
const canProcess = computed(() => {
    return props.campaign.status !== 'done' && props.campaign.pending_count > 0;
});
</script>
