<?php

namespace App\Http\Controllers;

use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StaffAssignment::with(['user', 'assigner', 'project'])
            ->latest('id');

        if ($request->has('user_id') && $request->input('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('project_id') && $request->input('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('type') && $request->input('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status') && $request->input('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $items = $query->get()->map(fn (StaffAssignment $item) => $this->formatData($item));

        return response()->json([
            'assignments' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:project_tb,id'],
            'type' => ['required', 'string', Rule::in(['assignment', 'note', 'deadline', 'message'])],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'role_in_project' => ['nullable', 'string', 'max:100'],
            'target_deadline' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $user = $request->user();
        $conversation = [];
        if (!empty($validated['note'])) {
            $conversation[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'sender_id' => $user?->id,
                'sender_name' => $user?->name ?? 'Admin',
                'sender_role' => $user?->role ?? 'admin',
                'sender_photo' => $user?->profile_photo_path ? (str_starts_with($user->profile_photo_path, 'http') ? $user->profile_photo_path : '/storage/' . $user->profile_photo_path) : null,
                'message' => $validated['note'],
                'created_at' => now()->format('Y-m-d H:i'),
            ];
        }

        $assignment = StaffAssignment::create([
            'user_id' => $validated['user_id'],
            'assigned_by' => $user?->id,
            'project_id' => $validated['project_id'] ?? null,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'note' => $validated['note'] ?? null,
            'conversation' => $conversation,
            'role_in_project' => $validated['role_in_project'] ?? null,
            'target_deadline' => $validated['target_deadline'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'status' => $validated['status'] ?? 'pending',
            'completed_at' => ($validated['status'] ?? '') === 'completed' ? now() : null,
        ]);

        $assignment->load(['user', 'assigner', 'project']);

        return response()->json([
            'message' => 'Staff record created successfully.',
            'assignment' => $this->formatData($assignment),
        ], 201);
    }

    public function update(Request $request, StaffAssignment $staffAssignment): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:project_tb,id'],
            'type' => ['sometimes', 'required', 'string', Rule::in(['assignment', 'note', 'deadline', 'message'])],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'role_in_project' => ['nullable', 'string', 'max:100'],
            'target_deadline' => ['nullable', 'date'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'completed' && $staffAssignment->status !== 'completed') {
                $validated['completed_at'] = now();
            } elseif ($validated['status'] !== 'completed') {
                $validated['completed_at'] = null;
            }
        }

        $staffAssignment->update($validated);
        $staffAssignment->load(['user', 'assigner', 'project']);

        return response()->json([
            'message' => 'Staff record updated successfully.',
            'assignment' => $this->formatData($staffAssignment),
        ]);
    }

    public function toggleStatus(Request $request, StaffAssignment $staffAssignment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $status = $validated['status'];
        $staffAssignment->update([
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        $staffAssignment->load(['user', 'assigner', 'project']);

        return response()->json([
            'message' => 'Status updated.',
            'assignment' => $this->formatData($staffAssignment),
        ]);
    }

    public function reply(Request $request, StaffAssignment $staffAssignment): JsonResponse
    {
        $validated = $request->validate([
            'staff_reply' => ['nullable', 'string', 'max:3000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $user = $request->user();
        $replyContent = trim($validated['staff_reply'] ?? '');

        $convo = is_array($staffAssignment->conversation) ? $staffAssignment->conversation : [];
        if (!empty($replyContent)) {
            $convo[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'sender_id' => $user?->id,
                'sender_name' => $user?->name ?? 'Staff',
                'sender_role' => $user?->role ?? 'staff',
                'sender_photo' => $user?->profile_photo_path ? (str_starts_with($user->profile_photo_path, 'http') ? $user->profile_photo_path : '/storage/' . $user->profile_photo_path) : null,
                'message' => $replyContent,
                'created_at' => now()->format('Y-m-d H:i'),
            ];
        }

        $updateData = [
            'staff_reply' => !empty($replyContent) ? $replyContent : $staffAssignment->staff_reply,
            'staff_replied_at' => !empty($replyContent) ? now() : $staffAssignment->staff_replied_at,
            'conversation' => $convo,
        ];

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] === 'completed') {
                $updateData['completed_at'] = now();
            }
        }

        $staffAssignment->update($updateData);
        $staffAssignment->load(['user', 'assigner', 'project']);

        return response()->json([
            'message' => 'Reply posted successfully.',
            'assignment' => $this->formatData($staffAssignment),
        ]);
    }

    public function sendMessage(Request $request, StaffAssignment $staffAssignment): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $user = $request->user();
        $msgText = trim($validated['message']);

        $convo = is_array($staffAssignment->conversation) ? $staffAssignment->conversation : [];

        // If conversation is empty but record had note/staff_reply, populate initial items
        if (empty($convo)) {
            if (!empty($staffAssignment->note)) {
                $convo[] = [
                    'id' => 'initial_note_' . $staffAssignment->id,
                    'sender_id' => $staffAssignment->assigned_by,
                    'sender_name' => $staffAssignment->assigner?->name ?? 'Admin',
                    'sender_role' => $staffAssignment->assigner?->role ?? 'admin',
                    'sender_photo' => $staffAssignment->assigner?->profile_photo_path ? (str_starts_with($staffAssignment->assigner->profile_photo_path, 'http') ? $staffAssignment->assigner->profile_photo_path : '/storage/' . $staffAssignment->assigner->profile_photo_path) : null,
                    'message' => $staffAssignment->note,
                    'created_at' => $staffAssignment->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
                ];
            }
            if (!empty($staffAssignment->staff_reply)) {
                $convo[] = [
                    'id' => 'initial_reply_' . $staffAssignment->id,
                    'sender_id' => $staffAssignment->user_id,
                    'sender_name' => $staffAssignment->user?->name ?? 'Staff',
                    'sender_role' => $staffAssignment->user?->role ?? 'staff',
                    'sender_photo' => $staffAssignment->user?->profile_photo_path ? (str_starts_with($staffAssignment->user->profile_photo_path, 'http') ? $staffAssignment->user->profile_photo_path : '/storage/' . $staffAssignment->user->profile_photo_path) : null,
                    'message' => $staffAssignment->staff_reply,
                    'created_at' => $staffAssignment->staff_replied_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
                ];
            }
        }

        $newMessage = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'sender_id' => $user?->id,
            'sender_name' => $user?->name ?? 'User',
            'sender_role' => $user?->role ?? 'admin',
            'sender_photo' => $user?->profile_photo_path ? (str_starts_with($user->profile_photo_path, 'http') ? $user->profile_photo_path : '/storage/' . $user->profile_photo_path) : null,
            'message' => $msgText,
            'created_at' => now()->format('Y-m-d H:i'),
        ];

        $convo[] = $newMessage;

        $updateData = [
            'conversation' => $convo,
        ];

        // If sender is the assigned staff, also update staff_reply
        if ($user && $user->id === $staffAssignment->user_id) {
            $updateData['staff_reply'] = $msgText;
            $updateData['staff_replied_at'] = now();
        }

        if (isset($validated['status']) && !empty($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] === 'completed') {
                $updateData['completed_at'] = now();
            }
        }

        $staffAssignment->update($updateData);
        $staffAssignment->load(['user', 'assigner', 'project']);

        return response()->json([
            'message' => 'Message sent successfully.',
            'assignment' => $this->formatData($staffAssignment),
        ]);
    }

    public function destroy(StaffAssignment $staffAssignment): JsonResponse
    {
        $staffAssignment->delete();

        return response()->json([
            'message' => 'Record deleted successfully.',
        ]);
    }

    private function formatData(StaffAssignment $item): array
    {
        $convo = is_array($item->conversation) ? $item->conversation : [];

        // Synthesize fallback conversation thread if none exists in DB
        if (empty($convo)) {
            if (!empty($item->note)) {
                $convo[] = [
                    'id' => 'initial_note_' . $item->id,
                    'sender_id' => $item->assigned_by,
                    'sender_name' => $item->assigner?->name ?? 'Admin',
                    'sender_role' => $item->assigner?->role ?? 'admin',
                    'sender_photo' => $item->assigner?->profile_photo_path ? (str_starts_with($item->assigner->profile_photo_path, 'http') ? $item->assigner->profile_photo_path : '/storage/' . $item->assigner->profile_photo_path) : null,
                    'message' => $item->note,
                    'created_at' => $item->created_at?->format('Y-m-d H:i') ?? '',
                ];
            }
            if (!empty($item->staff_reply)) {
                $convo[] = [
                    'id' => 'initial_reply_' . $item->id,
                    'sender_id' => $item->user_id,
                    'sender_name' => $item->user?->name ?? 'Staff',
                    'sender_role' => $item->user?->role ?? 'staff',
                    'sender_photo' => $item->user?->profile_photo_path ? (str_starts_with($item->user->profile_photo_path, 'http') ? $item->user->profile_photo_path : '/storage/' . $item->user->profile_photo_path) : null,
                    'message' => $item->staff_reply,
                    'created_at' => $item->staff_replied_at?->format('Y-m-d H:i') ?? '',
                ];
            }
        }

        return [
            'id' => $item->id,
            'userId' => $item->user_id,
            'assignedBy' => $item->assigned_by,
            'userName' => $item->user?->name,
            'userEmail' => $item->user?->email,
            'assignerName' => $item->assigner?->name,
            'projectId' => $item->project_id,
            'projectName' => $item->project?->project_name,
            'projectLocation' => $item->project?->location,
            'type' => $item->type,
            'title' => $item->title,
            'note' => $item->note,
            'staffReply' => $item->staff_reply,
            'staffRepliedAt' => $item->staff_replied_at?->format('Y-m-d H:i'),
            'conversation' => $convo,
            'roleInProject' => $item->role_in_project,
            'targetDeadline' => $item->target_deadline?->format('Y-m-d'),
            'priority' => $item->priority,
            'status' => $item->status,
            'completedAt' => $item->completed_at?->format('Y-m-d H:i'),
            'createdAt' => $item->created_at?->toISOString(),
            'updatedAt' => $item->updated_at?->toISOString(),
        ];
    }
}
