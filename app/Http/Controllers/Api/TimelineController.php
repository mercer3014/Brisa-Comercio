<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\PortalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TimelineController extends Controller
{
    public function __construct(private PortalApi $api)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(Cache::remember('api.timeline', 600, fn () => $this->api->timeline()));
    }
}
