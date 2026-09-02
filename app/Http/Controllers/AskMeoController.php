<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AskMeoController extends Controller
{
    /**
     * Display the Ask MEO public page.
     */
    public function index(Request $request)
    {
        $activeInquiry = null;
        $token = $request->query('token') ?: $request->session()->get('active_inquiry_token');

        if ($token) {
            $inquiry = Inquiry::where('tracking_token', $token)->first();
            if ($inquiry) {
                // If the concern has already been resolved, direct to ResolveConcern page
                if ($inquiry->status === 'resolved' && ! $request->has('new')) {
                    return redirect()->route('ask.meo.resolved', ['token' => $inquiry->tracking_token]);
                }
                $activeInquiry = $this->transformInquiry($inquiry);
            }
        }

        return Inertia::render('AskMEO', [
            'activeInquiry' => $activeInquiry,
        ]);
    }

    /**
     * Dedicated page for resolved concerns.
     */
    public function resolvedConcern(Request $request, ?string $token = null)
    {
        $trackingToken = $token ?: $request->query('token') ?: $request->session()->get('active_inquiry_token');

        if (! $trackingToken) {
            return redirect()->route('ask.meo');
        }

        $inquiry = Inquiry::where('tracking_token', $trackingToken)->first();

        if (! $inquiry) {
            return redirect()->route('ask.meo');
        }

        return Inertia::render('ResolveConcern', [
            'inquiry' => $this->transformInquiry($inquiry),
        ]);
    }

    /**
     * Store a newly submitted concern from the public (supporting up to 5 photos).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'], // max 5MB each
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
        ], [
            'fullname.required' => 'Please provide your full name.',
            'phone.required' => 'Contact number is required so we can follow up with you.',
            'location.required' => 'Please enter the specific location / barangay of your concern.',
            'message.required' => 'Please describe your concern or inquiry.',
            'photos.max' => 'You can upload a maximum of 5 pictures.',
            'photos.*.max' => 'Each attached photo must not exceed 5MB.',
        ]);

        $uploadedPaths = [];

        // Handle multiple photos (up to 5)
        if ($request->hasFile('photos')) {
            $files = array_slice($request->file('photos'), 0, 5);
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $uploadedPaths[] = $file->store('inquiries', 'public');
                }
            }
        } elseif ($request->hasFile('photo')) {
            $uploadedPaths[] = $request->file('photo')->store('inquiries', 'public');
        }

        // Generate unique tracking token: e.g. MEO-20260824-A1B2
        $trackingToken = 'MEO-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        while (Inquiry::where('tracking_token', $trackingToken)->exists()) {
            $trackingToken = 'MEO-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        }

        $inquiry = Inquiry::create([
            'tracking_token' => $trackingToken,
            'fullname' => $validated['fullname'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'location' => $validated['location'],
            'subject' => $validated['subject'] ?? 'Public Concern / Inquiry',
            'message' => $validated['message'],
            'photo_path' => $uploadedPaths[0] ?? null,
            'photos' => $uploadedPaths,
            'status' => 'pending',
        ]);

        \App\Services\ActivityLogger::log(
            'inquiries',
            'create',
            "New citizen concern #{$trackingToken} submitted by {$inquiry->fullname} ({$inquiry->location}): '{$inquiry->subject}'",
            'info',
            ['tracking_token' => $trackingToken, 'location' => $inquiry->location]
        );

        $request->session()->put('active_inquiry_token', $trackingToken);

        return redirect()->route('ask.meo', ['token' => $trackingToken])
            ->with('success', "Your concern has been submitted successfully with reference #{$trackingToken}.")
            ->with('submittedInquiry', $this->transformInquiry($inquiry));
    }

    /**
     * Look up inquiry status by phone number or reference tracking token.
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('query', ''));

        if (! $query) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter your Reference Code or Contact Number.',
            ], 422);
        }

        // Search by tracking token or phone number
        $inquiries = Inquiry::with(['acceptedBy', 'resolvedBy', 'cancelledBy', 'updatedBy'])
            ->where('tracking_token', $query)
            ->orWhere('phone', $query)
            ->latest()
            ->get();

        if ($inquiries->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No concern inquiry found matching that Reference Code or Contact Number.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'count' => $inquiries->count(),
            'inquiries' => $inquiries->map(fn (Inquiry $i) => $this->transformInquiry($i)),
        ]);
    }

    /**
     * Citizen requests cancellation of their submitted concern.
     */
    public function cancelConcern(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_token' => ['required', 'string'],
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $inquiry = Inquiry::where('tracking_token', $validated['tracking_token'])->first();

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'message' => 'Concern record not found with that tracking reference.',
            ], 404);
        }

        if ($inquiry->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This concern has already been cancelled.',
            ], 422);
        }

        if ($inquiry->status === 'resolved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot request cancellation for an already resolved concern.',
            ], 422);
        }

        $reason = $validated['cancellation_reason'] ?: 'Cancellation requested by citizen';

        $inquiry->update([
            'status' => 'cancel_requested',
            'cancellation_reason' => $reason,
        ]);

        \App\Services\ActivityLogger::log(
            'inquiries',
            'cancel_requested',
            "Citizen {$inquiry->fullname} submitted cancellation request for concern #{$inquiry->tracking_token}. Reason: {$reason}",
            'warning',
            ['tracking_token' => $inquiry->tracking_token, 'reason' => $reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Your cancellation request has been submitted and is awaiting confirmation by MEO staff/admin.',
            'inquiry' => $this->transformInquiry($inquiry->fresh(['acceptedBy', 'resolvedBy', 'cancelledBy', 'updatedBy'])),
        ]);
    }

    /**
     * Citizen withdraws their cancellation request (reverts to active status).
     */
    public function withdrawCancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_token' => ['required', 'string'],
        ]);

        $inquiry = Inquiry::where('tracking_token', $validated['tracking_token'])->first();

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'message' => 'Concern record not found with that tracking reference.',
            ], 404);
        }

        if ($inquiry->status !== 'cancel_requested') {
            return response()->json([
                'success' => false,
                'message' => 'This concern does not have a pending cancellation request.',
            ], 422);
        }

        $revertStatus = $inquiry->accepted_by ? 'accepted' : 'pending';

        $inquiry->update([
            'status' => $revertStatus,
            'cancellation_reason' => null,
        ]);

        \App\Services\ActivityLogger::log(
            'inquiries',
            'withdraw_cancel',
            "Citizen {$inquiry->fullname} withdrew cancellation request for concern #{$inquiry->tracking_token}. Status reverted to {$revertStatus}.",
            'info',
            ['tracking_token' => $inquiry->tracking_token, 'status' => $revertStatus]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cancellation request withdrawn. Your concern remains active for MEO action.',
            'inquiry' => $this->transformInquiry($inquiry->fresh(['acceptedBy', 'resolvedBy', 'cancelledBy', 'updatedBy'])),
        ]);
    }

    /**
     * Clear active session submission to allow relaying a new concern.
     */
    public function resetSession(Request $request): RedirectResponse
    {
        $request->session()->forget('active_inquiry_token');

        return redirect()->route('ask.meo');
    }

    /**
     * Admin/Staff list of inquiries.
     */
    public function adminIndex(): JsonResponse
    {
        $inquiries = Inquiry::with(['acceptedBy', 'resolvedBy', 'cancelledBy', 'updatedBy'])
            ->latest()
            ->get()
            ->map(fn (Inquiry $i) => $this->transformInquiry($i));

        return response()->json($inquiries);
    }

    /**
     * Admin/Staff update status (Accept, Resolve, Decline, Cancel).
     */
    public function updateStatus(Request $request, Inquiry $inquiry): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,accepted,resolved,declined,cancel_requested,cancelled'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = auth()->id();
        $updates = [
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $inquiry->admin_notes,
            'updated_by' => $userId,
        ];

        if (isset($validated['cancellation_reason'])) {
            $updates['cancellation_reason'] = $validated['cancellation_reason'];
        }

        if ($validated['status'] === 'accepted') {
            if (! $inquiry->accepted_at) {
                $updates['accepted_at'] = now();
            }
            $updates['accepted_by'] = $userId;
        }

        if ($validated['status'] === 'resolved') {
            if (! $inquiry->resolved_at) {
                $updates['resolved_at'] = now();
            }
            $updates['resolved_by'] = $userId;
        }

        if ($validated['status'] === 'cancelled') {
            if (! $inquiry->cancelled_at) {
                $updates['cancelled_at'] = now();
            }
            $updates['cancelled_by'] = $userId;
        }

        $inquiry->update($updates);

        $currentUser = auth()->user();
        $severity = match ($validated['status']) {
            'resolved' => 'success',
            'accepted' => 'info',
            'cancelled' => 'warning',
            'declined' => 'warning',
            default => 'info',
        };

        $actionDesc = match ($validated['status']) {
            'accepted' => "Officer {$currentUser?->name} accepted concern #{$inquiry->tracking_token} for inspection.",
            'resolved' => "Officer {$currentUser?->name} marked concern #{$inquiry->tracking_token} as resolved.",
            'cancelled' => "Officer {$currentUser?->name} confirmed cancellation for concern #{$inquiry->tracking_token}.",
            'declined' => "Officer {$currentUser?->name} declined concern #{$inquiry->tracking_token}.",
            default => "Officer {$currentUser?->name} updated concern #{$inquiry->tracking_token} status to {$validated['status']}.",
        };

        \App\Services\ActivityLogger::log(
            'inquiries',
            $validated['status'],
            $actionDesc,
            $severity,
            [
                'tracking_token' => $inquiry->tracking_token,
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? null,
                'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Inquiry status updated to {$validated['status']}.",
            'inquiry' => $this->transformInquiry($inquiry->fresh(['acceptedBy', 'resolvedBy', 'cancelledBy', 'updatedBy'])),
        ]);
    }

    /**
     * Admin/Staff delete inquiry.
     */
    public function destroy(Inquiry $inquiry): JsonResponse
    {
        $token = $inquiry->tracking_token;
        $citizen = $inquiry->fullname;

        if (!empty($inquiry->photos) && is_array($inquiry->photos)) {
            foreach ($inquiry->photos as $p) {
                if ($p && Storage::disk('public')->exists($p)) {
                    Storage::disk('public')->delete($p);
                }
            }
        }

        if ($inquiry->photo_path && Storage::disk('public')->exists($inquiry->photo_path)) {
            Storage::disk('public')->delete($inquiry->photo_path);
        }

        $inquiry->delete();

        \App\Services\ActivityLogger::log(
            'inquiries',
            'delete',
            "Inquiry record #{$token} (Citizen: {$citizen}) was deleted from the system.",
            'danger',
            ['tracking_token' => $token, 'citizen' => $citizen]
        );

        return response()->json(['success' => true, 'message' => 'Inquiry deleted.']);
    }

    /**
     * Transform inquiry model for frontend display.
     */
    public function transformInquiry(Inquiry $inquiry): array
    {
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
            'cancellation_reason' => $inquiry->cancellation_reason,
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
            'cancelled_at' => $inquiry->cancelled_at?->format('M j, Y g:i A'),
            'cancelled_by' => $inquiry->cancelled_by,
            'cancelled_by_user' => $inquiry->cancelledBy ? [
                'id' => $inquiry->cancelledBy->id,
                'name' => $inquiry->cancelledBy->name,
                'role' => $inquiry->cancelledBy->role,
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
    }
}
