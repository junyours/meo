<?php

namespace App\Http\Controllers;

use App\Models\Bulletin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulletinController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Bulletin::latest()->get()->map(fn (Bulletin $bulletin) => $this->data($bulletin)));
    }

    public function store(Request $request): JsonResponse
    {
        $bulletin = Bulletin::create($this->validated($request));

        return response()->json($this->data($bulletin), 201);
    }

    public function update(Request $request, Bulletin $bulletin): JsonResponse
    {
        $bulletin->update($this->validated($request));

        return response()->json($this->data($bulletin->fresh()));
    }

    public function archive(Bulletin $bulletin): JsonResponse
    {
        $bulletin->update(['is_archived' => true, 'archived_at' => now()]);

        return response()->json($this->data($bulletin->fresh()));
    }

    public function restore(Bulletin $bulletin): JsonResponse
    {
        $bulletin->update(['is_archived' => false, 'archived_at' => null]);

        return response()->json($this->data($bulletin->fresh()));
    }

    public function visibility(Request $request, Bulletin $bulletin): JsonResponse
    {
        $bulletin->update(['is_public' => $request->boolean('is_public')]);

        return response()->json($this->data($bulletin->fresh()));
    }

    public function destroy(Bulletin $bulletin): JsonResponse
    {
        $bulletin->delete();

        return response()->json(['message' => 'Bulletin deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'summary' => ['required', 'string', 'max:300'],
            'is_public' => ['boolean'],
        ]);
    }

    private function data(Bulletin $bulletin): array
    {
        return [
            'id' => $bulletin->id,
            'title' => $bulletin->title,
            'category' => $bulletin->category,
            'date' => $bulletin->created_at?->format('F j, Y'),
            'summary' => $bulletin->summary,
            'isArchived' => $bulletin->is_archived,
            'isPublic' => $bulletin->is_public,
            'archivedAt' => $bulletin->archived_at?->format('F j, Y'),
        ];
    }
}
