<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class HealthMonitoringType extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = [
        'name',
        'code',
        'unit',
        'icon',
        'is_default',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $connection = 'hosMysql';
    
    public function health_monitoring_data() {
        return $this->hasMany(HealthMonitoringData::class, 'type_id');
    }
    
    public static function setCode($val) {
        $val = strtolower($val);
        $val = str_replace(' ', '-', $val);
        return $val;
    }

}
