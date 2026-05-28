<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Exception;

class TenantController extends Controller
{
    public function createTenant(Request $request)
    {
        // VALIDATION
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'subdomain' => 'required|string|max:50|alpha_num',
            'email'     => 'required|email',
            'phone'     => 'required',
            'password'  => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $subdomain = strtolower($request->subdomain);
        // sanitize db name (avoid weird chars)
        $dbName = 'tenant_' . preg_replace('/[^a-z0-9_]/', '_', $subdomain);

        DB::beginTransaction();
        try {
            // ensure tenants table exists and subdomain/email unique
            $exists = DB::table('tenants')->where('subdomain', $subdomain)->orWhere('email', $request->email)->exists();
            if ($exists) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'The subdomain or email has already been taken.'
                ], 422);
            }

            // insert in central tenants table
            $tenantId = DB::table('tenants')->insertGetId([
                'name'       => $request->name,
                'subdomain'  => $subdomain,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => bcrypt($request->password),
                'database'   => $dbName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // create database for tenant
            $connection = config('database.default'); // e.g. 'mysql' or 'pgsql'
            $driver = config("database.connections.{$connection}.driver");

            if ($driver === 'pgsql') {
                // For postgres: CREATE DATABASE requires a DB user with CREATEDB privilege.
                DB::statement("CREATE DATABASE \"{$dbName}\"");
            } else {
                // assume mysql-compatible
                DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            }

            // Configure tenant connection at runtime
            $tenantConnection = [
                'driver'    => config("database.connections.{$connection}.driver"),
                'host'      => config("database.connections.{$connection}.host"),
                'port'      => config("database.connections.{$connection}.port"),
                'database'  => $dbName,
                'username'  => config("database.connections.{$connection}.username"),
                'password'  => config("database.connections.{$connection}.password"),
                'charset'   => config("database.connections.{$connection}.charset", 'utf8mb4'),
                'collation' => config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci'),
                'prefix'    => '',
            ];

            config(['database.connections.tenant' => $tenantConnection]);
            // run tenant migrations (migrations placed in database/migrations/tenant)
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path'     => 'database/migrations/tenant',
                '--force'    => true,
            ]);

            DB::commit();

            $fullSubdomain = $subdomain . '.' . config('app.domain', 'billingmu.com');

            return response()->json([
                'status'    => 'success',
                'message'   => 'Tenant created successfully!',
                'tenant_id' => $tenantId,
                'subdomain' => $fullSubdomain
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Tenant creation error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
            ], 500);
        }
    }
}
