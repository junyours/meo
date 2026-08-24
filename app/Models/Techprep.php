<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Techprep extends Model
{
    use HasFactory;
    protected $table = 'tech_prep_tb';

    protected $fillable = [
        'project_id',
        'hazard_assessment_status',
        'hazard_assessment_notes',
        'pow_ded_status',
        'pow_ded_notes',
        'supplementary_budget_status',
        'supplementary_budget_notes',
        'alobs_status',
        'alobs_notes',
        'ecc_cnc_status',
        'ecc_cnc_notes',
        'submission_tech_docs_status',
        'submission_tech_docs_notes',
        'bidding_status',
        'bidding_notes',
        'contract_ntp_status',
        'contract_ntp_notes',
        'remarks',
    ];
}
