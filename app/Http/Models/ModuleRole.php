<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

//use Reliese\Database\Eloquent\Model as Eloquent;

class ModuleRole extends Model
{
	protected $table = 'module_role';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'module_id' => 'int',
		'role_id' => 'int'
	];

	protected $fillable = [
		'module_id',
		'role_id',
		'create',
		'edit',
		'view',
		'report',
		'approved',
		'reject'
	];

	public function module()
	{
		return $this->belongsTo(\App\Models\Module::class);
	}

	public function role()
	{
		return $this->belongsTo(\App\Models\Role::class);
	}
}
