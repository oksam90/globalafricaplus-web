<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sdg;
use Illuminate\Http\JsonResponse;

class SdgController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Sdg::orderBy('number')->get()]);
    }
}
