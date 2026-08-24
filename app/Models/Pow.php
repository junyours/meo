<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pow extends Model
{
    use HasFactory;

    protected $table = 'pow_prep_tb';

    protected $fillable = [
        'project_id',
        'project_cost',
        'office_concern',
        'status',
    ];
}
