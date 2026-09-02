<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Projects;
use App\Models\Inquiry;
use App\Models\Bulletin;
use App\Models\Reminder;
use App\Models\DocumentScanner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Log an action to the activity_logs table.
     */
    public static function log(
        string $module,
        string $action,
        string $description,
        string $severity = 'info',
        array $properties = [],
        ?User $user = null
    ): ?ActivityLog {
        try {
            $currentUser = $user ?: Auth::user();

            $log = ActivityLog::create([
                'user_id' => $currentUser?->id,
                'user_name' => $currentUser?->name ?: 'System / Citizen',
                'user_email' => $currentUser?->email ?: null,
                'user_role' => $currentUser?->role ?: 'system',
                'module' => strtolower($module),
                'action' => strtolower($action),
                'description' => $description,
                'severity' => in_array($severity, ['info', 'success', 'warning', 'danger']) ? $severity : 'info',
                'ip_address' => Request::ip() ?: '127.0.0.1',
                'user_agent' => Request::userAgent() ?: 'Internal System Process',
                'properties' => $properties ?: null,
            ]);

            return $log;
        } catch (\Throwable $e) {
            \Log::error('Failed to write activity log: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Synthesize and backfill initial activity logs if the table is empty.
     */
    public static function backfillHistoricalLogs(): void
    {
        if (ActivityLog::count() > 0) {
            return;
        }

        try {
            // 1. Initial System Boot
            ActivityLog::create([
                'user_name' => 'System Engine',
                'user_role' => 'system',
                'module' => 'system',
                'action' => 'initialize',
                'description' => 'Municipal Engineering Office Management System successfully initialized with database schemas.',
                'severity' => 'success',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System Bootstrapper',
                'properties' => ['environment' => app()->environment(), 'version' => '1.0.0'],
                'created_at' => now()->subDays(5),
            ]);

            // 2. Existing Users
            $users = User::all();
            foreach ($users as $u) {
                ActivityLog::create([
                    'user_id' => $u->id,
                    'user_name' => $u->name,
                    'user_email' => $u->email,
                    'user_role' => $u->role,
                    'module' => 'users',
                    'action' => 'create',
                    'description' => "Official {$u->role} account for '{$u->name}' ({$u->email}) was provisioned.",
                    'severity' => $u->role === 'superadmin' ? 'warning' : 'info',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Administrative Console',
                    'properties' => ['user_id' => $u->id, 'role' => $u->role, 'email' => $u->email],
                    'created_at' => $u->created_at ?: now()->subDays(4),
                ]);
            }

            // 3. Projects
            $projects = Projects::latest()->take(10)->get();
            foreach ($projects as $p) {
                $statusName = [0 => 'Ongoing', 1 => 'Completed', 2 => 'Delayed', 3 => 'Not Started', 4 => 'Suspended'][(int)$p->status] ?? 'Ongoing';
                ActivityLog::create([
                    'user_name' => 'Superadmin Official',
                    'user_role' => 'superadmin',
                    'module' => 'projects',
                    'action' => 'create',
                    'description' => "Infrastructure project record '{$p->project_name}' was registered under {$p->source_of_fund} (Status: {$statusName}).",
                    'severity' => 'info',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'MEO Project Management Portal',
                    'properties' => [
                        'project_id' => $p->id,
                        'project_name' => $p->project_name,
                        'location' => $p->location,
                        'budget' => (float)$p->total_project_cost,
                        'status' => $statusName,
                    ],
                    'created_at' => $p->created_at ?: now()->subDays(3),
                ]);
            }

            // 4. Inquiries
            $inquiries = Inquiry::with(['acceptedBy', 'resolvedBy', 'cancelledBy'])->latest()->take(10)->get();
            foreach ($inquiries as $inq) {
                ActivityLog::create([
                    'user_name' => $inq->fullname,
                    'user_email' => $inq->email,
                    'user_role' => 'citizen',
                    'module' => 'inquiries',
                    'action' => 'submit',
                    'description' => "Public concern #{$inq->tracking_token} submitted by {$inq->fullname} for {$inq->location}: '{$inq->subject}'",
                    'severity' => 'info',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Ask MEO Citizen Portal',
                    'properties' => [
                        'tracking_token' => $inq->tracking_token,
                        'fullname' => $inq->fullname,
                        'location' => $inq->location,
                        'subject' => $inq->subject,
                    ],
                    'created_at' => $inq->created_at ?: now()->subDays(2),
                ]);

                if ($inq->status === 'accepted' && $inq->acceptedBy) {
                    ActivityLog::create([
                        'user_id' => $inq->acceptedBy->id,
                        'user_name' => $inq->acceptedBy->name,
                        'user_email' => $inq->acceptedBy->email,
                        'user_role' => $inq->acceptedBy->role,
                        'module' => 'inquiries',
                        'action' => 'accept',
                        'description' => "Concern #{$inq->tracking_token} accepted by {$inq->acceptedBy->name} ({$inq->acceptedBy->role}) for site inspection.",
                        'severity' => 'success',
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Administrative Console',
                        'properties' => ['tracking_token' => $inq->tracking_token, 'officer_id' => $inq->acceptedBy->id],
                        'created_at' => $inq->accepted_at ?: now()->subDay(),
                    ]);
                } elseif ($inq->status === 'resolved' && $inq->resolvedBy) {
                    ActivityLog::create([
                        'user_id' => $inq->resolvedBy->id,
                        'user_name' => $inq->resolvedBy->name,
                        'user_email' => $inq->resolvedBy->email,
                        'user_role' => $inq->resolvedBy->role,
                        'module' => 'inquiries',
                        'action' => 'resolve',
                        'description' => "Concern #{$inq->tracking_token} was resolved and closed by {$inq->resolvedBy->name}.",
                        'severity' => 'success',
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Administrative Console',
                        'properties' => ['tracking_token' => $inq->tracking_token, 'notes' => $inq->admin_notes],
                        'created_at' => $inq->resolved_at ?: now()->subHours(6),
                    ]);
                } elseif ($inq->status === 'cancelled') {
                    ActivityLog::create([
                        'user_name' => $inq->cancelledBy ? $inq->cancelledBy->name : 'MEO Officer',
                        'user_role' => $inq->cancelledBy ? $inq->cancelledBy->role : 'admin',
                        'module' => 'inquiries',
                        'action' => 'cancel',
                        'description' => "Cancellation of concern #{$inq->tracking_token} confirmed. Reason: {$inq->cancellation_reason}",
                        'severity' => 'warning',
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Administrative Console',
                        'properties' => ['tracking_token' => $inq->tracking_token, 'reason' => $inq->cancellation_reason],
                        'created_at' => $inq->cancelled_at ?: now()->subHours(2),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Error backfilling historical activity logs: ' . $e->getMessage());
        }
    }
}
