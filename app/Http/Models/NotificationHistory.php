<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class NotificationHistory extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'notification_history';
    protected $casts = [
        'user_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'user_id',
        'content',
        'is_seen',
        'fcm_response',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function user() {
        return $this->belongsTo(\App\Models\User::class);
    }

}
