<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\HealthMonitoringData;
use App\Models\HealthMonitoringType;
use App\Models\FamilyTree;

class HealthCareController extends Controller
{
    use \App\Traits\WebServicesDoc;
    
    public function getHealthCareTypes() {
        $types = HealthMonitoringType::select(['id', 'name', 'unit', 'icon'])->get()->toArray();
        
        foreach ($types as $key => $type) {
            if(!empty($type['icon'])){
                $types[$key]['icon'] = getDocPath(false).'/'.$type['icon'];
            }
        }
        $response = responseBuilder()->success("Health Monitor Types:", $types);
        $this->urlComponents('Get Health Monitoring Types', $response, 'Health_Monitoring_Data');
        return $response;
    }
    public function store(Request $request) {
        // healthmonitoring data should be added for self only not for fnf, Only view is allowed
        $rules = [
            'sync_time' => 'required', 'custom_data' => 'array'
            ];
        if($request->has('custom_data')){
            $rules['custom_data.*.type_id'] =  'bail|required|exists:hosMysql.health_monitoring_types,id';
            $rules['data.*.value'] = 'required';
        }
        $request->validate($rules);
        $oHealthMonitoringData = new HealthMonitoringData();
        $data = $oHealthMonitoringData->saveHealthData($request);
        if($data){
            $response = responseBuilder()->success('Saved health monitoring data successfully');
            $this->urlComponents('Add Health Monitoring Data', $response, 'Health_Monitoring_Data');
            return $response;
        }
        return responseBuilder()->error('Unable to process request for saving health care data');
    }
    
    public function getHealthCareByRelationshipID($fnfUserId) {
        $user = \Auth::user();
        $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $fnfUserId]);
        if(!$getUser){
             return responseBuilder()->error('You don\'t have permission to view health monitoring data for this user', 403);
        }
        $permissonPrefix = ($getUser['parent_user_id'] == $fnfUserId) ? 'shared_profile': 'assc_shared_profile';
        if($getUser[$permissonPrefix] == 'N'){
            return ['status' => false, 'message' => 'This you don\'t have permission to view medical records for this user', 'code' => 403];
        }
        $nokUser = \App\User::__HUID($fnfUserId);
        $fnfHUID = $nokUser['__'];
        $fnfUserData = $nokUser['user'];
//        \DB::connection('hosMysql')->enableQueryLog();
        $healthMonitoringData = [];
        $healthMonitoringTypes = HealthMonitoringType::select(['id', 'name', 'unit', 'code', 'icon'])->where('is_default', 'Y')->get()->toArray();
        foreach ($healthMonitoringTypes as $type) {
            $healthMonitingTemp = HealthMonitoringData::select(['id', 'type_id', 'custom_data', 'data_value', 'active_time', 'sync_time', 'distance', 'created_at', 'updated_at'])
                    ->where(['is_deleted' => '0', 'huid' => $fnfHUID, 'type_id' => $type['id']])->whereNull('custom_data')
                    ->orderBy('id', 'DESC')->first();
            if(!empty($healthMonitingTemp)){
                $healthMonitingTemp = $healthMonitingTemp->toArray();
                $healthMonitoringData[$type['code']] = array_merge($type, $healthMonitingTemp);
            }
            $healthMonitingTemp2 = HealthMonitoringData::select(['id', 'type_id', 'custom_data', 'data_value', 'active_time', 'sync_time', 'distance', 'created_at', 'updated_at'])
                    ->where(['is_deleted' => '0', 'huid' => $fnfHUID, 'type_id' => $type['id']])->whereNotNull('custom_data')
                    ->orderBy('id', 'DESC')->first();
            if(!empty($healthMonitingTemp2)){
                $healthMonitingTemp2 = $healthMonitingTemp2->toArray();
                $healthMonitoringData[$type['code'].'_custom'] = array_merge($type, $healthMonitingTemp2);
            }
            
//            $queries = \DB::connection('hosMysql')->getQueryLog();
//            dump($queries);     die;   
        }
//            dump($healthMonitoringData);
//            die;
        if(empty($healthMonitoringData)){
            $response = responseBuilder()->success('No data found for this user');
            $this->urlComponents('Get FNF\'s Health Monitoring Data', $response, 'Health_Monitoring_Data');
            return $response;
        }        
        
        $healthData = [];
        $path = 'storage/common/health_monitoring_types/';
        foreach ($healthMonitoringData as $key => $value) {
            $data['sync_time'] = $value['sync_time'];
            $data['active_time'] = $value['active_time'];
            $data['distance'] = $value['distance'];
            //&& in_array($value['type_id'], [1, 2, 3, 12])
            if(empty($value['custom_data'])){
                $data[$value['code']] = $value['data_value'];
            }
            if(!empty($value['custom_data'])){
                $customData['data_value'] = unserialize($value['custom_data']);
                $customData['type_id'] = $value['type_id'];
                $customData['type_name'] = $value['name'];
                $customData['type_icon'] = asset($path.$value['icon']);
                $data['custom_data'][] = $customData;
            }
//            $healthData[] =$data;
        }
//        $healthMonitoringData = HealthMonitoringData::select(['type_id', 'data_value', 'active_time', 'sync_time', 'distance', 'created_at', 'updated_at'])
//                ->where(['is_deleted' => '0'])
//                ->where('huid', $fnfHUID)
//                ->with(['health_monitoring_type'=> function($healthMonitoringTypeQuery){
//                    $healthMonitoringTypeQuery->select(['id', 'name', 'code', 'unit', 'is_default']);
//                }])->orderBy('updated_at', 'desc')->get()->toArray();
        
        
//        $data = [];
//        foreach ($healthMonitoringData as $value) {
//            $data['sync_time'] = $value['sync_time'];
//            $value['type_name'] = $value['health_monitoring_type']['name'];
//            $data['data'][] = $value;
//         }
        $response = responseBuilder()->success('Your health monitoring data are here', $data);
        $this->urlComponents('Get FNF\'s Health Monitoring Data', $response, 'Health_Monitoring_Data');
        return $response;
    }
}
