<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    // ───────────────── Admin CRUD (Optimisation point 4) ─────────────────

    /**
     * Admin — paginated list of every sector with project counts.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Category::withCount('projects')
            ->withCount('subCategories');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'name');
        $query = match ($sort) {
            'recent'   => $query->orderByDesc('created_at'),
            'projects' => $query->orderByDesc('projects_count'),
            default    => $query->orderBy('name'),
        };

        return response()->json($query->paginate(min(50, (int) $request->query('per_page', 20))));
    }

    /**
     * Admin — create a new sector.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'slug'  => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:categories,slug'],
            'icon'  => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['name']);

        $category = Category::create($data);

        Log::info('admin.sector.created', [
            'admin_id' => $request->user()->id,
            'sector_id' => $category->id,
            'slug'     => $category->slug,
        ]);

        return response()->json([
            'message' => "Secteur « {$category->name} » créé.",
            'data'    => $category,
        ], 201);
    }

    /**
     * Admin — update a sector.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],
            'slug'  => ['sometimes', 'string', 'max:120', 'alpha_dash', 'unique:categories,slug,' . $id],
            'icon'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $category->update($data);

        Log::info('admin.sector.updated', [
            'admin_id' => $request->user()->id,
            'sector_id' => $category->id,
            'changes'  => array_keys($data),
        ]);

        return response()->json([
            'message' => "Secteur « {$category->name} » mis à jour.",
            'data'    => $category->fresh(),
        ]);
    }

    /**
     * Admin — delete a sector.
     *
     * Safeguard: refuse if the sector still has projects unless `force=true`.
     * Foreign-key cascade in projects table is `nullOnDelete`, so the projects
     * survive but their category_id becomes null.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::withCount('projects')->findOrFail($id);

        if ($category->projects_count > 0 && !$request->boolean('force')) {
            return response()->json([
                'message'        => "Ce secteur contient {$category->projects_count} projet(s). Passez `force: true` pour confirmer (les projets seront détachés).",
                'projects_count' => $category->projects_count,
                'requires_force' => true,
            ], 422);
        }

        Log::warning('admin.sector.deleted', [
            'admin_id'       => $request->user()->id,
            'sector_id'      => $category->id,
            'slug'           => $category->slug,
            'projects_count' => $category->projects_count,
            'forced'         => $request->boolean('force'),
        ]);

        $category->delete();

        return response()->json([
            'message' => "Secteur « {$category->name} » supprimé.",
            'id'      => $id,
        ]);
    }

    /**
     * Build a unique slug from a name, appending -2, -3… on collision.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
