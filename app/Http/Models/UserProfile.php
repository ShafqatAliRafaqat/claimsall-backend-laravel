<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class UserProfile extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		/*'basic_salary' => 'float',
		'outdoor_availed_amount' => 'float',
		'outdoor_remaining_amount' => 'float',
		'outdoor_total_amount' => 'float',*/
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int',
		'total_walk_steps' => 'int',
		'total_walk_distance' => 'int',
		'total_cycling_time' => 'int',
		'total_cycling_distance' => 'int',
		'average_heart_rate' => 'float',
		'max_heart_rate' => 'float',
		'min_heart_rate' => 'float'
	];

	protected $dates = [
		'create_at',
		'update_at'
	];
        const TB_FIELDS =  [
		'user_id',
		//'basic_salary',
                //'gross_salary',
		//'outdoor_availed_amount',
		//'outdoor_remaining_amount',
		//'outdoor_total_amount',
		'is_deleted',
		'create_at',
		'update_at',
		'created_by',
		'updated_by',
		'deleted_by',
		'total_walk_steps',
		'total_walk_distance',
		'total_cycling_time',
		'total_cycling_distance',
		'average_heart_rate',
		'max_heart_rate',
		'min_heart_rate',
            'weight', 'height', 
            'surgical_history',
            'medical_history', 
            'known_genetic_disorders', 
            'any_disability', 
            'family_medical_history', 'allergies',
            'merital_status', 'dependant', 'kids'
	];
        
	protected $fillable = self::TB_FIELDS;

        public function user()
	{
		return $this->belongsTo(\App\User::class, 'user_id');
	}
}
