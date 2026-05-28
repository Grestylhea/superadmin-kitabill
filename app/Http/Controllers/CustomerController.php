<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\OLT;
use App\Models\User;
use App\Services\MikrotikService;
use App\Services\WhatsAppService;
use App\Events\CustomerStatusUpdated;
use App\Events\CustomerStatsUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RouterOS\Client;
use RouterOS\Query;


class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with(['package', 'router']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('customer_mikrotik_username', 'like', "%{$search}%"); // ✅ Tambahkan search untuk username
            });
        }

        // ✅ Filter berdasarkan status (active, suspended)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('connection_type')) {
            $query->where('connection_type', $request->connection_type);
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // ✅ Order by created_at DESC untuk menampilkan yang terbaru di atas
        // ✅ Pastikan tidak include soft deleted (default behavior sudah benar)
        $customers = $query->orderBy('created_at', 'desc')->paginate(15);
        
        \Log::info("Customer index: Found " . $customers->total() . " customers (excluding soft deleted)");
        
        $packages = Package::where('is_active', true)->get();

        return view('customers.index', compact('customers', 'packages'));
    }

    public function create()
    {
        $packages = Package::where('is_active', true)->get();
        $routers = Router::where('is_active', true)->get();
        $olts = OLT::where('is_active', true)->get();
        
        try {
            $teknisis = User::role('teknisi')->get();
        } catch (\Exception $e) {
            $teknisis = User::all();
        }

        return view('customers.create', compact('packages', 'routers', 'olts', 'teknisis'));
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => ['nullable', 'email', \Illuminate\Validation\Rule::unique('customers', 'email')->whereNull('deleted_at')],
                'phone' => 'required|string|max:20',
                'address' => 'required|string',
                'package_id' => 'required|exists:packages,id',
                'router_id' => 'nullable|exists:routers,id',
                'connection_type' => 'required|in:pppoe_direct,pppoe_mikrotik,static_ip,hotspot,dhcp',
                'customer_mikrotik_username' => ['nullable', 'string', \Illuminate\Validation\Rule::unique('customers', 'customer_mikrotik_username')->whereNull('deleted_at')],
                'customer_mikrotik_password' => 'nullable|string',
                'installation_date' => 'required|date',
                'olt_id' => 'nullable|exists:olts,id',
                'assigned_teknisi_id' => 'nullable|exists:users,id',
                'ont_serial_number' => 'nullable|string',
                'pon_port' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'notes' => 'nullable|string',
                'id_card_number' => 'nullable|string',
                'customer_mikrotik_ip' => 'nullable|string',
                // Static IP fields
                'static_ip' => 'nullable|ip',
                'static_subnet' => 'nullable|ip',
                'static_gateway' => 'nullable|ip',
                // DHCP fields
                'mac_address' => 'nullable|string',
                'dhcp_ip' => 'nullable|ip',
                // Custom Isolir Date
                'custom_isolir_date_only' => 'nullable|date',
                'custom_isolir_time' => 'nullable|date_format:H:i',
            ];
            
            // ✅ Validasi khusus untuk PPPoE: username dan password WAJIB
            $connectionType = $request->input('connection_type');
            
            // ✅ Jika field utama kosong, coba ambil dari backup
            if (empty($request->input('customer_mikrotik_username')) && $request->has('pppoe_username_backup')) {
                $request->merge(['customer_mikrotik_username' => $request->input('pppoe_username_backup')]);
            }
            if (empty($request->input('customer_mikrotik_password')) && $request->has('pppoe_password_backup')) {
                $request->merge(['customer_mikrotik_password' => $request->input('pppoe_password_backup')]);
            }
            
            \Log::info("Validating customer creation", [
                'connection_type' => $connectionType,
                'has_username' => $request->has('customer_mikrotik_username'),
                'username_value' => $request->input('customer_mikrotik_username'),
                'has_password' => $request->has('customer_mikrotik_password'),
                'password_value' => $request->input('customer_mikrotik_password') ? '***' : 'EMPTY',
                'has_backup_username' => $request->has('pppoe_username_backup'),
                'backup_username_value' => $request->input('pppoe_username_backup'),
                'all_inputs' => array_keys($request->all())
            ]);
            
            if (in_array($connectionType, ['pppoe_direct', 'pppoe_mikrotik'])) {
                // ✅ Validasi lebih ketat: required, filled (tidak boleh kosong setelah trim)
                // ✅ HAPUS unique validation - akan handle manual di bawah
                $rules['customer_mikrotik_username'] = [
                    'required',
                    'string',
                    'filled', // Tidak boleh kosong setelah trim
                ];
                $rules['customer_mikrotik_password'] = ['required', 'string', 'filled']; // Tidak boleh kosong setelah trim
                
                \Log::info("PPPoE validation rules applied", [
                    'username_rule' => 'required|string|filled|unique',
                    'password_rule' => 'required|string|filled',
                    'username_value_before_validation' => $request->input('customer_mikrotik_username'),
                    'password_empty' => empty($request->input('customer_mikrotik_password')),
                    'username_empty' => empty($request->input('customer_mikrotik_username')),
                    'username_trimmed' => trim($request->input('customer_mikrotik_username', '')),
                    'password_trimmed_length' => strlen(trim($request->input('customer_mikrotik_password', '')))
                ]);
            }
            
            // ✅ Validasi khusus untuk Hotspot: password WAJIB (username bisa pakai customer_code)
            if ($connectionType === 'hotspot') {
                $rules['customer_mikrotik_password'] = 'required|string|min:1';
            }
            
            try {
                $validated = $request->validate($rules);
            } catch (\Illuminate\Validation\ValidationException $e) {
                \Log::error("Validation failed", [
                    'errors' => $e->errors(),
                    'connection_type' => $connectionType,
                    'has_username' => $request->has('customer_mikrotik_username'),
                    'username_value' => $request->input('customer_mikrotik_username'),
                    'username_empty' => empty($request->input('customer_mikrotik_username')),
                    'username_trimmed' => trim($request->input('customer_mikrotik_username', '')),
                    'has_password' => $request->has('customer_mikrotik_password'),
                    'password_empty' => empty($request->input('customer_mikrotik_password')),
                    'password_trimmed_length' => strlen(trim($request->input('customer_mikrotik_password', ''))),
                    'has_backup_username' => $request->has('pppoe_username_backup'),
                    'backup_username_value' => $request->input('pppoe_username_backup'),
                    'has_backup_password' => $request->has('pppoe_password_backup'),
                    'backup_password_empty' => empty($request->input('pppoe_password_backup')),
                    'all_request_keys' => array_keys($request->all()),
                    'request_all_except_password' => $request->except(['customer_mikrotik_password', 'pppoe_password_backup'])
                ]);
                throw $e;
            }

            // ✅ LOGIKA BARU: Handle username yang sudah ada
            $username = trim($validated['customer_mikrotik_username'] ?? '');
            $isUpdate = false;
            $existingCustomer = null;
            
            // ✅ Cek apakah username sudah ada di database (HANYA untuk PPPoE)
            if (in_array($connectionType, ['pppoe_direct', 'pppoe_mikrotik']) && $username) {
                $existingCustomer = \App\Models\Customer::where('customer_mikrotik_username', $username)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($existingCustomer) {
                    // ✅ 1A: Username ada di database → UPDATE customer yang sudah ada
                    $isUpdate = true;
                    \Log::info("Username exists in database, will update existing customer", [
                        'username' => $username,
                        'existing_customer_id' => $existingCustomer->id,
                        'existing_customer_code' => $existingCustomer->customer_code
                    ]);
                } else {
                    // Username tidak ada di database, cek di Mikrotik
                    $router = \App\Models\Router::find($validated['router_id'] ?? null);
                    if ($router) {
                        try {
                            $mikrotik = new \App\Services\MikrotikService($router);
                            
                            // Cek apakah username ada di Mikrotik
                            $query = new \RouterOS\Query('/ppp/secret/print');
                            $query->where('name', $username);
                            $secrets = $mikrotik->getClient()->query($query)->read();
                            
                            // Jika tidak ditemukan dengan where, coba tanpa where dan filter manual
                            if (empty($secrets)) {
                                $query = new \RouterOS\Query('/ppp/secret/print');
                                $allSecrets = $mikrotik->getClient()->query($query)->read();
                                $secrets = array_filter($allSecrets, function($secret) use ($username) {
                                    return ($secret['name'] ?? '') === $username;
                                });
                                $secrets = array_values($secrets);
                            }
                            
                            if (!empty($secrets)) {
                                // ✅ 2A: Username tidak ada di database tapi ada di Mikrotik
                                // → Create customer baru di database dan update secret di Mikrotik
                                \Log::info("Username exists in Mikrotik but not in database, will create new customer and update Mikrotik secret", [
                                    'username' => $username,
                                    'mikrotik_secret_profile' => $secrets[0]['profile'] ?? 'N/A'
                                ]);
                                // Secret akan di-update saat provision ke Mikrotik (di bawah)
                            } else {
                                // ✅ 3: Username tidak ada di database dan tidak ada di Mikrotik
                                // → Create customer baru (normal flow)
                                \Log::info("Username does not exist in database or Mikrotik, will create new customer", [
                                    'username' => $username
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Log::warning("Could not check Mikrotik for username existence, proceeding with normal create", [
                                'username' => $username,
                                'error' => $e->getMessage()
                            ]);
                            // Lanjutkan dengan normal create jika tidak bisa cek Mikrotik
                        }
                    }
                }
            }

            // ✅ Generate Customer Code HANYA jika create baru (bukan update)
            // Jika update, skip generate customer_code
            if (!$isUpdate) {
                // Generate Customer Code - Cari yang terbesar untuk menghindari duplicate
                // ✅ Gunakan try-catch untuk handle error jika ada customer_code yang tidak valid
                try {
                // Hanya ambil customer_code yang mengikuti format MTK-XXXXXX (dimana X adalah angka)
                $lastCustomer = Customer::whereRaw("customer_code ~ '^MTK-[0-9]+$'") // PostgreSQL regex: MTK- diikuti hanya angka
                    ->orderByRaw('CAST(SUBSTRING(customer_code FROM 5) AS INTEGER) DESC')
                    ->first();
                
                if ($lastCustomer) {
                    // Extract number dari customer_code (misal: MTK-000001 -> 1)
                    $lastNumber = (int) substr($lastCustomer->customer_code, 4);
                    $nextNumber = $lastNumber + 1;
                } else {
                    $nextNumber = 1;
                }
            } catch (\Exception $e) {
                // Jika error (misal ada customer_code yang tidak valid), gunakan cara alternatif
                \Log::warning("Error getting last customer_code, using alternative method: " . $e->getMessage());
                $lastCustomer = Customer::where('customer_code', 'like', 'MTK-%')
                    ->get()
                    ->map(function($customer) {
                        // Extract number dengan cara aman
                        $code = $customer->customer_code;
                        if (preg_match('/^MTK-(\d+)$/', $code, $matches)) {
                            return (int) $matches[1];
                        }
                        return 0;
                    })
                    ->filter(function($num) {
                        return $num > 0;
                    })
                    ->max();
                
                $nextNumber = $lastCustomer ? $lastCustomer + 1 : 1;
            }
            
            // ✅ Pastikan tidak duplicate dengan loop sampai dapat yang unik
            // ✅ Cek termasuk soft deleted untuk menghindari conflict
            do {
                $validated['customer_code'] = 'MTK-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
                $exists = Customer::withTrashed()->where('customer_code', $validated['customer_code'])->exists();
                if ($exists) {
                    $nextNumber++;
                    \Log::info("Customer code {$validated['customer_code']} sudah ada, increment ke " . $nextNumber);
                }
            } while ($exists);
            }
            
            // ✅ Set status dan is_online HANYA jika create baru
            if (!$isUpdate) {
                $validated['status'] = 'active';
                $validated['is_online'] = false;
            }

            // 📅 Calculate next_billing_date based on package settings
            $package = \App\Models\Package::find($validated['package_id']);
            $installationDate = \Carbon\Carbon::parse($validated['installation_date']);
            
            if ($package && $package->custom_expire_day) {
                // Calculate next billing based on custom_expire_day
                $nextBilling = $installationDate->copy();
                
                // If current day is past the custom_expire_day, move to next month
                $expireDay = (int) $package->custom_expire_day; // Pastikan integer
                if ($installationDate->day > $expireDay) {
                    $nextBilling->addMonth();
                }
                
                $nextBilling->day($expireDay);
                
                // Set time if custom_expire_time is defined
                if ($package->custom_expire_time) {
                    $time = \Carbon\Carbon::parse($package->custom_expire_time);
                    $nextBilling->setTime($time->hour, $time->minute);
                }
                
                $validated['next_billing_date'] = $nextBilling;
                
                // ✅ SISTEM BARU: Auto set custom_isolir_date = next_billing_date
                // Isolir otomatis terisi sesuai paket yang dipakai
                // TAPI: Jika user manual input custom_isolir_date (override), gunakan input user
                if ($request->filled('custom_isolir_date_only')) {
                    // User manual input isolir date (override)
                    $date = $validated['custom_isolir_date_only'];
                    $time = $validated['custom_isolir_time'] ?? ($package->custom_expire_time ? \Carbon\Carbon::parse($package->custom_expire_time)->format('H:i') : '23:59');
                    $validated['custom_isolir_date'] = \Carbon\Carbon::parse("$date $time");
                    $validated['custom_isolir_executed'] = false;
                    
                    Log::info("Custom isolir date manually set (override) during create", [
                        'customer_code' => $validated['customer_code'] ?? 'N/A',
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'custom_isolir_date' => $validated['custom_isolir_date']->format('Y-m-d H:i'),
                        'next_billing_date' => $validated['next_billing_date']->format('Y-m-d H:i'),
                        'note' => 'Manual override from user input'
                    ]);
                } else {
                    // Auto set dari paket
                    $validated['custom_isolir_date'] = $nextBilling->copy();
                    $validated['custom_isolir_executed'] = false;
                    
                    Log::info("Custom isolir date auto-set from package", [
                        'customer_code' => $validated['customer_code'] ?? 'N/A',
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'custom_expire_day' => $package->custom_expire_day,
                        'custom_expire_time' => $package->custom_expire_time,
                        'custom_isolir_date' => $validated['custom_isolir_date']->format('Y-m-d H:i'),
                        'next_billing_date' => $validated['next_billing_date']->format('Y-m-d H:i')
                    ]);
                }
            } else {
                // Default: 30 days from installation
                $validated['next_billing_date'] = $installationDate->copy()->addDays(30);
                
                // ✅ Jika user manual input custom_isolir_date, gunakan input user
                if ($request->filled('custom_isolir_date_only')) {
                    $date = $validated['custom_isolir_date_only'];
                    $time = $validated['custom_isolir_time'] ?? '23:59';
                    $validated['custom_isolir_date'] = \Carbon\Carbon::parse("$date $time");
                    $validated['custom_isolir_executed'] = false;
                    
                    Log::info("Custom isolir date manually set (override) during create - no custom_expire_day", [
                        'customer_code' => $validated['customer_code'] ?? 'N/A',
                        'custom_isolir_date' => $validated['custom_isolir_date']->format('Y-m-d H:i'),
                        'note' => 'Manual input - package has no custom_expire_day'
                    ]);
                } else {
                    // ✅ Jika paket tidak punya custom_expire_day dan user tidak input manual, custom_isolir_date tetap null
                    $validated['custom_isolir_date'] = null;
                    $validated['custom_isolir_executed'] = false;
                }
            }
            
            // ✅ Hapus field ekstra yang bukan kolom DB
            unset($validated['custom_isolir_date_only'], $validated['custom_isolir_time']);

            // ✅ Jika router_id tidak diisi tapi package punya router, gunakan router dari package
            if (empty($validated['router_id'])) {
                $package = \App\Models\Package::with('routers')->find($validated['package_id']);
                if ($package && $package->routers->count() > 0) {
                    $router = $package->routers->first();
                    $validated['router_id'] = $router->id;
                    
                    // ✅ Auto-set connection_type sesuai dengan package jika belum diisi atau tidak sesuai
                    $packageConnectionType = $router->pivot->connection_type;
                    if ($packageConnectionType === 'pppoe' && !in_array($validated['connection_type'], ['pppoe_direct', 'pppoe_mikrotik'])) {
                        $validated['connection_type'] = 'pppoe_direct';
                    } else if ($packageConnectionType === 'hotspot' && $validated['connection_type'] !== 'hotspot') {
                        $validated['connection_type'] = 'hotspot';
                    }
                }
            }

            // 🔧 Prepare connection_config based on connection type
            $connectionConfig = [];
            $connectionType = $validated['connection_type'];

            if ($connectionType === 'static_ip') {
                $connectionConfig = [
                    'static_ip' => $request->input('static_ip'),
                    'static_subnet' => $request->input('static_subnet'),
                    'static_gateway' => $request->input('static_gateway'),
                ];
            } elseif ($connectionType === 'dhcp') {
                $connectionConfig = [
                    'mac_address' => $request->input('mac_address'),
                    'dhcp_ip' => $request->input('dhcp_ip'),
                ];
            }

            if (!empty($connectionConfig)) {
                $validated['connection_config'] = $connectionConfig;
            }

            // ✅ CREATE atau UPDATE customer berdasarkan logika di atas
            if ($isUpdate && $existingCustomer) {
                // UPDATE customer yang sudah ada
                // ✅ Jangan update customer_code jika sudah ada
                unset($validated['customer_code']);
                // ✅ Jika customer status "terminated", ubah ke "active" saat update (migrasi dari status lama)
                if ($existingCustomer->status === 'terminated') {
                    $validated['status'] = 'active';
                    \Log::info("Customer status migrated from terminated to active", [
                        'customer_code' => $existingCustomer->customer_code,
                        'username' => $username
                    ]);
                }
                $existingCustomer->update($validated);
                $customer = $existingCustomer->fresh();
                
                Log::info("Customer updated successfully (username already existed in database)", [
                    'customer_code' => $customer->customer_code,
                    'name' => $customer->name,
                    'username' => $username,
                    'old_status' => $existingCustomer->getOriginal('status') ?? 'N/A',
                    'new_status' => $customer->status,
                    'updated_fields' => array_keys($validated)
                ]);
            } else {
                // CREATE customer baru
                $customer = Customer::create($validated);
                
                Log::info("Customer created successfully", [
                    'customer_code' => $customer->customer_code,
                    'name' => $customer->name,
                    'username' => $username
                ]);
            }

            // 🌐 Auto-provision ke Mikrotik berdasarkan tipe koneksi
            try {
                if ($customer->router_id) {
                    $this->provisionToMikrotik($customer, $validated);
                }
            } catch (\Exception $e) {
                Log::error("Failed to provision to Mikrotik", [
                    'customer' => $customer->customer_code,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'validated' => [
                        'username' => $validated['customer_mikrotik_username'] ?? 'N/A',
                        'password' => isset($validated['customer_mikrotik_password']) ? (strlen($validated['customer_mikrotik_password']) > 0 ? '***' : 'EMPTY') : 'N/A',
                        'connection_type' => $validated['connection_type'] ?? 'N/A'
                    ]
                ]);
                // ✅ Re-throw error agar user tahu ada masalah dan customer tidak tersimpan
                throw new \Exception("Gagal memprovisi customer ke Mikrotik: " . $e->getMessage());
            }

            // 📱 Send WhatsApp Welcome Message (HANYA untuk customer baru, bukan update)
            if (!$isUpdate) {
                try {
                    // ✅ Pastikan customer memiliki nomor telepon
                    if (empty($customer->phone)) {
                        Log::warning("Cannot send welcome WhatsApp: customer phone is empty", [
                            'customer' => $customer->customer_code,
                            'customer_id' => $customer->id
                        ]);
                    } else {
                        $whatsapp = app(WhatsAppService::class);
                        
                        // ✅ Kirim welcome message (sendMessage() akan handle gateway check sendiri)
                        if ($whatsapp->sendWelcomeMessage($customer)) {
                            Log::info("✅ Welcome WhatsApp sent successfully to new customer", [
                                'customer' => $customer->customer_code,
                                'customer_name' => $customer->name,
                                'phone' => $customer->phone,
                                'formatted_phone' => $this->formatPhoneForLog($customer->phone)
                            ]);
                        } else {
                            Log::warning("⚠️ Welcome WhatsApp send returned false", [
                                'customer' => $customer->customer_code,
                                'phone' => $customer->phone
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Failed to send welcome WhatsApp", [
                        'customer' => $customer->customer_code,
                        'phone' => $customer->phone ?? 'N/A',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                Log::info("Welcome WhatsApp skipped (customer update, not new customer)", [
                    'customer' => $customer->customer_code
                ]);
            }

            $successMessage = $isUpdate 
                ? "Customer berhasil diupdate dan sudah terprovisi ke Mikrotik!"
                : "Customer berhasil ditambahkan dan sudah terprovisi ke Mikrotik! WhatsApp welcome telah dikirim.";
            
            return redirect()->route('customers.index')
                ->with('success', $successMessage);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("Customer validation failed", ['errors' => $e->errors()]);
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Validasi gagal. Periksa kembali data yang diisi.');

        } catch (\Exception $e) {
            Log::error("Failed to create customer", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan customer: ' . $e->getMessage());
        }
    }

    public function show(Customer $customer)
    {
        $customer->load(['package', 'router', 'olt', 'invoices', 'tickets']);
        return view('customers.show', compact('customer'));
    }

    /**
     * Sync billing date customer sesuai dengan custom_expire_day paket
     */
    public function syncBillingDate(Customer $customer)
    {
        try {
            $customer->load('package');
            
            if (!$customer->package) {
                return back()->with('error', 'Customer tidak memiliki paket!');
            }
            
            $package = $customer->package;
            
            if (!$package->custom_expire_day) {
                return back()->with('warning', 'Paket ini tidak memiliki custom_expire_day. Billing date tidak bisa di-sync.');
            }
            
            // ✅ LOGIKA BARU: Gunakan tanggal HARI INI sebagai acuan
            $today = now();
            $expireDay = (int) $package->custom_expire_day; // Pastikan integer
            $nextBilling = $today->copy();
            
            // Jika tanggal expire sudah lewat di bulan ini → set ke bulan depan
            if ($today->day > $expireDay) {
                // Tanggal expire sudah lewat → set ke bulan depan
                $nextBilling->addMonth();
            }
            // Jika belum lewat, tetap di bulan ini
            
            // Set ke custom_expire_day (pastikan integer)
            $nextBilling->day($expireDay);
            
            // Set waktu dari paket atau default 23:59
            if ($package->custom_expire_time) {
                $time = \Carbon\Carbon::parse($package->custom_expire_time);
                $nextBilling->setTime($time->hour, $time->minute);
            } else {
                $nextBilling->setTime(23, 59);
            }
            
            $oldDate = $customer->next_billing_date ? $customer->next_billing_date->format('d M Y H:i') : 'Belum di-set';
            
            $customer->next_billing_date = $nextBilling;
            
            // ✅ SISTEM BARU: Auto update custom_isolir_date = next_billing_date
            $oldIsolirDate = $customer->custom_isolir_date ? $customer->custom_isolir_date->format('d M Y H:i') : 'Belum di-set';
            $customer->custom_isolir_date = $nextBilling->copy();
            $customer->custom_isolir_executed = false;
            
            $customer->save();
            
            Log::info("Billing date and custom isolir date synced manually", [
                'customer' => $customer->customer_code,
                'package' => $package->name,
                'today' => $today->format('d M Y'),
                'old_billing_date' => $oldDate,
                'new_billing_date' => $nextBilling->format('d M Y H:i'),
                'old_isolir_date' => $oldIsolirDate,
                'new_isolir_date' => $customer->custom_isolir_date->format('d M Y H:i')
            ]);
            
            return back()->with('success', "✅ Billing date dan isolir date berhasil di-sync! Dari {$oldDate} menjadi {$nextBilling->format('d M Y H:i')} (sesuai paket tanggal {$package->custom_expire_day}). Isolir date juga di-update ke {$customer->custom_isolir_date->format('d M Y H:i')}");
            
        } catch (\Exception $e) {
            Log::error("Failed to sync billing date", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);
            
            return back()->with('error', '❌ Gagal sync billing date: ' . $e->getMessage());
        }
    }

    public function edit(Customer $customer)
    {
        $packages = Package::where('is_active', true)->get();
        $routers = Router::where('is_active', true)->get();
        $olts = OLT::where('is_active', true)->get();
        
        try {
            $teknisis = User::role('teknisi')->get();
        } catch (\Exception $e) {
            $teknisis = User::all();
        }

        return view('customers.edit', compact('customer', 'packages', 'routers', 'olts', 'teknisis'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            // --- data umum customer ---
            'name'     => 'required|string|max:255',
            'email'    => ['nullable', 'email', \Illuminate\Validation\Rule::unique('customers', 'email')->ignore($customer->id)->whereNull('deleted_at')],
            'phone'    => 'nullable|string|max:50',
            'address'  => 'nullable|string',

            // --- konfigurasi koneksi utama ---
            'connection_type' => 'required|string',         // pppoe_direct / pppoe_mikrotik / static_ip / hotspot / dhcp
            'package_id'      => 'required|exists:packages,id',
            'router_id'       => 'nullable|exists:routers,id',

            // --- field PPPoE (hanya dipakai untuk connection_type PPPoE) ---
            'pppoe_username'  => 'nullable|string|max:191',
            'pppoe_password'  => 'nullable|string|max:191',

            // --- contoh field static IP (kalau ada inputnya) ---
            'static_ip_address' => 'nullable|ip',
            'static_ip_gateway' => 'nullable|ip',
            'static_ip_dns'     => 'nullable|string',
            
            // --- Custom Isolir Date ---
            'custom_isolir_date_only' => 'nullable|date',
            'custom_isolir_time'      => 'nullable|date_format:H:i',
        ]);

        // ==========================
        // Bangun connection_config
        // ==========================

        // Ambil config lama dulu (kalau ada) supaya tidak hilang
        $config = $customer->connection_config ?? [];

        switch ($validated['connection_type']) {
            case 'pppoe_direct':
            case 'pppoe_mikrotik':
                $config['username'] = $validated['pppoe_username'] ?? ($config['username'] ?? null);
                $config['password'] = $validated['pppoe_password'] ?? ($config['password'] ?? null);

                // kalau mau, bisa hapus setting static/dhcp lama
                unset($config['ip'], $config['gateway'], $config['dns']);
                break;

            case 'static_ip':
                // contoh kalau kamu pakai config static:
                $config['ip']      = $validated['static_ip_address'] ?? ($config['ip'] ?? null);
                $config['gateway'] = $validated['static_ip_gateway'] ?? ($config['gateway'] ?? null);
                $config['dns']     = $validated['static_ip_dns'] ?? ($config['dns'] ?? null);

                // hapus username/password PPPoE kalau ga dipakai
                unset($config['username'], $config['password']);
                break;

            default:
                // untuk hotspot/dhcp, bisa kosongkan PPPoE & static
                unset(
                    $config['username'], $config['password'],
                    $config['ip'], $config['gateway'], $config['dns']
                );
                break;
        }

        // Masukkan kembali ke validated
        $validated['connection_config'] = $config;

        // ==========================
        // Handle Custom Isolir Date
        // ==========================
        // ✅ SISTEM BARU: Custom isolir date otomatis dari paket
        // Jika paket punya custom_expire_day, custom_isolir_date = next_billing_date
        // Jika paket tidak punya custom_expire_day, custom_isolir_date = null
        
        // Cek apakah package berubah atau perlu update isolir date
        $newPackage = \App\Models\Package::find($validated['package_id']);
        $packageChanged = isset($validated['package_id']) && $validated['package_id'] != $customer->package_id;
        
        // Jika package berubah atau paket punya custom_expire_day, update custom_isolir_date
        if ($newPackage && $newPackage->custom_expire_day) {
            // Paket punya custom_expire_day - custom_isolir_date akan di-set otomatis dari next_billing_date
            // (akan di-set di bagian Update Billing Date di bawah)
            // Untuk sementara, set null dulu, nanti akan di-update
            $validated['custom_isolir_date'] = null;
            $validated['custom_isolir_executed'] = false;
        } else {
            // Paket tidak punya custom_expire_day - custom_isolir_date = null
            $validated['custom_isolir_date'] = null;
            $validated['custom_isolir_executed'] = false;
        }
        
        // ✅ Jika user manual input custom_isolir_date (override), gunakan input user
        // Ini untuk kasus khusus seperti trial period atau kontrak khusus
        if ($request->filled('custom_isolir_date_only')) {
            $date = $validated['custom_isolir_date_only'];
            $time = $validated['custom_isolir_time'] ?? '23:59';
            $validated['custom_isolir_date'] = \Carbon\Carbon::parse("$date $time");
            $validated['custom_isolir_executed'] = false;
            
            \Log::info("Custom isolir date manually set (override)", [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'custom_isolir_date' => $validated['custom_isolir_date']->format('Y-m-d H:i'),
                'note' => 'Manual override from user input'
            ]);
        }

        // ==========================
        // Update Billing Date jika Package berubah
        // ==========================
        if (isset($validated['package_id']) && $validated['package_id'] != $customer->package_id) {
            $newPackage = \App\Models\Package::find($validated['package_id']);
            
            if ($newPackage && $newPackage->custom_expire_day) {
                // Hitung ulang next_billing_date berdasarkan paket baru
                $nextBilling = now();
                
                // Jika hari sekarang lewat dari custom_expire_day, pindah ke bulan berikutnya
                $expireDay = (int) $newPackage->custom_expire_day; // Pastikan integer
                if ($nextBilling->day > $expireDay) {
                    $nextBilling->addMonth();
                }
                
                $nextBilling->day($expireDay);
                
                // Set waktu jika ada custom_expire_time
                if ($newPackage->custom_expire_time) {
                    $time = \Carbon\Carbon::parse($newPackage->custom_expire_time);
                    $nextBilling->setTime($time->hour, $time->minute);
                } else {
                    $nextBilling->setTime(23, 59);
                }
                
                $validated['next_billing_date'] = $nextBilling;
                
                // ✅ SISTEM BARU: Auto update custom_isolir_date = next_billing_date
                // Hanya update jika user tidak manual input (tidak ada override)
                if (!$request->filled('custom_isolir_date_only')) {
                    $validated['custom_isolir_date'] = $nextBilling->copy();
                    $validated['custom_isolir_executed'] = false;
                    
                    \Log::info("Custom isolir date auto-updated from package change", [
                        'customer' => $customer->customer_code,
                        'old_package' => $customer->package_id,
                        'new_package' => $validated['package_id'],
                        'custom_isolir_date' => $validated['custom_isolir_date']->format('Y-m-d H:i'),
                        'next_billing_date' => $nextBilling->format('Y-m-d H:i')
                    ]);
                }
                
                \Log::info("Next billing date updated due to package change", [
                    'customer' => $customer->customer_code,
                    'old_package' => $customer->package_id,
                    'new_package' => $validated['package_id'],
                    'new_billing_date' => $nextBilling->format('Y-m-d H:i')
                ]);
            } else {
                // Paket baru tidak punya custom_expire_day - reset custom_isolir_date
                if (!$request->filled('custom_isolir_date_only')) {
                    $validated['custom_isolir_date'] = null;
                    $validated['custom_isolir_executed'] = false;
                }
            }
        } else {
            // Package tidak berubah - cek apakah perlu update custom_isolir_date dari next_billing_date
            // (jika paket punya custom_expire_day dan custom_isolir_date belum di-set atau sudah di-execute)
            $currentPackage = $customer->package;
            if ($currentPackage && $currentPackage->custom_expire_day && $customer->next_billing_date) {
                // Jika custom_isolir_date belum di-set atau sudah di-execute, update dari next_billing_date
                if (!$customer->custom_isolir_date || $customer->custom_isolir_executed) {
                    // Hanya update jika user tidak manual input
                    if (!$request->filled('custom_isolir_date_only')) {
                        $validated['custom_isolir_date'] = $customer->next_billing_date->copy();
                        $validated['custom_isolir_executed'] = false;
                        
                        \Log::info("Custom isolir date auto-updated from next_billing_date", [
                            'customer' => $customer->customer_code,
                            'custom_isolir_date' => $validated['custom_isolir_date']->format('Y-m-d H:i'),
                            'next_billing_date' => $customer->next_billing_date->format('Y-m-d H:i')
                        ]);
                    }
                }
            }
        }

        // Field ekstra yang bukan kolom DB harus dihapus dari validated
        unset(
            $validated['pppoe_username'],
            $validated['pppoe_password'],
            $validated['static_ip_address'],
            $validated['static_ip_gateway'],
            $validated['static_ip_dns'],
            $validated['custom_isolir_date_only'],
            $validated['custom_isolir_time'],
        );

        // ==========================
        // ✅ Cek perubahan package SEBELUM update customer
        // ==========================
        $oldPackageId = $customer->package_id;
        $packageChanged = isset($validated['package_id']) && $validated['package_id'] != $oldPackageId;
        
        // ✅ Simpan original custom_isolir_date untuk perbandingan
        $oldCustomIsolirDate = $customer->custom_isolir_date;

        // ==========================
        // Simpan customer
        // ==========================
        // ✅ Log sebelum update untuk tracking
        \Log::info("Updating customer", [
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'has_custom_isolir_date' => isset($validated['custom_isolir_date']) && $validated['custom_isolir_date'] !== null,
            'custom_isolir_date' => isset($validated['custom_isolir_date']) && $validated['custom_isolir_date'] ? $validated['custom_isolir_date']->format('Y-m-d H:i') : 'NULL',
            'status' => $customer->status,
            'fields_to_update' => array_keys($validated)
        ]);
        
        $customer->update($validated);
        
        // ✅ Log setelah update untuk tracking
        \Log::info("Customer updated successfully", [
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->name,
            'custom_isolir_date' => $customer->custom_isolir_date ? $customer->custom_isolir_date->format('Y-m-d H:i') : 'NULL',
            'status' => $customer->status,
            'deleted_at' => $customer->deleted_at ? $customer->deleted_at->format('Y-m-d H:i:s') : 'NULL'
        ]);

        // =========================
        // ✅ Sync ke Mikrotik: Update PPPoE Secret (user), bukan profile
        // ✅ PENTING: JANGAN sync ke Mikrotik jika hanya update custom isolir date
        // =========================
        $router = Router::find($customer->router_id) ?? Router::first();

        // ✅ Cek apakah hanya custom isolir date yang berubah
        // ✅ PENTING: Jika hanya custom isolir date yang berubah, JANGAN sync ke Mikrotik
        // ✅ Custom isolir date hanya untuk scheduling, tidak perlu sync ke Mikrotik
        $onlyCustomIsolirChanged = false;
        
        // ✅ Cek apakah custom isolir date berubah
        $customIsolirDateChanged = false;
        if (isset($validated['custom_isolir_date'])) {
            $newIsolirDate = $validated['custom_isolir_date'];
            $oldDateStr = $oldCustomIsolirDate ? $oldCustomIsolirDate->format('Y-m-d H:i') : null;
            $newDateStr = $newIsolirDate ? $newIsolirDate->format('Y-m-d H:i') : null;
            $customIsolirDateChanged = ($oldDateStr != $newDateStr);
        } else {
            // Jika custom_isolir_date dihapus (null), juga dianggap berubah
            $customIsolirDateChanged = ($oldCustomIsolirDate !== null);
        }
        
        // ✅ Cek field lain yang mungkin berubah (selain custom isolir date)
        $otherFieldsChanged = false;
        
        // Cek field-field penting yang memerlukan sync ke Mikrotik
        $fieldsToCheck = [
            'package_id',
            'connection_type',
            'customer_mikrotik_username',
            'customer_mikrotik_password',
            'name',
            'phone',
            'email',
            'status',
            'router_id'
        ];
        
        foreach ($fieldsToCheck as $field) {
            if (isset($validated[$field])) {
                $oldValue = $customer->$field;
                $newValue = $validated[$field];
                
                // Bandingkan nilai (handle Carbon dates)
                if ($oldValue instanceof \Carbon\Carbon && $newValue instanceof \Carbon\Carbon) {
                    if (!$oldValue->eq($newValue)) {
                        $otherFieldsChanged = true;
                        break;
                    }
                } elseif ($oldValue != $newValue) {
                    $otherFieldsChanged = true;
                    break;
                }
            }
        }
        
        // ✅ Jika hanya custom isolir date yang berubah, skip sync ke Mikrotik
        $onlyCustomIsolirChanged = $customIsolirDateChanged && !$otherFieldsChanged && !$packageChanged;
        
        if ($onlyCustomIsolirChanged) {
            Log::info("✅ Only custom isolir date changed, skipping Mikrotik sync to prevent user deletion", [
                'customer' => $customer->customer_code,
                'customer_id' => $customer->id,
                'old_date' => $oldCustomIsolirDate ? $oldCustomIsolirDate->format('Y-m-d H:i') : 'NULL',
                'new_date' => isset($validated['custom_isolir_date']) && $validated['custom_isolir_date'] ? $validated['custom_isolir_date']->format('Y-m-d H:i') : 'NULL',
                'package_changed' => $packageChanged,
                'other_fields_changed' => $otherFieldsChanged,
                'note' => 'Custom isolir date is only for scheduling, no need to sync to Mikrotik'
            ]);
        } else if ($customIsolirDateChanged) {
            Log::info("Custom isolir date changed, but other fields also changed - will sync to Mikrotik", [
                'customer' => $customer->customer_code,
                'customer_id' => $customer->id,
                'old_date' => $oldCustomIsolirDate ? $oldCustomIsolirDate->format('Y-m-d H:i') : 'NULL',
                'new_date' => isset($validated['custom_isolir_date']) && $validated['custom_isolir_date'] ? $validated['custom_isolir_date']->format('Y-m-d H:i') : 'NULL',
                'package_changed' => $packageChanged,
                'other_fields_changed' => $otherFieldsChanged
            ]);
        }

        if ($router && $customer->package && !$onlyCustomIsolirChanged) {
            try {
                $service = new MikrotikService($router);
                
                // ✅ Pastikan profile sesuai dengan package di router
                if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik'])) {
                    // ✅ Hanya sync/create profile jika package berubah (untuk memastikan profile ada)
                    if ($packageChanged) {
                        // 1. Pastikan PPP profile paket ada / ter-update
                        $service->syncPackageProfile($customer->package);
                        Log::info("PPPoE profile synced to Mikrotik (package changed)", [
                            'customer' => $customer->customer_code,
                            'old_package' => $oldPackageId,
                            'new_package' => $customer->package_id,
                            'profile' => $customer->package->name
                        ]);
                    }
                    
                    // 2. ✅ SELALU sync PPP secret (username, password, profile) - ini yang penting!
                    // Ini akan update user jika sudah ada, atau create jika belum ada
                    $service->syncCustomerPppoe($customer);
                    
                    Log::info("✅ PPPoE secret (user) synced to Mikrotik", [
                        'customer' => $customer->customer_code,
                        'username' => $customer->customer_mikrotik_username ?? 'N/A',
                        'profile' => $customer->package->name,
                        'package_changed' => $packageChanged,
                        'only_custom_isolir_changed' => $onlyCustomIsolirChanged
                    ]);
                } elseif ($customer->connection_type === 'hotspot') {
                    // ✅ Hanya sync hotspot profile jika package berubah
                    $profile = $customer->package->name;
                    
                    if ($packageChanged) {
                        $service->createHotspotProfile($profile, $customer->package->download_speed, $customer->package->upload_speed);
                        Log::info("Hotspot profile synced to Mikrotik (package changed)", [
                            'customer' => $customer->customer_code,
                            'old_package' => $oldPackageId,
                            'new_package' => $customer->package_id,
                            'profile' => $profile
                        ]);
                    }
                    
                    // Update hotspot user dengan profile yang benar
                    $username = $customer->customer_mikrotik_username ?? $customer->customer_code;
                    $password = $customer->customer_mikrotik_password ?? '';
                    
                    if ($username) {
                        // Cek apakah user sudah ada, jika ya update, jika tidak create
                        try {
                            $service->updateHotspotUser($username, $profile, [
                                'password' => $password
                            ]);
                        } catch (\Exception $e) {
                            // Jika update gagal (user tidak ada), coba create
                            if ($password) {
                                $service->createHotspotUser($username, $password, $profile);
                            }
                        }
                    }
                    
                    Log::info("Hotspot customer synced to Mikrotik", [
                        'customer' => $customer->customer_code,
                        'profile' => $profile,
                        'package_changed' => $packageChanged
                    ]);
                }

            } catch (\Exception $e) {
                \Log::error('Failed to sync customer to Mikrotik: '.$e->getMessage(), [
                    'customer' => $customer->customer_code,
                    'connection_type' => $customer->connection_type,
                    'package' => $customer->package->name ?? 'N/A'
                ]);
                // ✅ Jangan gagalkan update customer, cukup dicatat di log
                // Tapi user akan melihat warning di response
            }
        }

        return redirect()
            ->route('customers.edit', $customer->id)
            ->with('success', 'Customer berhasil diperbarui!');

    }


    public function destroy(Customer $customer)
    {
        try {
            $customerCode = $customer->customer_code;
            $customerName = $customer->name;
            
            // ✅ Hapus dari Mikrotik TERLEBIH DAHULU sebelum hapus dari database
            $mikrotikDeleted = false;
            $mikrotikError = null;
            
            // ✅ Reload customer untuk memastikan data terbaru
            $customer->refresh();
            $customer->load('router'); // Load relationship
            
            Log::info("Starting customer deletion process", [
                'customer' => $customerCode,
                'customer_id' => $customer->id,
                'has_router' => $customer->router ? 'yes' : 'no',
                'router_id' => $customer->router_id,
                'router_name' => $customer->router->name ?? 'N/A',
                'router_ip' => $customer->router->ip_address ?? 'N/A',
                'has_username' => $customer->customer_mikrotik_username ? 'yes' : 'no',
                'username' => $customer->customer_mikrotik_username,
                'connection_type' => $customer->connection_type,
                'connection_config' => $customer->connection_config
            ]);
            
            if ($customer->router && $customer->customer_mikrotik_username) {
                try {
                    $mikrotik = new MikrotikService($customer->router);
                    
                    Log::info("Attempting to delete user from Mikrotik", [
                        'customer' => $customerCode,
                        'username' => $customer->customer_mikrotik_username,
                        'connection_type' => $customer->connection_type,
                        'router' => $customer->router->name,
                        'router_ip' => $customer->router->ip_address
                    ]);
                    
                    // Hapus berdasarkan connection type
                    switch ($customer->connection_type) {
                        case 'pppoe_direct':
                        case 'pppoe_mikrotik':
                            try {
                                $result = $mikrotik->deletePPPoEUser($customer->customer_mikrotik_username);
                                if ($result) {
                                    $mikrotikDeleted = true;
                                    Log::info("✅ PPPoE user deleted from Mikrotik", [
                                        'customer' => $customerCode,
                                        'username' => $customer->customer_mikrotik_username,
                                        'router' => $customer->router->name
                                    ]);
                                } else {
                                    Log::warning("⚠️ PPPoE user deletion returned false", [
                                        'customer' => $customerCode,
                                        'username' => $customer->customer_mikrotik_username
                                    ]);
                                    throw new \Exception("Gagal menghapus PPPoE user dari Mikrotik");
                                }
                            } catch (\Exception $deleteEx) {
                                // Re-throw exception agar tidak di-catch oleh outer catch
                                throw $deleteEx;
                            }
                            break;
                            
                        case 'hotspot':
                            try {
                                $result = $mikrotik->deleteHotspotUser($customer->customer_mikrotik_username);
                                if ($result) {
                                    $mikrotikDeleted = true;
                                    Log::info("✅ Hotspot user deleted from Mikrotik", [
                                        'customer' => $customerCode,
                                        'username' => $customer->customer_mikrotik_username,
                                        'router' => $customer->router->name
                                    ]);
                                } else {
                                    Log::warning("⚠️ Hotspot user deletion returned false", [
                                        'customer' => $customerCode,
                                        'username' => $customer->customer_mikrotik_username
                                    ]);
                                    throw new \Exception("Gagal menghapus Hotspot user dari Mikrotik");
                                }
                            } catch (\Exception $deleteEx) {
                                // Re-throw exception agar tidak di-catch oleh outer catch
                                throw $deleteEx;
                            }
                            break;
                            
                        case 'static_ip':
                            // Delete static IP address jika ada
                            $config = is_string($customer->connection_config) 
                                ? json_decode($customer->connection_config, true) 
                                : ($customer->connection_config ?? []);
                            
                            if (isset($config['ip_address']) || isset($config['static_ip'])) {
                                try {
                                    $ipAddress = $config['ip_address'] ?? $config['static_ip'];
                                    // Gunakan RouterOS Client langsung
                                    $client = new Client([
                                        'host' => $customer->router->ip_address,
                                        'user' => $customer->router->username,
                                        'pass' => $customer->router->password,
                                        'port' => $customer->router->api_port ?? 8728,
                                    ]);
                                    
                                    $query = new Query('/ip/address/print');
                                    $query->where('address', $ipAddress);
                                    $addresses = $client->query($query)->read();
                                    
                                    if (!empty($addresses)) {
                                        $query = new Query('/ip/address/remove');
                                        $query->equal('.id', $addresses[0]['.id']);
                                        $client->query($query)->read();
                                        
                                        $mikrotikDeleted = true;
                                        Log::info("Static IP deleted from Mikrotik", [
                                            'customer' => $customerCode,
                                            'ip_address' => $ipAddress
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    $mikrotikError = "Failed to delete static IP: " . $e->getMessage();
                                    Log::warning($mikrotikError);
                                }
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $mikrotikError = "Failed to delete from Mikrotik: " . $e->getMessage();
                    Log::error($mikrotikError, [
                        'customer' => $customerCode,
                        'username' => $customer->customer_mikrotik_username,
                        'router' => $customer->router->name ?? 'N/A',
                        'connection_type' => $customer->connection_type,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // ✅ Jangan lanjutkan delete dari database jika gagal hapus dari Mikrotik
                    throw new \Exception("Gagal menghapus user dari Mikrotik: " . $e->getMessage() . ". Customer tidak dihapus dari database.");
                }
            } else {
                // ✅ Log jika tidak ada router atau username
                Log::warning("Cannot delete from Mikrotik - missing router or username", [
                    'customer' => $customerCode,
                    'has_router' => $customer->router ? 'yes' : 'no',
                    'has_username' => $customer->customer_mikrotik_username ? 'yes' : 'no',
                    'router_id' => $customer->router_id,
                    'username' => $customer->customer_mikrotik_username
                ]);
            }
            
            // ✅ Soft delete customer dari database HANYA jika berhasil hapus dari Mikrotik atau tidak perlu hapus dari Mikrotik
            if ($mikrotikDeleted || !$customer->router || !$customer->customer_mikrotik_username) {
                $customer->delete();
                Log::info("Customer soft deleted from database", [
                    'customer' => $customerCode,
                    'mikrotik_deleted' => $mikrotikDeleted,
                    'has_router' => $customer->router ? 'yes' : 'no',
                    'has_username' => $customer->customer_mikrotik_username ? 'yes' : 'no'
                ]);
            } else {
                Log::error("Cannot delete customer - Mikrotik deletion failed", [
                    'customer' => $customerCode,
                    'mikrotik_deleted' => $mikrotikDeleted,
                    'has_router' => $customer->router ? 'yes' : 'no',
                    'has_username' => $customer->customer_mikrotik_username ? 'yes' : 'no'
                ]);
                throw new \Exception("Gagal menghapus user dari Mikrotik. Customer tidak dihapus dari database.");
            }
            
            Log::info("Customer deleted successfully", [
                'customer_code' => $customerCode,
                'name' => $customerName,
                'mikrotik_deleted' => $mikrotikDeleted,
                'connection_type' => $customer->connection_type ?? 'N/A'
            ]);
            
            $message = "✅ Customer {$customerName} ({$customerCode}) berhasil dihapus!";
            if ($mikrotikDeleted) {
                $message .= " User juga telah dihapus dari Mikrotik.";
            }
            
            // ✅ Return JSON response untuk AJAX request
            if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'customer_code' => $customerCode,
                        'mikrotik_deleted' => $mikrotikDeleted
                    ]
                ]);
            }
            
            return redirect()->route('customers.index')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            Log::error("Failed to delete customer", [
                'customer' => $customer->customer_code ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // ✅ Return JSON response untuk AJAX request
            if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Gagal menghapus customer: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', '❌ Gagal menghapus customer: ' . $e->getMessage());
        }
    }

    public function suspend(Customer $customer)
    {
        try {
            // Update status ke suspended
            $customer->update([
                'status' => 'suspended',
                'is_online' => false
            ]);

            // Ubah profile Mikrotik ke PROFIL-ISOLIR untuk PPPoE
            if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) 
                && $customer->router 
                && $customer->customer_mikrotik_username) {
                
                $mikrotik = new MikrotikService($customer->router);
                $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
                
                // Ganti profile ke PROFIL-ISOLIR
                $mikrotik->setUserProfile($customer->customer_mikrotik_username, $isolirProfileName);
                
                Log::info("Customer manually suspended - Profile changed to ISOLIR", [
                    'customer' => $customer->customer_code,
                    'username' => $customer->customer_mikrotik_username,
                    'profile' => $isolirProfileName
                ]);
            }

            return redirect()->back()->with('success', '✅ Customer berhasil diisolir! Profile Mikrotik telah diubah ke PROFIL-ISOLIR.');
        } catch (\Exception $e) {
            Log::error("Failed to suspend customer", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', '❌ Gagal isolir customer: ' . $e->getMessage());
        }
    }

    public function activate(Customer $customer)
    {
        try {
            // ✅ PERBAIKAN: Set status ke 'terminated' dulu (Offline), bukan langsung 'active' (Online)
            // Status akan di-update otomatis ke 'active' (Online) oleh sync command jika benar-benar online di Mikrotik
            $customer->update([
                'status' => 'terminated', // Set ke terminated (Offline) dulu, bukan active (Online)
                'is_online' => false, // Pastikan is_online = false
                'custom_isolir_date' => null, // Reset custom isolir agar tidak di-isolir lagi
                'custom_isolir_executed' => false
            ]);

            // Restore profile Mikrotik ke paket normal untuk PPPoE
            if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) 
                && $customer->router 
                && $customer->customer_mikrotik_username
                && $customer->package) {
                
                $mikrotik = new MikrotikService($customer->router);
                $normalProfile = $customer->package->name;
                
                // Restore profile ke paket normal
                $mikrotik->setUserProfile($customer->customer_mikrotik_username, $normalProfile);
                
                Log::info("Customer manually activated - Profile restored", [
                    'customer' => $customer->customer_code,
                    'username' => $customer->customer_mikrotik_username,
                    'profile' => $normalProfile,
                    'custom_isolir_reset' => true,
                    'status_set_to' => 'terminated',
                    'note' => 'Status akan di-update ke active oleh sync command jika benar-benar online'
                ]);
            }
            
            // ✅ Trigger sync status untuk update status berdasarkan real status di Mikrotik
            try {
                \Artisan::call('customers:sync-online-status', [
                    '--router' => $customer->router_id
                ]);
                Log::info("Status sync triggered after activation", [
                    'customer' => $customer->customer_code,
                    'router_id' => $customer->router_id
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to trigger status sync after activation", [
                    'customer' => $customer->customer_code,
                    'error' => $e->getMessage()
                ]);
                // Jangan gagalkan aktivasi jika sync gagal
            }

            return redirect()->back()->with('success', '✅ Customer berhasil diaktifkan! Profile dikembalikan ke paket normal. Status akan di-update otomatis saat customer benar-benar online.');
        } catch (\Exception $e) {
            Log::error("Failed to activate customer", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', '❌ Gagal aktivasi customer: ' . $e->getMessage());
        }
    }

    public function changePackage(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id'
        ]);

        try {
            $newPackage = Package::findOrFail($validated['package_id']);

            if ($customer->router && $customer->customer_mikrotik_username) {
                $mikrotik = new MikrotikService($customer->router);
                $mikrotik->updatePPPoEProfile($customer->customer_mikrotik_username, $newPackage->mikrotik_profile_name ?? 'default');
            }

            $customer->update(['package_id' => $validated['package_id']]);

            return redirect()->back()->with('success', 'Package berhasil diubah');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal ubah package: ' . $e->getMessage());
        }
    }

    public function syncStatus()
    {
        try {
            // ✅ INCREASE EXECUTION TIME untuk sync yang mungkin butuh waktu lama
            set_time_limit(120); // 2 menit untuk membaca data dari Mikrotik
            
            // ✅ LOCK MECHANISM - Prevent multiple instances running simultaneously
            $lockKey = 'sync-online-status-running';
            $lockTimeout = 90; // 90 detik timeout (sesuai dengan frontend timeout)
            $lastSyncKey = 'sync-online-status-last-run';
            $minSyncInterval = 3; // Minimum 3 detik antar sync (throttle)
            
            // ✅ THROTTLE: Cek apakah sync baru saja berjalan
            $lastSync = Cache::get($lastSyncKey);
            if ($lastSync) {
                // Pastikan $lastSync adalah Carbon instance atau timestamp valid
                if ($lastSync instanceof \Carbon\Carbon) {
                    $diffSeconds = now()->diffInSeconds($lastSync);
                } else {
                    // Jika bukan Carbon, coba parse sebagai timestamp
                    try {
                        $lastSyncCarbon = \Carbon\Carbon::parse($lastSync);
                        $diffSeconds = now()->diffInSeconds($lastSyncCarbon);
                    } catch (\Exception $e) {
                        // Jika tidak bisa di-parse, reset lastSync
                        Cache::forget($lastSyncKey);
                        $diffSeconds = $minSyncInterval + 1; // Force allow sync
                    }
                }
                
                // ✅ FIX: Pastikan diffSeconds tidak negatif atau terlalu besar
                if ($diffSeconds < 0 || $diffSeconds > 300) {
                    // Jika diff negatif atau terlalu besar (> 5 menit), reset
                    Cache::forget($lastSyncKey);
                    $diffSeconds = $minSyncInterval + 1; // Force allow sync
                }
                
                if ($diffSeconds < $minSyncInterval) {
                    $remaining = $minSyncInterval - $diffSeconds;
                    return response()->json([
                        'success' => true,
                        'message' => "Sync terlalu cepat, tunggu " . round($remaining, 1) . " detik lagi...",
                        'synced' => false,
                        'throttled' => true
                    ]);
                }
            }
            
            // ✅ ATOMIC LOCK: Gunakan Cache::lock() untuk atomic operation
            // Ini mencegah race condition dengan lebih baik daripada Cache::has() + Cache::put()
            $lock = null;
            $lockAcquired = false;
            
            try {
                // Coba gunakan atomic lock jika cache driver mendukung
                $lock = Cache::lock($lockKey, $lockTimeout);
                $lockAcquired = $lock->get();
                
                if (!$lockAcquired) {
                    // Lock sudah dipegang oleh proses lain
                    return response()->json([
                        'success' => true,
                        'message' => 'Sync sedang berjalan...',
                        'synced' => false
                    ]);
                }
            } catch (\Exception $e) {
                // Fallback jika cache driver tidak mendukung lock (misalnya file cache)
                // Cache lock not supported, using fallback
                if (Cache::has($lockKey)) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Sync sedang berjalan...',
                        'synced' => false
                    ]);
                }
                Cache::put($lockKey, now(), now()->addSeconds($lockTimeout));
                $lockAcquired = true;
            }
            
            if (!$lockAcquired) {
                // Jika tidak bisa mendapatkan lock, berarti sync sedang berjalan
                return response()->json([
                    'success' => true,
                    'message' => 'Sync sedang berjalan...',
                    'synced' => false
                ]);
            }
            
            // ✅ Hapus logging - fokus pada akurasi
            
            // ✅ FIX: lastSync akan di-set SETELAH sync selesai (bukan di sini)
            
            try {
                // ✅ SYNC LANGSUNG - Baca /ppp/active dan update database
                // ✅ Logging minimal untuk mengurangi spam - hanya log summary di akhir
                
                $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
                $syncedCount = 0;
                $errorCount = 0;
                $onlineUpdates = 0;
                $offlineUpdates = 0;
                $isolirUpdates = 0;
                
                // Get routers
                $routers = Router::where('is_active', true)->get();
                
                if ($routers->isEmpty()) {
                    Cache::forget($lockKey);
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak ada router aktif'
                    ], 400);
                }
                
                foreach ($routers as $router) {
                    try {
                        // Get all PPPoE customers for this router
                        $customers = Customer::where('router_id', $router->id)
                            ->whereIn('connection_type', ['pppoe_direct', 'pppoe_mikrotik'])
                            ->whereNotNull('customer_mikrotik_username')
                            ->get(['id', 'customer_code', 'name', 'customer_mikrotik_username', 'status']);
                        
                        if ($customers->isEmpty()) {
                            continue;
                        }
                        
                        // Connect to MikroTik
                        $mikrotik = new MikrotikService($router);
                        
                        // Get active sessions dari /ppp/active
                        $activeSessions = $mikrotik->getActivePPPoESessions();
                        
                        // Convert to array if Collection
                        if ($activeSessions instanceof \Illuminate\Support\Collection) {
                            $activeSessions = $activeSessions->toArray();
                        }
                        
                        // Build array of active usernames (lowercase for case-insensitive matching)
                        // ✅ PASTIKAN: Gunakan array dengan key untuk menghindari duplicate dan O(1) lookup
                        $activeUsernamesLower = [];
                        foreach ($activeSessions as $session) {
                            $name = $session['name'] ?? '';
                            if (!empty($name)) {
                                $normalized = strtolower(trim($name));
                                $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized);
                                // ✅ Gunakan key untuk menghindari duplicate dan memudahkan lookup
                                $activeUsernamesLower[$normalized] = true;
                            }
                        }
                        // Convert ke array untuk backward compatibility (tapi sudah unique karena key)
                        $activeUsernamesLower = array_keys($activeUsernamesLower);
                        
                        // ✅ Total active sessions untuk summary log di akhir
                        $totalActive = count($activeUsernamesLower);
                        
                        // Get secrets untuk cek profile
                        $pppoeSecrets = $mikrotik->getAllPPPoESecrets();
                        $userProfiles = [];
                        foreach ($pppoeSecrets as $secret) {
                            $secretName = $secret['name'] ?? '';
                            $secretUsername = strtolower(trim($secretName));
                            $secretUsername = preg_replace('/[\x00-\x1F\x7F]/', '', $secretUsername);
                            if (!empty($secretUsername)) {
                                $userProfiles[$secretUsername] = $secret['profile'] ?? '';
                            }
                        }
                        
                        // ✅ BATCH UPDATE: Kumpulkan semua update dulu, baru eksekusi sekaligus
                        // Check if online - gunakan array_flip untuk O(1) lookup (hitung sekali)
                        $activeUsernamesSet = array_flip($activeUsernamesLower);
                        
                        foreach ($customers as $customer) {
                            try {
                                $usernameOriginal = $customer->customer_mikrotik_username ?? '';
                                $username = strtolower(trim($usernameOriginal));
                                $username = preg_replace('/[\x00-\x1F\x7F]/', '', $username);
                                
                                if (empty($username)) {
                                    continue;
                                }
                                
                                // ✅ FLEXIBLE MATCHING: Cek exact match dulu, lalu fuzzy match
                                $isOnline = isset($activeUsernamesSet[$username]);
                                $matchedUsername = null;
                                $bestMatch = null;
                                $bestDistance = PHP_INT_MAX;
                                
                                // Jika exact match ditemukan, set variabel
                                if ($isOnline) {
                                    $matchedUsername = $username;
                                    $bestMatch = $username;
                                    $bestDistance = 0;
                                }
                                
                                // Jika tidak exact match, cek fuzzy match untuk typo detection
                                if (!$isOnline) {
                                    // ✅ FUZZY MATCHING: Cek apakah ada username yang mirip (kemungkinan typo)
                                    
                                    foreach ($activeUsernamesLower as $active) {
                                        // Exact match (double check)
                                        if ($active === $username) {
                                            $isOnline = true;
                                            $matchedUsername = $active;
                                            $bestMatch = $active;
                                            $bestDistance = 0;
                                            break;
                                        }
                                        
                                        // ✅ LEVENSHTEIN DISTANCE: Untuk deteksi typo (max 2 karakter berbeda)
                                        if (strlen($active) > 0 && strlen($username) > 0) {
                                            $distance = levenshtein($active, $username);
                                            if ($distance < $bestDistance && $distance <= 2) {
                                                $bestDistance = $distance;
                                                $bestMatch = $active;
                                            }
                                        }
                                        
                                        // ✅ PARTIAL MATCH: DISABLED - Terlalu agresif dan bisa menyebabkan false positive
                                        // ✅ Hanya gunakan exact match dan fuzzy match dengan distance <= 1
                                        // $minLength = min(strlen($active), strlen($username));
                                        // if ($minLength >= 5) {
                                        //     $shorter = strlen($active) < strlen($username) ? $active : $username;
                                        //     $longer = strlen($active) >= strlen($username) ? $active : $username;
                                        //     
                                        //     // Jika shorter adalah prefix dari longer (minimal 80% match)
                                        //     if (strpos($longer, $shorter) === 0) {
                                        //         $matchLength = strlen($shorter);
                                        //         if ($matchLength >= max(5, strlen($shorter) * 0.8)) {
                                        //             if ($bestDistance > 0) { // Hanya jika belum ada exact match
                                        //                 $bestMatch = $active;
                                        //                 $bestDistance = 0; // Prefix match dianggap exact
                                        //             }
                                        //         }
                                        //     }
                                        // }
                                    }
                                    
                                    // ✅ Apply fuzzy match jika ditemukan
                                    // ✅ PERKETAT: Hanya gunakan fuzzy match jika distance = 1 (satu karakter berbeda)
                                    // ✅ Distance = 2 terlalu longgar dan bisa menyebabkan false positive
                                    if ($bestMatch && $bestDistance <= 1) {
                                        $isOnline = true;
                                        $matchedUsername = $bestMatch;
                                        
                                        // ✅ Hapus logging - fokus pada akurasi
                                    } else {
                                        // ✅ Logging minimal - disabled untuk mengurangi spam
                                        // ✅ Uncomment jika perlu debugging customer yang tidak match
                                        // if ($customer->status === 'active') {
                                        //     Log::warning("⚠️ Customer 'active' tapi tidak ditemukan di active sessions", [
                                        //         'customer_code' => $customer->customer_code,
                                        //         'db_username' => $usernameOriginal,
                                        //         'router' => $router->name
                                        //     ]);
                                        // }
                                    }
                                }
                                
                                // Check profile untuk isolir
                                // Cek profile dari username database dulu, lalu dari matched username jika berbeda
                                $customerProfile = $userProfiles[$username] ?? null;
                                
                                // Jika tidak ada profile dari username database dan ada matched username, cek dari matched username
                                if (!$customerProfile && $matchedUsername && $matchedUsername !== $username) {
                                    $customerProfile = $userProfiles[$matchedUsername] ?? null;
                                }
                                
                                $isIsolir = $mikrotik->isIsolirProfile($customerProfile, $isolirProfileName);
                                
                                // Determine new status
                                $newStatus = null;
                                if ($isIsolir) {
                                    $newStatus = 'suspended';
                                } elseif ($isOnline) {
                                    $newStatus = 'active';
                                } else {
                                    // ✅ Customer tidak online di Mikrotik
                                    if ($customer->status === 'suspended') {
                                        // Customer sudah isolir, jangan ubah ke terminated
                                        continue;
                                    }
                                    $newStatus = 'terminated';
                                }
                                
                                // ✅ PASTIKAN UPDATE - Update jika status berbeda
                                // ✅ Update langsung tanpa validasi konsistensi yang kompleks
                                if ($customer->status !== $newStatus) {
                                    $oldStatus = $customer->status;
                                    
                                    // ✅ Hapus logging - fokus pada akurasi, bukan logging
                                    
                                    // ✅ Batch update untuk mengurangi query database
                                    // Kumpulkan dulu semua update, baru execute sekaligus
                                    DB::table('customers')
                                        ->where('id', $customer->id)
                                        ->update([
                                            'status' => $newStatus,
                                            'updated_at' => now()
                                        ]);
                                    
                                    // ✅ Reload customer untuk mendapatkan data terbaru
                                    $customer->refresh();
                                    
                                    // ✅ Broadcast event untuk real-time update via WebSocket
                                    try {
                                        event(new CustomerStatusUpdated($customer, $oldStatus, $newStatus));
                                        
                                        // ✅ Broadcast stats update juga setiap kali ada perubahan status
                                        // ✅ Ini memastikan jumlah online/offline selalu update real-time
                                        $stats = [
                                            'total' => Customer::count(),
                                            'online' => Customer::where('status', 'active')->count(),
                                            'offline' => Customer::where('status', 'terminated')->count(),
                                            'suspended' => Customer::where('status', 'suspended')->count(),
                                        ];
                                        event(new CustomerStatsUpdated($stats));
                                    } catch (\Exception $e) {
                                        Log::warning("Failed to broadcast customer status update: " . $e->getMessage());
                                    }
                                    
                                    $syncedCount++;
                                    
                                    // Count updates
                                    if ($newStatus === 'active') {
                                        $onlineUpdates++;
                                    } elseif ($newStatus === 'terminated') {
                                        $offlineUpdates++;
                                    } elseif ($newStatus === 'suspended') {
                                        $isolirUpdates++;
                                    }
                                }
                                
                            } catch (\Exception $e) {
                                $errorCount++;
                                Log::error("Failed to sync customer {$customer->customer_code}: " . $e->getMessage());
                            }
                        }
                        
                        // ✅ Logging minimal - hanya log summary di akhir untuk mengurangi spam
                        
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error("❌ Failed to sync router {$router->name}: " . $e->getMessage(), [
                            'router' => $router->name,
                            'router_ip' => $router->ip_address,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                }
                
                // ✅ Broadcast stats update - hanya jika ada perubahan signifikan
                // ✅ Ini mengurangi fluktuasi kecil yang menyebabkan angka "lompat-lompat"
                try {
                    $newStats = [
                        'total' => Customer::count(),
                        'online' => Customer::where('status', 'active')->count(),
                        'offline' => Customer::where('status', 'terminated')->count(),
                        'suspended' => Customer::where('status', 'suspended')->count(),
                    ];
                    
                    // ✅ Ambil stats terakhir dari cache untuk perbandingan
                    $lastStats = Cache::get('last_broadcasted_stats', []);
                    $statsChanged = false;
                    
                    // ✅ Cek apakah ada perubahan signifikan (minimal 5 customer atau 1.5%)
                    // ✅ Threshold lebih besar untuk mengurangi fluktuasi
                    if (empty($lastStats)) {
                        $statsChanged = true; // First time, always broadcast
                    } else {
                        $threshold = 5; // Minimal 5 customer perubahan (dinaikkan dari 3)
                        $percentThreshold = 0.015; // Atau 1.5% perubahan (dikurangi dari 2%)
                        
                        foreach (['online', 'offline', 'suspended'] as $key) {
                            $diff = abs($newStats[$key] - ($lastStats[$key] ?? 0));
                            $total = $newStats['total'] > 0 ? $newStats['total'] : 1;
                            $percentDiff = $diff / $total;
                            
                            // Jika perubahan >= threshold atau >= percentThreshold
                            if ($diff >= $threshold || $percentDiff >= $percentThreshold) {
                                $statsChanged = true;
                                break;
                            }
                        }
                    }
                    
                    // ✅ Broadcast hanya jika ada perubahan signifikan
                    if ($statsChanged) {
                        event(new CustomerStatsUpdated($newStats));
                        // Simpan stats terakhir untuk perbandingan berikutnya
                        Cache::put('last_broadcasted_stats', $newStats, now()->addMinutes(10));
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to broadcast stats: " . $e->getMessage());
                }
                
                // Sync billing dates (tetap di controller)
                $syncedBillingCount = 0;
                
                // 2. Sync Billing Dates (untuk customer yang paketnya punya custom_expire_day)
                $billingCustomers = Customer::with('package')
                    ->whereHas('package', function($query) {
                        $query->whereNotNull('custom_expire_day');
                    })
                    ->get();

                $today = now(); // Tanggal hari ini untuk acuan

                foreach ($billingCustomers as $customer) {
                    try {
                        $package = $customer->package;
                        if (!$package || !$package->custom_expire_day) {
                            continue;
                        }

                        // ✅ LOGIKA BARU: Gunakan tanggal HARI INI sebagai acuan
                        $nextBilling = $today->copy();
                        
                        // Jika tanggal expire sudah lewat di bulan ini → set ke bulan depan
                        $expireDay = (int) $package->custom_expire_day; // Pastikan integer
                        if ($today->day > $expireDay) {
                            $nextBilling->addMonth();
                        }
                        // Jika belum lewat, tetap di bulan ini
                        
                        // Set ke custom_expire_day (pastikan integer)
                        $nextBilling->day($expireDay);
                        
                        // Set waktu dari paket atau default 23:59
                        if ($package->custom_expire_time) {
                            $time = \Carbon\Carbon::parse($package->custom_expire_time);
                            $nextBilling->setTime($time->hour, $time->minute);
                        } else {
                            $nextBilling->setTime(23, 59);
                        }

                        // Cek apakah perlu update
                        if (!$customer->next_billing_date || 
                            $customer->next_billing_date->format('Y-m-d H:i') !== $nextBilling->format('Y-m-d H:i')) {
                            $customer->next_billing_date = $nextBilling;
                            
                            // ✅ SISTEM BARU: Auto update custom_isolir_date = next_billing_date
                            $customer->custom_isolir_date = $nextBilling->copy();
                            $customer->custom_isolir_executed = false;
                            
                            $customer->save();
                            $syncedBillingCount++;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to sync billing date for customer {$customer->customer_code}: " . $e->getMessage());
                        continue;
                    }
                }
                
                // ✅ FIX: Update lastSync SETELAH sync selesai (bukan di awal)
                // ✅ Gunakan Carbon instance untuk konsistensi dengan throttle check
                Cache::put($lastSyncKey, now(), now()->addMinutes(5));
                
                // ✅ Hapus logging - fokus pada akurasi
                
                // ✅ Return response (lock akan di-release di finally block)
                $messages = [];
                if ($syncedCount > 0) {
                    $messages[] = "{$syncedCount} customer status";
                }
                if ($syncedBillingCount > 0) {
                    $messages[] = "{$syncedBillingCount} billing dates";
                }
                
                $message = !empty($messages) 
                    ? "✅ Sync berhasil: " . implode(", ", $messages) . " telah di-update!" 
                    : "✅ Sync selesai (tidak ada perubahan)";
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'synced' => true,
                    'stats' => [
                        'online' => Customer::where('status', 'active')->count(),
                        'offline' => Customer::where('status', 'terminated')->count(),
                        'suspended' => Customer::where('status', 'suspended')->count(),
                        'total' => Customer::count()
                    ],
                    'updates' => [
                        'online' => $onlineUpdates,
                        'offline' => $offlineUpdates,
                        'isolir' => $isolirUpdates,
                        'total' => $syncedCount
                    ]
                ]);
                
            } catch (\Exception $e) {
                Log::error("Sync status failed: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal melakukan sync: ' . $e->getMessage()
                ], 500);
            } finally {
                // ✅ RELEASE LOCK - Pastikan lock selalu di-release meskipun ada error
                if ($lock && $lockAcquired) {
                    try {
                        $lock->release();
                    } catch (\Exception $e) {
                        // Jika release gagal, gunakan fallback
                        Cache::forget($lockKey);
                    }
                } else {
                    // Fallback: release lock manual
                    Cache::forget($lockKey);
                }
            }
        } catch (\Exception $e) {
            // ✅ Pastikan lock di-release jika error di outer try
            Cache::forget($lockKey);
            Log::error("Sync status failed (outer): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan sync: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== AJAX METHODS ====================

    public function getCustomersAjax(Request $request)
    {
        $query = Customer::with(['package', 'router']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('customer_mikrotik_username', 'like', "%{$search}%");
            });
        }

        // ✅ Filter berdasarkan status (active, suspended)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ Filter Online/Offline - berdasarkan status
        // Online = active, Offline = terminated
        if ($request->filled('is_online')) {
            $isOnline = $request->is_online == '1';
            if ($isOnline) {
                $query->where('status', 'active'); // Online = active
            } else {
                $query->where('status', 'terminated'); // Offline = terminated
            }
        }

        if ($request->filled('connection_type')) {
            $query->where('connection_type', $request->connection_type);
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // ✅ PASTIKAN data langsung dari database tanpa cache Eloquent
        $customers = $query->latest()->paginate(15);

        // ✅ Ambil semua ID customer yang akan ditampilkan
        $customerIds = collect($customers->items())->pluck('id')->toArray();
        
        // ✅ Ambil data fresh status langsung dari database (1 query untuk semua)
        $freshStatuses = DB::table('customers')
            ->select('id', 'status')
            ->whereIn('id', $customerIds)
            ->get()
            ->keyBy('id');
        
        // ✅ Update setiap customer dengan data fresh status
        $customersData = collect($customers->items())->map(function ($customer) use ($freshStatuses) {
            $freshData = $freshStatuses->get($customer->id);
            if ($freshData) {
                $customer->status = $freshData->status;
            }
            return $customer;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $customersData,
            'pagination' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ],
            'links' => [
                'first' => $customers->url(1),
                'last' => $customers->url($customers->lastPage()),
                'prev' => $customers->previousPageUrl(),
                'next' => $customers->nextPageUrl(),
            ]
        ]);
    }

    public function suspendAjax(Customer $customer)
    {
        try {
            $oldStatus = $customer->status;
            
            // Update status
            $customer->update([
                'status' => 'suspended',
                'is_online' => false
            ]);

            // Reload untuk mendapatkan data terbaru
            $customer->refresh();

            // Ubah profile ke PROFIL-ISOLIR untuk PPPoE
            if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) 
                && $customer->router 
                && $customer->customer_mikrotik_username) {
                
                $mikrotik = new MikrotikService($customer->router);
                $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
                
                $mikrotik->setUserProfile($customer->customer_mikrotik_username, $isolirProfileName);
                
                Log::info("Customer suspended via AJAX - Profile changed to ISOLIR", [
                    'customer' => $customer->customer_code,
                    'profile' => $isolirProfileName
                ]);
            }

            // ✅ Broadcast event untuk real-time update via WebSocket
            try {
                event(new CustomerStatusUpdated($customer, $oldStatus, 'suspended'));
                
                // ✅ Broadcast stats update juga
                $stats = [
                    'total' => Customer::count(),
                    'online' => Customer::where('status', 'active')->count(),
                    'offline' => Customer::where('status', 'terminated')->count(),
                    'suspended' => Customer::where('status', 'suspended')->count(),
                ];
                event(new CustomerStatsUpdated($stats));
            } catch (\Exception $e) {
                Log::warning("Failed to broadcast customer status update: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil diisolir! Profile diubah ke PROFIL-ISOLIR.',
                'data' => $customer->fresh(['package', 'router'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal isolir customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function activateAjax(Customer $customer)
    {
        try {
            $oldStatus = $customer->status;
            
            // ✅ PERBAIKAN: Set status ke 'terminated' dulu (Offline), bukan langsung 'active' (Online)
            // Status akan di-update otomatis ke 'active' (Online) oleh sync command jika benar-benar online di Mikrotik
            $customer->update([
                'status' => 'terminated', // Set ke terminated (Offline) dulu, bukan active (Online)
                'is_online' => false, // Pastikan is_online = false
                'custom_isolir_date' => null, // Reset custom isolir agar tidak di-isolir lagi
                'custom_isolir_executed' => false
            ]);

            // Reload untuk mendapatkan data terbaru
            $customer->refresh();

            // Restore profile ke paket normal untuk PPPoE
            if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) 
                && $customer->router 
                && $customer->customer_mikrotik_username
                && $customer->package) {
                
                $mikrotik = new MikrotikService($customer->router);
                $normalProfile = $customer->package->name;
                
                $mikrotik->setUserProfile($customer->customer_mikrotik_username, $normalProfile);
                
                Log::info("Customer activated via AJAX - Profile restored", [
                    'customer' => $customer->customer_code,
                    'profile' => $normalProfile,
                    'custom_isolir_reset' => true,
                    'status_set_to' => 'terminated',
                    'note' => 'Status akan di-update ke active oleh sync command jika benar-benar online'
                ]);
            }
            
            // ✅ Broadcast event untuk real-time update via WebSocket
            try {
                event(new CustomerStatusUpdated($customer, $oldStatus, 'terminated'));
                
                // ✅ Broadcast stats update juga
                $stats = [
                    'total' => Customer::count(),
                    'online' => Customer::where('status', 'active')->count(),
                    'offline' => Customer::where('status', 'terminated')->count(),
                    'suspended' => Customer::where('status', 'suspended')->count(),
                ];
                event(new CustomerStatsUpdated($stats));
            } catch (\Exception $e) {
                Log::warning("Failed to broadcast customer status update: " . $e->getMessage());
            }
            
            // ✅ Trigger sync status untuk update status berdasarkan real status di Mikrotik
            // ✅ Sync akan broadcast event lagi jika status berubah ke 'active'
            try {
                \Artisan::call('customers:sync-online-status', [
                    '--router' => $customer->router_id
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to trigger status sync after AJAX activation", [
                    'customer' => $customer->customer_code,
                    'error' => $e->getMessage()
                ]);
                // Jangan gagalkan aktivasi jika sync gagal
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil diaktifkan! Profile dikembalikan ke paket normal. Status akan di-update otomatis saat customer benar-benar online.',
                'data' => $customer->fresh(['package', 'router'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal aktivasi customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStatsAjax()
    {
        // ✅ PASTIKAN data langsung dari database (fresh query tanpa cache)
        // ✅ GUNAKAN STATUS SAJA: active = Online, terminated = Offline, suspended = Isolir
        
        $baseQuery = DB::table('customers')->whereNull('deleted_at');
        
        // Total customers (semua status)
        $total = (clone $baseQuery)->count();
        
        // Online customers: status = 'active'
        $online = (clone $baseQuery)
            ->where('status', 'active')
            ->count();
        
        // Offline customers: status = 'terminated'
        $offline = (clone $baseQuery)
            ->where('status', 'terminated')
            ->count();
        
        // Suspended/Isolir customers: status = 'suspended'
        $suspended = (clone $baseQuery)
            ->where('status', 'suspended')
            ->count();
        
        $stats = [
            'total' => $total,
            'active' => $online, // Active = Online
            'suspended' => $suspended,
            'online' => $online,
            'offline' => $offline,
        ];

        // Calculate percentages berdasarkan total
        $stats['online_percentage'] = $total > 0 
            ? round(($online / $total) * 100, 1) 
            : 0;
        $stats['offline_percentage'] = $total > 0 
            ? round(($offline / $total) * 100, 1) 
            : 0;
        $stats['suspended_percentage'] = $total > 0 
            ? round(($suspended / $total) * 100, 1) 
            : 0;
        $stats['active_percentage'] = $total > 0
            ? round(($online / $total) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Provision customer ke Mikrotik berdasarkan tipe koneksi
     */
    protected function provisionToMikrotik(Customer $customer, array $validated)
    {
        $router = Router::find($customer->router_id);
        if (!$router) {
            throw new \Exception("Router tidak ditemukan");
        }

        $mikrotik = new MikrotikService($router);
        $package = Package::find($customer->package_id);

        // ✅ Pastikan profile/paket ada di Mikrotik (hanya CREATE jika belum ada, jangan UPDATE)
        if ($package) {
            // ✅ Cek dulu apakah profile sudah ada, jika belum ada baru create
            $mikrotik->ensurePackageProfile($package);
        }

        $connectionType = $validated['connection_type'];
        
        Log::info("Provisioning customer to Mikrotik", [
            'customer' => $customer->customer_code,
            'type' => $connectionType,
            'router' => $router->name
        ]);

        switch ($connectionType) {
            case 'pppoe_direct':
            case 'pppoe_mikrotik':
                $this->provisionPPPoE($mikrotik, $customer, $validated, $package);
                break;

            case 'hotspot':
                $this->provisionHotspot($mikrotik, $customer, $validated, $package);
                break;

            case 'static_ip':
                $this->provisionStaticIP($mikrotik, $customer, $validated);
                break;

            case 'dhcp':
                $this->provisionDHCP($mikrotik, $customer, $validated);
                break;
        }

        Log::info("Customer successfully provisioned to Mikrotik", [
            'customer' => $customer->customer_code
        ]);
    }

    /**
     * Provision PPPoE ke Mikrotik
     */
    protected function provisionPPPoE(MikrotikService $mikrotik, Customer $customer, array $validated, ?Package $package)
    {
        $username = $validated['customer_mikrotik_username'] ?? null;
        $password = $validated['customer_mikrotik_password'] ?? null;

        // ✅ Validasi lebih ketat
        if (empty($username) || trim($username) === '') {
            Log::error("PPPoE username is empty", [
                'customer' => $customer->customer_code,
                'validated' => [
                    'username' => $username,
                    'has_username_key' => isset($validated['customer_mikrotik_username']),
                    'connection_type' => $validated['connection_type'] ?? 'N/A'
                ]
            ]);
            throw new \Exception("PPPoE Username wajib diisi dan tidak boleh kosong");
        }
        
        if (empty($password) || trim($password) === '') {
            Log::error("PPPoE password is empty", [
                'customer' => $customer->customer_code,
                'username' => $username,
                'has_password_key' => isset($validated['customer_mikrotik_password'])
            ]);
            throw new \Exception("PPPoE Password wajib diisi dan tidak boleh kosong");
        }
        
        // Trim whitespace
        $username = trim($username);
        $password = trim($password);
        
        Log::info("Provisioning PPPoE user", [
            'customer' => $customer->customer_code,
            'username' => $username,
            'password_length' => strlen($password),
            'package_id' => $package->id ?? 'N/A',
            'package_name' => $package->name ?? 'N/A'
        ]);

        // ✅ Pastikan profile sesuai dengan package di router
        $profile = $package ? $package->name : 'default';
        
        Log::info("Provisioning PPPoE user", [
            'customer' => $customer->customer_code,
            'username' => $username,
            'package_id' => $package->id ?? 'N/A',
            'package_name' => $package->name ?? 'N/A',
            'profile' => $profile
        ]);
        
        // ✅ Pastikan profile sudah dibuat di Mikrotik sebelum create user (HANYA CREATE jika belum ada)
        if ($package) {
            $profileCreated = $mikrotik->ensurePackageProfile($package); // ✅ Hanya create, tidak update
            Log::info("Profile ensured in Mikrotik before creating user", [
                'profile' => $profile,
                'package' => $package->name,
                'profile_created' => $profileCreated
            ]);
        }

        // ✅ Create PPPoE Secret di Mikrotik dengan profile yang benar
        Log::info("Creating PPPoE user in Mikrotik", [
            'username' => $username,
            'profile' => $profile,
            'package_name' => $package->name ?? 'N/A'
        ]);
        
        $mikrotik->createPPPoEUser($username, $password, $profile);
        
        Log::info("PPPoE user created successfully", [
            'username' => $username,
            'profile' => $profile
        ]);

        // Simpan ke database (both fields and connection_config)
        $customer->update([
            'customer_mikrotik_username' => $username,
            'customer_mikrotik_password' => $password,
            'connection_config' => [
                'username' => $username,
                'password' => $password,
                'profile' => $profile,
                'service' => 'pppoe'
            ]
        ]);

        Log::info("PPPoE user created in Mikrotik", [
            'customer' => $customer->customer_code,
            'username' => $username,
            'profile' => $profile
        ]);
    }

    /**
     * Provision Hotspot ke Mikrotik
     */
    protected function provisionHotspot(MikrotikService $mikrotik, Customer $customer, array $validated, ?Package $package)
    {
        $username = $validated['customer_mikrotik_username'] ?? $customer->customer_code;
        $password = $validated['customer_mikrotik_password'] ?? \Str::random(8);

        // ✅ Pastikan profile sesuai dengan package di router
        $profile = $package ? $package->name : 'default';

        // ✅ Pastikan hotspot profile sudah dibuat di Mikrotik sebelum create user (HANYA CREATE jika belum ada)
        if ($package) {
            // Cek apakah profile sudah ada, jika belum ada baru create
            $profileExists = $mikrotik->checkProfileExists($package->name, 'hotspot');
            if (!$profileExists) {
                $mikrotik->createHotspotProfile($profile, $package->download_speed, $package->upload_speed);
                Log::info("Hotspot profile created in Mikrotik before creating user", [
                    'profile' => $profile,
                    'package' => $package->name
                ]);
            } else {
                Log::info("Hotspot profile already exists in Mikrotik, skipping profile creation", [
                    'profile' => $profile,
                    'package' => $package->name
                ]);
            }
        }

        // ✅ CREATE Hotspot User di Mikrotik dengan profile yang benar
        $mikrotik->createHotspotUser($username, $password, $profile);

        // Simpan connection_config
        $customer->update([
            'customer_mikrotik_username' => $username,
            'customer_mikrotik_password' => $password,
            'connection_config' => [
                'username' => $username,
                'password' => $password,
                'profile' => $profile,
                'type' => 'hotspot'
            ]
        ]);

        Log::info("Hotspot user created in Mikrotik", [
            'customer' => $customer->customer_code,
            'username' => $username,
            'profile' => $profile
        ]);
    }

    /**
     * Provision Static IP ke Mikrotik
     */
    protected function provisionStaticIP(MikrotikService $mikrotik, Customer $customer, array $validated)
    {
        $config = $customer->connection_config ?? [];
        $staticIP = $config['static_ip'] ?? null;
        $subnet = $config['static_subnet'] ?? '255.255.255.0';
        $gateway = $config['static_gateway'] ?? null;

        if (!$staticIP) {
            throw new \Exception("Static IP address wajib diisi");
        }

        // Format: IP/CIDR
        $cidr = $this->subnetToCIDR($subnet);
        $address = $staticIP . '/' . $cidr;
        $interface = 'bridge'; // Default interface, bisa disesuaikan

        // Create Static IP di Mikrotik
        $mikrotik->createStaticIP($address, $interface);

        // Update connection_config
        $customer->update([
            'connection_config' => [
                'static_ip' => $staticIP,
                'static_subnet' => $subnet,
                'static_gateway' => $gateway,
                'cidr' => $cidr,
                'type' => 'static_ip'
            ]
        ]);

        Log::info("Static IP created in Mikrotik", [
            'customer' => $customer->customer_code,
            'ip' => $address
        ]);
    }

    /**
     * Provision DHCP Lease ke Mikrotik
     */
    protected function provisionDHCP(MikrotikService $mikrotik, Customer $customer, array $validated)
    {
        $config = $customer->connection_config ?? [];
        $macAddress = $config['mac_address'] ?? null;
        $ipAddress = $config['dhcp_ip'] ?? null;

        if (!$macAddress || !$ipAddress) {
            throw new \Exception("MAC Address dan IP Address wajib diisi untuk DHCP");
        }

        // Create DHCP Lease di Mikrotik
        $comment = "Customer: {$customer->name} ({$customer->customer_code})";
        $mikrotik->createDHCPLease($macAddress, $ipAddress, $comment);

        // Update connection_config
        $customer->update([
            'connection_config' => [
                'mac_address' => $macAddress,
                'dhcp_ip' => $ipAddress,
                'type' => 'dhcp'
            ]
        ]);

        Log::info("DHCP Lease created in Mikrotik", [
            'customer' => $customer->customer_code,
            'mac' => $macAddress,
            'ip' => $ipAddress
        ]);
    }

    /**
     * Convert subnet mask to CIDR notation
     */
    protected function subnetToCIDR(string $subnet): int
    {
        $mapping = [
            '255.255.255.255' => 32,
            '255.255.255.254' => 31,
            '255.255.255.252' => 30,
            '255.255.255.248' => 29,
            '255.255.255.240' => 28,
            '255.255.255.224' => 27,
            '255.255.255.192' => 26,
            '255.255.255.128' => 25,
            '255.255.255.0' => 24,
            '255.255.254.0' => 23,
            '255.255.252.0' => 22,
            '255.255.248.0' => 21,
            '255.255.240.0' => 20,
            '255.255.224.0' => 19,
            '255.255.192.0' => 18,
            '255.255.128.0' => 17,
            '255.255.0.0' => 16,
        ];

        return $mapping[$subnet] ?? 24;
    }
    
    /**
     * Format phone untuk logging (mask sebagian nomor untuk privacy)
     */
    private function formatPhoneForLog($phone)
    {
        if (empty($phone)) {
            return 'N/A';
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 4) {
            return substr($phone, 0, 2) . '****' . substr($phone, -2);
        }
        return '****';
    }

    /**
     * ✅ Keep-alive endpoint untuk dynamic sync frequency
     * Set flag "user_active" di cache dengan TTL 2 menit
     * Jika billing dibuka → sync setiap 1 menit
     * Jika billing tidak dibuka selama 2 menit → sync kembali ke 5 menit
     */
    public function keepAlive()
    {
        try {
            // Set flag dengan TTL 2 menit (120 detik)
            \Cache::put('sync_user_active', true, now()->addMinutes(2));
            
            return response()->json([
                'success' => true,
                'message' => 'Keep-alive received',
                'sync_mode' => '1 minute', // Real-time mode
                'expires_at' => now()->addMinutes(2)->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Keep-alive error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Keep-alive failed'
            ], 500);
        }
    }

    /**
     * Get PPPoE interface detail untuk customer
     * Mengembalikan uptime, remote IP, last link up/down, dan traffic stats
     */
    public function getPPPoEInterfaceDetail(Customer $customer)
    {
        try {
            // Hanya untuk customer dengan tipe PPPoE
            if (!in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer ini bukan tipe PPPoE'
                ], 400);
            }

            // Pastikan customer punya router dan username
            if (!$customer->router || !$customer->customer_mikrotik_username) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak memiliki router atau username'
                ], 400);
            }

            // Ambil detail interface dari Mikrotik dengan timeout handling
            try {
                $mikrotik = new MikrotikService($customer->router);
                $interfaceDetail = $mikrotik->getPPPoEInterfaceDetail($customer->customer_mikrotik_username);

                if (!$interfaceDetail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Interface PPPoE tidak ditemukan atau customer sedang offline. Mungkin terjadi timeout saat mengambil data.',
                        'data' => null
                    ]);
                }
            } catch (\Exception $e) {
                $isTimeout = strpos(strtolower($e->getMessage()), 'timeout') !== false || 
                            strpos(strtolower($e->getMessage()), 'timed out') !== false;
                
                Log::error("Failed to get PPPoE interface detail (timeout handling)", [
                    'customer' => $customer->customer_code,
                    'error' => $e->getMessage(),
                    'is_timeout' => $isTimeout
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $isTimeout 
                        ? 'Timeout saat mengambil data dari Mikrotik. Silakan coba lagi.' 
                        : 'Gagal mengambil data interface: ' . $e->getMessage(),
                    'data' => null,
                    'is_timeout' => $isTimeout
                ], 500);
            }

            // ✅ Broadcast traffic data via WebSocket untuk real-time monitoring
            if (isset($interfaceDetail['traffic']) && $interfaceDetail['traffic']) {
                try {
                    $trafficData = $interfaceDetail['traffic'];
                    
                    // Log traffic data sebelum broadcast
                    Log::info("Broadcasting traffic data", [
                        'customer_id' => $customer->id,
                        'customer_code' => $customer->customer_code,
                        'tx_rate' => $trafficData['tx_rate'] ?? 0,
                        'rx_rate' => $trafficData['rx_rate'] ?? 0,
                        'tx_bytes' => $trafficData['tx_bytes'] ?? 0,
                        'rx_bytes' => $trafficData['rx_bytes'] ?? 0,
                    ]);
                    
                    event(new \App\Events\PPPoETrafficUpdated($customer->id, $trafficData));
                } catch (\Exception $e) {
                    Log::warning("Failed to broadcast traffic update: " . $e->getMessage(), [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::debug("No traffic data to broadcast", [
                    'customer_id' => $customer->id,
                    'has_traffic' => isset($interfaceDetail['traffic']),
                    'traffic_data' => $interfaceDetail['traffic'] ?? null
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $interfaceDetail
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get PPPoE interface detail", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data interface: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start real-time traffic monitoring untuk customer
     * Akan mengirim traffic data via WebSocket setiap beberapa detik
     */
    public function startTrafficMonitoring(Customer $customer)
    {
        try {
            // Hanya untuk customer dengan tipe PPPoE
            if (!in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer ini bukan tipe PPPoE'
                ], 400);
            }

            // Pastikan customer punya router dan username
            if (!$customer->router || !$customer->customer_mikrotik_username) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak memiliki router atau username'
                ], 400);
            }

            // Set flag di cache bahwa monitoring aktif untuk customer ini
            $cacheKey = "traffic_monitoring_{$customer->id}";
            \Cache::put($cacheKey, true, now()->addMinutes(10)); // Monitoring aktif selama 10 menit

            // Ambil data pertama kali dan broadcast
            $this->broadcastTrafficData($customer);

            // ✅ Start background job untuk polling (jika menggunakan queue)
            // Atau bisa menggunakan scheduled task
            // Untuk sekarang, polling dilakukan dari frontend setiap 2 detik

            return response()->json([
                'success' => true,
                'message' => 'Traffic monitoring dimulai. Data akan dikirim via WebSocket setiap 2 detik.',
                'customer_id' => $customer->id
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to start traffic monitoring", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai monitoring: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop real-time traffic monitoring untuk customer
     */
    public function stopTrafficMonitoring(Customer $customer)
    {
        try {
            $cacheKey = "traffic_monitoring_{$customer->id}";
            \Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => 'Traffic monitoring dihentikan.',
                'customer_id' => $customer->id
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to stop traffic monitoring", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghentikan monitoring: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Broadcast traffic data untuk customer tertentu
     */
    protected function broadcastTrafficData(Customer $customer)
    {
        try {
            $mikrotik = new MikrotikService($customer->router);
            $interfaceDetail = $mikrotik->getPPPoEInterfaceDetail($customer->customer_mikrotik_username);

            if ($interfaceDetail && isset($interfaceDetail['traffic']) && $interfaceDetail['traffic']) {
                event(new \App\Events\PPPoETrafficUpdated($customer->id, $interfaceDetail['traffic']));
            }
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast traffic data", [
                'customer' => $customer->customer_code,
                'error' => $e->getMessage()
            ]);
        }
    }
}
