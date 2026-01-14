<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use App\Services\TiptapToHtmlService;

class ExportService
{
    public function __construct(
        protected ProjectPrelimService $projectPrelimService,
        protected ChapterReferenceService $chapterReferenceService
    ) {}

    /**
     * Normalize stored chapter content into HTML suitable for Pandoc.
     * - If already HTML, return as-is.
     * - If Markdown/plain text, convert to HTML.
     * - If unknown (e.g. JSON), fall back to escaped preformatted text.
     */
    private function normalizeContentToHtml(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '<')) {
            return $content;
        }

        // If it looks like JSON, try rendering it as a Tiptap document.
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (($decoded['type'] ?? null) || ($decoded['content'] ?? null)) {
                return app(TiptapToHtmlService::class)->convert($trimmed);
            }

            return '<pre>'.htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</pre>';
        }

        // Markdown/plain-text to HTML
        return Str::markdown($content);
    }

    /**
     * Check if Pandoc is available on the system
     */
    private function isPandocAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('pandoc --version 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            Log::debug('Pandoc is not available', [
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);
        }

        return $returnCode === 0;
    }

    private function findLibreOfficeBinary(): ?string
    {
        $candidates = [];

        $output = [];
        $returnCode = 0;
        exec('command -v soffice 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && ! empty($output[0])) {
            $candidates[] = trim((string) $output[0]);
        }

        $output = [];
        $returnCode = 0;
        exec('command -v libreoffice 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && ! empty($output[0])) {
            $candidates[] = trim((string) $output[0]);
        }

        $candidates = array_merge($candidates, [
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/lib/libreoffice/program/soffice',
            '/opt/libreoffice/program/soffice',
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function isLibreOfficeAvailable(): bool
    {
        $bin = $this->findLibreOfficeBinary();
        if (! $bin) {
            Log::debug('LibreOffice is not available; falling back to Pandoc', [
                'return_code' => 127,
                'output' => 'LibreOffice binary not found',
            ]);

            return false;
        }

        $output = [];
        $returnCode = 0;
        exec(escapeshellarg($bin).' --version 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            Log::debug('LibreOffice is not available; falling back to Pandoc', [
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);
        }

        return $returnCode === 0;
    }

    /**
     * Convert HTML to DOCX using LibreOffice (HTML import tends to respect page breaks and layout better than Pandoc).
     */
    private function convertWithLibreOffice(string $html, string $outputPath, array $metadata = []): bool
    {
        try {
            $bin = $this->findLibreOfficeBinary();
            if (! $bin) {
                return false;
            }

            $tempDir = rtrim(sys_get_temp_dir(), '/').'/finalyze-export';
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $jobDir = $tempDir.'/word_export_'.uniqid();
            if (! is_dir($jobDir)) {
                mkdir($jobDir, 0755, true);
            }

            $tempHtmlFile = $jobDir.'/export.html';

            $fullHtml = $this->prepareHtmlForPandoc($html, $metadata);
            $fullHtml = $this->normalizeHtmlForLibreOffice($fullHtml);
            $fullHtml = $this->prepareAssetsForLibreOffice($fullHtml, $jobDir);

            file_put_contents($tempHtmlFile, $fullHtml);

            $inputUrl = 'file://'.$tempHtmlFile;
            $profileDir = $jobDir.'/lo-profile';
            if (! is_dir($profileDir)) {
                mkdir($profileDir, 0755, true);
            }

            $command = sprintf(
                '%s --headless --norestore --nodefault --nolockcheck --nofirststartwizard -env:UserInstallation=%s --convert-to %s --outdir %s %s 2>&1',
                escapeshellarg($bin),
                escapeshellarg('file://'.$profileDir),
                escapeshellarg('docx:MS Word 2007 XML'),
                escapeshellarg($jobDir),
                escapeshellarg($inputUrl),
            );

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            $generated = $jobDir.'/export.docx';
            if ($returnCode !== 0 || ! file_exists($generated)) {
                Log::warning('LibreOffice conversion failed', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode,
                ]);

                File::deleteDirectory($jobDir);

                return false;
            }

            File::move($generated, $outputPath);
            File::deleteDirectory($jobDir);

            if (! $this->validateAndRepairDocx($outputPath)) {
                Log::warning('LibreOffice produced an invalid DOCX (failed validation/repair)', [
                    'output_file' => $outputPath,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('LibreOffice conversion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Convert HTML to DOCX using Pandoc for superior quality
     */
    private function convertWithPandoc(string $html, string $outputPath, array $metadata = []): bool
    {
        try {
            // Create temp directory for conversion
            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $jobDir = $tempDir.'/word_export_'.uniqid();
            if (! is_dir($jobDir)) {
                mkdir($jobDir, 0755, true);
            }

            // Create temporary HTML file
            $tempHtmlFile = $jobDir.'/export.html';

            // Prepare HTML with metadata and proper structure
            $fullHtml = $this->prepareHtmlForPandoc($html, $metadata);
            $fullHtml = $this->prepareAssetsForPandoc($fullHtml, $jobDir);

            // Write HTML to temp file
            file_put_contents($tempHtmlFile, $fullHtml);

            // Get reference template path
            $referenceDoc = resource_path('templates/reference.docx');
            $referenceOption = file_exists($referenceDoc) ? '--reference-doc='.escapeshellarg($referenceDoc) : '';

            $resourcePath = implode(':', array_filter([
                $jobDir,
                storage_path('app/public'),
                public_path(),
            ]));

            // Build Pandoc command with enhanced options
            $command = sprintf(
                'pandoc %s -f html -t docx -o %s %s --resource-path=%s --standalone 2>&1',
                escapeshellarg($tempHtmlFile),
                escapeshellarg($outputPath),
                $referenceOption,
                escapeshellarg($resourcePath)
            );

            // Execute Pandoc
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            // Clean up job directory
            if (is_dir($jobDir)) {
                File::deleteDirectory($jobDir);
            }

            if ($returnCode !== 0) {
                Log::warning('Pandoc conversion failed', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode,
                ]);

                return false;
            }

            if (! $this->validateAndRepairDocx($outputPath)) {
                Log::warning('Pandoc produced an invalid DOCX (failed validation/repair)', [
                    'output_file' => $outputPath,
                ]);

                return false;
            }

            Log::info('Pandoc conversion successful', [
                'output_file' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Pandoc conversion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Prepare embedded assets (images + mermaid diagrams) so Pandoc can include them in DOCX.
     * - Rewrites <img src="/storage/..."> to an absolute filesystem path inside the container.
     * - Extracts base64 images into files.
     * - Renders Mermaid blocks into PNGs using Puppeteer + Mermaid (node script).
     */
    private function prepareAssetsForPandoc(string $html, string $jobDir): string
    {
        $html = $this->normalizeMermaidBlocks($html);
        $html = $this->renderMermaidBlocksToImages($html, $jobDir);
        $html = $this->rewriteImageSourcesForPandoc($html, $jobDir);

        return $html;
    }

    private function prepareAssetsForLibreOffice(string $html, string $jobDir): string
    {
        $html = $this->normalizeMermaidBlocks($html);
        $html = $this->renderMermaidBlocksToImages($html, $jobDir);
        $html = $this->rewriteImageSourcesForLibreOffice($html, $jobDir);

        return $html;
    }

    private function normalizeMermaidBlocks(string $html): string
    {
        // Convert data-mermaid blocks into <div class="mermaid">code</div>
        $html = preg_replace_callback(
            '/<div[^>]*data-mermaid[^>]*data-mermaid-code="([^"]*)"[^>]*>.*?<\/div>/s',
            function ($matches) {
                $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');

                return '<div class="mermaid">'."\n".$code."\n".'</div>';
            },
            $html
        ) ?? $html;

        // Convert <pre><code class="...language-mermaid..."> into <div class="mermaid">
        $html = preg_replace_callback(
            '/<pre[^>]*>\s*<code[^>]*class="[^"]*language-mermaid[^"]*"[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/s',
            function ($matches) {
                $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                $code = strip_tags($code);

                return '<div class="mermaid">'."\n".trim($code)."\n".'</div>';
            },
            $html
        ) ?? $html;

        return $html;
    }

    private function renderMermaidBlocksToImages(string $html, string $jobDir): string
    {
        $assetsDir = $jobDir.'/assets';
        if (! is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        $pattern = '/<div[^>]*class=["\'][^"\']*mermaid[^"\']*["\'][^>]*>([\s\S]*?)<\/div>/i';

        return preg_replace_callback($pattern, function ($matches) use ($assetsDir) {
            $raw = $matches[1] ?? '';
            $code = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
            $code = strip_tags($code);
            $code = trim($code);

            if ($code === '') {
                return '';
            }

            $hash = substr(sha1($code), 0, 12);
            $outputPath = $assetsDir."/mermaid_{$hash}.png";

            if (! file_exists($outputPath)) {
                $ok = $this->renderMermaidToPng($code, $outputPath);
                if (! $ok) {
                    Log::warning('Failed to render Mermaid diagram for DOCX export; keeping as code block');

                    return '<pre><code>'.htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</code></pre>';
                }
            }

            return '<p><img src="'.htmlspecialchars($outputPath, ENT_QUOTES | ENT_HTML5, 'UTF-8').'" style="max-width: 100%;"/></p>';
        }, $html) ?? $html;
    }

    private function findChromePathForBrowsershot(): ?string
    {
        $candidates = [
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function renderMermaidToPng(string $code, string $outputPath): bool
    {
        $chromePath = $this->findChromePathForBrowsershot();
        if (! $chromePath) {
            Log::warning('Chrome not found; cannot render Mermaid diagrams for DOCX export');

            return false;
        }

        $mermaidJs = base_path('node_modules/mermaid/dist/mermaid.min.js');
        if (! file_exists($mermaidJs)) {
            Log::warning('Mermaid JS not found; cannot render Mermaid diagrams for DOCX export', [
                'path' => $mermaidJs,
            ]);

            return false;
        }

        $mermaidScript = file_get_contents($mermaidJs);
        if (! is_string($mermaidScript) || $mermaidScript === '') {
            Log::warning('Failed to read Mermaid JS; cannot render Mermaid diagrams for DOCX export', [
                'path' => $mermaidJs,
            ]);

            return false;
        }

        $html = <<<HTML
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <style>
      body { margin: 0; padding: 0; background: white; }
      .wrap { padding: 16px; }
    </style>
    <script>{$mermaidScript}</script>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        if (window.mermaid) {
          window.mermaid.initialize({ startOnLoad: true, theme: 'default', securityLevel: 'strict' });
        }
      });
    </script>
  </head>
  <body>
    <div class="wrap">
      <div class="mermaid">{$this->escapeBareAmpersands(htmlspecialchars($code, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'))}</div>
    </div>
  </body>
</html>
HTML;

        try {
            Browsershot::html($html)
                ->setChromePath($chromePath)
                ->windowSize(1400, 900)
                ->deviceScaleFactor(2)
                ->noSandbox()
                ->addChromiumArguments([
                    'disable-dev-shm-usage',
                    'disable-gpu',
                    'no-zygote',
                    'single-process',
                    'disable-crashpad',
                    'disable-breakpad',
                ])
                ->setDelay(1500)
                ->select('.mermaid')
                ->save($outputPath);

            return file_exists($outputPath) && filesize($outputPath) > 100;
        } catch (\Throwable $e) {
            Log::warning('Mermaid render failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function rewriteImageSourcesForPandoc(string $html, string $jobDir): string
    {
        $assetsDir = $jobDir.'/assets';
        if (! is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        return preg_replace_callback(
            '/<img([^>]*)\ssrc=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) use ($assetsDir) {
                $beforeSrc = $matches[1];
                $src = $matches[2];
                $afterSrc = $matches[3];

                $resolved = $this->resolveImageSrcForPandoc($src, $assetsDir);

                return '<img'.$beforeSrc.' src="'.htmlspecialchars($resolved, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"'.$afterSrc.'>';
            },
            $html
        ) ?? $html;
    }

    private function rewriteImageSourcesForLibreOffice(string $html, string $jobDir): string
    {
        $assetsDir = $jobDir.'/assets';
        if (! is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        return preg_replace_callback(
            '/<img([^>]*)\ssrc=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) use ($assetsDir) {
                $beforeSrc = $matches[1];
                $src = $matches[2];
                $afterSrc = $matches[3];

                $resolved = $this->resolveImageSrcForLibreOffice($src, $assetsDir);

                return '<img'.$beforeSrc.' src="'.htmlspecialchars($resolved, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"'.$afterSrc.'>';
            },
            $html
        ) ?? $html;
    }

    private function resolveImageSrcForPandoc(string $src, string $assetsDir): string
    {
        $src = trim($src);
        if ($src === '') {
            return $src;
        }

        // Keep remote images as-is (Pandoc may fetch them if allowed).
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }

        // Extract base64 images into files.
        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $src, $m)) {
            $mime = strtolower($m[1]);
            $data = base64_decode($m[2], true);
            if ($data === false) {
                return $src;
            }

            $ext = match ($mime) {
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg',
                'image/webp' => 'webp',
                default => 'png',
            };

            $file = $assetsDir.'/img_'.substr(sha1($src), 0, 12).'.'.$ext;
            if (! file_exists($file)) {
                file_put_contents($file, $data);
            }

            return $file;
        }

        // /storage/... maps to storage/app/public/...
        if (str_starts_with($src, '/storage/')) {
            $relative = substr($src, strlen('/storage/'));
            $path = storage_path('app/public/'.$relative);

            return file_exists($path) ? $path : $src;
        }

        // Already a filesystem path
        if (str_starts_with($src, '/')) {
            return $src;
        }

        // Relative path (try public/ first)
        $publicPath = public_path($src);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        return $src;
    }

    private function resolveImageSrcForLibreOffice(string $src, string $assetsDir): string
    {
        $src = trim($src);
        if ($src === '') {
            return $src;
        }

        if (str_starts_with($src, 'data:image/')) {
            return $src;
        }

        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }

        $resolved = $this->resolveImageSrcForPandoc($src, $assetsDir);

        if (str_starts_with($resolved, 'http://') || str_starts_with($resolved, 'https://')) {
            return $resolved;
        }

        if (str_starts_with($resolved, 'file://')) {
            return $resolved;
        }

        $dataUri = $this->fileToDataUri($resolved);
        if ($dataUri !== null) {
            return $dataUri;
        }

        return $this->toFileUrl($resolved);
    }

    private function toFileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return 'file://'.$path;
    }

    private function fileToDataUri(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $data = file_get_contents($path);
        if (! is_string($data) || $data === '') {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }

    /**
     * Prepare HTML for optimal Pandoc conversion
     */
    private function prepareHtmlForPandoc(string $html, array $metadata = []): string
    {
        // Build HTML document with proper structure
        $title = $metadata['title'] ?? 'Document';
        $author = $metadata['author'] ?? '';

        $htmlDocument = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
HTML;

        if ($author) {
            $htmlDocument .= "\n    <meta name=\"author\" content=\"{$author}\">";
        }

        $htmlDocument .= <<<'HTML'

    <style>
        .page-break {
            page-break-after: always;
            break-after: page;
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 2.0;
        }
        h1, h2, h3, h4, h5, h6 {
            font-weight: bold;
            margin-top: 1em;
            margin-bottom: 0.5em;
        }
        h1 { font-size: 14pt; text-align: center; }
        h2 { font-size: 13pt; }
        h3 { font-size: 12pt; }
        p {
            text-align: justify;
            margin-bottom: 1em;
        }
        blockquote {
            margin-left: 1in;
            margin-right: 1in;
            font-style: italic;
        }
        pre, code {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            background-color: #f4f4f4;
        }
        table {
            border-collapse: collapse;
            margin: 1em auto;
        }
        table, th, td {
            border: 1px solid black;
            padding: 0.5em;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
HTML;

        // Clean and enhance the content HTML
        $cleanedHtml = $this->preprocessHtmlForPandoc($html);

        $htmlDocument .= "\n{$cleanedHtml}\n</body>\n</html>";

        return $this->sanitizeForDocx($htmlDocument);
    }

    /**
     * Preprocess HTML content for better Pandoc conversion
     */
    private function preprocessHtmlForPandoc(string $html): string
    {
        $html = $this->sanitizeForDocx($html);

        // CRITICAL: Escape bare ampersands to prevent invalid XML/HTML.
        // This fixes issues with & in text and attribute values (e.g. "A & B", URLs with query params).
        $html = $this->escapeBareAmpersands($html);

        // Don't strip all styles - Pandoc handles them well
        // Just clean up problematic patterns

        // Remove data-* attributes that might confuse Pandoc
        $html = preg_replace('/\s*data-[a-z-]+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Preserve important inline styles (colors, backgrounds)
        // but remove position/display/width CSS that doesn't translate to Word
        $html = preg_replace_callback('/style\s*=\s*["\']([^"\']*)["\']/', function ($matches) {
            $style = $matches[1];

            // Keep only Word-relevant CSS properties
            $allowedProps = [
                'color',
                'background-color',
                'font-size',
                'font-weight',
                'font-style',
                'text-align',
                // Layout + structure (critical for page breaks and reference indentation)
                'page-break-after',
                'page-break-before',
                'break-after',
                'break-before',
                'margin',
                'margin-left',
                'margin-right',
                'margin-top',
                'margin-bottom',
                'text-indent',
                'line-height',
                'page-break-inside',
                'break-inside',
            ];
            $styleParts = explode(';', $style);
            $filteredParts = [];

            foreach ($styleParts as $part) {
                $part = trim($part);
                if (empty($part)) {
                    continue;
                }

                foreach ($allowedProps as $prop) {
                    if (stripos($part, $prop) === 0) {
                        $filteredParts[] = $part;
                        break;
                    }
                }
            }

            if (empty($filteredParts)) {
                return '';
            }

            return 'style="'.implode('; ', $filteredParts).'"';
        }, $html);

        // Convert Tiptap-specific code blocks to standard pre/code
        $html = preg_replace('/<pre[^>]*><code[^>]*class="language-([^"]*)"[^>]*>(.*?)<\/code><\/pre>/is', '<pre><code class="$1">$2</code></pre>', $html);

        // Ensure tables have proper structure
        $html = preg_replace('/<table[^>]*>/', '<table>', $html);

        // Convert <strong> and <em> properly
        $html = str_replace(['<strong>', '</strong>'], ['<b>', '</b>'], $html);
        $html = str_replace(['<em>', '</em>'], ['<i>', '</i>'], $html);

        return $html;
    }

    private function normalizeHtmlForLibreOffice(string $html): string
    {
        $html = str_replace(['&nbsp;', '&#160;', "\xc2\xa0"], ' ', $html);
        $html = preg_replace('/[ \t]{2,}/', ' ', $html) ?? $html;

        return $html;
    }

    /**
     * Remove characters that are invalid in XML 1.0 (DOCX is XML-based).
     */
    private function sanitizeForDocx(string $text): string
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        // Ensure valid UTF-8 and drop invalid byte sequences
        $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;

        // Strip invalid XML 1.0 characters (keep TAB, LF, CR)
        $text = preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            $text
        );

        return $text ?? '';
    }

    /**
     * Escape bare ampersands without double-escaping existing HTML entities.
     */
    private function escapeBareAmpersands(string $html): string
    {
        return preg_replace('/&(?![a-zA-Z]+;|#\d+;|#x[0-9A-Fa-f]+;)/', '&amp;', $html) ?? $html;
    }

    private function isValidDocx(string $path): bool
    {
        if (! file_exists($path) || filesize($path) < 512) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle) {
            $signature = fread($handle, 4) ?: '';
            fclose($handle);
            // DOCX is a zip; should start with "PK".
            if (! str_starts_with($signature, "PK")) {
                return false;
            }
        }

        if (! class_exists(\ZipArchive::class)) {
            // Can't validate without zip support; assume ok.
            return true;
        }

        $zip = new \ZipArchive();
        $result = $zip->open($path);
        if ($result !== true) {
            return false;
        }

        $required = ['[Content_Types].xml', '_rels/.rels'];
        foreach ($required as $requiredEntry) {
            if ($zip->locateName($requiredEntry) === false) {
                $zip->close();

                return false;
            }
        }

        $xmlEntriesToValidate = [
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/styles.xml',
            'word/_rels/document.xml.rels',
            'docProps/core.xml',
            'docProps/app.xml',
        ];

        foreach ($xmlEntriesToValidate as $entry) {
            $index = $zip->locateName($entry);
            if ($index === false) {
                continue;
            }

            $xml = $zip->getFromIndex($index);
            if (! is_string($xml) || $xml === '') {
                $zip->close();

                return false;
            }

            if (! $this->isWellFormedXml($xml)) {
                $zip->close();

                return false;
            }
        }

        $zip->close();

        return true;
    }

    private function isWellFormedXml(string $xml): bool
    {
        if ($xml === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok && empty($errors);
    }

    /**
     * DOCX conversion can emit invalid WordprocessingML (notably bare '&' in <w:t>),
     * which makes the resulting DOCX unreadable by Word. Validate the output, attempt an in-place XML
     * repair if needed, then re-validate.
     */
    private function validateAndRepairDocx(string $path): bool
    {
        if ($this->isValidDocx($path)) {
            return true;
        }

        $repaired = $this->repairDocxXmlInPlace($path);

        if ($repaired) {
            Log::info('Attempted DOCX XML repair', ['path' => $path]);
        }

        return $this->isValidDocx($path);
    }

    private function escapeBareAmpersandsForXml(string $xml): string
    {
        return preg_replace('/&(?!((?:amp|lt|gt|apos|quot);|#\d+;|#x[0-9A-Fa-f]+;))/', '&amp;', $xml) ?? $xml;
    }

    private function repairDocxXmlInPlace(string $path): bool
    {
        if (! class_exists(\ZipArchive::class)) {
            return false;
        }

        $zip = new \ZipArchive();
        $result = $zip->open($path);
        if ($result !== true) {
            return false;
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = is_array($stat) ? ($stat['name'] ?? null) : null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (! (str_ends_with($name, '.xml') || str_ends_with($name, '.rels'))) {
                continue;
            }

            $entries[] = $name;
        }

        $changed = false;

        foreach ($entries as $name) {
            $xml = $zip->getFromName($name);
            if (! is_string($xml) || $xml === '') {
                continue;
            }

            $fixed = $this->sanitizeForDocx($xml);
            $fixed = $this->escapeBareAmpersandsForXml($fixed);

            if ($fixed === $xml) {
                continue;
            }

            if (! $this->isWellFormedXml($fixed)) {
                continue;
            }

            $zip->addFromString($name, $fixed);
            $changed = true;
        }

        $zip->close();

        return $changed;
    }

    /**
     * Remove References section from chapter HTML content
     * This is used when exporting full projects to avoid duplicate references
     */
    private function stripReferencesSection(string $html): string
    {
        // Pattern to match References section with various possible structures:
        // 1. <h1>REFERENCES</h1> or <h2>REFERENCES</h2>
        // 2. Followed by content until next heading or end of document
        // 3. May include div wrappers, class="references-section", etc.

        // Log original HTML length for debugging
        $originalLength = strlen($html);
        Log::debug('stripReferencesSection: Starting', ['original_length' => $originalLength]);

        // First, remove div.references-section wrapper if present (case insensitive)
        $html = preg_replace(
            '/<div[^>]*class=["\']?references-section["\']?[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove References heading and all content until next major heading or end
        // Match various heading levels (h1-h3) with "REFERENCES" or "REFERENCE" (case insensitive)
        $html = preg_replace(
            '/<h[123][^>]*>\s*REFERENCES?\s*<\/h[123]>.*?(?=<h[123]|$)/is',
            '',
            $html
        );

        // Also catch references sections that might be wrapped in other divs
        $html = preg_replace(
            '/<div[^>]*>\s*<h[123][^>]*>\s*REFERENCES?\s*<\/h[123]>.*?<\/div>/is',
            '',
            $html
        );

        // Remove any standalone references heading without content (edge case)
        $html = preg_replace(
            '/<h[123][^>]*>\s*REFERENCES?\s*<\/h[123]>/is',
            '',
            $html
        );

        $finalLength = strlen($html);
        Log::debug('stripReferencesSection: Complete', [
            'original_length' => $originalLength,
            'final_length' => $finalLength,
            'removed_bytes' => $originalLength - $finalLength,
        ]);

        return trim($html);
    }

    /**
     * Export entire project to Word document
     */
    public function exportToWord(Project $project): string
    {
        try {
            $preliminaryPages = $this->projectPrelimService->resolve($project);

            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $filename = $exportDir.'/'.$project->slug.'_'.time().'.docx';

            $fullHtml = $this->buildFullProjectHtml($project, $preliminaryPages);
            $metadata = [
                'title' => $project->title,
                'author' => $project->student_name ?: ($project->user->name ?? 'Unknown'),
            ];

            // Prefer LibreOffice for layout fidelity (page breaks + CSS).
            if ($this->isLibreOfficeAvailable()) {
                Log::info('Using LibreOffice for full project Word export', ['project_id' => $project->id]);

                if ($this->convertWithLibreOffice($fullHtml, $filename, $metadata)) {
                    Log::info('LibreOffice full project export successful');

                    return $filename;
                }

                Log::warning('LibreOffice failed for full project, falling back to Pandoc');
            }

            // Try Pandoc next
            if ($this->isPandocAvailable()) {
                Log::info('Using Pandoc for full project export', ['project_id' => $project->id]);

                if ($this->convertWithPandoc($fullHtml, $filename, $metadata)) {
                    Log::info('Pandoc full project export successful');

                    return $filename;
                }

                Log::warning('Pandoc failed for full project');
            }

            throw new \Exception('Export failed: LibreOffice and Pandoc are unavailable or failed');
        } catch (\Exception $e) {
            Log::error('Export to Word failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Build complete HTML for full project export (Pandoc)
     */
    private function buildFullProjectHtml(Project $project, array $preliminaryPages = []): string
    {
        $html = '';

        // Title page - escape all text content
        $html .= '<div style="text-align: center; margin-bottom: 3em;">';
        $html .= '<h1>'.htmlspecialchars(strtoupper($project->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
        $html .= '<p>BY</p>';
        $html .= '<p><strong>'.htmlspecialchars(strtoupper($project->student_name ?: ($project->user->name ?? 'AUTHOR NAME')), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong></p>';

        if ($project->abstract) {
            $html .= '<div style="margin-top: 2em;">';
            $html .= '<h2>ABSTRACT</h2>';
            $html .= '<p style="text-align: justify;">'.htmlspecialchars($project->abstract, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            $html .= '</div>';
        }

        if ($project->university) {
            $html .= '<p>'.htmlspecialchars(strtoupper($project->university), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        if ($project->field_of_study) {
            $html .= '<p>Department of '.htmlspecialchars($project->field_of_study, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        $html .= '<p>'.$project->created_at->format('F Y').'</p>';
        $html .= '</div>';
        $html .= '<div class="page-break"></div>';

        // Preliminary pages
        foreach ($preliminaryPages as $page) {
            $title = strtoupper($page['title'] ?? '');
            $content = $page['html'] ?? '';

            $html .= '<h1 style="text-align: center;">'.$title.'</h1>';
            $html .= $content;
            $html .= '<div class="page-break"></div>';
        }

        // Table of contents
        $html .= '<h1 style="text-align: center;">TABLE OF CONTENTS</h1>';

        $chapters = $project->chapters()->orderBy('chapter_number')->get();
        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<p>CHAPTER '.$chapter->chapter_number.': '.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            }
        }

        if ($project->references) {
            $html .= '<p>REFERENCES</p>';
        }

        $html .= '<div class="page-break"></div>';

        // Get references to append to last chapter
        $referencesHtml = $this->chapterReferenceService->formatProjectReferencesFromDatabase($project);

        // All chapters
        $chaptersWithContent = $chapters->filter(fn ($ch) => ! empty($ch->content));
        $lastChapter = $chaptersWithContent->last();

        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<h1>CHAPTER '.$this->numberToWords($chapter->chapter_number).'</h1>';
                $html .= '<h1>'.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';

                // Strip individual chapter references - we'll add consolidated references at the end
                $chapterContent = $this->normalizeContentToHtml($chapter->content);
                $chapterContent = $this->stripReferencesSection($chapterContent);
                $html .= $chapterContent; // Content is processed by preprocessHtmlForPandoc which handles escaping

                // If this is the last chapter, append consolidated references (no page break)
                if ($lastChapter && $chapter->id === $lastChapter->id && ! empty($referencesHtml)) {
                    $html .= $referencesHtml;
                    Log::info('Added collected project references to last chapter', ['project_id' => $project->id, 'last_chapter' => $chapter->chapter_number]);
                } else {
                    // Only add page break if not the last chapter
                    $html .= '<div class="page-break"></div>';
                }
            }
        }

        return $html;
    }

    /**
     * Export single chapter to Word document
     */
    public function exportChapterToWord(Project $project, $chapter): string
    {
        try {
            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            // Generate filename
            $filename = $exportDir.'/'.$project->slug.'-chapter-'.$chapter->chapter_number.'_'.time().'.docx';

            if (! empty($chapter->content)) {
                // Build chapter HTML with title - escape special characters
                $chapterHtml = '<h1>CHAPTER '.$this->numberToWords($chapter->chapter_number).'</h1>';
                $chapterHtml .= '<h1>'.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';

                // Strip inline references from chapter content
                $chapterContent = $this->normalizeContentToHtml($chapter->content);
                $cleanedContent = $this->stripReferencesSection($chapterContent);
                $chapterHtml .= $cleanedContent; // Content is processed by preprocessHtmlForPandoc

                // Append chapter references section (for single chapter export)
                $referencesHtml = $this->chapterReferenceService->formatChapterReferencesSection($chapter);
                if (! empty($referencesHtml)) {
                    $chapterHtml .= $referencesHtml;
                    Log::info('Added chapter references to export', ['chapter_id' => $chapter->id]);
                }

                $metadata = [
                    'title' => htmlspecialchars($project->title.' - Chapter '.$chapter->chapter_number, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'author' => htmlspecialchars($project->student_name ?: ($project->user->name ?? 'Unknown'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ];

                if ($this->isLibreOfficeAvailable()) {
                    Log::info('Using LibreOffice for chapter export', ['chapter_id' => $chapter->id]);

                    if ($this->convertWithLibreOffice($chapterHtml, $filename, $metadata)) {
                        Log::info('LibreOffice chapter export successful');

                        return $filename;
                    }

                    Log::warning('LibreOffice failed for chapter export, falling back to Pandoc');
                }

                // Try Pandoc next
                if ($this->convertWithPandoc($chapterHtml, $filename, $metadata)) {
                    Log::info('Pandoc chapter export successful');

                    return $filename;
                }

                Log::warning('Pandoc failed for chapter export');
            }

            throw new \Exception('Export failed: LibreOffice and Pandoc are unavailable or failed');
        } catch (\Exception $e) {
            Log::error('Export chapter to Word failed', [
                'project_id' => $project->id,
                'chapter_id' => $chapter->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Export multiple selected chapters to Word document
     */
    public function exportMultipleChaptersToWord(Project $project, array $chapterNumbers): string
    {
        try {
            // Set memory limits for large documents
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Convert to integers and validate
            $chapterNumbers = array_map('intval', $chapterNumbers);
            sort($chapterNumbers); // Ensure proper order

            Log::info('Starting multiple chapters export', [
                'project_id' => $project->id,
                'chapter_numbers' => $chapterNumbers,
            ]);

            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $chaptersString = implode('-', $chapterNumbers);
            $filename = $exportDir.'/'.$project->slug.'-chapters-'.$chaptersString.'_'.time().'.docx';

            $html = $this->buildMultipleChaptersHtml($project, $chapterNumbers);

            $metadata = [
                'title' => $project->title.' (Selected Chapters)',
                'author' => $project->student_name ?: ($project->user->name ?? 'Unknown'),
            ];

            if ($this->isLibreOfficeAvailable()) {
                Log::info('Using LibreOffice for multiple chapters export');

                if ($this->convertWithLibreOffice($html, $filename, $metadata)) {
                    Log::info('LibreOffice multiple chapters export successful');

                    return $filename;
                }

                Log::warning('LibreOffice failed for multiple chapters, falling back to Pandoc');
            }

            if ($this->isPandocAvailable()) {
                Log::info('Using Pandoc for multiple chapters export');

                if ($this->convertWithPandoc($html, $filename, $metadata)) {
                    Log::info('Pandoc multiple chapters export successful');

                    return $filename;
                }

                Log::warning('Pandoc failed for multiple chapters');
            }

            throw new \Exception('Export failed: LibreOffice and Pandoc are unavailable or failed');
        } catch (\Exception $e) {
            Log::error('Export multiple chapters failed', [
                'project_id' => $project->id,
                'chapter_numbers' => $chapterNumbers ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Build HTML for multiple selected chapters export (Pandoc)
     */
    private function buildMultipleChaptersHtml(Project $project, array $chapterNumbers): string
    {
        $html = '';

        // Title page - escape all text content
        $html .= '<div style="text-align: center; margin-bottom: 3em;">';
        $html .= '<h1>'.htmlspecialchars(strtoupper($project->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
        $html .= '<p>(Selected Chapters: '.implode(', ', $chapterNumbers).')</p>';
        $html .= '<p>BY</p>';
        $html .= '<p><strong>'.htmlspecialchars(strtoupper($project->student_name ?: ($project->user->name ?? 'AUTHOR NAME')), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong></p>';

        if ($project->university) {
            $html .= '<p>'.htmlspecialchars(strtoupper($project->university), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        $html .= '<p>'.$project->created_at->format('F Y').'</p>';
        $html .= '</div>';
        $html .= '<div class="page-break"></div>';

        // Table of contents
        $html .= '<h1 style="text-align: center;">TABLE OF CONTENTS</h1>';

        $chapters = $project->chapters()
            ->whereIn('chapter_number', $chapterNumbers)
            ->orderBy('chapter_number')
            ->get();

        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<p>CHAPTER '.$chapter->chapter_number.': '.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            }
        }

        $html .= '<div class="page-break"></div>';

        // Get references to append to last chapter
        $referencesHtml = $this->formatSelectedChaptersReferences($chapters);

        // Selected chapters
        $chaptersWithContent = $chapters->filter(fn ($ch) => ! empty($ch->content));
        $lastChapter = $chaptersWithContent->last();

        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<h1>CHAPTER '.$this->numberToWords($chapter->chapter_number).'</h1>';
                $html .= '<h1>'.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';

                // Strip individual chapter references - we'll add consolidated references at the end
                $chapterContent = $this->normalizeContentToHtml($chapter->content);
                $chapterContent = $this->stripReferencesSection($chapterContent);
                $html .= $chapterContent; // Content is processed by preprocessHtmlForPandoc

                // If this is the last chapter, append consolidated references (no page break)
                if ($lastChapter && $chapter->id === $lastChapter->id && ! empty($referencesHtml)) {
                    $html .= $referencesHtml;
                } else {
                    // Only add page break if not the last chapter
                    $html .= '<div class="page-break"></div>';
                }
            }
        }

        return $html;
    }

    /**
     * Format references section for selected chapters export.
     *
     * @param  \Illuminate\Support\Collection  $chapters
     */
    private function formatSelectedChaptersReferences($chapters, string $style = 'APA'): string
    {
        $allReferences = collect();

        // First try to get citations from database
        foreach ($chapters as $chapter) {
            $chapterCitations = $this->chapterReferenceService->getChapterReferencesFromDatabase($chapter, $style);

            foreach ($chapterCitations as $citation) {
                if (empty($citation['reference']) || $citation['reference'] === '[CITATION NEEDED - REQUIRES VERIFICATION]') {
                    continue;
                }

                $refKey = $citation['reference'];
                if (! $allReferences->has($refKey)) {
                    $allReferences->put($refKey, $citation);
                }
            }
        }

        // If no database citations, parse from chapter HTML content
        if ($allReferences->isEmpty()) {
            foreach ($chapters as $chapter) {
                $chapterRefs = $this->chapterReferenceService->extractReferencesFromHtml($chapter->content);

                foreach ($chapterRefs as $reference) {
                    $refKey = $reference;
                    if (! $allReferences->has($refKey)) {
                        $allReferences->put($refKey, ['reference' => $reference]);
                    }
                }
            }
        }

        if ($allReferences->isEmpty()) {
            return '';
        }

        // Sort alphabetically by reference text
        $sortedRefs = $allReferences->values()->sortBy('reference');

        $html = '<div class="references-section" style="margin-top: 2em; font-size: 14px;">';
        $html .= '<h1 style="text-align: center; font-weight: bold; margin-bottom: 1em; font-size: 16px;">REFERENCES</h1>';

        foreach ($sortedRefs as $ref) {
            $html .= '<p style="font-size: 14px; text-indent: -0.5in; margin-left: 0.5in; margin-bottom: 0.5em; text-align: justify;">'.
                htmlspecialchars($ref['reference'], ENT_QUOTES | ENT_HTML5, 'UTF-8').
                '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Convert chapter number to words (ONE, TWO, THREE, etc.)
     */
    private function numberToWords(int $number): string
    {
        $words = [
            1 => 'ONE',
            2 => 'TWO',
            3 => 'THREE',
            4 => 'FOUR',
            5 => 'FIVE',
            6 => 'SIX',
            7 => 'SEVEN',
            8 => 'EIGHT',
            9 => 'NINE',
            10 => 'TEN',
        ];

        return $words[$number] ?? (string) $number;
    }

}
