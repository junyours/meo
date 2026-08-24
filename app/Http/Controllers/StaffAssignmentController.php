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

        $assignment = StaffAssignment::create([
            'user_id' => $validated['user_id'],
            'assigned_by' => $request->user()?->id,
            'project_id' => $validated['project_id'] ?? null,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'note' => $validated['note'] ?? null,
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
            'staff_reply' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $updateData = [
            'staff_reply' => $validated['staff_reply'] ?? null,
            'staff_replied_at' => !empty($validated['staff_reply']) ? now() : null,
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

    public function destroy(StaffAssignment $staffAssignment): JsonResponse
    {
        $staffAssignment->delete();

        return response()->json([
            'message' => 'Record deleted successfully.',
        ]);
    }

    private function formatData(StaffAssignment $item): array
    {
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
