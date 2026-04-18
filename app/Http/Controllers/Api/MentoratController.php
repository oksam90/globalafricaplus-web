<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorAvailability;
use App\Models\MentorReview;
use App\Models\Mentorship;
use App\Models\MentorshipSession;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MentoratController extends Controller
{
    // ───────────────── PUBLIC ─────────────────

    /**
     * Mentorat stats for the portal.
     */
    public function stats(): JsonResponse
    {
        $mentorRoleId = Role::where('slug', 'mentor')->value('id');

        $mentorsCount = DB::table('role_user')->where('role_id', $mentorRoleId)->count();

        $activeMentorships = Mentorship::where('status', 'accepted')->count();
        $completedMentorships = Mentorship::where('status', 'completed')->count();
        $sessionsCompleted = MentorshipSession::where('status', 'completed')->count();
        $countriesCount = User::whereHas('roles', fn ($q) => $q->where('slug', 'mentor'))
            ->whereNotNull('country')
            ->distinct('country')
            ->count('country');
        $avgRating = round(MentorReview::avg('rating') ?? 0, 1);
        $skillsCount = Skill::count();

        return response()->json([
            'mentors_count' => $mentorsCount,
            'active_mentorships' => $activeMentorships,
            'completed_mentorships' => $completedMentorships,
            'sessions_completed' => $sessionsCompleted,
            'countries_count' => $countriesCount,
            'avg_rating' => $avgRating,
            'skills_count' => $skillsCount,
        ]);
    }

    /**
     * Skills list grouped by category.
     */
    public function skills(): JsonResponse
    {
        $skills = Skill::orderBy('category')->orderBy('name')->get();
        $grouped = $skills->groupBy('category')->map(fn ($items) => $items->values());

        return response()->json([
            'data' => $skills,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Public mentor directory with search & filters.
     *
     * Query: search, country, skill, sort (rating|sessions|recent), per_page
     */
    public function mentors(Request $request): JsonResponse
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'mentor'))
            ->with([
                'skills' => fn ($q) => $q->select('skills.id', 'skills.slug', 'skills.name', 'skills.category')
                    ->withPivot('level', 'years_experience'),
                'mentorAvailabilities' => fn ($q) => $q->where('is_active', true),
            ])
            ->withCount([
                'mentorshipsAsMentor as mentorships_active_count' => fn ($q) => $q->where('status', 'accepted'),
                'mentorshipsAsMentor as mentorships_completed_count' => fn ($q) => $q->where('status', 'completed'),
                'mentorReviewsReceived as reviews_count',
            ])
            ->withAvg('mentorReviewsReceived as avg_rating', 'rating');

        // Search by name or bio
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('bio', 'like', "%$search%");
            });
        }

        // Filter by country
        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        // Filter by skill
        if ($skill = $request->input('skill')) {
            $query->whereHas('skills', fn ($q) => $q->where('skills.slug', $skill));
        }

        // Sort
        $sort = $request->input('sort', 'rating');
        $query = match ($sort) {
            'sessions' => $query->orderByDesc('mentorships_completed_count'),
            'recent' => $query->latest(),
            default => $query->orderByDesc('avg_rating')->orderByDesc('mentorships_completed_count'),
        };

        // Only select safe fields
        $query->select([
            'id', 'name', 'country', 'city', 'avatar', 'bio',
            'is_diaspora', 'kyc_level', 'created_at',
        ]);

        $perPage = min(24, (int) $request->input('per_page', 12));

        return response()->json($query->paginate($perPage));
    }

    /**
     * Public mentor profile detail.
     */
    public function mentorShow(int $id): JsonResponse
    {
        $mentor = User::where('id', $id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'mentor'))
            ->with([
                'skills' => fn ($q) => $q->withPivot('level', 'years_experience'),
                'mentorAvailabilities' => fn ($q) => $q->where('is_active', true)->orderByRaw("FIELD(day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday')"),
                'roleProfiles' => fn ($q) => $q->whereHas('role', fn ($r) => $r->where('slug', 'mentor')),
                'roleProfiles.role',
            ])
            ->withCount([
                'mentorshipsAsMentor as mentorships_completed_count' => fn ($q) => $q->where('status', 'completed'),
                'mentorshipsAsMentor as mentorships_active_count' => fn ($q) => $q->where('status', 'accepted'),
                'mentorReviewsReceived as reviews_count',
            ])
            ->withAvg('mentorReviewsReceived as avg_rating', 'rating')
            ->select(['id', 'name', 'country', 'city', 'avatar', 'bio', 'is_diaspora', 'kyc_level', 'created_at'])
            ->firstOrFail();

        // Latest reviews
        $reviews = MentorReview::where('reviewed_id', $id)
            ->with('reviewer:id,name,avatar,country')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $mentor,
            'reviews' => $reviews,
        ]);
    }

    // ───────────────── AUTHENTICATED ─────────────────

    /**
     * Request mentorship (mentee → mentor).
     */
    public function requestMentorship(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mentor_id' => ['required', 'exists:users,id'],
            'skill_id' => ['nullable', 'exists:skills,id'],
            'topic' => ['required', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'duration_weeks' => ['sometimes', 'integer', 'min:2', 'max:52'],
        ]);

        $user = $request->user();

        // Cannot mentor yourself
        if ((int) $data['mentor_id'] === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous mentorer vous-même.'], 422);
        }

        // Check mentor has the mentor role
        $mentor = User::findOrFail($data['mentor_id']);
        if (!$mentor->hasRole('mentor')) {
            return response()->json(['message' => "Cet utilisateur n'est pas mentor."], 422);
        }

        // Check for duplicate active request
        $exists = Mentorship::where('mentor_id', $data['mentor_id'])
            ->where('mentee_id', $user->id)
            ->whereIn('status', ['requested', 'accepted'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Vous avez déjà une demande active avec ce mentor.'], 409);
        }

        $mentorship = Mentorship::create([
            'mentor_id' => $data['mentor_id'],
            'mentee_id' => $user->id,
            'skill_id' => $data['skill_id'] ?? null,
            'topic' => $data['topic'],
            'message' => $data['message'] ?? null,
            'goals' => $data['goals'] ?? null,
            'duration_weeks' => $data['duration_weeks'] ?? 8,
            'status' => 'requested',
        ]);

        return response()->json([
            'data' => $mentorship->load(['mentor:id,name,avatar,country', 'mentee:id,name,avatar,country', 'skill']),
        ], 201);
    }

    /**
     * My mentorships (as mentor + as mentee).
     */
    public function myMentorships(Request $request): JsonResponse
    {
        $user = $request->user();

        $asMentor = Mentorship::forMentor($user->id)
            ->with(['mentee:id,name,avatar,country', 'skill:id,name', 'sessions'])
            ->withCount('sessions')
            ->latest()
            ->get();

        $asMentee = Mentorship::forMentee($user->id)
            ->with(['mentor:id,name,avatar,country', 'skill:id,name', 'sessions'])
            ->withCount('sessions')
            ->latest()
            ->get();

        return response()->json([
            'as_mentor' => $asMentor,
            'as_mentee' => $asMentee,
        ]);
    }

    /**
     * Respond to a mentorship request (accept/decline).
     */
    public function respond(Request $request, Mentorship $mentorship): JsonResponse
    {
        // Only the mentor can respond
        if ($mentorship->mentor_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($mentorship->status !== 'requested') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 422);
        }

        $data = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        if ($data['action'] === 'accept') {
            $mentorship->update(['status' => 'accepted', 'accepted_at' => now()]);
        } else {
            $mentorship->update(['status' => 'declined']);
        }

        return response()->json([
            'data' => $mentorship->fresh(['mentor:id,name,avatar,country', 'mentee:id,name,avatar,country', 'skill']),
        ]);
    }

    /**
     * Complete a mentorship.
     */
    public function complete(Request $request, Mentorship $mentorship): JsonResponse
    {
        $user = $request->user();
        if ($mentorship->mentor_id !== $user->id && $mentorship->mentee_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($mentorship->status !== 'accepted') {
            return response()->json(['message' => 'Seul un mentorat en cours peut être marqué comme terminé.'], 422);
        }

        $mentorship->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json([
            'data' => $mentorship->fresh(['mentor:id,name,avatar,country', 'mentee:id,name,avatar,country']),
        ]);
    }

    // ───────────────── SESSIONS ─────────────────

    /**
     * Add a session to a mentorship.
     */
    public function addSession(Request $request, Mentorship $mentorship): JsonResponse
    {
        $user = $request->user();
        if ($mentorship->mentor_id !== $user->id && $mentorship->mentee_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($mentorship->status !== 'accepted') {
            return response()->json(['message' => 'Le mentorat doit être accepté.'], 422);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $mentorship->sessions()->create(array_merge($data, [
            'status' => 'scheduled',
        ]));

        return response()->json(['data' => $session], 201);
    }

    /**
     * Update session status (complete/cancel).
     */
    public function updateSession(Request $request, MentorshipSession $session): JsonResponse
    {
        $mentorship = $session->mentorship;
        $user = $request->user();

        if ($mentorship->mentor_id !== $user->id && $mentorship->mentee_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'in:completed,cancelled,no_show'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'mentor_feedback' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $session->update($data);

        return response()->json(['data' => $session->fresh()]);
    }

    // ───────────────── REVIEWS ─────────────────

    /**
     * Leave a review after mentorship completion.
     */
    public function review(Request $request, Mentorship $mentorship): JsonResponse
    {
        $user = $request->user();

        // Must be a participant
        if ($mentorship->mentor_id !== $user->id && $mentorship->mentee_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($mentorship->status !== 'completed') {
            return response()->json(['message' => 'Le mentorat doit être terminé pour laisser un avis.'], 422);
        }

        // Determine who is being reviewed
        $reviewedId = $mentorship->mentor_id === $user->id
            ? $mentorship->mentee_id
            : $mentorship->mentor_id;

        // Prevent duplicate reviews
        if (MentorReview::where('mentorship_id', $mentorship->id)->where('reviewer_id', $user->id)->exists()) {
            return response()->json(['message' => 'Vous avez déjà laissé un avis pour ce mentorat.'], 409);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
        ]);

        $review = MentorReview::create([
            'mentorship_id' => $mentorship->id,
            'reviewer_id' => $user->id,
            'reviewed_id' => $reviewedId,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'tags' => $data['tags'] ?? null,
        ]);

        return response()->json(['data' => $review->load('reviewer:id,name,avatar')], 201);
    }

    // ───────────────── AVAILABILITIES ─────────────────

    /**
     * Get/set mentor availabilities.
     */
    public function myAvailabilities(Request $request): JsonResponse
    {
        $availabilities = $request->user()
            ->mentorAvailabilities()
            ->orderByRaw("FIELD(day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday')")
            ->get();

        return response()->json(['data' => $availabilities]);
    }

    public function saveAvailabilities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slots' => ['required', 'array'],
            'slots.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
            'slots.*.timezone' => ['sometimes', 'string', 'max:50'],
        ]);

        $user = $request->user();

        // Replace all existing slots
        $user->mentorAvailabilities()->delete();

        foreach ($data['slots'] as $slot) {
            $user->mentorAvailabilities()->create([
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'timezone' => $slot['timezone'] ?? 'Africa/Dakar',
                'is_active' => true,
            ]);
        }

        return response()->json([
            'data' => $user->mentorAvailabilities()->orderByRaw("FIELD(day_of_week, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday')")->get(),
        ]);
    }
}
