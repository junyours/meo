<?php

namespace App\Http\Controllers;

use App\Models\Bulletin;
use App\Models\Inquiry;
use App\Models\Projects;
use App\Models\Projectfundtype;
use App\Models\Reminder;
use App\Models\StaffAssignment;
use App\Models\Techprep;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const STATUS_TO_LABEL = [
        0 => 'Ongoing',
        1 => 'Completed',
        2 => 'Delayed',
        3 => 'Not Started',
        4 => 'Suspended',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user ? $user->role : 'staff';
        $isStaff = $role === 'staff';
        $isAdminOrSuperadmin = in_array($role, ['admin', 'superadmin'], true);

        // 1. Projects Data
        $projects = Projects::with(['remarks', 'latestFundType', 'techprep'])
            ->latest()
            ->get()
            ->map(fn (Projects $project) => $this->toProjectData($project));

        // 2. Bulletins Data
        $bulletins = Bulletin::where('is_archived', false)
            ->latest()
            ->get()
            ->map(fn (Bulletin $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'category' => $b->category,
                'summary' => $b->summary,
                'isPublic' => (bool) $b->is_public,
                'isPinned' => (bool) $b->is_pinned,
                'isArchived' => (bool) $b->is_archived,
                'date' => optional($b->created_at)->toDateString(),
                'createdAt' => optional($b->created_at)->toISOString(),
            ]);

        // 3. Reminders Data
        $remindersQuery = Reminder::query();
        if ($isStaff && $user) {
            $remindersQuery->where(function ($q) use ($user) {
                $q->where('audience', 'everyone')
                  ->orWhere('user_id', $user->id);
            });
        }
        $reminders = $remindersQuery->latest()->get()->map(fn (Reminder $r) => [
            'id' => $r->id,
            'title' => $r->title,
            'description' => $r->description,
            'category' => $r->category,
            'location' => $r->location,
            'startsAt' => optional($r->starts_at)->toISOString(),
            'endsAt' => optional($r->ends_at)->toISOString(),
            'isDone' => (bool) $r->is_done,
            'audience' => $r->audience,
            'userId' => $r->user_id,
            'createdAt' => optional($r->created_at)->toISOString(),
        ]);

        // 4. Staff Assignments Data
        $assignmentsQuery = StaffAssignment::with(['user', 'project', 'assigner']);
        if ($isStaff && $user) {
            $assignmentsQuery->where('user_id', $user->id);
        }
        $assignments = $assignmentsQuery->latest()->get()->map(function (StaffAssignment $sa) {
            $convo = is_array($sa->conversation) ? $sa->conversation : (json_decode($sa->conversation, true) ?? []);
            return [
                'id' => $sa->id,
                'userId' => $sa->user_id,
                'userName' => $sa->user?->name ?? 'Unassigned',
                'userRole' => $sa->user?->role ?? '',
                'userAvatar' => $sa->user?->profile_photo_url,
                'assignerName' => $sa->assigner?->name ?? 'Admin / Assigner',
                'projectId' => $sa->project_id,
                'projectName' => $sa->project?->project_name,
                'projectLocation' => $sa->project?->location,
                'type' => $sa->type,
                'title' => $sa->title,
                'note' => $sa->note,
                'priority' => $sa->priority,
                'status' => $sa->status,
                'targetDeadline' => optional($sa->target_deadline)->toISOString(),
                'roleInProject' => $sa->role_in_project,
                'staffReply' => $sa->staff_reply,
                'staffRepliedAt' => optional($sa->staff_replied_at)->toISOString(),
                'conversation' => $convo,
                'createdAt' => optional($sa->created_at)->toISOString(),
            ];
        });

        // 5. Citizen Inquiries (For Admin, Superadmin & Staff)
        $inquiries = Inquiry::with(['acceptedBy', 'resolvedBy', 'cancelledBy'])
            ->latest()
            ->get()
            ->map(fn (Inquiry $i) => [
                'id' => $i->id,
                'tracking_token' => $i->tracking_token,
                'fullname' => $i->fullname,
                'phone' => $i->phone,
                'email' => $i->email,
                'location' => $i->location,
                'subject' => $i->subject,
                'message' => $i->message,
                'status' => $i->status,
                'admin_notes' => $i->admin_notes,
                'cancellation_reason' => $i->cancellation_reason,
                'cancelled_at' => optional($i->cancelled_at)->toISOString(),
                'photo_urls' => $i->photo_urls ?? [],
                'accepted_by_name' => $i->acceptedBy?->name,
                'resolved_by_name' => $i->resolvedBy?->name,
                'cancelled_by_name' => $i->cancelledBy?->name,
                'createdAt' => optional($i->created_at)->toISOString(),
                'created_at_relative' => $i->created_at?->diffForHumans(),
            ]);

        // 6. Security & Audit Alerts (For Superadmin)
        $systemLogs = [];
        if ($role === 'superadmin') {
            $systemLogs = \App\Models\ActivityLog::whereIn('severity', ['warning', 'danger'])
                ->latest()
                ->take(15)
                ->get()
                ->map(fn (\App\Models\ActivityLog $l) => [
                    'id' => $l->id,
                    'user_name' => $l->user_name,
                    'user_role' => $l->user_role,
                    'module' => $l->module,
                    'action' => $l->action,
                    'description' => $l->description,
                    'severity' => $l->severity,
                    'ip_address' => $l->ip_address,
                    'createdAt' => optional($l->created_at)->toISOString(),
                    'created_at_relative' => $l->created_at?->diffForHumans(),
                ]);
        }

        return Inertia::render('Notificationspage', [
            'initialProjects' => $projects,
            'initialBulletins' => $bulletins,
            'initialReminders' => $reminders,
            'initialAssignments' => $assignments,
            'initialInquiries' => $inquiries,
            'initialSystemLogs' => $systemLogs,
            'users' => User::all(['id', 'name', 'email', 'role', 'profile_photo_path']),
        ]);
    }

    private function toProjectData(Projects $project): array
    {
        $latestRemark = $project->remarks?->sortByDesc('created_at')->first();
        $fundType = $project->latestFundType;

        return [
            'id' => $project->id,
            'title' => $project->project_name,
            'location' => $project->location,
            'totalCost' => (float) $project->total_project_cost,
            'description' => $project->project_description,
            'sourceOfFund' => $project->source_of_fund,
            'fundCategory' => $fundType ? Projectfundtype::categoryForFundType($fundType->fund_type) : null,
            'fundCategoryLabel' => $fundType?->fund_type,
            'year' => (int) $project->year,
            'duration' => $project->project_duration,
            'startDate' => optional($project->start_date)->format('Y-m-d'),
            'targetCompletionDate' => optional($project->target_completion_date)->format('Y-m-d'),
            'actualCompletionDate' => optional($project->actual_completion_date)->format('Y-m-d'),
            'revisedCompletionDate' => optional($project->revised_completion_date)->format('Y-m-d'),
            'timeExtension' => $project->time_extention,
            'daysSuspensionOrder' => $project->days_suspension_order,
            'accomplishment' => (float) $project->percentage_of_accomplishment,
            'contractor' => $project->contractor,
            'remarks' => $latestRemark?->remark ?? '',
            'status' => self::STATUS_TO_LABEL[(int) $project->status] ?? 'Ongoing',
            'createdAt' => optional($project->created_at)->toISOString(),
            'technical_preparations' => $project->relationLoaded('techprep') && $project->techprep
                ? $this->toTechnicalPreparationsData($project->techprep)
                : null,
        ];
    }

    private function toTechnicalPreparationsData(Techprep $techprep): array
    {
        $statusMap = [1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'na'];
        $fields = [
            'hazardAssessment' => ['status' => 'hazard_assessment_status', 'notes' => 'hazard_assessment_notes'],
            'powDed' => ['status' => 'pow_ded_status', 'notes' => 'pow_ded_notes'],
            'supplementalBudget' => ['status' => 'supplementary_budget_status', 'notes' => 'supplementary_budget_notes'],
            'alobs' => ['status' => 'alobs_status', 'notes' => 'alobs_notes'],
            'eccCnc' => ['status' => 'ecc_cnc_status', 'notes' => 'ecc_cnc_notes'],
            'technicalDocsToBac' => ['status' => 'submission_tech_docs_status', 'notes' => 'submission_tech_docs_notes'],
            'bidding' => ['status' => 'bidding_status', 'notes' => 'bidding_notes'],
            'contractNtp' => ['status' => 'contract_ntp_status', 'notes' => 'contract_ntp_notes'],
        ];

        $data = collect($fields)->mapWithKeys(fn (array $columns, string $requestField) => [
            $requestField => [
                'status' => $statusMap[(int) $techprep->{$columns['status']}] ?? '',
                'notes' => $techprep->{$columns['notes']} ?? '',
                'updatedAt' => optional($techprep->updated_at)->toISOString(),
            ],
        ])->all();

        $data['remarks'] = $techprep->remarks ?? '';
        $data['lastUpdated'] = optional($techprep->updated_at)->toISOString();

        return $data;
    }
}
