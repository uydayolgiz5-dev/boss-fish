<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VengerClient;
use App\Models\VengerResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VengerCabinetController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $clients = VengerClient::query()->where('created_by', $user->id);
        $results = VengerResult::query()->where('created_by', $user->id);

        return response()->json([
            'user' => $user->only(['id', 'name', 'login', 'email', 'role']),
            'stats' => [
                'clients_count' => (clone $clients)->count(),
                'results_count' => (clone $results)->count(),
            ],
        ]);
    }
}
