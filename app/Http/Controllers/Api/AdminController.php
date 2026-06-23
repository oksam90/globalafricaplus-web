<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\KYCVerification;
use App\Models\Mentorship;
use App\Models\Project;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Training;
use App\Models\TrainingPurchase;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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

        $subscription = $user->activeSubscription();

        return response()->json([
            'user' => $user,
            'stats' => [
                'projects_count' => $projectsCount,
                'published_count' => $publishedCount,
            ],
            'subscription' => $subscription?->load('plan'),
        ]);
    }

    // ───────────────── Subscriptions (admin grant / revoke) ─────────────────

    /**
     * Admin grants a subscription plan to a user manually — bypasses payment.
     *
     * Typical use: enterprise client paid out-of-band (bank transfer, invoice),
     * or platform comp/trial extension. Any existing active subscription is
     * cancelled first to keep activeSubscription() coherent.
     */
    public function grantSubscription(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'plan_slug'     => ['required', 'string', 'exists:subscription_plans,slug'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'reason'        => ['nullable', 'string', 'max:500'],
        ]);

        $plan = SubscriptionPlan::where('slug', $data['plan_slug'])->firstOrFail();
        $cycle = $data['billing_cycle'];
        $endsAt = $cycle === 'yearly' ? now()->addYear() : now()->addMonth();

        $planColumn = in_array($plan->slug, ['starter', 'pro', 'enterprise'], true) ? $plan->slug : 'starter';

        $admin = $request->user();
        $reason = $data['reason'] ?? null;

        $subscription = DB::transaction(function () use ($user, $plan, $cycle, $endsAt, $planColumn, $admin, $reason) {
            Subscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return Subscription::create([
                'user_id'                  => $user->id,
                'plan_id'                  => $plan->id,
                'plan_slug'                => $planColumn,
                'billing_cycle'            => $cycle,
                'amount'                   => 0,
                'currency'                 => $plan->currency ?: 'XOF',
                'status'                   => 'active',
                'starts_at'                => now(),
                'ends_at'                  => $plan->isFree() ? null : $endsAt,
                'trial_ends_at'            => null,
                'payment_method'           => 'admin_grant',
                'payment_gateway'          => null,
                'gateway_subscription_ref' => null,
                'payment_reference'        => 'admin-grant:' . Str::uuid(),
                'gateway_metadata'         => [
                    'granted_by_admin_id'    => $admin->id,
                    'granted_by_admin_email' => $admin->email,
                    'reason'                 => $reason,
                    'granted_at'             => now()->toIso8601String(),
                ],
            ]);
        });

        Log::warning('admin.grant_subscription', [
            'admin_id'      => $request->user()->id,
            'admin_email'   => $request->user()->email,
            'target_id'     => $user->id,
            'target_email'  => $user->email,
            'plan_slug'     => $plan->slug,
            'billing_cycle' => $cycle,
            'reason'        => $data['reason'] ?? null,
            'ip'            => $request->ip(),
        ]);

        return response()->json([
            'message'      => "Plan « {$plan->name} » activé pour {$user->name}.",
            'subscription' => $subscription->load('plan'),
        ]);
    }

    /**
     * Admin revokes (cancels) the user's active subscription immediately.
     *
     * Unlike user-initiated cancel which keeps access until ends_at, this
     * sets status = cancelled AND ends_at = now() so middleware-gated
     * features cut off immediately.
     */
    public function revokeSubscription(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $sub = $user->activeSubscription();

        if (!$sub) {
            return response()->json(['message' => "Cet utilisateur n'a pas d'abonnement actif."], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $previous = $sub->plan?->name;
        $sub->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'ends_at'      => now(),
            'gateway_metadata' => array_merge((array) $sub->gateway_metadata, [
                'revoked_by_admin_id'    => $request->user()->id,
                'revoked_by_admin_email' => $request->user()->email,
                'revoke_reason'          => $data['reason'] ?? null,
                'revoked_at'             => now()->toIso8601String(),
            ]),
        ]);

        Log::warning('admin.revoke_subscription', [
            'admin_id'        => $request->user()->id,
            'admin_email'     => $request->user()->email,
            'target_id'       => $user->id,
            'target_email'    => $user->email,
            'subscription_id' => $sub->id,
            'plan'            => $previous,
            'reason'          => $data['reason'] ?? null,
            'ip'              => $request->ip(),
        ]);

        return response()->json([
            'message'      => "Abonnement de {$user->name} désactivé.",
            'subscription' => $sub->fresh()->load('plan'),
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
            'kyc_override_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $reason = $data['kyc_override_reason'] ?? null;
        unset($data['kyc_override_reason']); // not a user column

        // Audit-fix 2026-05 — TEMPORARY admin KYC override while Smile sandbox
        // gate (ticket #1757) is unresolved. When the admin promotes a user
        // to verified/certified through this endpoint we cascade the dates
        // that the Smile flow normally writes (kyc_verified_at,
        // kyc_expires_at) AND record an immutable KYCVerification row so the
        // override is traceable post-hoc. Downgrading to basic/none clears
        // the dates so the kyc.smile:verified middleware refuses the user.
        $tierIsRising = array_key_exists('kyc_level', $data)
            && in_array($data['kyc_level'], ['verified', 'certified'], true)
            && $user->kyc_level !== $data['kyc_level'];

        $tierIsDropping = array_key_exists('kyc_level', $data)
            && in_array($data['kyc_level'], ['none', 'basic'], true)
            && in_array($user->kyc_level, ['verified', 'certified'], true);

        DB::transaction(function () use (&$data, $user, $tierIsRising, $tierIsDropping, $request, $reason) {
            if ($tierIsRising) {
                $partnerJobId = 'admin-override:' . $user->id . ':' . Str::uuid();
                $verification = KYCVerification::create([
                    'user_id'           => $user->id,
                    'smile_job_id'      => null,
                    'partner_job_id'    => $partnerJobId,
                    'job_type'          => 'basic_kyc', // closest existing enum value
                    'country'           => $this->resolveIsoCountry($user->country),
                    'id_type'           => 'NATIONAL_ID',
                    'id_number_hash'    => hash_hmac('sha256', $partnerJobId, (string) config('app.key')),
                    'result_code'       => 'ADMOVR',
                    'result_text'       => sprintf('Manual admin override by user #%d', $request->user()->id),
                    'kyc_level_granted' => $data['kyc_level'],
                    'status'            => 'approved',
                    'callback_payload'  => [
                        'override'          => true,
                        'admin_id'          => $request->user()->id,
                        'admin_email'       => $request->user()->email,
                        'target_user_id'    => $user->id,
                        'reason'            => $reason,
                        'note'              => 'Temporary: Smile sandbox /v1/token + /v1/upload still returning 2205 — see ticket #1757.',
                        'created_at'        => now()->toIso8601String(),
                    ],
                    'expires_at'   => now()->addMonths((int) config('smile.kyc_expiry_months', 24)),
                    'submitted_at' => now(),
                    'completed_at' => now(),
                ]);

                $data['kyc_verified_at']     = now();
                $data['kyc_expires_at']      = now()->addMonths((int) config('smile.kyc_expiry_months', 24));
                $data['kyc_verification_id'] = $verification->id;
            } elseif ($tierIsDropping) {
                $data['kyc_verified_at']     = null;
                $data['kyc_expires_at']      = null;
                $data['kyc_verification_id'] = null;
            }

            $user->update($data);
        });

        if ($tierIsRising || $tierIsDropping) {
            Log::warning('admin.kyc_override', [
                'admin_id'    => $request->user()->id,
                'admin_email' => $request->user()->email,
                'target_id'   => $user->id,
                'target_email' => $user->email,
                'from_level'  => $user->getOriginal('kyc_level'),
                'to_level'    => $data['kyc_level'] ?? null,
                'reason'      => $reason,
                'ip'          => $request->ip(),
            ]);
        }

        return response()->json([
            'user' => $user->fresh()->load('roles:id,slug,name')->makeVisible(User::SELF_VISIBLE),
            'message' => $tierIsRising
                ? 'Utilisateur mis à jour — KYC validé manuellement (override admin).'
                : ($tierIsDropping
                    ? 'Utilisateur mis à jour — KYC dégradé, accès financier révoqué.'
                    : 'Utilisateur mis à jour.'),
            'kyc_override' => $tierIsRising || $tierIsDropping,
        ]);
    }

    /**
     * Convertit le `country` d'un utilisateur (stocké comme nom d'affichage,
     * ex. "Sénégal") en code ISO-3166 alpha-2 sûr pour la colonne char(2) de
     * kyc_verifications.
     *
     * Correctif: l'ancien `substr(strtoupper($name), 0, 2)` était byte-based et
     * coupait au milieu d'un caractère multi-octets accentué ("Sénégal" → "S\xC3"),
     * produisant une chaîne UTF-8 invalide → MySQL "Incorrect string value" → 500.
     *
     * On mappe les pays effectivement servis (UEMOA / CEMAC + diaspora courante)
     * et on retombe sur un fallback strictement ASCII (jamais d'UTF-8 invalide).
     */
    private function resolveIsoCountry(?string $country): string
    {
        $c = trim((string) $country);
        if ($c === '') {
            return 'SN';
        }

        // Déjà un code ISO alpha-2 ?
        if (preg_match('/^[A-Za-z]{2}$/', $c)) {
            return strtoupper($c);
        }

        static $map = [
            // UEMOA
            'sénégal' => 'SN', 'senegal' => 'SN',
            "côte d'ivoire" => 'CI', "cote d'ivoire" => 'CI', 'cote divoire' => 'CI',
            'mali' => 'ML',
            'burkina faso' => 'BF', 'burkina' => 'BF',
            'togo' => 'TG',
            'bénin' => 'BJ', 'benin' => 'BJ',
            'niger' => 'NE',
            'guinée-bissau' => 'GW', 'guinee-bissau' => 'GW', 'guinée bissau' => 'GW',
            // CEMAC
            'cameroun' => 'CM', 'cameroon' => 'CM',
            'gabon' => 'GA',
            'tchad' => 'TD', 'chad' => 'TD',
            'congo' => 'CG', 'république du congo' => 'CG',
            'république centrafricaine' => 'CF', 'centrafrique' => 'CF',
            'guinée équatoriale' => 'GQ', 'guinee equatoriale' => 'GQ',
            // Diaspora courante
            'france' => 'FR',
            'belgique' => 'BE', 'belgium' => 'BE',
            'canada' => 'CA',
            'états-unis' => 'US', 'etats-unis' => 'US', 'usa' => 'US', 'united states' => 'US',
            'royaume-uni' => 'GB', 'uk' => 'GB', 'united kingdom' => 'GB',
            'allemagne' => 'DE', 'germany' => 'DE',
            'espagne' => 'ES', 'spain' => 'ES',
            'italie' => 'IT', 'italy' => 'IT',
            'maroc' => 'MA', 'morocco' => 'MA',
            'guinée' => 'GN', 'guinee' => 'GN',
        ];

        $key = mb_strtolower($c, 'UTF-8');
        if (isset($map[$key])) {
            return $map[$key];
        }

        // Fallback strictement ASCII : on retire tout ce qui n'est pas A-Z,
        // on garde les 2 premières lettres. Jamais d'octet UTF-8 invalide.
        $ascii = strtoupper(preg_replace('/[^A-Za-z]/', '', $c));
        return $ascii !== '' ? substr($ascii, 0, 2) : 'SN';
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
     * Show a single training for the admin edit modal.
     */
    public function showTraining(Request $request, int $id): JsonResponse
    {
        $training = Training::with('instructor:id,name,email')
            ->withCount(['purchases as active_purchases_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('purchases')
            ->findOrFail($id);

        return response()->json(['data' => $training]);
    }

    /**
     * Admin — create a new training (Optimisation point 6).
     *
     * Defaults `user_id` to the calling admin if none is supplied (most admins
     * create platform-published trainings rather than instructor-authored ones).
     */
    public function storeTraining(Request $request): JsonResponse
    {
        $data = $this->validateTraining($request);

        if (empty($data['user_id'])) {
            $data['user_id'] = $request->user()->id;
        }

        $training = Training::create($data);

        Log::info('admin.training.created', [
            'admin_id'    => $request->user()->id,
            'training_id' => $training->id,
            'title'       => $training->title,
        ]);

        return response()->json([
            'message' => "Formation « {$training->title} » créée.",
            'data'    => $training->fresh(['instructor:id,name,email']),
        ], 201);
    }

    /**
     * Admin — update an existing training.
     */
    public function updateTraining(Request $request, int $id): JsonResponse
    {
        $training = Training::findOrFail($id);
        $data = $this->validateTraining($request, $id);

        $training->update($data);

        Log::info('admin.training.updated', [
            'admin_id'    => $request->user()->id,
            'training_id' => $training->id,
            'changes'     => array_keys($data),
        ]);

        return response()->json([
            'message' => "Formation « {$training->title} » mise à jour.",
            'data'    => $training->fresh(['instructor:id,name,email']),
        ]);
    }

    /**
     * Shared validation for training create / update. Slug is optional on
     * create (the model boot hook generates one from the title), but if it
     * is supplied it must be unique.
     */
    protected function validateTraining(Request $request, ?int $id = null): array
    {
        $unique = $id ? ',' . $id : '';

        return $request->validate([
            'title'             => ['required', 'string', 'max:200'],
            'slug'              => ['nullable', 'string', 'max:220', 'alpha_dash', 'unique:trainings,slug' . $unique],
            'summary'           => ['nullable', 'string', 'max:1000'],
            'description'       => ['nullable', 'string', 'max:30000'],
            'cover_image'       => ['nullable', 'string', 'max:500'],
            'video_preview_url' => ['nullable', 'url', 'max:500'],
            'content_url'       => ['nullable', 'url', 'max:500'],
            'category'          => ['nullable', 'string', 'max:80'],
            'level'             => ['nullable', 'in:beginner,intermediate,advanced'],
            'duration_minutes'  => ['nullable', 'integer', 'min:1', 'max:65535'],
            'curriculum'        => ['nullable', 'array'],
            'curriculum.*'      => ['string', 'max:300'],
            'price'             => ['required', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3', 'alpha'],
            'is_published'      => ['nullable', 'boolean'],
            'user_id'           => ['nullable', 'integer', 'exists:users,id'],
        ]);
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

    // ───────────────── Media uploads (admin) ─────────────────

    /**
     * Generic admin image uploader.
     *
     * Stores the file under `storage/app/public/<folder>/` and returns the
     * publicly-resolvable URL via the `public` disk. Used by the partner
     * modal and reusable for any admin media field (training covers,
     * testimonial avatars, ad banners…).
     *
     * Request:
     *   - file:   image file (required)
     *   - folder: target sub-directory (default: misc) — sanitised to
     *             [a-z0-9_-] so callers can pass `partners`, `logos`, etc.
     *
     * Response: { url, path, mime, size_kb }
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,svg,gif', 'max:4096'],
            'folder' => ['nullable', 'string', 'max:60'],
        ]);

        $folder = preg_replace('/[^a-z0-9_-]/i', '', (string) $request->input('folder', 'misc')) ?: 'misc';

        $path = $request->file('file')->store($folder, 'public');
        $url  = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

        Log::info('admin.upload_image', [
            'admin_id' => $request->user()->id,
            'folder'   => $folder,
            'path'     => $path,
            'mime'     => $request->file('file')->getMimeType(),
            'size_kb'  => (int) round($request->file('file')->getSize() / 1024),
        ]);

        return response()->json([
            'url'     => $url,
            'path'    => $path,
            'mime'    => $request->file('file')->getMimeType(),
            'size_kb' => (int) round($request->file('file')->getSize() / 1024),
        ], 201);
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

        $subscriptionPlans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'price_monthly', 'price_yearly', 'currency']);

        return response()->json([
            'roles' => $roles,
            'countries' => $countries,
            'subscription_plans' => $subscriptionPlans,
        ]);
    }
}
