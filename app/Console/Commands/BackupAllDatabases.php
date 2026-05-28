<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupAllDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:all';

    protected $description = 'Backup master database, all tenant databases, and evolution_api';

    public function handle()
    {
        $this->info('Starting full database backup...');

        $backupName = config('backup.backup.name', 'KITABILL_SUPERADMIN');
        $date = now()->format('Y-m-d-H-i-s');
        $tempDir = storage_path('app/backup-temp/' . $date);
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $databases = $this->getDatabasesToBackup();
        $this->info('Found ' . count($databases) . ' databases to backup.');

        $dbPassword = env('DB_PASSWORD', 'isp_password_2024');
        $dbUser = env('DB_USERNAME', 'isp_user');
        $dbHost = env('DB_HOST', '127.0.0.1');

        foreach ($databases as $db) {
            $this->info("Dumping database: {$db}...");
            $fileName = "{$db}.sql";
            $filePath = "{$tempDir}/{$fileName}";
            
            // Use pg_dump for PostgreSQL
            $command = "PGPASSWORD='{$dbPassword}' pg_dump -h {$dbHost} -U {$dbUser} {$db} > {$filePath}";
            
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $this->error("Failed to dump {$db}");
            }
        }

        // Zip the temp directory
        $zipName = "{$date}.zip";
        $zipPath = storage_path("app/private/{$backupName}/{$zipName}");
        
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $this->info('Creating zip archive...');
        $zipCommand = "cd {$tempDir} && zip -r {$zipPath} .";
        exec($zipCommand, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Backup completed successfully: {$zipName}");
            // Set permissions
            chmod($zipPath, 0775);
            // Cleanup temp
            exec("rm -rf {$tempDir}");
        } else {
            $this->error('Failed to create zip archive');
        }

        return $returnVar;
    }

    private function getDatabasesToBackup()
    {
        $masterDb = config('database.connections.pgsql.database', 'isp_manager');
        $evolutionDb = 'evolution_api';
        
        // Get all tenant databases from pg_database
        $tenantDbsOutput = [];
        exec("sudo -u postgres psql -t -c \"SELECT datname FROM pg_database WHERE datname LIKE 'tenant_%';\"", $tenantDbsOutput);
        
        $tenantDbs = array_map('trim', $tenantDbsOutput);
        $tenantDbs = array_filter($tenantDbs);

        return array_unique(array_merge([$masterDb, $evolutionDb], $tenantDbs));
    }
}
