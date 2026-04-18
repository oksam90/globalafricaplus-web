<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Public listing with advanced filters & sort.
     *
     * Query params:
     *  - search, country, category, sub_category, stage, sdg
     *  - amount_min, amount_max
     *  - sort (recent|popular|trending|ending|progress|jobs)
     *  - per_page (default 12)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->with(['category:id,slug,name,color,icon', 'subCategory:id,category_id,slug,name', 'user:id,name,country,avatar', 'sdgs:id,number,name,color'])
            ->withCount('followers')
            ->published();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('summary', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        $query->forCategory($request->input('category'))
              ->forSubCategory($request->input('sub_category'))
              ->forSdg($request->input('sdg'));

        if ($stage = $request->input('stage')) {
            $query->where('stage', $stage);
        }

        if ($request->filled('amount_min')) {
            $query->where('amount_needed', '>=', (float) $request->input('amount_min'));
        }
        if ($request->filled('amount_max')) {
            $query->where('amount_needed', '<=', (float) $request->input('amount_max'));
        }

        $query->sort($request->input('sort'));

        $perPage = min(48, (int) $request->input('per_page', 12));
        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $project = Project::with([
            'category', 'subCategory', 'user:id,name,country,avatar,bio',
            'sdgs:id,number,name,color',
            'updates' => fn ($q) => $q->limit(10),
            'updates.author:id,name,avatar',
        ])
            ->withCount(['followers', 'updates', 'investments'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Only show non-published projects to the owner / admin
        if ($project->status !== 'published') {
            $user = $request->user();
            if (!$user || ($project->user_id !== $user->id && !$user->hasRole('admin'))) {
                abort(404);
            }
        }

        $project->increment('views_count');

        // Similar projects (same category, published, exclude self)
        $related = Project::published()
            ->with('category:id,slug,name,color')
            ->where('id', '!=', $project->id)
            ->where(function ($q) use ($project) {
                $q->where('category_id', $project->category_id);
                if ($project->country) {
                    $q->orWhere('country', $project->country);
                }
            })
            ->orderByDesc('followers_count')
            ->limit(4)
            ->get();

        $isFollowing = false;
        if ($user = $request->user()) {
            $isFollowing = $project->followers()->where('user_id', $user->id)->exists();
        }

        return response()->json([
            'data' => $project,
            'related' => $related,
            'is_following' => $isFollowing,
            'can_edit' => $request->user() ? $request->user()->can('update', $project) : false,
        ]);
    }

    /**
     * Listing of current user's own projects (all statuses).
     */
    public function mine(Request $request): JsonResponse
    {
        $projects = Project::with(['category:id,slug,name,color'])
            ->withCount(['followers', 'updates', 'investments'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['currency'] ??= 'EUR';
        $data['status'] = $data['status'] ?? 'draft';
        if ($data['status'] === 'published') {
            $data['published_at'] = now();
        }

        $sdgIds = $data['sdg_ids'] ?? [];
        unset($data['sdg_ids']);

        $project = Project::create($data);
        if (!empty($sdgIds)) $project->sdgs()->sync($sdgIds);

        return response()->json(['data' => $project->load(['category', 'subCategory', 'sdgs'])], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();

        // Regenerate slug if title changed
        if (isset($data['title']) && $data['title'] !== $project->title) {
            $data['slug'] = Project::generateUniqueSlug($data['title'], $project->id);
        }

        $sdgIds = $data['sdg_ids'] ?? null;
        unset($data['sdg_ids']);

        $project->update($data);
        if ($sdgIds !== null) $project->sdgs()->sync($sdgIds);

        return response()->json(['data' => $project->fresh(['category', 'subCategory', 'sdgs'])]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();
        return response()->json(['message' => 'Projet supprimé.']);
    }

    public function publish(Request $request, Project $project): JsonResponse
    {
        $this->authorize('publish', $project);

        // Require minimal completeness
        if (blank($project->title) || blank($project->summary) || blank($project->country) || !$project->category_id || (float) $project->amount_needed <= 0) {
            return response()->json([
                'message' => 'Complétez titre, résumé, pays, catégorie et montant avant publication.',
            ], 422);
        }

        $project->update(['status' => 'published', 'published_at' => now()]);

        return response()->json(['data' => $project->fresh()]);
    }

    // ---------- Follow / Unfollow ----------

    public function follow(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        $attached = $project->followers()->syncWithoutDetaching([$user->id]);
        if (!empty($attached['attached'])) {
            $project->increment('followers_count');
        }

        return response()->json(['is_following' => true, 'followers_count' => $project->fresh()->followers_count]);
    }

    public function unfollow(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        $detached = $project->followers()->detach($user->id);
        if ($detached > 0) {
            $project->decrement('followers_count');
        }

        return response()->json(['is_following' => false, 'followers_count' => $project->fresh()->followers_count]);
    }

    // ---------- Project updates (news) ----------

    public function listUpdates(Project $project): JsonResponse
    {
        if ($project->status !== 'published') abort(404);
        $updates = $project->updates()->with('author:id,name,avatar')->paginate(10);
        return response()->json($updates);
    }

    public function storeUpdate(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $update = $project->updates()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $update->load('author:id,name,avatar')], 201);
    }
}
