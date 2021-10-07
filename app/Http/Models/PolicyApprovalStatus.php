<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class PolicyApprovalStatus extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = [
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function process_status() {
        return $this->hasOne(\App\Models\ProcessStatus::class, 'approval_status_id');
    }

    public function processes() {
        return $this->hasMany(\App\Models\PolicyApprovalProcess::class, 'process_status', 'approval_process_id');
    }
}
