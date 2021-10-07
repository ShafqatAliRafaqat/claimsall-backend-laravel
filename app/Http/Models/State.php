<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
	public $timestamps = false;

	protected $casts = [
		'country_id' => 'int'
	];

	protected $fillable = [
		'name',
		'country_id'
	];
        
        public function city() {
            return $this->hasOne(City::class);
        }    
        
        public function Country() {
            return $this->belongsTo(Country::class);
        }
}
