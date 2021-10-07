<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class DocumentCategory extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'document_category';
    protected $connection = 'hosMysql';
    protected $casts = [
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'name',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function category_medical_records() {
        return $this->hasMany(\App\Models\CategoryMedicalRecord::class);
    }

    public function documents() {
        return $this->hasManyThrough(\App\Models\CategoryMedicalRecord::class, \App\Models\Document::class, 'category_medical_record_id', 'id');
    }

    public function medical_claims() {
        return $this->hasMany(\App\Models\MedicalClaim::class);
    }

    public function document_medical_claim() {
        return $this->hasOne(\App\Models\DocumentMedicalClaim::class);
    }

//    public function documents()
//{
//    return $this->hasManyThrough('App\Documents', 'App\CategoryMedicalRecord');
//}
//public function documentCategoryMetas()
//{
//    return $this->hasManyThrough('App\DocumentCategoryMeta', 'App\CategoryMedicalRecord');
//}

    public function documentCategoryMetas() {
        return $this->hasManyThrough(\App\Models\DocumentCategoryMeta::class, \App\Models\CategoryMedicalRecord::class);
    }

}
