<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Admin-only system health page and JSON snapshot for ISCVMS operations.
 */
class SystemHealthController extends Controller
{
    public function index(SystemHealthService $health): View
    {
        return view('admin.system-health', [
            'snapshot' => $health->snapshot(),
        ]);
    }

    public function status(SystemHealthService $health): JsonResponse
    {
        return response()->json($health->snapshot());
    }
}
