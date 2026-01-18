<?php

namespace App\Http\Controllers;

use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateRequestController extends Controller
{
    public function __construct(
        private AffiliateService $affiliateService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->affiliateService->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate program is currently disabled.',
            ], 400);
        }

        if (! $this->affiliateService->requestAffiliateAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending affiliate request.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Affiliate request submitted.',
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'affiliate_status' => $user->affiliate_status,
            'affiliate_requested_at' => $user->affiliate_requested_at,
            'affiliate_approved_at' => $user->affiliate_approved_at,
            'affiliate_notes' => $user->affiliate_notes,
        ]);
    }

    public function dismissPromo(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->affiliateService->dismissPromoPopup($user);

        return response()->json([
            'success' => true,
        ]);
    }
}
