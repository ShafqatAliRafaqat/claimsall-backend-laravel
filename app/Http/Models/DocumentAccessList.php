<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class DocumentAccessList extends Eloquent
{
	protected $casts = [
		'document_id' => 'int',
		'shared_user_id' => 'int'
	];

	protected $fillable = [
		'document_id',
		'shared_user_id',
		'view',
		'edit',
		'delete'
	];

	public function user()
	{
		return $this->belongsTo(\App\Models\User::class, 'shared_user_id');
	}

	public function document()
	{
		return $this->belongsTo(\App\Models\Document::class);
	}
}
