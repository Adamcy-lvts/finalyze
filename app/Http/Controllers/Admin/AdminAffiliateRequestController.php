<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAffiliateRequestController extends Controller
{
    public function __construct(
        private AffiliateService $affiliateService
    ) {}

    public function index(Request $request): Response
    {
        $requests = User::where('affiliate_status', 'pending')
            ->orderByDesc('affiliate_requested_at')
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'requested_at' => $user->affiliate_requested_at?->toISOString(),
                'projects_count' => $user->projects()->count(),
                'created_at' => $user->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Affiliates/Requests', [
            'requests' => $requests,
        ]);
    }

    public function approve(User $user, Request $request): JsonResponse
    {
        $this->affiliateService->approveAffiliateRequest($user, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Affiliate request approved',
        ]);
    }

    public function reject(User $user, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->affiliateService->rejectAffiliateRequest($user, $request->user(), $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Affiliate request rejected',
        ]);
    }
}
