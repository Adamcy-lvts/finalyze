<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDefenseDeckOutline;
use App\Jobs\RenderDefenseDeckPptx;
use App\Models\DefenseSlideDeck;
use App\Models\Project;
use App\Services\Defense\DefenseCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DefenseDeckController extends Controller
{
    public function create(Request $request, $project_id, DefenseCreditService $creditService)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        if (! $creditService->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for defense deck generation.',
            ], 402);
        }

        $force = (bool) $request->boolean('force_refresh');
        $latest = DefenseSlideDeck::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->latest()
            ->first();

        if ($latest && ! $force) {
            if ($latest->status === 'ready') {
                return response()->json([
                    'success' => true,
                    'deck' => $this->formatDeckResponse($project, $latest),
                ]);
            }

            if (in_array($latest->status, ['rendering', 'queued', 'outlining', 'extracting', 'extracted', 'generating'], true)) {
                return response()->json([
                    'success' => true,
                    'deck' => $this->formatDeckResponse($project, $latest),
                ]);
            }

            if (! empty($latest->slides_json) && in_array($latest->status, ['outlined', 'failed'], true)) {
                $latest->update([
                    'status' => 'outlined',
                    'error_message' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'deck' => $this->formatDeckResponse($project, $latest),
                ]);
            }
        }

        $deck = DefenseSlideDeck::create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'status' => 'queued',
            'extraction_status' => 'pending',
            'ai_models' => [
                'outline' => 'gpt-4o',
                'extraction' => 'gpt-4o-mini',
                'pptx' => config('services.pptx.engine', 'pptxgenjs'),
            ],
        ]);

        GenerateDefenseDeckOutline::dispatch($deck->id);

        return response()->json([
            'success' => true,
            'deck' => $this->formatDeckResponse($project, $deck),
        ]);
    }

    public function sync(Request $request, $project_id, DefenseCreditService $creditService)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        if (! $creditService->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for defense deck generation.',
            ], 402);
        }

        $force = (bool) $request->boolean('force_refresh');
        $latest = DefenseSlideDeck::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->latest()
            ->first();

        if ($latest && ! $force && in_array($latest->status, ['outlined', 'rendering', 'ready'], true)) {
            return response()->json([
                'success' => true,
                'deck' => $this->formatDeckResponse($project, $latest),
            ]);
        }

        $deck = DefenseSlideDeck::create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'status' => 'queued',
            'extraction_status' => 'pending',
            'ai_models' => [
                'outline' => 'gpt-4o',
                'extraction' => 'gpt-4o-mini',
                'pptx' => config('services.pptx.engine', 'pptxgenjs'),
            ],
        ]);

        GenerateDefenseDeckOutline::dispatchSync($deck->id);

        return response()->json([
            'success' => true,
            'deck' => $this->formatDeckResponse($project, $deck->fresh()),
        ]);
    }

    public function latest(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $deck = DefenseSlideDeck::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'deck' => $deck ? $this->formatDeckResponse($project, $deck) : null,
        ]);
    }

    public function update(Request $request, $project_id, $deck)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $deck = DefenseSlideDeck::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->where('id', $deck)
            ->firstOrFail();

        $data = $request->validate([
            'slides' => ['required', 'array'],
            'slides.*.title' => ['nullable', 'string'],
            'slides.*.content_type' => ['nullable', 'string', 'in:bullets,paragraphs,mixed'],
            'slides.*.bullets' => ['nullable', 'array'],
            'slides.*.bullets.*' => ['nullable', 'string'],
            'slides.*.paragraphs' => ['nullable', 'array'],
            'slides.*.paragraphs.*' => ['nullable', 'string'],
            'slides.*.headings' => ['nullable', 'array'],
            'slides.*.headings.*.heading' => ['nullable', 'string'],
            'slides.*.headings.*.content' => ['nullable', 'string'],
            'slides.*.layout' => ['nullable', 'string'],
            'slides.*.visuals' => ['nullable', 'string'],
            'slides.*.speaker_notes' => ['nullable', 'string'],
            'slides.*.image_url' => ['nullable', 'string'],
            'slides.*.image_fit' => ['nullable', 'string'],
            'slides.*.image_scale' => ['nullable', 'numeric'],
            'slides.*.image_position_x' => ['nullable', 'numeric'],
            'slides.*.image_position_y' => ['nullable', 'numeric'],
            'slides.*.charts' => ['nullable', 'array'],
            'slides.*.tables' => ['nullable', 'array'],
        ]);

        $deck->update([
            'slides_json' => $data['slides'],
            'status' => in_array($deck->status, ['queued', 'extracting', 'extracted', 'generating'], true)
                ? 'outlined'
                : $deck->status,
        ]);

        return response()->json([
            'success' => true,
            'deck' => $this->formatDeckResponse($project, $deck->fresh()),
        ]);
    }

    public function export(Request $request, $project_id, $deck, DefenseCreditService $creditService)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $deck = DefenseSlideDeck::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->where('id', $deck)
            ->firstOrFail();

        \Log::info('Defense deck export requested', [
            'project_id' => $project->id,
            'deck_id' => $deck->id,
            'status' => $deck->status,
            'slides_count' => is_array($deck->slides_json) ? count($deck->slides_json) : null,
        ]);

        if (empty($deck->slides_json)) {
            return response()->json([
                'success' => false,
                'message' => 'Slides are required before export.',
            ], 422);
        }

        $deck->update([
            'status' => 'rendering',
            'error_message' => null,
        ]);

        \Log::info('Defense deck export queued', [
            'project_id' => $project->id,
            'deck_id' => $deck->id,
        ]);

        RenderDefenseDeckPptx::dispatch($deck->id);

        return response()->json([
            'success' => true,
            'deck' => $this->formatDeckResponse($project, $deck->fresh()),
        ]);
    }

    public function download(Request $request, $project_id, $deck)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $deck = DefenseSlideDeck::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->where('id', $deck)
            ->firstOrFail();

        if ($deck->status !== 'ready' || ! $deck->pptx_path) {
            return response()->json([
                'success' => false,
                'message' => 'Deck is not ready for download.',
            ], 409);
        }

        if (! Storage::disk('public')->exists($deck->pptx_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Generated file not found.',
            ], 404);
        }

        $filename = basename($deck->pptx_path);

        return Storage::disk('public')->download($deck->pptx_path, $filename);
    }

    private function formatDeckResponse(Project $project, DefenseSlideDeck $deck): array
    {
        return [
            'id' => $deck->id,
            'status' => $deck->status,
            'error_message' => $deck->error_message,
            'slides' => $deck->slides_json ?? [],
            'pptx_url' => $deck->status === 'ready'
                ? route('api.defense.deck.download', [
                    'project_id' => $project->id,
                    'deck' => $deck->id,
                ])
                : null,
            'updated_at' => $deck->updated_at?->toISOString(),
        ];
    }
}
