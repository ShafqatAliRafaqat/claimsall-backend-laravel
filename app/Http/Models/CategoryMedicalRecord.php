<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class CategoryMedicalRecord extends Eloquent
{
	public $timestamps = false;
        protected $connection = 'hosMysql';
        protected $table = 'category_medical_records';

	protected $casts = [
		'medical_record_id' => 'int',
		'document_category_id' => 'int'
	];

	protected $fillable = [
		'medical_record_id',
		'document_category_id'
	];

	public function document_category()
	{
		return $this->belongsTo(\App\Models\DocumentCategory::class);
	}

	public function medical_record()
	{
		return $this->belongsTo(\App\Models\MedicalRecord::class);
	}

	public function document_category_meta()
	{
		return $this->hasMany(\App\Models\DocumentCategoryMeta::class);
	}

	public function documents()
	{
		return $this->hasMany(\App\Models\Document::class);
	}
}
