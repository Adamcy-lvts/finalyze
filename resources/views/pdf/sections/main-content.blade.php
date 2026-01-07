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
            $numberToWord = function ($number) {
                $map = [
                    1 => 'One',
                    2 => 'Two',
                    3 => 'Three',
                    4 => 'Four',
                    5 => 'Five',
                    6 => 'Six',
                    7 => 'Seven',
                    8 => 'Eight',
                    9 => 'Nine',
                    10 => 'Ten',
                    11 => 'Eleven',
                    12 => 'Twelve',
                    13 => 'Thirteen',
                    14 => 'Fourteen',
                    15 => 'Fifteen',
                    16 => 'Sixteen',
                    17 => 'Seventeen',
                    18 => 'Eighteen',
                    19 => 'Nineteen',
                    20 => 'Twenty',
                ];

                if (isset($map[$number])) {
                    return $map[$number];
                }

                return (string) $number;
            };

            // Replace headings with pattern "CHAPTER X: Title" with centered two-line format
            $content = preg_replace_callback(
                '/<(h[1-6])>(CHAPTER\s+[^:]+):\s*(.+?)<\/\1>/i',
                function($matches) use ($numberToWord) {
                    $tag = $matches[1];
                    $chapterNumber = trim($matches[2]);
                    $chapterTitle = trim($matches[3]);
                    $chapterNumber = preg_replace_callback(
                        '/\b(\d+)\b/',
                        fn ($numMatch) => $numberToWord((int) $numMatch[1]),
                        $chapterNumber
                    );

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
        @if(!empty($formattedReferences))
            {{-- Use formatted references from ChapterReferenceService (sorted alphabetically) --}}
            {!! $formattedReferences !!}
        @elseif($project->references)
            {{-- Fallback to project-level references --}}
            <div class="chapter-title">REFERENCES</div>
            <div class="content">
                {!! nl2br(e($project->references)) !!}
            </div>
        @else
            <div class="chapter-title">REFERENCES</div>
            <div class="content">
                <p>References will be added here.</p>
            </div>
        @endif
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
