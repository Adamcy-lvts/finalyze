<?php

// Simple test script to verify Browsershot PDF generation
require_once __DIR__.'/vendor/autoload.php';

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

// Create a simple HTML template for testing
$htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PDF Test</title>
    <style>
        body {
            font-family: "Times New Roman", serif;
            margin: 40px;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .content {
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PDF Generation Test</h1>
        <p>Testing Browsershot PDF Generation</p>
    </div>
    <div class="content">
        <h2>Test Details</h2>
        <p>This PDF was generated using Browsershot with the following configuration:</p>
        <ul>
            <li>Chrome Path: '.(env('BROWSERSHOT_CHROME_PATH') ?: '/usr/bin/google-chrome').'</li>
            <li>Format: A4</li>
            <li>Orientation: Portrait</li>
            <li>Generated at: '.date('Y-m-d H:i:s').'</li>
        </ul>
        <p>If you can read this text, the PDF generation is working correctly!</p>
    </div>
</body>
</html>';

echo "Testing Browsershot PDF generation...\n";

try {
    // Test Chrome path detection
    $chromePaths = [
        env('BROWSERSHOT_CHROME_PATH'),
        '/usr/bin/chromium-browser',
        '/usr/bin/chromium',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
    ];

    $chromePath = null;
    foreach ($chromePaths as $path) {
        if ($path && file_exists($path) && is_executable($path)) {
            $chromePath = $path;
            echo "Found Chrome at: $chromePath\n";
            break;
        }
    }

    if (! $chromePath) {
        throw new Exception('No Chrome executable found!');
    }

    // Create output directory
    $outputDir = __DIR__.'/storage/app/public/test-pdfs';
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
        echo "Created output directory: $outputDir\n";
    }

    // Generate PDF using direct Browsershot
    $outputFile = $outputDir.'/browsershot-test-'.date('Y-m-d_H-i-s').'.pdf';

    echo "Generating PDF with Browsershot...\n";

    Browsershot::html($htmlContent)
        ->setChromePath($chromePath)
        ->format('A4')
        ->margins(20, 20, 20, 20)
        ->showBackground()
        ->waitUntilNetworkIdle()
        ->timeout(60)
        ->noSandbox()
        ->setOption('disable-web-security', true)
        ->save($outputFile);

    if (file_exists($outputFile)) {
        $fileSize = filesize($outputFile);
        echo "✅ PDF generated successfully!\n";
        echo "   File: $outputFile\n";
        echo '   Size: '.number_format($fileSize)." bytes\n";

        // Validate PDF file
        $fileHeader = file_get_contents($outputFile, false, null, 0, 4);
        if ($fileHeader === '%PDF') {
            echo "✅ PDF file format is valid\n";
        } else {
            echo '❌ PDF file format appears corrupted (header: '.bin2hex($fileHeader).")\n";
        }

    } else {
        echo "❌ PDF file was not created\n";
    }

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
}

echo "\nTest completed.\n";
