<?php

namespace App\Services\AI;

use App\Models\Project;
use App\Models\SystemSetting;

class SystemPromptService
{
    private const DEFAULT_GLOBAL_PROMPT = 'You are a helpful, careful academic AI assistant. Follow the user instructions and maintain a formal academic tone. Prefer the bullet symbol (•) for unordered lists unless explicitly requested otherwise. Never use "&" — always write "and".';

    private const DEFAULT_CHAT_PROMPT = 'You are an academic writing assistant helping a student with their research project. Be supportive, specific, and actionable. Keep responses concise unless the user requests more detail.';

    private const DEFAULT_EDITOR_PROMPT = 'You are an expert academic editor. Follow the user instructions precisely. Preserve meaning, improve clarity, and maintain an academic tone appropriate to the provided context. Output only what the user asked for (no extra commentary).';

    private const DEFAULT_ANALYSIS_PROMPT = 'You are an expert academic writing evaluator. Return exactly the requested JSON format with no additional commentary, markdown, or code fences.';

    public function getGlobalSystemPrompt(): string
    {
        return $this->getSettingText('ai.global_system_prompt', self::DEFAULT_GLOBAL_PROMPT);
    }

    public function getChatSystemPrompt(): string
    {
        $chat = $this->getSettingText('ai.chat_system_prompt', self::DEFAULT_CHAT_PROMPT);

        return $this->merge([$this->getGlobalSystemPrompt(), $chat]);
    }

    public function getEditorSystemPrompt(): string
    {
        $editor = $this->getSettingText('ai.editor_system_prompt', self::DEFAULT_EDITOR_PROMPT);

        return $this->merge([$this->getGlobalSystemPrompt(), $editor]);
    }

    public function getAnalysisSystemPrompt(): string
    {
        $analysis = $this->getSettingText('ai.analysis_system_prompt', self::DEFAULT_ANALYSIS_PROMPT);

        return $this->merge([$this->getGlobalSystemPrompt(), $analysis]);
    }

    /**
     * Build a chapter-generation system prompt by merging the global prompt
     * with the resolved faculty/template system prompt.
     */
    public function getChapterSystemPrompt(Project $project, string $templateSystemPrompt): string
    {
        return $this->merge([$this->getGlobalSystemPrompt(), $templateSystemPrompt]);
    }

    private function getSettingText(string $key, string $default): string
    {
        $setting = SystemSetting::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_array($value)) {
            $text = $value['text'] ?? $value['prompt'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return $default;
    }

    private function merge(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $text = is_string($part) ? trim($part) : '';
            if ($text !== '') {
                $clean[] = $text;
            }
        }

        return implode("\n\n", $clean);
    }
}

