<?php

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

class WebService extends \Eloquent
{
	//use \App\Models\CommonModelFunctions;
	//protected $dateFormat = 'U';
	public static $snakeAttributes = false;

	protected $casts = [
		'created_at' => 'string',
		'updated_at' => 'string'
	];

	protected $fillable = [
		'title',
                'module',
		'url',
		'method',
                'method_name',
		'header_params',
		'body_params',
		'response_sample',
                'auth'
	];
}
