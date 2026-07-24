<?php

declare(strict_types=1);

namespace Magna\Management\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Magna\Media\MediaFolder;
use Symfony\Component\HttpFoundation\Response;

class FolderController extends ManagementController
{
    public function index(): JsonResponse
    {
        Gate::authorize('media.view');

        /** @var Collection<int, MediaFolder> $folders */
        $folders = MediaFolder::query()->orderBy('name')->get();

        return response()->json([
            'data' => $folders->map(fn (MediaFolder $f): array => $this->folderToArray($f))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('media.upload');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string'],
        ]);

        $parentId = isset($validated['parent_id']) && is_string($validated['parent_id'])
            ? $validated['parent_id']
            : null;

        $parentPath = '';
        if ($parentId !== null) {
            $parent = MediaFolder::query()->find($parentId);
            if ($parent instanceof MediaFolder) {
                $parentPath = $parent->path;
            }
        }

        $slug = preg_replace('/[^a-z0-9\-_]/i', '-', $validated['name']) ?? $validated['name'];
        $path = trim($parentPath.'/'.$slug, '/');

        $folder = MediaFolder::create([
            'name' => $validated['name'],
            'parent_id' => $parentId,
            'path' => $path,
        ]);

        return response()->json(['data' => $this->folderToArray($folder)], 201);
    }

    public function destroy(Request $request, string $folder): Response
    {
        Gate::authorize('media.delete');

        $record = $this->findOrNotFound(MediaFolder::query(), $folder, 'Folder');
        if ($record instanceof JsonResponse) {
            return $record;
        }

        $record->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function folderToArray(MediaFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
            'created_at' => $folder->created_at?->toIso8601String(),
        ];
    }
}
