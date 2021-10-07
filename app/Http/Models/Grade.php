<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Grade extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'organization_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'title',
		'description',
		'organization_id',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function organization()
	{
		return $this->belongsTo(\App\Models\Organization::class);
	}

	public function organization_policy()
	{
		return $this->hasOne(\App\Models\OrganizationPolicy::class);
	}

	public function organization_user()
	{
		return $this->hasOne(\App\Models\OrganizationUser::class);
	}
}
