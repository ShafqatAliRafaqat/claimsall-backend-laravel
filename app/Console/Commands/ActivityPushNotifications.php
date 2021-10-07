<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ActivityPushNotifications extends Command
{
    use \App\Traits\FCM;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ActivityPushNotifications:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send push notification for sechedualing activity users.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
         $todayDate = date('Y-m-d');
         $nowTime = time();
//         echo '-------[CRONE_RUN_AT]-------';
//         dump(date('Y-m-d H:i:s',$nowTime));
//         echo '--------------';
         $activities = \DB::table('activity')
                 ->select('activity.*', 'activity_data.*', 'activity_data.created_at as ad_create_at')
                 ->where('activity.start_date', '<=', $todayDate)
                 ->where('activity.end_date', '>=', $todayDate)
                 ->whereNull('activity.deleted_at')
                 ->where('activity.is_notify', 'Y')
                 ->join('activity_data', 'activity.id', '=', 'activity_data.activity_id')
                 ->get();

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
//                         dump("{$minMin} <= {$acTime} <= {$plsMin}");
                         $a = date('H:i:s A', $minMin);
                         $b = date('H:i:s A', $acTime);
                         $c = date('H:i:s A', $plsMin);
//                         echo '-------[CONDITION+'.$acTime.']------->'.$time;
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
                                $notificationTime = date('h:i A', $acTime);
//                                 echo '<-------||[NOTIFICATION SENT @-'.$notificationTime.']||--------->';
                                    $pushNote = ['data' => ['title' => "Activity Alert",
                                        'body' => "You are being notified for {$activity->name} at {$notificationTime}", 
                                    'click_action' => 'ACTIVITY_NOTIFICATION_PUSH',
                                                'subTitle' => "Activity Alert"],
                                    'to' => $user->fcm_token, 'user_id' => $user->id, 'created_by' => $user->id];
                                    //$this->pushFCMNotification($pushNote);
                                    $pushSender = $pushReceiver = $pushSender = $pushReceiver = get_object_vars($user);
                                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                                    \Log::info("{$user->id} ====>Successful Sent Push @".now());
                                    // ==========> insert logs
                                    \DB::table('cron_push_logs')->insert(
                                        ['push_send_to' => $user->id, 'cron_type' => 'activity', 
                                            'activity_id' => $activity->id, 'notification_date' => date('Y-m-d'),
                                            'notification_time' => $time, 'push_send_at' => now()]
                                    );
                             }
                     }
                     
                 }
             }
         }
         $this->info('Cron [ '.date('Y-m-d H:i:s',$nowTime).' ] Run successfully!');
     }
    
    }
}
