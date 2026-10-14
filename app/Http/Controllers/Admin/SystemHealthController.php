<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Admin-only system health page and JSON snapshot for ISCVMS operations.
 */
class SystemHealthController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings', ['section' => 'health']);
    }

    public function status(SystemHealthService $health): JsonResponse
    {
        return response()->json($health->snapshot());
    }
}
