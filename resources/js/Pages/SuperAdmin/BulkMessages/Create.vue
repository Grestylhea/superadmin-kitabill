<template>
    <SuperAdminLayout>
        <Head title="New Bulk Message" />

        <div class="content-header mb-4">
            <h2 class="mb-1 text-primary fw-bold">New Announcement</h2>
            <p class="text-muted mb-0">Send WhatsApp message to multiple tenants</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form @submit.prevent="submit">
                            <!-- Title -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Campaign Title</label>
                                <input type="text" v-model="form.title" class="form-control" placeholder="e.g. System Maintenance Notice">
                                <div v-if="form.errors.title" class="text-danger small mt-1">{{ form.errors.title }}</div>
                            </div>

                            <!-- Target Audience -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Target Audience</label>
                                <div class="d-flex gap-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" v-model="form.target_type" value="all" id="targetAll">
                                        <label class="form-check-label" for="targetAll">
                                            All Tenants
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" v-model="form.target_type" value="selected" id="targetSelected">
                                        <label class="form-check-label" for="targetSelected">
                                            Select Specific Tenants
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" v-model="form.target_type" value="filtered" id="targetFiltered">
                                        <label class="form-check-label" for="targetFiltered">
                                            Filter by Plan/Status
                                        </label>
                                    </div>
                                </div>

                                <!-- Specific Tenants Selection -->
                                <div v-if="form.target_type === 'selected'" class="p-3 bg-light rounded mb-3">
                                    <label class="form-label small text-muted text-uppercase fw-bold mb-2">Select Tenants</label>
                                    <div class="tenant-list" style="max-height: 200px; overflow-y: auto;">
                                        <div v-for="tenant in tenants" :key="tenant.id" class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" :value="tenant.id" v-model="form.selected_tenant_ids" :id="'tenant'+tenant.id">
                                            <label class="form-check-label d-flex justify-content-between pe-3" :for="'tenant'+tenant.id">
                                                <span>{{ tenant.name }} <small class="text-muted">({{ tenant.subdomain }})</small></span>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ tenant.subscription_plan || 'No Plan' }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">{{ form.selected_tenant_ids.length }} tenants selected</small>
                                </div>

                                <!-- Filters -->
                                <div v-if="form.target_type === 'filtered'" class="p-3 bg-light rounded mb-3">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small text-muted text-uppercase fw-bold mb-2">By Plan</label>
                                            <div v-for="plan in plans" :key="plan" class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" :value="plan" v-model="form.filters.plans" :id="'plan'+plan">
                                                <label class="form-check-label" :for="'plan'+plan">{{ plan }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small text-muted text-uppercase fw-bold mb-2">By Status</label>
                                            <div v-for="status in statuses" :key="status" class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" :value="status" v-model="form.filters.statuses" :id="'status'+status">
                                                <label class="form-check-label text-capitalize" :for="'status'+status">{{ status }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Body -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Message Content</label>
                                <textarea 
                                    id="messageBody"
                                    v-model="form.message_body" 
                                    class="form-control" 
                                    rows="8" 
                                    placeholder="Type your message here..."
                                    ref="messageInput"
                                ></textarea>
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-2">Insert Placeholders:</small>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button 
                                            type="button" 
                                            v-for="ph in placeholders" 
                                            :key="ph"
                                            @click="insertPlaceholder(ph)"
                                            class="btn btn-sm btn-outline-secondary"
                                            style="font-size: 11px;"
                                        >
                                            {{ ph }}
                                        </button>
                                    </div>
                                </div>
                                <div v-if="form.errors.message_body" class="text-danger small mt-1">{{ form.errors.message_body }}</div>
                            </div>

                            <div class="d-flex justify-content-end gap-3 mt-5">
                                <Link :href="route('superadmin.bulk-messages.index')" class="btn btn-light">Cancel</Link>
                                <button type="submit" class="btn btn-primary px-4" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                                    Send Broadcast
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preview & Tips -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold">Message Preview</h6>
                    </div>
                    <div class="card-body bg-light">
                        <div class="whatsapp-preview p-3 bg-white rounded shadow-sm border" style="min-height: 200px; white-space: pre-wrap;">{{ previewMessage || 'Your message will appear here...' }}</div>
                        <small class="text-muted mt-2 d-block fst-italic">* Preview with dummy data</small>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold">Available Placeholders</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2"><code class="text-primary" v-pre>{{tenant_name}}</code> : Nama Tenant</li>
                            <li class="mb-2"><code class="text-primary" v-pre>{{username}}</code> : Username Login</li>
                            <li class="mb-2"><code class="text-primary" v-pre>{{email}}</code> : Email Utama</li>
                            <li class="mb-2"><code class="text-primary" v-pre>{{subdomain}}</code> : Subdomain Tenant</li>
                            <li class="mb-2"><code class="text-primary" v-pre>{{plan}}</code> : Paket Subscription</li>
                            <li class="mb-2"><code class="text-primary" v-pre>{{status}}</code> : Status Akun</li>
                            <li class="mb-2"><code class="text-primary" v-pre>{{trial_ends_at}}</code> : Tgl Berakhir Trial</li>
                            <li class="mb-0"><code class="text-primary" v-pre>{{subscription_expires_at}}</code> : Tgl Expired</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    tenants: Array,
    plans: Array,
    statuses: Array,
    placeholders: Array
});

const messageInput = ref(null);

const form = useForm({
    title: '',
    target_type: 'all', // all, selected, filtered
    selected_tenant_ids: [],
    filters: {
        plans: [],
        statuses: []
    },
    message_body: ''
});

const insertPlaceholder = (ph) => {
    const textarea = document.getElementById('messageBody');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = form.message_body;
    
    form.message_body = text.substring(0, start) + ph + text.substring(end);
    
    // Restore focus and cursor
    setTimeout(() => {
        textarea.focus();
        textarea.setSelectionRange(start + ph.length, start + ph.length);
    }, 0);
};

const previewMessage = computed(() => {
    let msg = form.message_body;
    if (!msg) return '';
    
    // Replace with dummy data for preview
    const replacements = {
        '{{tenant_name}}': 'Mitra Net',
        '{{username}}': 'mitranet_admin',
        '{{email}}': 'admin@mitranet.com',
        '{{subdomain}}': 'mitra',
        '{{plan}}': 'Professional',
        '{{status}}': 'active',
        '{{trial_ends_at}}': '15 Feb 2026',
        '{{subscription_expires_at}}': '15 Mar 2026'
    };
    
    Object.keys(replacements).forEach(key => {
        msg = msg.replaceAll(key, replacements[key]);
    });
    
    return msg;
});

const submit = () => {
    if (confirm('Are you sure you want to send this broadcast? This action cannot be undone.')) {
        form.post(route('superadmin.bulk-messages.store'));
    }
};
</script>

<style scoped>
.whatsapp-preview {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 14px;
    line-height: 1.4;
    color: #000;
}
</style>
