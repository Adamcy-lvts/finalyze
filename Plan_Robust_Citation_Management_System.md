# Plan: Robust Citation Management System

## User Request
Ensure that papers collected and injected into prompts are saved with their full reference metadata (DOI, links, sources) so they can be used to generate accurate References sections without relying on extracting from inline citations.

## Problem Statement

Currently, the system has a critical gap: papers are collected with full metadata and injected into prompts, but when References sections are generated, they're extracted from AI-generated HTML using fragile regex parsing. This leads to:
- Inaccurate or incomplete references due to parsing failures
- No guarantee that cited papers match the injected whitelist
- No detection of AI hallucinations (citing non-existent papers)
- Fragmented data across CollectedPaper, Citation, and DocumentCitation models

**✅ Verified via [app/Services/PaperCollectionService.php](app/Services/PaperCollectionService.php):**
The `collected_papers` table stores comprehensive metadata from 5 APIs (Semantic Scholar, OpenAlex, arXiv, PubMed, CrossRef):
- **Citation essentials**: title, authors, year, venue, doi, url
- **Quality indicators**: citation_count, quality_score, is_open_access
- **Traceability**: source_api, paper_id, abstract, collected_at

All data needed for generating accurate, properly formatted references is already captured in `collected_papers`.

## User Requirements ✅

1. **Strict Whitelist Enforcement** - Only pre-collected papers can be cited
2. **Metadata-based References** - Generate from stored CollectedPaper/Citation records, not HTML parsing
3. **Pre-create DocumentCitation** - Create records when papers injected into prompts
4. **Validation** - Flag citations not in whitelist as [UNVERIFIED]

## Solution Architecture

### Core Concept
Transform from: `Paper Collection → Prompt Injection → AI Generation → HTML Parsing`
To: `Paper Collection → Citation Pre-Creation → Prompt Injection → AI Generation → Validation → Database-Driven References`

### Key Components

1. **New Service: CitationWhitelistService**
   - Syncs CollectedPaper → Citation with formatted references
   - Pre-creates DocumentCitation records when papers injected
   - Validates post-generation citations against whitelist
   - Flags unauthorized citations as [UNVERIFIED]

2. **Database Enhancements**
   - Add `collected_paper_id` to citations table (bidirectional link)
   - Add `injected_paper_ids`, `citation_whitelist`, `citations_validated_at` to chapters
   - Add `collected_paper_id`, `is_whitelisted` to document_citations

3. **Controller Integration**
   - Modify `getCollectedPapersForAI()` to prepare whitelist
   - Add post-generation validation hook
   - Pass chapter object through generation pipeline

4. **Reference Generation Update**
   - Replace HTML regex extraction with database queries
   - Use `DocumentCitation` → `Citation` → `getFormattedCitation()`
   - Maintain HTML parsing as fallback for backward compatibility

## Implementation Plan

### Phase 1: Database Migrations

**Migration 1: Add Citation ↔ CollectedPaper Link**
```sql
ALTER TABLE citations ADD COLUMN collected_paper_id BIGINT UNSIGNED NULL;
ALTER TABLE citations ADD FOREIGN KEY (collected_paper_id) REFERENCES collected_papers(id) ON DELETE SET NULL;
CREATE INDEX idx_citations_collected_paper_id ON citations(collected_paper_id);
```

**Migration 2: Add Whitelist Tracking to Chapters**
```sql
ALTER TABLE chapters ADD COLUMN injected_paper_ids JSON NULL;
ALTER TABLE chapters ADD COLUMN citation_whitelist JSON NULL;
ALTER TABLE chapters ADD COLUMN citations_validated_at TIMESTAMP NULL;
ALTER TABLE chapters ADD COLUMN citation_violations_count INT DEFAULT 0;
```

**Migration 3: Enhance DocumentCitations**
```sql
ALTER TABLE document_citations ADD COLUMN collected_paper_id BIGINT UNSIGNED NULL;
ALTER TABLE document_citations ADD COLUMN is_whitelisted BOOLEAN DEFAULT FALSE;
ALTER TABLE document_citations ADD FOREIGN KEY (collected_paper_id) REFERENCES collected_papers(id) ON DELETE SET NULL;
CREATE INDEX idx_doc_citations_chapter_whitelist ON document_citations(chapter_id, is_whitelisted);
```

### Phase 2: Create CitationWhitelistService

**File:** `/app/Services/CitationWhitelistService.php`

**Key Methods:**

1. `prepareWhitelistForChapter(Chapter $chapter, Collection $injectedPapers): array`
   - Syncs each CollectedPaper → Citation with formatted references
   - Pre-creates DocumentCitation records (placeholders)
   - Saves `injected_paper_ids` and `citation_whitelist` to chapter
   - Returns whitelist array for logging

2. `syncCollectedPaperToCitation(CollectedPaper $paper): Citation`
   - Checks for existing citation by `collected_paper_id` or `doi`
   - Creates Citation with full metadata if new
   - Returns Citation instance for linking

3. `validateChapterCitations(Chapter $chapter): array`
   - Extracts all inline citations from chapter content
   - Compares against `citation_whitelist`
   - Flags violations by replacing citations with `[UNVERIFIED]` marker
   - Updates `citation_violations_count`
   - Returns validation report

4. `extractInlineCitations(string $content): array`
   - Pattern: `/(Author(?:\s+et\s+al\.)?,?\s*\d{4}[a-z]?)/`
   - Returns array with citation text, position, author, year

### Phase 3: Modify ChapterController

**File:** `/app/Http/Controllers/ChapterController.php`

**Changes:**

1. **Update `getCollectedPapersForAI()` (line 3376)**
   - Add `?Chapter $chapter = null` parameter
   - After ranking papers, call:
     ```php
     if ($chapter) {
         app(CitationWhitelistService::class)
             ->prepareWhitelistForChapter($chapter, $ranked);
     }
     ```

2. **Update `buildProgressivePrompt()` (line 1763)**
   - Create/retrieve Chapter object before calling `getCollectedPapersForAI()`
   - Pass chapter object to enable whitelist preparation

3. **Add validation hook in chapter save methods**
   - After chapter content saved, call:
     ```php
     try {
         $this->validateGeneratedCitations($chapter);
     } catch (\Exception $e) {
         Log::error("Citation validation failed", ['chapter_id' => $chapter->id]);
         $chapter->update(['needs_review' => true]);
     }
     ```

### Phase 4: Update ChapterReferenceService

**File:** `/app/Services/ChapterReferenceService.php`

**Changes:**

1. **New method: `getChapterReferencesFromDatabase()`**
   ```php
   public function getChapterReferencesFromDatabase(Chapter $chapter, string $style = 'APA'): Collection
   {
       return $chapter->documentCitations()
           ->with('citation')
           ->whereHas('citation', fn($q) => $q->where('verification_status', 'verified'))
           ->where('is_whitelisted', true)
           ->get()
           ->map(function($docCitation) use ($style) {
               return [
                   'reference' => $docCitation->citation->getFormattedCitation(strtolower($style)),
                   'inline_text' => $docCitation->inline_text,
               ];
           })
           ->unique('reference')
           ->sortBy('reference');
   }
   ```

2. **Update `formatChapterReferencesSection()` (line 41)**
   - FIRST: Try `getChapterReferencesFromDatabase()`
   - FALLBACK: Use existing `extractReferencesFromHtml()` for backward compatibility
   - Log which method was used for monitoring

3. **Update `formatProjectReferencesSection()`**
   - Collect from ALL chapters using database method
   - Fall back to HTML parsing if no DocumentCitations exist

## Critical Files to Modify

1. **`/app/Services/CitationWhitelistService.php`** (NEW)
   - Core orchestration for whitelist management
   - ~300 lines of code

2. **`/app/Http/Controllers/ChapterController.php`** (MODIFY)
   - Update `getCollectedPapersForAI()` method (line 3376)
   - Update `buildProgressivePrompt()` method (line 1763)
   - Add validation hooks post-generation
   - ~50 lines changed

3. **`/app/Services/ChapterReferenceService.php`** (MODIFY)
   - Add `getChapterReferencesFromDatabase()` method
   - Update `formatChapterReferencesSection()` (line 41)
   - Update `formatProjectReferencesSection()` (line 230)
   - ~100 lines changed

4. **Database Migrations** (NEW - 3 files)
   - `YYYY_MM_DD_add_collected_paper_id_to_citations.php`
   - `YYYY_MM_DD_add_citation_tracking_to_chapters.php`
   - `YYYY_MM_DD_add_whitelist_to_document_citations.php`

## Implementation Steps (Development)

### Step 1: Database Setup
- [ ] Run 3 database migrations
- [ ] Verify schema changes applied correctly

### Step 2: Create Services
- [ ] Create CitationWhitelistService with 4 key methods
- [ ] Add unit tests for service methods

### Step 3: Update Controllers & Services
- [ ] Modify ChapterController (getCollectedPapersForAI, buildProgressivePrompt)
- [ ] Update ChapterReferenceService (add database method, update formatters)
- [ ] Add validation hooks

### Step 4: Testing
- [ ] Run unit tests
- [ ] Generate test chapter with paper collection
- [ ] Verify DocumentCitations created
- [ ] Verify References section generated from database
- [ ] Test validation flagging

## Error Handling & Fallbacks

### Graceful Degradation Layers
1. **Primary:** Database-driven citations from DocumentCitation records
2. **Fallback 1:** HTML extraction (existing behavior) if no DocumentCitations
3. **Fallback 2:** Project-level references JSON field
4. **Final:** Empty references with warning log

### Validation Failure Handling
- Never fail chapter generation due to validation errors
- Log validation failures for monitoring
- Flag chapter as `needs_review = true`
- Continue with content save

### Whitelist Preparation Errors
- Log errors but don't block paper injection
- Post-generation validation will catch issues
- User notification via needs_review flag

## Testing Strategy

### Unit Tests
- `CitationWhitelistServiceTest.php`
  - Test CollectedPaper → Citation sync
  - Test DocumentCitation pre-creation
  - Test whitelist validation logic
  - Test violation flagging

### Feature Tests
- `ChapterCitationIntegrationTest.php`
  - End-to-end chapter generation with whitelist
  - Reference section generation from database
  - Validation integration
  - Backward compatibility with HTML parsing

### Manual Testing Checklist
- [ ] Generate chapter with paper collection
- [ ] Verify DocumentCitations pre-created
- [ ] Check citation_whitelist saved to chapter
- [ ] Validate inline citations post-generation
- [ ] Export PDF with References section
- [ ] Test legacy chapter (no DocumentCitations) fallback
- [ ] Verify violation flagging works

## Success Criteria

- ✅ All new chapters have `citation_whitelist` populated
- ✅ No critical errors in whitelist preparation
- ✅ References section generated from database (not HTML)
- ✅ Validation correctly flags unauthorized citations as [UNVERIFIED]
- ✅ References include DOI, URL, and full metadata
- ✅ Backward compatibility: HTML fallback works for edge cases

## Verification Plan

After implementation, verify:

1. **Data Flow:**
   ```
   CollectedPaper exists → Citation created with collected_paper_id set
   → DocumentCitation pre-created when papers injected
   → Chapter saved with citation_whitelist array
   → Post-generation validation runs
   → Export uses database citations (not HTML parsing)
   ```

2. **Database Integrity:**
   ```sql
   -- Check bidirectional links
   SELECT COUNT(*) FROM citations WHERE collected_paper_id IS NOT NULL;
   SELECT COUNT(*) FROM document_citations WHERE is_whitelisted = TRUE;
   SELECT COUNT(*) FROM chapters WHERE citation_whitelist IS NOT NULL;
   ```

3. **Validation Works:**
   - Generate chapter with 3 injected papers
   - Manually edit content to add unauthorized citation
   - Run validation
   - Confirm `[UNVERIFIED]` flag added
   - Confirm `citation_violations_count` incremented

4. **References Accuracy:**
   - Export chapter PDF
   - Verify References section matches injected papers exactly
   - Check DOI, URL, authors, year all present
   - Confirm APA 7th edition formatting

## Rollback Plan (if needed)

If critical issues detected in development:

1. **Revert to HTML parsing** by commenting out database query in ChapterReferenceService
2. **Debug the issue** using logs and test data
3. **Fix and redeploy**

Database migrations are additive (no data loss), so rollback is safe. In development, can also rollback migrations with `php artisan migrate:rollback`.

---

## Summary

This plan transforms the citation system from fragile HTML parsing to robust database-driven reference generation, ensuring:

✅ **Accuracy**: Only pre-collected papers with verified metadata used
✅ **Validation**: AI hallucinations detected and flagged
✅ **Reliability**: Database queries replace regex parsing
✅ **Traceability**: Full audit trail from collection → citation → reference
✅ **Backward Compatibility**: Existing chapters continue working via fallback
