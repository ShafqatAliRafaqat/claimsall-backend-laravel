<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class AuditTrail extends Eloquent
{
	public $timestamps = false;

	protected $casts = [
		'created_by' => 'int'
	];

	protected $fillable = [
		'activity_title',
		'request_method',
		'request_header',
		'request_body',
		'request_url',
		'response_data',
		'ip_address',
		'created_at',
		'created_by'
	];
}
