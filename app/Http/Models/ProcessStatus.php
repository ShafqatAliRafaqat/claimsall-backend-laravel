<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class ProcessStatus extends Eloquent {

    protected $table = 'process_status';
    public $incrementing = false;
    public $timestamps = false;
    protected $casts = [
        'approval_process_id' => 'int',
        'approval_status_id' => 'int'
    ];
    protected $fillable = [
        'approval_process_id',
        'approval_status_id'
    ];

    public function policy_approval_process() {
        return $this->belongsTo(\App\Models\PolicyApprovalProcess::class, 'approval_process_id');
    }

    public function policy_approval_status() {
        return $this->belongsTo(\App\Models\PolicyApprovalStatus::class, 'approval_status_id');
    }

}
