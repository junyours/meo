<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Infra extends Model
{
    use HasFactory;

    protected $table = 'infra_audit_tb';

    protected $fillable = [
        'project_id',
        'form1',
        'form2a',
         'form2b',
         'status',
    ];
}
