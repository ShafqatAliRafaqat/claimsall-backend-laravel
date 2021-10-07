<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\NotificationHistory;

class NotificationController extends Controller
{
    use \App\Traits\WebServicesDoc;
    
    public function index()
    {
        $user = \Auth::user();
//        dump($user->notifications_history()->paginate(10));
        $notifications = $user->unseen_notifications_history()->orderBy('id', 'DESC')->paginate(1000);
        $notifications = $notifications->toArray();
        if(count($notifications['data'])>0){
            $data = [];
            foreach ($notifications['data'] as $notification) {
                $data[] = ['content' => unserialize($notification['content']), 'id' => $notification['id'], 'created_at' => $notification['created_at']];
            }
            $notifications['data'] = $data;
        }
        $response = responseBuilder()->success('User Notifications List', $notifications, false);
        $this->urlComponents('User Notification List', $response, 'User_Management');
        return $response;
    }

    public function update(Request $request, $id)
    {
        $request->validate(['is_seen' => 'required']);
        $notificationHistory = NotificationHistory::findOrFail($id);
        $notificationHistory->update($request->only(['content', 'is_seen']));
        $response = responseBuilder()->success('Notifications status updated successfully', [], false);
        $this->urlComponents('Notification Update call', $response, 'User_Management');
        return $response;
    }
}
