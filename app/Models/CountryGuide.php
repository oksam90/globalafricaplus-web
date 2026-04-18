<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryGuide extends Model
{
    protected $fillable = [
        'country', 'country_code', 'flag', 'currency', 'official_language',
        'population', 'gdp', 'gdp_growth', 'remittances_gdp',
        'ease_of_business_score', 'key_sectors',
        'legal_framework', 'taxation', 'investment_incentives',
        'risks', 'opportunities', 'diaspora_programs',
        'investment_agency', 'investment_agency_url',
    ];

    protected $casts = [
        'key_sectors' => 'array',
        'gdp' => 'decimal:2',
        'gdp_growth' => 'decimal:2',
        'remittances_gdp' => 'decimal:2',
        'ease_of_business_score' => 'decimal:1',
    ];

    /**
     * Number of published projects in this country.
     */
    public function projectsCount(): int
    {
        return Project::published()->where('country', $this->country)->count();
    }

    /**
     * Total amount raised for projects in this country.
     */
    public function totalRaised(): float
    {
        return (float) Project::published()->where('country', $this->country)->sum('amount_raised');
    }

    /**
     * Total jobs targeted in this country.
     */
    public function jobsTarget(): int
    {
        return (int) Project::published()->where('country', $this->country)->sum('jobs_target');
    }
}
