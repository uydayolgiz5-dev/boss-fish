<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OwnerProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = OwnerProject::forUser($request->user()->id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (OwnerProject $p) => $p->toPayload(false))
            ->values();

        return response()->json(['projects' => $projects]);
    }

    public function show(Request $request, OwnerProject $project): JsonResponse
    {
        $this->assertOwned($request, $project);

        return response()->json(['project' => $project->toPayload(true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $project = OwnerProject::create([
            'user_id' => $request->user()->id,
            ...$this->mapFields($data),
        ]);

        return response()->json(['project' => $project->toPayload(true)], 201);
    }

    public function update(Request $request, OwnerProject $project): JsonResponse
    {
        $this->assertOwned($request, $project);
        $data = $this->validated($request);
        $project->update($this->mapFields($data));

        return response()->json(['project' => $project->fresh()->toPayload(true)]);
    }

    public function destroy(Request $request, OwnerProject $project): JsonResponse
    {
        $this->assertOwned($request, $project);
        $project->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:180',
            'client_name' => 'nullable|string|max:180',
            'region' => 'nullable|string|max:120',
            'area_ha' => 'nullable|numeric|min:0|max:100000',
            'recommended_method' => 'nullable|string|max:64',
            'site_score' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|in:draft,ready',
            'inputs' => 'required|array',
            'results' => 'nullable|array',
        ]);
    }

    private function mapFields(array $data): array
    {
        return [
            'title' => trim($data['title']),
            'client_name' => isset($data['client_name']) ? trim((string) $data['client_name']) : null,
            'region' => $data['region'] ?? null,
            'area_ha' => $data['area_ha'] ?? 0,
            'recommended_method' => $data['recommended_method'] ?? null,
            'site_score' => $data['site_score'] ?? null,
            'status' => $data['status'] ?? 'ready',
            'inputs' => $data['inputs'],
            'results' => $data['results'] ?? null,
        ];
    }

    private function assertOwned(Request $request, OwnerProject $project): void
    {
        if ((int) $project->user_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
