<template>
    <SuperAdminLayout>
        <Head title="Profile Settings" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h3 class="page-title">Profile Settings</h3>
                <p class="page-subtitle">Manage your account information and security</p>
            </div>

            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                {{ $page.props.flash.success }}
                <button type="button" class="btn-close" @click="$page.props.flash.success = null"></button>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Profile Information Card -->
                    <div class="settings-card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-person-circle me-2"></i>
                                Profile Information
                            </h5>
                            <p class="card-subtitle">Update your account's profile information</p>
                        </div>

                        <form @submit.prevent="updateProfile">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Name <span class="text-danger">*</span></label>
                                        <input 
                                            type="text" 
                                            v-model="profileForm.name" 
                                            class="form-control"
                                            required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input 
                                            type="email" 
                                            v-model="profileForm.email" 
                                            class="form-control"
                                            required>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <label class="form-label">Profile Photo</label>
                                        <div class="d-flex align-items-center gap-4">
                                            <!-- Photo Preview -->
                                            <div class="photo-preview-wrapper rounded-circle border border-2 shadow-sm d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 80px; height: 80px; background-color: #F1F5F9;">
                                                <img v-if="photoPreview" :src="photoPreview" alt="Preview" class="w-100 h-100" style="object-fit: cover;">
                                                <img v-else-if="$page.props.auth?.user?.photo" :src="'/storage/' + $page.props.auth.user.photo" alt="Current" class="w-100 h-100" style="object-fit: cover;">
                                                <i v-else class="bi bi-person text-muted" style="font-size: 2.5rem;"></i>
                                            </div>
                                            
                                            <!-- File Input -->
                                            <div class="flex-grow-1">
                                                <input 
                                                    type="file" 
                                                    @change="handlePhotoChange" 
                                                    class="form-control"
                                                    accept="image/*"
                                                    ref="photoInput">
                                                <small class="text-muted d-block mt-2">Recommended: Square image, max 2MB (JPEG, PNG, GIF)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" :disabled="processing">
                                    <i class="bi bi-save me-2"></i>
                                    {{ processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Card -->
                    <div class="settings-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-shield-lock me-2"></i>
                                Change Password
                            </h5>
                            <p class="card-subtitle">Update your password to keep your account secure</p>
                        </div>

                        <form @submit.prevent="updatePassword">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                        <input 
                                            type="password" 
                                            v-model="passwordForm.current_password" 
                                            class="form-control"
                                            required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                                        <input 
                                            type="password" 
                                            v-model="passwordForm.password" 
                                            class="form-control"
                                            required
                                            minlength="8">
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <input 
                                            type="password" 
                                            v-model="passwordForm.password_confirmation" 
                                            class="form-control"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" :disabled="processingPassword">
                                    <i class="bi bi-key me-2"></i>
                                    {{ processingPassword ? 'Updating...' : 'Update Password' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    user: Object,
});

const processing = ref(false);
const processingPassword = ref(false);

const profileForm = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    photo: null,
});

const photoPreview = ref(null);
const photoInput = ref(null);

const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        profileForm.photo = file;
        photoPreview.value = URL.createObjectURL(file);
    } else {
        profileForm.photo = null;
        photoPreview.value = null;
    }
};

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updateProfile = () => {
    processing.value = true;
    profileForm.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('superadmin.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            processing.value = false;
        },
        onError: () => {
            processing.value = false;
        }
    });
};

const updatePassword = () => {
    processingPassword.value = true;
    passwordForm.put(route('superadmin.profile.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            processingPassword.value = false;
            passwordForm.reset();
        },
        onError: () => {
            processingPassword.value = false;
        }
    });
};
</script>

<style scoped>
/* Page Header */
.page-header {
    margin-bottom: 32px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

:global(.dark) .page-title {
    color: #F1F5F9;
}

.page-subtitle {
    color: #64748B;
    margin: 0;
}

:global(.dark) .page-subtitle {
    color: #94A3B8;
}

/* Settings Card */
.settings-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

:global(.dark) .settings-card {
    background: #1E293B;
}

.card-header {
    padding: 24px 32px;
    border-bottom: 1px solid #E2E8F0;
}

:global(.dark) .card-header {
    border-bottom-color: #334155;
}

.card-title {
    font-size: 20px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

:global(.dark) .card-title {
    color: #F1F5F9;
}

.card-subtitle {
    color: #64748B;
    margin: 0;
    font-size: 14px;
}

:global(.dark) .card-subtitle {
    color: #94A3B8;
}

.card-body {
    padding: 32px;
}

.card-footer {
    padding: 24px 32px;
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
}

:global(.dark) .card-footer {
    background: #0F172A;
    border-top-color: #334155;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 8px;
}

:global(.dark) .form-label {
    color: #F1F5F9;
}

.form-control {
    border: 2px solid #E2E8F0;
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 14px;
    transition: all 0.3s;
}

:global(.dark) .form-control {
    background: #0F172A;
    border-color: #334155;
    color: #F1F5F9;
}

.form-control:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    border: none;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Alert */
.alert {
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
</style>

