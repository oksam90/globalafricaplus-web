<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallApplication;
use App\Models\EconomicZone;
use App\Models\GovernmentCall;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GovernmentController extends Controller
{
    // ═══════════════════════════════════════════════════
    //  PUBLIC — Portail Gouvernement (visible par tous)
    // ═══════════════════════════════════════════════════

    /**
     * Stats globales du module gouvernement.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'open_calls' => GovernmentCall::where('status', 'open')->count(),
            'total_calls' => GovernmentCall::public()->count(),
            'total_budget' => (float) GovernmentCall::where('status', 'open')->sum('budget'),
            'total_applications' => CallApplication::count(),
            'countries_count' => GovernmentCall::public()->distinct('country')->count('country'),
            'zones_count' => EconomicZone::where('status', 'active')->count(),
        ]);
    }

    /**
     * Liste publique des appels à projets (ouverts/fermés/attribués).
     */
    public function calls(Request $request): JsonResponse
    {
        $query = GovernmentCall::public()
            ->with('author:id,name,country,avatar');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhere('sector', 'like', "%$search%");
            });
        }

        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }
        if ($sector = $request->input('sector')) {
            $query->where('sector', $sector);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = $request->input('sort', 'recent');
        $query = match ($sort) {
            'deadline' => $query->orderBy('closes_at'),
            'budget' => $query->orderByDesc('budget'),
            'applications' => $query->orderByDesc('applications_count'),
            default => $query->orderByDesc('published_at'),
        };

        return response()->json($query->paginate(12));
    }

    /**
     * Détail d'un appel public.
     */
    public function callShow(string $slug): JsonResponse
    {
        $call = GovernmentCall::public()
            ->with('author:id,name,country,avatar')
            ->withCount('applications')
            ->where('slug', $slug)
            ->firstOrFail();

        $call->increment('views_count');

        // Other open calls from same country
        $related = GovernmentCall::where('status', 'open')
            ->where('id', '!=', $call->id)
            ->where('country', $call->country)
            ->limit(4)
            ->get(['id', 'title', 'slug', 'sector', 'budget', 'currency', 'closes_at']);

        return response()->json([
            'data' => $call,
            'related' => $related,
        ]);
    }

    /**
     * Liste publique des ZES.
     */
    public function zones(Request $request): JsonResponse
    {
        $query = EconomicZone::with('author:id,name,country');

        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }
        if ($sector = $request->input('sector')) {
            $query->whereJsonContains('sectors', $sector);
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    // ═══════════════════════════════════════════════════
    //  AUTHENTICATED — Gestion par le gouvernement
    // ═══════════════════════════════════════════════════

    /**
     * Mes appels à projets (gouvernement connecté).
     */
    public function myCalls(Request $request): JsonResponse
    {
        $calls = GovernmentCall::where('user_id', $request->user()->id)
            ->withCount('applications')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($calls);
    }

    /**
     * Créer un nouvel appel.
     */
    public function storeCall(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'country' => ['required', 'string', 'max:80'],
            'geographic_zone' => ['nullable', 'string', 'max:150'],
            'sector' => ['nullable', 'string', 'max:100'],
            'eligibility_criteria' => ['nullable', 'string', 'max:5000'],
            'required_documents' => ['nullable', 'string', 'max:3000'],
            'evaluation_criteria' => ['nullable', 'string', 'max:3000'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'status' => ['nullable', Rule::in(['draft', 'open'])],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['slug'] = GovernmentCall::generateUniqueSlug($data['title']);
        $data['currency'] = $data['currency'] ?? 'EUR';
        $data['status'] = $data['status'] ?? 'draft';

        if ($data['status'] === 'open') {
            $data['published_at'] = now();
        }

        $call = GovernmentCall::create($data);

        return response()->json(['data' => $call], 201);
    }

    /**
     * Modifier un appel.
     */
    public function updateCall(Request $request, int $id): JsonResponse
    {
        $call = GovernmentCall::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'country' => ['sometimes', 'string', 'max:80'],
            'geographic_zone' => ['nullable', 'string', 'max:150'],
            'sector' => ['nullable', 'string', 'max:100'],
            'eligibility_criteria' => ['nullable', 'string', 'max:5000'],
            'required_documents' => ['nullable', 'string', 'max:3000'],
            'evaluation_criteria' => ['nullable', 'string', 'max:3000'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
        ]);

        if (isset($data['title']) && $data['title'] !== $call->title) {
            $data['slug'] = GovernmentCall::generateUniqueSlug($data['title'], $call->id);
        }

        $call->update($data);

        return response()->json(['data' => $call->fresh()]);
    }

    /**
     * Publier (ouvrir) un appel.
     */
    public function publishCall(Request $request, int $id): JsonResponse
    {
        $call = GovernmentCall::where('user_id', $request->user()->id)->findOrFail($id);

        if (blank($call->title) || blank($call->description) || blank($call->country)) {
            return response()->json([
                'message' => 'Complétez titre, description et pays avant publication.',
            ], 422);
        }

        $call->update([
            'status' => 'open',
            'published_at' => $call->published_at ?? now(),
            'opens_at' => $call->opens_at ?? now()->toDateString(),
        ]);

        return response()->json(['data' => $call->fresh()]);
    }

    /**
     * Clôturer un appel.
     */
    public function closeCall(Request $request, int $id): JsonResponse
    {
        $call = GovernmentCall::where('user_id', $request->user()->id)->findOrFail($id);
        $call->update(['status' => 'closed']);

        return response()->json(['data' => $call->fresh()]);
    }

    /**
     * Attribuer un appel (marquer comme terminé/attribué).
     */
    public function awardCall(Request $request, int $id): JsonResponse
    {
        $call = GovernmentCall::where('user_id', $request->user()->id)->findOrFail($id);
        $call->update(['status' => 'awarded']);

        return response()->json(['data' => $call->fresh()]);
    }

    /**
     * Supprimer un appel (draft uniquement).
     */
    public function deleteCall(Request $request, int $id): JsonResponse
    {
        $call = GovernmentCall::where('user_id', $request->user()->id)->findOrFail($id);

        if ($call->status !== 'draft') {
            return response()->json(['message' => 'Seuls les brouillons peuvent être supprimés.'], 422);
        }

        $call->delete();
        return response()->json(['message' => 'Appel supprimé.']);
    }

    // ─── Applications / Candidatures ───

    /**
     * Voir les candidatures reçues pour un appel.
     */
    public function callApplications(Request $request, int $callId): JsonResponse
    {
        $call = GovernmentCall::where('user_id', $request->user()->id)->findOrFail($callId);

        $apps = CallApplication::where('call_id', $call->id)
            ->with([
                'user:id,name,email,country,avatar',
                'project:id,title,slug,status,amount_needed,amount_raised',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($apps);
    }

    /**
     * Évaluer une candidature (gouvernement).
     */
    public function reviewApplication(Request $request, int $applicationId): JsonResponse
    {
        $app = CallApplication::with('call')->findOrFail($applicationId);

        // Verify ownership
        if ($app->call->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'shortlisted', 'accepted', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $data['reviewed_at'] = now();
        $app->update($data);

        return response()->json(['data' => $app->fresh(['user:id,name', 'project:id,title,slug'])]);
    }

    // ─── Entrepreneur side: postuler à un appel ───

    /**
     * Soumettre une candidature à un appel.
     */
    public function apply(Request $request, int $callId): JsonResponse
    {
        $call = GovernmentCall::findOrFail($callId);

        if (!$call->isOpen()) {
            return response()->json(['message' => "Cet appel n'est plus ouvert."], 422);
        }

        // Check unique
        $existing = CallApplication::where('call_id', $callId)
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($existing) {
            return response()->json(['message' => 'Vous avez déjà candidaté à cet appel.'], 422);
        }

        $data = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'motivation' => ['required', 'string', 'max:5000'],
            'proposal' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['call_id'] = $callId;
        $data['user_id'] = $request->user()->id;

        $app = CallApplication::create($data);

        // Increment counter
        $call->increment('applications_count');

        return response()->json(['data' => $app], 201);
    }

    /**
     * Mes candidatures (côté entrepreneur).
     */
    public function myApplications(Request $request): JsonResponse
    {
        $apps = CallApplication::where('user_id', $request->user()->id)
            ->with([
                'call:id,title,slug,country,sector,budget,currency,closes_at,status',
                'project:id,title,slug',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($apps);
    }

    // ─── ZES management ───

    public function myZones(Request $request): JsonResponse
    {
        return response()->json(
            EconomicZone::where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get()
        );
    }

    public function storeZone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'country' => ['required', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'incentives' => ['nullable', 'array'],
            'sectors' => ['nullable', 'array'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'planned', 'closed'])],
            'website' => ['nullable', 'url', 'max:300'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['slug'] = EconomicZone::generateUniqueSlug($data['name']);

        $zone = EconomicZone::create($data);

        return response()->json(['data' => $zone], 201);
    }

    public function updateZone(Request $request, int $id): JsonResponse
    {
        $zone = EconomicZone::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'country' => ['sometimes', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'incentives' => ['nullable', 'array'],
            'sectors' => ['nullable', 'array'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'planned', 'closed'])],
            'website' => ['nullable', 'url', 'max:300'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ]);

        if (isset($data['name']) && $data['name'] !== $zone->name) {
            $data['slug'] = EconomicZone::generateUniqueSlug($data['name'], $zone->id);
        }

        $zone->update($data);

        return response()->json(['data' => $zone->fresh()]);
    }

    public function deleteZone(Request $request, int $id): JsonResponse
    {
        $zone = EconomicZone::where('user_id', $request->user()->id)->findOrFail($id);
        $zone->delete();

        return response()->json(['message' => 'Zone supprimée.']);
    }
}
