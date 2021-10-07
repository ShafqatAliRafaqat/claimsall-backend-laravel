<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
	public $timestamps = false;

	protected $casts = [
		'state_id' => 'int'
	];

	protected $fillable = [
		'name',
		'state_id'
	];
        
        public function organization() {
            return $this->belongTo(\App\Models\Organization::class);
        }    
        
        public function state() {
            return $this->belongsTo(State::class);
        }
        
        public function user() {
            return $this->belongsTo(\App\User::class);
        }
        
}
