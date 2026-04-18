<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    // ──────────────────────────────────────────────
    // PUBLIC — job listings (projects recruiting)
    // ──────────────────────────────────────────────

    /**
     * Paginated list of projects with jobs_target > 0.
     */
    public function listings(Request $request): JsonResponse
    {
        $q = Project::published()
            ->where('jobs_target', '>', 0)
            ->with('category:id,slug,name,color')
            ->with('user:id,name,country,avatar');

        // Filters
        if ($request->filled('country')) {
            $q->where('country', $request->country);
        }
        if ($request->filled('category')) {
            $q->forCategory($request->category);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('title', 'like', $term)
                    ->orWhere('summary', 'like', $term);
            });
        }

        // Sort
        $q->when($request->sort === 'jobs', fn ($b) => $b->orderByDesc('jobs_target'))
          ->when($request->sort === 'recent', fn ($b) => $b->latest('published_at'))
          ->when($request->sort === 'funding', fn ($b) => $b->orderByRaw('(CASE WHEN amount_needed > 0 THEN amount_raised / amount_needed ELSE 0 END) DESC'))
          ->when(!$request->sort, fn ($b) => $b->orderByDesc('jobs_target'));

        $data = $q->paginate(12, [
            'id', 'title', 'slug', 'summary', 'country', 'city',
            'jobs_target', 'stage', 'amount_needed', 'amount_raised',
            'currency', 'user_id', 'category_id', 'published_at',
        ]);

        return response()->json($data);
    }

    /**
     * Stats for the public emploi portal.
     */
    public function stats(): JsonResponse
    {
        $recruiting = Project::published()->where('jobs_target', '>', 0);
        $totalJobs = (int) (clone $recruiting)->sum('jobs_target');
        $projectsCount = (clone $recruiting)->count();
        $countries = (clone $recruiting)->distinct('country')->count('country');

        return response()->json([
            'total_jobs' => $totalJobs,
            'projects_count' => $projectsCount,
            'countries' => $countries,
        ]);
    }

    // ──────────────────────────────────────────────
    // AUTHENTICATED — job applications
    // ──────────────────────────────────────────────

    /**
     * Apply to a project (jobseeker).
     */
    public function apply(Request $request, int $projectId): JsonResponse
    {
        $user = $request->user();
        $project = Project::published()->where('jobs_target', '>', 0)->findOrFail($projectId);

        // Cannot apply to own project
        if ($project->user_id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas candidater à votre propre projet.'], 422);
        }

        // Check uniqueness
        if (JobApplication::where('user_id', $user->id)->where('project_id', $projectId)->exists()) {
            return response()->json(['message' => 'Vous avez déjà candidaté à ce projet.'], 422);
        }

        $data = $request->validate([
            'role_applied' => ['required', 'string', 'max:150'],
            'motivation' => ['required', 'string', 'max:5000'],
            'proposal' => ['nullable', 'string', 'max:5000'],
            'cv_url' => ['nullable', 'url', 'max:300'],
        ]);

        $app = JobApplication::create([
            ...$data,
            'user_id' => $user->id,
            'project_id' => $projectId,
        ]);

        return response()->json(['data' => $app->load('project:id,title,slug')], 201);
    }

    /**
     * My applications (jobseeker).
     */
    public function myApplications(Request $request): JsonResponse
    {
        $apps = JobApplication::where('user_id', $request->user()->id)
            ->with('project:id,title,slug,country,jobs_target,category_id,user_id')
            ->with('project.category:id,name,color')
            ->with('project.user:id,name')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($apps);
    }

    /**
     * Withdraw an application (only if still submitted).
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $app = JobApplication::where('user_id', $request->user()->id)->findOrFail($id);

        if ($app->status !== 'submitted') {
            return response()->json(['message' => 'Impossible de retirer une candidature déjà traitée.'], 422);
        }

        $app->delete();

        return response()->json(['message' => 'Candidature retirée.']);
    }

    // ──────────────────────────────────────────────
    // ENTREPRENEUR — review applications to my projects
    // ──────────────────────────────────────────────

    /**
     * List applications received for a project I own.
     */
    public function projectApplications(Request $request, int $projectId): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($projectId);

        $apps = JobApplication::where('project_id', $project->id)
            ->with('user:id,name,email,country,avatar')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($apps);
    }

    /**
     * Review an application (entrepreneur updates status/score/notes).
     */
    public function reviewApplication(Request $request, int $id): JsonResponse
    {
        $app = JobApplication::findOrFail($id);

        // Verify ownership
        $project = Project::where('id', $app->project_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'under_review', 'shortlisted', 'accepted', 'rejected'])],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'review_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $app->update([
            ...$data,
            'reviewed_at' => now(),
        ]);

        return response()->json(['data' => $app->fresh()->load('user:id,name,email,country,avatar')]);
    }

    // ──────────────────────────────────────────────
    // SKILLS — manage my skills
    // ──────────────────────────────────────────────

    /**
     * List all available skills.
     */
    public function skillsList(): JsonResponse
    {
        $skills = Skill::orderBy('category')->orderBy('name')->get();

        return response()->json(['data' => $skills]);
    }

    /**
     * Get my skills (authenticated user).
     */
    public function mySkills(Request $request): JsonResponse
    {
        $skills = $request->user()->skills()->orderBy('category')->orderBy('name')->get();

        return response()->json(['data' => $skills]);
    }

    /**
     * Sync my skills (replace all).
     */
    public function syncSkills(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skills' => ['required', 'array', 'max:20'],
            'skills.*.id' => ['required', 'exists:skills,id'],
            'skills.*.level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'expert'])],
            'skills.*.years_experience' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $sync = [];
        foreach ($data['skills'] as $s) {
            $sync[$s['id']] = [
                'level' => $s['level'],
                'years_experience' => $s['years_experience'],
            ];
        }

        $request->user()->skills()->sync($sync);

        return response()->json([
            'data' => $request->user()->skills()->orderBy('category')->orderBy('name')->get(),
        ]);
    }
}
