<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class Order extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'quantity' => 'int',
		'city_id' => 'int',
		'user_id' => 'int',
		'medical_record_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'medicine_name',
		'medicine_short_code',
		'quantity',
		'shipping_address',
		'city_id',
		'user_id',
		'medical_record_id',
		'prefered_pharmacy',
		'phone_number',
		'status',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function user()
	{
		return $this->belongsTo(\App\Models\User::class);
	}

	public function medical_record()
	{
		return $this->belongsTo(\App\Models\MedicalRecord::class);
	}
}
