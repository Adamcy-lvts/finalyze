<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->title }}</title>
    <script src="https://unpkg.com/pagedjs/dist/paged.polyfill.js"></script>
    <script>
        // Detect if Paged.js script loaded
        console.log('Attempting to load Paged.js from CDN...');

        // Check if Paged object exists after script should have loaded
        window.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded. Checking for Paged.js...', {
                hasPagedPolyfill: typeof window.PagedPolyfill !== 'undefined',
                hasPaged: typeof window.Paged !== 'undefined',
                windowKeys: Object.keys(window).filter(k => k.toLowerCase().includes('page'))
            });
        });
    </script>
    <style>
        /* 
            RESET & BASE STYLES 
            Clean slate for Paged.js
        */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 2.0;
            color: #000;
            margin: 0;
            padding: 0;
            background: white;
        }

        /* 
            PAGED.JS CONFIGURATION 
            @page rules control the physical paper layout
        */
        @page {
            size: A4;
            margin: 1in; /* Standard academic margins */

            @bottom-center {
                content: counter(page);
                font-family: 'Times New Roman', Times, serif;
                font-size: 11pt;
            }
        }

        /* Title Page: No page number */
        @page:first {
            @bottom-center {
                content: none;
            }
        }

        /* Frontmatter: Roman Numerals (i, ii, iii...) - continues from title page */
        @page frontmatter {
            @bottom-center {
                content: counter(page, lower-roman);
            }
        }

        /* Main Content: Arabic Numerals (1, 2, 3...) - uses same counter but displays in arabic */
        @page main {
            @bottom-center {
                content: counter(page);
            }
        }

        /* References and Appendices continue main numbering */
        @page references {
            @bottom-center {
                content: counter(page);
            }
        }

        /* 
            SECTION STYLING 
            Each section corresponds to a named page type
        */
        section.title-page {
            page: title;
            page-break-after: always;
            break-after: page;
            width: 100%;
            text-align: center;
            line-height: 1.5;
            counter-reset: page 1;
        }

        section.frontmatter-section {
            page: frontmatter;
            page-break-after: always;
            break-after: page;
            width: 100%;
        }

        section.chapter-section {
            page: main;
            page-break-before: always;
            break-before: page;
            width: 100%;
        }

        /* Reset page counter to 1 for first chapter - CRITICAL FIX */
        section.first-chapter {
            counter-reset: page 1 !important;
        }

        /* Minimal chapter content styling - let content render as stored */
        section.chapter-section * {
            /* Remove any forced transformations */
            text-transform: none !important;
        }

        /* Chapter heading formatting - two centered lines */
        .chapter-heading-wrapper {
            text-align: center;
            margin: 30px 0 20px 0;
            page-break-after: avoid;
        }

        .chapter-number-line {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            text-transform: uppercase !important;
            margin: 0 0 10px 0;
        }

        .chapter-title-line {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            text-transform: uppercase !important;
            margin: 0;
        }

        /* Frontmatter section content styling */
        .section-content {
            text-align: justify;
        }

        .section-content h2 {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            margin: 20px 0;
        }

        /*
            MINIMAL CONTENT STYLING
            Let the content render naturally as stored in database
        */
        p {
            text-align: justify;
            margin: 10px 0;
            text-indent: 0.5in;
        }

        /* Title Page Specifics */
        .university { font-size: 14pt; text-transform: uppercase; margin-bottom: 10px; }
        .main-title { font-size: 14pt; text-transform: uppercase; margin: 0.5in 0; }
        .author-name { font-size: 12pt; margin: 5px 0; }
        .student-id { font-size: 12pt; margin: 5px 0; }
        .dissertation-text { font-size: 12pt; margin: 1in 0.5in 0.5in 0.5in; line-height: 1.8; text-align: center; }
        .institution-details { font-size: 12pt; margin: 0.5in 0; line-height: 1.8; }
        .date { font-size: 12pt; margin-top: 1in; }

        /* Lists & TOC */
        .toc-item { display: flex; justify-content: space-between; border-bottom: 1px dotted #ccc; margin: 10px 0; }
        .list-item { margin: 8px 0; line-height: 1.8; }

        /* Signatures */
        .signature-line { margin-top: 60px; border-top: 1px solid #000; width: 300px; padding-top: 5px; }
        .certification-entry { margin: 40px 0; }
        .certification-entry .signature-line { border-top: 1px dotted #000; width: 400px; margin-top: 30px; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }

        /* Chapter Headers */
        .chapter-number { font-size: 14pt; font-weight: bold; text-transform: uppercase; text-align: center; margin-bottom: 10px; }
        .chapter-title { font-size: 14pt; font-weight: bold; text-transform: uppercase; text-align: center; margin: 0; }

        /* Utility */
        .pagedjs_pages { width: 100%; } /* Fix for Paged.js UI */
    </style>
    <script>
        // Global error handler to catch any JavaScript errors
        window.addEventListener('error', function(event) {
            console.error('Global Error:', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error
            });
        });

        // Promise rejection handler
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled Promise Rejection:', event.reason);
        });

        // Set PagedConfig before Paged.js auto-executes
        window.PagedConfig = {
            auto: true,
            before: () => {
                console.log('✓ Paged.js: Starting pagination...');
            },
            after: (flow) => {
                console.log('✓ Paged.js: Pagination complete', {
                    totalPages: flow?.total || 'unknown',
                    status: 'success'
                });
                window.status = 'ready_to_print';
            },
            onError: (error) => {
                console.error('✗ Paged.js Error:', error);
                // Still set status to allow PDF generation even with errors
                window.status = 'ready_to_print';
            }
        };

        console.log('✓ PDF Template loaded');
        console.log('✓ PagedConfig set, waiting for Paged.js to initialize...');

        // Fallback: Set ready status after 5 seconds if Paged.js hasn't completed
        setTimeout(function() {
            if (window.status !== 'ready_to_print') {
                console.warn('⚠ Timeout: Paged.js did not complete. Setting ready status anyway.');
                console.log('Current window.status:', window.status);
                window.status = 'ready_to_print';
            }
        }, 5000);
    </script>
</head>

<body>

    <!-- TITLE PAGE -->
    <section class="title-page">
        <div class="university">{{ strtoupper($project->title) }}</div>
        <div class="main-title">BY</div>
        <div class="author-name">{{ strtoupper($project->user->name) }}</div>
        @if($project->student_id)
            <div class="student-id">{{ $project->student_id }}</div>
        @endif

        <div class="dissertation-text">
            A {{ strtoupper($project->type) }} SUBMITTED TO THE SCHOOL OF<br>
            POST-GRADUATE STUDIES IN PARTIAL FULFILMENT FOR THE REQUIREMENTS<br>
            OF THE AWARD OF THE DEGREE OF {{ strtoupper($project->degree ?? 'MASTERS') }} ({{ strtoupper($project->degree_abbreviation ?? 'M.Sc.') }}) IN {{ strtoupper($project->course ?? 'COMPUTER SCIENCE') }}
        </div>

        <div class="institution-details">
            AT THE DEPARTMENT OF<br>
            {{ strtoupper($project->course ?? 'COMPUTER SCIENCE') }}<br>
            FACULTY OF {{ strtoupper($project->faculty ?? 'SCIENCE') }}<br><br>
            {{ strtoupper($project->full_university_name) }}
        </div>

        <div class="date">{{ strtoupper(now()->format('F, Y')) }}</div>
    </section>

    <!-- PRELIMINARY PAGES -->
    <!-- Each include must NOT have a wrapper div in the included file -->
    <script>
        console.log('Starting preliminary pages rendering...', {
            hasDedication: {{ $project->dedication ? 'true' : 'false' }},
            hasAcknowledgements: {{ $project->acknowledgements ? 'true' : 'false' }},
            hasAbstract: {{ $project->abstract ? 'true' : 'false' }},
            hasTableOfContents: {{ $chapters->count() > 0 ? 'true' : 'false' }},
            hasTables: {{ (isset($project->tables) && count($project->tables) > 0) ? 'true' : 'false' }},
            hasAbbreviations: {{ (isset($project->abbreviations) && count($project->abbreviations) > 0) ? 'true' : 'false' }}
        });
    </script>

    <section class="frontmatter-section">
        <div class="section-content">
            <h2>DECLARATION</h2>

            @if($project->declaration)
                <div class="preliminary-content">{!! $project->declaration !!}</div>
            @else
                <p>
                    I, <strong>{{ $project->user->name }}</strong>, with Student Registration Number <strong>{{ $project->student_id ?? '....................' }}</strong>,
                    declare that this {{ $project->type }} titled <strong>"{{ $project->title }}"</strong> is my original work and has not been
                    submitted for the award of any degree or diploma in this or any other university.
                </p>

                <div class="signature-line">
                    Student's Signature & Date
                </div>
            @endif
        </div>
    </section>

    <section class="frontmatter-section">
        <div class="section-content">
            <h2>CERTIFICATION</h2>

            @if($project->certification)
                <div class="preliminary-content">{!! $project->certification !!}</div>
            @else
                <p>
                    This is to certify that this {{ ucfirst($project->type) }} entitled "{{ $project->title }}"
                    has been duly carried out and presented by {{ $project->user->name }} ({{ $project->student_id ?? 'Student ID' }})
                    in the Department of {{ $project->course ?? 'Computer Science' }}, Faculty of {{ ucwords($project->faculty ?? 'Science') }},
                    {{ $project->full_university_name }}, under my supervision.
                </p>

                @if($project->certification_signatories && count($project->certification_signatories) > 0)
                    @foreach($project->certification_signatories as $signatory)
                        <div class="certification-entry">
                            @if(isset($signatory['name']) && $signatory['name'])
                                <div class="role">{{ $signatory['name'] }}</div>
                            @endif
                            <div class="signature-line">
                                {{ $signatory['title'] ?? 'Signatory' }} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="certification-entry">
                        <div class="role">{{ $project->supervisor_name ?? 'Dr. [Supervisor Name]' }}</div>
                        <div class="signature-line">
                            Supervisor &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                        </div>
                    </div>

                    <div class="certification-entry">
                        <div class="signature-line">
                            Center Director &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                        </div>
                    </div>

                    <div class="certification-entry">
                        <div class="signature-line">
                            Head of Department &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                        </div>
                    </div>

                    <div class="certification-entry">
                        <div class="signature-line">
                            Dean Faculty of {{ ucwords($project->faculty ?? 'Science') }} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                        </div>
                    </div>

                    <div class="certification-entry">
                        <div class="signature-line">
                            Dean School of Postgraduate Studies &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Signature & Date
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </section>

    <section class="frontmatter-section">
        <div class="section-content">
            <h2>DEDICATION</h2>

            @if($project->dedication)
                <div class="preliminary-content">{!! $project->dedication !!}</div>
            @else
                <p>
                    I dedicate this research work firstly to God almighty the maker of heaven and the earth and also to my
                    family members for their unwavering support throughout this journey.
                </p>
            @endif
        </div>
    </section>

    <section class="frontmatter-section">
        <div class="section-content">
            <h2>ACKNOWLEDGEMENTS</h2>

            @if($project->acknowledgements)
                <div class="preliminary-content">{!! $project->acknowledgements !!}</div>
            @else
                <p>
                    First and foremost, I am thankful to God Almighty for enabling me achieve this dream.
                    This work has been a journey enriched by the presence of many people.
                </p>
                <p>
                    I am grateful to my supervisor {{ $project->supervisor_name ?? 'Dr. [Supervisor Name]' }} for the
                    invaluable scholarly advice and timeless effort despite a tight schedule. The contribution made it
                    possible for the smooth completion of my research.
                </p>
                <p>
                    I profoundly thank and appreciate the enormous support of {{ $project->full_university_name }}
                    management for their timeless effort and guidance.
                </p>
            @endif
        </div>
    </section>

    <section class="frontmatter-section">
        <div class="section-content">
            <h2>ABSTRACT</h2>

            @if($project->abstract)
                <div class="preliminary-content">{!! $project->abstract !!}</div>
            @else
                <p>
                    This {{ $project->type }} investigated {{ strtolower($project->title) }}.
                    The research was conducted at {{ $project->full_university_name }} in the
                    Department of {{ $project->course }}.
                </p>
            @endif
        </div>
    </section>

    <section class="frontmatter-section">
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
    </section>

    <section class="frontmatter-section">
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
    </section>

    <section class="frontmatter-section">
        <div class="section-content">
            <h2>List of Abbreviations and Acronyms</h2>

            @if(isset($project->abbreviations) && count($project->abbreviations) > 0)
                @foreach($project->abbreviations as $abbr => $meaning)
                    <div class="list-item"><strong>{{ $abbr }}</strong> – {{ $meaning }}</div>
                @endforeach
            @else
                <div class="list-item"><strong>CD</strong> – Compact Disc</div>
                <div class="list-item"><strong>CD-ROM</strong> – Compact Disc-Read-Only Memory</div>
                <div class="list-item"><strong>DIRs</strong> – Digital Information Resources</div>
                <div class="list-item"><strong>DVD</strong> – Digital Versatile Disc</div>
                <div class="list-item"><strong>ICT</strong> – Information and Communication Technologies</div>
                <div class="list-item"><strong>IT</strong> – Information Technology</div>
            @endif
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <script>
        console.log('Starting chapter rendering...', {
            totalChapters: {{ $chapters->count() }},
            chapterIds: @json($chapters->pluck('id')),
            chapterNumbers: @json($chapters->pluck('chapter_number')),
            chapterTitles: @json($chapters->pluck('title'))
        });
    </script>

    @foreach($chapters as $index => $chapter)
        <section class="chapter-section {{ $loop->first ? 'first-chapter' : '' }}">
            <script>
                console.log('Rendering chapter {{ $chapter->chapter_number }}', {
                    id: {{ $chapter->id }},
                    title: @json($chapter->title),
                    hasContent: {{ isset($chapterContents[$chapter->id]) && !empty($chapterContents[$chapter->id]) ? 'true' : 'false' }},
                    contentLength: {{ isset($chapterContents[$chapter->id]) ? strlen($chapterContents[$chapter->id]) : 0 }}
                });
            </script>
            @php
                // Format chapter headings: "CHAPTER ONE: INTRODUCTION" becomes two centered lines
                $content = $chapterContents[$chapter->id] ?? '<p>No content available for this chapter.</p>';

                // Replace headings with pattern "CHAPTER X: Title" with centered two-line format
                $content = preg_replace_callback(
                    '/<(h[1-6])>(CHAPTER\s+[^:]+):\s*(.+?)<\/\1>/i',
                    function($matches) {
                        $tag = $matches[1];
                        $chapterNumber = trim($matches[2]); // e.g., "CHAPTER ONE"
                        $chapterTitle = trim($matches[3]);   // e.g., "INTRODUCTION"

                        return '<div class="chapter-heading-wrapper">' .
                               '<' . $tag . ' class="chapter-number-line">' . strtoupper($chapterNumber) . '</' . $tag . '>' .
                               '<' . $tag . ' class="chapter-title-line">' . strtoupper($chapterTitle) . '</' . $tag . '>' .
                               '</div>';
                    },
                    $content
                );
            @endphp
            {!! $content !!}
        </section>
    @endforeach

    <script>
        console.log('All chapters rendered successfully');
    </script>

    <!-- REFERENCES -->
    <section class="chapter-section">
        <div class="chapter-title">REFERENCES</div>
        <div class="content">
            @if($project->references)
                {!! nl2br(e($project->references)) !!}
            @else
                <p>References will be added here.</p>
            @endif
        </div>
    </section>

    <!-- APPENDICES -->
    <section class="chapter-section">
        <div class="chapter-title">APPENDICES</div>
        <div class="content">
            @if($project->appendices)
                {!! nl2br(e($project->appendices)) !!}
            @else
                <p><strong>APPENDIX I: INTRODUCTION LETTER</strong></p>
                <p><strong>APPENDIX II: QUESTIONNAIRE FOR RESPONDENT</strong></p>
            @endif
        </div>
    </section>

    <script>
        console.log('All sections rendered successfully. Document ready for Paged.js pagination.', {
            timestamp: new Date().toISOString()
        });
    </script>

</body>
</html>
