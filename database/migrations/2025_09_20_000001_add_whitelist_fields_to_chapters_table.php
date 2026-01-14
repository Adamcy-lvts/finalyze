<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->json('injected_paper_ids')->nullable()->after('summary');
            $table->json('citation_whitelist')->nullable()->after('injected_paper_ids');
            $table->timestamp('citations_validated_at')->nullable()->after('citation_whitelist');
            $table->integer('citation_violations_count')->default(0)->after('citations_validated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn([
                'injected_paper_ids',
                'citation_whitelist',
                'citations_validated_at',
                'citation_violations_count',
            ]);
        });
    }
};
