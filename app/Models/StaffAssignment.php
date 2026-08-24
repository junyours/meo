<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAssignment extends Model
{
    use HasFactory;

    protected $table = 'staff_assignments';

    protected $fillable = [
        'user_id',
        'assigned_by',
        'project_id',
        'type',
        'title',
        'note',
        'staff_reply',
        'staff_replied_at',
        'role_in_project',
        'target_deadline',
        'priority',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'target_deadline' => 'date',
        'completed_at' => 'datetime',
        'staff_replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }
}
