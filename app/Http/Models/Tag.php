<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class Tag extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'type',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function documents()
	{
		return $this->belongsToMany(\App\Models\Document::class);
	}
}
