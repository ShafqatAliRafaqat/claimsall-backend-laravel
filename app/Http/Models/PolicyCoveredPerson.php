<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class PolicyCoveredPerson extends Eloquent {

    protected $table = 'policy_covered_persons';
    protected $casts = [
        'organization_policy_id' => 'int',
        'claim_type_id' => 'int',
        'relationship_type_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int'
    ];
    protected $fillable = [
        'organization_policy_id',
        'organization_common_policy_id',
        'claim_type_id',
        'relationship_type_id',
        'created_by',
        'updated_by'
    ];

    public function claim_type() {
        return $this->belongsTo(\App\Models\ClaimType::class);
    }

    public function organization_policy() {
        return $this->belongsTo(\App\Models\OrganizationPolicy::class);
    }
    
    public function organization_common_policy() {
        return $this->belongsTo(\App\Models\OrganizationCommonPolicy::class);
    }

    public function relationship_type() {
        return $this->belongsTo(\App\Models\RelationshipType::class);
    }
    
    
    public static function setDataFormat($data, $user) {
        $newData = [];
        foreach ($data as $row) {
            foreach ($row['relationship_type_id'] as $id) {
                $row['relationship_type_id'] = $id;
                $row['created_by'] =$row['updated_by'] =$user->id;
                $newData[] = $row;
            }
        }
        return $newData;
    }

}
