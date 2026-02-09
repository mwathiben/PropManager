<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthCheckController extends Controller
{
    public function payments(Request $request, PaymentHealthService $service): JsonResponse
    {
        return response()->json($service->check($request->boolean('ping')));
    }
}
