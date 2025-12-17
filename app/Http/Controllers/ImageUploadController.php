<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:5120'],
        ]);

        $user = Auth::user();
        $file = $request->file('image');

        // Generate unique filename
        $filename = uniqid().'_'.time().'.'.$file->getClientOriginalExtension();

        // Store in user-specific folder
        $path = $file->storeAs(
            'editor-images/user-'.$user->id,
            $filename,
            'public'
        );

        // Get image dimensions
        $imageInfo = getimagesize($file->getRealPath());
        $width = $imageInfo[0] ?? null;
        $height = $imageInfo[1] ?? null;

        return response()->json([
            'success' => true,
            'url' => Storage::url($path),
            'filename' => $filename,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $url = $request->input('url');

        // Extract the path from the URL (e.g., /storage/editor-images/user-1/file.jpg -> editor-images/user-1/file.jpg)
        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));

        // Security check: ensure the path belongs to the current user
        $expectedPrefix = 'editor-images/user-'.$user->id.'/';
        if (! str_starts_with($path, $expectedPrefix)) {
            Log::warning('User '.$user->id.' attempted to delete unauthorized image: '.$path);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete the file from storage
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            Log::info('Deleted editor image: '.$path.' by user '.$user->id);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Image not found',
        ], 404);
    }
}
