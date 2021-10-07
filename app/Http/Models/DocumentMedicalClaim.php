<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;


class DocumentMedicalClaim extends Eloquent
{
	protected $table = 'document_medical_claim';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'document_id' => 'int',
		'medical_claim_id' => 'int',
		'document_category_id' => 'int'
	];

	protected $fillable = [
		'document_id',
		'medical_claim_id',
		'document_category_id'
	];

	public function document_category()
	{
		return $this->belongsTo(\App\Models\DocumentCategory::class);
	}

	public function document()
	{
		return $this->belongsTo(\App\Models\Document::class);
	}
        
        

	public function medical_claim()
	{
		return $this->belongsTo(\App\Models\MedicalClaim::class);
	}
}
