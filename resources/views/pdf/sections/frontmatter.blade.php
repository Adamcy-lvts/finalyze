<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{!! $project->title !!} - Preliminary Pages</title>
    @include('pdf.sections.base-styles')
</head>
<body>
    {{-- PRELIMINARY PAGES --}}
    @foreach($preliminaryPages as $page)
        <div class="section-content">
            <h2>{{ strtoupper($page['title']) }}</h2>
            <div class="preliminary-content">{!! $page['html'] !!}</div>
        </div>
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    {{-- TABLE OF CONTENTS --}}
    <div class="page-break"></div>
    <div class="section-content">
        <h2>Table of Contents</h2>

        <div class="toc-item">
            <span class="toc-chapter">Title Page</span>
            <span class="toc-page-num"></span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">Declaration</span>
            <span class="toc-page-num">i</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">Certification</span>
            <span class="toc-page-num">ii</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">Dedication</span>
            <span class="toc-page-num">iii</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">Acknowledgements</span>
            <span class="toc-page-num">iv</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">Abstract</span>
            <span class="toc-page-num">v</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">Table of Contents</span>
            <span class="toc-page-num">vi</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">List of Tables</span>
            <span class="toc-page-num">vii</span>
        </div>
        <div class="toc-item">
            <span class="toc-chapter">List of Abbreviations</span>
            <span class="toc-page-num">viii</span>
        </div>

        @foreach($chapters as $chapter)
            <div class="toc-item">
                <span class="toc-chapter">Chapter {{ $chapter->chapter_number }}: {{ $chapter->title }}</span>
                <span class="toc-page-num">{{ $loop->iteration }}</span>
            </div>
        @endforeach

        <div class="toc-item">
            <span class="toc-chapter">References</span>
            <span class="toc-page-num">{{ count($chapters) + 1 }}</span>
        </div>

        <div class="toc-item">
            <span class="toc-chapter">Appendices</span>
            <span class="toc-page-num">{{ count($chapters) + 2 }}</span>
        </div>
    </div>

    {{-- LIST OF TABLES --}}
    <div class="page-break"></div>
    <div class="section-content">
        <h2>List of Tables</h2>

        @if(isset($project->tables) && count($project->tables) > 0)
            @foreach($project->tables as $table)
                <div class="list-item">{{ $table }}</div>
            @endforeach
        @else
            <div class="list-item">Table 4.1: Distribution of Respondents by Age</div>
            <div class="list-item">Table 4.2: Academic Qualification of Respondents</div>
            <div class="list-item">Table 4.3: Gender Distribution of the Respondents</div>
        @endif
    </div>

    {{-- LIST OF ABBREVIATIONS --}}
    <div class="page-break"></div>
    <div class="section-content">
        <h2>List of Abbreviations and Acronyms</h2>

        @if(isset($project->abbreviations) && count($project->abbreviations) > 0)
            @foreach($project->abbreviations as $abbr => $meaning)
                <div class="list-item"><strong>{{ $abbr }}</strong> - {{ $meaning }}</div>
            @endforeach
        @else
            <div class="list-item"><strong>CD</strong> - Compact Disc</div>
            <div class="list-item"><strong>CD-ROM</strong> - Compact Disc-Read-Only Memory</div>
            <div class="list-item"><strong>DIRs</strong> - Digital Information Resources</div>
            <div class="list-item"><strong>DVD</strong> - Digital Versatile Disc</div>
            <div class="list-item"><strong>ICT</strong> - Information and Communication Technologies</div>
            <div class="list-item"><strong>IT</strong> - Information Technology</div>
        @endif
    </div>
</body>
</html>
