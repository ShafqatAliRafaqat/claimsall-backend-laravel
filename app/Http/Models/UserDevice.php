<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class UserDevice extends Eloquent {

    //use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'user_id',
        'fcm_token'
    ];
    public $incrementing = false;

    public function user() {
        return $this->belongsTo(\App\User::class);
    }

    public static function insertOrUpdate($data) {
        $device = self::where('user_id', $data['user_id'])->first();
        if ($device) {
            return self::where('user_id', $data['user_id'])->update($data);
        }
        return self::create($data);
    }

}
