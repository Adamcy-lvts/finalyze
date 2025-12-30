<?php

namespace App\Services\Defense;

use Illuminate\Support\Facades\Log;

class PptxGenJsService
{
    public function export(array $slides, string $title, string $filename): string
    {
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $payload = [
            'title' => $title,
            'slides' => $slides,
        ];

        $inputPath = $tempDir.'/deck_'.uniqid().'.json';
        $outputPath = $tempDir.'/'.$filename;

        file_put_contents($inputPath, json_encode($payload, JSON_UNESCAPED_SLASHES));

        $nodeBinary = config('services.pptx.node_binary', 'node');
        $scriptPath = base_path('scripts/pptx/export-defense-deck.mjs');

        $command = sprintf(
            '%s %s --input %s --output %s',
            escapeshellcmd($nodeBinary),
            escapeshellarg($scriptPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        $output = [];
        $returnCode = 0;
        exec($command.' 2>&1', $output, $returnCode);

        if (file_exists($inputPath)) {
            unlink($inputPath);
        }

        if ($returnCode !== 0 || ! file_exists($outputPath)) {
            Log::error('PPTX export failed', [
                'command' => $command,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);

            throw new \RuntimeException('PPTX export failed.');
        }

        return $outputPath;
    }
}
