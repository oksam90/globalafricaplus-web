<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CountryGuide;
use App\Models\DiasporaSimulation;
use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiasporaController extends Controller
{
    /**
     * Diaspora-specific stats for the portal.
     */
    public function stats(): JsonResponse
    {
        $diasporaUsers = User::where('is_diaspora', true)->count();

        $diasporaInvestorIds = User::where('is_diaspora', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'investor'))
            ->pluck('id');

        $totalInvested = Investment::whereIn('investor_id', $diasporaInvestorIds)
            ->whereIn('status', ['escrow', 'released'])
            ->sum('amount');

        $investmentsCount = Investment::whereIn('investor_id', $diasporaInvestorIds)
            ->whereIn('status', ['escrow', 'released'])
            ->count();

        $residenceCountries = User::where('is_diaspora', true)
            ->whereNotNull('residence_country')
            ->distinct('residence_country')
            ->count('residence_country');

        $originCountries = User::where('is_diaspora', true)
            ->whereNotNull('country')
            ->distinct('country')
            ->count('country');

        // Top destination countries (where diaspora invests)
        $topDestinations = Project::published()
            ->whereHas('investments', function ($q) use ($diasporaInvestorIds) {
                $q->whereIn('investor_id', $diasporaInvestorIds)
                  ->whereIn('status', ['escrow', 'released']);
            })
            ->select('country', DB::raw('COUNT(*) as projects_count'), DB::raw('SUM(amount_raised) as total_raised'))
            ->groupBy('country')
            ->orderByDesc('total_raised')
            ->limit(10)
            ->get();

        // Top residence countries of diaspora members
        $topResidences = User::where('is_diaspora', true)
            ->whereNotNull('residence_country')
            ->select('residence_country', DB::raw('COUNT(*) as members_count'))
            ->groupBy('residence_country')
            ->orderByDesc('members_count')
            ->limit(10)
            ->get();

        // Projects by country for map data
        $projectsByCountry = Project::published()
            ->select('country', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount_raised),0) as raised'), DB::raw('COALESCE(SUM(amount_needed),0) as needed'), DB::raw('COALESCE(SUM(jobs_target),0) as jobs'))
            ->groupBy('country')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'diaspora_members' => $diasporaUsers,
            'diaspora_invested' => (float) $totalInvested,
            'diaspora_investments_count' => $investmentsCount,
            'residence_countries' => $residenceCountries,
            'origin_countries' => $originCountries,
            'top_destinations' => $topDestinations,
            'top_residences' => $topResidences,
            'projects_by_country' => $projectsByCountry,
            'total_remittances_estimate' => 100_000_000_000, // $100Md (World Bank estimate)
        ]);
    }

    /**
     * List all country guides.
     */
    public function countries(): JsonResponse
    {
        $guides = CountryGuide::orderBy('country')->get()->map(function ($g) {
            return [
                'id' => $g->id,
                'country' => $g->country,
                'country_code' => $g->country_code,
                'flag' => $g->flag,
                'currency' => $g->currency,
                'population' => $g->population,
                'gdp' => $g->gdp,
                'gdp_growth' => $g->gdp_growth,
                'remittances_gdp' => $g->remittances_gdp,
                'ease_of_business_score' => $g->ease_of_business_score,
                'key_sectors' => $g->key_sectors,
                'investment_agency' => $g->investment_agency,
                'projects_count' => $g->projectsCount(),
                'total_raised' => $g->totalRaised(),
                'jobs_target' => $g->jobsTarget(),
            ];
        });

        return response()->json(['data' => $guides]);
    }

    /**
     * Single country guide detail.
     */
    public function countryShow(string $code): JsonResponse
    {
        $guide = CountryGuide::where('country_code', strtoupper($code))->firstOrFail();

        // Top projects in this country
        $topProjects = Project::published()
            ->with(['category:id,slug,name,color', 'user:id,name,country,avatar'])
            ->where('country', $guide->country)
            ->orderByDesc('followers_count')
            ->orderByDesc('views_count')
            ->limit(6)
            ->get();

        // Sector breakdown for this country
        $sectorBreakdown = Project::published()
            ->where('country', $guide->country)
            ->join('categories', 'projects.category_id', '=', 'categories.id')
            ->select('categories.name', 'categories.color', 'categories.slug', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(projects.amount_raised),0) as raised'))
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.slug')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'data' => $guide,
            'top_projects' => $topProjects,
            'sector_breakdown' => $sectorBreakdown,
            'stats' => [
                'projects_count' => $guide->projectsCount(),
                'total_raised' => $guide->totalRaised(),
                'jobs_target' => $guide->jobsTarget(),
            ],
        ]);
    }

    /**
     * Investment simulator — calculates estimated impact.
     */
    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'origin_country' => ['required', 'string', 'max:100'],
            'destination_country' => ['required', 'string', 'max:100'],
            'investment_type' => ['required', 'in:equity,donation,loan,reward'],
            'duration_months' => ['required', 'integer', 'min:6', 'max:120'],
            'target_sector' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $amount = (float) $data['amount'];
        $type = $data['investment_type'];
        $months = (int) $data['duration_months'];

        // --- Impact estimation model ---

        // Average jobs created per €10k invested (varies by type and sector)
        $jobsPerTenK = match ($type) {
            'equity' => 2.5,
            'loan' => 1.8,
            'reward' => 1.2,
            'donation' => 3.0,
        };

        // Sector multiplier
        $sectorMultiplier = match (strtolower($data['target_sector'] ?? '')) {
            'agritech' => 1.8,
            'industrie' => 1.5,
            'commerce-retail', 'commerce & retail' => 1.4,
            'tourisme-culture', 'tourisme & culture' => 1.3,
            'energie', 'énergie' => 1.2,
            'healthtech' => 1.1,
            'edtech' => 1.0,
            'fintech' => 0.8,
            default => 1.2,
        };

        $estimatedJobs = round(($amount / 10_000) * $jobsPerTenK * $sectorMultiplier, 1);

        // Estimated financial return (annualized, rough model)
        $annualReturn = match ($type) {
            'equity' => 0.12,     // 12% p.a. average for early-stage Africa
            'loan' => 0.08,       // 8% interest
            'reward' => 0.0,      // no financial return
            'donation' => 0.0,    // no financial return
        };
        $years = $months / 12;
        $estimatedReturn = round($amount * pow(1 + $annualReturn, $years) - $amount, 2);

        // SDG impact score (1-100)
        $sdgScore = min(100, round(($amount / 5000) * 10 * $sectorMultiplier));

        // Transfer cost estimation (% of amount, World Bank averages for Africa corridors)
        $transferCostPct = match (true) {
            $amount >= 200_000 => 1.2,
            $amount >= 50_000 => 2.5,
            $amount >= 10_000 => 4.0,
            default => 6.5,
        };
        $transferCost = round($amount * ($transferCostPct / 100), 2);

        // People impacted (multiplier effect: 1 job = ~5 dependents in Africa)
        $peopleImpacted = (int) round($estimatedJobs * 5.2);

        // Save simulation for analytics (optional)
        $simulation = null;
        if ($request->user()) {
            $simulation = DiasporaSimulation::create([
                'user_id' => $request->user()->id,
                'origin_country' => $data['origin_country'],
                'destination_country' => $data['destination_country'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'EUR',
                'investment_type' => $type,
                'duration_months' => $months,
                'estimated_return' => $estimatedReturn,
                'estimated_jobs' => $estimatedJobs,
                'target_sector' => $data['target_sector'] ?? null,
            ]);
        }

        return response()->json([
            'input' => [
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'EUR',
                'type' => $type,
                'duration_months' => $months,
                'origin' => $data['origin_country'],
                'destination' => $data['destination_country'],
                'sector' => $data['target_sector'] ?? null,
            ],
            'impact' => [
                'estimated_jobs' => $estimatedJobs,
                'estimated_return' => $estimatedReturn,
                'annual_return_pct' => $annualReturn * 100,
                'sdg_score' => $sdgScore,
                'people_impacted' => $peopleImpacted,
                'transfer_cost_pct' => $transferCostPct,
                'transfer_cost' => $transferCost,
                'net_investment' => round($amount - $transferCost, 2),
            ],
            'simulation_id' => $simulation?->id,
        ]);
    }

    /**
     * Projects filterable for diaspora investors (published, sorted by impact).
     */
    public function projects(Request $request): JsonResponse
    {
        $query = Project::published()
            ->with(['category:id,slug,name,color', 'user:id,name,country,avatar', 'sdgs:id,number,name,color'])
            ->withCount('followers');

        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        if ($sector = $request->input('sector')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $sector));
        }

        // Sort by "diaspora relevance" — high funding need + high job impact + follower momentum
        $query->orderByRaw('(jobs_target * 2 + followers_count + (CASE WHEN amount_needed > 0 THEN (1 - amount_raised/amount_needed) * 50 ELSE 0 END)) DESC');

        return response()->json($query->paginate(12));
    }
}
