<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Mentorship;
use App\Models\Project;
use App\Models\Role;
use App\Models\Training;
use App\Models\TrainingPurchase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            ->findOrFail($id)
            // Admin oversight needs the compliance + contact fields that
            // are hidden by default for relation-loaded users.
            ->makeVisible(User::SELF_VISIBLE);

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
            'user' => $user->load('roles:id,slug,name')->makeVisible(User::SELF_VISIBLE),
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
            'user' => $user->fresh()->load('roles:id,slug,name')->makeVisible(User::SELF_VISIBLE),
            'action' => $action,
            'message' => $action === 'added' ? "Rôle « $slug » ajouté." : "Rôle « $slug » retiré.",
        ]);
    }

    // ───────────────── Admin destructive actions ─────────────────

    /**
     * Hard-delete a user.
     *
     * Safeguards:
     *   - cannot delete self
     *   - cannot delete the last remaining admin
     *   - deleting an admin requires the `confirm=true` flag
     *   - any owned data (projects, investments) is **also** deleted via FK cascades
     *     declared in their respective migrations
     */
    public function destroyUser(Request $request, int $id): JsonResponse
    {
        $admin  = $request->user();
        $target = User::with('roles:id,slug')->findOrFail($id);

        if ($admin->id === $target->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $isAdmin = $target->roles->contains('slug', 'admin');
        if ($isAdmin) {
            $remainingAdmins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
                ->where('id', '!=', $target->id)
                ->count();
            if ($remainingAdmins < 1) {
                return response()->json(['message' => 'Impossible de supprimer le dernier administrateur.'], 422);
            }
            if (!$request->boolean('confirm')) {
                return response()->json([
                    'message'         => "Confirmez la suppression d'un autre administrateur en passant `confirm: true`.",
                    'requires_confirm' => true,
                ], 422);
            }
        }

        $name  = $target->name;
        $email = $target->email;

        // Audit 2026-05 — admin destructive actions need an immutable trail
        // (RGPD Art. 30, LCB-FT Art. 35). We log before the delete so the
        // snapshot survives even if the row is gone.
        Log::warning('admin.destroy_user', [
            'admin_id'   => $admin->id,
            'admin_email' => $admin->email,
            'target_id'  => $id,
            'target_email' => $email,
            'target_was_admin' => $isAdmin,
            'ip'         => $request->ip(),
        ]);

        $target->delete();

        return response()->json([
            'message' => "Utilisateur « {$name} » supprimé.",
            'id'      => $id,
        ]);
    }

    /**
     * "Demote a mentor" — strips the mentor role, cancels their pending /
     * accepted mentorships. The user account itself stays alive.
     *
     * Pass `?delete_account=true` to also hard-delete the user (subject to the
     * same safeguards as destroyUser).
     */
    public function destroyMentor(Request $request, int $id): JsonResponse
    {
        $target = User::with('roles:id,slug')->findOrFail($id);

        if (!$target->roles->contains('slug', 'mentor')) {
            return response()->json(['message' => "Cet utilisateur n'est pas mentor."], 422);
        }

        $cancelled = DB::transaction(function () use ($target) {
            $cancelled = Mentorship::where('mentor_id', $target->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->update(['status' => 'cancelled']);

            // Detach the role; falls back to lone remaining role if any.
            if ($target->roles()->count() > 1) {
                $target->roles()->detach(Role::where('slug', 'mentor')->value('id'));
                if ($target->active_role_slug === 'mentor') {
                    $next = $target->roles()->first();
                    $target->update(['active_role_slug' => $next?->slug]);
                }
            } else {
                // mentor is their only role → fall back to a baseline role
                $baseline = Role::firstOrCreate(['slug' => 'investor'], ['name' => 'Investisseur']);
                $target->roles()->sync([$baseline->id]);
                $target->update(['active_role_slug' => 'investor']);
            }

            return $cancelled;
        });

        // Audit 2026-05 — log before mutation visible.
        Log::info('admin.destroy_mentor', [
            'admin_id'              => $request->user()->id,
            'target_id'             => $id,
            'mentorships_cancelled' => $cancelled,
            'delete_account'        => $request->boolean('delete_account'),
        ]);

        // Optional account hard-delete in the same call.
        if ($request->boolean('delete_account')) {
            return $this->destroyUser($request, $id);
        }

        return response()->json([
            'message'              => "Statut mentor retiré pour « {$target->name} ».",
            'mentorships_cancelled' => $cancelled,
            'user'                 => $target->fresh(['roles:id,slug,name']),
        ]);
    }

    // ───────────────── Trainings (admin) ─────────────────

    /**
     * List every training (published or not) with stats.
     */
    public function trainings(Request $request): JsonResponse
    {
        $query = Training::with('instructor:id,name,email')
            ->withCount(['purchases as active_purchases_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('purchases');

        if ($search = $request->query('q')) {
            $query->where('title', 'like', "%{$search}%");
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $sort = $request->query('sort', 'recent');
        $query = match ($sort) {
            'popular'  => $query->orderByDesc('purchases_count'),
            'price'    => $query->orderByDesc('price'),
            default    => $query->orderByDesc('created_at'),
        };

        return response()->json($query->paginate(20));
    }

    /**
     * Hard-delete a training.
     *
     * Safeguards:
     *   - if there are ACTIVE (paid, non-refunded) purchases, refuse unless
     *     `force=true` is passed; in that case mark the purchases as cancelled
     *     and revoke access first.
     */
    public function destroyTraining(Request $request, int $id): JsonResponse
    {
        $training = Training::findOrFail($id);

        $activeCount = TrainingPurchase::where('training_id', $training->id)
            ->where('status', 'active')
            ->count();

        if ($activeCount > 0 && !$request->boolean('force')) {
            return response()->json([
                'message'                => "Cette formation a {$activeCount} apprenant(s) actif(s). Passez `force: true` pour confirmer.",
                'active_purchases'      => $activeCount,
                'requires_force'        => true,
            ], 422);
        }

        // Audit 2026-05 — log before mutation.
        Log::warning('admin.destroy_training', [
            'admin_id'         => $request->user()->id,
            'training_id'      => $training->id,
            'title'            => $training->title,
            'active_purchases' => $activeCount,
            'forced'           => $request->boolean('force'),
        ]);

        DB::transaction(function () use ($training) {
            // Revoke access for any still-active purchases.
            TrainingPurchase::where('training_id', $training->id)
                ->where('status', 'active')
                ->update([
                    'status'             => 'cancelled',
                    'access_revoked_at' => now(),
                ]);

            $training->delete();
        });

        return response()->json([
            'message' => "Formation « {$training->title} » supprimée.",
            'id'      => $id,
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
