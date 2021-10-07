<?php

namespace App\Traits;

trait FCM {

    public function pushFCMNotification($fields, $debug= false) {

        $headers = array(
            env('FCM_URL'),
            'Content-Type: application/json',
            'Authorization: key=' . env('FCM_SERVER_KEY')
        );
        $userId = $loggedInId = null;
        if(!empty($fields['created_by'])){
            $loggedInId = $fields['created_by'];
            unset($fields['created_by']);
        }
        if(!empty($fields['user_id'])){
            $userId = $fields['user_id'];
            unset($fields['user_id']);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('FCM_URL'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        if($debug===true){
            dump($result);
        }
        if(!empty($fields['data']['push_notification_id'])){
            $notificationHistory = \App\Models\NotificationHistory::find($fields['data']['push_notification_id']);
            $notificationHistory->update(['fcm_response' => serialize($result), 'content' =>  serialize($fields)]);
        }elseif(!empty($userId)){
            \App\Models\NotificationHistory::create(['user_id' => $userId, 'content' => serialize($fields),
                'fcm_response' => serialize($result), 'created_by'=> $loggedInId, 'updated_by' => $loggedInId]);
        }
        //logger('=========FCM RESULT============', [$result]);
        curl_close($ch);
        if ($result === FALSE) {
            return 0;
        }
        $res = json_decode($result);
        //logger('=========FCM============', [$res]);
        if (isset($res->success)) {
            return $res->success;
        } else {
            return 0;
        }
    }
    
    public function sendNotification($pushReceiver, $pushSender, $content) {
        $userDevice = \App\Models\UserDevice::select('fcm_token')->where('user_id', $pushReceiver['id'])->first();
        $notificationHistory =\App\Models\NotificationHistory::create(['user_id' => $pushReceiver['id'], 'content' => serialize($content),
                 'created_by'=> $pushSender['id'], 'updated_by' => $pushSender['id']]);
        if(!empty($userDevice)){
            $content['to'] = $userDevice->fcm_token;
            $content['data']['push_notification_id'] = $notificationHistory->id;
            return $this->pushFCMNotification($content);
        }
    }

}
