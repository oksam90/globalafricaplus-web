<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlanTemplate;
use App\Models\FormalizationProgress;
use App\Models\FormalizationStep;
use App\Models\MicrofinancePartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormalizationController extends Controller
{
    // ──────────────────────────────────────────────
    // PUBLIC — Hub overview
    // ──────────────────────────────────────────────

    /**
     * Stats for the formalisation hub.
     */
    public function stats(): JsonResponse
    {
        $countries = FormalizationStep::distinct('country')->count('country');
        $steps = FormalizationStep::count();
        $templates = BusinessPlanTemplate::count();
        $partners = MicrofinancePartner::active()->count();
        $usersFormalized = FormalizationProgress::where('status', 'completed')
            ->distinct('user_id')
            ->count('user_id');

        return response()->json([
            'countries' => $countries,
            'steps' => $steps,
            'templates' => $templates,
            'partners' => $partners,
            'users_formalized' => $usersFormalized,
        ]);
    }

    /**
     * List countries with formalization steps count.
     */
    public function countries(): JsonResponse
    {
        $countries = FormalizationStep::selectRaw('country, COUNT(*) as steps_count')
            ->groupBy('country')
            ->orderBy('country')
            ->get();

        return response()->json(['data' => $countries]);
    }

    /**
     * Get all steps for a given country (public preview).
     */
    public function countrySteps(string $country): JsonResponse
    {
        $steps = FormalizationStep::forCountry($country)->get();

        if ($steps->isEmpty()) {
            return response()->json(['message' => 'Aucun parcours pour ce pays.'], 404);
        }

        return response()->json(['data' => $steps]);
    }

    /**
     * Business plan templates (RG-030: gratuit).
     */
    public function templates(Request $request): JsonResponse
    {
        $q = BusinessPlanTemplate::query();

        if ($request->filled('sector')) {
            $q->where('sector', $request->sector);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('title', 'like', $term)->orWhere('sector', 'like', $term);
            });
        }

        return response()->json([
            'data' => $q->orderByDesc('downloads_count')->get(),
        ]);
    }

    /**
     * Get a single template by slug.
     */
    public function templateShow(string $slug): JsonResponse
    {
        $tpl = BusinessPlanTemplate::where('slug', $slug)->firstOrFail();
        $tpl->increment('downloads_count');

        return response()->json(['data' => $tpl]);
    }

    /**
     * Microfinance partners, optionally filtered by country.
     */
    public function partners(Request $request): JsonResponse
    {
        $q = MicrofinancePartner::active();

        if ($request->filled('country')) {
            $q->forCountry($request->country);
        }

        return response()->json([
            'data' => $q->orderBy('country')->orderBy('name')->get(),
        ]);
    }

    // ──────────────────────────────────────────────
    // AUTHENTICATED — User progress
    // ──────────────────────────────────────────────

    /**
     * Get my formalization progress for a country.
     * Auto-detects country from user profile if not specified.
     */
    public function myProgress(Request $request): JsonResponse
    {
        $user = $request->user();
        $country = $request->get('country', $user->country);

        if (!$country) {
            return response()->json(['message' => 'Veuillez spécifier un pays.'], 422);
        }

        $steps = FormalizationStep::forCountry($country)->get();
        $progress = FormalizationProgress::where('user_id', $user->id)
            ->whereIn('step_id', $steps->pluck('id'))
            ->get()
            ->keyBy('step_id');

        $stepsWithProgress = $steps->map(function ($step) use ($progress) {
            $p = $progress->get($step->id);
            return [
                'step' => $step,
                'progress' => $p ? [
                    'id' => $p->id,
                    'status' => $p->status,
                    'notes' => $p->notes,
                    'completed_at' => $p->completed_at,
                ] : [
                    'id' => null,
                    'status' => 'not_started',
                    'notes' => null,
                    'completed_at' => null,
                ],
            ];
        });

        $completedCount = $progress->where('status', 'completed')->count();
        $totalSteps = $steps->count();

        return response()->json([
            'country' => $country,
            'steps' => $stepsWithProgress,
            'completion' => $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100) : 0,
            'completed_steps' => $completedCount,
            'total_steps' => $totalSteps,
        ]);
    }

    /**
     * Update progress for a specific step.
     */
    public function updateProgress(Request $request, int $stepId): JsonResponse
    {
        $user = $request->user();
        $step = FormalizationStep::findOrFail($stepId);

        $data = $request->validate([
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $progress = FormalizationProgress::updateOrCreate(
            ['user_id' => $user->id, 'step_id' => $stepId],
            [
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'completed_at' => $data['status'] === 'completed' ? now() : null,
            ]
        );

        return response()->json(['data' => $progress->load('step')]);
    }

    /**
     * Global formalization summary for current user.
     */
    public function mySummary(Request $request): JsonResponse
    {
        $user = $request->user();

        $allProgress = FormalizationProgress::where('user_id', $user->id)
            ->with('step:id,country,title,order')
            ->get();

        $byCountry = $allProgress->groupBy(fn ($p) => $p->step->country);
        $summary = [];

        foreach ($byCountry as $country => $items) {
            $total = FormalizationStep::where('country', $country)->count();
            $completed = $items->where('status', 'completed')->count();
            $inProgress = $items->where('status', 'in_progress')->count();

            $summary[] = [
                'country' => $country,
                'total_steps' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'completion' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ];
        }

        return response()->json(['data' => $summary]);
    }
}
