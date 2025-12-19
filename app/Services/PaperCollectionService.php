<?php

namespace App\Services;

use App\Models\CollectedPaper;
use App\Models\Project;
use App\Services\APIs\ArXivAPI;
use App\Services\APIs\CrossRefAPI;
use App\Services\APIs\OpenAlexAPI;
use App\Services\APIs\PubMedAPI;
use App\Services\APIs\SemanticScholarAPI;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaperCollectionService
{
    public function __construct(
        protected SemanticScholarAPI $semanticScholar,
        protected OpenAlexAPI $openAlex,
        protected ArXivAPI $arxiv,
        protected CrossRefAPI $crossRef,
        protected PubMedAPI $pubMed
    ) {}

    /**
     * Collect high-quality papers for a project topic from multiple sources
     */
    public function collectPapersForProject(Project $project): Collection
    {
        $topic = $project->topic;
        $field = $project->field_of_study ?? 'general';
        $academicLevel = $project->category->academic_levels[0] ?? 'undergraduate';

        Log::info("Collecting papers for project: {$project->title}", [
            'topic' => $topic,
            'field' => $field,
            'academic_level' => $academicLevel,
        ]);

        // Check cache first
        $cacheKey = 'papers_collection_'.md5($topic.'_'.$field.'_'.$academicLevel);
        $cachedCollection = Cache::get($cacheKey);

        if ($cachedCollection) {
            Log::info("Using cached paper collection for: {$topic}");

            return collect($cachedCollection);
        }

        // Collect from multiple sources
        $papers = collect();

        try {
            // Primary source: Semantic Scholar (best for computer science, AI, etc.)
            $semanticPapers = $this->collectFromSemanticScholar($topic);
            $papers = $papers->merge($semanticPapers);

            // Secondary: OpenAlex (broad coverage)
            $openAlexPapers = $this->collectFromOpenAlex($topic);
            $papers = $papers->merge($openAlexPapers);

            // Preprints / free PDFs: arXiv
            $arxivPapers = $this->collectFromArXiv($topic);
            $papers = $papers->merge($arxivPapers);

            // Tertiary: PubMed (for medical/health topics)
            if ($this->isMedicalField($field)) {
                $pubMedPapers = $this->collectFromPubMed($topic);
                $papers = $papers->merge($pubMedPapers);
            }

            // CrossRef for additional validation
            $crossRefPapers = $this->collectFromCrossRef($topic);
            $papers = $papers->merge($crossRefPapers);

        } catch (\Exception $e) {
            Log::error('Error collecting papers: '.$e->getMessage());
        }

        // Deduplicate and rank
        $uniquePapers = $this->deduplicateAndRank($papers);

        // Cache the results for 24 hours
        Cache::put($cacheKey, $uniquePapers->toArray(), now()->addHours(24));

        Log::info("Collected {$uniquePapers->count()} unique papers for: {$topic}");

        return $uniquePapers;
    }

    /**
     * Collect papers from Semantic Scholar
     */
    public function collectFromSemanticScholar(string $topic): Collection
    {
        try {
            $cooldownUntil = Cache::get('semantic_scholar_rate_limited_until');
            if ($cooldownUntil && now()->lessThan($cooldownUntil)) {
                Log::info('Semantic Scholar temporarily rate-limited; skipping this cycle', [
                    'until' => (string) $cooldownUntil,
                ]);

                return collect();
            }

            $limit = (int) config('ai.paper_collection.limits.semantic_scholar', 15);
            $limit = max(1, min(100, $limit));

            $filters = [
                'venue_quality' => 'high',
                'min_citations' => 5,
                'publication_years' => [date('Y') - 10, date('Y')], // Last 10 years
            ];

            $queries = $this->buildSearchQueries($topic);
            $papers = [];
            foreach ($queries as $query) {
                $batch = $this->semanticScholar->searchByTopic($query, $limit, $filters);
                $papers = array_merge($papers, $batch);
                if (count($papers) >= $limit) {
                    break;
                }
            }

            return collect($papers)->map(function ($paper) {
                return $this->formatPaper($paper, 'semantic_scholar');
            });

        } catch (\Exception $e) {
            // Semantic Scholar returns 429 frequently without an API key; back off briefly.
            $message = $e->getMessage();
            if (str_contains($message, '429') || str_contains(strtolower($message), 'too many requests')) {
                Cache::put('semantic_scholar_rate_limited_until', now()->addMinutes(15), now()->addMinutes(20));
            }
            Log::warning('SemanticScholar collection failed: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Collect papers from OpenAlex
     */
    public function collectFromOpenAlex(string $topic): Collection
    {
        try {
            $limit = (int) config('ai.paper_collection.limits.openalex', 10);
            $limit = max(1, min(100, $limit));

            $filters = [
                'year_from' => (int) date('Y') - 10,
                'year_to' => (int) date('Y'),
                // OpenAlex search is broad; keep filters light to avoid zero results.
                'min_citations' => 0,
                'open_access' => false,
            ];

            $queries = $this->buildSearchQueries($topic);
            $papers = [];
            foreach ($queries as $query) {
                $batch = $this->openAlex->searchWorks($query, $limit, $filters);
                $papers = array_merge($papers, $batch);
                if (count($papers) >= $limit) {
                    break;
                }
            }

            return collect($papers)->map(function ($paper) {
                return $this->formatPaper($paper, 'openalex');
            });

        } catch (\Exception $e) {
            Log::warning('OpenAlex collection failed: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Collect papers from arXiv (free preprints / PDFs)
     */
    public function collectFromArXiv(string $topic): Collection
    {
        try {
            $limit = (int) config('ai.paper_collection.limits.arxiv', 10);
            $limit = max(1, min(100, $limit));

            $queries = $this->buildSearchQueries($topic);
            $papers = [];
            foreach ($queries as $query) {
                $batch = $this->arxiv->searchByTopic($query, $limit);
                $papers = array_merge($papers, $batch);
                if (count($papers) >= $limit) {
                    break;
                }
            }

            return collect($papers)->map(function ($paper) {
                return $this->formatPaper($paper, 'arxiv');
            });
        } catch (\Exception $e) {
            Log::warning('ArXiv collection failed: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Collect papers from PubMed
     */
    public function collectFromPubMed(string $topic): Collection
    {
        try {
            $limit = (int) config('ai.paper_collection.limits.pubmed', 8);
            $limit = max(1, min(100, $limit));

            $filters = [
                'year_from' => date('Y') - 10, // Last 10 years
            ];

            $papers = $this->pubMed->searchWorks($topic, $limit, $filters);

            return collect($papers)->map(function ($paper) {
                return $this->formatPaper($paper, 'pubmed');
            });

        } catch (\Exception $e) {
            Log::warning('PubMed collection failed: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Collect papers from CrossRef
     */
    public function collectFromCrossRef(string $topic): Collection
    {
        try {
            $limit = (int) config('ai.paper_collection.limits.crossref', 8);
            $limit = max(1, min(100, $limit));

            $filters = [
                'from-pub-date' => date('Y') - 10,
            ];

            $queries = $this->buildSearchQueries($topic);
            $papers = [];
            foreach ($queries as $query) {
                $batch = $this->crossRef->searchWorks($query, $limit, $filters);
                $papers = array_merge($papers, $batch);
                if (count($papers) >= $limit) {
                    break;
                }
            }

            return collect($papers)->map(function ($paper) {
                return $this->formatPaper($paper, 'crossref');
            });

        } catch (\Exception $e) {
            Log::warning('CrossRef collection failed: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Format paper data from different sources into consistent structure
     */
    protected function formatPaper(array $paper, string $source): array
    {
        switch ($source) {
            case 'semantic_scholar':
                return [
                    'title' => $paper['title'] ?? 'Unknown Title',
                    'authors' => $this->formatAuthors($paper['authors'] ?? []),
                    'year' => $paper['year'] ?? null,
                    'venue' => $paper['venue'] ?? null,
                    'doi' => $paper['doi'] ?? null,
                    'url' => $paper['url'] ?? null,
                    'abstract' => $paper['abstract'] ?? null,
                    'citation_count' => $paper['citationCount'] ?? 0,
                    'quality_score' => $paper['generation_score'] ?? 0,
                    'source' => $source,
                    'paper_id' => $paper['paperId'] ?? null,
                    'is_open_access' => $paper['isOpenAccess'] ?? false,
                ];

            case 'openalex':
                return [
                    'title' => $paper['title'] ?? 'Unknown Title',
                    'authors' => $this->formatAuthorsList($paper['authors'] ?? []),
                    'year' => $paper['year'] ?? null,
                    'venue' => $paper['journal'] ?? null,
                    'doi' => $paper['doi'] ?? null,
                    'url' => $paper['oa_url'] ?? ($paper['url'] ?? null),
                    'abstract' => $paper['abstract'] ?? null,
                    'citation_count' => $paper['citation_count'] ?? 0,
                    'quality_score' => $paper['generation_score'] ?? $this->calculateOpenAlexScoreFromFormatted($paper),
                    'source' => $source,
                    'paper_id' => $paper['openalex_id'] ?? null,
                    'is_open_access' => $paper['is_oa'] ?? false,
                ];

            case 'arxiv':
                return [
                    'title' => $paper['title'] ?? 'Unknown Title',
                    'authors' => $this->formatAuthorsList($paper['authors'] ?? []),
                    'year' => $paper['year'] ?? null,
                    'venue' => $paper['journal_ref'] ?? 'arXiv',
                    'doi' => $paper['doi'] ?? null,
                    // Prefer the free PDF URL for downstream use
                    'url' => $paper['pdf_url'] ?? ($paper['url'] ?? null),
                    'abstract' => $paper['abstract'] ?? null,
                    'citation_count' => 0,
                    'quality_score' => $this->calculateArXivScore($paper),
                    'source' => $source,
                    'paper_id' => $paper['arxiv_id'] ?? null,
                    'is_open_access' => true,
                ];

            case 'pubmed':
                return [
                    'title' => $paper['title'] ?? 'Unknown Title',
                    'authors' => is_array($paper['authors']) ? implode(', ', array_slice($paper['authors'], 0, 3)) : 'Unknown Authors',
                    'year' => $paper['year'] ?? null,
                    'venue' => $paper['journal'] ?? null,
                    'doi' => $paper['doi'] ?? null,
                    'url' => $paper['url'] ?? "https://pubmed.ncbi.nlm.nih.gov/{$paper['pubmed_id']}",
                    'abstract' => $paper['abstract'] ?? null,
                    'citation_count' => 0, // PubMed doesn't provide citation counts
                    'quality_score' => $this->calculatePubMedScore($paper),
                    'source' => $source,
                    'paper_id' => $paper['pubmed_id'] ?? null,
                    'is_open_access' => false,
                ];

            case 'crossref':
                return [
                    'title' => $paper['title'][0] ?? 'Unknown Title',
                    'authors' => $this->formatCrossRefAuthors($paper['author'] ?? []),
                    'year' => $paper['published-print']['date-parts'][0][0] ??
                             $paper['published-online']['date-parts'][0][0] ?? null,
                    'venue' => $paper['container-title'][0] ?? null,
                    'doi' => $paper['DOI'] ?? null,
                    'url' => $paper['URL'] ?? null,
                    'abstract' => null, // CrossRef doesn't provide abstracts
                    'citation_count' => $paper['is-referenced-by-count'] ?? 0,
                    'quality_score' => $this->calculateCrossRefScore($paper),
                    'source' => $source,
                    'paper_id' => $paper['DOI'] ?? null,
                    'is_open_access' => false,
                ];

            default:
                return [];
        }
    }

    /**
     * Remove duplicates and rank papers by quality
     */
    public function deduplicateAndRank(Collection $papers): Collection
    {
        $minQuality = (float) config('ai.paper_collection.min_quality_score', 0.3);
        $minQuality = max(0.0, min(1.0, $minQuality));
        $maxPapers = (int) config('ai.paper_collection.max_papers', 20);
        $maxPapers = max(1, min(200, $maxPapers));

        // Group by similar titles (handle slight variations)
        $grouped = $papers->groupBy(function ($paper) {
            return $this->normalizeTitle($paper['title']);
        });

        // Pick best version of each paper
        $deduplicated = $grouped->map(function ($group) {
            return $group->sortByDesc('quality_score')->first();
        });

        // Rank for AI generation
        return $deduplicated
            ->filter(function ($paper) use ($minQuality) {
                return ($paper['quality_score'] ?? 0) > $minQuality; // Minimum quality threshold
            })
            ->sortByDesc('quality_score')
            ->take($maxPapers)
            ->values();
    }

    /**
     * Build progressively simpler search queries from a topic string.
     */
    private function buildSearchQueries(string $topic): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            return [];
        }

        $clean = strtolower($topic);
        $clean = preg_replace('/[^a-z0-9\\s]+/', ' ', $clean);
        $clean = preg_replace('/\\s+/', ' ', trim((string) $clean));

        $words = array_values(array_filter(explode(' ', $clean), fn ($w) => $w !== ''));
        $stop = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'into', 'using', 'use',
            'system', 'study', 'research', 'analysis', 'development', 'challenges',
            'technical', 'technological', 'based',
        ];
        $stopSet = array_fill_keys($stop, true);

        $keywords = [];
        foreach ($words as $w) {
            if (strlen($w) < 3) {
                continue;
            }
            if (isset($stopSet[$w])) {
                continue;
            }
            $keywords[] = $w;
        }
        $keywords = array_values(array_unique($keywords));

        $queries = [];
        $queries[] = $topic;
        if ($clean !== '' && $clean !== $topic) {
            $queries[] = $clean;
        }
        if (! empty($keywords)) {
            $queries[] = implode(' ', array_slice($keywords, 0, 10));
            $queries[] = implode(' ', array_slice($keywords, 0, 6));
            $queries[] = implode(' ', array_slice($keywords, 0, 4));
        }

        return array_values(array_unique(array_filter($queries, fn ($q) => is_string($q) && trim($q) !== '')));
    }

    /**
     * Normalize title for deduplication
     */
    protected function normalizeTitle(string $title): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $title));
    }

    /**
     * Check if field is medical/health related
     */
    protected function isMedicalField(string $field): bool
    {
        $medicalFields = [
            'medicine', 'health', 'biology', 'biochemistry', 'pharmacology',
            'nursing', 'public health', 'epidemiology', 'medical',
        ];

        return collect($medicalFields)->contains(function ($medField) use ($field) {
            return stripos($field, $medField) !== false;
        });
    }

    /**
     * Format authors from different sources
     */
    protected function formatAuthors(array $authors): string
    {
        if (empty($authors)) {
            return 'Unknown Authors';
        }

        return collect($authors)->take(3)->map(function ($author) {
            return $author['name'] ?? 'Unknown Author';
        })->join(', ');
    }

    protected function formatOpenAlexAuthors(array $authorships): string
    {
        if (empty($authorships)) {
            return 'Unknown Authors';
        }

        return collect($authorships)->take(3)->map(function ($authorship) {
            return $authorship['author']['display_name'] ?? 'Unknown Author';
        })->join(', ');
    }

    protected function formatAuthorsList(array $authors): string
    {
        if (empty($authors)) {
            return 'Unknown Authors';
        }

        return collect($authors)
            ->filter(fn ($author) => is_string($author) && trim($author) !== '')
            ->take(3)
            ->join(', ') ?: 'Unknown Authors';
    }

    protected function formatPubMedAuthors(array $authors): string
    {
        if (empty($authors)) {
            return 'Unknown Authors';
        }

        return collect($authors)->take(3)->map(function ($author) {
            $name = ($author['fore_name'] ?? '').' '.($author['last_name'] ?? '');

            return trim($name) ?: 'Unknown Author';
        })->join(', ');
    }

    protected function formatCrossRefAuthors(array $authors): string
    {
        if (empty($authors)) {
            return 'Unknown Authors';
        }

        return collect($authors)->take(3)->map(function ($author) {
            $name = ($author['given'] ?? '').' '.($author['family'] ?? '');

            return trim($name) ?: 'Unknown Author';
        })->join(', ');
    }

    /**
     * Calculate quality scores for different sources
     */
    protected function calculateOpenAlexScore(array $paper): float
    {
        $score = 0.0;

        // Citation count (0.4 weight)
        $citations = $paper['cited_by_count'] ?? 0;
        $score += min(0.4, ($citations / 100) * 0.4);

        // Open access bonus (0.2 weight)
        if ($paper['open_access']['is_oa'] ?? false) {
            $score += 0.2;
        }

        // Recent publication bonus (0.2 weight)
        $year = $paper['publication_year'] ?? 0;
        if ($year >= date('Y') - 5) {
            $score += 0.2;
        } elseif ($year >= date('Y') - 10) {
            $score += 0.1;
        }

        // Venue quality (0.2 weight)
        if (! empty($paper['host_venue']['display_name'])) {
            $score += 0.2;
        }

        return round($score, 2);
    }

    protected function calculateOpenAlexScoreFromFormatted(array $paper): float
    {
        $score = 0.0;

        $citations = (int) ($paper['citation_count'] ?? 0);
        $score += min(0.4, ($citations / 100) * 0.4);

        if (! empty($paper['is_oa'])) {
            $score += 0.2;
        }

        $year = (int) ($paper['year'] ?? 0);
        if ($year >= (int) date('Y') - 5) {
            $score += 0.2;
        } elseif ($year >= (int) date('Y') - 10) {
            $score += 0.1;
        }

        if (! empty($paper['journal'])) {
            $score += 0.2;
        }

        return round($score, 2);
    }

    protected function calculateArXivScore(array $paper): float
    {
        $score = 0.2; // Base score for arXiv (free access)

        $relevance = (float) ($paper['relevance_score'] ?? 0.0);
        $score += min(0.5, $relevance * 0.5);

        $year = (int) ($paper['year'] ?? 0);
        if ($year >= (int) date('Y') - 5) {
            $score += 0.2;
        } elseif ($year >= (int) date('Y') - 10) {
            $score += 0.1;
        }

        if (! empty($paper['doi'])) {
            $score += 0.1;
        }

        if (! empty($paper['abstract'])) {
            $score += 0.1;
        }

        return round(min(1.0, $score), 2);
    }

    protected function calculatePubMedScore(array $paper): float
    {
        $score = 0.3; // Base score for being in PubMed

        // Has abstract (0.3 weight)
        if (! empty($paper['abstract'])) {
            $score += 0.3;
        }

        // Recent publication (0.2 weight)
        $year = $paper['year'] ?? 0;
        if ($year >= date('Y') - 5) {
            $score += 0.2;
        } elseif ($year >= date('Y') - 10) {
            $score += 0.1;
        }

        // Has DOI (0.2 weight)
        if (! empty($paper['doi'])) {
            $score += 0.2;
        }

        return round($score, 2);
    }

    protected function calculateCrossRefScore(array $paper): float
    {
        $score = 0.0;

        // Citation count (0.5 weight)
        $citations = $paper['is-referenced-by-count'] ?? 0;
        $score += min(0.5, ($citations / 50) * 0.5);

        // Has DOI (0.2 weight)
        if (! empty($paper['DOI'])) {
            $score += 0.2;
        }

        // Recent publication (0.2 weight)
        $year = $paper['published-print']['date-parts'][0][0] ??
                $paper['published-online']['date-parts'][0][0] ?? 0;
        if ($year >= date('Y') - 5) {
            $score += 0.2;
        } elseif ($year >= date('Y') - 10) {
            $score += 0.1;
        }

        // Journal quality (0.1 weight)
        if (! empty($paper['container-title'][0])) {
            $score += 0.1;
        }

        return round($score, 2);
    }

    /**
     * Store collected papers in the collected_papers table
     */
    public function storePapersForProject(Project $project, Collection $papers): void
    {
        foreach ($papers as $paper) {
            CollectedPaper::createOrUpdatePaper([
                'user_id' => $project->user_id,
                'project_id' => $project->id,
                'title' => $paper['title'],
                'authors' => $paper['authors'],
                'year' => $paper['year'],
                'venue' => $paper['venue'],
                'doi' => $paper['doi'],
                'url' => $paper['url'],
                'abstract' => $paper['abstract'],
                'citation_count' => $paper['citation_count'],
                'quality_score' => $paper['quality_score'],
                'source_api' => $paper['source'],
                'paper_id' => $paper['paper_id'],
                'is_open_access' => $paper['is_open_access'],
                'collected_at' => now(),
            ]);
        }

        Log::info("Stored {$papers->count()} collected papers for project: {$project->title}");
    }
}
