<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\DocumentCitation;
use Illuminate\Support\Facades\Log;

class ChapterCitationService
{
    public function __construct(
        private ChapterReferenceService $chapterReferenceService,
        private CitationWhitelistService $citationWhitelistService
    ) {}

    public function validateGeneratedCitations(Chapter $chapter): void
    {
        try {
            DocumentCitation::where('chapter_id', $chapter->id)
                ->where('source', 'ai_generated')
                ->delete();

            $this->citationWhitelistService->validateChapterCitations(
                $chapter,
                $chapter->content ?? ''
            );

            $this->appendReferencesIfMissing($chapter);
        } catch (\Exception $e) {
            Log::error('Citation validation failed', [
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function appendReferencesIfMissing(Chapter $chapter): void
    {
        $content = (string) ($chapter->content ?? '');
        if ($content === '') {
            return;
        }

        if ($this->chapterHasReferencesSection($content)) {
            return;
        }

        $referencesHtml = $this->chapterReferenceService
            ->formatChapterReferencesFromDatabase($chapter);

        if ($referencesHtml === '') {
            return;
        }

        $chapter->update([
            'content' => $content."\n\n".$referencesHtml,
        ]);
    }

    private function chapterHasReferencesSection(string $html): bool
    {
        if (preg_match('/<div[^>]*class="references-section"[^>]*>/i', $html)) {
            return true;
        }

        if (preg_match('/<h[12][^>]*>\\s*REFERENCES?\\s*<\\/h[12]>/i', $html)) {
            return true;
        }

        return preg_match('/<p[^>]*>\\s*(?:<strong[^>]*>|<b[^>]*>)?\\s*REFERENCES?\\s*(?:<\\/strong>|<\\/b>)?\\s*<\\/p>/i', $html) === 1;
    }
}
