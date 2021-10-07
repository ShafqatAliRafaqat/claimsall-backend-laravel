<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model
{
	//use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $table = 'user_meta';

	private $authUser = null;

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'user_id',
		'type',
		'name',
		'meta_value',
		'description',
		'created_by',
		'updated_by'
	];
}
