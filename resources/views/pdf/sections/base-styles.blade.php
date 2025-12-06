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
</style>
