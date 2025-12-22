<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeSkillsService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.api_key');
        $configuredModel = (string) config('services.anthropic.skills_model', '');
        $this->model = trim($configuredModel) !== '' ? $configuredModel : 'claude-sonnet-4.5-20250929';
    }

    public function generatePptx(array $slides, string $title, string $filename): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Anthropic API key not configured.');
        }

        $slidesJson = json_encode(['slides' => $slides], JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
Create a polished thesis defense PowerPoint using the provided JSON. Use a clean academic theme, 16:9 layout, and consistent typography. Keep slides concise and readable.

Title: {$title}

Slides JSON:
{$slidesJson}

Return a PPTX file named "{$filename}".
PROMPT;

        $response = Http::timeout(300)->withHeaders([
            'x-api-key' => $this->apiKey,
            'content-type' => 'application/json',
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'code-execution-2025-08-25,skills-2025-10-02',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'container' => [
                'skills' => [
                    [
                        'type' => 'anthropic',
                        'skill_id' => 'pptx',
                        'version' => 'latest',
                    ],
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'tools' => [
                [
                    'type' => 'code_execution_20250825',
                    'name' => 'code_execution',
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude Skills request failed: '.$response->body());
        }

        $payload = $response->json();
        $fileIds = $this->findFileIds($payload);

        if (empty($fileIds)) {
            Log::warning('Claude Skills response had no file IDs', [
                'response' => $payload,
            ]);
            throw new \RuntimeException('Claude Skills did not return a PPTX file.');
        }

        return [
            'file_id' => $fileIds[0],
            'response' => $payload,
        ];
    }

    public function downloadFile(string $fileId): string
    {
        $response = Http::timeout(300)->withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'files-api-2025-04-14',
        ])->get("https://api.anthropic.com/v1/files/{$fileId}/content");

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to download Claude Skills file: '.$response->body());
        }

        return $response->body();
    }

    private function findFileIds(array $payload): array
    {
        $fileIds = [];
        $walker = function ($value) use (&$walker, &$fileIds): void {
            if (is_array($value)) {
                if (isset($value['file_id']) && is_string($value['file_id'])) {
                    $fileIds[] = $value['file_id'];
                }
                foreach ($value as $item) {
                    $walker($item);
                }
            }
        };

        $walker($payload);

        return array_values(array_unique($fileIds));
    }
}
