<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowMilestone;
use App\Models\Project;
use App\Services\Payment\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 4 — Escrow milestone API.
 *
 *   GET    /api/escrow/projects/{project}/milestones    — list milestones (entrepreneur or investor)
 *   GET    /api/escrow/milestones/mine                  — milestones I (the investor) need to validate
 *   POST   /api/escrow/milestones/{milestone}/submit    — entrepreneur submits proof
 *   POST   /api/escrow/milestones/{milestone}/approve   — investor validates → triggers release job
 *   POST   /api/escrow/milestones/{milestone}/reject    — investor rejects with reason
 */
class EscrowController extends Controller
{
    public function __construct(
        protected EscrowService $escrow,
    ) {}

    public function projectMilestones(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // Authorisation: the project owner OR any investor of the project may see this list.
        $isOwner = $project->user_id === $user->id;
        $isInvestor = $project->investments()
            ->where('investor_id', $user->id)
            ->whereIn('status', ['escrow', 'released'])
            ->exists();
        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;

        if (!$isOwner && !$isInvestor && !$isAdmin) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $milestones = $project->escrowMilestones()
            ->with(['investment:id,investor_id,amount,currency,status', 'releaseTransaction:id,status,paid_at,gateway_reference'])
            ->get();

        return response()->json([
            'data' => $milestones,
            'project' => [
                'id'              => $project->id,
                'slug'            => $project->slug,
                'title'           => $project->title,
                'payout_phone'    => $isOwner || $isAdmin ? $project->payout_phone : null,
                'payout_provider' => $isOwner || $isAdmin ? $project->payout_provider : null,
            ],
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $milestones = EscrowMilestone::query()
            ->whereHas('investment', fn ($q) => $q->where('investor_id', $user->id))
            ->with(['project:id,slug,title,cover_image', 'investment:id,amount,currency,status'])
            ->whereIn('status', ['in_review', 'pending', 'approved'])
            ->orderBy('due_at')
            ->get();

        return response()->json(['data' => $milestones]);
    }

    public function submit(Request $request, EscrowMilestone $milestone): JsonResponse
    {
        $data = $request->validate([
            'evidence'           => ['required', 'array'],
            'evidence.notes'     => ['nullable', 'string', 'max:5000'],
            'evidence.urls'      => ['nullable', 'array'],
            'evidence.urls.*'    => ['url', 'max:500'],
            'evidence.files'     => ['nullable', 'array'],
        ]);

        try {
            $milestone = $this->escrow->submitMilestone($milestone, $request->user(), $data['evidence']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $milestone]);
    }

    public function approve(Request $request, EscrowMilestone $milestone): JsonResponse
    {
        try {
            $milestone = $this->escrow->approveMilestone($milestone, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data'    => $milestone,
            'message' => 'Validation enregistrée. Le décaissement est en cours.',
        ]);
    }

    public function reject(Request $request, EscrowMilestone $milestone): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $milestone = $this->escrow->rejectMilestone($milestone, $request->user(), $data['reason']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $milestone]);
    }
}
