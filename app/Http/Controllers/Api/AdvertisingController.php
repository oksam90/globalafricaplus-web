<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdBanner;
use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    // ═══════════════════════════════════════════════════
    //  ADMIN — Partners (Optimisation point 9)
    // ═══════════════════════════════════════════════════

    public function adminPartnersIndex(Request $request): JsonResponse
    {
        $query = Partner::query();

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $sort = $request->query('sort', 'sort_order');
        $query = match ($sort) {
            'name'   => $query->orderBy('name'),
            'recent' => $query->orderByDesc('created_at'),
            default  => $query->orderBy('sort_order')->orderBy('name'),
        };

        return response()->json($query->paginate(min(50, (int) $request->query('per_page', 30))));
    }

    public function adminPartnerShow(int $id): JsonResponse
    {
        return response()->json(['data' => Partner::findOrFail($id)]);
    }

    public function adminStorePartner(Request $request): JsonResponse
    {
        $data = $this->validatePartner($request);
        $data['slug'] = $data['slug'] ?? $this->uniquePartnerSlug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        $partner = Partner::create($data);

        Log::info('admin.partner.created', [
            'admin_id'   => $request->user()->id,
            'partner_id' => $partner->id,
            'name'       => $partner->name,
        ]);

        return response()->json([
            'message' => "Partenaire « {$partner->name} » créé.",
            'data'    => $partner,
        ], 201);
    }

    public function adminUpdatePartner(Request $request, int $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);
        $data = $this->validatePartner($request, $id);

        if (isset($data['name']) && !isset($data['slug']) && $data['name'] !== $partner->name) {
            $data['slug'] = $this->uniquePartnerSlug($data['name'], $partner->id);
        }

        $partner->update($data);

        Log::info('admin.partner.updated', [
            'admin_id'   => $request->user()->id,
            'partner_id' => $partner->id,
            'changes'    => array_keys($data),
        ]);

        return response()->json([
            'message' => "Partenaire « {$partner->name} » mis à jour.",
            'data'    => $partner->fresh(),
        ]);
    }

    public function adminDestroyPartner(Request $request, int $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);

        Log::warning('admin.partner.deleted', [
            'admin_id'   => $request->user()->id,
            'partner_id' => $partner->id,
            'name'       => $partner->name,
        ]);

        $name = $partner->name;
        $partner->delete();

        return response()->json([
            'message' => "Partenaire « {$name} » supprimé.",
            'id'      => $id,
        ]);
    }

    protected function validatePartner(Request $request, ?int $id = null): array
    {
        $unique = $id ? ',' . $id : '';
        $nameRule = $id ? ['sometimes', 'string', 'max:150'] : ['required', 'string', 'max:150'];
        $logoRule = $id ? ['sometimes', 'string', 'max:500'] : ['required', 'string', 'max:500'];

        return $request->validate([
            'name'        => $nameRule,
            'slug'        => ['nullable', 'string', 'max:170', 'alpha_dash', 'unique:partners,slug' . $unique],
            'logo_url'    => $logoRule,
            'website'     => ['nullable', 'url', 'max:300'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type'        => ['nullable', Rule::in(['institutional', 'financial', 'tech', 'ngo', 'government', 'media'])],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
    }

    protected function uniquePartnerSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'partenaire';
        $slug = $base;
        $i = 2;
        while (
            Partner::where('slug', $slug)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // ═══════════════════════════════════════════════════
    //  ADMIN — Testimonials (Optimisation point 10)
    // ═══════════════════════════════════════════════════

    public function adminTestimonialsIndex(Request $request): JsonResponse
    {
        $query = Testimonial::query();

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('author_name', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        $sort = $request->query('sort', 'sort_order');
        $query = match ($sort) {
            'recent' => $query->orderByDesc('created_at'),
            'rating' => $query->orderByDesc('rating'),
            default  => $query->orderByDesc('is_featured')->orderBy('sort_order'),
        };

        return response()->json($query->paginate(min(50, (int) $request->query('per_page', 20))));
    }

    public function adminTestimonialShow(int $id): JsonResponse
    {
        return response()->json(['data' => Testimonial::findOrFail($id)]);
    }

    public function adminStoreTestimonial(Request $request): JsonResponse
    {
        $data = $this->validateTestimonial($request);
        $data['is_featured'] = $request->boolean('is_featured', false);
        $data['is_active']   = $request->boolean('is_active', true);

        $testimonial = Testimonial::create($data);

        Log::info('admin.testimonial.created', [
            'admin_id'        => $request->user()->id,
            'testimonial_id'  => $testimonial->id,
            'author'          => $testimonial->author_name,
        ]);

        return response()->json([
            'message' => "Témoignage de « {$testimonial->author_name} » créé.",
            'data'    => $testimonial,
        ], 201);
    }

    public function adminUpdateTestimonial(Request $request, int $id): JsonResponse
    {
        $testimonial = Testimonial::findOrFail($id);
        $data = $this->validateTestimonial($request, $id);

        $testimonial->update($data);

        Log::info('admin.testimonial.updated', [
            'admin_id'       => $request->user()->id,
            'testimonial_id' => $testimonial->id,
            'changes'        => array_keys($data),
        ]);

        return response()->json([
            'message' => "Témoignage de « {$testimonial->author_name} » mis à jour.",
            'data'    => $testimonial->fresh(),
        ]);
    }

    public function adminDestroyTestimonial(Request $request, int $id): JsonResponse
    {
        $testimonial = Testimonial::findOrFail($id);

        Log::warning('admin.testimonial.deleted', [
            'admin_id'       => $request->user()->id,
            'testimonial_id' => $testimonial->id,
            'author'         => $testimonial->author_name,
        ]);

        $name = $testimonial->author_name;
        $testimonial->delete();

        return response()->json([
            'message' => "Témoignage de « {$name} » supprimé.",
            'id'      => $id,
        ]);
    }

    protected function validateTestimonial(Request $request, ?int $id = null): array
    {
        $nameRule   = $id ? ['sometimes', 'string', 'max:150'] : ['required', 'string', 'max:150'];
        $roleRule   = $id ? ['sometimes', 'string', 'max:150'] : ['required', 'string', 'max:150'];
        $contentRule = $id ? ['sometimes', 'string', 'max:3000'] : ['required', 'string', 'max:3000'];

        return $request->validate([
            'author_name'    => $nameRule,
            'author_role'    => $roleRule,
            'author_avatar'  => ['nullable', 'string', 'max:500'],
            'author_country' => ['nullable', 'string', 'max:80'],
            'content'        => $contentRule,
            'rating'         => ['nullable', 'integer', 'min:1', 'max:5'],
            'project_title'  => ['nullable', 'string', 'max:200'],
            'is_featured'    => ['nullable', 'boolean'],
            'is_active'      => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
    }
}
