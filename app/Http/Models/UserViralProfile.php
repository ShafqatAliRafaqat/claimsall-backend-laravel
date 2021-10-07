<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class UserViralProfile extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = [
        'user_id' => 'int',
        'viral_profile_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'user_id',
        'viral_profile_id',
        'owned_by',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function user() {
        return $this->belongsTo(\App\User::class);
    }
    
    public function viralProfilesToRespond() {
        return $this->belongsTo(\App\User::class, 'viral_profile_id');
    }

}
