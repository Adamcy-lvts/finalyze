import axios from 'axios';

export interface BaseSuggestion {
    id: string;
    type: 'generate-section' | 'expand' | 'improve' | 'rephrase' | 'cite' | 'restructure' | 'reference' | 'conclusion';
    title: string;
    description: string;
    action: string;
    priority: 'high' | 'medium' | 'low';
    confidence: number;
    section?: string;
    style?: string;
}

export interface ScoredSuggestion extends BaseSuggestion {
    score: number;
    reasoning: string;
    context?: {
        wordCount: number;
        sectionCount: number;
        hasReferences: boolean;
        chapterProgress: number;
    };
}

export interface ChapterAnalysis {
    status: 'EMPTY' | 'PARTIAL' | 'COMPLETE' | 'NEEDS_REVIEW';
    recommendation: string;
    section: {
        number: string;
        name: string;
        description: string;
    };
    rationale: string;
    show_section_button: boolean;
    show_full_chapter_button: boolean;
    completion_percentage?: number;
    word_count_progress?: {
        current: number;
        target: number;
        percentage: number;
    };
    content_quality_score?: number;
    missing_elements?: string[];
}

export interface ContentMetrics {
    wordCount: number;
    paragraphCount: number;
    sectionCount: number;
    hasReferences: boolean;
    hasConclusion: boolean;
    hasIntroduction: boolean;
    academicToneScore: number;
    readabilityScore: number;
    coherenceScore: number;
}

interface CacheEntry<T> {
    data: T;
    timestamp: number;
    hash: string;
}

class SmartSuggestionsService {
    private cache = new Map<string, CacheEntry<any>>();
    private readonly CACHE_TTL = 5 * 60 * 1000; // 5 minutes
    private readonly DEBOUNCE_DELAY = 300;
    private debounceTimeouts = new Map<string, NodeJS.Timeout>();

    // Content analysis patterns
    private readonly SECTION_PATTERNS = {
        introduction: [/^(introduction|overview|background)/i, /\b(introduce|overview|context)\b/gi],
        literature: [/^(literature|related work|background)/i, /\b(literature|studies|research)\b/gi],
        methodology: [/^(methodology|method|approach)/i, /\b(method|approach|technique)\b/gi],
        results: [/^(results|findings|analysis)/i, /\b(result|finding|data)\b/gi],
        discussion: [/^(discussion|interpretation)/i, /\b(discuss|interpret|implication)\b/gi],
        conclusion: [/^(conclusion|summary)/i, /\b(conclude|summary|final)\b/gi]
    };

    private readonly QUALITY_INDICATORS = {
        academic: /\b(research|study|analysis|methodology|hypothesis|literature)\b/gi,
        citations: /\[[0-9]+\]|\([^)]+\d{4}[^)]*\)/g,
        transitions: /\b(however|furthermore|moreover|therefore|consequently|in contrast)\b/gi,
        evidence: /\b(according to|studies show|research indicates|data suggests)\b/gi
    };

    private generateContentHash(content: string): string {
        let hash = 0;
        for (let i = 0; i < content.length; i++) {
            const char = content.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(16);
    }

    private getCachedResult<T>(key: string, content: string): T | null {
        const cached = this.cache.get(key);
        if (!cached) return null;

        const contentHash = this.generateContentHash(content);
        const isExpired = Date.now() - cached.timestamp > this.CACHE_TTL;
        const isStale = cached.hash !== contentHash;

        if (isExpired || isStale) {
            this.cache.delete(key);
            return null;
        }

        return cached.data;
    }

    private setCachedResult<T>(key: string, content: string, data: T): void {
        const contentHash = this.generateContentHash(content);
        this.cache.set(key, {
            data,
            timestamp: Date.now(),
            hash: contentHash
        });
    }

    private debounce<T extends (...args: any[]) => Promise<any>>(
        key: string,
        fn: T,
        delay: number = this.DEBOUNCE_DELAY
    ): T {
        return ((...args: Parameters<T>) => {
            return new Promise<Awaited<ReturnType<T>>>((resolve, reject) => {
                const existingTimeout = this.debounceTimeouts.get(key);
                if (existingTimeout) {
                    clearTimeout(existingTimeout);
                }

                const timeout = setTimeout(async () => {
                    try {
                        const result = await fn(...args);
                        resolve(result);
                    } catch (error) {
                        reject(error);
                    }
                    this.debounceTimeouts.delete(key);
                }, delay);

                this.debounceTimeouts.set(key, timeout);
            });
        }) as T;
    }

    public analyzeContent(content: string): ContentMetrics {
        const cacheKey = `content-metrics-${this.generateContentHash(content)}`;
        const cached = this.getCachedResult<ContentMetrics>(cacheKey, content);
        if (cached) return cached;

        const metrics: ContentMetrics = {
            wordCount: content.trim().split(/\s+/).length,
            paragraphCount: content.split(/\n\s*\n/).length,
            sectionCount: this.countSections(content),
            hasReferences: this.hasReferences(content),
            hasConclusion: this.hasSection(content, 'conclusion'),
            hasIntroduction: this.hasSection(content, 'introduction'),
            academicToneScore: this.calculateAcademicTone(content),
            readabilityScore: this.calculateReadability(content),
            coherenceScore: this.calculateCoherence(content)
        };

        this.setCachedResult(cacheKey, content, metrics);
        return metrics;
    }

    private countSections(content: string): number {
        const headingPattern = /^(#{1,6}|\d+\.\d*|\b[A-Z][a-zA-Z\s]+:)/gm;
        const matches = content.match(headingPattern);
        return matches ? matches.length : 1;
    }

    private hasReferences(content: string): boolean {
        return this.QUALITY_INDICATORS.citations.test(content);
    }

    private hasSection(content: string, sectionType: keyof typeof this.SECTION_PATTERNS): boolean {
        const patterns = this.SECTION_PATTERNS[sectionType];
        return patterns.some(pattern => pattern.test(content));
    }

    private calculateAcademicTone(content: string): number {
        const academicMatches = (content.match(this.QUALITY_INDICATORS.academic) || []).length;
        const totalWords = content.split(/\s+/).length;
        return Math.min((academicMatches / totalWords) * 100, 100);
    }

    private calculateReadability(content: string): number {
        const sentences = content.split(/[.!?]+/).length;
        const words = content.split(/\s+/).length;
        const avgWordsPerSentence = words / sentences;
        
        // Flesch Reading Ease approximation for academic content
        return Math.max(0, Math.min(100, 206.835 - (1.015 * avgWordsPerSentence)));
    }

    private calculateCoherence(content: string): number {
        const transitionMatches = (content.match(this.QUALITY_INDICATORS.transitions) || []).length;
        const paragraphs = content.split(/\n\s*\n/).length;
        return Math.min((transitionMatches / paragraphs) * 50, 100);
    }

    public async getChapterAnalysis(
        projectId: number,
        chapterId: number,
        content: string
    ): Promise<ChapterAnalysis> {
        const cacheKey = `chapter-analysis-${projectId}-${chapterId}`;
        const cached = this.getCachedResult<ChapterAnalysis>(cacheKey, content);
        if (cached) return cached;

        try {
            const { data } = await axios.post(`/api/projects/${projectId}/chapters/${chapterId}/suggest-section`, {
                current_content: content
            });

            if (data.success && data.analysis) {
                console.log('✅ Using structured project outline data:', {
                    projectId,
                    chapterId,
                    status: data.analysis.status,
                    completion: data.analysis.completion_percentage,
                    structured: data.structured
                });

                // Prioritize structured analysis from the API
                const analysis: ChapterAnalysis = {
                    status: data.analysis.status,
                    recommendation: data.analysis.recommendation,
                    section: data.analysis.section,
                    rationale: data.analysis.rationale,
                    show_section_button: data.analysis.show_section_button,
                    show_full_chapter_button: data.analysis.show_full_chapter_button,
                    completion_percentage: data.analysis.completion_percentage,
                    word_count_progress: data.analysis.word_count_progress,
                    content_quality_score: this.calculateOverallQuality(content),
                    missing_elements: this.identifyMissingElements(content)
                };
                
                this.setCachedResult(cacheKey, content, analysis);
                return analysis;
            }
        } catch (error) {
            console.error('Structured chapter analysis failed, using fallback:', error);
        }

        // Only use fallback analysis if API fails
        console.warn('Using fallback chapter analysis - API may not have project outline configured');
        return this.generateFallbackAnalysis(content);
    }

    private calculateOverallQuality(content: string): number {
        const metrics = this.analyzeContent(content);
        
        const scores = [
            metrics.academicToneScore * 0.3,
            metrics.readabilityScore * 0.2,
            metrics.coherenceScore * 0.25,
            (metrics.hasReferences ? 100 : 0) * 0.15,
            (metrics.hasIntroduction && metrics.hasConclusion ? 100 : 0) * 0.1
        ];

        return scores.reduce((sum, score) => sum + score, 0);
    }

    private identifyMissingElements(content: string): string[] {
        const missing: string[] = [];
        const metrics = this.analyzeContent(content);

        if (!metrics.hasIntroduction) missing.push('Introduction section');
        if (!metrics.hasReferences) missing.push('Citations and references');
        if (!metrics.hasConclusion) missing.push('Conclusion section');
        if (metrics.coherenceScore < 30) missing.push('Transition sentences');
        if (metrics.academicToneScore < 20) missing.push('Academic vocabulary');

        return missing;
    }

    private generateFallbackAnalysis(content: string): ChapterAnalysis {
        const metrics = this.analyzeContent(content);
        const isEmpty = !content.trim();
        
        let nextSection = 'introduction';
        let sectionNumber = '1.1';
        
        if (!isEmpty) {
            if (!metrics.hasIntroduction) {
                nextSection = 'introduction';
                sectionNumber = '1.1';
            } else if (metrics.sectionCount < 3) {
                nextSection = 'content';
                sectionNumber = '1.2';
            } else if (!metrics.hasConclusion) {
                nextSection = 'conclusion';
                sectionNumber = '1.3';
            }
        }

        // More realistic completion calculation
        const completionPercentage = isEmpty ? 0 : Math.min(
            (metrics.sectionCount / 6) * 100, // Changed from 5 to 6 sections for more realistic calculation
            100
        );

        // Consider a chapter complete only if it has most essential elements
        const isComplete = !isEmpty && 
            metrics.hasIntroduction && 
            metrics.hasConclusion && 
            metrics.wordCount > 1500 && 
            metrics.sectionCount >= 4;

        const shouldShowSectionButton = !isComplete && (!isEmpty || metrics.wordCount > 50);
        const shouldShowFullChapterButton = isEmpty && metrics.wordCount < 50;

        return {
            status: isEmpty ? 'EMPTY' : (isComplete ? 'COMPLETE' : 'PARTIAL'),
            recommendation: isEmpty ? 'START_CHAPTER' : (isComplete ? 'CHAPTER_COMPLETE' : 'CONTINUE_WRITING'),
            section: {
                number: sectionNumber,
                name: this.getSectionDisplayName(nextSection),
                description: this.getSectionDescription(nextSection)
            },
            rationale: isEmpty ? 
                'Chapter is empty - start with an introduction' : 
                (isComplete ? 
                    `Chapter appears complete with ${metrics.sectionCount} sections and ${metrics.wordCount} words` :
                    `Chapter is ${completionPercentage.toFixed(0)}% complete - continue writing`
                ),
            show_section_button: shouldShowSectionButton,
            show_full_chapter_button: shouldShowFullChapterButton,
            completion_percentage: completionPercentage,
            word_count_progress: {
                current: metrics.wordCount,
                target: Math.max(2000, metrics.wordCount * 1.2), // More realistic target
                percentage: Math.min((metrics.wordCount / 2000) * 100, 100)
            },
            content_quality_score: this.calculateOverallQuality(content),
            missing_elements: this.identifyMissingElements(content)
        };
    }

    private getSectionDisplayName(sectionId: string): string {
        const names = {
            introduction: 'Introduction',
            literature: 'Literature Review',
            methodology: 'Methodology',
            results: 'Results',
            discussion: 'Discussion',
            conclusion: 'Conclusion',
            content: 'Main Content'
        };
        return names[sectionId as keyof typeof names] || 'Next Section';
    }

    private getSectionDescription(sectionId: string): string {
        const descriptions = {
            introduction: 'Overview and objectives of this chapter',
            literature: 'Review of relevant research and literature',
            methodology: 'Research methods and approach',
            results: 'Findings and data analysis',
            discussion: 'Interpretation and implications',
            conclusion: 'Summary and transition to next chapter',
            content: 'Main chapter content and analysis'
        };
        return descriptions[sectionId as keyof typeof descriptions] || 'Continue developing this section';
    }

    public async generateSmartSuggestions(
        content: string,
        selectedText: string = '',
        chapterAnalysis?: ChapterAnalysis
    ): Promise<ScoredSuggestion[]> {
        const debouncedGenerate = this.debounce(
            'smart-suggestions',
            this._generateSmartSuggestions.bind(this)
        );

        return debouncedGenerate(content, selectedText, chapterAnalysis);
    }

    private async _generateSmartSuggestions(
        content: string,
        selectedText: string = '',
        chapterAnalysis?: ChapterAnalysis
    ): Promise<ScoredSuggestion[]> {
        const cacheKey = `suggestions-${selectedText ? 'with-selection' : 'general'}`;
        const cached = this.getCachedResult<ScoredSuggestion[]>(cacheKey, content + selectedText);
        if (cached) return cached;

        const metrics = this.analyzeContent(content);
        const suggestions: BaseSuggestion[] = [];

        // Generate context-aware suggestions
        suggestions.push(...this.generateStructuralSuggestions(metrics, chapterAnalysis));
        suggestions.push(...this.generateContentSuggestions(content, metrics));
        suggestions.push(...this.generateQualitySuggestions(metrics));
        
        if (selectedText) {
            suggestions.push(...this.generateSelectionSuggestions(selectedText, metrics));
        }

        // Score and rank suggestions
        const scoredSuggestions = suggestions
            .map(suggestion => this.scoreSuggestion(suggestion, metrics, content, selectedText))
            .sort((a, b) => b.score - a.score)
            .slice(0, 6); // Limit to top 6 suggestions

        this.setCachedResult(cacheKey, content + selectedText, scoredSuggestions);
        return scoredSuggestions;
    }

    private generateStructuralSuggestions(
        metrics: ContentMetrics,
        chapterAnalysis?: ChapterAnalysis
    ): BaseSuggestion[] {
        const suggestions: BaseSuggestion[] = [];

        if (chapterAnalysis?.show_section_button && chapterAnalysis.section.name !== 'NONE') {
            suggestions.push({
                id: 'generate-next-section',
                type: 'generate-section',
                title: `Add ${chapterAnalysis.section.name}`,
                description: chapterAnalysis.section.description,
                action: 'generate-section',
                priority: 'high',
                confidence: 0.9,
                section: chapterAnalysis.section.name.toLowerCase().replace(/\s+/g, '_')
            });
        }

        if (!metrics.hasIntroduction) {
            suggestions.push({
                id: 'add-introduction',
                type: 'generate-section',
                title: 'Add Introduction',
                description: 'Start with a clear introduction to set the context',
                action: 'generate-section',
                priority: 'high',
                confidence: 0.95,
                section: 'introduction'
            });
        }

        if (!metrics.hasConclusion && metrics.wordCount > 500) {
            suggestions.push({
                id: 'add-conclusion',
                type: 'generate-section',
                title: 'Add Conclusion',
                description: 'Summarize key points and provide closure',
                action: 'generate-section',
                priority: 'medium',
                confidence: 0.8,
                section: 'conclusion'
            });
        }

        return suggestions;
    }

    private generateContentSuggestions(content: string, metrics: ContentMetrics): BaseSuggestion[] {
        const suggestions: BaseSuggestion[] = [];

        if (metrics.wordCount > 200 && metrics.wordCount < 800) {
            suggestions.push({
                id: 'expand-content',
                type: 'expand',
                title: 'Expand Content',
                description: 'Add more depth and detail to existing sections',
                action: 'expand',
                priority: 'medium',
                confidence: 0.7
            });
        }

        if (metrics.wordCount > 800) {
            suggestions.push({
                id: 'improve-structure',
                type: 'improve',
                title: 'Improve Structure',
                description: 'Enhance organization and academic flow',
                action: 'improve',
                priority: 'medium',
                confidence: 0.75
            });
        }

        if (metrics.coherenceScore < 40) {
            suggestions.push({
                id: 'add-transitions',
                type: 'restructure',
                title: 'Add Transitions',
                description: 'Improve flow between paragraphs and sections',
                action: 'improve',
                priority: 'medium',
                confidence: 0.8
            });
        }

        return suggestions;
    }

    private generateQualitySuggestions(metrics: ContentMetrics): BaseSuggestion[] {
        const suggestions: BaseSuggestion[] = [];

        if (!metrics.hasReferences && metrics.wordCount > 300) {
            suggestions.push({
                id: 'add-citations',
                type: 'cite',
                title: 'Add Citations',
                description: 'Support your arguments with academic references',
                action: 'cite',
                priority: 'high',
                confidence: 0.85
            });
        }

        if (metrics.academicToneScore < 30) {
            suggestions.push({
                id: 'enhance-academic-tone',
                type: 'rephrase',
                title: 'Enhance Academic Tone',
                description: 'Use more formal academic language',
                action: 'rephrase',
                priority: 'medium',
                confidence: 0.7,
                style: 'Academic Formal'
            });
        }

        return suggestions;
    }

    private generateSelectionSuggestions(
        selectedText: string,
        metrics: ContentMetrics
    ): BaseSuggestion[] {
        const suggestions: BaseSuggestion[] = [];

        if (selectedText.length > 10) {
            suggestions.push({
                id: 'expand-selection',
                type: 'expand',
                title: 'Expand Selection',
                description: 'Add more detail to the selected text',
                action: 'expand',
                priority: 'high',
                confidence: 0.9
            });

            suggestions.push({
                id: 'rephrase-selection',
                type: 'rephrase',
                title: 'Rephrase Selection',
                description: 'Improve clarity and academic style',
                action: 'rephrase',
                priority: 'medium',
                confidence: 0.8
            });
        }

        return suggestions;
    }

    private scoreSuggestion(
        suggestion: BaseSuggestion,
        metrics: ContentMetrics,
        content: string,
        selectedText: string
    ): ScoredSuggestion {
        let score = suggestion.confidence * 100;
        let reasoning = `Base confidence: ${(suggestion.confidence * 100).toFixed(0)}%`;

        // Priority weighting
        const priorityWeights = { high: 1.3, medium: 1.0, low: 0.7 };
        score *= priorityWeights[suggestion.priority];
        reasoning += ` | Priority: ${suggestion.priority}`;

        // Context-specific scoring
        if (suggestion.type === 'cite' && metrics.academicToneScore > 50) {
            score *= 1.2;
            reasoning += ' | Academic context bonus';
        }

        if (suggestion.type === 'expand' && selectedText) {
            score *= 1.4;
            reasoning += ' | Selected text bonus';
        }

        if (suggestion.type === 'generate-section' && metrics.wordCount < 100) {
            score *= 1.5;
            reasoning += ' | Early stage bonus';
        }

        // Quality penalties
        if (metrics.wordCount > 2000 && suggestion.type === 'expand') {
            score *= 0.7;
            reasoning += ' | Length penalty';
        }

        return {
            ...suggestion,
            score: Math.round(score),
            reasoning,
            context: {
                wordCount: metrics.wordCount,
                sectionCount: metrics.sectionCount,
                hasReferences: metrics.hasReferences,
                chapterProgress: Math.min((metrics.wordCount / 2000) * 100, 100)
            }
        };
    }

    public clearCache(): void {
        this.cache.clear();
    }

    public getCacheStats(): { size: number; oldestEntry: number } {
        const now = Date.now();
        let oldestTime = now;
        
        for (const entry of this.cache.values()) {
            if (entry.timestamp < oldestTime) {
                oldestTime = entry.timestamp;
            }
        }

        return {
            size: this.cache.size,
            oldestEntry: oldestTime === now ? 0 : now - oldestTime
        };
    }
}

export const smartSuggestionsService = new SmartSuggestionsService();