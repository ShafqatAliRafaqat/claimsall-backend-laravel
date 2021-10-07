<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityData;

class ActivityController extends Controller
{
    use \App\Traits\WebServicesDoc,        \App\Traits\FCM;
    public function getActivities(Request $request)
    {
        $post = $request->all();
        $activity = new Activity();
        $data = $activity->getActivities($post);
        if(isset($data['status']) && $data['status']===true) {
            $res = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $res, FALSE);
            $this->urlComponents('Get List of All Activities scheduled by loggedin User', $response, 'Activity_Scheduling_Management');
            return $response;
        }
        return responseBuilder()->error($data['message'], $data['code']);
    }

    public function store(\App\Http\Requests\Activity $request) {
        $activity = new Activity();
        $res = $activity->addActivity($request);
        if(isset($res['status']) && $res['status'] === true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Schedule an Activity by loggedin User', $response, 'Activity_Scheduling_Management');
            return $response;
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }

    public function update(\App\Http\Requests\Activity $request, $id)
    {
        $activity = new Activity();
        $res = $activity->updateActivityById($request, $id);
        if(isset($res['status']) && $res['status'] === true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Update an already Scheduled Activity by loggedin User', $response, 'Activity_Scheduling_Management');
            return $response;
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }

    public function destroy($id)
    {
        $activity = new Activity();
        $res = $activity->deleteByUserAndId($id);
        if($res['status']=== true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Delete Scheduled Activity by loggedin User', $response, 'Activity_Scheduling_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
    
     public function activityCron() {
         
         \DB::enableQueryLog();
//         $currentTime = now();
         $todayDate = date('Y-m-d');
         $nowTime = time();
         echo '-------[CRONE_RUN_AT]-------';
         dump(date('Y-m-d H:i:s',$nowTime));
         echo '--------------';
         $activities = \DB::table('activity')
                 ->select('activity.*', 'activity_data.*', 'activity_data.created_at as ad_create_at')
                 ->where('activity.start_date', '<=', $todayDate)
                 ->where('activity.end_date', '>=', $todayDate)
                 ->whereNull('activity.deleted_at')
                 ->where('activity.is_notify', 'Y')
                 ->join('activity_data', 'activity.id', '=', 'activity_data.activity_id')
                 ->get();
         //$queries = \DB::getQueryLog();
         //dump($queries);
//         dump($activities);
//         die;
         if(!empty($activities)){
             foreach ($activities as $key => $activity) {
                 if(!empty($activity->frequency_timing)){
                     $activityTimes = unserialize($activity->frequency_timing);
                     foreach ($activityTimes as $key => $time) {
                         $mints = (!empty($activity->reminder_interval)) ? $activity->reminder_interval : '10';
                         $mints = intval($mints);
                         $currentTime = time();
                         $plsMin = $currentTime + ($mints*60);
                         $minMin = $currentTime - (5*60);
                         $acTime = strtotime($time);
                         dump("{$minMin} <= {$acTime} <= {$plsMin}");
                         $a = date('H:i:s A', $minMin);
                         $b = date('H:i:s A', $acTime);
                         $c = date('H:i:s A', $plsMin);
                         echo '-------[CONDITION+'.$acTime.']------->'.$time;
                         dump("{$a} <= {$b} <= {$c}");
                         echo intval($mints). ' Status: of ['.$activity->id.']';
                         var_dump(($minMin <= $acTime) );
                         var_dump( ($acTime <= $plsMin));
                         var_dump(($minMin <= $acTime) && ($acTime <= $plsMin));
                         if(($minMin <= $acTime) && ($acTime <= $plsMin)){ 
                             $pushLogs = \DB::table('cron_push_logs')->where('notification_time', '=', $time)
                                    ->where('notification_date', '=', $todayDate)
                                    ->where('cron_type', '=', 'activity')
                                    ->where('activity_id', '=', $activity->id)
                                    ->count();
                            if($pushLogs > 0){
                                continue;
                            }
                            // dd('=======> found entry');
                            $user = \DB::table('users')
                                    ->where([
                                        ['is_deleted', '=', '0'],
                                        ['id', '=', $activity->user_id]
                                        ])->join('user_devices', 'users.id', '=', 'user_devices.user_id')->first();
                                if(!empty($user->fcm_token)){
                                $notificationTime = date('H:i:s A', $acTime);
                                 echo '<-------||[NOTIFICATION SENT @-'.$notificationTime.']||--------->';
                                    $pushNote = ['data' => ['title' => env('APP_NAME')." - {$activity->title} notification",
                                        'body' => "You are being notify for {$activity->type} {$activity->name} at {$notificationTime}", 
                                    'click_action' => 'ACTIVITY_NOTIFICATION_PUSH',
                                                'subTitle' => "Activity {$activity->type}"],
                                    'to' => $user->fcm_token, 'user_id' => $user->id, 'created_by' => $user->id];
                              
//                                    $this->pushFCMNotification($pushNote);
                                                $pushSender = $pushReceiver = get_object_vars($user);
                                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                                    // ==========> insert logs
                                    \DB::table('cron_push_logs')->insert(
                                        ['push_send_to' => $user->id, 'cron_type' => 'activity', 
                                            'activity_id' => $activity->id, 'notification_date' => date('Y-m-d'),
                                            'notification_time' => $time, 'push_send_at' => now()]
                                    );
                             }else{
                                 echo "<------------------|{$user->id} No fcm token|----------------->";
                             }
                     }
                     
                 }
             }
         }
         
//        dump($activities);
//        die;
     }
    
    }
    
    
    
    
}
