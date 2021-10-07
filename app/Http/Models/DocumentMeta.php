<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentMeta extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    private $authUser = null;

    public function __construct() {
        $this->authUser = \App\User::__HUID();
    }

    protected $connection = 'hosMysql';
    protected $table = 'document_meta';
    protected $casts = [
        'document_id' => 'int',
        'medicine_name' => 'string',
        'medicine_type' => 'string',
        'quantity' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string'
    ];
    protected $fillable = [
        'document_id',
        'medicine_name',
        'medicine_type',
        'quantity',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function document() {
        return $this->belongsTo(\App\Models\Document::class);
    }
}
