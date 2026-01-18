<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Services\AffiliateService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'word_balance_data' => $request->user()->getWordBalanceData(),
                    'affiliate' => $request->user() ? [
                        'status' => $request->user()->affiliate_status,
                        'is_affiliate' => $request->user()->isAffiliate(),
                        'is_pure' => $request->user()->isPureAffiliate(),
                        'has_dual_access' => $request->user()->hasDualAccess(),
                        'can_request' => $request->user()->canRequestAffiliateAccess(),
                    ] : null,
                ] : null,
            ],
            'affiliate' => $request->user() ? [
                'enabled' => app(AffiliateService::class)->isEnabled(),
                'show_promo' => app(AffiliateService::class)->shouldShowPromoPopup($request->user()),
            ] : null,
            'csrf_token' => csrf_token(),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'message' => $request->session()->get('message'),
            ],
        ];
    }
}
