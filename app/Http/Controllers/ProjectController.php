<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\Projectfundtype;
use App\Models\Techprep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    private const STATUS_TO_LABEL = [
        0 => 'Ongoing',
        1 => 'Completed',
        2 => 'Delayed',
        3 => 'Not Started',
        4 => 'Suspended',
    ];

    private const LABEL_TO_STATUS = [
        'Ongoing' => 0,
        'Completed' => 1,
        'Delayed' => 2,
        'Not Started' => 3,
        'Suspended' => 4,
    ];

    public function index(): Response
    {
        return $this->renderDashboard('Superadmin/Dashboard');
    }

    public function adminIndex(): Response
    {
        return $this->renderDashboard('Admin/Dashboard');
    }

    public function staffIndex(): Response
    {
        return $this->renderDashboard('Staff/Dashboard');
    }

    private function renderDashboard(string $page): Response
    {
        $stats = [
            'total_users' => \App\Models\User::count(),
            'superadmin_count' => \App\Models\User::where('role', 'superadmin')->count(),
            'admin_count' => \App\Models\User::where('role', 'admin')->count(),
            'staff_count' => \App\Models\User::where('role', 'staff')->count(),
        ];

        $inquiries = \App\Models\Inquiry::with(['acceptedBy', 'resolvedBy', 'updatedBy'])->latest()->get()->map(function (\App\Models\Inquiry $inquiry) {
            return [
                'id' => $inquiry->id,
                'tracking_token' => $inquiry->tracking_token,
                'fullname' => $inquiry->fullname,
                'phone' => $inquiry->phone,
                'email' => $inquiry->email,
                'location' => $inquiry->location,
                'subject' => $inquiry->subject,
                'message' => $inquiry->message,
                'photo_url' => $inquiry->photo_url,
                'photo_urls' => $inquiry->photo_urls,
                'status' => $inquiry->status,
                'admin_notes' => $inquiry->admin_notes,
                'accepted_at' => $inquiry->accepted_at?->format('M j, Y g:i A'),
                'accepted_by' => $inquiry->accepted_by,
                'accepted_by_user' => $inquiry->acceptedBy ? [
                    'id' => $inquiry->acceptedBy->id,
                    'name' => $inquiry->acceptedBy->name,
                    'role' => $inquiry->acceptedBy->role,
                ] : null,
                'resolved_at' => $inquiry->resolved_at?->format('M j, Y g:i A'),
                'resolved_by' => $inquiry->resolved_by,
                'resolved_by_user' => $inquiry->resolvedBy ? [
                    'id' => $inquiry->resolvedBy->id,
                    'name' => $inquiry->resolvedBy->name,
                    'role' => $inquiry->resolvedBy->role,
                ] : null,
                'updated_by' => $inquiry->updated_by,
                'updated_by_user' => $inquiry->updatedBy ? [
                    'id' => $inquiry->updatedBy->id,
                    'name' => $inquiry->updatedBy->name,
                    'role' => $inquiry->updatedBy->role,
                ] : null,
                'created_at' => $inquiry->created_at?->format('M j, Y g:i A'),
                'created_at_relative' => $inquiry->created_at?->diffForHumans(),
            ];
        });

        return Inertia::render($page, [
            'users' => \App\Models\User::all(),
            'stats' => $stats,
            'projects' => Projects::with(['remarks', 'latestFundType', 'techprep'])->latest()->get()->map(fn (Projects $project) => $this->toProjectTabData($project)),
            'inquiries' => $inquiries,
        ]);
    }

    public function editProjects($id)
    {
        return Inertia::render('Projects/EditProjects', [
            'projectId' => $id,
        ]);
    }

    public function show(Projects $project): JsonResponse
    {
        $project->load(['remarks', 'latestFundType', 'techprep']);

        return response()->json([
            'project' => $this->toProjectTabData($project),
        ]);
    }

    public function details(Projects $project): Response
    {
        $project->load(['remarks', 'fundTypes', 'latestFundType', 'techprep', 'powPrep', 'infra']);

        $documents = \App\Models\DocumentScanner::where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (\App\Models\DocumentScanner $doc) => [
                'id'                => $doc->id,
                'name'              => $doc->document_name,
                'type'              => $doc->document_type,
                'filePath'          => $doc->file_path,
                'fileSize'          => $doc->file_size,
                'pageCount'         => $doc->page_count,
                'processingStatus'  => $doc->processing_status,
                'ocrProcessed'      => $doc->ocr_processed,
                'aiClassification'  => $doc->ai_classification,
                'aiTags'            => $doc->ai_tags,
                'blockchainVerified'=> $doc->blockchain_verified,
                'isPublic'          => $doc->is_public,
                'uploadedAt'        => optional($doc->created_at)->toISOString(),
                'signedBy'          => $doc->signed_by,
                'scanLocation'      => $doc->scan_location,
            ]);

        return Inertia::render('Admin/ProjectDetails', [
            'project'   => $this->toProjectTabData($project),
            'documents' => $documents,
        ]);
    }

    public function staffDetails(Projects $project): Response
    {
        $project->load(['remarks', 'fundTypes', 'latestFundType', 'techprep', 'powPrep', 'infra']);

        $documents = \App\Models\DocumentScanner::where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (\App\Models\DocumentScanner $doc) => [
                'id'                => $doc->id,
                'name'              => $doc->document_name,
                'type'              => $doc->document_type,
                'filePath'          => $doc->file_path,
                'fileSize'          => $doc->file_size,
                'pageCount'         => $doc->page_count,
                'processingStatus'  => $doc->processing_status,
                'ocrProcessed'      => $doc->ocr_processed,
                'aiClassification'  => $doc->ai_classification,
                'aiTags'            => $doc->ai_tags,
                'blockchainVerified'=> $doc->blockchain_verified,
                'isPublic'          => $doc->is_public,
                'uploadedAt'        => optional($doc->created_at)->toISOString(),
                'signedBy'          => $doc->signed_by,
                'scanLocation'      => $doc->scan_location,
            ]);

        $assignments = \App\Models\StaffAssignment::with(['user', 'assigner'])
            ->where('project_id', $project->id)
            ->latest('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'userId' => $item->user_id,
                    'assignedBy' => $item->assigned_by,
                    'userName' => $item->user?->name,
                    'userEmail' => $item->user?->email,
                    'assignerName' => $item->assigner?->name,
                    'projectId' => $item->project_id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'note' => $item->note,
                    'staffReply' => $item->staff_reply,
                    'staffRepliedAt' => optional($item->staff_replied_at)->format('Y-m-d H:i'),
                    'roleInProject' => $item->role_in_project,
                    'targetDeadline' => optional($item->target_deadline)->format('Y-m-d'),
                    'priority' => $item->priority,
                    'status' => $item->status,
                    'completedAt' => optional($item->completed_at)->format('Y-m-d H:i'),
                    'createdAt' => optional($item->created_at)->toISOString(),
                ];
            });

        return Inertia::render('Staff/StaffProjectDetails', [
            'project'     => $this->toProjectTabData($project),
            'documents'   => $documents,
            'assignments' => $assignments,
        ]);
    }

    public function staffInfo(Projects $project): Response
    {
        $project->load(['remarks', 'fundTypes', 'latestFundType', 'techprep', 'powPrep', 'infra']);

        return Inertia::render('Staff/MyProjectInfo', [
            'project' => $this->toProjectTabData($project),
        ]);
    }

    public function fundSources(Request $request): JsonResponse
    {
        $dbSources = Projectfundtype::query()
            ->select('fund_type', 'fund_source')
            ->distinct()
            ->orderBy('fund_type')
            ->orderBy('fund_source')
            ->get()
            ->reduce(function (array $grouped, Projectfundtype $record) {
                $category = Projectfundtype::categoryForFundType($record->fund_type);

                if ($category === null) {
                    return $grouped;
                }

                if (! isset($grouped[$category])) {
                    $grouped[$category] = [];
                }

                $grouped[$category][] = $record->fund_source;

                return $grouped;
            }, []);

        if ($category = $request->query('category')) {
            $fundType = Projectfundtype::fundTypeForCategory($category);

            if (! $fundType) {
                return response()->json(['sources' => []]);
            }

            $sources = collect($dbSources[$category] ?? [])
                ->sort()
                ->values()
                ->all();

            return response()->json(['sources' => $sources]);
        }

        $sources = collect($dbSources)
            ->flatMap(fn (array $categorySources, string $categoryKey) => collect($categorySources)->map(fn (string $source) => [
                'category' => $categoryKey,
                'source' => $source,
            ]))
            ->sortBy(fn (array $item) => $item['category'].'|'.$item['source'])
            ->values()
            ->all();

        return response()->json(['sources' => $sources]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProject($request);

        $project = DB::transaction(function () use ($validated) {
            $project = Projects::create([
                'project_name' => $validated['name'],
                'location' => $validated['location'],
                'total_project_cost' => $validated['totalCost'],
                'original_cost' => $validated['originalCost'] ?? null,
                'revised_cost' => $validated['revisedCost'] ?? null,
                'project_description' => $validated['description'] ?? null,
                'source_of_fund' => $validated['sourceOfFund'],
                'year' => $validated['year'],
                'project_duration' => $validated['duration'],
                'start_date' => $validated['startDate'],
                'target_completion_date' => $validated['targetCompletionDate'],
                'actual_completion_date' => $validated['actualCompletionDate'] ?? null,
                'revised_completion_date' => $validated['revisedCompletionDate'] ?? null,
                'time_extention' => $validated['timeExtension'] ?? 0,
                'days_suspension_order' => $validated['daysSuspensionOrder'] ?? 0,
                'percentage_of_accomplishment' => $validated['accomplishment'] ?? 0,
                'contractor' => $validated['contractor'],
                'status' => self::LABEL_TO_STATUS[$validated['status']],
            ]);

            if (! empty($validated['remarks'])) {
                $project->remarks()->create([
                    'remark' => $validated['remarks'],
                ]);
            }

            $project->fundTypes()->create([
                'fund_type' => Projectfundtype::fundTypeForCategory($validated['fundCategory']),
                'fund_source' => $validated['sourceOfFund'],
            ]);

            return $project->load(['remarks', 'latestFundType']);
        });

        return response()->json([
            'message' => 'Project created successfully.',
            'project' => $this->toProjectTabData($project),
        ], 201);
    }

    public function update(Request $request, Projects $project): JsonResponse
    {
        $validated = $this->validateProject($request);

        $project = DB::transaction(function () use ($project, $validated) {
            $project->update([
                'project_name' => $validated['name'],
                'location' => $validated['location'],
                'total_project_cost' => $validated['totalCost'],
                'original_cost' => $validated['originalCost'] ?? null,
                'revised_cost' => $validated['revisedCost'] ?? null,
                'project_description' => $validated['description'] ?? null,
                'source_of_fund' => $validated['sourceOfFund'],
                'year' => $validated['year'],
                'project_duration' => $validated['duration'],
                'start_date' => $validated['startDate'],
                'target_completion_date' => $validated['targetCompletionDate'],
                'actual_completion_date' => $validated['actualCompletionDate'] ?? null,
                'revised_completion_date' => $validated['revisedCompletionDate'] ?? null,
                'time_extention' => $validated['timeExtension'] ?? 0,
                'days_suspension_order' => $validated['daysSuspensionOrder'] ?? 0,
                'percentage_of_accomplishment' => $validated['accomplishment'] ?? 0,
                'contractor' => $validated['contractor'],
                'status' => self::LABEL_TO_STATUS[$validated['status']],
            ]);

            $latestRemark = $project->remarks()->latest()->first();
            $remarkText = $validated['remarks'] ?? '';

            if ($latestRemark) {
                $latestRemark->update(['remark' => $remarkText]);
            } elseif ($remarkText !== '') {
                $project->remarks()->create(['remark' => $remarkText]);
            }

            $fundTypeData = [
                'fund_type' => Projectfundtype::fundTypeForCategory($validated['fundCategory']),
                'fund_source' => $validated['sourceOfFund'],
            ];

            $latestFundType = $project->latestFundType()->first();
            if ($latestFundType) {
                $latestFundType->update($fundTypeData);
            } else {
                $project->fundTypes()->create($fundTypeData);
            }

            return $project->load(['remarks', 'latestFundType']);
        });

        return response()->json([
            'message' => 'Project updated successfully.',
            'project' => $this->toProjectTabData($project),
        ]);
    }

    public function updateAccomplishment(Request $request, Projects $project): JsonResponse
    {
        $validated = $request->validate([
            'accomplishment' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(self::LABEL_TO_STATUS))],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $project->percentage_of_accomplishment = $validated['accomplishment'];

        if (isset($validated['status'])) {
            $project->status = self::LABEL_TO_STATUS[$validated['status']];
        } elseif ((float)$validated['accomplishment'] >= 100) {
            $project->status = 1; // Completed
        }

        $project->save();

        if (!empty($validated['remarks'])) {
            $latestRemark = $project->remarks()->latest()->first();
            if ($latestRemark) {
                $latestRemark->update(['remark' => $validated['remarks']]);
            } else {
                $project->remarks()->create(['remark' => $validated['remarks']]);
            }
        }

        $project->load(['remarks', 'latestFundType', 'techprep']);

        return response()->json([
            'message' => 'Project accomplishment updated successfully.',
            'project' => $this->toProjectTabData($project),
        ]);
    }

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'totalCost' => ['required', 'numeric', 'min:0'],
            'originalCost' => ['nullable', 'numeric', 'min:0'],
            'revisedCost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'fundCategory' => ['required', Rule::in(array_keys(Projectfundtype::CATEGORY_TO_TYPE))],
            'sourceOfFund' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'duration' => ['required', 'integer', 'min:0'],
            'startDate' => ['required', 'date'],
            'targetCompletionDate' => ['required', 'date', 'after_or_equal:startDate'],
            'actualCompletionDate' => ['nullable', 'date'],
            'revisedCompletionDate' => ['nullable', 'date'],
            'timeExtension' => ['nullable', 'integer', 'min:0'],
            'daysSuspensionOrder' => ['nullable', 'integer', 'min:0'],
            'accomplishment' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contractor' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(self::LABEL_TO_STATUS))],
        ]);
    }

    public function technicalPreparations(Request $request, Projects $project): JsonResponse
    {
        $fields = [
            'hazardAssessment' => [
                'status' => 'hazard_assessment_status',
                'notes' => 'hazard_assessment_notes',
            ],
            'powDed' => [
                'status' => 'pow_ded_status',
                'notes' => 'pow_ded_notes',
            ],
            'supplementalBudget' => [
                'status' => 'supplementary_budget_status',
                'notes' => 'supplementary_budget_notes',
            ],
            'alobs' => [
                'status' => 'alobs_status',
                'notes' => 'alobs_notes',
            ],
            'eccCnc' => [
                'status' => 'ecc_cnc_status',
                'notes' => 'ecc_cnc_notes',
            ],
            'technicalDocsToBac' => [
                'status' => 'submission_tech_docs_status',
                'notes' => 'submission_tech_docs_notes',
            ],
            'bidding' => [
                'status' => 'bidding_status',
                'notes' => 'bidding_notes',
            ],
            'contractNtp' => [
                'status' => 'contract_ntp_status',
                'notes' => 'contract_ntp_notes',
            ],
        ];
        $allowedStatuses = ['green', 'yellow', 'red', 'na', ''];
        $rules = [];
        foreach (array_keys($fields) as $field) {
            $rules[$field.'.status'] = ['nullable', 'string', Rule::in($allowedStatuses)];
            $rules[$field.'.notes'] = ['nullable', 'string', 'max:65535'];
        }
        $rules['remarks'] = ['nullable', 'string', 'max:65535'];

        $validated = $request->validate($rules);
        $statusMap = ['green' => 1, 'yellow' => 2, 'red' => 3, 'na' => 4, '' => null];
        $values = [];

        foreach ($fields as $requestField => $columns) {
            if (isset($validated[$requestField])) {
                if (array_key_exists('status', $validated[$requestField])) {
                    $status = $validated[$requestField]['status'] ?? '';
                    $values[$columns['status']] = $statusMap[$status] ?? null;
                }
                if (array_key_exists('notes', $validated[$requestField])) {
                    $values[$columns['notes']] = $validated[$requestField]['notes'] ?? null;
                }
            }
        }

        if (array_key_exists('remarks', $validated)) {
            $values['remarks'] = $validated['remarks'] ?? null;
        }

        $techprep = $project->techprep()->updateOrCreate([], $values);

        return response()->json([
            'message' => 'Technical preparation statuses updated successfully.',
            'technical_preparations' => $this->toTechnicalPreparationsData($techprep),
        ]);
    }

    private function toProjectTabData(Projects $project): array
    {
        $latestRemark = $project->remarks
            ->sortByDesc('created_at')
            ->first();
        $fundType = $project->latestFundType;

        return [
            'id' => $project->id,
            'name' => $project->project_name,
            'location' => $project->location,
            'totalCost' => (float) $project->total_project_cost,
            'originalCost' => $project->original_cost ? (float) $project->original_cost : null,
            'revisedCost' => $project->revised_cost ? (float) $project->revised_cost : null,
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
            'priority' => 'Medium',
            'category' => 'Infrastructure',
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
            'hazardAssessment' => [
                'status' => 'hazard_assessment_status',
                'notes' => 'hazard_assessment_notes',
            ],
            'powDed' => [
                'status' => 'pow_ded_status',
                'notes' => 'pow_ded_notes',
            ],
            'supplementalBudget' => [
                'status' => 'supplementary_budget_status',
                'notes' => 'supplementary_budget_notes',
            ],
            'alobs' => [
                'status' => 'alobs_status',
                'notes' => 'alobs_notes',
            ],
            'eccCnc' => [
                'status' => 'ecc_cnc_status',
                'notes' => 'ecc_cnc_notes',
            ],
            'technicalDocsToBac' => [
                'status' => 'submission_tech_docs_status',
                'notes' => 'submission_tech_docs_notes',
            ],
            'bidding' => [
                'status' => 'bidding_status',
                'notes' => 'bidding_notes',
            ],
            'contractNtp' => [
                'status' => 'contract_ntp_status',
                'notes' => 'contract_ntp_notes',
            ],
        ];

        $data = collect($fields)->mapWithKeys(fn (array $columns, string $requestField) => [
            $requestField => [
                'status' => $statusMap[(int) $techprep->{$columns['status']}] ?? '',
                'notes' => $techprep->{$columns['notes']} ?? '',
                'updatedAt' => optional($techprep->updated_at)->toISOString(),
                'updatedBy' => '',
            ],
        ])->all();

        $data['remarks'] = $techprep->remarks ?? '';
        $data['lastUpdated'] = optional($techprep->updated_at)->toISOString();

        return $data;
    }
}
