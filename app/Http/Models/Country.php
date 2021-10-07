<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Country extends Eloquent
{
	public $timestamps = false;

	protected $casts = [
		'phonecode' => 'int'
	];

	protected $fillable = [
		'sortname',
		'name',
		'phonecode'
	];
        
        public function state() {
            return $this->hasOne(State::class);
        }
        
}
