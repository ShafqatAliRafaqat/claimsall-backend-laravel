<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
	//use \Illuminate\Database\Eloquent\SoftDeletes;
        use \App\Traits\FCM;
	protected $table = 'activity';

	protected $casts = [
		'user_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $dates = [
		'start_date',
		'end_date'
	];

	protected $fillable = [
		'user_id',
		'title',
		'start_date',
		'end_date',
		'is_notify',
		'reminder_interval',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function user()
	{
		return $this->belongsTo(\App\User::class);
	}

	public function activity_data()
	{
		return $this->hasMany(\App\Models\ActivityData::class);
	}

	public function getActivities($post)
    {
    	$loggedin_user = \Auth::user();
    	$flag = $post['flag'];
        $user_id = !empty($post['user_id'])? $post['user_id']: null;
        if (!empty($user_id) && ($user_id != $loggedin_user->id)) {
			$associate_user = \App\Models\FamilyTree::checkAssociateUserRights($loggedin_user, ['associate_user_id' => $user_id]);
			if(empty($associate_user)) {
				return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 422];
			}
		}

		if (!empty($user_id)) { // user from FnF list
			$userID = $user_id;
		}
		else { // loggedin user
			$userID = $loggedin_user->id;
		}

		$viral_profiles = \App\Models\UserViralProfile::where(['user_id' => $userID])->get();
		$vp_ids_arr = [];
		if (count($viral_profiles) > 0) {
			foreach ($viral_profiles as $key => $viral_profile) {
				array_push($vp_ids_arr, $viral_profile->viral_profile_id);
			}
			array_push($vp_ids_arr, $userID);
		}
		else {
			array_push($vp_ids_arr, $userID);
		}

		//DB::connection()->enableQueryLog();

		//$time = now();
		$time = date('Y-m-d');
		//print_r($time);exit;
    	if ($flag == 1) { // upcoming
    		$records = $this->whereIn('user_id', $vp_ids_arr)
	        				->where('start_date', '>', $time)
	        				->where('end_date', '>', $time)
	        				->orderby('start_date', 'ASC')
	        				->paginate(10);
    	}
    	elseif ($flag == 2) { // previous
    		$records = $this->whereIn('user_id', $vp_ids_arr)
    						->where('start_date', '<', $time)
	        				->where('end_date', '<', $time)
	        				->orderby('start_date', 'ASC')
	        				->paginate(10);
	        //echo '<pre>';print_r($records);exit;
    	}
    	elseif ($flag == 3) { //all
	        $records = $this->whereIn('user_id', $vp_ids_arr)
	        				->orderby('start_date', 'ASC')
	        				->paginate(10);
    	}
    	else { // current
	        $records = $this->whereIn('user_id', $vp_ids_arr)
	        				->where('start_date', '<=', $time)
	        				->where('end_date', '>=', $time)
	        				->orderby('start_date', 'ASC')
	        				->paginate(10);
    	}

    	//return DB::getQueryLog();

        foreach ($records as $key => $record) {
        	$record['activity_data'] = $record->activity_data;
        	foreach ($record['activity_data'] as $key => $value) {
        		$value->frequency_timing = unserialize($value->frequency_timing);
        	}
        }
        if(count($records)<=0) {
            return ['status' => true, 'message' => 'You haven\'t scheduled any activity yet' ];
        }
        return ['status' => true, 'message' => 'Got activities', 'data' => $records];
    }

	public function addActivity($request)
	{
		$user = \Auth::user();
		$post = $request->all();
		$post['created_by'] = $user->id;
                $post['user_id'] = (!empty($post['user_id'])) ? $post['user_id'] : $user->id;
		if ($user->id != $post['user_id']) {
			$associate_user = \App\Models\FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['user_id']]);
			if(empty($associate_user)) {
				return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 403];
			}
                    $userName = getUserName($user);
                    $pushNote = ['data' => ['title' => env('APP_NAME') . " - {$userName} has scheduled an activity for you",
                                        'body' => "{$userName} has scheduled an activity: {$post['title']} starting {$post['start_date']} to {$post['end_date']}.",
                                        'click_action' => 'ACTIVITY_SCH_PUSH',
                                        'subTitle' => "Activity Added"],
                                    'user_id' => $user->id, 'created_by' => $user->id];
                                $pushSender = $user->toArray();
                                $pushReceiver['id'] = $post['user_id'];
                               
                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);       
		}
		
		$activity = \App\Models\Activity::create($post);
		$activityData = [];
		if(!empty($post['medicines'])) {
			foreach ($post['medicines'] as $key => $medicine) {
	            $time = now();
	            if(!empty($medicine['name'])) {
	            	$activityData[] = [
		                'activity_id'		=> $activity->id,
		                'name' 				=> $medicine['name'],
		                'dosage'			=> $medicine['dosage'],
		                'frequency' 		=> $medicine['frequency'],
		                'frequency_timing'	=> serialize($medicine['frequency_timing']),
		                'created_at' 		=> $time,
		                'created_by'		=> $user->id
		            ];
	            }
	        }
		}
        if(\App\Models\ActivityData::insert($activityData)) {
            return ['status' => true, 'message' => 'Activity is scheduled successfully!'];
        }
        return responseBuilder()->error('An error occured while assigning users'); 
    }

    public function updateActivityById($request, $id)
	{
        $user = \Auth::user();
		$post = $request->all();
        $post['updated_by'] = $user->id;
        $activity = $this->where(['id' =>$id])->first();
        if(!$activity) {
            return ['status' => false, 'code' => 400, 'message' => 'The activity you want to edit is not found'];
        }
        $post['user_id'] = (!empty($post['user_id'])) ? $post['user_id'] : $user->id;
        if ($user->id != $post['user_id']) {
			$associate_user = \App\Models\FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['user_id']]);
			if(empty($associate_user)) {
				return ['status' => false, 'message' => 'Sorry, this user is not in your family tree', 'code' => 403];
			}
                        
                    $userName = getUserName($user);
                    $pushNote = ['data' => ['title' => env('APP_NAME') . " - {$userName} has edit your activity",
                                        'body' => "{$userName} has edit your activity: {$post['title']} starting {$post['start_date']} to {$post['end_date']}.",
                                        'click_action' => 'ACTIVITY_SCH_PUSH',
                                        'subTitle' => "Activity updated"],
                                    'user_id' => $user->id, 'created_by' => $user->id];
                                $pushSender = $user->toArray();
                                $pushReceiver['id'] = $post['user_id'];
                               
                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);        
		}
			
        if($activity->update($post)) {
        	\App\Models\ActivityData::where('activity_id', $activity->id)->delete();
        	$activityData = [];
			if(!empty($post['medicines'])) {
				foreach ($post['medicines'] as $key => $medicine) {
		            $time = now();
		            if(!empty($medicine['name'])) {
		            	$activityData[] = [
			                'activity_id'	=> $activity->id,
			                'name' 			=> $medicine['name'],
			                'dosage'		=> $medicine['dosage'],
			                'frequency' 	=> $medicine['frequency'],
			                'frequency_timing'	=> serialize($medicine['frequency_timing']),
			                'created_at' 	=> $time,
			                'created_by'	=> $user->id
			            ];
		            }
		        }
			}
	        \App\Models\ActivityData::insert($activityData);
            return ['status' => true, 'message' => 'Activity updated successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function deleteByUserAndId($id)
    {
		$user = \Auth::user();
        $activity = $this->where(['id' => $id])->first();
        if(!$activity) {
            return ['status' => false, 'message' => 'Sorry we did not find your record'];
        }
        if($user->id != $activity->user_id){
            $userName = getUserName($user);
                    $pushNote = ['data' => ['title' => env('APP_NAME') . " - {$userName} has deleted your activity",
                                        'body' => "{$userName} has deleted your activity: {$activity['title']}.",
                                        'click_action' => 'ACTIVITY_SCH_PUSH',
                                        'subTitle' => "Activity deleted"],
                                    'user_id' => $user->id, 'created_by' => $user->id];
                                $pushSender = $user->toArray();
                                $pushReceiver['id'] = $activity['user_id'];
                               
                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);     
        }
        $activity->delete();
        return ['status'=> true, 'message' => 'Deleted successfully!'];
    }
}
