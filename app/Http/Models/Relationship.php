<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
                'is_deleted' => 'string',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'type_id',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function family_tree()
	{
		return $this->hasOne(\App\Models\FamilyTree::class, 'id');
	}

	public function medical_claims()
	{
		return $this->hasMany(\App\Models\MedicalClaim::class);
	}

	public function medical_records()
	{
		return $this->hasMany(\App\Models\MedicalRecord::class);
	}

	public function policy_covered_people()
	{
		return $this->hasMany(\App\Models\PolicyCoveredPerson::class, 'relation_ship_id');
	}
        
        public function relationship_type() {
            return $this->belongsTo(\App\Models\RelationshipType::class, 'type_id');
        }
        
        
        public static function getAsscRelaitonshipId($id, $gender) {
            if($id==1){
                return 2;
            }elseif ($id == 2) {
                return 1;
            }elseif ($id == 22) {
                return 22;
            }elseif ($id == 5 || $id == 6) {
                return ($gender=="Male") ? 9: 10;
            }elseif ($id == 9 || $id == 10) {
                return ($gender=="Male") ? 5: 6;
            }
            return null;
        }
}
