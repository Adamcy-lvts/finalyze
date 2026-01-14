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
        Schema::table('document_citations', function (Blueprint $table) {
            $table->foreignId('collected_paper_id')
                ->nullable()
                ->constrained('collected_papers')
                ->nullOnDelete()
                ->after('citation_id');
            $table->boolean('is_whitelisted')->default(false)->after('collected_paper_id');
            $table->string('whitelist_key')->nullable()->after('is_whitelisted');

            $table->index(['chapter_id', 'is_whitelisted'], 'doc_citations_chapter_whitelist_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_citations', function (Blueprint $table) {
            $table->dropIndex('doc_citations_chapter_whitelist_idx');
            $table->dropConstrainedForeignId('collected_paper_id');
            $table->dropColumn(['is_whitelisted', 'whitelist_key']);
        });
    }
};
