<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remarks extends Model
{
    use HasFactory;

    protected $table = 'remarks_tb';

    protected $fillable = [
        'id',
        'remark',
        ];
}
