<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppointmentPushNotifications extends Command {

    use \App\Traits\FCM;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AppointmentPushNotifications:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for run job for look up up comming appointments and send push notification for concen user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $todayDate = date('Y-m-d H:i:s');
        $currentTimeFromNext2Hrs = time() + (2 * 3600);
        $todayDateNew = date('Y-m-d H:i:s', $currentTimeFromNext2Hrs);

        $activities = \DB::table('appointments')
                ->where('appointment_date', '>=', $todayDate)
                ->where('appointment_date', '<', $todayDateNew)
                ->where('is_notify', 'Y')
                ->whereNull('deleted_at')
                ->get();

        if (!empty($activities)) {
            foreach ($activities as $key => $activity) {
                if (!empty($activity->appointment_date)) {
                    if (!empty($activity->reminder_interval)) {
//                        $interval = (intval($activity->reminder_interval) + (3));
                        $interval = (intval($activity->reminder_interval));
                        $appointTimeU = strtotime($activity->appointment_date);
                        $interval = $interval * 60;
                        $appintmentTimeTo = $appointTimeU - $interval;
                        $appintmentTimeFrom = $appointTimeU + $interval;
                        \Log::info($appintmentTimeTo . " ====>Successful Sent APPOINTMENT Push @" . $appintmentTimeFrom);
                        if (($appintmentTimeTo <= time()) && (time() <= $appintmentTimeFrom)) {
                            \Log::info($appintmentTimeTo . " ====>{" . time() . "}" . $appintmentTimeFrom);
                            $time = date('h:i:s', $appointTimeU);
//                            \DB::enableQueryLog();
                            $pushLogs = \DB::table('cron_push_logs')->where('notification_time', '=', $time)
                                    ->where('notification_date', '=', date('Y-m-d'))
                                    ->where('cron_type', '=', 'appointment')
                                    ->where('activity_id', '=', $activity->id)
                                    ->count();
                            \Log::info(" ====>COUNT=" . $pushLogs);
//                            $queries = \DB::getQueryLog();
//                            dump($queries);
//                            dump($pushLogs);
//                            die;
                            if ($pushLogs > 0) {
                                continue;
                            }

                            $users = \DB::table('users')
                                            ->where([
                                                ['is_deleted', '=', '0']
                                            ])->whereIn('id', [$activity->user_id, $activity->created_by])
                                            ->join('user_devices', 'users.id', '=', 'user_devices.user_id')->get();
                            if (!empty($users)) {
                                foreach ($users as $user) {
                                    if (!empty($user->fcm_token)) {
                                        $notificationTime = date('h:i A', $appointTimeU);
                                        $pushNote = ['data' => ['title' => "Appointment Alert",
                                                'body' => "You are being notified for appointment with {$activity->name} at {$notificationTime}",
                                                'click_action' => 'APPOINTMENT_NOTIFICATION_PUSH',
                                                'subTitle' => "Appointment Alert"],
                                            'to' => $user->fcm_token, 'user_id' => $user->id, 'created_by' => $user->id];
                                        $pushSender = $pushReceiver = get_object_vars($user);
                                        $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                                        // ==========> insert logs
                                        \DB::table('cron_push_logs')->insert(
                                                ['push_send_to' => $user->id, 'cron_type' => 'appointment',
                                                    'activity_id' => $activity->id, 'notification_date' => date('Y-m-d'),
                                                    'notification_time' => $time, 'push_send_at' => now()]
                                        );
                                        \Log::info("{$user->id} ====>Successful Sent Appointment Push @" . now());
                                    }
                                }
                            }                            // endif
                            
                        }
                    }
                }
            }
        }
    }

}
