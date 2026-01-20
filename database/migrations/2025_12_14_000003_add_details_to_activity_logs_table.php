<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'route')) {
                $table->string('route', 190)->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('activity_logs', 'method')) {
                $table->string('method', 16)->nullable()->after('route');
            }
            if (! Schema::hasColumn('activity_logs', 'url')) {
                $table->string('url', 500)->nullable()->after('method');
            }
            if (! Schema::hasColumn('activity_logs', 'status_code')) {
                $table->unsignedSmallInteger('status_code')->nullable()->after('url');
            }
            if (! Schema::hasColumn('activity_logs', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('status_code');
            }
            if (! Schema::hasColumn('activity_logs', 'request_id')) {
                $table->string('request_id', 64)->nullable()->after('duration_ms');
            }

            $table->index(['route', 'method'], 'activity_logs_route_method_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'request_id')) {
                $table->dropIndex('activity_logs_route_method_idx');
                $table->dropColumn(['route', 'method', 'url', 'status_code', 'duration_ms', 'request_id']);
            }
        });
    }
};
