<template>
    <SuperAdminLayout>
        <Head title="System Settings" />
        
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h3 class="page-title">System Settings</h3>
                <p class="page-subtitle">Configure system-wide settings and payment gateway for subscriptions</p>
            </div>

            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                {{ $page.props.flash.success }}
                <button type="button" class="btn-close" @click="$page.props.flash.success = null"></button>
            </div>

            <!-- Tabs -->
            <div class="settings-tabs mb-4">
                <button 
                    class="tab-btn" 
                    :class="{ active: activeTab === 'payment' }"
                    @click="activeTab = 'payment'">
                    <i class="bi bi-credit-card me-2"></i>
                    Payment Gateway
                </button>
                <button 
                    class="tab-btn" 
                    :class="{ active: activeTab === 'general' }"
                    @click="activeTab = 'general'">
                    <i class="bi bi-gear me-2"></i>
                    General Settings
                </button>
                <button 
                    class="tab-btn" 
                    :class="{ active: activeTab === 'email' }"
                    @click="activeTab = 'email'">
                    <i class="bi bi-envelope me-2"></i>
                    Email Settings
                </button>
                <button 
                    class="tab-btn" 
                    :class="{ active: activeTab === 'whatsapp' }"
                    @click="activeTab = 'whatsapp'">
                    <i class="bi bi-whatsapp me-2"></i>
                    WhatsApp Settings
                </button>
                <button 
                    class="tab-btn" 
                    :class="{ active: activeTab === 'referral' }"
                    @click="activeTab = 'referral'">
                    <i class="bi bi-people me-2"></i>
                    Referral System
                </button>
            </div>

            <!-- Payment Gateway Tab -->
            <div v-show="activeTab === 'payment'" class="settings-card">
                <div class="card-header">
                    <div>
                        <h5 class="card-title">Payment Gateway Configuration</h5>
                        <p class="card-subtitle">Configure payment gateway for tenant subscription payments (separate from tenant billing)</p>
                    </div>
                </div>

                <form @submit.prevent="savePaymentSettings">
                    <div class="card-body">
                        <!-- Active Gateway -->
                        <div class="form-section">
                            <h6 class="section-title">Active Payment Gateway</h6>
                            <p class="section-description">Select which payment gateway to use for subscription payments</p>

                            <div class="gateway-selector">
                                <label class="gateway-option" :class="{ active: form.subscription_payment_gateway === 'xendit' }">
                                    <input type="radio" v-model="form.subscription_payment_gateway" value="xendit" hidden>
                                    <div class="gateway-card">
                                        <div class="gateway-logo">
                                            <img src="https://www.xendit.co/images/xendit-logo.png" alt="Xendit">
                                        </div>
                                        <div class="gateway-name">Xendit</div>
                                        <div class="gateway-badge" v-if="form.subscription_payment_gateway === 'xendit'">
                                            <i class="bi bi-check-circle-fill"></i> Active
                                        </div>
                                    </div>
                                </label>

                                <label class="gateway-option" :class="{ active: form.subscription_payment_gateway === 'midtrans' }">
                                    <input type="radio" v-model="form.subscription_payment_gateway" value="midtrans" hidden>
                                    <div class="gateway-card">
                                        <div class="gateway-logo">
                                            <img src="https://midtrans.com/assets/images/logo/logo-midtrans.svg" alt="Midtrans">
                                        </div>
                                        <div class="gateway-name">Midtrans</div>
                                        <div class="gateway-badge" v-if="form.subscription_payment_gateway === 'midtrans'">
                                            <i class="bi bi-check-circle-fill"></i> Active
                                        </div>
                                    </div>
                                </label>


                                <label class="gateway-option" :class="{ active: form.subscription_payment_gateway === 'duitku' }">
                                    <input type="radio" v-model="form.subscription_payment_gateway" value="duitku" hidden>
                                    <div class="gateway-card">
                                        <div class="gateway-logo">
                                            <div class="text-primary fw-bold fs-4">DUITKU</div>
                                        </div>
                                        <div class="gateway-name">Duitku</div>
                                        <div class="gateway-badge" v-if="form.subscription_payment_gateway === 'duitku'">
                                            <i class="bi bi-check-circle-fill"></i> Active
                                        </div>
                                    </div>
                                </label>

                                <label class="gateway-option" :class="{ active: form.subscription_payment_gateway === 'tripay' }">
                                    <input type="radio" v-model="form.subscription_payment_gateway" value="tripay" hidden>
                                    <div class="gateway-card">
                                        <div class="gateway-logo">
                                            <img src="https://tripay.co.id/assets/images/logo-black.png" alt="Tripay" style="max-height: 25px;">
                                        </div>
                                        <div class="gateway-name">Tripay</div>
                                        <div class="gateway-badge" v-if="form.subscription_payment_gateway === 'tripay'">
                                            <i class="bi bi-check-circle-fill"></i> Active
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Tripay Settings -->
                        <div v-show="form.subscription_payment_gateway === 'tripay'" class="form-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="section-title mb-1">Tripay API Configuration</h6>
                                    <p class="section-description mb-0">Configure Tripay for subscription payments</p>
                                </div>
                                <button type="button" @click="testConnection('tripay')" class="btn btn-outline-primary btn-sm" :disabled="testing">
                                    <i class="bi bi-plug me-1"></i>
                                    {{ testing ? 'Testing...' : 'Test Connection' }}
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Merchant Code <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.tripay_merchant_code" 
                                        class="form-control"
                                        placeholder="T12345">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mode</label>
                                    <select v-model="form.tripay_mode" class="form-select">
                                        <option value="sandbox">Sandbox (Testing)</option>
                                        <option value="production">Production (Real)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Key <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.tripay_api_key" 
                                        class="form-control"
                                        placeholder="***">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Private Key <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.tripay_private_key" 
                                        class="form-control"
                                        placeholder="***">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" v-model="form.tripay_enabled" id="tripayEnabled">
                                        <label class="form-check-label" for="tripayEnabled">Enable Tripay Gateway</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info mt-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <strong>Webhook URL untuk Subscription Payment:</strong>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary"
                                                @click="copyWebhookUrl('tripay-superadmin')">
                                                <i class="bi bi-clipboard me-1"></i> Copy URL
                                            </button>
                                        </div>
                                        <div class="webhook-url-box">
                                            <code id="webhook-url-tripay">{{ webhookUrl }}/api/webhooks/tripay-superadmin</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Xendit Settings -->
                        <div v-show="form.subscription_payment_gateway === 'xendit'" class="form-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="section-title mb-1">Xendit API Configuration</h6>
                                    <p class="section-description mb-0">Configure Xendit for subscription payments</p>
                                </div>
                                <button type="button" @click="testConnection('xendit')" class="btn btn-outline-primary btn-sm" :disabled="testing">
                                    <i class="bi bi-plug me-1"></i>
                                    {{ testing ? 'Testing...' : 'Test Connection' }}
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">API Key <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.xendit_subscription_api_key" 
                                        class="form-control"
                                        placeholder="xnd_development_***">
                                    <small class="text-muted">Get from Xendit Dashboard → Settings → API Keys</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Webhook Verification Token</label>
                                    <input 
                                        type="text" 
                                        v-model="form.xendit_subscription_webhook_token" 
                                        class="form-control"
                                        placeholder="Enter webhook token from Xendit">
                                    <div class="alert alert-info mt-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <strong>Webhook URL untuk Subscription Payment:</strong>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary"
                                                @click="copyWebhookUrl('xendit')">
                                                <i class="bi bi-clipboard me-1"></i> Copy URL
                                            </button>
                                        </div>
                                        <div class="webhook-url-box">
                                            <code id="webhook-url-xendit">{{ webhookUrl }}/webhooks/xendit</code>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Langkah konfigurasi:</strong><br>
                                                1. Copy URL di atas<br>
                                                2. Login ke Xendit Dashboard → Settings → Webhooks<br>
                                                3. Add Webhook dengan URL yang sudah di-copy<br>
                                                4. Pilih Events: "Invoice" → "Invoice Paid", "Invoice Expired"<br>
                                                5. Copy "Webhook Verification Token" yang diberikan Xendit<br>
                                                6. Paste token di field "Webhook Verification Token" di atas, lalu klik "Save Settings"
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Midtrans Settings -->
                        <div v-show="form.subscription_payment_gateway === 'midtrans'" class="form-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="section-title mb-1">Midtrans API Configuration</h6>
                                    <p class="section-description mb-0">Configure Midtrans for subscription payments</p>
                                </div>
                                <button type="button" @click="testConnection('midtrans')" class="btn btn-outline-primary btn-sm" :disabled="testing">
                                    <i class="bi bi-plug me-1"></i>
                                    {{ testing ? 'Testing...' : 'Test Connection' }}
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Server Key <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.midtrans_subscription_server_key" 
                                        class="form-control"
                                        placeholder="SB-Mid-server-***">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Client Key <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.midtrans_subscription_client_key" 
                                        class="form-control"
                                        placeholder="SB-Mid-client-***">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info mt-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <strong>Webhook URL untuk Subscription Payment:</strong>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary"
                                                @click="copyWebhookUrl('midtrans')">
                                                <i class="bi bi-clipboard me-1"></i> Copy URL
                                            </button>
                                        </div>
                                        <div class="webhook-url-box">
                                            <code id="webhook-url-midtrans">{{ webhookUrl }}/webhooks/midtrans</code>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Langkah konfigurasi:</strong><br>
                                                1. Copy URL di atas<br>
                                                2. Login ke Midtrans Dashboard → Settings → Notification URL<br>
                                                3. Paste URL yang sudah di-copy<br>
                                                4. Save settings
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Duitku Settings -->
                        <div v-show="form.subscription_payment_gateway === 'duitku'" class="form-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="section-title mb-1">Duitku API Configuration</h6>
                                    <p class="section-description mb-0">Configure Duitku for subscription payments</p>
                                </div>
                                <button type="button" @click="testConnection('duitku')" class="btn btn-outline-primary btn-sm" :disabled="testing">
                                    <i class="bi bi-plug me-1"></i>
                                    {{ testing ? 'Testing...' : 'Test Connection' }}
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Merchant Code <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.duitku_subscription_merchant_code" 
                                        class="form-control"
                                        placeholder="D1234">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Key <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="form.duitku_subscription_api_key" 
                                        class="form-control"
                                        placeholder="***">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info mt-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <strong>Webhook URL untuk Subscription Payment:</strong>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary"
                                                @click="copyWebhookUrl('duitku')">
                                                <i class="bi bi-clipboard me-1"></i> Copy URL
                                            </button>
                                        </div>
                                        <div class="webhook-url-box">
                                            <code id="webhook-url-duitku">{{ webhookUrl }}/webhooks/duitku</code>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Langkah konfigurasi:</strong><br>
                                                1. Copy URL di atas<br>
                                                2. Login ke Duitku Dashboard → Settings → Callback URL<br>
                                                3. Paste URL yang sudah di-copy<br>
                                                4. Save settings
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Additional Settings -->
                        <div class="form-section">
                            <h6 class="section-title">Additional Settings</h6>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Setup Fee (IDR)</label>
                                    <input 
                                        type="number" 
                                        v-model="form.subscription_setup_fee" 
                                        class="form-control"
                                        min="0"
                                        placeholder="0">
                                    <small class="text-muted">One-time fee for new subscriptions</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Enable Trial Period</label>
                                    <div class="form-check form-switch mt-2">
                                        <input 
                                            class="form-check-input" 
                                            type="checkbox" 
                                            v-model="form.enable_trial_period"
                                            id="enableTrial">
                                        <label class="form-check-label" for="enableTrial">
                                            {{ form.enable_trial_period ? 'Enabled' : 'Disabled' }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4" v-show="form.enable_trial_period">
                                    <label class="form-label">Trial Period (Days)</label>
                                    <input 
                                        type="number" 
                                        v-model="form.trial_period_days" 
                                        class="form-control"
                                        min="1"
                                        max="90"
                                        placeholder="14">
                                </div>
                            </div>
                        </div>

                        <!-- Test Result -->
                        <div v-if="testResult" class="alert mt-4" :class="testResult.success ? 'alert-success' : 'alert-danger'">
                            <i class="bi" :class="testResult.success ? 'bi-check-circle' : 'bi-x-circle'"></i>
                            {{ testResult.message }}
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" :disabled="processing">
                            <i class="bi bi-save me-2"></i>
                            {{ processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- General Settings Tab (Placeholder) -->
            <div v-show="activeTab === 'general'" class="settings-card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-gear" style="font-size: 48px; color: #94A3B8;"></i>
                    <h5 class="mt-3">General Settings</h5>
                    <p class="text-muted">Coming soon...</p>
                </div>
            </div>

            <!-- Email Settings Tab -->
            <div v-show="activeTab === 'email'" class="settings-card">
                <div class="card-header">
                    <div>
                        <h5 class="card-title">Email Configuration</h5>
                        <p class="card-subtitle">Configure SMTP settings for sending emails (tenant registration, notifications, etc.)</p>
                    </div>
                </div>

                <form @submit.prevent="saveEmailSettings">
                    <div class="card-body">
                        <!-- Quick Guide -->
                        <div class="alert alert-info mb-4">
                            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Cara Menggunakan Email Settings</h6>
                            <p class="mb-2"><strong>Email Settings digunakan untuk mengirim email otomatis ke tenant saat:</strong></p>
                            <ul class="mb-0 ps-3">
                                <li>Tenant baru mendaftar (email registrasi dengan link pembayaran)</li>
                                <li>Tenant memilih trial plan (email welcome)</li>
                                <li>Notifikasi pembayaran dan lainnya</li>
                            </ul>
                            <hr class="my-3">
                            <p class="mb-2"><strong>Contoh Provider SMTP yang bisa digunakan:</strong></p>
                            <ul class="mb-0 ps-3">
                                <li><strong>Gmail:</strong> smtp.gmail.com, Port 587 (TLS) atau 465 (SSL), gunakan App Password</li>
                                <li><strong>SendGrid:</strong> smtp.sendgrid.net, Port 587, gunakan API Key sebagai password</li>
                                <li><strong>Mailgun:</strong> smtp.mailgun.org, Port 587, gunakan SMTP credentials</li>
                                <li><strong>Amazon SES:</strong> email-smtp.region.amazonaws.com, Port 587</li>
                            </ul>
                        </div>

                        <div class="form-section">
                            <h6 class="section-title">SMTP Configuration</h6>
                            <p class="section-description">Configure your email server settings. Leave password empty to keep current password.</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Mail Driver <span class="text-danger">*</span></label>
                                    <select v-model="emailForm.mail_mailer" class="form-select">
                                        <option value="smtp">SMTP</option>
                                        <option value="sendmail">Sendmail</option>
                                        <option value="mailgun">Mailgun</option>
                                        <option value="ses">Amazon SES</option>
                                        <option value="postmark">Postmark</option>
                                        <option value="log">Log (Testing)</option>
                                    </select>
                                </div>

                                <div class="col-md-6" v-show="emailForm.mail_mailer === 'smtp'">
                                    <label class="form-label">SMTP Host *</label>
                                    <input 
                                        type="text" 
                                        v-model="emailForm.mail_host" 
                                        class="form-control"
                                        placeholder="smtp.gmail.com"
                                        required>
                                    <small class="text-muted">Contoh: smtp.gmail.com, smtp.sendgrid.net, smtp.mailgun.org</small>
                                </div>

                                <div class="col-md-3" v-show="emailForm.mail_mailer === 'smtp'">
                                    <label class="form-label">SMTP Port *</label>
                                    <input 
                                        type="number" 
                                        v-model="emailForm.mail_port" 
                                        class="form-control"
                                        placeholder="587"
                                        required>
                                    <small class="text-muted">Biasanya 587 (TLS) atau 465 (SSL)</small>
                                </div>

                                <div class="col-md-3" v-show="emailForm.mail_mailer === 'smtp'">
                                    <label class="form-label">Encryption *</label>
                                    <select v-model="emailForm.mail_encryption" class="form-select" required>
                                        <option value="tls">TLS (Port 587)</option>
                                        <option value="ssl">SSL (Port 465)</option>
                                        <option value="">None (Port 25)</option>
                                    </select>
                                    <small class="text-muted">Pilih sesuai port yang digunakan</small>
                                </div>

                                <div class="col-md-6" v-show="emailForm.mail_mailer === 'smtp'">
                                    <label class="form-label">SMTP Username</label>
                                    <input 
                                        type="text" 
                                        v-model="emailForm.mail_username" 
                                        class="form-control"
                                        placeholder="your-email@gmail.com">
                                    <small class="text-muted">Email address atau username dari provider SMTP</small>
                                </div>

                                <div class="col-md-6" v-show="emailForm.mail_mailer === 'smtp'">
                                    <label class="form-label">SMTP Password</label>
                                    <input 
                                        type="password" 
                                        v-model="emailForm.mail_password" 
                                        class="form-control"
                                        placeholder="••••••••">
                                    <small class="text-muted">Password atau App Password (untuk Gmail). Kosongkan jika tidak ingin mengubah password yang sudah ada.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">From Address (Pengirim) <span class="text-danger">*</span></label>
                                    <input 
                                        type="email" 
                                        v-model="emailForm.mail_from_address" 
                                        class="form-control"
                                        placeholder="noreply@kitabill.site"
                                        required>
                                    <small class="text-muted">Email yang akan muncul sebagai pengirim. Harus sesuai dengan SMTP account atau verified domain.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">From Name (Nama Pengirim) <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        v-model="emailForm.mail_from_name" 
                                        class="form-control"
                                        placeholder="KitaBill"
                                        required>
                                    <small class="text-muted">Nama yang akan muncul di email (contoh: "KitaBill" atau "KitaBill Support")</small>
                                </div>
                            </div>
                        </div>

                        <div v-if="emailTestResult" class="alert mt-4" :class="emailTestResult.success ? 'alert-success' : 'alert-danger'">
                            <i class="bi" :class="emailTestResult.success ? 'bi-check-circle' : 'bi-x-circle'"></i>
                            {{ emailTestResult.message }}
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="button" @click="testEmailConnection" class="btn btn-outline-primary me-2" :disabled="testingEmail">
                            <i class="bi bi-envelope-check me-2"></i>
                            {{ testingEmail ? 'Testing...' : 'Test Email' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="processingEmail">
                            <i class="bi bi-save me-2"></i>
                            {{ processingEmail ? 'Saving...' : 'Save Email Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- WhatsApp Settings Tab -->
            <div v-show="activeTab === 'whatsapp'" class="settings-card">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-whatsapp text-success me-2"></i>WhatsApp Gateway
                    </h5>
                </div>
                <div class="card-body">
                    <!-- WhatsApp Connection Status -->
                    <div class="mb-4">
                        <p class="text-muted mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Scan QR untuk menghubungkan WhatsApp ke gateway. QR akan auto-refresh setiap 1 menit jika belum terkoneksi.
                        </p>

                        <div class="d-flex align-items-center mb-3">
                            <span 
                                :class="waStatus.connected ? 'badge bg-success' : 'badge bg-danger'" 
                                class="me-2 px-3 py-2">
                                <i :class="waStatus.connected ? 'bi bi-check-circle' : 'bi bi-x-circle'" class="me-1"></i>
                                {{ waStatus.status || 'CHECKING...' }}
                            </span>
                            <span class="text-muted">
                                {{ waStatus.connected ? 'Terhubung dan siap digunakan.' : 'Belum terhubung.' }}
                            </span>
                            <span v-if="waStatus.uptime && waStatus.uptime !== null" class="text-muted ms-2">
                                (Uptime: {{ String(waStatus.uptime) }})
                            </span>
                        </div>

                        <div class="mb-3">
                            <button 
                                type="button" 
                                @click="scanNewQR" 
                                class="btn btn-primary btn-sm me-2"
                                :disabled="scanningQR">
                                <span v-if="scanningQR" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-arrow-clockwise me-1"></i> 
                                Scan QR Baru
                            </button>
                            <button 
                                type="button" 
                                @click="reconnectWA" 
                                class="btn btn-outline-secondary btn-sm"
                                :disabled="reconnecting">
                                <span v-if="reconnecting" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-plug me-1"></i> 
                                Reconnect
                            </button>
                        </div>

                        <!-- QR Code Display -->
                        <div v-if="qrCodeUrl && !waStatus.connected" class="mb-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold mb-3">Scan QR Code dengan WhatsApp</h6>
                                    <img 
                                        :src="qrCodeUrl" 
                                        alt="WhatsApp QR Code" 
                                        class="img-fluid mb-3"
                                        style="max-width: 300px; border: 2px solid #0d6efd; border-radius: 8px;">
                                    <p class="text-muted small mb-0">
                                        Buka WhatsApp > Settings > Linked Devices > Link a Device
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor WhatsApp (Opsional)</label>
                        <input 
                            v-model="whatsappForm.whatsapp_phone_number" 
                            type="text" 
                            class="form-control" 
                            placeholder="628xxxxxxxxxxx">
                        <small class="text-muted">Nomor yang akan digunakan untuk notifikasi</small>
                    </div>

                    <!-- Test WhatsApp -->
                    <div class="card border-primary mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Test WhatsApp</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input 
                                        v-model="testForm.phone" 
                                        type="text" 
                                        class="form-control" 
                                        placeholder="6281234567890">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Message</label>
                                    <input 
                                        v-model="testForm.message" 
                                        type="text" 
                                        class="form-control" 
                                        placeholder="Test message dari KITABILL">
                                </div>
                            </div>
                            <button 
                                type="button" 
                                @click="testWhatsApp" 
                                class="btn btn-success"
                                :disabled="testingWhatsApp">
                                <span v-if="testingWhatsApp" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="bi bi-send me-2"></i> 
                                Kirim Test
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referral Settings Tab -->
            <div v-show="activeTab === 'referral'" class="settings-card">
                <div class="card-header bg-white border-bottom py-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon-box bg-primary-subtle text-primary">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-1">Referral System Configuration</h5>
                            <p class="card-subtitle mb-0">Manage global referral system settings, commission rates, and withdrawal rules.</p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="saveReferralSettings">
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="p-4 rounded-4 bg-light border">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1">Global System Status</h6>
                                            <p class="text-muted small mb-0">Enable or disable the referral feature for all tenants at once.</p>
                                        </div>
                                        <div class="form-check form-switch custom-switch lg">
                                            <input class="form-check-input" type="checkbox" v-model="referralForm.referral_system_enabled" id="referralEnabled">
                                            <label class="form-check-label fw-bold" for="referralEnabled">
                                                {{ referralForm.referral_system_enabled ? 'Active' : 'Inactive' }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group p-4 rounded-4 border bg-white h-100">
                                    <label class="form-label fw-bold text-uppercase small text-muted mb-3">
                                        <i class="bi bi-percent me-2"></i>Default Commission Rate
                                    </label>
                                    <div class="input-group input-group-lg border rounded-3 overflow-hidden">
                                        <input type="number" v-model="referralForm.global_referral_commission_rate" class="form-control border-0" min="0" max="100" step="0.1" placeholder="10">
                                        <span class="input-group-text bg-light border-0 fw-bold">%</span>
                                    </div>
                                    <p class="text-muted small mt-3 mb-0">
                                        Percentage of payment given as commission to referrers. Individual tenants can have custom rates that override this.
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group p-4 rounded-4 border bg-white h-100">
                                    <label class="form-label fw-bold text-uppercase small text-muted mb-3">
                                        <i class="bi bi-wallet2 me-2"></i>Minimum Withdrawal
                                    </label>
                                    <div class="input-group input-group-lg border rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-light border-0 fw-bold">Rp</span>
                                        <input type="number" v-model="referralForm.referral_min_withdrawal_amount" class="form-control border-0" min="0" placeholder="50000">
                                    </div>
                                    <p class="text-muted small mt-3 mb-0">
                                        Minimum balance a tenant must reach before they can submit a withdrawal request.
                                    </p>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="alert alert-info border-info-subtle bg-info-subtle p-4 rounded-4 mb-0">
                                    <div class="d-flex gap-3">
                                        <i class="bi bi-info-circle-fill fs-4 text-info"></i>
                                        <div class="small">
                                            <h6 class="fw-bold mb-1">Administrator Note:</h6>
                                            <p class="mb-0">
                                                When the global referral system is disabled, all referral links will cease to function, and no new commissions will be calculated. 
                                                However, existing balances and earned commissions will remain in the database for record-keeping.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-4 border-top">
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-bold shadow-sm" :disabled="processingReferral">
                            <span v-if="processingReferral" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-save-fill me-2"></i>
                            {{ processingReferral ? 'Applying Changes...' : 'Save Referral Configuration' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, getCurrentInstance } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import axios from 'axios';

// ✅ Direct URL constants - tidak menggunakan helper untuk menghindari masalah
const WA_GATEWAY_ROUTES = {
    STATUS: '/wa-gateway/status',
    QR: '/wa-gateway/qr',
    RECONNECT: '/wa-gateway/reconnect',
    SEND_TEST: '/wa-gateway/send-test',
};

const props = defineProps({
    paymentSettings: Object,
    whatsappSettings: Object,
    emailSettings: Object,
    referralSettings: Object,
    activeGateway: String,
    webhookUrl: String,
});

const activeTab = ref('payment');
const processing = ref(false);
const testing = ref(false);
const testResult = ref(null);
const processingWhatsApp = ref(false);
const testingWhatsApp = ref(false);
const whatsappTestResult = ref(null);

const form = reactive({
    subscription_payment_gateway: props.activeGateway || 'xendit',
    xendit_subscription_api_key: props.paymentSettings?.xendit_subscription_api_key?.value || '',
    xendit_subscription_webhook_token: props.paymentSettings?.xendit_subscription_webhook_token?.value || '',
    midtrans_subscription_server_key: props.paymentSettings?.midtrans_subscription_server_key?.value || '',
    midtrans_subscription_client_key: props.paymentSettings?.midtrans_subscription_client_key?.value || '',
    duitku_subscription_merchant_code: props.paymentSettings?.duitku_subscription_merchant_code?.value || '',
    duitku_subscription_api_key: props.paymentSettings?.duitku_subscription_api_key?.value || '',
    tripay_merchant_code: props.paymentSettings?.tripay_merchant_code?.value || '',
    tripay_api_key: props.paymentSettings?.tripay_api_key?.value || '',
    tripay_private_key: props.paymentSettings?.tripay_private_key?.value || '',
    tripay_mode: props.paymentSettings?.tripay_mode?.value || 'sandbox',
    tripay_enabled: props.paymentSettings?.tripay_enabled?.value === '1' || props.paymentSettings?.tripay_enabled?.value === true,
    subscription_setup_fee: props.paymentSettings?.subscription_setup_fee?.value || '0',
    enable_trial_period: props.paymentSettings?.enable_trial_period?.value === '1' || true,
    trial_period_days: props.paymentSettings?.trial_period_days?.value || '14',
});

const whatsappForm = reactive({
    whatsapp_provider: props.whatsappSettings?.whatsapp_provider?.value || 'custom',
    whatsapp_api_url: props.whatsappSettings?.whatsapp_api_url?.value || '',
    whatsapp_api_token: props.whatsappSettings?.whatsapp_api_token?.value || '',
    whatsapp_phone_number: props.whatsappSettings?.whatsapp_phone_number?.value || '',
    whatsapp_otp_template: props.whatsappSettings?.whatsapp_otp_template?.value || 'Kode OTP KitaBill Anda: {code}\n\nJangan berikan kode ini kepada siapapun.\nBerlaku selama 5 menit.',
});

const emailForm = reactive({
    mail_mailer: props.emailSettings?.mail_mailer?.value || 'smtp',
    mail_host: props.emailSettings?.mail_host?.value || '',
    mail_port: props.emailSettings?.mail_port?.value || '587',
    mail_username: props.emailSettings?.mail_username?.value || '',
    mail_password: '', // Always empty, user needs to enter if want to change
    mail_encryption: props.emailSettings?.mail_encryption?.value || 'tls',
    mail_from_address: props.emailSettings?.mail_from_address?.value || 'noreply@kitabill.site',
    mail_from_name: props.emailSettings?.mail_from_name?.value || 'KitaBill',
});

const referralForm = reactive({
    referral_system_enabled: props.referralSettings?.referral_system_enabled?.value === '1' || props.referralSettings?.referral_system_enabled?.value === true,
    global_referral_commission_rate: props.referralSettings?.global_referral_commission_rate?.value || 10,
    referral_min_withdrawal_amount: props.referralSettings?.referral_min_withdrawal_amount?.value || 50000,
});

const processingEmail = ref(false);
const processingReferral = ref(false);
const testingEmail = ref(false);
const emailTestResult = ref(null);

// WhatsApp Gateway state
const waStatus = reactive({
    connected: false,
    status: 'CHECKING...',
    uptime: null
});
const qrCodeUrl = ref(null);
const scanningQR = ref(false);
const reconnecting = ref(false);
const statusCheckInterval = ref(null);
const qrRefreshInterval = ref(null);
const testForm = reactive({
    phone: '6281234567890',
    message: 'Test message dari KITABILL'
});

const savePaymentSettings = () => {
    processing.value = true;
    testResult.value = null;

    router.post(route('superadmin.settings.payment.update'), form, {
        onSuccess: () => {
            processing.value = false;
        },
        onError: () => {
            processing.value = false;
        }
    });
};

const saveReferralSettings = () => {
    processingReferral.value = true;
    router.post(route('superadmin.settings.referral.update'), referralForm, {
        onSuccess: () => {
            processingReferral.value = false;
        },
        onError: () => {
            processingReferral.value = false;
        }
    });
};

const testConnection = async (gateway) => {
    testing.value = true;
    testResult.value = null;

    try {
        const response = await fetch(route('superadmin.settings.test-connection'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ gateway })
        });

        const data = await response.json();
        testResult.value = data;
    } catch (error) {
        testResult.value = {
            success: false,
            message: 'Connection test failed: ' + error.message
        };
    } finally {
        testing.value = false;
    }
};

const saveWhatsAppSettings = () => {
    processingWhatsApp.value = true;
    whatsappTestResult.value = null;

    router.post(route('superadmin.settings.whatsapp.update'), whatsappForm, {
        onSuccess: () => {
            processingWhatsApp.value = false;
        },
        onError: () => {
            processingWhatsApp.value = false;
        }
    });
};

const testWhatsAppConnection = async () => {
    testingWhatsApp.value = true;
    whatsappTestResult.value = null;

    try {
        const response = await fetch(route('superadmin.settings.test-whatsapp'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                test_number: whatsappForm.whatsapp_phone_number
            })
        });

        const data = await response.json();
        whatsappTestResult.value = data;
    } catch (error) {
        whatsappTestResult.value = {
            success: false,
            message: 'Connection test failed: ' + error.message
        };
    } finally {
        testingWhatsApp.value = false;
    }
};

const copyWebhookUrl = (gateway) => {
    const url = `${props.webhookUrl}/webhooks/${gateway}`;
    navigator.clipboard.writeText(url).then(() => {
        // Show toast notification (you can use a toast library or simple alert)
        alert('Webhook URL copied to clipboard!');
    }).catch(() => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Webhook URL copied to clipboard!');
    });
};

const saveEmailSettings = () => {
    processingEmail.value = true;
    emailTestResult.value = null;

    router.post(route('superadmin.settings.email.update'), emailForm, {
        onSuccess: () => {
            processingEmail.value = false;
        },
        onError: () => {
            processingEmail.value = false;
        }
    });
};

const testEmailConnection = async () => {
    testingEmail.value = true;
    emailTestResult.value = null;

    try {
        // For now, just show a message that test email feature will be implemented
        // You can implement actual test email sending later
        emailTestResult.value = {
            success: true,
            message: 'Email settings saved. Test email feature coming soon!'
        };
    } catch (error) {
        emailTestResult.value = {
            success: false,
            message: 'Test failed: ' + error.message
        };
    } finally {
        testingEmail.value = false;
    }
};

// WhatsApp Gateway Functions
const checkWAStatus = async () => {
    try {
        const url = WA_GATEWAY_ROUTES.STATUS;
        console.log('🔍 Checking WA status:', url);
        const response = await axios.get(url);
        console.log('✅ WA status response:', response.data);
        if (response.data && response.data.success !== false) {
            waStatus.connected = response.data.connected || false;
            waStatus.status = waStatus.connected ? 'CONNECTED' : 'DISCONNECTED';
            // ✅ FIX: Pastikan uptime selalu string atau null, bukan undefined
            waStatus.uptime = response.data.uptime || null;
            
            // Jika sudah connected, stop QR refresh
            if (waStatus.connected) {
                // ✅ Speed up UI update: switch status polling to 10s when connected
                if (statusCheckInterval.value) {
                    clearInterval(statusCheckInterval.value);
                }
                statusCheckInterval.value = setInterval(() => {
                    checkWAStatus();
                }, 10000);

                if (qrRefreshInterval.value) {
                    clearInterval(qrRefreshInterval.value);
                    qrRefreshInterval.value = null;
                }
                qrCodeUrl.value = null;
            } else {
                // ✅ While disconnected/connecting, poll status every 2s for faster CONNECTED detection
                if (statusCheckInterval.value) {
                    clearInterval(statusCheckInterval.value);
                }
                statusCheckInterval.value = setInterval(() => {
                    checkWAStatus();
                }, 2000);
            }
        } else {
            waStatus.connected = false;
            waStatus.status = 'DISCONNECTED';
            waStatus.uptime = null;
        }
    } catch (error) {
        console.error('❌ Error checking WA status:', error);
        console.error('❌ Error URL:', error.config?.url);
        console.error('❌ Error response:', error.response?.data);
        waStatus.connected = false;
        waStatus.status = 'DISCONNECTED';
        waStatus.uptime = null;
    }
};

const scanNewQR = async () => {
    if (scanningQR.value) return;
    scanningQR.value = true;
    try {
        // Jika sudah connected, reconnect dulu untuk generate QR baru
        // Jika belum connected, langsung ambil QR tanpa reconnect
        if (waStatus.connected) {
            await axios.post(WA_GATEWAY_ROUTES.RECONNECT);
            // Tunggu sebentar setelah reconnect
            await new Promise(resolve => setTimeout(resolve, 2000));
        }
        
        // Ambil QR code
        const response = await axios.get(WA_GATEWAY_ROUTES.QR);
        if (response.data.success && (response.data.dataUrl || response.data.qrImage)) {
            qrCodeUrl.value = response.data.dataUrl || response.data.qrImage;
            
            // Auto-refresh QR lebih sering saat proses scan (biar cepat update)
            if (qrRefreshInterval.value) {
                clearInterval(qrRefreshInterval.value);
            }
            qrRefreshInterval.value = setInterval(async () => {
                if (!waStatus.connected) {
                    try {
                        const qrResponse = await axios.get(WA_GATEWAY_ROUTES.QR);
                        if (qrResponse.data.success && (qrResponse.data.dataUrl || qrResponse.data.qrImage)) {
                            qrCodeUrl.value = qrResponse.data.dataUrl || qrResponse.data.qrImage;
                        } else if (qrResponse.data.connected) {
                            // Sudah connected, stop refresh
                            clearInterval(qrRefreshInterval.value);
                            qrRefreshInterval.value = null;
                            qrCodeUrl.value = null;
                            checkWAStatus();
                        }
                    } catch (error) {
                        console.error('Error refreshing QR:', error);
                    }
                } else {
                    clearInterval(qrRefreshInterval.value);
                    qrRefreshInterval.value = null;
                }
            }, 1500); // 1.5s saat proses scan
        } else if (response.data.connected) {
            qrCodeUrl.value = null;
            checkWAStatus();
        } else {
            alert('QR code belum tersedia. Silakan coba lagi dalam beberapa saat.');
        }
    } catch (error) {
        console.error('Error getting QR:', error);
        alert('Gagal mengambil QR code: ' + (error.response?.data?.message || error.message));
    } finally {
        scanningQR.value = false;
    }
};

const reconnectWA = async () => {
    if (reconnecting.value) return;
    if (!confirm('Reconnect akan logout WhatsApp dan generate QR baru. Lanjutkan?')) return;
    
    reconnecting.value = true;
    try {
        await axios.post(WA_GATEWAY_ROUTES.RECONNECT);
        waStatus.connected = false;
        waStatus.status = 'DISCONNECTED';
        waStatus.uptime = null;
        qrCodeUrl.value = null;
        
        // Tunggu sebentar lalu scan QR baru
        setTimeout(() => {
            scanNewQR();
        }, 2000);
    } catch (error) {
        console.error('Error reconnecting:', error);
        alert('Gagal reconnect: ' + (error.response?.data?.message || error.message));
    } finally {
        reconnecting.value = false;
    }
};

const testWhatsApp = async () => {
    if (!testForm.phone || !testForm.message) {
        alert('Phone number dan message harus diisi');
        return;
    }
    
    testingWhatsApp.value = true;
    try {
        const response = await axios.post(WA_GATEWAY_ROUTES.SEND_TEST, {
            phone: testForm.phone,
            message: testForm.message
        });
        
        if (response.data.success) {
            alert('Pesan berhasil dikirim!');
        } else {
            alert('Gagal mengirim pesan: ' + (response.data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error sending test message:', error);
        alert('Gagal mengirim pesan: ' + (error.response?.data?.message || error.message));
    } finally {
        testingWhatsApp.value = false;
    }
};

// Auto-check status: lebih sering saat belum connected agar UI cepat update setelah scan QR
const startStatusCheck = () => {
    if (statusCheckInterval.value) {
        clearInterval(statusCheckInterval.value);
    }
    checkWAStatus(); // Check immediately
    statusCheckInterval.value = setInterval(() => {
        checkWAStatus();
    }, waStatus.connected ? 10000 : 2000); // 2s saat DISCONNECTED, 10s saat CONNECTED
};

// Initialize on mount
onMounted(() => {
    if (activeTab.value === 'whatsapp') {
        startStatusCheck();
    }
});

// Watch activeTab to start/stop status check
watch(activeTab, (newTab) => {
    if (newTab === 'whatsapp') {
        startStatusCheck();
    } else {
        if (statusCheckInterval.value) {
            clearInterval(statusCheckInterval.value);
            statusCheckInterval.value = null;
        }
        if (qrRefreshInterval.value) {
            clearInterval(qrRefreshInterval.value);
            qrRefreshInterval.value = null;
        }
    }
});

onUnmounted(() => {
    if (statusCheckInterval.value) {
        clearInterval(statusCheckInterval.value);
    }
    if (qrRefreshInterval.value) {
        clearInterval(qrRefreshInterval.value);
    }
});
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

.page-subtitle {
    color: #64748B;
    margin: 0;
}

/* Tabs */
.settings-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid #E2E8F0;
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #64748B;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: -2px;
}

.tab-btn:hover {
    color: #3B82F6;
    background: #F1F5F9;
}

.tab-btn.active {
    color: #3B82F6;
    border-bottom-color: #3B82F6;
}

/* Settings Card */
.settings-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.card-header {
    padding: 24px 32px;
    border-bottom: 1px solid #E2E8F0;
}

.card-title {
    font-size: 20px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 4px;
}

.card-subtitle {
    color: #64748B;
    margin: 0;
    font-size: 14px;
}

.card-body {
    padding: 32px;
}

.card-footer {
    padding: 24px 32px;
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
}

/* Form Sections */
.form-section {
    margin-bottom: 24px;
}

.section-title {
    font-size: 16px;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 8px;
}

.section-description {
    font-size: 14px;
    color: #64748B;
    margin-bottom: 20px;
}

/* Gateway Selector */
.gateway-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.gateway-option {
    cursor: pointer;
}

.gateway-card {
    background: #F8FAFC;
    border: 2px solid #E2E8F0;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.gateway-option:hover .gateway-card {
    border-color: #3B82F6;
    background: #EFF6FF;
}

.gateway-option.active .gateway-card {
    border-color: #3B82F6;
    background: #EFF6FF;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.gateway-logo {
    height: 48px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gateway-logo img {
    max-height: 100%;
    max-width: 150px;
}

.gateway-name {
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 8px;
}

.gateway-badge {
    color: #3B82F6;
    font-size: 14px;
    font-weight: 600;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 8px;
}

.form-control {
    border: 2px solid #E2E8F0;
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #3B82F6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-check-input {
    width: 48px;
    height: 24px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: #3B82F6;
    border-color: #3B82F6;
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

.btn-outline-primary {
    border: 2px solid #3B82F6;
    color: #3B82F6;
    background: transparent;
}

.btn-outline-primary:hover:not(:disabled) {
    background: #3B82F6;
    color: white;
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
}

/* Webhook URL Box */
.webhook-url-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    margin: 8px 0;
}

:global(.dark) .webhook-url-box {
    background: #0F172A;
    border-color: #334155;
}

.webhook-url-box code {
    color: #3B82F6;
    font-size: 14px;
    word-break: break-all;
}

:global(.dark) .webhook-url-box code {
    color: #60A5FA;
}

/* Responsive */
/* Webhook URL Box */
.webhook-url-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 12px;
    margin: 8px 0;
}

:global(.dark) .webhook-url-box {
    background: #0F172A;
    border-color: #334155;
}

.webhook-url-box code {
    color: #3B82F6;
    font-size: 14px;
    word-break: break-all;
}

:global(.dark) .webhook-url-box code {
    color: #60A5FA;
}

@media (max-width: 768px) {
    .gateway-selector {
        grid-template-columns: 1fr;
    }
}
</style>
