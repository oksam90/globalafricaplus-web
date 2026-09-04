<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlanTemplate;
use App\Models\FormalizationProgress;
use App\Models\FormalizationStep;
use App\Models\MicrofinancePartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Administration du Hub de formalisation.
 *
 * Couvre l'INTÉGRALITÉ du module, soit les trois référentiels :
 *   • formalization_steps      — parcours de formalisation, par pays
 *   • business_plan_templates  — modèles de business plan
 *   • microfinance_partners    — partenaires de financement
 *
 * Toutes les routes sont derrière `role:admin` + throttles admin-read /
 * admin-write (cf. routes/web.php).
 */
class AdminFormalizationController extends Controller
{
    /** Zone tampon utilisée pendant le réordonnancement (hors plage réelle). */
    private const ORDER_STAGING_OFFSET = 1000;

    /** Position maximale acceptée pour une étape. */
    private const ORDER_MAX = 500;

    // ══════════════════════════════════════════════════════════
    //  Vue d'ensemble
    // ══════════════════════════════════════════════════════════

    /**
     * Compteurs + liste des pays couverts (pour les filtres de l'UI).
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'counts' => [
                'steps'     => FormalizationStep::count(),
                'countries' => FormalizationStep::distinct()->count('country'),
                'templates' => BusinessPlanTemplate::count(),
                'partners'  => MicrofinancePartner::count(),
                'progress'  => FormalizationProgress::count(),
            ],
            'countries' => FormalizationStep::selectRaw('country, COUNT(*) as steps_count')
                ->groupBy('country')
                ->orderBy('country')
                ->get(),
            'sectors' => BusinessPlanTemplate::select('sector')->distinct()->orderBy('sector')->pluck('sector'),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  1. Étapes de formalisation (parcours par pays)
    // ══════════════════════════════════════════════════════════

    public function stepsIndex(Request $request): JsonResponse
    {
        $query = FormalizationStep::query();

        if ($country = trim((string) $request->query('country', ''))) {
            $query->where('country', $country);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('institution', 'like', "%{$search}%");
            });
        }

        $query = match ($request->query('sort')) {
            'recent' => $query->orderByDesc('created_at'),
            default  => $query->orderBy('country')->orderBy('order'),
        };

        return response()->json($query->paginate(min(100, (int) $request->query('per_page', 25))));
    }

    public function stepShow(int $id): JsonResponse
    {
        return response()->json(['data' => FormalizationStep::findOrFail($id)]);
    }

    public function stepStore(Request $request): JsonResponse
    {
        $data = $this->validateStep($request);

        // `order` est optionnel à la création : on empile en fin de parcours.
        $data['order'] ??= (int) FormalizationStep::where('country', $data['country'])->max('order') + 1;
        $data['slug']  = $this->uniqueStepSlug($data['title'], $data['country']);

        $step = FormalizationStep::create($data);

        Log::info('admin.formalization_step.created', [
            'admin_id' => $request->user()->id,
            'step_id'  => $step->id,
            'country'  => $step->country,
        ]);

        return response()->json([
            'message' => "Étape « {$step->title} » créée pour {$step->country}.",
            'data'    => $step,
        ], 201);
    }

    public function stepUpdate(Request $request, int $id): JsonResponse
    {
        $step = FormalizationStep::findOrFail($id);
        $data = $this->validateStep($request, $step);

        if (($data['title'] ?? $step->title) !== $step->title) {
            $data['slug'] = $this->uniqueStepSlug($data['title'], $data['country'] ?? $step->country, $step->id);
        }

        $step->update($data);

        Log::info('admin.formalization_step.updated', [
            'admin_id' => $request->user()->id,
            'step_id'  => $step->id,
        ]);

        return response()->json([
            'message' => "Étape « {$step->title} » mise à jour.",
            'data'    => $step->fresh(),
        ]);
    }

    /**
     * Suppression d'une étape.
     *
     * La progression des utilisateurs est supprimée en cascade (FK). On
     * renumérote ensuite le parcours du pays pour ne pas laisser de trou dans
     * la séquence (`unique(country, order)`).
     */
    public function stepDestroy(Request $request, int $id): JsonResponse
    {
        $step    = FormalizationStep::findOrFail($id);
        $country = $step->country;
        $title   = $step->title;
        $affected = FormalizationProgress::where('step_id', $step->id)->count();

        DB::transaction(function () use ($step, $country) {
            $step->delete();
            $this->resequence($country);
        });

        Log::warning('admin.formalization_step.deleted', [
            'admin_id'          => $request->user()->id,
            'country'           => $country,
            'title'             => $title,
            'progress_removed'  => $affected,
        ]);

        return response()->json([
            'message' => "Étape « {$title} » supprimée."
                . ($affected > 0 ? " {$affected} suivi(s) utilisateur associé(s) ont été retirés." : ''),
        ]);
    }

    /**
     * Réordonne le parcours d'un pays.
     *
     * Attend `ids` : la liste ordonnée des identifiants d'étapes du pays.
     * L'écriture se fait en deux temps (offset négatif puis position finale)
     * pour ne pas violer l'index unique (country, order) en cours de route.
     */
    public function stepsReorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'max:80'],
            'ids'     => ['required', 'array', 'min:1'],
            'ids.*'   => ['integer', 'exists:formalization_steps,id'],
        ]);

        $steps = FormalizationStep::where('country', $data['country'])
            ->whereIn('id', $data['ids'])
            ->pluck('id')
            ->all();

        if (count($steps) !== count($data['ids'])) {
            return response()->json([
                'message' => 'Certaines étapes n\'appartiennent pas à ce pays.',
            ], 422);
        }

        DB::transaction(function () use ($data) {
            $this->applyOrder($data['ids']);
        });

        return response()->json([
            'message' => 'Parcours réordonné.',
            'data'    => FormalizationStep::forCountry($data['country'])->get(),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  2. Modèles de business plan
    // ══════════════════════════════════════════════════════════

    public function templatesIndex(Request $request): JsonResponse
    {
        $query = BusinessPlanTemplate::query();

        if ($sector = trim((string) $request->query('sector', ''))) {
            $query->where('sector', $sector);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('sector', 'like', "%{$search}%");
            });
        }

        $query = match ($request->query('sort')) {
            'downloads' => $query->orderByDesc('downloads_count'),
            'recent'    => $query->orderByDesc('created_at'),
            default     => $query->orderBy('title'),
        };

        return response()->json($query->paginate(min(100, (int) $request->query('per_page', 25))));
    }

    public function templateShow(int $id): JsonResponse
    {
        return response()->json(['data' => BusinessPlanTemplate::findOrFail($id)]);
    }

    public function templateStore(Request $request): JsonResponse
    {
        $data = $this->validateTemplate($request);
        $data['slug'] = $this->uniqueSlug(BusinessPlanTemplate::class, $data['title']);

        $tpl = BusinessPlanTemplate::create($data);

        Log::info('admin.bp_template.created', [
            'admin_id'    => $request->user()->id,
            'template_id' => $tpl->id,
        ]);

        return response()->json([
            'message' => "Modèle « {$tpl->title} » créé.",
            'data'    => $tpl,
        ], 201);
    }

    public function templateUpdate(Request $request, int $id): JsonResponse
    {
        $tpl  = BusinessPlanTemplate::findOrFail($id);
        $data = $this->validateTemplate($request, $tpl);

        if (isset($data['title']) && $data['title'] !== $tpl->title) {
            $data['slug'] = $this->uniqueSlug(BusinessPlanTemplate::class, $data['title'], $tpl->id);
        }

        $tpl->update($data);

        Log::info('admin.bp_template.updated', [
            'admin_id'    => $request->user()->id,
            'template_id' => $tpl->id,
        ]);

        return response()->json([
            'message' => "Modèle « {$tpl->title} » mis à jour.",
            'data'    => $tpl->fresh(),
        ]);
    }

    public function templateDestroy(Request $request, int $id): JsonResponse
    {
        $tpl   = BusinessPlanTemplate::findOrFail($id);
        $title = $tpl->title;
        $tpl->delete();

        Log::warning('admin.bp_template.deleted', [
            'admin_id' => $request->user()->id,
            'title'    => $title,
        ]);

        return response()->json(['message' => "Modèle « {$title} » supprimé."]);
    }

    // ══════════════════════════════════════════════════════════
    //  3. Partenaires de microfinance
    // ══════════════════════════════════════════════════════════

    public function partnersIndex(Request $request): JsonResponse
    {
        $query = MicrofinancePartner::query();

        if ($country = trim((string) $request->query('country', ''))) {
            $query->where('country', $country);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $query = match ($request->query('sort')) {
            'recent' => $query->orderByDesc('created_at'),
            default  => $query->orderBy('country')->orderBy('name'),
        };

        return response()->json($query->paginate(min(100, (int) $request->query('per_page', 25))));
    }

    public function partnerShow(int $id): JsonResponse
    {
        return response()->json(['data' => MicrofinancePartner::findOrFail($id)]);
    }

    public function partnerStore(Request $request): JsonResponse
    {
        $data = $this->validatePartner($request);
        $data['slug'] = $this->uniqueSlug(MicrofinancePartner::class, $data['name']);

        $partner = MicrofinancePartner::create($data);

        Log::info('admin.microfinance_partner.created', [
            'admin_id'   => $request->user()->id,
            'partner_id' => $partner->id,
        ]);

        return response()->json([
            'message' => "Partenaire « {$partner->name} » créé.",
            'data'    => $partner,
        ], 201);
    }

    public function partnerUpdate(Request $request, int $id): JsonResponse
    {
        $partner = MicrofinancePartner::findOrFail($id);
        $data    = $this->validatePartner($request, $partner);

        if (isset($data['name']) && $data['name'] !== $partner->name) {
            $data['slug'] = $this->uniqueSlug(MicrofinancePartner::class, $data['name'], $partner->id);
        }

        $partner->update($data);

        Log::info('admin.microfinance_partner.updated', [
            'admin_id'   => $request->user()->id,
            'partner_id' => $partner->id,
        ]);

        return response()->json([
            'message' => "Partenaire « {$partner->name} » mis à jour.",
            'data'    => $partner->fresh(),
        ]);
    }

    public function partnerDestroy(Request $request, int $id): JsonResponse
    {
        $partner = MicrofinancePartner::findOrFail($id);
        $name    = $partner->name;
        $partner->delete();

        Log::warning('admin.microfinance_partner.deleted', [
            'admin_id' => $request->user()->id,
            'name'     => $name,
        ]);

        return response()->json(['message' => "Partenaire « {$name} » supprimé."]);
    }

    // ══════════════════════════════════════════════════════════
    //  Validation & helpers
    // ══════════════════════════════════════════════════════════

    private function validateStep(Request $request, ?FormalizationStep $step = null): array
    {
        $required = $step ? 'sometimes' : 'required';

        $rules = [
            'country'              => [$required, 'string', 'max:80'],
            'title'                => [$required, 'string', 'max:200'],
            'description'          => [$required, 'string', 'max:5000'],
            'order'                => ['nullable', 'integer', 'min:1', 'max:' . self::ORDER_MAX],
            'institution'          => ['nullable', 'string', 'max:200'],
            'required_documents'   => ['nullable', 'array', 'max:30'],
            'required_documents.*' => ['string', 'max:200'],
            'estimated_duration'   => ['nullable', 'string', 'max:100'],
            'estimated_cost'       => ['nullable', 'string', 'max:100'],
            'link'                 => ['nullable', 'url', 'max:300'],
            'tips'                 => ['nullable', 'string', 'max:2000'],
        ];

        $data = $request->validate($rules);

        // `unique(country, order)` : on rejette proprement plutôt que de laisser
        // remonter une QueryException 500.
        $country = $data['country'] ?? $step?->country;
        if (!empty($data['order']) && $country) {
            $clash = FormalizationStep::where('country', $country)
                ->where('order', $data['order'])
                ->when($step, fn ($q) => $q->where('id', '!=', $step->id))
                ->exists();

            if ($clash) {
                // ValidationException → 422 au format standard {message, errors},
                // celui que sait déjà afficher le formulaire d'administration.
                throw ValidationException::withMessages([
                    'order' => "La position {$data['order']} est déjà occupée dans le parcours « {$country} ». Utilisez les flèches de réordonnancement ou choisissez une autre position.",
                ]);
            }
        }

        return $data;
    }

    private function validateTemplate(Request $request, ?BusinessPlanTemplate $tpl = null): array
    {
        $required = $tpl ? 'sometimes' : 'required';

        return $request->validate([
            'title'             => [$required, 'string', 'max:200'],
            'sector'            => [$required, 'string', 'max:100'],
            'description'       => [$required, 'string', 'max:5000'],
            'sections'          => [$required, 'array', 'min:1', 'max:30'],
            'sections.*.title'  => ['required', 'string', 'max:200'],
            'sections.*.prompt' => ['nullable', 'string', 'max:1000'],
            'language'          => ['nullable', 'string', 'max:20'],
            'is_free'           => ['nullable', 'boolean'],
        ]);
    }

    private function validatePartner(Request $request, ?MicrofinancePartner $partner = null): array
    {
        $required = $partner ? 'sometimes' : 'required';

        return $request->validate([
            'name'          => [$required, 'string', 'max:200'],
            'country'       => [$required, 'string', 'max:80'],
            'type'          => [$required, 'string', 'max:80'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'products'      => ['nullable', 'array', 'max:20'],
            'products.*'    => ['string', 'max:120'],
            'min_amount'    => ['nullable', 'string', 'max:50'],
            'max_amount'    => ['nullable', 'string', 'max:50'],
            'interest_rate' => ['nullable', 'string', 'max:50'],
            'website'       => ['nullable', 'url', 'max:300'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'logo'          => ['nullable', 'url', 'max:300'],
            'is_active'     => ['nullable', 'boolean'],
        ]);
    }

    /** Renumérote 1..n les étapes d'un pays après suppression. */
    private function resequence(string $country): void
    {
        $this->applyOrder(
            FormalizationStep::where('country', $country)->orderBy('order')->pluck('id')->all()
        );
    }

    /**
     * Écrit les positions 1..n pour la liste d'identifiants donnée.
     *
     * L'index unique (country, order) interdit toute collision, même
     * transitoire : on passe donc d'abord par une zone tampon (1000+) hors de
     * la plage utilisée, puis on écrit les positions définitives.
     */
    private function applyOrder(array $ids): void
    {
        $offset = self::ORDER_STAGING_OFFSET;

        foreach ($ids as $i => $id) {
            FormalizationStep::where('id', $id)->update(['order' => $offset + $i]);
        }
        foreach ($ids as $i => $id) {
            FormalizationStep::where('id', $id)->update(['order' => $i + 1]);
        }
    }

    /**
     * Slug d'étape : unique par pays (deux pays peuvent avoir « Ouvrir un
     * compte bancaire »), la colonne n'étant pas contrainte globalement.
     */
    private function uniqueStepSlug(string $title, string $country, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'etape';
        $slug = $base;
        $i    = 2;

        while (FormalizationStep::where('country', $country)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /** Slug globalement unique (templates & partenaires ont un index unique). */
    private function uniqueSlug(string $model, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'element';
        $slug = $base;
        $i    = 2;

        while ($model::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
