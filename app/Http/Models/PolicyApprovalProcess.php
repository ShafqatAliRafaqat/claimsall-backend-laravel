<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class PolicyApprovalProcess extends Eloquent {

    //use \Illuminate\Database\Eloquent\SoftDeletes;
    
    const AD= [3,4];//APPROVED / DECLINE
    const ADP= [3,4, 5];//APPROVED / DECLINE // PARTIALAPPROVED

    protected $table = 'policy_approval_process';
    protected $casts = [
        'role_id' => 'int',
        'organization_id' => 'int',
        'approval_order' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'role_id',
        'organization_id',
        'approval_order',
        'is_ready_to_pay',
        'type',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function organization() {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function role() {
        return $this->belongsTo(\App\Models\Role::class);
    }

    public function process_status() {
        return $this->hasOne(\App\Models\ProcessStatus::class, 'approval_process_id');
    }
    
    public function statuses() {
        return $this->belongsToMany(\App\Models\PolicyApprovalStatus::class, 'process_status', 'approval_process_id', 'approval_status_id');
    }

}
