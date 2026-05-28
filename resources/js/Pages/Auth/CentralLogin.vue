<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login.submit'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="auth-container">
        <Head title="Login - KitaBill" />
        
        <ThemeToggle />

        <div class="auth-card">
            <!-- Logo & Title -->
            <div class="brand-header">
                <div class="logo-container">
                    <img src="/images/logo.png" alt="KitaBill Logo" class="logo">
                    <span class="brand-name">Kita<span class="brand-accent">Bill</span></span>
                </div>
                <h1 class="title">Selamat Datang Kembali</h1>
                <p class="subtitle">
                    Masuk untuk mengelola jaringan ISP Anda
                </p>
            </div>

            <!-- Status Message -->
            <div v-if="status" class="alert alert-info">
                <svg class="alert-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ status }}</span>
            </div>

            <!-- Login Info Box -->
            <div class="info-box">
                <svg class="info-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="info-content">
                    <strong>Login Terpusat</strong>
                    <p>Sistem akan otomatis mengarahkan Anda ke subdomain tenant yang sesuai setelah login.</p>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="auth-form">
                <!-- Email Field -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        <svg class="label-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Email Address
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="form-input"
                        :class="{ 'error': form.errors.email }"
                        placeholder="admin@contoh.com"
                        required
                        autofocus
                    />
                    <span v-if="form.errors.email" class="error-message">
                        <svg class="error-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ form.errors.email }}
                    </span>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <svg class="label-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Password
                    </label>
                    <div class="password-input-wrapper">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="form-input"
                            :class="{ 'error': form.errors.password }"
                            placeholder="••••••••"
                            required
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="password-toggle"
                            tabindex="-1"
                        >
                            <svg v-if="!showPassword" class="toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg v-else class="toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="error-message">
                        <svg class="error-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ form.errors.password }}
                    </span>
                </div>

                <!-- Remember & Forgot -->
                <div class="form-options">
                    <label class="checkbox-label">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="checkbox-input"
                        />
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Ingat Saya</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="forgot-link"
                    >
                        Lupa Password?
                    </Link>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="form.processing"
                >
                    <svg v-if="!form.processing" class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <svg v-else class="btn-icon animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ form.processing ? 'Memproses Login...' : 'Masuk ke Dashboard →' }}</span>
                </button>

                <!-- Register Link -->
                <div class="register-prompt">
                    <span>Belum punya akun?</span>
                    <a href="https://kitabill.site/tenant-register" class="register-link" target="_blank">
                        Daftar Gratis
                    </a>
                </div>
            </form>

            <!-- Footer -->
            <div class="auth-footer">
                <Link :href="route('home')" class="footer-link">
                    <svg class="footer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali ke Beranda
                </Link>
                <p>&copy; {{ new Date().getFullYear() }} KitaBill. All rights reserved.</p>
            </div>
        </div>

        <!-- Decorative Elements -->
        <div class="decoration decoration-1"></div>
        <div class="decoration decoration-2"></div>
        <div class="decoration decoration-3"></div>
    </div>
</template>

<style scoped>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.auth-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

:global(.dark) .auth-container {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
}

.auth-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 480px;
    width: 100%;
    padding: 48px 40px;
    position: relative;
    z-index: 10;
    animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

:global(.dark) .auth-card {
    background: rgba(31, 41, 55, 0.95);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Brand Header */
.brand-header {
    text-align: center;
    margin-bottom: 32px;
}

.logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 24px;
}

.logo {
    height: 48px;
    width: auto;
}

.brand-name {
    font-size: 32px;
    font-weight: 700;
    color: #1F2937;
}

:global(.dark) .brand-name {
    color: #F9FAFB;
}

.brand-accent {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.title {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 12px;
}

:global(.dark) .title {
    color: #F9FAFB;
}

.subtitle {
    font-size: 15px;
    color: #6B7280;
    line-height: 1.6;
}

:global(.dark) .subtitle {
    color: #9CA3AF;
}

/* Alert */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
    animation: fadeIn 0.3s ease;
}

.alert-info {
    background: #DBEAFE;
    border: 1px solid #93C5FD;
    color: #1E40AF;
}

:global(.dark) .alert-info {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.3);
    color: #93C5FD;
}

.alert-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

/* Info Box */
.info-box {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    margin-bottom: 24px;
}

:global(.dark) .info-box {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%);
    border-color: rgba(59, 130, 246, 0.3);
}

.info-icon {
    width: 20px;
    height: 20px;
    color: #3B82F6;
    flex-shrink: 0;
    margin-top: 2px;
}

.info-content strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #1F2937;
    margin-bottom: 4px;
}

:global(.dark) .info-content strong {
    color: #F9FAFB;
}

.info-content p {
    font-size: 13px;
    color: #6B7280;
    line-height: 1.5;
    margin: 0;
}

:global(.dark) .info-content p {
    color: #9CA3AF;
}

/* Form */
.auth-form {
    margin-bottom: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

:global(.dark) .form-label {
    color: #D1D5DB;
}

.label-icon {
    width: 18px;
    height: 18px;
    color: #3B82F6;
}

.form-input {
    width: 100%;
    padding: 14px 16px;
    font-size: 15px;
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    background: #FFFFFF;
    color: #111827;
    transition: all 0.3s ease;
    font-family: inherit;
}

:global(.dark) .form-input {
    background: #374151;
    border-color: #4B5563;
    color: #F9FAFB;
}

.form-input:focus {
    outline: none;
    border-color: #3B82F6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

:global(.dark) .form-input:focus {
    border-color: #60A5FA;
    box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.1);
}

.form-input.error {
    border-color: #EF4444;
}

/* Password Input */
.password-input-wrapper {
    position: relative;
}

.password-input-wrapper .form-input {
    padding-right: 48px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: background 0.2s ease;
}

.password-toggle:hover {
    background: rgba(59, 130, 246, 0.1);
}

.toggle-icon {
    width: 20px;
    height: 20px;
    color: #6B7280;
}

:global(.dark) .toggle-icon {
    color: #9CA3AF;
}

.error-message {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 13px;
    color: #DC2626;
}

:global(.dark) .error-message {
    color: #F87171;
}

.error-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

/* Form Options */
.form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
}

.checkbox-input {
    display: none;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #D1D5DB;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    background: #FFFFFF;
}

:global(.dark) .checkbox-custom {
    border-color: #4B5563;
    background: #374151;
}

.checkbox-input:checked + .checkbox-custom {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    border-color: #3B82F6;
}

.checkbox-input:checked + .checkbox-custom::after {
    content: '✓';
    color: white;
    font-size: 14px;
    font-weight: bold;
}

.checkbox-text {
    font-size: 14px;
    color: #374151;
}

:global(.dark) .checkbox-text {
    color: #D1D5DB;
}

.forgot-link {
    font-size: 14px;
    color: #3B82F6;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

.forgot-link:hover {
    color: #2563EB;
}

/* Button */
.btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px 24px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    color: #FFFFFF;
    box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.4);
}

.btn-primary:active:not(:disabled) {
    transform: translateY(0);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-icon {
    width: 20px;
    height: 20px;
}

/* Register Prompt */
.register-prompt {
    text-align: center;
    margin-top: 24px;
    font-size: 14px;
    color: #6B7280;
}

:global(.dark) .register-prompt {
    color: #9CA3AF;
}

.register-link {
    color: #3B82F6;
    text-decoration: none;
    font-weight: 600;
    margin-left: 6px;
    transition: color 0.2s ease;
}

.register-link:hover {
    color: #2563EB;
}

/* Footer */
.auth-footer {
    text-align: center;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #E5E7EB;
}

:global(.dark) .auth-footer {
    border-top-color: #374151;
}

.footer-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #6B7280;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 12px;
    transition: all 0.3s ease;
}

:global(.dark) .footer-link {
    color: #9CA3AF;
}

.footer-link:hover {
    color: #3B82F6;
    gap: 12px;
}

.footer-icon {
    width: 18px;
    height: 18px;
}

.auth-footer p {
    font-size: 13px;
    color: #9CA3AF;
    margin-top: 12px;
}

:global(.dark) .auth-footer p {
    color: #6B7280;
}

/* Decorative Elements */
.decoration {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.6;
    z-index: 1;
    animation: float 6s ease-in-out infinite;
}

.decoration-1 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(96, 165, 250, 0.4) 0%, transparent 70%);
    top: -200px;
    left: -200px;
}

.decoration-2 {
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(147, 51, 234, 0.3) 0%, transparent 70%);
    bottom: -150px;
    right: -150px;
    animation-delay: -2s;
}

.decoration-3 {
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 70%);
    top: 50%;
    right: 10%;
    animation-delay: -4s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-20px) scale(1.05); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 640px) {
    .auth-card {
        padding: 32px 24px;
    }

    .brand-name {
        font-size: 28px;
    }

    .title {
        font-size: 24px;
    }

    .subtitle {
        font-size: 14px;
    }

    .btn {
        padding: 14px 20px;
        font-size: 15px;
    }

    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}
</style>
