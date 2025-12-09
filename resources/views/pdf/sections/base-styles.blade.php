{{-- Base styles for all PDF sections --}}
<style>
    * {
        box-sizing: border-box;
    }

    @page {
        size: A4;
        margin: 1in;
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

    /* Title Page Specifics */
    .title-page {
        text-align: center;
        line-height: 1.5;
    }

    .university { font-size: 14pt; text-transform: uppercase; margin-bottom: 10px; }
    .main-title { font-size: 14pt; text-transform: uppercase; margin: 0.5in 0; }
    .author-name { font-size: 12pt; margin: 5px 0; }
    .student-id { font-size: 12pt; margin: 5px 0; }
    .dissertation-text { font-size: 12pt; margin: 1in 0.5in 0.5in 0.5in; line-height: 1.8; text-align: center; }
    .institution-details { font-size: 12pt; margin: 0.5in 0; line-height: 1.8; }
    .date { font-size: 12pt; margin-top: 1in; }

    /* Section content styling */
    .section-content {
        text-align: justify;
    }

    .section-content h2 {
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin: 20px 0;
    }

    /* Paragraphs */
    p {
        text-align: justify;
        margin: 10px 0;
        text-indent: 0.5in;
    }

    /* Lists & TOC */
    .toc-item {
        display: flex;
        justify-content: space-between;
        border-bottom: 1px dotted #ccc;
        margin: 10px 0;
        text-indent: 0;
    }

    .list-item {
        margin: 8px 0;
        line-height: 1.8;
        text-indent: 0;
    }

    /* Signatures */
    .signature-line { margin-top: 60px; border-top: 1px solid #000; width: 300px; padding-top: 5px; }
    .certification-entry { margin: 40px 0; }
    .certification-entry .signature-line { border-top: 1px dotted #000; width: 400px; margin-top: 30px; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
    th { background: #f0f0f0; font-weight: bold; }

    /* Chapter Headers */
    .chapter-heading-wrapper {
        text-align: center;
        margin: 30px 0 20px 0;
        page-break-after: avoid;
    }

    .chapter-number-line {
        text-align: center;
        font-weight: bold;
        font-size: 14pt;
        text-transform: uppercase;
        margin: 0 0 10px 0;
    }

    .chapter-title-line {
        text-align: center;
        font-weight: bold;
        font-size: 14pt;
        text-transform: uppercase;
        margin: 0;
    }

    .chapter-title {
        font-size: 14pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: center;
        margin: 0 0 20px 0;
    }

    /* Page breaks */
    .page-break {
        page-break-after: always;
        break-after: page;
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

    /* For very complex diagrams, allow page break */
    .mermaid-large {
        max-height: none;
        page-break-inside: auto;
    }

    .mermaid-large svg {
        max-height: 600px !important;
    }
</style>

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
