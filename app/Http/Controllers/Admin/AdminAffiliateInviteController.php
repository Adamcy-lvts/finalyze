<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateInvite;
use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAffiliateInviteController extends Controller
{
    public function __construct(
        private AffiliateService $affiliateService
    ) {}

    public function index(): Response
    {
        $invites = AffiliateInvite::with('createdBy:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($invite) => [
                'id' => $invite->id,
                'code' => $invite->code,
                'type' => $invite->type,
                'max_uses' => $invite->max_uses,
                'uses' => $invite->uses,
                'expires_at' => $invite->expires_at?->toISOString(),
                'is_active' => $invite->is_active,
                'note' => $invite->note,
                'created_by' => $invite->createdBy?->name,
                'created_at' => $invite->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Affiliates/Invites', [
            'invites' => $invites,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:single_use,reusable',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);

        $invite = $this->affiliateService->createInvite(
            admin: $request->user(),
            type: $data['type'],
            maxUses: $data['max_uses'] ?? null,
            expiresAt: isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null,
            note: $data['note'] ?? null,
        );

        return response()->json([
            'success' => true,
            'invite' => $invite,
        ]);
    }

    public function update(Request $request, AffiliateInvite $invite): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
            'note' => 'nullable|string|max:255',
        ]);

        $invite->update([
            'is_active' => $data['is_active'] ?? $invite->is_active,
            'expires_at' => isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : $invite->expires_at,
            'note' => $data['note'] ?? $invite->note,
        ]);

        return response()->json([
            'success' => true,
            'invite' => $invite->fresh(),
        ]);
    }

    public function destroy(AffiliateInvite $invite): JsonResponse
    {
        $invite->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
