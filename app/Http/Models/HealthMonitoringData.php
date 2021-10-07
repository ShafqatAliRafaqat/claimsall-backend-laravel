<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

class HealthMonitoringData extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    private $authUser = null;

    public function __construct() {
        $this->authUser = \App\User::__HUID();
    }

    protected $connection = 'hosMysql';
    protected $table = 'health_monitoring_data';
    protected $casts = [
        'user_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $dates = [
        'sync_time'
    ];
    protected $fillable = [
        'huid',
        'type_id',
        'cutom_type',
        'data_value',
        'active_time',
        'sync_time',
        'capture_time',
        'distance',
        'is_deleted',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function health_monitoring_type() {
        return $this->belongsTo(\App\Models\HealthMonitoringType::class, 'type_id');
    }

    public function saveHealthData($request) {
        $post = $request->all();
        $data = $rowData = [];
        $defaultCats = HealthMonitoringType::where('is_default', 'Y')->pluck('id', 'code')->toArray();
        if(empty($defaultCats)){ return;}
        $syncTime = $post['sync_time'];
        $activeTime = (!empty($post['active_time']) ? $post['active_time'] : null);
        $distance = (!empty($post['distance']) ? $post['distance'] : null);
        $customData = !empty($post['custom_data']) ? $post['custom_data'] : [];
       
        foreach ($post as $key => $value) {
            if (!empty($defaultCats[$key])) {
                $rowData = ['sync_time' => $syncTime, 'type_id' => $defaultCats[$key], 'data_value' => $value];
                $rowData['active_time'] = $activeTime;
                $rowData['distance'] = $distance;
                $rowData['huid'] = $rowData['created_by'] = $rowData['updated_by'] = $this->authUser['__'];
                $rowData['created_at'] = $rowData['updated_at'] = now();
                $rowData['custom_data'] =$rowData['capture_time'] = null;
                $data[] = $rowData;
            }
        }
        foreach ($customData as $value) {
            if (!empty($value['data_value'])) {
                $rowData = ['type_id' => $value['type_id'], 'sync_time' => $syncTime, 'data_value' => 0,
                    'custom_data' => serialize($value['data_value']), 'capture_time' => null
                        ];
                $rowData['active_time'] = $activeTime;
                $rowData['distance'] = $distance;
                $rowData['huid'] = $rowData['created_by'] = $rowData['updated_by'] = $this->authUser['__'];
                $rowData['created_at'] = $rowData['updated_at'] = now();
//                dump($rowData);
//                die;
                $data[] = $rowData;
            }
        }
        return $this->insert($data);
    }

}
