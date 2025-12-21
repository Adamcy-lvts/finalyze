# Defense Preparation Feature - Complete Architecture Plan

## Overview

Transform the existing Defense.vue UI mockup into a fully functional AI-powered defense preparation system with two independent modes: **Text/Chat Mode** and **Audio Mode**. This feature helps undergraduate and postgraduate students prepare for thesis/dissertation defenses through AI-simulated panel interactions.

---

## Current State Analysis

### Existing UI Components (Defense.vue)
- Preparation Suite with Executive Briefing
- Predicted Defense Questions (accordion)
- Presentation Guide
- Opening Statement pitch laboratory
- Simulation Lab with AI panelists (The Skeptic, The Methodologist, The Generalist)
- Chat interface mockup
- Performance HUD (Clarity, Technical Depth, Stress Handling)
- Readiness Score display

### Existing Backend (DefenseController.php)
- `getQuestions()` - Fetches/generates defense questions
- `generateQuestions()` - Synchronous question generation
- `streamGenerate()` - Streaming question generation (incomplete)
- `markHelpful()` / `hideQuestion()` - Question management
- Uses `AIContentGenerator` service
- Caches questions for 6 hours

### Existing Infrastructure (Discovered)

#### Credit/Word Balance System
- **Storage**: `word_balance` column on users table
- **Service**: `WordBalanceService` with `deductForGeneration()` method
- **Existing Estimate**: `estimateDefenseWords()` returns 1000 words
- **Transaction Types**: Already has `defense` reference type in `WordTransaction`
- **Low Balance Threshold**: 500 words minimum for defense features
- **Real-time Updates**: `WordBalanceUpdated` event broadcasts via Reverb

#### AI Services
- **Main Service**: `AIContentGenerator` at `app/Services/AIContentGenerator.php`
- **OpenAI Provider**: Supports `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`
- **Claude Provider**: Supports Claude 3 models (Haiku, Sonnet, Opus)
- **Streaming**: Fully implemented with SSE pattern
- **Token Tracking**: `AIUsageLogger` logs all API calls with cost estimation
- **Model Pricing Config**: `config/ai.php` has `model_pricing` array

#### WebSocket/Real-time
- **Solution**: Laravel Reverb (v1.6) - already configured
- **Existing Channels**:
  - `project.{projectId}.generation` - generation progress
  - `user.{id}` - user notifications, balance updates
  - `admin.ai` / `admin.notifications` - admin features
- **Broadcasting Service**: `GenerationBroadcaster` pattern to follow
- **Audio Features**: None exist - will need to build from scratch

---

## Proposed Architecture

### Phase 1: Text/Chat Defense Mode (GPT-4o)

#### 1.1 Database Schema

```sql
-- Defense Sessions Table
CREATE TABLE defense_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    mode ENUM('text', 'audio') DEFAULT 'text',
    status ENUM('pending', 'in_progress', 'completed', 'abandoned') DEFAULT 'pending',

    -- Panelist Selection (JSON array of persona IDs)
    -- Example: ["skeptic", "methodologist", "generalist"] or ["all"]
    selected_panelists JSON NOT NULL,

    -- Difficulty scales questioning intensity
    difficulty_level ENUM('undergraduate', 'masters', 'doctoral') DEFAULT 'undergraduate',

    -- Session Limits (user chooses one, others null)
    time_limit_minutes INT UNSIGNED NULL,      -- e.g., 15, 30, 45
    question_limit INT UNSIGNED NULL,          -- e.g., 5, 10, 15
    -- If both null = manual end only

    -- Tracking
    session_duration_seconds INT UNSIGNED DEFAULT 0,
    questions_asked INT UNSIGNED DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,

    -- Performance & Credits
    performance_metrics JSON, -- { clarity: 0-100, technical_depth: 0-100, stress_handling: 0-100 }
    readiness_score INT UNSIGNED DEFAULT 0,
    words_consumed INT UNSIGNED DEFAULT 0,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    -- deleted_at TIMESTAMP NULL, -- Soft delete (history retained until manual delete)

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Defense Messages Table (Chat History)
CREATE TABLE defense_messages (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    role ENUM('panelist', 'student', 'system') NOT NULL,
    panelist_persona VARCHAR(50) NULL, -- 'skeptic', 'methodologist', 'generalist'
    content TEXT NOT NULL,
    audio_url VARCHAR(500) NULL, -- For audio mode
    audio_duration_seconds DECIMAL(8,2) NULL,
    tokens_used INT UNSIGNED DEFAULT 0,
    response_time_ms INT UNSIGNED NULL, -- How long student took to respond
    ai_feedback JSON NULL, -- Real-time feedback on response
    created_at TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES defense_sessions(id) ON DELETE CASCADE
);

-- Defense Feedback Table (Post-session analysis)
CREATE TABLE defense_feedback (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    overall_score INT UNSIGNED, -- 0-100
    strengths JSON, -- Array of identified strengths
    weaknesses JSON, -- Array of areas for improvement
    question_performance JSON, -- Per-question breakdown
    recommendations TEXT,
    generated_at TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES defense_sessions(id) ON DELETE CASCADE
);
```

#### 1.2 Backend Services

**File: `app/Services/DefenseSimulationService.php`**
```php
class DefenseSimulationService
{
    // Core simulation logic
    public function startSession(Project $project, array $config): DefenseSession;
    public function generatePanelistQuestion(DefenseSession $session, string $persona): DefenseMessage;
    public function processStudentResponse(DefenseSession $session, string $response): array;
    public function evaluateResponse(DefenseMessage $question, DefenseMessage $answer): array;
    public function endSession(DefenseSession $session): DefenseFeedback;
    public function calculatePerformanceMetrics(DefenseSession $session): array;
}
```

**File: `app/Services/Defense/PanelistPersonaService.php`**
```php
class PanelistPersonaService
{
    // Persona definitions with different questioning styles
    public function getPersona(string $type): array;
    public function buildSystemPrompt(string $persona, Project $project, string $academicLevel): string;
    public function getFollowUpStrategy(string $persona, array $previousExchanges): string;
}
```

**File: `app/Services/Defense/DefenseCreditService.php`**
```php
class DefenseCreditService
{
    // Credit calculation and deduction
    public function estimateSessionCost(string $mode, int $estimatedMinutes): float;
    public function calculateTextCost(int $inputTokens, int $outputTokens): float;
    public function calculateAudioCost(float $durationSeconds, string $direction): float;
    public function deductCredits(User $user, float $amount, DefenseSession $session): bool;
    public function hasEnoughCredits(User $user, string $mode): bool;
}
```

#### 1.3 API Endpoints

```php
// routes/api.php - Defense Simulation Routes
Route::prefix('projects/{project}/defense')->middleware('auth:sanctum')->group(function () {
    // Session Management
    Route::post('/sessions', [DefenseSimulationController::class, 'startSession']);
    Route::get('/sessions/{session}', [DefenseSimulationController::class, 'getSession']);
    Route::post('/sessions/{session}/end', [DefenseSimulationController::class, 'endSession']);
    Route::delete('/sessions/{session}', [DefenseSimulationController::class, 'abandonSession']);

    // Text Mode
    Route::post('/sessions/{session}/respond', [DefenseSimulationController::class, 'submitResponse']);
    Route::get('/sessions/{session}/next-question', [DefenseSimulationController::class, 'getNextQuestion']);

    // Audio Mode (Phase 2)
    Route::post('/sessions/{session}/audio/respond', [DefenseAudioController::class, 'submitAudioResponse']);
    Route::get('/sessions/{session}/audio/question', [DefenseAudioController::class, 'getAudioQuestion']);

    // Feedback & Analysis
    Route::get('/sessions/{session}/feedback', [DefenseSimulationController::class, 'getFeedback']);
    Route::get('/sessions/{session}/transcript', [DefenseSimulationController::class, 'getTranscript']);

    // Preparation Tools
    Route::get('/executive-briefing', [DefensePreparationController::class, 'getExecutiveBriefing']);
    Route::post('/opening-statement/analyze', [DefensePreparationController::class, 'analyzeOpeningStatement']);
    Route::get('/presentation-guide', [DefensePreparationController::class, 'getPresentationGuide']);

    // Credit Estimation
    Route::get('/estimate-cost', [DefenseSimulationController::class, 'estimateCost']);
});
```

#### 1.4 Panelist Personas (Enhanced)

```typescript
interface PanelistPersona {
    id: string;
    name: string;
    role: string;
    avatar: string;
    color: string;
    questioningStyle: 'aggressive' | 'methodical' | 'supportive';
    focusAreas: string[];
    systemPrompt: string;
    difficultyModifier: number; // 0.8 - 1.2
}

const PERSONAS = {
    skeptic: {
        id: 'skeptic',
        name: 'The Skeptic',
        role: 'Critical Reviewer',
        avatar: '🧐',
        color: 'text-amber-500',
        questioningStyle: 'aggressive',
        focusAreas: ['methodology_flaws', 'sample_size', 'bias', 'generalizability'],
        difficultyModifier: 1.2,
    },
    methodologist: {
        id: 'methodologist',
        name: 'The Methodologist',
        role: 'Technical Expert',
        avatar: '🧪',
        color: 'text-blue-500',
        questioningStyle: 'methodical',
        focusAreas: ['research_design', 'data_analysis', 'validity', 'reliability'],
        difficultyModifier: 1.0,
    },
    generalist: {
        id: 'generalist',
        name: 'The Generalist',
        role: 'Value Reviewer',
        avatar: '🌍',
        color: 'text-green-500',
        questioningStyle: 'supportive',
        focusAreas: ['contribution', 'practical_implications', 'future_research'],
        difficultyModifier: 0.8,
    },
    theorist: {
        id: 'theorist',
        name: 'The Theorist',
        role: 'Framework Expert',
        avatar: '📚',
        color: 'text-purple-500',
        questioningStyle: 'methodical',
        focusAreas: ['theoretical_framework', 'literature_gaps', 'conceptual_clarity'],
        difficultyModifier: 1.1,
    },
    practitioner: {
        id: 'practitioner',
        name: 'The Practitioner',
        role: 'Industry Expert',
        avatar: '💼',
        color: 'text-cyan-500',
        questioningStyle: 'supportive',
        focusAreas: ['real_world_application', 'industry_relevance', 'implementation'],
        difficultyModifier: 0.9,
    }
};
```

#### 1.5 Text Mode AI Configuration

**Model Selection: GPT-4o**
- Cost-effective for text-based interactions
- Excellent reasoning and contextual understanding
- Good at maintaining persona consistency

**Prompt Engineering Structure:**
```
SYSTEM PROMPT:
- Project context (title, topic, field, methodology)
- Chapter summaries with key findings
- Panelist persona and questioning style
- Academic level (undergraduate/masters/doctoral)
- Conversation history context

USER PROMPT (for generating questions):
- Previous Q&A exchanges
- Current focus area
- Follow-up instruction if applicable

EVALUATION PROMPT (for scoring responses):
- Original question
- Student's response
- Rubric for clarity, depth, accuracy
- Feedback generation instructions
```

---

### Phase 2: Audio Defense Mode (OpenAI Realtime API)

#### 2.1 Model Selection & Cost Analysis

**Recommended: GPT-4o Realtime API**
- Supports audio input/output natively
- Lower latency than whisper + tts pipeline
- Natural conversation flow

**Alternative (Cost-Optimized):**
- Input: Whisper API (transcription) - $0.006/minute
- Processing: GPT-4o-mini (text) - Cheaper than GPT-4o
- Output: TTS-1 (speech) - $0.015/1K characters

**Cost Comparison:**

| Mode | Component | Cost |
|------|-----------|------|
| Text (GPT-4o) | Input | $2.50/1M tokens |
| Text (GPT-4o) | Output | $10.00/1M tokens |
| Audio (Realtime) | Audio Input | $100/1M tokens (~$0.06/min) |
| Audio (Realtime) | Audio Output | $200/1M tokens (~$0.24/min) |
| Audio (Alt) | Whisper | $0.006/minute |
| Audio (Alt) | TTS-1 | $0.015/1K chars |

**Credit Calculation Formula:**
```php
// Text Mode (per exchange)
$textCost = ($inputTokens * 2.50 / 1_000_000) + ($outputTokens * 10.00 / 1_000_000);
$creditsDeducted = $textCost * $creditMultiplier; // e.g., 1 credit = $0.01

// Audio Mode - Realtime API (per minute)
$audioInputCostPerMin = 0.06;  // ~$0.06/min for audio input
$audioOutputCostPerMin = 0.24; // ~$0.24/min for audio output
$audioCost = ($inputMinutes * $audioInputCostPerMin) + ($outputMinutes * $audioOutputCostPerMin);
$creditsDeducted = $audioCost * $creditMultiplier;

// Audio Mode - Alternative Pipeline (per minute)
$whisperCost = $inputMinutes * 0.006;
$ttsCost = $outputCharacters * 0.015 / 1000;
$gptCost = $textTokens * $gptRate;
$totalAudioAltCost = $whisperCost + $ttsCost + $gptCost;
```

#### 2.2 Audio Mode Architecture

**File: `app/Services/Defense/AudioDefenseService.php`**
```php
class AudioDefenseService
{
    public function initializeRealtimeSession(DefenseSession $session): WebSocketConnection;
    public function processAudioChunk(DefenseSession $session, string $audioData): void;
    public function generateAudioResponse(DefenseSession $session, string $text): string;
    public function transcribeAudio(string $audioPath): string;
    public function synthesizeSpeech(string $text, string $voice): string;
}
```

**WebSocket Handler: `app/Http/Controllers/DefenseWebSocketController.php`**
```php
class DefenseWebSocketController
{
    public function handleConnection(Request $request, DefenseSession $session);
    public function onAudioReceived(string $audioChunk);
    public function onTranscriptionComplete(string $text);
    public function sendAudioResponse(string $audioData);
    public function onSessionEnd();
}
```

#### 2.3 Audio Mode Frontend Components

```typescript
// composables/useAudioDefense.ts
interface AudioDefenseState {
    isRecording: boolean;
    isProcessing: boolean;
    isSpeaking: boolean;
    currentTranscript: string;
    audioLevel: number;
    connectionStatus: 'connecting' | 'connected' | 'disconnected';
}

function useAudioDefense(sessionId: string) {
    // WebSocket connection management
    // Audio recording with MediaRecorder API
    // Real-time audio playback
    // Voice activity detection
    // Push-to-talk or voice activation modes
}
```

---

### Phase 3: Shared Components & Enhancements

#### 3.1 Performance Metrics System

```typescript
interface PerformanceMetrics {
    clarity: number;        // 0-100: How clear and articulate
    technicalDepth: number; // 0-100: Technical accuracy and depth
    stressHandling: number; // 0-100: Composure under pressure
    responseTime: number;   // Average seconds to respond
    questionCoverage: number; // % of question points addressed
    confidenceScore: number;  // Derived from speech patterns/response quality
}

interface SessionAnalytics {
    totalQuestions: number;
    questionsAnswered: number;
    averageScore: number;
    strongestArea: string;
    weakestArea: string;
    improvementTrend: number[]; // Score progression over session
}
```

#### 3.2 Readiness Score Algorithm

```typescript
function calculateReadinessScore(project: Project, sessions: DefenseSession[]): number {
    const weights = {
        chapterCompletion: 0.25,      // All chapters written
        questionPractice: 0.20,       // # of questions practiced
        simulationPerformance: 0.30,  // Average simulation scores
        topicCoverage: 0.15,          // Coverage of all defense topics
        recentActivity: 0.10,         // Preparation recency
    };

    // Calculate each component and return weighted sum
}
```

#### 3.3 Enhanced Features for Students

**3.3.1 Intelligent Question Bank**
- AI-generated questions based on actual thesis content
- Categorized by: Methodology, Literature, Findings, Theory, Contribution
- Difficulty levels: Easy, Medium, Hard
- User can mark questions as "mastered" or "needs practice"
- Spaced repetition for question review

**3.3.2 Executive Briefing (AI-Generated)**
- One-page thesis summary
- Key contributions highlighted
- Potential weak points identified
- Talking points for each chapter
- Auto-updates when chapters change

**3.3.3 Presentation Guide**
- Suggested slide structure
- Time allocation per section
- Key visuals to include
- Transition suggestions
- Exportable to PowerPoint outline

**3.3.4 Opening Statement Workshop**
- AI analysis of pitch effectiveness
- Timing feedback (aim for 60-90 seconds)
- Clarity score
- Suggestions for improvement
- Practice recordings with playback

**3.3.5 Post-Defense Analysis**
- Full transcript with annotations
- Per-question performance breakdown
- Comparison with previous sessions
- Personalized improvement tips
- Progress tracking over time

**3.3.6 Study Mode**
- Flashcard-style Q&A practice
- Quick-fire question rounds
- Focus on weak areas
- Gamification elements (streaks, achievements)

---

### Implementation Roadmap

#### Phase 1: Text/Chat Mode (Independent - Can Ship First)

**Step 1.1: Database & Models**
- [ ] Migration: `create_defense_sessions_table`
- [ ] Migration: `create_defense_messages_table`
- [ ] Migration: `create_defense_feedback_table`
- [ ] Model: `DefenseSession` with relationships
- [ ] Model: `DefenseMessage` with relationships
- [ ] Model: `DefenseFeedback` with relationships

**Step 1.2: Core Services**
- [ ] `DefenseSimulationService` - Session orchestration
- [ ] `PanelistPersonaService` - Persona prompts and behaviors
- [ ] `DefenseCreditService` - Word balance integration
- [ ] `DefensePerformanceService` - Metrics calculation

**Step 1.3: API Endpoints**
- [ ] `POST /projects/{project}/defense/sessions` - Start session
- [ ] `GET /projects/{project}/defense/sessions/{session}` - Get session
- [ ] `POST /projects/{project}/defense/sessions/{session}/respond` - Submit response
- [ ] `POST /projects/{project}/defense/sessions/{session}/end` - End session
- [ ] `GET /projects/{project}/defense/sessions/{session}/feedback` - Get feedback

**Step 1.4: Broadcasting Events**
- [ ] `DefenseSessionStarted` event
- [ ] `DefenseMessageSent` event
- [ ] `DefensePerformanceUpdated` event
- [ ] `DefenseSessionEnded` event
- [ ] Add channel: `defense.{sessionId}` to channels.php

**Step 1.5: Frontend Integration**
- [ ] Refactor Defense.vue to use real data
- [ ] Create `useDefenseSession.ts` composable
- [ ] Create `DefenseChat.vue` component
- [ ] Create `PerformanceHUD.vue` component
- [ ] Create `SessionFeedback.vue` component
- [ ] Wire up mode selector with text as default

**Step 1.6: Preparation Tools**
- [ ] `GET /projects/{project}/defense/executive-briefing` - AI-generated summary
- [ ] `POST /projects/{project}/defense/opening-statement/analyze` - Pitch analysis
- [ ] `GET /projects/{project}/defense/presentation-guide` - Slide structure
- [ ] Wire up Executive Briefing card
- [ ] Wire up Opening Statement workshop

---

#### Phase 2: Audio Mode (Independent - Ships After Phase 1)

**Step 2.1: OpenAI Audio Integration**
- [ ] Add Whisper API integration to OpenAI provider
- [ ] Add TTS API integration to OpenAI provider
- [ ] Create `AudioTranscriptionService`
- [ ] Create `TextToSpeechService`

**Step 2.2: Audio API Endpoints**
- [ ] `POST /defense/sessions/{session}/audio/upload` - Upload audio chunk
- [ ] `GET /defense/sessions/{session}/audio/response` - Get TTS audio
- [ ] Audio file storage (temporary, auto-delete after processing)

**Step 2.3: Frontend Audio Components**
- [ ] Create `DefenseAudio.vue` component
- [ ] Create `useAudioRecorder.ts` composable (MediaRecorder API)
- [ ] Create `useAudioPlayer.ts` composable
- [ ] Add push-to-talk button with visual feedback
- [ ] Add audio level visualization
- [ ] Add mode warning about higher word consumption

**Step 2.4: Audio Credit Calculation**
- [ ] Track audio duration per exchange
- [ ] Apply 5x multiplier to word costs
- [ ] Show real-time word consumption in UI

---

#### Phase 3: Enhancements (After Core Features Work)

**Step 3.1: Analytics & Progress**
- [ ] Readiness score algorithm implementation
- [ ] Session history page
- [ ] Progress charts over time
- [ ] Comparison with previous sessions

**Step 3.2: Question Bank Improvements**
- [ ] Spaced repetition algorithm
- [ ] "Mastered" / "Needs Practice" marking
- [ ] Category-based practice mode
- [ ] Quick-fire question rounds

**Step 3.3: Export & Sharing**
- [ ] Export session transcript as PDF
- [ ] Export presentation guide to PowerPoint outline
- [ ] Share readiness score (optional)

---

### File Structure (Critical Files to Create/Modify)

```
app/
├── Http/
│   └── Controllers/
│       ├── DefenseController.php              # MODIFY - Add session endpoints
│       ├── DefenseSimulationController.php    # NEW - Simulation flow
│       └── DefenseAudioController.php         # NEW - Audio mode (Phase 2)
├── Models/
│   ├── DefenseSession.php                     # NEW
│   ├── DefenseMessage.php                     # NEW
│   └── DefenseFeedback.php                    # NEW
├── Services/
│   └── Defense/
│       ├── DefenseSimulationService.php       # NEW - Core orchestration
│       ├── PanelistPersonaService.php         # NEW - Persona prompts
│       ├── DefenseCreditService.php           # NEW - Word balance integration
│       ├── DefensePerformanceService.php      # NEW - Metrics calculation
│       ├── AudioTranscriptionService.php      # NEW - Whisper (Phase 2)
│       └── TextToSpeechService.php            # NEW - TTS (Phase 2)
├── Events/
│   └── Defense/
│       ├── DefenseSessionStarted.php          # NEW
│       ├── DefenseMessageSent.php             # NEW
│       ├── DefensePerformanceUpdated.php      # NEW
│       └── DefenseSessionEnded.php            # NEW
└── config/
    └── pricing.php                            # MODIFY - Add defense estimation

database/migrations/
├── xxxx_create_defense_sessions_table.php     # NEW
├── xxxx_create_defense_messages_table.php     # NEW
└── xxxx_create_defense_feedback_table.php     # NEW

routes/
├── api.php                                    # MODIFY - Add defense routes
└── channels.php                               # MODIFY - Add defense channel

resources/js/
├── pages/projects/
│   └── Defense.vue                            # MAJOR REFACTOR
├── components/defense/
│   ├── DefenseChat.vue                        # NEW - Chat interface
│   ├── DefenseAudio.vue                       # NEW - Audio interface (Phase 2)
│   ├── PanelistCard.vue                       # NEW - Panelist display
│   ├── PerformanceHUD.vue                     # NEW - Real-time metrics
│   ├── ModeSelector.vue                       # NEW - Text/Audio toggle
│   ├── ExecutiveBriefing.vue                  # NEW - AI summary
│   ├── OpeningStatementEditor.vue             # NEW - Pitch workshop
│   └── SessionFeedback.vue                    # NEW - Post-session report
├── composables/
│   ├── useDefenseSession.ts                   # NEW - Session state
│   ├── useDefenseChat.ts                      # NEW - Chat logic
│   ├── useAudioRecorder.ts                    # NEW - Audio recording (Phase 2)
│   └── useAudioPlayer.ts                      # NEW - Audio playback (Phase 2)
└── types/
    └── defense.ts                             # NEW - TypeScript interfaces
```

---

### Credit System Design (Word-Based - Actual Usage)

The application uses a **word-based credit system**. We'll integrate with the existing `WordBalanceService` and charge based on **actual word counts** from AI responses.

#### Configuration (config/pricing.php additions)
```php
'estimation' => [
    // Existing
    'defense_words' => 1000,  // Per question generation (for estimation UI)

    // New - Defense Simulation
    'defense_session_base' => 200,           // Base cost to start session
    'defense_feedback_generation' => 500,    // Post-session analysis

    // Audio mode multiplier (covers Whisper + TTS API costs)
    'defense_audio_multiplier' => 2.0,       // 2x text mode for audio processing overhead
]
```

#### Credit Calculation Logic

**Text Mode (Actual Word Count):**
```php
// In DefenseCreditService
public function deductForTextExchange(User $user, DefenseSession $session, string $aiResponse): void
{
    // Count actual words in the AI response
    $wordCount = str_word_count(strip_tags($aiResponse));

    $this->wordBalanceService->deductForGeneration(
        $user,
        $wordCount,
        "Defense Q&A - {$wordCount} words",
        'defense_simulation',
        $session->project_id,
        ['session_id' => $session->id, 'mode' => 'text', 'word_count' => $wordCount]
    );
}
```

**Audio Mode (Word Count + Processing Overhead):**
```php
public function deductForAudioExchange(
    User $user,
    DefenseSession $session,
    string $transcribedText,  // Student's speech (from Whisper)
    string $aiResponse        // AI's response (sent to TTS)
): void
{
    // Count words from both transcription and response
    $transcriptionWords = str_word_count($transcribedText);
    $responseWords = str_word_count(strip_tags($aiResponse));
    $baseWordCount = $transcriptionWords + $responseWords;

    // Apply multiplier for Whisper + TTS API overhead
    $multiplier = config('pricing.estimation.defense_audio_multiplier', 2.0);
    $totalWords = (int) ceil($baseWordCount * $multiplier);

    $this->wordBalanceService->deductForGeneration(
        $user,
        $totalWords,
        "Defense Audio Q&A - {$totalWords} words (base: {$baseWordCount} × {$multiplier})",
        'defense_simulation',
        $session->project_id,
        [
            'session_id' => $session->id,
            'mode' => 'audio',
            'transcription_words' => $transcriptionWords,
            'response_words' => $responseWords,
            'multiplier' => $multiplier,
            'total_words' => $totalWords,
        ]
    );
}
```

#### Example Cost Breakdown (10-exchange session)

| Mode | Per Exchange (avg) | 10 Exchanges | Session Total |
|------|-------------------|--------------|---------------|
| Text Mode | ~150-300 words | ~2,000 words | ~2,700 words (incl. base + feedback) |
| Audio Mode | ~300-600 words (2x) | ~4,000 words | ~4,700 words (incl. base + feedback) |

**Audio Multiplier Justification (2x):**
- Whisper API: Processes student speech → outputs text
- GPT-4o: Generates response (same as text mode)
- TTS API: Converts response to speech
- The 2x multiplier covers the additional Whisper + TTS costs proportionally

#### Credit Warning UI
```vue
<template>
    <div class="mode-selector">
        <button @click="selectMode('text')" :class="{ active: mode === 'text' }">
            <MessageSquare class="h-5 w-5" />
            <span>Text Mode</span>
            <Badge variant="secondary">Standard rate</Badge>
        </button>

        <button @click="selectMode('audio')" :class="{ active: mode === 'audio' }">
            <Mic class="h-5 w-5" />
            <span>Audio Mode</span>
            <Badge variant="destructive">2× word usage</Badge>
        </button>
    </div>

    <Alert v-if="mode === 'audio'" variant="warning">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>Higher Word Usage</AlertTitle>
        <AlertDescription>
            Audio mode uses 2× more words due to voice processing (transcription + speech synthesis).
            Your current balance: {{ userWordBalance.toLocaleString() }} words.
        </AlertDescription>
    </Alert>
</template>
```

---

### Security Considerations

1. **Session Validation**: Ensure users can only access their own defense sessions
2. **Rate Limiting**: Prevent abuse of AI generation endpoints
3. **Audio Storage**: Temporary storage with auto-deletion after processing
4. **Credit Verification**: Check balance before each AI call
5. **Content Filtering**: Ensure AI responses remain professional and educational

---

### Success Metrics

1. **Engagement**: Sessions started per user, completion rate
2. **Improvement**: Readiness score progression over time
3. **Satisfaction**: User ratings, return usage
4. **Effectiveness**: Self-reported defense success rates
5. **Revenue**: Credit consumption per feature

---

---

## Quick Start (Minimum Viable Implementation)

To get a working defense simulation quickly, implement in this order:

### MVP Checklist (Text Mode Only)
1. **Database**: Create `defense_sessions`, `defense_messages`, `defense_feedback` tables (with soft deletes)
2. **Models**: `DefenseSession`, `DefenseMessage`, `DefenseFeedback` with relationships
3. **Service**: `DefenseSimulationService` with:
   - `startSession(project, panelists[], difficulty, limits)`
   - `generateQuestion(session)` - picks persona, generates contextual question
   - `processResponse(session, response)` - evaluates and scores
   - `endSession(session)` - generates feedback
4. **Service**: `PanelistPersonaService` - 5 personas with difficulty-scaled prompts
5. **Service**: `DefenseCreditService` - word count deduction from actual AI response
6. **Controller**: `DefenseSimulationController` with session CRUD + respond endpoint
7. **Frontend**:
   - Session setup modal (select panelists, difficulty, limits)
   - Refactor Defense.vue simulation section to use real API
   - Real-time chat with panelist avatars
8. **Broadcasting**: Add `defense.{sessionId}` channel for real-time updates

This MVP can ship in ~5-7 days and provides immediate value to students.

---

## Model Recommendations

| Feature | Recommended Model | Rationale |
|---------|-------------------|-----------|
| Text Chat (Panelist Questions) | `gpt-4o` | Best persona consistency, academic tone |
| Text Chat (Quick responses) | `gpt-4o-mini` | Cost-effective for follow-ups |
| Response Evaluation | `gpt-4o-mini` | Structured output, lower cost |
| Executive Briefing | `gpt-4o` | High-quality synthesis needed |
| Audio Transcription | `whisper-1` | Best accuracy, $0.006/min |
| Text-to-Speech | `tts-1` | Good quality, $0.015/1K chars |
| Audio (Premium) | `tts-1-hd` | Higher quality, $0.030/1K chars |

---

## Summary

This architecture provides a comprehensive defense preparation system with:

1. **Text Mode (Phase 1)**: GPT-4o based chat simulation with 5 selectable AI panelist personas
2. **Audio Mode (Phase 2)**: Voice-based practice using Whisper + GPT-4o + TTS-1 pipeline (2x word cost)
3. **Academic Levels**: Difficulty scales for Undergraduate, Masters, and Doctoral students
4. **Flexible Sessions**: Time limit, question limit, or manual end - user's choice
5. **Word-Based Credits**: Actual word count from AI responses (text) or with 2x multiplier (audio)
6. **Session History**: Retained permanently until manually deleted by user
7. **Real-time Features**: Laravel Reverb broadcasts for live metrics and chat updates
8. **Performance Analytics**: Clarity, technical depth, stress handling scores with post-session feedback

The modular design allows independent development of text and audio modes. **Text mode can ship first** as a complete feature, with audio mode added later as a premium enhancement.

---

## User Decisions (Confirmed)

1. **Academic Levels**: ✅ Difficulty scales based on level (Undergraduate, Masters, Doctoral)
2. **Session Duration**: ✅ All options available - time limit, question limit, OR manual end (user chooses)
3. **Panelist Selection**: ✅ All 5 personas included with option to select all or choose specific ones
4. **Credit Calculation**:
   - Text Mode: Use actual word count returned from AI response
   - Audio Mode: Use word count from transcription + AI response + processing overhead
5. **Session History**: ✅ Retained forever until manually deleted by user
