<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class UserNotification extends Eloquent
{
	protected $casts = [
		'user_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $dates = [
		'schedual_date',
		'send_at'
	];

	protected $fillable = [
		'user_id',
		'type',
		'message',
		'subject',
		'schedual_date',
		'send_at',
		'status',
		'is_send',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(\App\Models\User::class);
	}
}
