<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthReport extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'nurse_id', 'report_type', 'title', 'period_start', 'period_end', 'summary', 'metrics', 'status', 'generated_at'];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'metrics' => 'array', 'generated_at' => 'datetime'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
