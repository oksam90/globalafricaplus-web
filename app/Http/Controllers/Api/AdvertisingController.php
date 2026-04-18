<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdBanner;
use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisingController extends Controller
{
    /**
     * Active ad banners for a given position (default: home_top).
     */
    public function banners(Request $request): JsonResponse
    {
        $position = $request->input('position', 'home_top');

        $banners = AdBanner::active()
            ->position($position)
            ->orderBy('sort_order')
            ->get();

        // Track impressions
        AdBanner::active()->position($position)->increment('impressions');

        return response()->json(['data' => $banners]);
    }

    /**
     * Record a click on an ad banner.
     */
    public function bannerClick(int $id): JsonResponse
    {
        $banner = AdBanner::findOrFail($id);
        $banner->increment('clicks');

        return response()->json([
            'redirect' => $banner->cta_url,
        ]);
    }

    /**
     * Active partners list.
     */
    public function partners(Request $request): JsonResponse
    {
        $partners = Partner::active()
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $partners]);
    }

    /**
     * Active testimonials (featured first).
     */
    public function testimonials(Request $request): JsonResponse
    {
        $testimonials = Testimonial::active()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $testimonials]);
    }
}
