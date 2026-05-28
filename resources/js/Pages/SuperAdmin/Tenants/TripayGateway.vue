<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    tenant: Object,
    settings: Object,
});

const form = useForm({
    merchant_code: props.settings.merchant_code || '',
    api_key: props.settings.api_key || '',
    private_key: props.settings.private_key || '',
    mode: props.settings.mode || 'sandbox',
    enabled: props.settings.enabled || false,
});

const testStatus = ref(null); // null, 'loading', 'success', 'error'
const testMessage = ref('');

const submit = () => {
    form.put(route('superadmin.tenants.gateways.tripay.update', props.tenant.id), {
        onSuccess: () => {
            // Flash message handled by layout usually, or we can add local invalidation
        },
    });
};

const testConnection = async () => {
    testStatus.value = 'loading';
    testMessage.value = 'Testing connection...';

    try {
        const response = await axios.post(route('superadmin.tenants.gateways.tripay.test', props.tenant.id), {
            merchant_code: form.merchant_code,
            api_key: form.api_key,
            private_key: form.private_key,
            mode: form.mode,
        });

        if (response.data.success) {
            testStatus.value = 'success';
            testMessage.value = response.data.message;
        } else {
            testStatus.value = 'error';
            testMessage.value = response.data.message;
        }
    } catch (error) {
        testStatus.value = 'error';
        testMessage.value = error.response?.data?.message || 'Connection failed';
    }
};
</script>

<template>
    <Head title="Tripay Configuration" />

    <SuperAdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Manage Tenant: {{ tenant.name }} ({{ tenant.subdomain }})
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <div class="mb-6 flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Tripay Configuration</h3>
                            <Link :href="route('superadmin.tenants.show', tenant.id)" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                &larr; Back to Tenant
                            </Link>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
                            
                            <!-- Enabled Toggle -->
                            <div class="flex items-center">
                                <input id="enabled" type="checkbox" v-model="form.enabled" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <label for="enabled" class="ml-2 block text-sm text-gray-900">
                                    Enable Tripay Payment Gateway
                                </label>
                            </div>

                            <!-- Mode -->
                            <div>
                                <label class="block font-medium text-sm text-gray-700">Mode</label>
                                <select v-model="form.mode" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="sandbox">Sandbox (Test)</option>
                                    <option value="production">Production (Live)</option>
                                </select>
                            </div>

                            <!-- Merchant Code -->
                            <div>
                                <label class="block font-medium text-sm text-gray-700">Merchant Code</label>
                                <input v-model="form.merchant_code" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <div v-if="form.errors.merchant_code" class="text-red-500 text-sm mt-1">{{ form.errors.merchant_code }}</div>
                            </div>

                            <!-- API Key -->
                            <div>
                                <label class="block font-medium text-sm text-gray-700">API Key</label>
                                <input v-model="form.api_key" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="********">
                                <p class="text-xs text-gray-500 mt-1">Leave masked if unchanged.</p>
                                <div v-if="form.errors.api_key" class="text-red-500 text-sm mt-1">{{ form.errors.api_key }}</div>
                            </div>

                            <!-- Private Key -->
                            <div>
                                <label class="block font-medium text-sm text-gray-700">Private Key</label>
                                <input v-model="form.private_key" type="text" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="********">
                                <p class="text-xs text-gray-500 mt-1">Leave masked if unchanged.</p>
                                <div v-if="form.errors.private_key" class="text-red-500 text-sm mt-1">{{ form.errors.private_key }}</div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-4 pt-4 border-t">
                                <button type="submit" :disabled="form.processing" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Save Configuration
                                </button>
                                
                                <button type="button" @click="testConnection" :disabled="testStatus === 'loading'" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <span v-if="testStatus === 'loading'">Testing...</span>
                                    <span v-else>Test Connection</span>
                                </button>
                            </div>

                            <!-- Test Result Alert -->
                            <div v-if="testStatus && testStatus !== 'loading'" :class="{'bg-green-100 border-green-400 text-green-700': testStatus === 'success', 'bg-red-100 border-red-400 text-red-700': testStatus === 'error'}" class="px-4 py-3 rounded relative border mt-4">
                                <strong class="font-bold">{{ testStatus === 'success' ? 'Success!' : 'Error!' }}</strong>
                                <span class="block sm:inline ml-2">{{ testMessage }}</span>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
