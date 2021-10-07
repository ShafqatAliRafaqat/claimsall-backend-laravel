<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class RelationshipType extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = [
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'name',
        'policy_part',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function policy_covered_person() {
        return $this->hasOne(\App\Models\PolicyCoveredPerson::class);
    }

    public function relationships() {
        return $this->hasMany(\App\Models\Relationship::class, 'type_id');
    }

}
