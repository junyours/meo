<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WelcomeContentController;
use App\Models\Projects;
use App\Models\Bulletin;
use App\Models\WelcomeContent;
use App\Http\Controllers\BulletinController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\StaffAssignmentController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        return match (auth()->user()->role) {
            'superadmin' => redirect()->route('superadmin.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
            default => redirect()->route('dashboard'),
        };
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'welcomeContent' => WelcomeContent::getActive(),
        'projects' => Projects::with(['techprep', 'latestFundType', 'remarks'])->latest()->get()->map(function (Projects $project) {
            $status = [0 => 'ongoing', 1 => 'completed', 2 => 'delayed', 3 => 'not_started', 4 => 'suspended'][(int) $project->status] ?? 'ongoing';
            $prepFields = [
                'hazardAssessment' => 'hazard_assessment_status', 'powDed' => 'pow_ded_status',
                'supplementalBudget' => 'supplementary_budget_status', 'alobs' => 'alobs_status',
                'eccCnc' => 'ecc_cnc_status', 'technicalDocsToBac' => 'submission_tech_docs_status',
                'bidding' => 'bidding_status', 'contractNtp' => 'contract_ntp_status',
            ];
            $statusMap = [1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'na'];

            $fundCategory = $project->latestFundType?->fund_type ?: (
                $project->source_of_fund ? (
                    str_contains(strtolower($project->source_of_fund), 'national') || str_contains(strtolower($project->source_of_fund), 'gaa') || str_contains(strtolower($project->source_of_fund), 'dpwh') ? 'National' :
                    (str_contains(strtolower($project->source_of_fund), 'provincial') ? 'Provincial' : 'LGU')
                ) : 'LGU'
            );

            return [
                'id' => $project->id,
                'title' => $project->project_name,
                'location' => $project->location,
                'status' => $status,
                'progress' => (float) $project->percentage_of_accomplishment,
                'contractor' => $project->contractor ?: 'In-House / LGU Implemented',
                'budget' => (float) $project->total_project_cost,
                'totalCost' => (float) $project->total_project_cost,
                'originalCost' => $project->original_cost ? (float) $project->original_cost : null,
                'revisedCost' => $project->revised_cost ? (float) $project->revised_cost : null,
                'sourceOfFund' => $project->source_of_fund ?: 'LGU General Fund',
                'fundCategory' => $fundCategory,
                'year' => $project->year,
                'duration' => $project->project_duration,
                'description' => $project->project_description,
                'startDate' => optional($project->start_date)->format('M d, Y') ?: 'N/A',
                'endDate' => optional($project->target_completion_date)->format('M d, Y') ?: 'N/A',
                'actualCompletionDate' => optional($project->actual_completion_date)->format('M d, Y') ?: null,
                'revisedCompletionDate' => optional($project->revised_completion_date)->format('M d, Y') ?: null,
                'timeExtension' => $project->time_extention ? (int) $project->time_extention : 0,
                'daysSuspensionOrder' => $project->days_suspension_order ? (int) $project->days_suspension_order : 0,
                'remarks' => $project->remarks ? $project->remarks->pluck('remark')->filter()->values()->all() : [],
                'technical_preparations' => collect($prepFields)->mapWithKeys(fn ($field, $key) => [$key => ['status' => $project->techprep ? ($statusMap[(int) $project->techprep->{$field}] ?? 'na') : 'na']])->all(),
            ];
        })->values(),
        'announcements' => Bulletin::where('is_public', true)->where('is_archived', false)->latest()->get()->map(fn (Bulletin $bulletin) => [
            'id' => $bulletin->id,
            'title' => $bulletin->title,
            'category' => strtolower($bulletin->category),
            'date' => $bulletin->created_at?->toDateString(),
            'content' => $bulletin->summary,
            'isNew' => $bulletin->created_at?->gte(now()->subDays(7)),
        ])->values(),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/ask-meo', function () {
    return Inertia::render('AskMEO');
})->name('ask.meo');

Route::post('/ask-meo', function (Request $request) {
    $base = $request->validate([
        'fullname' => 'required|string|max:255',
        'phone' => 'nullable|string|max:50',
        'phone_na' => 'nullable|boolean',
        'email' => 'nullable|email|max:255',
        'email_na' => 'nullable|boolean',
        'subject' => 'nullable|string|max:255',
        'message' => 'required|string',
    ]);

    // If reCAPTCHA secret is configured, verify token from client.
    $recaptchaSecret = env('RECAPTCHA_SECRET');
    if ($recaptchaSecret) {
        $token = $request->input('recaptcha_token') ?: $request->input('g-recaptcha-response');
        if (! $token) {
            return back()->withErrors(['recaptcha' => 'reCAPTCHA token missing.'])->withInput();
        }

        $resp = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $recaptchaSecret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        $body = $resp->json();
        // for v3 expect 'success' and a 'score' between 0.0 and 1.0
        $scoreThreshold = 0.5; // tweak as needed
        if (! ($body && isset($body['success']) && $body['success'] === true)) {
            return back()->withErrors(['recaptcha' => 'reCAPTCHA verification failed.'])->withInput();
        }
        if (isset($body['score']) && $body['score'] < $scoreThreshold) {
            return back()->withErrors(['recaptcha' => 'reCAPTCHA score too low.'])->withInput();
        }
    } else {
        // fallback: require the simple checkbox
        $request->validate(['not_robot' => 'accepted']);
    }

    // TODO: persist or send message. For now flash a success message.
    return back()->with('success', 'Your message was received.');
})->name('ask.meo.send');

Route::get('/dashboard', function () {
    if (auth()->user()) {
        if (auth()->user()->role === 'superadmin') {
            return redirect()->route('superadmin.dashboard');
        }
        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        if (auth()->user()->role === 'staff') {
            return redirect()->route('staff.dashboard');
        }
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::match(['put', 'post'], '/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/bulletins', [BulletinController::class, 'index'])->name('bulletins.index');
    Route::post('/bulletins', [BulletinController::class, 'store'])->name('bulletins.store');
    Route::put('/bulletins/{bulletin}', [BulletinController::class, 'update'])->name('bulletins.update');
    Route::post('/bulletins/{bulletin}/archive', [BulletinController::class, 'archive'])->name('bulletins.archive');
    Route::post('/bulletins/{bulletin}/restore', [BulletinController::class, 'restore'])->name('bulletins.restore');
    Route::patch('/bulletins/{bulletin}/visibility', [BulletinController::class, 'visibility'])->name('bulletins.visibility');
    Route::delete('/bulletins/{bulletin}', [BulletinController::class, 'destroy'])->name('bulletins.destroy');
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::put('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
    Route::patch('/reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
    Route::get('/staff-assignments', [StaffAssignmentController::class, 'index'])->name('staff-assignments.index');
    Route::post('/staff-assignments', [StaffAssignmentController::class, 'store'])->name('staff-assignments.store');
    Route::put('/staff-assignments/{staffAssignment}', [StaffAssignmentController::class, 'update'])->name('staff-assignments.update');
    Route::patch('/staff-assignments/{staffAssignment}/status', [StaffAssignmentController::class, 'toggleStatus'])->name('staff-assignments.status');
    Route::patch('/staff-assignments/{staffAssignment}/reply', [StaffAssignmentController::class, 'reply'])->name('staff-assignments.reply');
    Route::delete('/staff-assignments/{staffAssignment}', [StaffAssignmentController::class, 'destroy'])->name('staff-assignments.destroy');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'adminIndex'])->name('dashboard');
    Route::get('/projects/fund-sources', [ProjectController::class, 'fundSources'])->name('projects.fund-sources');
    Route::get('/projects/{project}/details', [ProjectController::class, 'details'])->name('projects.details');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/technical-preparations', [ProjectController::class, 'technicalPreparations'])->name('projects.technical-preparations');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/verify', [DocumentController::class, 'verifyIntegrity'])->name('documents.verify');
});

// Staff routes
Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'staffIndex'])->name('dashboard');
    Route::get('/projects/fund-sources', [ProjectController::class, 'fundSources'])->name('projects.fund-sources');
    Route::get('/projects/{project}/info', [ProjectController::class, 'staffInfo'])->name('projects.info');
    Route::get('/projects/{project}/details', [ProjectController::class, 'details'])->name('projects.details');
    Route::get('/projects/{project}/my-details', [ProjectController::class, 'staffDetails'])->name('projects.my-details');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('/projects/{project}/accomplishment', [ProjectController::class, 'updateAccomplishment'])->name('projects.accomplishment');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/verify', [DocumentController::class, 'verifyIntegrity'])->name('documents.verify');
});

// Superadmin routes
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [ProjectController::class, 'index'])->name('dashboard');
    Route::get('/projects/fund-sources', [ProjectController::class, 'fundSources'])->name('projects.fund-sources');
    Route::get('/projects/{project}/details', [ProjectController::class, 'details'])->name('projects.details');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/technical-preparations', [ProjectController::class, 'technicalPreparations'])->name('projects.technical-preparations');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/verify', [DocumentController::class, 'verifyIntegrity'])->name('documents.verify');
    Route::get('/welcome-content', [WelcomeContentController::class, 'index'])->name('welcome-content.index');
    Route::post('/welcome-content', [WelcomeContentController::class, 'store'])->name('welcome-content.store');
    Route::put('/welcome-content/{id}', [WelcomeContentController::class, 'update'])->name('welcome-content.update');
    Route::post('/welcome-content/upload-image', [WelcomeContentController::class, 'uploadImage'])->name('welcome-content.upload-image');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/send-reset-link', [\App\Http\Controllers\UserController::class, 'sendResetLink'])->name('users.send-reset-link');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/auth.php';
