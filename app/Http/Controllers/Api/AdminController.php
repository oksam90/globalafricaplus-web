<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Mentorship;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ───────────────── Analytics / Overview ─────────────────

    public function analytics(Request $request): JsonResponse
    {
        // Global KPIs
        $totalUsers = User::count();
        $totalProjects = Project::count();
        $publishedProjects = Project::where('status', 'published')->count();
        $pendingProjects = Project::where('status', 'pending')->count();
        $draftProjects = Project::where('status', 'draft')->count();
        $totalRaised = (float) Project::where('status', 'published')->sum('amount_raised');
        $totalNeeded = (float) Project::where('status', 'published')->sum('amount_needed');
        $totalInvestments = Investment::count();
        $totalMentorships = Mentorship::count();
        $activeMentorships = Mentorship::where('status', 'accepted')->count();

        // Users by role
        $usersByRole = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->select('roles.slug', 'roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.slug', 'roles.name')
            ->orderByDesc('count')
            ->get();

        // Projects by status
        $projectsByStatus = Project::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Projects by country (top 10)
        $projectsByCountry = Project::where('status', 'published')
            ->select('country', DB::raw('count(*) as count'), DB::raw('SUM(amount_raised) as raised'))
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Projects by category
        $projectsByCategory = Project::where('status', 'published')
            ->join('categories', 'categories.id', '=', 'projects.category_id')
            ->select('categories.name', 'categories.color', DB::raw('count(*) as count'), DB::raw('SUM(projects.amount_raised) as raised'))
            ->groupBy('categories.name', 'categories.color')
            ->orderByDesc('count')
            ->get();

        // Recent signups (last 30 days)
        $recentSignups = User::where('created_at', '>=', now()->subDays(30))->count();

        // Registration trend (last 12 months)
        $registrationTrend = User::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // KYC distribution
        $kycDistribution = User::select('kyc_level', DB::raw('count(*) as count'))
            ->groupBy('kyc_level')
            ->get()
            ->pluck('count', 'kyc_level');

        return response()->json([
            'kpis' => [
                'total_users' => $totalUsers,
                'recent_signups' => $recentSignups,
                'total_projects' => $totalProjects,
                'published_projects' => $publishedProjects,
                'pending_projects' => $pendingProjects,
                'draft_projects' => $draftProjects,
                'total_raised' => $totalRaised,
                'total_needed' => $totalNeeded,
                'funding_rate' => $totalNeeded > 0 ? round(($totalRaised / $totalNeeded) * 100, 1) : 0,
                'total_investments' => $totalInvestments,
                'total_mentorships' => $totalMentorships,
                'active_mentorships' => $activeMentorships,
            ],
            'users_by_role' => $usersByRole,
            'projects_by_status' => $projectsByStatus,
            'projects_by_country' => $projectsByCountry,
            'projects_by_category' => $projectsByCategory,
            'registration_trend' => $registrationTrend,
            'kyc_distribution' => $kycDistribution,
        ]);
    }

    // ───────────────── Users Management ─────────────────

    public function users(Request $request): JsonResponse
    {
        $query = User::with(['roles:id,slug,name'])
            ->withCount('roleProfiles');

        // Search
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // Filter by role
        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $role));
        }

        // Filter by KYC level
        if ($kyc = $request->input('kyc_level')) {
            $query->where('kyc_level', $kyc);
        }

        // Filter by country
        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        // Sort
        $sort = $request->input('sort', 'recent');
        $query = match ($sort) {
            'name' => $query->orderBy('name'),
            'email' => $query->orderBy('email'),
            'oldest' => $query->orderBy('created_at', 'asc'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = min(50, (int) $request->input('per_page', 20));

        return response()->json($query->paginate($perPage));
    }

    public function userShow(Request $request, int $id): JsonResponse
    {
        $user = User::with(['roles:id,slug,name', 'roleProfiles.role:id,slug,name'])
            ->findOrFail($id);

        $projectsCount = Project::where('user_id', $id)->count();
        $publishedCount = Project::where('user_id', $id)->where('status', 'published')->count();

        return response()->json([
            'user' => $user,
            'stats' => [
                'projects_count' => $projectsCount,
                'published_count' => $publishedCount,
            ],
        ]);
    }

    public function userUpdate(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150', 'unique:users,email,' . $id],
            'country' => ['sometimes', 'nullable', 'string', 'max:60'],
            'city' => ['sometimes', 'nullable', 'string', 'max:60'],
            'kyc_level' => ['sometimes', 'in:none,basic,verified,certified'],
            'is_diaspora' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return response()->json([
            'user' => $user->load('roles:id,slug,name'),
            'message' => 'Utilisateur mis à jour.',
        ]);
    }

    public function userToggleRole(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $slug = $request->validate(['slug' => ['required', 'string', 'exists:roles,slug']])['slug'];

        if ($user->hasRole($slug)) {
            // Don't allow removing last role
            if ($user->roles()->count() <= 1) {
                return response()->json(['message' => 'Impossible de retirer le dernier rôle.'], 422);
            }
            $user->removeRole($slug);
            $action = 'removed';
        } else {
            $user->assignRole($slug);
            $action = 'added';
        }

        return response()->json([
            'user' => $user->fresh()->load('roles:id,slug,name'),
            'action' => $action,
            'message' => $action === 'added' ? "Rôle « $slug » ajouté." : "Rôle « $slug » retiré.",
        ]);
    }

    // ───────────────── Moderation ─────────────────

    public function moderationQueue(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');

        $query = Project::with([
                'category:id,slug,name,color',
                'user:id,name,email,country,avatar',
            ])
            ->withCount('followers');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $sort = $request->input('sort', 'oldest');
        $query = match ($sort) {
            'recent' => $query->orderByDesc('created_at'),
            'amount' => $query->orderByDesc('amount_needed'),
            default => $query->orderBy('created_at', 'asc'), // oldest first for moderation
        };

        return response()->json($query->paginate(15));
    }

    public function moderateProject(Request $request, int $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,unpublish,delete'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        switch ($data['action']) {
            case 'approve':
                $project->update([
                    'status' => 'published',
                    'published_at' => $project->published_at ?? now(),
                ]);
                $message = 'Projet approuvé et publié.';
                break;

            case 'reject':
                $project->update(['status' => 'rejected']);
                $message = 'Projet rejeté.';
                break;

            case 'unpublish':
                $project->update(['status' => 'draft']);
                $message = 'Projet dé-publié (retour brouillon).';
                break;

            case 'delete':
                $project->delete();
                return response()->json(['message' => 'Projet supprimé définitivement.']);
        }

        return response()->json([
            'project' => $project->fresh(['category', 'user:id,name']),
            'message' => $message,
        ]);
    }

    // ───────────────── Platform config helpers ─────────────────

    public function platformConfig(): JsonResponse
    {
        $roles = Role::withCount('users')->get();
        $countries = User::select('country', DB::raw('count(*) as count'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'roles' => $roles,
            'countries' => $countries,
        ]);
    }
}
