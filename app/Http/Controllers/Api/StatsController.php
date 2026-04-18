<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'projects_count' => Project::where('status', 'published')->count(),
            'users_count' => User::count(),
            'countries_count' => Project::where('status', 'published')->distinct('country')->count('country'),
            'amount_raised' => (float) Project::where('status', 'published')->sum('amount_raised'),
            'jobs_target' => (int) Project::where('status', 'published')->sum('jobs_target'),
        ]);
    }
}
