<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DataCleanupService
{
    public function tableSummaries(): array
    {
        $tables = $this->tableNames();
        $locked = $this->lockedTables($tables);

        $summaries = [];
        foreach ($tables as $table) {
            $summaries[] = [
                'name' => $table,
                'count' => $this->tableCount($table),
                'locked' => in_array($table, $locked, true),
            ];
        }

        return $summaries;
    }

    public function userStats(): array
    {
        $superAdminRole = config('admin_cleanup.super_admin_role', 'super_admin');
        $superAdminIds = User::role($superAdminRole)->pluck('id');
        $totalUsers = DB::table('users')->count();
        $superAdminCount = $superAdminIds->count();
        $deletableCount = DB::table('users')
            ->when($superAdminIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $superAdminIds))
            ->count();

        return [
            'total' => $totalUsers,
            'super_admins' => $superAdminCount,
            'deletable' => $deletableCount,
        ];
    }

    public function purge(array $tables, User $admin): array
    {
        $tables = array_values(array_unique($tables));
        $existing = $this->tableNames();
        $locked = $this->lockedTables($existing);
        $purgeable = array_values(array_diff($existing, $locked));
        $invalid = array_values(array_diff($tables, $purgeable));

        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'tables' => 'Some selected tables are locked or invalid: '.implode(', ', $invalid),
            ]);
        }

        $deletedCounts = [];
        $connection = DB::connection();

        $this->disableForeignKeyChecks($connection);

        try {
            foreach ($tables as $table) {
                if ($table === 'users') {
                    $deletedCounts[$table] = $this->purgeUsers();
                    continue;
                }

                $deletedCounts[$table] = $this->tableCount($table);
                DB::table($table)->delete();
            }
        } finally {
            $this->enableForeignKeyChecks($connection);
        }

        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'data_cleanup',
            'description' => 'Admin data cleanup executed',
            'metadata' => [
                'tables' => $tables,
                'deleted_counts' => $deletedCounts,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $deletedCounts;
    }

    public function lockedTables(array $existingTables = []): array
    {
        $locked = config('admin_cleanup.locked_tables', []);
        $permissionTables = array_values(config('permission.table_names', []));
        $locked = array_values(array_unique(array_merge($locked, $permissionTables)));

        if (! empty($existingTables)) {
            $locked = array_values(array_intersect($locked, $existingTables));
        }

        return $locked;
    }

    private function purgeUsers(): int
    {
        $superAdminRole = config('admin_cleanup.super_admin_role', 'super_admin');
        $superAdminIds = User::role($superAdminRole)->pluck('id');

        if ($superAdminIds->isEmpty()) {
            throw ValidationException::withMessages([
                'tables' => 'No super_admin users found. Aborting to avoid deleting all users.',
            ]);
        }

        $query = DB::table('users')->whereNotIn('id', $superAdminIds);
        $count = $query->count();
        $query->delete();

        return $count;
    }

    private function tableNames(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => $this->sqliteTables($connection),
            'pgsql' => $this->postgresTables($connection),
            default => $this->mysqlTables($connection),
        };
    }

    private function mysqlTables(Connection $connection): array
    {
        $results = $connection->select('SHOW TABLES');
        $tables = [];

        foreach ($results as $row) {
            $values = array_values((array) $row);
            $tables[] = $values[0] ?? null;
        }

        $tables = array_values(array_filter($tables));
        sort($tables);

        return $tables;
    }

    private function postgresTables(Connection $connection): array
    {
        $results = $connection->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $tables = array_map(fn ($row) => $row->tablename, $results);
        sort($tables);

        return $tables;
    }

    private function sqliteTables(Connection $connection): array
    {
        $results = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table'");
        $tables = array_map(fn ($row) => $row->name, $results);
        $tables = array_values(array_filter($tables, fn ($name) => $name !== 'sqlite_sequence'));
        sort($tables);

        return $tables;
    }

    private function tableCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }

    private function disableForeignKeyChecks(Connection $connection): void
    {
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF');
            return;
        }

        if ($driver === 'pgsql') {
            $connection->statement('SET session_replication_role = replica');
            return;
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
    }

    private function enableForeignKeyChecks(Connection $connection): void
    {
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = ON');
            return;
        }

        if ($driver === 'pgsql') {
            $connection->statement('SET session_replication_role = DEFAULT');
            return;
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
