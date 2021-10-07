<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleUser extends Model
{
	protected $table = 'role_user';
	public $incrementing = false;
	//public $timestamps = false;

	//
	//public $timestamps = true;

	protected $casts = [
		'user_id' => 'int',
		'role_id' => 'int'
	];

	protected $fillable = [
		'user_id',
		'role_id',
		'status',
		'created_at',
		'updated_at'
	];

	public function role()
	{
		return $this->belongsTo(\App\Models\Role::class);
	}

	public function user()
	{
		return $this->hasOne(\App\User::class, 'id', 'user_id');
	}
}
