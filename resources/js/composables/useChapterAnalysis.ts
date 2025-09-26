import { ref, computed } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';

interface QualityAnalysis {
    id: number;
    total_score: number;
    quality_level: string;
    meets_threshold: boolean;
    meets_defense_requirement: boolean;
    scores: {
        [key: string]: {
            score: number;
            max: number;
            percentage: number;
        };
    };
    metrics: {
        word_count: number;
        paragraph_count: number;
        sentence_count: number;
        citation_count: number;
        verified_citation_count: number;
        completion_percentage: number;
        reading_time_minutes: number;
    };
    detailed_feedback: {
        grammar_issues: string[];
        readability_metrics: Record<string, any>;
        structure_feedback: string[];
        citation_analysis: string[];
    };
    suggestions: {
        [key: string]: string[];
    };
    analyzed_at: string;
}

export function useChapterAnalysis(chapterId: number) {
    const isAnalyzing = ref(false);
    const latestAnalysis = ref<QualityAnalysis | null>(null);
    const analysisHistory = ref<Partial<QualityAnalysis>[]>([]);
    const error = ref<string | null>(null);

    // Quality level color mapping
    const qualityLevelColor = computed(() => {
        if (!latestAnalysis.value?.quality_level) return 'text-muted-foreground';

        switch (latestAnalysis.value.quality_level) {
            case 'Excellent': return 'text-green-600';
            case 'Good': return 'text-blue-600';
            case 'Satisfactory': return 'text-yellow-600';
            case 'Needs Improvement': return 'text-orange-600';
            case 'Poor': return 'text-red-600';
            default: return 'text-muted-foreground';
        }
    });

    // Get top areas needing improvement
    const topImprovementAreas = computed(() => {
        if (!latestAnalysis.value?.scores) return [];

        return Object.entries(latestAnalysis.value.scores)
            .sort(([,a], [,b]) => a.percentage - b.percentage)
            .slice(0, 3)
            .map(([category, data]) => ({
                category: category.replace(' & ', ' & '),
                percentage: Math.round(data.percentage),
                score: data.score,
                max: data.max
            }));
    });

    // Run comprehensive analysis
    const analyzeChapter = async (): Promise<QualityAnalysis | null> => {
        try {
            isAnalyzing.value = true;
            error.value = null;

            // Ensure CSRF cookie is set for Sanctum SPA authentication
            await axios.get('/sanctum/csrf-cookie');

            const response = await axios.post(route('api.chapters.analysis.analyze', { chapter: chapterId }));

            if (response.data.success) {
                latestAnalysis.value = response.data.analysis;
                return response.data.analysis;
            } else {
                throw new Error(response.data.error || 'Analysis failed');
            }
        } catch (err: any) {
            error.value = err.response?.data?.error || err.message || 'Analysis failed';
            console.error('Chapter analysis failed:', err);
            return null;
        } finally {
            isAnalyzing.value = false;
        }
    };

    // Get latest analysis results
    const getLatestAnalysis = async (): Promise<QualityAnalysis | null> => {
        try {
            // Ensure CSRF cookie is set for Sanctum SPA authentication
            await axios.get('/sanctum/csrf-cookie');

            const response = await axios.get(route('api.chapters.analysis.latest', { chapter: chapterId }));

            if (response.data.success) {
                latestAnalysis.value = response.data.analysis;
                return response.data.analysis;
            } else {
                // No analysis found yet
                latestAnalysis.value = null;
                return null;
            }
        } catch (err: any) {
            if (err.response?.status !== 404) {
                error.value = err.response?.data?.error || err.message || 'Failed to get analysis';
                console.error('Failed to get latest analysis:', err);
            }
            return null;
        }
    };

    // Get analysis history
    const getAnalysisHistory = async (): Promise<Partial<QualityAnalysis>[]> => {
        try {
            // Ensure CSRF cookie is set for Sanctum SPA authentication
            await axios.get('/sanctum/csrf-cookie');

            const response = await axios.get(route('api.chapters.analysis.history', { chapter: chapterId }));

            if (response.data.success) {
                analysisHistory.value = response.data.analyses;
                return response.data.analyses;
            } else {
                throw new Error(response.data.error || 'Failed to get history');
            }
        } catch (err: any) {
            error.value = err.response?.data?.error || err.message || 'Failed to get analysis history';
            console.error('Failed to get analysis history:', err);
            return [];
        }
    };

    // Auto-analyze when significant content changes occur
    const autoAnalyze = async (wordCount: number, minWords: number = 500): Promise<void> => {
        // Only auto-analyze if chapter has substantial content
        if (wordCount >= minWords) {
            await analyzeChapter();
        }
    };

    // Get quality score color class
    const getScoreColor = (score: number, maxScore: number): string => {
        const percentage = (score / maxScore) * 100;

        if (percentage >= 80) return 'text-green-600';
        if (percentage >= 70) return 'text-blue-600';
        if (percentage >= 60) return 'text-yellow-600';
        if (percentage >= 40) return 'text-orange-600';
        return 'text-red-600';
    };

    // Get quality progress variant
    const getProgressVariant = (score: number): 'default' | 'destructive' | 'success' => {
        if (score >= 80) return 'success';
        if (score >= 60) return 'default';
        return 'destructive';
    };

    return {
        // State
        isAnalyzing,
        latestAnalysis,
        analysisHistory,
        error,

        // Computed
        qualityLevelColor,
        topImprovementAreas,

        // Methods
        analyzeChapter,
        getLatestAnalysis,
        getAnalysisHistory,
        autoAnalyze,
        getScoreColor,
        getProgressVariant,
    };
}