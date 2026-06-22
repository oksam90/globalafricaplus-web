<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'sub_category_id',
        'title', 'slug', 'summary', 'description',
        'country', 'city', 'amount_needed', 'amount_raised', 'currency',
        'payout_account_holder', 'payout_bank_name', 'payout_iban', 'payout_bic', 'payout_bank_country',
        'stage', 'status', 'jobs_target', 'views_count', 'followers_count',
        'cover_image', 'gallery', 'website', 'video_url', 'pitch_deck_url',
        'stage_details',
        'tags', 'deadline', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'gallery' => 'array',
        'stage_details' => 'array',
        'deadline' => 'date',
        'published_at' => 'datetime',
        'amount_needed' => 'decimal:2',
        'amount_raised' => 'decimal:2',
    ];

    protected $appends = ['progress_percent', 'trust_badges'];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = self::generateUniqueSlug($project->title);
            }
        });

        static::updating(function (Project $project) {
            // Auto-set published_at on first publish
            if ($project->isDirty('status') && $project->status === 'published' && !$project->published_at) {
                $project->published_at = now();
            }
        });
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'projet';
        $slug = $base;
        $i = 1;
        $query = static::where('slug', $slug);
        if ($ignoreId) $query->where('id', '!=', $ignoreId);

        while ($query->exists()) {
            $slug = $base.'-'.$i++;
            $query = static::where('slug', $slug);
            if ($ignoreId) $query->where('id', '!=', $ignoreId);
        }
        return $slug;
    }

    // ---------- Relations ----------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function escrowMilestones(): HasMany
    {
        return $this->hasMany(EscrowMilestone::class)->orderBy('position');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function sdgs(): BelongsToMany
    {
        return $this->belongsToMany(Sdg::class, 'project_sdg')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_followers')->withTimestamps();
    }

    // ---------- Scopes ----------

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeForCategory(Builder $q, ?string $slug): Builder
    {
        if (!$slug) return $q;
        return $q->whereHas('category', fn ($c) => $c->where('slug', $slug));
    }

    public function scopeForSubCategory(Builder $q, ?string $slug): Builder
    {
        if (!$slug) return $q;
        return $q->whereHas('subCategory', fn ($c) => $c->where('slug', $slug));
    }

    public function scopeForSdg(Builder $q, int|string|null $number): Builder
    {
        if ($number === null || $number === '') return $q;
        return $q->whereHas('sdgs', fn ($s) => $s->where('number', (int) $number));
    }

    public function scopeSort(Builder $q, ?string $sort): Builder
    {
        return match ($sort) {
            'popular'     => $q->orderByDesc('views_count')->orderByDesc('followers_count'),
            'trending'    => $q->orderByDesc('followers_count')->orderByDesc('published_at'),
            'ending'      => $q->whereNotNull('deadline')->orderBy('deadline'),
            'progress'    => $q->orderByRaw('(CASE WHEN amount_needed > 0 THEN amount_raised / amount_needed ELSE 0 END) DESC'),
            'jobs'        => $q->orderByDesc('jobs_target'),
            default       => $q->latest('published_at')->latest('id'),
        };
    }

    // ---------- Accessors ----------

    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->amount_needed <= 0) {
            return 0;
        }
        return round(min(100, ($this->amount_raised / $this->amount_needed) * 100), 1);
    }

    /**
     * Badges de confiance affichés sur la card / fiche projet.
     *
     *  - legal_formalization : l'entrepreneur a renseigné dans son profil
     *    entrepreneur le statut juridique, le n° RCCM (registration_number)
     *    et le n° fiscal (tax_id). Données partagées entre tous ses projets.
     *  - bank_account : le projet a un compte de réception bancaire complet
     *    pour le décaissement automatique des jalons.
     *  - stage_info : toutes les « Informations à fournir » du stade du projet
     *    sont renseignées (config/project_stages.php → info_required).
     *  - stage_docs : tous les « Documents requis » du stade sont renseignés —
     *    pitch deck (pitch_deck_url) inclus (config → docs_required).
     *
     * `stage` est renvoyé pour que le front puisse libeller les deux badges
     * de stade (« … — stade « Idée » »).
     *
     * Si la relation `user.roleProfiles.role` n'est pas chargée, le badge
     * juridique est marqué `null` (indéterminé) plutôt que `false`, pour
     * éviter un faux négatif sur les endpoints qui n'eager-loadent pas.
     */
    public function getTrustBadgesAttribute(): array
    {
        $legal = null;
        if ($this->relationLoaded('user') && $this->user?->relationLoaded('roleProfiles')) {
            $entrepreneurProfile = $this->user->roleProfiles
                ->first(fn ($p) => $p->relationLoaded('role') && $p->role?->slug === 'entrepreneur');
            $data = $entrepreneurProfile?->data ?? [];
            $legal = !empty($data['legal_status'])
                  && !empty($data['registration_number'])
                  && !empty($data['tax_id']);
        }

        $bank = !empty($this->payout_account_holder)
            && !empty($this->payout_bank_name)
            && !empty($this->payout_iban)
            && !empty($this->payout_bic)
            && !empty($this->payout_bank_country);

        // ── Badges propres au stade du projet ──
        $stage   = $this->stage ?: 'idea';
        $stageDef = config("project_stages.$stage", config('project_stages.idea', []));
        $details = $this->stage_details ?? [];

        $allFilled = static fn (array $keys): bool => collect($keys)
            ->every(fn ($k) => filled($details[$k] ?? null));

        $infoRequired = $stageDef['info_required'] ?? [];
        $docsRequired = $stageDef['docs_required'] ?? [];

        $stageInfo = !empty($infoRequired) && $allFilled($infoRequired);
        // Le pitch deck est toujours requis pour les documents.
        $stageDocs = filled($this->pitch_deck_url) && $allFilled($docsRequired);

        return [
            'legal_formalization' => $legal,
            'bank_account'        => $bank,
            'stage'               => $stage,
            'stage_info'          => $stageInfo,
            'stage_docs'          => $stageDocs,
        ];
    }
}
