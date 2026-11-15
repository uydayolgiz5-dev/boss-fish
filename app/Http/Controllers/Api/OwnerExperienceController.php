<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OwnerExperienceFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerExperienceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $parentId = $request->query('parent_id');
        $parentId = $parentId === null || $parentId === '' || $parentId === 'null'
            ? null
            : (int) $parentId;

        if ($parentId) {
            $this->findOwned($request, $parentId);
        }

        $folders = OwnerExperienceFolder::forUser($userId)
            ->where('parent_id', $parentId)
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OwnerExperienceFolder $folder) => $folder->toPayload(false, false))
            ->values();

        return response()->json([
            'parent' => $parentId ? $this->findOwned($request, $parentId)->toPayload(false) : null,
            'breadcrumb' => $parentId ? $this->breadcrumb($request, $parentId) : [],
            'folders' => $folders,
        ]);
    }

    public function show(Request $request, OwnerExperienceFolder $experience): JsonResponse
    {
        $folder = $this->findOwned($request, $experience->id);
        $folder->load(['children' => function ($query) {
            $query->withCount('children')->orderBy('sort_order')->orderBy('id');
        }]);

        return response()->json([
            'folder' => $folder->toPayload(true),
            'breadcrumb' => $this->breadcrumb($request, $folder->id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'parent_id' => 'nullable|integer',
            'body' => 'nullable|string|max:200000',
        ]);

        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            $this->findOwned($request, (int) $parentId);
        }

        $maxSort = OwnerExperienceFolder::forUser($request->user()->id)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        $folder = OwnerExperienceFolder::create([
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'title' => trim($data['title']),
            'body' => $data['body'] ?? '',
            'sort_order' => ((int) $maxSort) + 1,
        ]);

        return response()->json([
            'folder' => $folder->toPayload(false),
            'message' => 'Papka yaratildi.',
        ], 201);
    }

    public function update(Request $request, OwnerExperienceFolder $experience): JsonResponse
    {
        $folder = $this->findOwned($request, $experience->id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:180',
            'body' => 'sometimes|nullable|string|max:200000',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ]);

        if (array_key_exists('title', $data)) {
            $folder->title = trim($data['title']);
        }
        if (array_key_exists('body', $data)) {
            $folder->body = $data['body'] ?? '';
        }
        if (array_key_exists('sort_order', $data)) {
            $folder->sort_order = (int) $data['sort_order'];
        }
        $folder->save();

        return response()->json([
            'folder' => $folder->fresh()->toPayload(false),
            'message' => 'Saqlandi.',
        ]);
    }

    public function destroy(Request $request, OwnerExperienceFolder $experience): JsonResponse
    {
        $folder = $this->findOwned($request, $experience->id);
        $folder->delete();

        return response()->json(['message' => 'Papka o‘chirildi.']);
    }

    private function findOwned(Request $request, int $id): OwnerExperienceFolder
    {
        $folder = OwnerExperienceFolder::forUser($request->user()->id)->find($id);
        if (! $folder) {
            throw ValidationException::withMessages([
                'folder' => ['Papka topilmadi.'],
            ]);
        }

        return $folder;
    }

    private function breadcrumb(Request $request, int $folderId): array
    {
        $crumbs = [];
        $current = OwnerExperienceFolder::forUser($request->user()->id)->find($folderId);
        $guard = 0;

        while ($current && $guard < 40) {
            array_unshift($crumbs, [
                'id' => $current->id,
                'title' => $current->title,
            ]);
            $current = $current->parent_id
                ? OwnerExperienceFolder::forUser($request->user()->id)->find($current->parent_id)
                : null;
            $guard++;
        }

        return $crumbs;
    }
}
