<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();

        $defaults = [
            'ai.global_system_prompt' => [
                'value' => 'You are a helpful, careful academic AI assistant. Follow the user instructions and maintain a formal academic tone. Prefer the bullet symbol (•) for unordered lists unless explicitly requested otherwise. Never use "&" — always write "and".',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Global system prompt applied to most AI features (chapter writing, chat, etc.).',
            ],
            'ai.chat_system_prompt' => [
                'value' => 'You are an academic writing assistant helping a student with their research project. Be supportive, specific, and actionable. Keep responses concise unless the user requests more detail.',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Chat-specific system prompt (merged with the global system prompt).',
            ],
            'ai.editor_system_prompt' => [
                'value' => 'You are an expert academic editor. Follow the user instructions precisely. Preserve meaning, improve clarity, and maintain an academic tone appropriate to the provided context. Output only what the user asked for (no extra commentary).',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Editor system prompt used for rephrase/expand/improve actions (merged with the global system prompt).',
            ],
            'ai.analysis_system_prompt' => [
                'value' => 'You are an expert academic writing evaluator. Return exactly the requested JSON format with no additional commentary, markdown, or code fences.',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Analysis system prompt used for scoring/evaluation (merged with the global system prompt).',
            ],
        ];

        foreach ($defaults as $key => $row) {
            $exists = DB::table('system_settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('system_settings')->insert([
                'key' => $key,
                // JSON column: store as JSON string
                'value' => json_encode($row['value'], JSON_UNESCAPED_UNICODE),
                'type' => $row['type'],
                'group' => $row['group'],
                'description' => $row['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->whereIn('key', [
            'ai.global_system_prompt',
            'ai.chat_system_prompt',
            'ai.editor_system_prompt',
            'ai.analysis_system_prompt',
        ])->delete();
    }
};

