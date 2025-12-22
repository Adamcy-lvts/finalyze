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

            if ($latest->status === 'rendering' || $latest->status === 'outlining' || $latest->status === 'queued') {
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
                RenderDefenseDeckPptx::dispatch($latest->id);

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
            'ai_models' => [
                'outline' => 'gpt-4o',
                'pptx' => 'claude-skills-pptx',
            ],
        ]);

        GenerateDefenseDeckOutline::dispatch($deck->id);

        return response()->json([
            'success' => true,
            'deck' => $this->formatDeckResponse($project, $deck),
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
