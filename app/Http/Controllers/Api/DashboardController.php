<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\MentorReview;
use App\Models\Mentorship;
use App\Models\MentorshipSession;
use App\Models\CallApplication;
use App\Models\EconomicZone;
use App\Models\FormalizationProgress;
use App\Models\FormalizationStep;
use App\Models\GovernmentCall;
use App\Models\JobApplication;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return role-contextual KPIs + recent activity for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeRole = $user->active_role_slug;

        $data = [
            'common' => $this->commonData($user),
        ];

        // Role-specific data
        $data['role_data'] = match ($activeRole) {
            'entrepreneur' => $this->entrepreneurData($user),
            'investor'     => $this->investorData($user),
            'mentor'       => $this->mentorData($user),
            'jobseeker'    => $this->jobseekerData($user),
            'government'   => $this->governmentData($user),
            'admin'        => $this->adminData(),
            default        => [],
        };

        return response()->json($data);
    }

    private function commonData($user): array
    {
        return [
            'member_since' => $user->created_at?->format('d/m/Y'),
            'roles_count' => $user->roles()->count(),
            'kyc_level' => $user->kyc_level,
            'country' => $user->country,
            'is_diaspora' => $user->is_diaspora,
        ];
    }

    private function entrepreneurData($user): array
    {
        $projects = Project::where('user_id', $user->id);
        $published = (clone $projects)->where('status', 'published');

        $totalRaised = (float) (clone $published)->sum('amount_raised');
        $totalNeeded = (float) (clone $published)->sum('amount_needed');
        $totalViews = (int) (clone $published)->sum('views_count');
        $totalFollowers = (int) (clone $published)->sum('followers_count');
        $totalJobs = (int) (clone $published)->sum('jobs_target');

        $recentProjects = Project::where('user_id', $user->id)
            ->with('category:id,slug,name,color')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'slug', 'status', 'stage', 'amount_needed', 'amount_raised', 'views_count', 'followers_count', 'category_id', 'created_at']);

        // Mentorships as mentee
        $mentorships = Mentorship::where('mentee_id', $user->id)
            ->with('mentor:id,name,avatar,country')
            ->whereIn('status', ['accepted', 'requested'])
            ->limit(3)
            ->get();

        // Formalization progress
        $formalizationData = null;
        if ($user->country) {
            $stepsCount = FormalizationStep::where('country', $user->country)->count();
            if ($stepsCount > 0) {
                $stepIds = FormalizationStep::where('country', $user->country)->pluck('id');
                $completedSteps = FormalizationProgress::where('user_id', $user->id)
                    ->whereIn('step_id', $stepIds)
                    ->where('status', 'completed')
                    ->count();
                $inProgressSteps = FormalizationProgress::where('user_id', $user->id)
                    ->whereIn('step_id', $stepIds)
                    ->where('status', 'in_progress')
                    ->count();
                $formalizationData = [
                    'country' => $user->country,
                    'total_steps' => $stepsCount,
                    'completed' => $completedSteps,
                    'in_progress' => $inProgressSteps,
                    'completion' => round(($completedSteps / $stepsCount) * 100),
                ];
            }
        }

        // Legal status from profile
        $profile = $user->profileFor('entrepreneur');
        $legalStatus = $profile?->data['legal_status'] ?? null;

        // Job applications received
        $projectIds = Project::where('user_id', $user->id)->pluck('id');
        $jobAppsReceived = JobApplication::whereIn('project_id', $projectIds)->count();
        $jobAppsPending = JobApplication::whereIn('project_id', $projectIds)->where('status', 'submitted')->count();

        return [
            'kpis' => [
                ['label' => 'Projets publiés', 'value' => (clone $published)->count(), 'icon' => '🚀'],
                ['label' => 'Montant levé', 'value' => $this->fmtMoney($totalRaised), 'icon' => '💰'],
                ['label' => 'Vues totales', 'value' => $totalViews, 'icon' => '👁️'],
                ['label' => 'Followers', 'value' => $totalFollowers, 'icon' => '❤️'],
                ['label' => 'Emplois visés', 'value' => $totalJobs, 'icon' => '👷'],
                ['label' => 'Candidatures reçues', 'value' => $jobAppsReceived, 'icon' => '📨', 'alert' => $jobAppsPending > 0],
            ],
            'funding_progress' => $totalNeeded > 0 ? round(($totalRaised / $totalNeeded) * 100, 1) : 0,
            'total_raised' => $totalRaised,
            'total_needed' => $totalNeeded,
            'recent_projects' => $recentProjects,
            'active_mentorships' => $mentorships,
            'formalization' => $formalizationData,
            'legal_status' => $legalStatus,
            'job_applications_pending' => $jobAppsPending,
        ];
    }

    private function investorData($user): array
    {
        $investments = Investment::where('investor_id', $user->id);
        $confirmed = (clone $investments)->whereIn('status', ['escrow', 'released']);

        $totalInvested = (float) (clone $confirmed)->sum('amount');
        $projectsCount = (clone $confirmed)->distinct('project_id')->count('project_id');

        $recentInvestments = Investment::where('investor_id', $user->id)
            ->with('project:id,title,slug,country,category_id,amount_needed,amount_raised')
            ->with('project.category:id,name,color')
            ->latest()
            ->limit(5)
            ->get();

        // Projects followed
        $followedCount = DB::table('project_followers')->where('user_id', $user->id)->count();

        return [
            'kpis' => [
                ['label' => 'Investissements', 'value' => (clone $investments)->count(), 'icon' => '📊'],
                ['label' => 'Total investi', 'value' => $this->fmtMoney($totalInvested), 'icon' => '💰'],
                ['label' => 'Projets financés', 'value' => $projectsCount, 'icon' => '🚀'],
                ['label' => 'Projets suivis', 'value' => $followedCount, 'icon' => '❤️'],
                ['label' => 'En attente', 'value' => (clone $investments)->where('status', 'pending')->count(), 'icon' => '⏳'],
                ['label' => 'En escrow', 'value' => (clone $investments)->where('status', 'escrow')->count(), 'icon' => '🔒'],
            ],
            'total_invested' => $totalInvested,
            'recent_investments' => $recentInvestments,
        ];
    }

    private function mentorData($user): array
    {
        $asMentor = Mentorship::where('mentor_id', $user->id);
        $pending = (clone $asMentor)->where('status', 'requested')->count();
        $active = (clone $asMentor)->where('status', 'accepted')->count();
        $completed = (clone $asMentor)->where('status', 'completed')->count();
        $avgRating = round(MentorReview::where('reviewed_id', $user->id)->avg('rating') ?? 0, 1);
        $reviewsCount = MentorReview::where('reviewed_id', $user->id)->count();
        $sessionsCompleted = MentorshipSession::whereHas('mentorship', fn ($q) => $q->where('mentor_id', $user->id))
            ->where('status', 'completed')
            ->count();

        $pendingRequests = Mentorship::where('mentor_id', $user->id)
            ->where('status', 'requested')
            ->with('mentee:id,name,avatar,country')
            ->with('skill:id,name')
            ->latest()
            ->limit(5)
            ->get();

        $activeMentorships = Mentorship::where('mentor_id', $user->id)
            ->where('status', 'accepted')
            ->with('mentee:id,name,avatar,country')
            ->with('skill:id,name')
            ->withCount('sessions')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'kpis' => [
                ['label' => 'Demandes en attente', 'value' => $pending, 'icon' => '📨', 'alert' => $pending > 0],
                ['label' => 'Mentorats actifs', 'value' => $active, 'icon' => '🔄'],
                ['label' => 'Mentorats terminés', 'value' => $completed, 'icon' => '✅'],
                ['label' => 'Sessions réalisées', 'value' => $sessionsCompleted, 'icon' => '💬'],
                ['label' => 'Note moyenne', 'value' => $avgRating > 0 ? $avgRating . '★' : '—', 'icon' => '⭐'],
                ['label' => 'Avis reçus', 'value' => $reviewsCount, 'icon' => '📝'],
            ],
            'pending_requests' => $pendingRequests,
            'active_mentorships' => $activeMentorships,
        ];
    }

    private function jobseekerData($user): array
    {
        $skillsCount = $user->skills()->count();

        // Projects with jobs
        $jobProjects = Project::published()
            ->where('jobs_target', '>', 0)
            ->count();

        // My applications
        $myApps = JobApplication::where('user_id', $user->id);
        $totalApps = (clone $myApps)->count();
        $pendingApps = (clone $myApps)->where('status', 'submitted')->count();
        $acceptedApps = (clone $myApps)->where('status', 'accepted')->count();
        $rejectedApps = (clone $myApps)->where('status', 'rejected')->count();

        // Recent applications
        $recentApplications = JobApplication::where('user_id', $user->id)
            ->with('project:id,title,slug,country,jobs_target,category_id')
            ->with('project.category:id,name,color')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Top skills
        $topSkills = $user->skills()
            ->orderByRaw("FIELD(skill_user.level, 'expert', 'advanced', 'intermediate', 'beginner')")
            ->limit(6)
            ->get(['skills.id', 'skills.name', 'skills.category']);

        return [
            'kpis' => [
                ['label' => 'Compétences', 'value' => $skillsCount, 'icon' => '🛠️'],
                ['label' => 'Candidatures', 'value' => $totalApps, 'icon' => '📨'],
                ['label' => 'En attente', 'value' => $pendingApps, 'icon' => '⏳', 'alert' => $pendingApps > 0],
                ['label' => 'Acceptées', 'value' => $acceptedApps, 'icon' => '✅'],
                ['label' => 'Projets recrutant', 'value' => $jobProjects, 'icon' => '💼'],
                ['label' => 'Profil complété', 'value' => $user->profileFor('jobseeker')?->completion . '%', 'icon' => '📄'],
            ],
            'total_applications' => $totalApps,
            'accepted_applications' => $acceptedApps,
            'rejected_applications' => $rejectedApps,
            'recent_applications' => $recentApplications,
            'top_skills' => $topSkills,
        ];
    }

    private function governmentData($user): array
    {
        $myCalls = GovernmentCall::where('user_id', $user->id);
        $callsCount = (clone $myCalls)->count();
        $openCalls = (clone $myCalls)->where('status', 'open')->count();
        $closedCalls = (clone $myCalls)->where('status', 'closed')->count();
        $awardedCalls = (clone $myCalls)->where('status', 'awarded')->count();
        $draftCalls = (clone $myCalls)->where('status', 'draft')->count();
        $totalBudget = (float) (clone $myCalls)->whereIn('status', ['open', 'closed', 'awarded'])->sum('budget');

        // Applications received
        $callIds = GovernmentCall::where('user_id', $user->id)->pluck('id');
        $totalApplications = CallApplication::whereIn('call_id', $callIds)->count();
        $pendingApplications = CallApplication::whereIn('call_id', $callIds)->where('status', 'submitted')->count();

        // ZES
        $myZones = EconomicZone::where('user_id', $user->id)->count();

        // Projects & fundraising in user's country
        $projectsInCountry = 0;
        $raisedInCountry = 0;
        $jobsInCountry = 0;
        if ($user->country) {
            $countryProjects = Project::published()->where('country', $user->country);
            $projectsInCountry = (clone $countryProjects)->count();
            $raisedInCountry = (float) (clone $countryProjects)->sum('amount_raised');
            $jobsInCountry = (int) (clone $countryProjects)->sum('jobs_target');
        }

        // Recent calls
        $recentCalls = GovernmentCall::where('user_id', $user->id)
            ->withCount('applications')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'status', 'sector', 'budget', 'currency', 'closes_at', 'applications_count', 'created_at']);

        // Recent applications needing review
        $pendingReviews = CallApplication::whereIn('call_id', $callIds)
            ->where('status', 'submitted')
            ->with(['user:id,name,country,avatar', 'call:id,title,slug'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'kpis' => [
                ['label' => 'Appels publiés', 'value' => $callsCount - $draftCalls, 'icon' => '🏛️'],
                ['label' => 'Appels ouverts', 'value' => $openCalls, 'icon' => '📢', 'alert' => $openCalls > 0],
                ['label' => 'Candidatures reçues', 'value' => $totalApplications, 'icon' => '📨'],
                ['label' => 'En attente de revue', 'value' => $pendingApplications, 'icon' => '⏳', 'alert' => $pendingApplications > 0],
                ['label' => 'Projets dans mon pays', 'value' => $projectsInCountry, 'icon' => '🚀'],
                ['label' => 'Levé dans mon pays', 'value' => $this->fmtMoney($raisedInCountry), 'icon' => '💰'],
            ],
            'total_budget' => $totalBudget,
            'jobs_in_country' => $jobsInCountry,
            'my_zones_count' => $myZones,
            'draft_calls' => $draftCalls,
            'awarded_calls' => $awardedCalls,
            'recent_calls' => $recentCalls,
            'pending_reviews' => $pendingReviews,
        ];
    }

    private function adminData(): array
    {
        $users = DB::table('users')->count();
        $projects = Project::count();
        $published = Project::where('status', 'published')->count();
        $pending = Project::where('status', 'pending')->count();
        $totalRaised = (float) Project::where('status', 'published')->sum('amount_raised');
        $mentorships = Mentorship::count();

        return [
            'kpis' => [
                ['label' => 'Utilisateurs', 'value' => $users, 'icon' => '👥'],
                ['label' => 'Projets total', 'value' => $projects, 'icon' => '📦'],
                ['label' => 'Publiés', 'value' => $published, 'icon' => '✅'],
                ['label' => 'En attente modération', 'value' => $pending, 'icon' => '⏳', 'alert' => $pending > 0],
                ['label' => 'Total levé', 'value' => $this->fmtMoney($totalRaised), 'icon' => '💰'],
                ['label' => 'Mentorats', 'value' => $mentorships, 'icon' => '🎓'],
            ],
        ];
    }

    private function fmtMoney(float $amount): string
    {
        if ($amount >= 1_000_000) return round($amount / 1_000_000, 1) . 'M €';
        if ($amount >= 1_000) return round($amount / 1_000) . 'k €';
        return $amount . ' €';
    }
}
