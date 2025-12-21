export interface DefenseSession {
    id: number
    user_id: number
    project_id: number
    mode: 'text' | 'audio'
    status: 'pending' | 'in_progress' | 'completed' | 'abandoned'
    selected_panelists: string[]
    difficulty_level: 'undergraduate' | 'masters' | 'doctoral'
    time_limit_minutes: number | null
    question_limit: number | null
    session_duration_seconds: number
    questions_asked: number
    started_at: string | null
    completed_at: string | null
    performance_metrics: DefensePerformanceMetrics | null
    readiness_score: number
    words_consumed: number
}

export interface DefenseMessage {
    id: number
    session_id: number
    role: 'panelist' | 'student' | 'system'
    panelist_persona: string | null
    is_follow_up?: boolean
    content: string
    audio_url?: string | null
    audio_duration_seconds?: number | null
    tokens_used?: number
    response_time_ms?: number | null
    ai_feedback?: DefenseEvaluation | null
    created_at?: string
}

export interface DefenseEvaluation {
    clarity: number
    technical_depth: number
    feedback?: string
    strengths?: string[]
    improvements?: string[]
}

export interface DefensePerformanceMetrics {
    clarity: number
    technical_depth: number
    response_time: number
    question_coverage: number
    confidence_score: number
    readiness_score: number
}

export interface DefenseFeedback {
    id: number
    session_id: number
    overall_score: number
    strengths: string[]
    weaknesses: string[]
    recommendations: string
    generated_at: string
}
