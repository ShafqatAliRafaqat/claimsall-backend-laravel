<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Reliese\Database\Eloquent\Model as Eloquent;

class EmailTemplate extends Model
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'user_id' => 'int',
		'organization_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'user_id',
		'organization_id',
		'is_active',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function user()
	{
		return $this->belongsTo(\App\Models\User::class);
	}

	public function organization()
	{
		return $this->belongsTo(\App\Models\Organization::class);
	}
}
