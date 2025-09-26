<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\ChapterAnalysisResult;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Log;

class ChapterAnalysisService
{
    /**
     * 100-point scoring system constants
     */
    public const GRAMMAR_STYLE_MAX = 0; // Disabled - needs sophisticated analysis

    public const READABILITY_MAX = 0; // Disabled - simplistic approach

    public const STRUCTURE_MAX = 25; // +5 from readability

    public const CITATIONS_MAX = 30; // +5 from readability

    public const ORIGINALITY_MAX = 30; // +5 from readability

    public const ARGUMENT_MAX = 15; // +5 from readability

    public const COMPLETION_THRESHOLD = 80;

    public function __construct(
        private ChapterContentAnalysisService $contentAnalysisService,
        private OpenAIProvider $aiProvider
    ) {}

    /**
     * Perform comprehensive chapter analysis and return result
     */
    public function analyzeChapter(Chapter $chapter): ChapterAnalysisResult
    {
        Log::info('Starting comprehensive chapter analysis', [
            'chapter_id' => $chapter->id,
            'chapter_title' => $chapter->title,
            'word_count' => strlen($chapter->content ?? ''),
        ]);

        // Get basic content metrics from existing service
        $basicAnalysis = $this->contentAnalysisService->analyzeChapterContent($chapter);

        // Perform detailed academic quality analysis
        $grammarScore = ['score' => 0, 'issues' => ['Grammar analysis disabled - needs enhancement']]; // Disabled
        $readabilityScore = ['score' => 0, 'metrics' => ['Readability analysis disabled - simplistic approach']]; // Disabled
        $structureScore = $this->analyzeStructure($chapter);
        $citationsScore = $this->analyzeCitations($chapter);
        $originalityScore = $this->analyzeOriginality($chapter);
        $argumentScore = $this->analyzeArgumentStrength($chapter);

        // Calculate total score (grammar and readability disabled)
        $totalScore = $structureScore['score'] + $citationsScore['score'] +
                     $originalityScore['score'] + $argumentScore['score'];

        // Create analysis result
        $result = ChapterAnalysisResult::create([
            'chapter_id' => $chapter->id,
            'grammar_style_score' => $grammarScore['score'],
            'readability_score' => $readabilityScore['score'],
            'structure_score' => $structureScore['score'],
            'citations_score' => $citationsScore['score'],
            'originality_score' => $originalityScore['score'],
            'argument_score' => $argumentScore['score'],
            'total_score' => $totalScore,
            'word_count' => $basicAnalysis['word_count'],
            'character_count' => $basicAnalysis['character_count'],
            'paragraph_count' => $basicAnalysis['paragraph_count'],
            'sentence_count' => $basicAnalysis['sentence_count'],
            'citation_count' => $chapter->documentCitations()->count(),
            'verified_citation_count' => $chapter->verifiedCitations()->count(),
            'completion_percentage' => $basicAnalysis['completion_percentage'],
            'reading_time_minutes' => $basicAnalysis['reading_time_minutes'],
            'meets_defense_requirement' => $basicAnalysis['meets_defense_requirement'],
            'meets_completion_threshold' => $totalScore >= self::COMPLETION_THRESHOLD,
            'grammar_issues' => $grammarScore['issues'],
            'readability_metrics' => $readabilityScore['metrics'],
            'structure_feedback' => $structureScore['feedback'],
            'citation_analysis' => $citationsScore['analysis'],
            'suggestions' => $this->generateSuggestions([
                'grammar' => $grammarScore,
                'readability' => $readabilityScore,
                'structure' => $structureScore,
                'citations' => $citationsScore,
                'originality' => $originalityScore,
                'argument' => $argumentScore,
            ]),
            'analyzed_at' => now(),
        ]);

        Log::info('Chapter analysis completed', [
            'chapter_id' => $chapter->id,
            'total_score' => $totalScore,
            'meets_threshold' => $totalScore >= self::COMPLETION_THRESHOLD,
            'analysis_id' => $result->id,
        ]);

        return $result;
    }

    // Grammar analysis method removed - disabled for basic approach

    // Readability analysis method removed - disabled for simplistic approach

    /**
     * AI-powered chapter structure and organization analysis (25 points max)
     */
    private function analyzeStructure(Chapter $chapter): array
    {
        $content = $chapter->content ?? '';

        if (empty($content)) {
            return ['score' => 0, 'feedback' => ['No content to analyze']];
        }

        // Strip HTML tags for AI analysis
        $cleanContent = strip_tags($content);

        // Prepare AI prompt for structure analysis
        $prompt = $this->buildStructureAnalysisPrompt($cleanContent, $chapter->title);

        try {
            Log::info('Starting AI structure analysis', [
                'chapter_id' => $chapter->id,
                'content_length' => strlen($cleanContent),
            ]);

            $aiResponse = $this->aiProvider->generate($prompt, [
                'model' => 'gpt-4o',
                'temperature' => 0.3, // Lower temperature for more consistent analysis
                'max_tokens' => 1500,
            ]);

            // Parse AI response to extract score and feedback
            $analysisResult = $this->parseStructureAnalysisResponse($aiResponse);

            Log::info('AI structure analysis completed', [
                'chapter_id' => $chapter->id,
                'score' => $analysisResult['score'],
            ]);

            return $analysisResult;

        } catch (\Exception $e) {
            Log::error('AI structure analysis failed', [
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to basic analysis if AI fails
            return $this->basicStructureAnalysis($content);
        }
    }

    /**
     * Build AI prompt for structure analysis
     */
    private function buildStructureAnalysisPrompt(string $content, string $title): string
    {
        return "As an expert academic writing evaluator, analyze the structure and organization of this chapter titled '{$title}'.

Evaluate the following aspects and provide a score out of 25 points:

1. **Logical Flow & Coherence (8 points)**: Does the chapter progress logically from introduction to conclusion? Are ideas connected smoothly?

2. **Section Organization (7 points)**: Are there clear sections/headings? Is the content properly divided into logical segments?

3. **Paragraph Structure (5 points)**: Are paragraphs well-organized with clear topic sentences? Appropriate length and focus?

4. **Transitions & Connectivity (5 points)**: Are there effective transitions between sections and paragraphs? Does the text flow naturally?

Chapter Content:
{$content}

Respond in this exact JSON format:
{
  \"score\": [0-25],
  \"feedback\": [
    \"Specific feedback point 1\",
    \"Specific feedback point 2\",
    \"Specific feedback point 3\"
  ],
  \"breakdown\": {
    \"logical_flow\": [0-8],
    \"section_organization\": [0-7],
    \"paragraph_structure\": [0-5],
    \"transitions\": [0-5]
  }
}";
    }

    /**
     * Parse AI response for structure analysis
     */
    private function parseStructureAnalysisResponse(string $response): array
    {
        // Try to extract JSON from response
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonResponse = json_decode($matches[0], true);

            if ($jsonResponse && isset($jsonResponse['score'], $jsonResponse['feedback'])) {
                return [
                    'score' => min(self::STRUCTURE_MAX, max(0, (int) $jsonResponse['score'])),
                    'feedback' => $jsonResponse['feedback'] ?? [],
                    'breakdown' => $jsonResponse['breakdown'] ?? [],
                ];
            }
        }

        // Fallback parsing if JSON fails
        preg_match('/score["\']?\s*[:=]\s*(\d+)/i', $response, $scoreMatch);
        $score = isset($scoreMatch[1]) ? min(self::STRUCTURE_MAX, max(0, (int) $scoreMatch[1])) : 0;

        return [
            'score' => $score,
            'feedback' => ['AI analysis completed but response format was unclear'],
            'breakdown' => [],
        ];
    }

    /**
     * Fallback basic structure analysis if AI fails
     */
    private function basicStructureAnalysis(string $content): array
    {
        $score = 10; // Give some basic points if AI fails
        $feedback = ['Basic structure analysis (AI unavailable)'];

        // Simple checks
        $paragraphs = array_filter(preg_split('/\n\s*\n/', strip_tags($content)));
        if (count($paragraphs) >= 3) {
            $score += 5;
            $feedback[] = 'Adequate paragraph structure';
        }

        return [
            'score' => min(self::STRUCTURE_MAX, $score),
            'feedback' => $feedback,
        ];
    }

    /**
     * AI-powered citations and references analysis (30 points max)
     */
    private function analyzeCitations(Chapter $chapter): array
    {
        $content = strip_tags($chapter->content ?? '');

        if (empty($content)) {
            return ['score' => 0, 'analysis' => ['No content to analyze']];
        }

        try {
            Log::info('Starting AI citation analysis', [
                'chapter_id' => $chapter->id,
                'chapter_title' => $chapter->title,
                'content_length' => strlen($content),
            ]);

            $aiAnalysis = $this->aiCitationAnalysis($content, $chapter->title);

            Log::info('AI citation analysis completed', [
                'chapter_id' => $chapter->id,
                'score' => $aiAnalysis['score'],
            ]);

            return $aiAnalysis;

        } catch (\Exception $e) {
            Log::error('AI citation analysis failed', [
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to basic text-based analysis
            return $this->basicTextCitationAnalysis($content, $chapter->title);
        }
    }

    /**
     * AI-powered citation analysis that considers chapter type and context
     */
    private function aiCitationAnalysis(string $content, string $title): array
    {
        $prompt = "As an expert academic writing evaluator, analyze the citation quality in this chapter titled '{$title}'.

**Important Context**: Different chapter types have different citation expectations:
- Introduction: 5-15 citations (background, context)
- Literature Review: 30-100+ citations (comprehensive survey)
- Methodology: 10-25 citations (methods, protocols)
- Results/Analysis: 5-20 citations (interpretation, comparison)
- Discussion/Conclusion: 15-40 citations (synthesis, implications)

Analyze and score out of 30 points:

1. **Citation Presence & Appropriateness (12 points)**:
   - Are citations present in the text?
   - Is the citation density appropriate for this chapter type?
   - Look for patterns like (Author, Year), [1], (Smith et al., 2023), etc.

2. **Citation Integration & Flow (8 points)**:
   - Are citations smoothly integrated into sentences?
   - Do they support the arguments being made?
   - Are they used to build credibility, not just decoration?

3. **Citation Quality & Relevance (5 points)**:
   - Do citations appear relevant to the claims?
   - Are they used to support arguments rather than just list facts?
   - Is there evidence of scholarly source usage?

4. **Citation Context & Purpose (5 points)**:
   - Is sufficient context provided for citations?
   - Are citations used strategically to strengthen arguments?
   - Do they show engagement with existing literature?

Chapter Content:
{$content}

Respond in this exact JSON format:
{
  \"score\": [0-30],
  \"analysis\": [
    \"Specific feedback about citation presence and density\",
    \"Specific feedback about citation integration\",
    \"Specific feedback about citation quality and usage\"
  ],
  \"breakdown\": {
    \"presence_appropriateness\": [0-12],
    \"integration_flow\": [0-8],
    \"quality_relevance\": [0-5],
    \"context_purpose\": [0-5]
  }
}";

        $aiResponse = $this->aiProvider->generate($prompt, [
            'model' => 'gpt-4o',
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ]);

        return $this->parseCitationAnalysisResponse($aiResponse);
    }

    /**
     * Parse AI citation analysis response
     */
    private function parseCitationAnalysisResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonResponse = json_decode($matches[0], true);

            if ($jsonResponse && isset($jsonResponse['score'], $jsonResponse['analysis'])) {
                return [
                    'score' => min(self::CITATIONS_MAX, max(0, (int) $jsonResponse['score'])),
                    'analysis' => $jsonResponse['analysis'] ?? [],
                    'breakdown' => $jsonResponse['breakdown'] ?? [],
                ];
            }
        }

        // Fallback parsing
        preg_match('/score["\']?\s*[:=]\s*(\d+)/i', $response, $scoreMatch);
        $score = isset($scoreMatch[1]) ? min(self::CITATIONS_MAX, max(0, (int) $scoreMatch[1])) : 10;

        return [
            'score' => $score,
            'analysis' => ['AI citation analysis completed'],
            'breakdown' => [],
        ];
    }

    /**
     * Fallback text-based citation analysis
     */
    private function basicTextCitationAnalysis(string $content, string $title): array
    {
        $score = 0;
        $analysis = [];

        // Simple pattern matching for common citation formats
        $citationPatterns = [
            '/\([^)]*\d{4}[^)]*\)/',          // (Author, 2024) format
            '/\[[^\]]*\d{4}[^\]]*\]/',        // [Author, 2024] format
            '/\[[0-9]+\]/',                   // [1], [2] format
            '/\b[A-Z][a-z]+ et al\.,?\s*\d{4}/',  // Smith et al., 2024
        ];

        $citationCount = 0;
        foreach ($citationPatterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            $citationCount += count($matches[0]);
        }

        // Score based on citation presence
        if ($citationCount >= 10) {
            $score += 15;
            $analysis[] = "Good citation presence detected ($citationCount citations)";
        } elseif ($citationCount >= 5) {
            $score += 10;
            $analysis[] = "Moderate citation presence ($citationCount citations)";
        } elseif ($citationCount >= 1) {
            $score += 5;
            $analysis[] = "Some citations present ($citationCount citations)";
        } else {
            $analysis[] = 'No clear citation patterns detected';
        }

        // Basic integration check
        $words = str_word_count($content);
        if ($words > 0 && $citationCount > 0) {
            $citationDensity = ($citationCount / $words) * 1000; // Citations per 1000 words

            if ($citationDensity >= 5) {
                $score += 8;
                $analysis[] = 'Good citation density for academic writing';
            } elseif ($citationDensity >= 2) {
                $score += 5;
                $analysis[] = 'Moderate citation density';
            }
        }

        return [
            'score' => min(self::CITATIONS_MAX, $score),
            'analysis' => $analysis,
        ];
    }

    // Old citation integration method removed - replaced with AI analysis

    /**
     * AI-powered content originality analysis (30 points max)
     */
    private function analyzeOriginality(Chapter $chapter): array
    {
        $content = strip_tags($chapter->content ?? '');

        if (empty($content)) {
            return ['score' => 0, 'analysis' => ['No content to analyze']];
        }

        try {
            Log::info('Starting AI originality analysis', [
                'chapter_id' => $chapter->id,
                'content_length' => strlen($content),
            ]);

            $aiAnalysis = $this->aiOriginalityAnalysis($content, $chapter->title);

            Log::info('AI originality analysis completed', [
                'chapter_id' => $chapter->id,
                'score' => $aiAnalysis['score'],
            ]);

            return $aiAnalysis;

        } catch (\Exception $e) {
            Log::error('AI originality analysis failed', [
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to basic analysis
            return $this->basicOriginalityAnalysis($content);
        }
    }

    /**
     * AI-powered originality analysis
     */
    private function aiOriginalityAnalysis(string $content, string $title): array
    {
        $prompt = "As an expert academic writing evaluator, analyze the originality and critical thinking in this chapter titled '{$title}'.

Evaluate these aspects and provide a score out of 30 points:

1. **Original Analysis vs. Description (12 points)**: Does the author provide original analysis and interpretation rather than just describing or summarizing sources? Is there evidence of critical thinking?

2. **Synthesis and Connection of Ideas (8 points)**: Does the author connect ideas from multiple sources in original ways? Are there novel insights or perspectives?

3. **Personal Academic Voice (5 points)**: Does the author demonstrate their own understanding and perspective while maintaining academic objectivity?

4. **Critical Evaluation (5 points)**: Does the author critically evaluate sources, identify gaps, or challenge existing ideas appropriately?

Chapter Content:
{$content}

Respond in this exact JSON format:
{
  \"score\": [0-30],
  \"analysis\": [
    \"Specific feedback about original analysis\",
    \"Specific feedback about synthesis\",
    \"Specific feedback about critical thinking\"
  ],
  \"breakdown\": {
    \"original_analysis\": [0-12],
    \"synthesis\": [0-8],
    \"academic_voice\": [0-5],
    \"critical_evaluation\": [0-5]
  }
}";

        $aiResponse = $this->aiProvider->generate($prompt, [
            'model' => 'gpt-4o',
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ]);

        return $this->parseOriginalityAnalysisResponse($aiResponse);
    }

    /**
     * Parse AI originality analysis response
     */
    private function parseOriginalityAnalysisResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonResponse = json_decode($matches[0], true);

            if ($jsonResponse && isset($jsonResponse['score'], $jsonResponse['analysis'])) {
                return [
                    'score' => min(self::ORIGINALITY_MAX, max(0, (int) $jsonResponse['score'])),
                    'analysis' => $jsonResponse['analysis'] ?? [],
                    'breakdown' => $jsonResponse['breakdown'] ?? [],
                ];
            }
        }

        // Fallback parsing
        preg_match('/score["\']?\s*[:=]\s*(\d+)/i', $response, $scoreMatch);
        $score = isset($scoreMatch[1]) ? min(self::ORIGINALITY_MAX, max(0, (int) $scoreMatch[1])) : 15;

        return [
            'score' => $score,
            'analysis' => ['AI originality analysis completed'],
            'breakdown' => [],
        ];
    }

    /**
     * Fallback basic originality analysis
     */
    private function basicOriginalityAnalysis(string $content): array
    {
        $words = str_word_count($content);
        $score = 0;
        $analysis = [];

        // Check for analytical language vs descriptive
        $analyticalTerms = [
            'analyze', 'examine', 'evaluate', 'assess', 'critique', 'argue',
            'suggests', 'implies', 'demonstrates', 'reveals', 'indicates',
        ];

        $analyticalCount = 0;
        $lowerContent = strtolower($content);
        foreach ($analyticalTerms as $term) {
            $analyticalCount += substr_count($lowerContent, $term);
        }

        $analyticalDensity = $words > 0 ? ($analyticalCount / ($words / 100)) : 0;

        if ($analyticalDensity >= 2) {
            $score += 20;
            $analysis[] = 'Good analytical approach detected';
        } elseif ($analyticalDensity >= 1) {
            $score += 12;
            $analysis[] = 'Some analytical content, could be enhanced';
        } else {
            $score += 5;
            $analysis[] = 'Focus more on analysis rather than description';
        }

        // Check for direct quotation overuse
        $quotationMarks = substr_count($content, '"') + substr_count($content, '"') + substr_count($content, '"');
        $quotationRatio = $words > 0 ? ($quotationMarks / $words) * 100 : 0;

        if ($quotationRatio <= 5) {
            $score += 8;
            $analysis[] = 'Appropriate use of direct quotations';
        } elseif ($quotationRatio <= 10) {
            $score += 5;
            $analysis[] = 'Moderate quotation use';
        } else {
            $score += 2;
            $analysis[] = 'High quotation usage - focus more on original analysis';
        }

        return [
            'score' => min(self::ORIGINALITY_MAX, $score),
            'analysis' => $analysis,
        ];
    }

    /**
     * AI-powered argument strength and coherence analysis (15 points max)
     */
    private function analyzeArgumentStrength(Chapter $chapter): array
    {
        $content = strip_tags($chapter->content ?? '');

        if (empty($content)) {
            return ['score' => 0, 'analysis' => ['No content to analyze']];
        }

        try {
            Log::info('Starting AI argument strength analysis', [
                'chapter_id' => $chapter->id,
                'content_length' => strlen($content),
            ]);

            $aiAnalysis = $this->aiArgumentStrengthAnalysis($content, $chapter->title);

            Log::info('AI argument strength analysis completed', [
                'chapter_id' => $chapter->id,
                'score' => $aiAnalysis['score'],
            ]);

            return $aiAnalysis;

        } catch (\Exception $e) {
            Log::error('AI argument strength analysis failed', [
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to basic analysis
            return $this->basicArgumentAnalysis($content);
        }
    }

    /**
     * AI-powered argument strength analysis
     */
    private function aiArgumentStrengthAnalysis(string $content, string $title): array
    {
        $prompt = "As an expert academic writing evaluator, analyze the argument strength and coherence in this chapter titled '{$title}'.

Evaluate these aspects and provide a score out of 15 points:

1. **Thesis Clarity and Strength (5 points)**: Is there a clear, well-defined thesis or main argument? Is it intellectually rigorous and defensible?

2. **Evidence Quality and Relevance (4 points)**: Is the evidence presented strong, relevant, and sufficient to support the claims? Are multiple types of evidence used effectively?

3. **Logical Reasoning and Flow (3 points)**: Do the arguments follow logically? Are there clear causal relationships and logical connections between ideas?

4. **Counter-argument Consideration (3 points)**: Does the author acknowledge and address potential counter-arguments or alternative viewpoints? Are they handled effectively?

Chapter Content:
{$content}

Respond in this exact JSON format:
{
  \"score\": [0-15],
  \"analysis\": [
    \"Specific feedback about thesis strength\",
    \"Specific feedback about evidence quality\",
    \"Specific feedback about logical reasoning\",
    \"Specific feedback about counter-arguments\"
  ],
  \"breakdown\": {
    \"thesis_clarity\": [0-5],
    \"evidence_quality\": [0-4],
    \"logical_reasoning\": [0-3],
    \"counter_arguments\": [0-3]
  }
}";

        $aiResponse = $this->aiProvider->generate($prompt, [
            'model' => 'gpt-4o',
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ]);

        return $this->parseArgumentAnalysisResponse($aiResponse);
    }

    /**
     * Parse AI argument analysis response
     */
    private function parseArgumentAnalysisResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonResponse = json_decode($matches[0], true);

            if ($jsonResponse && isset($jsonResponse['score'], $jsonResponse['analysis'])) {
                return [
                    'score' => min(self::ARGUMENT_MAX, max(0, (int) $jsonResponse['score'])),
                    'analysis' => $jsonResponse['analysis'] ?? [],
                    'breakdown' => $jsonResponse['breakdown'] ?? [],
                ];
            }
        }

        // Fallback parsing
        preg_match('/score["\']?\s*[:=]\s*(\d+)/i', $response, $scoreMatch);
        $score = isset($scoreMatch[1]) ? min(self::ARGUMENT_MAX, max(0, (int) $scoreMatch[1])) : 8;

        return [
            'score' => $score,
            'analysis' => ['AI argument analysis completed'],
            'breakdown' => [],
        ];
    }

    /**
     * Fallback basic argument analysis
     */
    private function basicArgumentAnalysis(string $content): array
    {
        $score = 0;
        $analysis = [];
        $lowerContent = strtolower($content);

        // Check for clear thesis/argument indicators
        $argumentIndicators = [
            'argue', 'contend', 'propose', 'suggest', 'maintain',
            'thesis', 'hypothesis', 'claim', 'assertion',
        ];

        $argumentTermsFound = 0;
        foreach ($argumentIndicators as $indicator) {
            if (strpos($lowerContent, $indicator) !== false) {
                $argumentTermsFound++;
            }
        }

        if ($argumentTermsFound >= 3) {
            $score += 6;
            $analysis[] = 'Clear argumentative structure detected';
        } elseif ($argumentTermsFound >= 1) {
            $score += 3;
            $analysis[] = 'Some argument development present';
        } else {
            $score += 1;
            $analysis[] = 'Develop clearer arguments and thesis statements';
        }

        // Check for evidence presentation
        $evidenceTerms = [
            'evidence', 'data', 'research shows', 'studies indicate',
            'according to', 'findings', 'results',
        ];

        $evidenceTermsFound = 0;
        foreach ($evidenceTerms as $term) {
            if (stripos($content, $term) !== false) {
                $evidenceTermsFound++;
            }
        }

        if ($evidenceTermsFound >= 3) {
            $score += 5;
            $analysis[] = 'Good use of evidence';
        } elseif ($evidenceTermsFound >= 1) {
            $score += 3;
            $analysis[] = 'Some evidence present';
        } else {
            $score += 1;
            $analysis[] = 'Include more evidence';
        }

        return [
            'score' => min(self::ARGUMENT_MAX, $score),
            'analysis' => $analysis,
        ];
    }

    /**
     * Generate improvement suggestions based on all analyses
     */
    private function generateSuggestions(array $scores): array
    {
        $suggestions = [];

        foreach ($scores as $category => $data) {
            $maxScore = $this->getMaxScore($category);

            // Skip disabled categories (max score = 0)
            if ($maxScore <= 0) {
                continue;
            }

            $percentage = ($data['score'] / $maxScore) * 100;

            if ($percentage < 70) {
                $suggestions[$category] = $this->getCategorySuggestions($category, $data);
            }
        }

        // Add general suggestions
        if (count($suggestions) > 3) {
            $suggestions['general'] = [
                'Focus on the areas with lowest scores first',
                'Consider seeking feedback from peers or advisors',
                'Review academic writing guidelines for your field',
            ];
        }

        return $suggestions;
    }

    /**
     * Helper methods
     */
    private function getMaxScore(string $category): int
    {
        return match ($category) {
            'grammar' => self::GRAMMAR_STYLE_MAX,
            'readability' => self::READABILITY_MAX,
            'structure' => self::STRUCTURE_MAX,
            'citations' => self::CITATIONS_MAX,
            'originality' => self::ORIGINALITY_MAX,
            'argument' => self::ARGUMENT_MAX,
            default => 0
        };
    }

    private function getCategorySuggestions(string $category, array $data): array
    {
        return match ($category) {
            'grammar' => [
                'Review sentence structure and length variety',
                'Use more academic vocabulary and formal tone',
                'Add transition words between paragraphs',
                'Check for consistency in formatting and style',
            ],
            'readability' => [
                'Vary sentence lengths for better flow',
                'Balance complex and simple sentences',
                'Ensure appropriate vocabulary level for audience',
                'Break up very long paragraphs',
            ],
            'structure' => [
                'Add clear headings and subheadings',
                'Use more transition words between sections',
                'Ensure logical progression of ideas',
                'Create stronger paragraph topic sentences',
            ],
            'citations' => [
                'Add more citations to support claims',
                'Verify all citation sources',
                'Integrate citations better into text',
                'Ensure citations directly support your arguments',
            ],
            'originality' => [
                'Add more original analysis and interpretation',
                'Reduce reliance on direct quotations',
                'Develop your own critical perspective',
                'Connect ideas in new ways',
            ],
            'argument' => [
                'Develop clearer thesis statements',
                'Add more evidence to support claims',
                'Address potential counterarguments',
                'Strengthen logical connections between ideas',
            ],
            default => []
        };
    }

    private function estimateSyllables(string $text): int
    {
        $text = strtolower(preg_replace('/[^a-zA-Z\s]/', '', $text));
        $words = explode(' ', $text);
        $syllables = 0;

        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }

            // Simple syllable estimation
            $vowels = preg_match_all('/[aeiouy]+/', $word);
            $syllables += max(1, $vowels);
        }

        return $syllables;
    }

    private function calculateVariance(array $numbers): float
    {
        if (empty($numbers)) {
            return 0;
        }

        $mean = array_sum($numbers) / count($numbers);
        $variance = array_sum(array_map(fn ($n) => pow($n - $mean, 2), $numbers)) / count($numbers);

        return $variance;
    }

    private function countComplexWords(string $content): int
    {
        $words = str_word_count($content, 1);
        $complexCount = 0;

        foreach ($words as $word) {
            if (strlen($word) >= 7 || $this->estimateSyllables($word) >= 3) {
                $complexCount++;
            }
        }

        return $complexCount;
    }

    private function analyzeCitationContexts(string $content): array
    {
        // Simple heuristic for citation integration
        $citationPatterns = [
            '/\([^)]*\d{4}[^)]*\)/',  // (Author, 2024) format
            '/\[[^\]]*\d{4}[^\]]*\]/', // [Author, 2024] format
        ];

        $citationCount = 0;
        $wellIntegratedCount = 0;

        foreach ($citationPatterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[0] as $citation) {
                $citationCount++;

                // Check if citation is well integrated (preceded by relevant text)
                $position = strpos($content, $citation);
                if ($position > 50) {
                    $precedingText = substr($content, max(0, $position - 50), 50);
                    if (preg_match('/\w+\s+\w+/', $precedingText)) {
                        $wellIntegratedCount++;
                    }
                }
            }
        }

        return [
            'total_citations' => $citationCount,
            'well_integrated' => $citationCount > 0 ? $wellIntegratedCount / $citationCount : 0,
        ];
    }
}
