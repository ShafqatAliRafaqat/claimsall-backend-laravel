<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityData extends Model
{
	//use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $table = 'activity_data';
	
	protected $casts = [
		'activity_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'activity_id',
		'type',
		'name',
		'dosage',
		'frequency',
		'frequency_timing',
		'created_by',
		'updated_by'
	];

	public function activity()
	{
		return $this->belongsTo(\App\Models\Activity::class);
	}
}
