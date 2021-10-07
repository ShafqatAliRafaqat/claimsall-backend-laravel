<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model {

    use \Illuminate\Database\Eloquent\SoftDeletes,
        \App\Traits\FCM;

    protected $casts = [
        'relationship_id' => 'int',
        'user_id' => 'int',
        'latitude' => 'float',
        'longitude' => 'float',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $dates = [
        'appointment_date'
    ];
    protected $fillable = [
        'relationship_id',
        'user_id',
        'name',
        'appointment_date',
        'reminder_interval',
        'address',
        'latitude',
        'longitude',
        'description',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function relationship() {
        return $this->belongsTo(\App\Models\Relationship::class);
    }

    public function user() {
        return $this->belongsTo(\App\User::class);
    }

    public function getAppointments($request) {
        $post = $request->all();
        $loggedin_user = \Auth::user();
        $flag = $post['flag'];
        $user_id = !empty($post['user_id']) ? $post['user_id'] : null;
        if (!empty($user_id) && ($user_id != $loggedin_user->id)) {
            $associate_user = \App\Models\FamilyTree::checkAssociateUserRights($loggedin_user, ['associate_user_id' => $user_id]);
            if (empty($associate_user)) {
                return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 403];
            }
        }

        if (!empty($user_id)) { // user from FnF list
            $userID = $user_id;
        } else { // loggedin user
            $userID = $loggedin_user->id;
        }

        $viral_profiles = \App\Models\UserViralProfile::where(['user_id' => $userID])->get();
        $vp_ids_arr = [];
        if (count($viral_profiles) > 0) {
            foreach ($viral_profiles as $key => $viral_profile) {
                array_push($vp_ids_arr, $viral_profile->viral_profile_id);
            }
            array_push($vp_ids_arr, $userID);
        } else {
            array_push($vp_ids_arr, $userID);
        }

        $time = now();
        if ($flag == 1) { // upcoming
            $records = $this->whereIn('user_id', $vp_ids_arr)
                    ->where('appointment_date', '>', $time)
                    ->orderby('appointment_date', 'ASC')
                    ->paginate(10);
        } elseif ($flag == 2) { // previous
            $records = $this->whereIn('user_id', $vp_ids_arr)
                    ->where('appointment_date', '<', $time)
                    ->orderby('appointment_date', 'ASC')
                    ->paginate(10);
        } elseif ($flag == 3) { // all
            $records = $this->whereIn('user_id', $vp_ids_arr)
                    ->orderby('appointment_date', 'ASC')
                    ->paginate(10);
        } else { // current
            $records = $this->whereIn('user_id', $vp_ids_arr)
                    ->where('appointment_date', '>', $time)
                    ->where('appointment_date', '<', now()->tomorrow())
                    ->orderby('appointment_date', 'ASC')
                    ->paginate(10);
        }

        if (count($records) <= 0) {
            return ['status' => true, 'message' => 'You haven\'t scheduled any appointment reminder yet'];
        }
        return ['status' => true, 'message' => 'Got appointments reminders', 'data' => $records];
    }

    public function addAppointment($request) {
        $user = \Auth::user();
        $post = $request->all();
        $post['created_by'] = $user->id;
        if ($user->id != $post['user_id']) {
            $associate_user = \App\Models\FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['user_id']]);
            if (empty($associate_user)) {
                return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 403];
            }
            //send push
            $userName = getUserName($user);
//                     $apTime = 
            $notifyTime = date('d-m-Y h:i A', strtotime($post['appointment_date']));
            $pushNote = ['data' => ['title' => env('APP_NAME') . " - {$userName} has scheduled an appointment",
                    'body' => "{$userName} has scheduled an appointment with {$post['name']} at {$notifyTime}",
                    'click_action' => 'APPOINTMENT_SCH_PUSH',
                    'subTitle' => "Appointment Added"],
                'user_id' => $user->id, 'created_by' => $user->id];
            $pushSender = $user->toArray();
            $pushReceiver['id'] = $post['user_id'];
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);
        }

        if (\App\Models\Appointment::create($post)) {
            return ['status' => true, 'message' => 'Appointment reminder scheduled successfully!'];
        }
        return responseBuilder()->error('An error occured while assigning users');
    }

    public function updateAppointmentById($request, $id) {
        $user = \Auth::user();
        $post = $request->all();
        $post['updated_by'] = $user->id;
        $appointment = $this->where(['id' => $id])->first();
        if (!$appointment) {
            return ['status' => false, 'code' => 400, 'message' => 'Appointment reminder you want to edit not found'];
        }
        if ($user->id != $post['user_id']) {
            $associate_user = \App\Models\FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['user_id']]);
            if (empty($associate_user)) {
                return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 403];
            }
            //send push
            $userName = getUserName($user);
            $pushNote = ['data' => ['title' => env('APP_NAME') . " - {$userName} update your appointment",
                    'body' => "{$userName} has update your appointment: {$post['name']}",
                    'click_action' => 'APPOINTMENT_SCH_PUSH',
                    'subTitle' => "Appointment Updated"],
                'user_id' => $user->id, 'created_by' => $user->id];
            $pushSender = $user->toArray();
            $pushReceiver['id'] = $post['user_id'];
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);
        }

        if ($appointment->update($post)) {
            return ['status' => true, 'message' => 'Appointment reminder updated successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function deleteByUserAndId($id) {
        $user = \Auth::user();
        $appointment = $this->where(['id' => $id])->first();
        if (!$appointment) {
            return ['status' => false, 'message' => 'Sorry we did not find your record'];
        }
         if ($user->id != $appointment->user_id) {
            $associate_user = \App\Models\FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $appointment->user_id]);
            if (empty($associate_user)) {
                return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 403];
            }
            //send push
            $userName = getUserName($user);
            $pushNote = ['data' => ['title' => env('APP_NAME') . " - {$userName} delete your appointment",
                    'body' => "{$userName} has deleted your appointment: {$appointment->name}",
                    'click_action' => 'APPOINTMENT_SCH_PUSH',
                    'subTitle' => "Appointment Updated"],
                'user_id' => $user->id, 'created_by' => $user->id];
            $pushSender = $user->toArray();
            $pushReceiver['id'] = $appointment->user_id;
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);
        }
        
        $appointment->deleted_by = $user->id;
        $appointment->save();
        $appointment->delete();
        return ['status' => true, 'message' => 'Deleted successfully!'];
    }

}
