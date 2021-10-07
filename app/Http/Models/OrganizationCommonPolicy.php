<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class OrganizationCommonPolicy extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = [
		'organization_id' => 'int',
		'start_month' => 'int',
		'end_month' => 'int',
		'indoor_limit' => 'float',
		'indoor_room_limit' => 'float',
		'outdoor_amount' => 'float',
		'maternity_room_limit' => 'float',
		'maternity_normal_case_limit' => 'float',
		'maternity_csection_case_limit' => 'float',
		'maternity_max_cases' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'short_code',
		'organization_id',
                'advance_payment_option',
		'start_month',
		'end_month',
		'indoor_limit',
		'indoor_duration',
		'indoor_room_limit',
		'outdoor_type',
		'outdoor_amount',
		'maternity_room_limit',
		'maternity_normal_case_limit',
		'maternity_csection_case_limit',
                'maternity_max_cases',
		'is_active',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

    
    public function organization() {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function policy_covered_persons() {
        return $this->hasMany(\App\Models\PolicyCoveredPerson::class);
    }
    
}
