<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->title }} - Main Content</title>
    @include('pdf.sections.base-styles')
</head>
<body>
    {{-- CHAPTERS --}}
    @foreach($chapters as $index => $chapter)
        @php
            $content = $chapterContents[$chapter->id] ?? '<p>No content available for this chapter.</p>';

            // Replace headings with pattern "CHAPTER X: Title" with centered two-line format
            $content = preg_replace_callback(
                '/<(h[1-6])>(CHAPTER\s+[^:]+):\s*(.+?)<\/\1>/i',
                function($matches) {
                    $tag = $matches[1];
                    $chapterNumber = trim($matches[2]);
                    $chapterTitle = trim($matches[3]);

                    return '<div class="chapter-heading-wrapper">' .
                           '<' . $tag . ' class="chapter-number-line">' . strtoupper($chapterNumber) . '</' . $tag . '>' .
                           '<' . $tag . ' class="chapter-title-line">' . strtoupper($chapterTitle) . '</' . $tag . '>' .
                           '</div>';
                },
                $content
            );
        @endphp

        <div class="chapter-content">
            {!! $content !!}
        </div>

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    {{-- REFERENCES --}}
    <div class="page-break"></div>
    <div class="section-content">
        <div class="chapter-title">REFERENCES</div>
        <div class="content">
            @if($project->references)
                {!! nl2br(e($project->references)) !!}
            @else
                <p>References will be added here.</p>
            @endif
        </div>
    </div>

    {{-- APPENDICES --}}
    <div class="page-break"></div>
    <div class="section-content">
        <div class="chapter-title">APPENDICES</div>
        <div class="content">
            @if($project->appendices)
                {!! nl2br(e($project->appendices)) !!}
            @else
                <p><strong>APPENDIX I: INTRODUCTION LETTER</strong></p>
                <p><strong>APPENDIX II: QUESTIONNAIRE FOR RESPONDENT</strong></p>
            @endif
        </div>
    </div>
</body>
</html>
