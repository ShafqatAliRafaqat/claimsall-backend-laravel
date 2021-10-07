<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Appointment;

//use App\Models\ActivityData;

class AppointmentController extends Controller {

    use \App\Traits\WebServicesDoc,        \App\Traits\FCM;

    public function getAppointments(Request $request) {
        $appointment = new Appointment();
        $data = $appointment->getAppointments($request);
        if (isset($data['status']) && $data['status'] === true) {
            $res = (isset($data['data'])) ? $data['data'] : [];
            $response = responseBuilder()->success($data['message'], $res, false);
            $this->urlComponents('List of Appointment Reminders by loggedin User', $response, 'Appointment_Reminder_Management');
            return $response;
        }
        return responseBuilder()->error($data['message'], $data['code']);
    }

    public function store(\App\Http\Requests\Appointment $request) {
        $appointment = new Appointment();
        $res = $appointment->addAppointment($request);
        if (isset($res['status']) && $res['status'] === true) {
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Set Appointment Reminder by loggedin User', $response, 'Appointment_Reminder_Management');
            return $response;
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }

    public function update(\App\Http\Requests\Appointment $request, $id) {
        $appointment = new Appointment();
        $res = $appointment->updateAppointmentById($request, $id);
        if (isset($res['status']) && $res['status'] === true) {
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Update An Already Set Appointment Reminder by loggedin User', $response, 'Appointment_Reminder_Management');
            return $response;
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }

    public function destroy($id) {
        $appointment = new Appointment();
        $res = $appointment->deleteByUserAndId($id);
        if ($res['status'] === true) {
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Update An Already Set Appointment Reminder by loggedin User', $response, 'Appointment_Reminder_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }

    public function appointmentCron() {
//        \DB::enableQueryLog();
//         $currentTime = now();
        $todayDate = date('Y-m-d H:i:s');
        $currentTimeFromNext2Hrs = time() + (2 * 3600);
        $todayDateNew = date('Y-m-d H:i:s', $currentTimeFromNext2Hrs);

        echo '-------[CRONE_RUN_AT]-------';
        dump($todayDate);
        echo '--------------';
        $activities = \DB::table('appointments')
                ->where('appointment_date', '>=', $todayDate)
                ->where('appointment_date', '<', $todayDateNew)
                ->where('is_notify', 'Y')
                ->get();
        if (!empty($activities)) {
            foreach ($activities as $key => $activity) {
                if (!empty($activity->appointment_date)) {
                    if (!empty($activity->reminder_interval)) {
//                        $interval = (intval($activity->reminder_interval) + (3));
                        $interval = (intval($activity->reminder_interval) + (3));
                        $appointTimeU = strtotime($activity->appointment_date);
                        $interval = $interval*60;
                        $appintmentTimeTo = $appointTimeU - $interval;
                        $appintmentTimeFrom = $appointTimeU + $interval;

                        echo 'To: =' . $interval;
                        dump(date('Y-m-d H:i:s', $appintmentTimeTo));
                        echo 'From: = ';
                        dump(date('Y-m-d H:i:s', $appintmentTimeFrom));
                        echo $activity->name . ' <==> Current:';
                        dump(date('Y-m-d H:i:s'));
                        //$cTime = time();
                        dump(($appintmentTimeTo <= time()) && (time() <= $appintmentTimeFrom));
                        if (($appintmentTimeTo <= time()) && (time() <= $appintmentTimeFrom)) {
                            $time = date('h:i:s', $appointTimeU);
//                            \DB::enableQueryLog();
                            $pushLogs = \DB::table('cron_push_logs')->where('notification_time', '=', $time)
                                    ->where('notification_date', '=', date('Y-m-d'))
                                    ->where('cron_type', '=', 'appointment')
                                    ->where('activity_id', '=', $activity->id)
                                    ->count();

                            if($pushLogs > 0){
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
                                        $pushNote = ['data' => ['title' => env('APP_NAME') . " - Appointment for {$activity->name} notification",
                                                'body' => "You are being notify for appointment with {$activity->name} at {$notificationTime}",
                                                'click_action' => 'APPOINTMENT_NOTIFICATION_PUSH',
                                                'subTitle' => "Appointment Notification"],
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
