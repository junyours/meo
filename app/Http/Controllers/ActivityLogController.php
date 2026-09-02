<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity logs with stats and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Auto-seed historical logs if empty
        ActivityLogger::backfillHistoricalLogs();

        $query = ActivityLog::query();

        // Search Filter
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        // Module Filter
        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->where('module', strtolower($request->input('module')));
        }

        // Severity Filter
        if ($request->filled('severity') && $request->input('severity') !== 'all') {
            $query->where('severity', strtolower($request->input('severity')));
        }

        // Role Filter
        if ($request->filled('role') && $request->input('role') !== 'all') {
            $query->where('user_role', strtolower($request->input('role')));
        }

        // Date Range Filter
        if ($request->filled('date_range')) {
            $range = $request->input('date_range');
            if ($range === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($range === 'week') {
                $query->where('created_at', '>=', now()->subDays(7));
            } elseif ($range === 'month') {
                $query->where('created_at', '>=', now()->subDays(30));
            }
        }

        // Statistics Summary
        $stats = [
            'total_logs' => ActivityLog::count(),
            'today_logs' => ActivityLog::whereDate('created_at', today())->count(),
            'alerts_count' => ActivityLog::whereIn('severity', ['warning', 'danger'])->count(),
            'unique_users' => ActivityLog::whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'by_module' => ActivityLog::selectRaw('module, count(*) as count')
                ->groupBy('module')
                ->pluck('count', 'module')
                ->toArray(),
        ];

        // Sorting
        $sortBy = $request->input('sort_by', 'newest');
        if ($sortBy === 'oldest') {
            $query->orderBy('id', 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min(max((int)$request->input('per_page', 15), 5), 100);
        $logs = $query->paginate($perPage);

        // Transform collection for clean frontend consumption
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name ?: 'System',
                'user_email' => $log->user_email,
                'user_role' => $log->user_role ?: 'system',
                'module' => $log->module,
                'action' => $log->action,
                'description' => $log->description,
                'severity' => $log->severity,
                'ip_address' => $log->ip_address ?: '127.0.0.1',
                'user_agent' => $log->user_agent,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->format('M d, Y • h:i:s A'),
                'created_at_relative' => $log->created_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'logs' => $logs,
            'stats' => $stats,
        ]);
    }

    /**
     * Export activity logs to CSV format for audit compliance.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $fileName = 'meo_activity_logs_' . date('Y-m-d_His') . '.csv';

        $query = ActivityLog::orderBy('id', 'desc');

        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->where('module', strtolower($request->input('module')));
        }
        if ($request->filled('severity') && $request->input('severity') !== 'all') {
            $query->where('severity', strtolower($request->input('severity')));
        }

        ActivityLogger::log('system', 'export', 'Superadmin exported system audit activity logs to CSV.', 'info', [
            'file_name' => $fileName,
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Log ID',
                'Timestamp (PST)',
                'User Name',
                'User Email',
                'Role',
                'Module',
                'Action',
                'Severity',
                'Description',
                'IP Address',
                'Metadata JSON',
            ]);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->user_name ?: 'System',
                        $log->user_email ?: 'N/A',
                        strtoupper($log->user_role ?: 'SYSTEM'),
                        strtoupper($log->module),
                        strtoupper($log->action),
                        strtoupper($log->severity),
                        $log->description,
                        $log->ip_address,
                        $log->properties ? json_encode($log->properties, JSON_UNESCAPED_SLASHES) : '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Clear / Prune activity logs (Superadmin only).
     */
    public function clear(Request $request): JsonResponse
    {
        $days = (int)$request->input('older_than_days', 0);

        if ($days > 0) {
            $deletedCount = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
            $msg = "Pruned {$deletedCount} activity logs older than {$days} days.";
        } else {
            $deletedCount = ActivityLog::count();
            ActivityLog::truncate();
            $msg = "All {$deletedCount} historical activity logs were purged.";
        }

        // Record the prune event as a fresh log
        ActivityLogger::log('system', 'delete', "Superadmin performed log purge: {$msg}", 'danger', [
            'deleted_count' => $deletedCount,
            'retention_days' => $days,
        ]);

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }
}
