<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $chapter->title }}</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            margin: 20px;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 8px 0;
            letter-spacing: 1px;
        }

        .header h2 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .header h3 {
            font-size: 12pt;
            margin: 0 0 15px 0;
        }

        .chapter-info {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }

        .chapter-number {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 5px 0;
        }

        .chapter-title {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin: 25px 0 15px 0;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }

        .info-item {
            margin-bottom: 8px;
            font-size: 11pt;
        }

        .info-item strong {
            font-weight: bold;
        }

        .content {
            font-size: 11pt;
            line-height: 1.8;
            text-align: justify;
            margin: 20px 0;
        }

        .content h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0 10px 0;
        }

        .content h2 {
            font-size: 13pt;
            font-weight: bold;
            margin: 18px 0 10px 0;
        }

        .content h3 {
            font-size: 12pt;
            font-weight: bold;
            margin: 16px 0 8px 0;
        }

        .content p {
            margin: 10px 0;
        }

        .content ul, .content ol {
            margin: 10px 0;
            padding-left: 30px;
        }

        .content li {
            margin: 5px 0;
        }

        .content blockquote {
            margin: 15px 0;
            padding: 10px 20px;
            border-left: 3px solid #ccc;
            background: #f9f9f9;
            font-style: italic;
        }

        .content code {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 2px 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .content pre {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            overflow-x: auto;
            margin: 15px 0;
        }

        /* Mermaid diagram container - properly constrained */
        .mermaid {
            display: block;
            width: 100%;
            max-width: 100%;
            max-height: 400px;
            overflow: hidden;
            text-align: center;
            margin: 20px auto;
            padding: 15px;
            background: #fff;
            page-break-inside: avoid;
            page-break-before: auto;
            page-break-after: auto;
        }

        .mermaid svg {
            max-width: 100% !important;
            max-height: 350px !important;
            width: auto !important;
            height: auto !important;
            display: block;
            margin: 0 auto;
        }

        /* For very complex diagrams */
        .mermaid-large {
            max-height: none;
            page-break-inside: auto;
        }

        .mermaid-large svg {
            max-height: 600px !important;
        }

        .content table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .content table th,
        .content table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .content table th {
            background: #f0f0f0;
            font-weight: bold;
        }

        .statistics {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
        }

        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .stat-item {
            flex: 1;
            min-width: 150px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            text-align: center;
        }

        .stat-label {
            font-size: 9pt;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 12pt;
            font-weight: bold;
            color: #000;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header">
        <h1>{{ $project->full_university_name }}</h1>
        <h2>Faculty of {{ ucwords($project->faculty ?? 'Science') }}</h2>
        <h3>Department of {{ $project->course }}</h3>
    </div>

    <!-- Chapter Info -->
    <div class="chapter-info">
        <div class="chapter-number">Chapter {{ $chapter->chapter_number }}</div>
        <div class="chapter-title">{{ $chapter->title }}</div>
    </div>

    <!-- Project Information -->
    <div>
        <div class="section-title">PROJECT INFORMATION</div>
        <div class="info-item">
            <strong>Project Title:</strong> {{ $project->title }}
        </div>
        <div class="info-item">
            <strong>Student Name:</strong> {{ $project->user->name }}
        </div>
        <div class="info-item">
            <strong>Course of Study:</strong> {{ $project->course }}
        </div>
        <div class="info-item">
            <strong>Field of Study:</strong> {{ $project->field_of_study }}
        </div>
        <div class="info-item">
            <strong>Academic Level:</strong> {{ ucfirst($project->academic_level) }}
        </div>
        <div class="info-item">
            <strong>Export Date:</strong> {{ now()->format('F j, Y') }}
        </div>
    </div>

    <!-- Chapter Content -->
    <div>
        <div class="section-title">CHAPTER CONTENT</div>
        <div class="content">
            {!! $chapterContent !!}
        </div>
    </div>

    <!-- Statistics -->
    @if($chapter->word_count > 0)
    <div class="statistics">
        <div class="section-title">CHAPTER STATISTICS</div>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Word Count</div>
                <div class="stat-value">{{ number_format($chapter->word_count) }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Chapter Status</div>
                @php
                    $statusLabel = $chapter->status instanceof \App\Enums\ChapterStatus
                        ? $chapter->status->label()
                        : ucfirst(str_replace('_', ' ', (string) $chapter->status));
                @endphp
                <div class="stat-value">{{ $statusLabel }}</div>
            </div>
            @if($chapter->quality_score)
            <div class="stat-item">
                <div class="stat-label">Quality Score</div>
                <div class="stat-value">{{ $chapter->quality_score }}%</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        Generated by Finalyze AI Academic Assistant | {{ now()->format('F j, Y \a\t g:i A') }}
    </div>

    <!-- Mermaid.js for diagram rendering -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
    <script>
        mermaid.initialize({
            startOnLoad: true,
            theme: 'default',
            securityLevel: 'loose',
            fontFamily: 'Times New Roman, serif',
            fontSize: 12,
            flowchart: {
                useMaxWidth: true,
                htmlLabels: true,
                curve: 'basis',
                padding: 10,
                nodeSpacing: 30,
                rankSpacing: 40
            },
            sequence: {
                useMaxWidth: true,
                width: 150,
                height: 50,
                boxMargin: 5,
                messageMargin: 20
            },
            gantt: {
                useMaxWidth: true,
                barHeight: 20,
                barGap: 4
            }
        });

        // Post-render scaling for oversized diagrams
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure mermaid has rendered
            setTimeout(function() {
                document.querySelectorAll('.mermaid svg').forEach(function(svg) {
                    try {
                        var bbox = svg.getBBox();
                        var width = bbox.width || svg.clientWidth || 800;
                        var height = bbox.height || svg.clientHeight || 600;

                        // Max dimensions for A4 with margins (in pixels at 96dpi)
                        var maxWidth = 500;
                        var maxHeight = 350;

                        // Calculate scale factor if needed
                        var scaleX = width > maxWidth ? maxWidth / width : 1;
                        var scaleY = height > maxHeight ? maxHeight / height : 1;
                        var scale = Math.min(scaleX, scaleY);

                        if (scale < 1) {
                            svg.style.width = (width * scale) + 'px';
                            svg.style.height = (height * scale) + 'px';
                        }

                        // Ensure viewBox is set for proper scaling
                        if (!svg.getAttribute('viewBox')) {
                            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
                        }
                    } catch (e) {
                        console.log('SVG scaling error:', e);
                    }
                });
            }, 500);
        });
    </script>
</body>

</html>
