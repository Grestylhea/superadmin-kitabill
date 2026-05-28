<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // 1. Expired Notification
            [
                'key' => 'wa_expired_template',
                'value' => "PEMBERITAHUAN KADALUARSA\n"
                    . "{invoice_number}\n\n"
                    . "Yth. {customer_name}\n\n"
                    . "Kami ingin memberitahukan bahwa masa aktif langganan internet Anda telah berakhir. Berikut adalah detail langganan Anda:\n\n"
                    . "========= RINCIAN LANGGANAN =========\n"
                    . "📄 Nomor Invoice: {invoice_number}\n"
                    . "💰 Total Tagihan: Rp {total}\n"
                    . "📅 Tanggal Jatuh Tempo: {due_date}\n"
                    . "📦 Paket: {package_name}\n"
                    . "👤 Customer Code: {customer_code}\n"
                    . "📅 Periode: {period}\n\n"
                    . "========= STATUS LANGGANAN =========\n"
                    . "🔴 Layanan Anda saat ini *DIBLOKIR* karena tagihan belum dibayar.\n\n"
                    . "Untuk mengaktifkan kembali layanan internet Anda, silakan lakukan pembayaran tagihan sesuai dengan rincian di atas.\n\n"
                    . "Setelah pembayaran diterima, layanan akan diaktifkan kembali secara otomatis.\n\n"
                    . "Jika Anda memiliki pertanyaan atau membutuhkan bantuan, jangan ragu untuk menghubungi customer service kami.\n\n"
                    . "Terima kasih atas perhatiannya.\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
            
            // 2. Reminder 7 Days
            [
                'key' => 'wa_reminder_h7',
                'value' => "PENGINGAT TAGIHAN\n"
                    . "H-7 Sebelum Jatuh Tempo\n\n"
                    . "Yth. {customer_name}\n\n"
                    . "Kami ingin mengingatkan bahwa tagihan internet Anda akan jatuh tempo dalam *7 hari*.\n\n"
                    . "========= RINCIAN TAGIHAN =========\n"
                    . "📄 Nomor Invoice: {invoice_number}\n"
                    . "💰 Total Tagihan: Rp {total}\n"
                    . "📅 Tanggal Jatuh Tempo: {due_date}\n"
                    . "📦 Paket: {package_name}\n"
                    . "📅 Periode: {period}\n\n"
                    . "========= INFORMASI PENTING =========\n"
                    . "Mohon lakukan pembayaran sebelum tanggal jatuh tempo untuk menghindari gangguan layanan.\n\n"
                    . "Setelah tanggal jatuh tempo, layanan akan otomatis diblokir jika pembayaran belum diterima.\n\n"
                    . "Terima kasih atas perhatiannya.\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
            
            // 3. Reminder 3 Days
            [
                'key' => 'wa_reminder_h3',
                'value' => "PERINGATAN TAGIHAN\n"
                    . "H-3 Sebelum Jatuh Tempo\n\n"
                    . "Yth. {customer_name}\n\n"
                    . "⚠️ *PERINGATAN PENTING*\n\n"
                    . "Tagihan internet Anda akan jatuh tempo dalam *3 hari*!\n\n"
                    . "========= RINCIAN TAGIHAN =========\n"
                    . "📄 Nomor Invoice: {invoice_number}\n"
                    . "💰 Total Tagihan: Rp {total}\n"
                    . "📅 Tanggal Jatuh Tempo: {due_date}\n"
                    . "📦 Paket: {package_name}\n"
                    . "📅 Periode: {period}\n\n"
                    . "========= TINDAKAN YANG DIPERLUKAN =========\n"
                    . "⚠️ Segera lakukan pembayaran untuk menghindari:\n"
                    . "• Gangguan layanan internet\n"
                    . "• Blokir akses otomatis\n"
                    . "• Ketidaknyamanan dalam penggunaan\n\n"
                    . "Mohon segera lakukan pembayaran sebelum tanggal jatuh tempo.\n\n"
                    . "Jika Anda sudah melakukan pembayaran, mohon abaikan pesan ini.\n\n"
                    . "Terima kasih.\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
            
            // 4. Reminder 1 Day
            [
                'key' => 'wa_reminder_h1',
                'value' => "PERINGATAN TERAKHIR\n"
                    . "H-1 Sebelum Jatuh Tempo\n\n"
                    . "Yth. {customer_name}\n\n"
                    . "🚨 *PERINGATAN TERAKHIR*\n\n"
                    . "Tagihan internet Anda akan jatuh tempo *BESOK*!\n\n"
                    . "========= RINCIAN TAGIHAN =========\n"
                    . "📄 Nomor Invoice: {invoice_number}\n"
                    . "💰 Total Tagihan: Rp {total}\n"
                    . "📅 Tanggal Jatuh Tempo: {due_date}\n"
                    . "📦 Paket: {package_name}\n"
                    . "📅 Periode: {period}\n\n"
                    . "========= PERINGATAN PENTING =========\n"
                    . "🚨 Jika pembayaran tidak dilakukan sebelum tanggal jatuh tempo, layanan internet Anda akan *OTOMATIS DIBLOKIR*.\n\n"
                    . "Segera lakukan pembayaran untuk menghindari:\n"
                    . "• Blokir layanan otomatis\n"
                    . "• Gangguan akses internet\n"
                    . "• Ketidaknyamanan dalam penggunaan\n\n"
                    . "Jangan sampai terlambat! Lakukan pembayaran sekarang juga.\n\n"
                    . "Jika Anda sudah melakukan pembayaran, mohon abaikan pesan ini.\n\n"
                    . "Terima kasih.\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
            
            // 5. Invoice Notification
            [
                'key' => 'wa_invoice_notification',
                'value' => "TAGIHAN BARU\n"
                    . "Invoice #{invoice_number}\n\n"
                    . "Yth. {customer_name}\n\n"
                    . "Tagihan bulanan internet Anda telah dibuat. Berikut adalah rincian tagihan:\n\n"
                    . "========= RINCIAN TAGIHAN =========\n"
                    . "📄 Nomor Invoice: {invoice_number}\n"
                    . "💰 Total Tagihan: Rp {total}\n"
                    . "📅 Tanggal Invoice: {issue_date}\n"
                    . "📅 Tanggal Jatuh Tempo: {due_date}\n"
                    . "📦 Paket: {package_name}\n"
                    . "📅 Periode: {period}\n"
                    . "👤 Customer Code: {customer_code}\n\n"
                    . "========= CARA PEMBAYARAN =========\n"
                    . "Silakan lakukan pembayaran sebelum tanggal jatuh tempo melalui:\n"
                    . "• Transfer Bank\n"
                    . "• E-Wallet\n"
                    . "• Payment Gateway (jika tersedia)\n\n"
                    . "Setelah melakukan pembayaran, mohon konfirmasi dengan mengirimkan bukti pembayaran kepada customer service kami.\n\n"
                    . "Jika Anda memiliki pertanyaan terkait tagihan ini, jangan ragu untuk menghubungi customer service kami.\n\n"
                    . "Terima kasih atas kepercayaan Anda menggunakan layanan kami.\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
            
            // 6. Reactivation / Balance Payment
            [
                'key' => 'wa_reactivation_template',
                'value' => "KONFIRMASI PEMBAYARAN\n"
                    . "Layanan Diaktifkan Kembali\n\n"
                    . "Yth. {customer_name}\n\n"
                    . "✅ *PEMBAYARAN DITERIMA*\n\n"
                    . "Kami ingin memberitahukan bahwa pembayaran Anda telah kami terima dan diproses.\n\n"
                    . "========= STATUS LANGGANAN =========\n"
                    . "✅ Layanan internet Anda telah *DIAKTIFKAN KEMBALI*\n"
                    . "📦 Paket: {package_name}\n"
                    . "🌐 Status: AKTIF\n"
                    . "👤 Customer Code: {customer_code}\n\n"
                    . "========= INFORMASI =========\n"
                    . "Layanan internet Anda sekarang sudah dapat digunakan kembali secara normal.\n\n"
                    . "Jika Anda mengalami kendala dalam mengakses internet, silakan:\n"
                    . "1. Restart modem/router Anda\n"
                    . "2. Cek koneksi kabel/wi-fi\n"
                    . "3. Hubungi customer service jika masalah masih berlanjut\n\n"
                    . "Terima kasih atas pembayaran Anda dan kepercayaan yang telah diberikan kepada kami.\n\n"
                    . "Selamat menikmati layanan internet kami!\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
            
            // 7. Welcome Message
            [
                'key' => 'wa_welcome_message',
                'value' => "SELAMAT DATANG\n"
                    . "Registrasi Berhasil\n\n"
                    . "Halo {customer_name},\n\n"
                    . "Selamat! Akun internet Anda telah berhasil dibuat dan diaktifkan.\n\n"
                    . "========= INFORMASI AKUN =========\n"
                    . "👤 Nama: {customer_name}\n"
                    . "🔑 Customer Code: {customer_code}\n"
                    . "👤 Username: {username}\n"
                    . "📦 Paket: {package_name}\n"
                    . "📞 Nomor HP: {phone}\n\n"
                    . "========= CARA MENGGUNAKAN =========\n"
                    . "1. Gunakan username dan password yang telah diberikan untuk koneksi internet\n"
                    . "2. Pastikan perangkat Anda terhubung dengan benar\n"
                    . "3. Jika mengalami kendala, hubungi customer service kami\n\n"
                    . "========= LAYANAN KAMI =========\n"
                    . "• Internet cepat dan stabil\n"
                    . "• Customer service 24/7\n"
                    . "• Support teknis profesional\n\n"
                    . "Jika Anda memiliki pertanyaan atau membutuhkan bantuan, jangan ragu untuk menghubungi customer service kami.\n\n"
                    . "Terima kasih telah memilih layanan kami. Selamat menikmati internet yang cepat dan stabil!\n\n"
                    . "Hormat kami,\n"
                    . "Tim Customer Service",
            ],
        ];

        foreach ($templates as $template) {
            Setting::updateOrCreate(
                ['key' => $template['key']],
                ['value' => $template['value']]
            );
        }

        $this->command->info('✅ WhatsApp templates seeded!');
    }
}

