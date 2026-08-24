<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projects extends Model
{
    use HasFactory;

    protected $table = 'project_tb';

    protected $fillable = [
        'project_name',
        'location',
        'total_project_cost',
        'original_cost',
        'revised_cost',
        'project_description',
        'source_of_fund',
        'year',
        'project_duration',
        'start_date',
        'target_completion_date',
        'actual_completion_date',
        'revised_completion_date',
        'time_extention',
        'days_suspension_order',
        'percentage_of_accomplishment',
        'contractor',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'revised_completion_date' => 'date',
        'total_project_cost' => 'decimal:2',
        'original_cost' => 'decimal:2',
        'revised_cost' => 'decimal:2',
        'percentage_of_accomplishment' => 'decimal:2',
    ];

    public function techprep()
    {
        return $this->hasOne(Techprep::class, 'project_id');
    }

    public function remarks()
    {
        return $this->hasMany(Remarks::class, 'project_id');
    }

    public function fundTypes()
    {
        return $this->hasMany(Projectfundtype::class, 'project_id');
    }

    public function latestFundType()
    {
        return $this->hasOne(Projectfundtype::class, 'project_id')->latestOfMany();
    }

    public function powPrep()
    {
        return $this->hasMany(Pow::class, 'project_id');
    }

    public function infra()
    {
        return $this->hasOne(Infra::class, 'project_id');
    }
}
