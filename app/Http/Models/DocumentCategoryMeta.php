<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;


class DocumentCategoryMeta extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

        protected $connection = 'hosMysql';
        protected $table = 'document_category_meta';
	protected $casts = [
		'category_medical_record_id' => 'int'
	];

	protected $fillable = [
		'category_medical_record_id',
		'medicine_name',
		'medicine_type',
		'quantity',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function category_medical_record()
	{
		return $this->belongsTo(\App\Models\CategoryMedicalRecord::class, 'id', 'document_category_id');
	}

	public function document_category()
	{
		return $this->belongsTo(\App\Models\DocumentCategory::class, 'category_id');
	}

	public function medical_record()
	{
		return $this->belongsTo(\App\Models\MedicalRecord::class);
	}
        
        
        public static function addOrUpdateByMedicalRecordID($data) {
            foreach ($data['medicines'] as $key => $medicines) {
                DocumentCategoryMeta::where([
                    'medical_record_id'=> $data['medical_record_id'], 
                    'category_id' => $key,])->update(['deleted_at' => now()]);
                    foreach ($medicines as $index => $medicine) {
                                $medicines[$index]['medical_record_id'] = $data['medical_record_id'];
                                $medicines[$index]['category_id'] = $key;
                                $medicines[$index]['created_at'] = now();
                                $medicines[$index]['updated_at'] = now();
                                $medicines[$index]['updated_by']= $medicines[$index]['created_by'] = $data['huid'];
                    }
                    self::insert($medicines);
            }
            return true;
        }
}
