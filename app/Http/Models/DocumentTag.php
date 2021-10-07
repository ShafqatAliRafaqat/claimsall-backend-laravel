<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class DocumentTag extends Eloquent
{
	protected $table = 'document_tag';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'tag_id' => 'int',
		'document_id' => 'int'
	];

	protected $fillable = [
		'tag_id',
		'document_id'
	];

	public function document()
	{
		return $this->belongsTo(\App\Models\Document::class);
	}

	public function tag()
	{
		return $this->belongsTo(\App\Models\Tag::class);
	}
}
