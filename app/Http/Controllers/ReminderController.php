<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $reminders = Reminder::with('user')
            ->where('audience', 'everyone')
            ->orWhere('user_id', $userId)
            ->orderBy('starts_at')
            ->get();

        return response()->json($reminders->map(fn (Reminder $reminder) => $this->data($reminder)));
    }

    public function store(Request $request): JsonResponse
    {
        $reminder = Reminder::create([
            ...$this->validated($request),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($this->data($reminder->load('user')), 201);
    }

    public function update(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless(
            $reminder->user_id === $request->user()->id || in_array($request->user()->role, ['admin', 'superadmin']),
            403
        );
        $reminder->update($this->validated($request));

        return response()->json($this->data($reminder->fresh(['user'])));
    }

    public function complete(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless(
            $reminder->user_id === $request->user()->id || 
            $reminder->audience === 'everyone' || 
            in_array($request->user()->role, ['admin', 'superadmin']),
            403
        );

        $validated = $request->validate([
            'is_done' => ['required', 'boolean'],
        ]);

        $reminder->update([
            'is_done' => $validated['is_done'],
            'completed_at' => $validated['is_done'] ? now() : null,
        ]);

        return response()->json($this->data($reminder->fresh(['user'])));
    }

    public function destroy(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless(
            $reminder->user_id === $request->user()->id || in_array($request->user()->role, ['admin', 'superadmin']),
            403
        );
        $reminder->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'audience' => ['required', 'in:personal,everyone'],
        ]);
    }

    private function data(Reminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'title' => $reminder->title,
            'category' => $reminder->category,
            'description' => $reminder->description,
            'startsAt' => $reminder->starts_at?->format('Y-m-d\TH:i'),
            'endsAt' => $reminder->ends_at?->format('Y-m-d\TH:i'),
            'location' => $reminder->location,
            'audience' => $reminder->audience,
            'isEveryone' => $reminder->audience === 'everyone',
            'creatorName' => $reminder->user?->name,
            'isDone' => $reminder->is_done,
            'completedAt' => $reminder->completed_at?->format('Y-m-d\TH:i'),
            'ownerId' => $reminder->user_id,
            'canManage' => $reminder->user_id === request()->user()?->id || in_array(request()->user()?->role, ['admin', 'superadmin']),
        ];
    }
}
