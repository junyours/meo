<?php

namespace Tests\Feature;

use App\Models\DocumentScanner;
use App\Models\Projects;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_preview_and_download_project_documents(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
        ]);

        $project = Projects::create([
            'project_name' => 'Sample Road Concreting',
            'location' => 'Poblacion',
            'status' => 0,
            'total_project_cost' => 1000000,
            'source_of_fund' => '20% EDF',
            'project_duration' => 120,
            'year' => '2026',
            'time_extention' => 0,
            'days_suspension_order' => 0,
            'percentage_of_accomplishment' => 0,
            'contractor' => 'ABC Construction',
            'start_date' => '2026-01-01',
            'target_completion_date' => '2026-12-31',
        ]);

        $file = UploadedFile::fake()->create('sample_plan.pdf', 500, 'application/pdf');
        $storedPath = $file->storeAs('documents', 'sample_plan.pdf', 'local');

        $document = DocumentScanner::create([
            'document_name' => 'Sample Plan',
            'document_type' => 'pdf',
            'file_path' => $storedPath,
            'file_hash' => hash_file('sha256', Storage::disk('local')->path($storedPath)),
            'file_size' => 500 * 1024,
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'is_public' => false,
            'processing_status' => 'completed',
        ]);

        // Staff accesses preview
        $previewResponse = $this->actingAs($staff)->get("/staff/documents/{$document->id}/preview");
        $previewResponse->assertStatus(200);

        // Staff accesses download
        $downloadResponse = $this->actingAs($staff)->get("/staff/documents/{$document->id}/download");
        $downloadResponse->assertStatus(200);
    }
}
