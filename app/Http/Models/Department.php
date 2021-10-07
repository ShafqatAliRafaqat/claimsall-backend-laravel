<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class Department extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function organizations()
	{
		return $this->hasMany(\App\Models\Organization::class);
	}
}
