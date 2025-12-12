<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Services\DataCollectionDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataCollectionController extends Controller
{
    public function __construct(
        private DataCollectionDetector $detector
    ) {}

    /**
     * Detect data collection needs for a chapter
     */
    public function detect(Chapter $chapter): JsonResponse
    {
        $this->ensureChapterOwnership($chapter);

        $detection = $this->detector->detectDataCollectionNeeds($chapter);

        return response()->json([
            'success' => true,
            'detection' => $detection,
        ]);
    }

    /**
     * Generate placeholder content for a chapter
     */
    public function generatePlaceholder(Chapter $chapter): JsonResponse
    {
        $this->ensureChapterOwnership($chapter);

        $placeholder = $this->detector->generatePlaceholder($chapter);

        return response()->json([
            'success' => true,
            'placeholder' => $placeholder,
        ]);
    }

    /**
     * Get template for specific data collection type
     */
    public function getTemplate(Request $request): JsonResponse
    {
        $type = $request->input('type');

        if (! $type) {
            return response()->json([
                'success' => false,
                'message' => 'Data collection type is required',
            ], 400);
        }

        $template = $this->detector->getTemplate($type);

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data collection type',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    /**
     * Get all available templates
     */
    public function getAllTemplates(): JsonResponse
    {
        $templates = $this->detector->getAllTemplates();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Get improvement suggestions for a chapter
     */
    public function getSuggestions(Chapter $chapter): JsonResponse
    {
        $this->ensureChapterOwnership($chapter);

        $suggestions = $this->detector->suggestImprovements($chapter);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Insert template content into chapter
     */
    public function insertTemplate(Request $request, Chapter $chapter): JsonResponse
    {
        $this->ensureChapterOwnership($chapter);

        $request->validate([
            'type' => 'required|string',
            'position' => 'nullable|string|in:append,prepend,replace',
        ]);

        $type = $request->input('type');
        $position = $request->input('position', 'append');

        $template = $this->detector->getTemplate($type);

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid template type',
            ], 404);
        }

        $currentContent = $chapter->content ?? '';
        $templateContent = $template['content'];

        switch ($position) {
            case 'prepend':
                $newContent = $templateContent."\n\n".$currentContent;
                break;
            case 'replace':
                $newContent = $templateContent;
                break;
            case 'append':
            default:
                $newContent = $currentContent."\n\n".$templateContent;
                break;
        }

        $chapter->update(['content' => $newContent]);

        return response()->json([
            'success' => true,
            'message' => 'Template inserted successfully',
            'template' => $template,
            'newContent' => $newContent,
        ]);
    }

    /**
     * Ensure the authenticated user owns the chapter's project.
     */
    private function ensureChapterOwnership(Chapter $chapter): void
    {
        if (! $chapter->project || $chapter->project->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
