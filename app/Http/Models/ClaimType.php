<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class ClaimType extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $connection = 'mysql';

    protected $casts = [
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'name',
        'description',
        'policy_claimed',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function organization_policies() {
        return $this->hasMany(\App\Models\OrganizationPolicy::class);
    }

    public function medical_claims() {
        return $this->hasMany(\App\Models\ClaimType::class);
    }
}
