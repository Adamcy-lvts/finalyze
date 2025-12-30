# Defense Slide Deck Generation - Two-Pass AI Architecture

## Overview

This document outlines the implementation plan for improving defense slide deck generation to produce **defense-ready presentations with actual data** (not generic outlines) in under 2 minutes.

## Problem Statement

### Current Limitations
- Truncates chapter content to 3000 characters, losing critical data
- Single-pass AI generation produces generic outlines
- Slides contain vague statements like "Key findings" instead of actual statistics
- No semantic understanding of chapter structure

### Target Outcome
- Slides contain specific statistics, percentages, and findings from the project
- Works for both platform-generated projects (with FacultyStructure) and uploaded projects
- Generation completes in under 2 minutes
- Professional, defense-ready presentation content

---

## Architecture

### Current Flow
```
Project → Truncated Content (3000 chars) → Single AI Call → Generic Slides
```

### New Two-Pass Flow
```
Project Chapters (HTML)
        ↓
   ┌─────────────────────────────────────┐
   │  PASS 1: STRUCTURED EXTRACTION      │
   │  ─────────────────────────────────  │
   │  HtmlContentParser                  │
   │    → Extract tables, stats, lists   │
   │  ChapterTypeDetector                │
   │    → Identify chapter purpose       │
   │  AI Extraction (gpt-4o-mini)        │
   │    → Extract type-specific data     │
   └─────────────────────────────────────┘
        ↓
   [Structured Data Schema]
        ↓
   ┌─────────────────────────────────────┐
   │  PASS 2: SLIDE GENERATION           │
   │  ─────────────────────────────────  │
   │  AI Generation (gpt-4o)             │
   │    → Build slides from extracted    │
   │      data, not raw content          │
   │    → Enforce 12-15 slide structure  │
   │    → Include actual statistics      │
   └─────────────────────────────────────┘
        ↓
   [Defense-Ready Slides]
        ↓
   ┌─────────────────────────────────────┐
   │  EXPORT: PPTX RENDERING             │
   │  ─────────────────────────────────  │
   │  PptxGenJS (Node.js)                │
   │    → Professional PPTX file         │
   └─────────────────────────────────────┘
```

---

## New Services

### 1. HtmlContentParser

**Location:** `app/Services/Defense/HtmlContentParser.php`

Parses Tiptap HTML content semantically to extract:

| Element | Extraction Method |
|---------|------------------|
| Tables | DOM parsing → headers + rows |
| Statistics | Regex: `\d+%`, `r = 0.xx`, `p < 0.xx`, `n = xxx`, `α = 0.xx` |
| Citations | Regex: `(Author, Year)` patterns |
| Headings | H1-H6 with hierarchy |
| Lists | Bullet and numbered items |

```php
class HtmlContentParser
{
    public function parse(string $html): array
    {
        return [
            'headings' => $this->extractHeadings($html),
            'paragraphs' => $this->extractParagraphs($html),
            'lists' => $this->extractLists($html),
            'tables' => $this->extractTables($html),
            'statistics' => $this->extractStatistics($html),
            'citations' => $this->extractCitations($html),
        ];
    }
}
```

### 2. ChapterTypeDetector

**Location:** `app/Services/Defense/ChapterTypeDetector.php`

Unified chapter type detection supporting both platform-generated and uploaded projects:

**Detection Priority:**
1. **FacultyStructure metadata** (if available) → Check `FacultyChapter.chapter_title`
2. **Title-based inference** → Pattern matching on chapter title
3. **Number fallback** → 1=intro, 2=litrev, 3=methodology, 4=results, 5=discussion

```php
class ChapterTypeDetector
{
    public function detect(Project $project, Chapter $chapter): string
    {
        // Strategy 1: FacultyStructure
        $type = $this->detectFromFacultyStructure($project, $chapter->chapter_number);
        if ($type) return $type;

        // Strategy 2: Title inference
        $type = $this->inferFromTitle($chapter->title);
        if ($type !== 'general') return $type;

        // Strategy 3: Number fallback
        return $this->fallbackFromNumber($chapter->chapter_number);
    }

    private function inferFromTitle(?string $title): string
    {
        $t = strtolower(trim((string)$title));

        if (str_contains($t, 'introduction')) return 'introduction';
        if (str_contains($t, 'literature')) return 'literature_review';
        if (str_contains($t, 'method')) return 'methodology';
        if (str_contains($t, 'result') || str_contains($t, 'finding')) return 'results';
        if (str_contains($t, 'discussion')) return 'discussion';
        if (str_contains($t, 'conclusion')) return 'conclusion';

        return 'general';
    }
}
```

### 3. DefenseContentExtractor

**Location:** `app/Services/Defense/DefenseContentExtractor.php`

Orchestrates Pass 1 extraction:

```php
class DefenseContentExtractor
{
    public function extractFromProject(Project $project): array
    {
        $extractedData = [
            'project_meta' => $this->extractProjectMeta($project),
            'chapters' => [],
        ];

        foreach ($project->chapters->sortBy('chapter_number') as $chapter) {
            $chapterType = $this->typeDetector->detect($project, $chapter);
            $extractedData['chapters'][] = $this->extractFromChapter($chapter, $chapterType);
        }

        return $extractedData;
    }

    private function extractFromChapter(Chapter $chapter, string $type): array
    {
        // Step 1: Parse HTML
        $parsed = $this->htmlParser->parse($chapter->content ?? '');

        // Step 2: AI extraction with type-specific prompt
        $prompt = $this->buildExtractionPrompt($type, $parsed);
        $extracted = $this->aiGenerator->generate($prompt, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.1,
        ]);

        // Step 3: Merge pre-extracted tables (more reliable)
        if (!empty($parsed['tables'])) {
            $extracted['tables_extracted'] = $parsed['tables'];
        }

        return [
            'number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'type' => $type,
            'extracted_data' => $extracted,
        ];
    }
}
```

---

## Data Extraction Schema

### Unified Output Structure

```php
[
    'project_meta' => [
        'title' => string,
        'topic' => string,
        'field_of_study' => string,
        'academic_level' => string,
        'university' => string,
    ],
    'chapters' => [
        [
            'number' => int,
            'title' => string,
            'type' => string,
            'word_count' => int,
            'extracted_data' => [...] // Type-specific
        ]
    ]
]
```

### Type-Specific Extraction Fields

#### Introduction
```php
'extracted_data' => [
    'problem_statement' => string,
    'research_gap' => string,
    'general_objective' => string,
    'specific_objectives' => [string],
    'research_questions' => [string],
    'scope' => [
        'coverage' => string,
        'delimitations' => string,
    ],
    'significance' => [
        'theoretical' => string,
        'practical' => string,
        'beneficiaries' => [string],
    ],
]
```

#### Literature Review
```php
'extracted_data' => [
    'key_concepts' => [
        ['term' => string, 'definition' => string]
    ],
    'theoretical_framework' => [
        ['theory' => string, 'application' => string]
    ],
    'empirical_studies' => [
        ['citation' => string, 'findings' => string, 'relevance' => string]
    ],
    'research_gap' => string,
    'conceptual_framework' => [
        'constructs' => [string],
        'relationships' => [string],
    ],
]
```

#### Methodology
```php
'extracted_data' => [
    'research_design' => [
        'type' => string,        // e.g., "Descriptive survey design"
        'justification' => string,
    ],
    'population' => [
        'target' => string,
        'sampling_technique' => string,
        'sample_size' => string, // e.g., "n = 200"
    ],
    'data_collection' => [
        'instrument' => string,  // e.g., "Structured questionnaire"
        'variables_measured' => [string],
    ],
    'data_analysis' => [
        'techniques' => [string], // e.g., ["Regression", "ANOVA"]
        'software' => string,     // e.g., "SPSS v26"
    ],
    'validity_reliability' => [
        'reliability_value' => string, // e.g., "α = 0.85"
    ],
]
```

#### Results
```php
'extracted_data' => [
    'response_rate' => string,   // e.g., "85% (170 of 200)"
    'demographics' => [
        ['category' => string, 'distribution' => string]
    ],
    'key_findings' => [
        [
            'objective_addressed' => string,
            'finding' => string,
            'statistic' => string,      // e.g., "r = 0.72, p < 0.01"
            'interpretation' => string,
        ]
    ],
    'tables_extracted' => [
        ['title' => string, 'columns' => [string], 'rows' => [[string]]]
    ],
    'hypothesis_results' => [
        ['hypothesis' => string, 'result' => string, 'evidence' => string]
    ],
]
```

#### Discussion / Conclusion
```php
'extracted_data' => [
    'summary_of_findings' => [string],
    'literature_comparison' => [
        ['finding' => string, 'agrees_with' => string, 'differs_from' => string]
    ],
    'implications' => [
        'theoretical' => [string],
        'practical' => [string],
    ],
    'limitations' => [string],
    'recommendations' => [
        'practical' => [string],
        'future_research' => [string],
    ],
    'conclusion_statement' => string,
]
```

---

## Database Changes

### Migration

```php
// database/migrations/XXXX_add_extraction_to_defense_slide_decks.php

Schema::table('defense_slide_decks', function (Blueprint $table) {
    $table->json('extraction_data')->nullable()->after('slides_json');
    $table->string('extraction_status')->default('pending')->after('status');
});
```

### Updated Status Flow

```
queued → extracting → extracted → generating → outlined → rendering → ready
           ↓            ↓           ↓            ↓           ↓
        failed       failed      failed       failed      failed
```

### Model Update

```php
// app/Models/DefenseSlideDeck.php

protected $casts = [
    'slides_json' => 'array',
    'ai_models' => 'array',
    'extraction_data' => 'array',  // ADD
];
```

---

## AI Prompts

### Pass 1: Extraction Prompt (Example for Methodology)

```
You are an academic content analyzer. Extract structured data from this METHODOLOGY chapter.

=== CHAPTER CONTENT ===
{parsed_content}

=== PRE-EXTRACTED DATA ===
Tables found: 2
Statistics: ["n = 200", "α = 0.85", "85%"]
Citations: 15

=== EXTRACT THESE FIELDS ===
1. research_design.type - The specific research design
2. research_design.justification - Why this design was chosen
3. population.target - Target population
4. population.sampling_technique - Sampling method
5. population.sample_size - Sample size with calculation
6. data_collection.instrument - Data collection tool
7. data_collection.variables_measured - Variables measured
8. data_analysis.techniques - Analysis techniques used
9. data_analysis.software - Software package
10. validity_reliability - Reliability measures

=== OUTPUT ===
Return ONLY valid JSON. Use null for fields not found in content.
```

### Pass 2: Slide Generation Prompt

```
You are an academic defense coach creating a thesis defense slide deck.

=== PROJECT ===
Title: {title}
Topic: {topic}
University: {university}
Academic Level: {type}

=== EXTRACTED DATA ===
{extracted_data_json}

=== RULES ===
1. Use EXACT statistics from extracted data
2. Include actual sample sizes, p-values, percentages
3. Reference specific findings, NOT generic statements
4. If data is null, state "Data not available in project"

=== REQUIRED SLIDES (12-15 total) ===
1. Title Slide - Project title, student, supervisor, university, date
2. Research Problem & Gap - From introduction.problem_statement
3. Objectives - Verbatim from introduction.specific_objectives
4. Literature Framework - From literature_review.theoretical_framework
5. Methodology - From methodology.research_design, sample_size, software
6. Key Findings #1 - From results.key_findings[0] with actual statistics
7. Key Findings #2 - From results.key_findings[1] with actual statistics
8. Key Findings #3 - From results.key_findings[2] OR tables
9. Discussion - From discussion.literature_comparison
10. Implications - From discussion.implications
11. Limitations - From discussion.limitations
12. Recommendations - From discussion.recommendations
13. Conclusion - From discussion.conclusion_statement
14. Thank You / Q&A

=== OUTPUT ===
Return strict JSON:
{
  "slides": [
    {
      "title": "string",
      "bullets": ["string with actual data"],
      "layout": "title|bullets|two_column",
      "speaker_notes": "string",
      "charts": [],
      "tables": []
    }
  ]
}
```

---

## Implementation Plan

### Phase 1: Infrastructure (Day 1)

| Task | File | Action |
|------|------|--------|
| 1.1 | `database/migrations/XXXX_add_extraction_to_defense_slide_decks.php` | Create |
| 1.2 | `app/Models/DefenseSlideDeck.php` | Add casts |
| 1.3 | `app/Services/Defense/HtmlContentParser.php` | Create |
| 1.4 | `app/Services/Defense/ChapterTypeDetector.php` | Create |

### Phase 2: Extraction Service (Day 2)

| Task | File | Action |
|------|------|--------|
| 2.1 | `app/Services/Defense/DefenseContentExtractor.php` | Create |
| 2.2 | Test extraction on sample chapters | Manual test |

### Phase 3: Slide Generation (Day 3)

| Task | File | Action |
|------|------|--------|
| 3.1 | `app/Services/Defense/DefenseSlideDeckService.php` | Add `buildSlidePromptFromExtraction()` |
| 3.2 | `app/Jobs/GenerateDefenseDeckOutline.php` | Modify for two-pass |

### Phase 4: API & Sync (Day 4)

| Task | File | Action |
|------|------|--------|
| 4.1 | `app/Http/Controllers/DefenseDeckController.php` | Add sync endpoint |
| 4.2 | `routes/api.php` | Add sync route |
| 4.3 | `resources/js/pages/projects/Defense.vue` | Update status handling |

### Phase 5: Testing (Day 5)

| Task | File | Action |
|------|------|--------|
| 5.1 | `tests/Feature/DefenseContentExtractionTest.php` | Create |
| 5.2 | `tests/Feature/DefenseDeckGenerationTest.php` | Create |
| 5.3 | Full integration test | Run |

---

## Performance Optimizations

| Optimization | Benefit |
|-------------|---------|
| Use `gpt-4o-mini` for extraction | Faster, cheaper (Pass 1) |
| Use `gpt-4o` for generation | Higher quality (Pass 2) |
| Low temperature (0.1) for extraction | Consistent data |
| Pre-extract tables via HTML parsing | More reliable than AI |
| Synchronous endpoint option | Skip queue, < 2 min |
| Cache extractions by `chapter_id:updated_at` | Skip re-extraction |

---

## Error Handling & Fallbacks

### If Pass 1 Extraction Fails

```php
try {
    $extractedData = $extractor->extractFromProject($project);
} catch (\Throwable $e) {
    Log::warning('Extraction failed, using legacy approach', [
        'project_id' => $project->id,
        'error' => $e->getMessage(),
    ]);

    // Fall back to legacy single-pass
    $prompt = $deckService->buildSlidePrompt($project);
    // Continue with truncated content approach
}
```

### If Chapter Has Insufficient Content

```php
if ($chapter->word_count < 200) {
    return [
        'number' => $chapter->chapter_number,
        'type' => $chapterType,
        'extracted_data' => null,
        'skip_reason' => 'Insufficient content (< 200 words)',
    ];
}
```

---

## Files Summary

### Create

| File | Purpose |
|------|---------|
| `app/Services/Defense/HtmlContentParser.php` | Parse HTML semantically |
| `app/Services/Defense/ChapterTypeDetector.php` | Detect chapter types |
| `app/Services/Defense/DefenseContentExtractor.php` | Pass 1 orchestration |
| `database/migrations/XXXX_add_extraction_to_defense_slide_decks.php` | New columns |
| `tests/Feature/DefenseContentExtractionTest.php` | Extraction tests |

### Modify

| File | Changes |
|------|---------|
| `app/Models/DefenseSlideDeck.php` | Add casts |
| `app/Services/Defense/DefenseSlideDeckService.php` | Add `buildSlidePromptFromExtraction()` |
| `app/Jobs/GenerateDefenseDeckOutline.php` | Two-pass logic |
| `app/Http/Controllers/DefenseDeckController.php` | Sync endpoint |
| `routes/api.php` | Sync route |
| `resources/js/pages/projects/Defense.vue` | New status states |

---

## Expected Results

### Before (Current)
```
Slide: "Key Findings"
Bullets:
  - "Important results were discovered"
  - "Data analysis revealed insights"
  - "Findings support the hypothesis"
```

### After (Two-Pass)
```
Slide: "Student Satisfaction with E-Learning"
Bullets:
  - "78% of 245 respondents rated platform usability as 'good' or 'excellent'"
  - "Significant correlation between internet speed and satisfaction (r=0.67, p<0.01)"
  - "Mobile users reported 23% lower satisfaction than desktop users (t=3.42, p<0.05)"
Speaker Notes:
  - "Emphasize the 78% satisfaction rate as a key success metric"
  - "The correlation finding suggests infrastructure investment would improve outcomes"
```

---

## Timeline

| Phase | Duration | Deliverable |
|-------|----------|-------------|
| Phase 1: Infrastructure | 1 day | Migration, models, parsers |
| Phase 2: Extraction | 1 day | DefenseContentExtractor working |
| Phase 3: Generation | 1 day | Two-pass slides generating |
| Phase 4: API & Frontend | 1 day | Sync endpoint, UI updates |
| Phase 5: Testing | 1 day | Full test coverage |
| **Total** | **5 days** | Production-ready feature |
