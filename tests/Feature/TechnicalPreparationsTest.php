<?php

namespace Tests\Feature;

use App\Models\Projects;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalPreparationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_retrieve_technical_preparations_remarks(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $project = Projects::create([
            'project_name' => 'Road Concreting Project',
            'location' => 'Brgy. San Jose',
            'total_project_cost' => 1500000,
            'source_of_fund' => 'LGU General Fund',
            'year' => 2026,
            'project_duration' => 90,
            'start_date' => '2026-01-01',
            'target_completion_date' => '2026-04-01',
            'time_extention' => 0,
            'days_suspension_order' => 0,
            'percentage_of_accomplishment' => 0,
            'contractor' => 'ABC Construction',
            'status' => 0,
        ]);

        $payload = [
            'hazardAssessment' => ['status' => 'green', 'notes' => 'MDRRMO cleared hazard zone on Jan 15.'],
            'powDed' => ['status' => 'yellow', 'notes' => 'POW revisions ongoing.'],
            'supplementalBudget' => ['status' => 'na', 'notes' => 'Not applicable for this funding source.'],
            'alobs' => ['status' => 'red', 'notes' => 'Waiting for accountant signature.'],
            'eccCnc' => ['status' => 'green', 'notes' => 'CNC issued.'],
            'technicalDocsToBac' => ['status' => 'yellow', 'notes' => 'Submitted draft docs.'],
            'bidding' => ['status' => '', 'notes' => ''],
            'contractNtp' => ['status' => '', 'notes' => ''],
            'remarks' => 'Pending MDRRMO sign-off and ECC approval.',
        ];

        $response = $this->actingAs($admin)->postJson("/admin/projects/{$project->id}/technical-preparations", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('technical_preparations.remarks', 'Pending MDRRMO sign-off and ECC approval.');
        $response->assertJsonPath('technical_preparations.hazardAssessment.status', 'green');
        $response->assertJsonPath('technical_preparations.hazardAssessment.notes', 'MDRRMO cleared hazard zone on Jan 15.');
        $response->assertJsonPath('technical_preparations.powDed.status', 'yellow');
        $response->assertJsonPath('technical_preparations.powDed.notes', 'POW revisions ongoing.');

        $this->assertDatabaseHas('tech_prep_tb', [
            'project_id' => $project->id,
            'hazard_assessment_status' => 1,
            'hazard_assessment_notes' => 'MDRRMO cleared hazard zone on Jan 15.',
            'pow_ded_status' => 2,
            'pow_ded_notes' => 'POW revisions ongoing.',
            'supplementary_budget_status' => 4,
            'supplementary_budget_notes' => 'Not applicable for this funding source.',
            'alobs_status' => 3,
            'alobs_notes' => 'Waiting for accountant signature.',
            'ecc_cnc_status' => 1,
            'ecc_cnc_notes' => 'CNC issued.',
            'submission_tech_docs_status' => 2,
            'submission_tech_docs_notes' => 'Submitted draft docs.',
            'remarks' => 'Pending MDRRMO sign-off and ECC approval.',
        ]);

        // Verify project show endpoint returns the technical preparations notes and remarks
        $showResponse = $this->actingAs($admin)->getJson("/admin/projects/{$project->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('project.technical_preparations.hazardAssessment.notes', 'MDRRMO cleared hazard zone on Jan 15.');
        $showResponse->assertJsonPath('project.technical_preparations.powDed.notes', 'POW revisions ongoing.');
        $showResponse->assertJsonPath('project.technical_preparations.remarks', 'Pending MDRRMO sign-off and ECC approval.');
    }

    public function test_superadmin_can_save_technical_preparations_remarks(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
        ]);

        $project = Projects::create([
            'project_name' => 'Drainage System Phase 2',
            'location' => 'Poblacion',
            'total_project_cost' => 2500000,
            'source_of_fund' => '20% Development Fund',
            'year' => 2026,
            'project_duration' => 120,
            'start_date' => '2026-02-01',
            'target_completion_date' => '2026-06-01',
            'time_extention' => 0,
            'days_suspension_order' => 0,
            'percentage_of_accomplishment' => 0,
            'contractor' => 'XYZ Builders',
            'status' => 0,
        ]);

        $payload = [
            'hazardAssessment' => ['status' => 'green'],
            'powDed' => ['status' => 'green'],
            'remarks' => 'All pre-construction technical docs finalized.',
        ];

        $response = $this->actingAs($superadmin)->postJson("/superadmin/projects/{$project->id}/technical-preparations", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('technical_preparations.remarks', 'All pre-construction technical docs finalized.');

        $this->assertDatabaseHas('tech_prep_tb', [
            'project_id' => $project->id,
            'hazard_assessment_status' => 1,
            'pow_ded_status' => 1,
            'remarks' => 'All pre-construction technical docs finalized.',
        ]);
    }
}
