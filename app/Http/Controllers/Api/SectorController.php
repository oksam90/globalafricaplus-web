<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class SectorController extends Controller
{
    /**
     * List all sectors with aggregate stats.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('subCategories:id,category_id,slug,name')
            ->withCount(['projects as projects_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('name')
            ->get()
            ->map(function ($c) {
                $stats = Project::published()
                    ->where('category_id', $c->id)
                    ->selectRaw('COALESCE(SUM(amount_needed),0) as total_needed, COALESCE(SUM(amount_raised),0) as total_raised, COALESCE(SUM(jobs_target),0) as jobs')
                    ->first();

                return [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'color' => $c->color,
                    'icon' => $c->icon,
                    'projects_count' => $c->projects_count,
                    'sub_categories' => $c->subCategories,
                    'total_needed' => (float) ($stats->total_needed ?? 0),
                    'total_raised' => (float) ($stats->total_raised ?? 0),
                    'jobs_target' => (int) ($stats->jobs ?? 0),
                ];
            });

        return response()->json(['data' => $categories]);
    }

    /**
     * Single sector page with stats and projects preview.
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::with('subCategories')
            ->where('slug', $slug)
            ->firstOrFail();

        $stats = Project::published()
            ->where('category_id', $category->id)
            ->selectRaw('
                COUNT(*) as projects_count,
                COUNT(DISTINCT country) as countries_count,
                COALESCE(SUM(amount_raised),0) as total_raised,
                COALESCE(SUM(amount_needed),0) as total_needed,
                COALESCE(SUM(jobs_target),0) as jobs
            ')
            ->first();

        $topProjects = Project::published()
            ->with('category:id,slug,name,color')
            ->where('category_id', $category->id)
            ->orderByDesc('followers_count')
            ->orderByDesc('views_count')
            ->limit(6)
            ->get();

        return response()->json([
            'data' => $category,
            'stats' => [
                'projects_count' => (int) $stats->projects_count,
                'countries_count' => (int) $stats->countries_count,
                'total_raised' => (float) $stats->total_raised,
                'total_needed' => (float) $stats->total_needed,
                'jobs_target' => (int) $stats->jobs,
            ],
            'top_projects' => $topProjects,
        ]);
    }
}
