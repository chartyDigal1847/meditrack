<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentConcern extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'external_student_id', 'title', 'description', 'severity', 'status', 'submitted_at', 'reviewed_at'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
}
