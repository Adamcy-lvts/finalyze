<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->title }}</title>
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
            NOTE: Page numbers are set via JavaScript handler after pagination
        */
        @page {
            size: A4;
            margin: 1in; /* Standard academic margins */

            @bottom-center {
                font-family: 'Times New Roman', Times, serif;
                font-size: 11pt;
            }
        }

        /*
            SECTION STYLING
            Data attributes help JavaScript identify section types
        */
        section.title-page {
            page-break-after: always;
            break-after: page;
            width: 100%;
            text-align: center;
            line-height: 1.5;
        }

        section.frontmatter-section {
            page-break-after: always;
            break-after: page;
            width: 100%;
        }

        section.chapter-section {
            page-break-before: always;
            break-before: page;
            width: 100%;
        }

        /* Minimal chapter content styling - let content render as stored */
        section.chapter-section * {
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
        .pagedjs_pages { width: 100%; }

        /* Custom page number display - positioned by Paged.js margin boxes */
        .pagedjs_margin-bottom-center .pagedjs_margin-content::after {
            content: attr(data-page-number);
        }
    </style>

    <script>
        // Global error handler
        window.addEventListener('error', function(event) {
            console.error('Global Error:', event.message, event.filename, event.lineno);
        });

        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled Promise Rejection:', event.reason);
        });

        /**
         * Custom Page Number Handler for Paged.js
         * This handler runs AFTER pagination and correctly sets page numbers:
         * - Title page: No number
         * - Frontmatter: Roman numerals (i, ii, iii...)
         * - Main content: Arabic numerals starting from 1
         */
        class PageNumberHandler extends Paged.Handler {
            constructor(chunker, polisher, caller) {
                super(chunker, polisher, caller);
                this.titlePageCount = 0;
                this.frontmatterPageCount = 0;
                this.mainContentStartIndex = -1;
                this.pageTypes = []; // Track each page's type
            }

            // Convert number to Roman numerals
            toRoman(num) {
                if (num <= 0) return '';
                const romanNumerals = [
                    { value: 1000, numeral: 'm' },
                    { value: 900, numeral: 'cm' },
                    { value: 500, numeral: 'd' },
                    { value: 400, numeral: 'cd' },
                    { value: 100, numeral: 'c' },
                    { value: 90, numeral: 'xc' },
                    { value: 50, numeral: 'l' },
                    { value: 40, numeral: 'xl' },
                    { value: 10, numeral: 'x' },
                    { value: 9, numeral: 'ix' },
                    { value: 5, numeral: 'v' },
                    { value: 4, numeral: 'iv' },
                    { value: 1, numeral: 'i' }
                ];

                let result = '';
                for (const { value, numeral } of romanNumerals) {
                    while (num >= value) {
                        result += numeral;
                        num -= value;
                    }
                }
                return result;
            }

            // Detect what type of content is on a page
            detectPageType(pageContent) {
                if (!pageContent) return 'unknown';

                // Check for title page
                if (pageContent.querySelector('.title-page') ||
                    pageContent.querySelector('[data-section-type="title"]')) {
                    return 'title';
                }

                // Check for main content (chapters)
                if (pageContent.querySelector('.chapter-section') ||
                    pageContent.querySelector('.first-chapter') ||
                    pageContent.querySelector('[data-section-type="chapter"]')) {
                    return 'main';
                }

                // Check for frontmatter
                if (pageContent.querySelector('.frontmatter-section') ||
                    pageContent.querySelector('[data-section-type="frontmatter"]')) {
                    return 'frontmatter';
                }

                return 'unknown';
            }

            // Called after each page is laid out
            afterPageLayout(pageElement, page, breakToken) {
                const pageIndex = page.position; // 0-based index
                const pageContent = pageElement.querySelector('.pagedjs_page_content');

                // Detect the type of content on this page
                let pageType = this.detectPageType(pageContent);

                // If we can't detect from content, inherit from previous page
                if (pageType === 'unknown' && this.pageTypes.length > 0) {
                    pageType = this.pageTypes[this.pageTypes.length - 1];
                }

                // Track when we first encounter main content
                if (pageType === 'main' && this.mainContentStartIndex === -1) {
                    this.mainContentStartIndex = pageIndex;
                    console.log('Main content starts at page index:', pageIndex);
                }

                this.pageTypes[pageIndex] = pageType;

                console.log(`Page ${pageIndex + 1}: type=${pageType}, mainStart=${this.mainContentStartIndex}`);
            }

            // Called after ALL pagination is complete - this is where we set page numbers
            afterRendered(pages) {
                console.log('Pagination complete. Total pages:', pages.length);
                console.log('Page types:', this.pageTypes);
                console.log('Main content starts at index:', this.mainContentStartIndex);

                let frontmatterCounter = 0;
                let mainCounter = 0;

                pages.forEach((page, index) => {
                    const pageType = this.pageTypes[index] || 'unknown';
                    const pageElement = document.querySelector(`.pagedjs_page[data-page-number="${index + 1}"]`);

                    if (!pageElement) {
                        console.warn(`Could not find page element for index ${index}`);
                        return;
                    }

                    // Find the bottom-center margin box
                    const bottomCenter = pageElement.querySelector('.pagedjs_margin-bottom-center .pagedjs_margin-content');

                    if (!bottomCenter) {
                        console.warn(`Could not find bottom-center margin for page ${index + 1}`);
                        return;
                    }

                    let pageNumberText = '';

                    if (pageType === 'title') {
                        // Title page: no number
                        pageNumberText = '';
                    } else if (pageType === 'frontmatter' || (pageType === 'unknown' && this.mainContentStartIndex === -1)) {
                        // Frontmatter: Roman numerals
                        frontmatterCounter++;
                        pageNumberText = this.toRoman(frontmatterCounter);
                    } else if (pageType === 'main' || (this.mainContentStartIndex !== -1 && index >= this.mainContentStartIndex)) {
                        // Main content: Arabic numerals starting from 1
                        mainCounter++;
                        pageNumberText = String(mainCounter);
                    } else {
                        // Fallback: continue whatever numbering was active
                        if (this.mainContentStartIndex !== -1 && index >= this.mainContentStartIndex) {
                            mainCounter++;
                            pageNumberText = String(mainCounter);
                        } else {
                            frontmatterCounter++;
                            pageNumberText = this.toRoman(frontmatterCounter);
                        }
                    }

                    // Set the page number
                    bottomCenter.textContent = pageNumberText;
                    bottomCenter.setAttribute('data-page-number', pageNumberText);

                    console.log(`Set page ${index + 1} number to: "${pageNumberText}" (type: ${pageType})`);
                });

                console.log('Page numbering complete');
                console.log(`Frontmatter pages: ${frontmatterCounter}, Main content pages: ${mainCounter}`);

                // Signal ready for PDF generation
                window.status = 'ready_to_print';
            }
        }

        // Configure Paged.js to NOT auto-start - we'll start it manually after registering handler
        window.PagedConfig = {
            auto: false
        };

        console.log('PDF Template loaded, waiting for Paged.js...');
    </script>

    <!-- Load Paged.js -->
    <script src="https://unpkg.com/pagedjs/dist/paged.polyfill.js"></script>

    <script>
        // Wait for DOM and Paged.js to be ready, then start pagination with our handler
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking for Paged.js...');

            // Wait a moment for Paged.js to fully initialize
            setTimeout(function() {
                if (typeof Paged !== 'undefined') {
                    console.log('Paged.js found, registering handler...');

                    // Register our custom handler
                    Paged.registerHandlers(PageNumberHandler);

                    console.log('Starting pagination...');

                    // Create new Paged instance and preview
                    const paged = new Paged.Previewer();
                    paged.preview().then(flow => {
                        console.log('Pagination complete via manual preview', {
                            totalPages: flow.total,
                            pageCount: document.querySelectorAll('.pagedjs_page').length
                        });
                    }).catch(err => {
                        console.error('Pagination error:', err);
                        window.status = 'ready_to_print'; // Still allow PDF even on error
                    });
                } else {
                    console.error('Paged.js not found!');
                    window.status = 'ready_to_print';
                }
            }, 100);
        });

        // Fallback timeout
        setTimeout(function() {
            if (window.status !== 'ready_to_print') {
                console.warn('Timeout: Setting ready status');
                window.status = 'ready_to_print';
            }
        }, 10000);
    </script>
</head>

<body>

    <!-- TITLE PAGE -->
    <section class="title-page" data-section-type="title">
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

    <!-- PRELIMINARY PAGES (FRONTMATTER) -->
    @foreach($preliminaryPages as $page)
        <section class="frontmatter-section" data-section-type="frontmatter">
            <div class="section-content">
                <h2>{{ strtoupper($page['title']) }}</h2>
                <div class="preliminary-content">{!! $page['html'] !!}</div>
            </div>
        </section>
    @endforeach

    <!-- TABLE OF CONTENTS -->
    <section class="frontmatter-section" data-section-type="frontmatter">
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

    <!-- LIST OF TABLES -->
    <section class="frontmatter-section" data-section-type="frontmatter">
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

    <!-- LIST OF ABBREVIATIONS -->
    <section class="frontmatter-section" data-section-type="frontmatter">
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

    <!-- MAIN CONTENT: CHAPTERS -->
    @foreach($chapters as $index => $chapter)
        <section class="chapter-section {{ $loop->first ? 'first-chapter' : '' }}" data-section-type="chapter" data-chapter-number="{{ $chapter->chapter_number }}">
            @php
                // Format chapter headings: "CHAPTER ONE: INTRODUCTION" becomes two centered lines
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
            {!! $content !!}
        </section>
    @endforeach

    <!-- REFERENCES -->
    <section class="chapter-section" data-section-type="chapter">
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
    <section class="chapter-section" data-section-type="chapter">
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

</body>
</html>
