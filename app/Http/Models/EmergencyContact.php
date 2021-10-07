<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
	//use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'user_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'phone_number',
		'user_id',
		'is_default',
		'message',
		'is_content',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function user()
	{
		return $this->belongsTo(\App\Models\User::class);
	}

    public function manageEmergencyContact($request)
	{
		$user = \Auth::user();
		$post = $request->all();
		$user_id = $post['user_id'];
        if(!empty($post['contacts'])){
            $contacts = array_column($post['contacts'], 'phone_number');
            if(in_array($user->contact_number, $contacts)){
                return ['status' => false, 'code' => 400, 'message' => 'You cannot add your personal number as emergency contact.'];
            }
        }
		// is_default equals 1 means this is a default number
		foreach ($post['contacts'] as $key => $value) {
			if (!isset($value['id']) && empty($value['id'])) {
				if (isset($value['phone_number'])) {
					$emergency_contact = $this->where([
												'user_id' 			=> $user_id,
												'phone_number' 		=> $value['phone_number']
											])
										  ->first();
					$emergency_contacts_count = $this->where(['user_id' => $user_id])
													 ->get()
													 ->count();
					if ($emergency_contacts_count >= 5) {
						return ['status' => false, 'code' => 400, 'message' => 'Emergency Contacts limit exceeded'];
					}
					$emergency_contact = new EmergencyContact();
					$emergency_contact['user_id'] = $user_id;
					$emergency_contact['phone_number'] = $value['phone_number'];
					$emergency_contact['is_default'] = $value['is_default'];
					$emergency_contact->save();
				}
				else {
					$emergency_contact = $this->where([
												'user_id' 			=> $user_id,
												'message' 		=> $value['message']
											])
										  ->first();
					$emergency_contacts_count = $this->where(['user_id' => $user_id])
													 ->get()
													 ->count();
					if ($emergency_contacts_count >= 5) {
						return ['status' => false, 'code' => 400, 'message' => 'Emergency Contacts limit exceeded'];
					}
					$emergency_contact = new EmergencyContact();
					$emergency_contact['user_id'] = $user_id;
					$emergency_contact['message'] = $value['message'];
					$emergency_contact['is_content'] = $value['is_content'];
					$emergency_contact->save();
				}
			}
			else {
				if (isset($value['phone_number']) && !empty($value['phone_number'])) {
					$emergency_contact = $this->where(['id' => $value['id']])->first();
					$emergency_contact['phone_number'] = $value['phone_number'];
		        	$emergency_contact['is_default'] = $value['is_default'];
		        	$emergency_contact->save();
				}
				else {
					if (isset($value['message']) && !empty($value['message'])) {
						$emergency_contact = $this->where(['id' => $value['id']])->first();
						$emergency_contact['message'] = $value['message'];
			        	$emergency_contact['is_content'] = $value['is_content'];
			        	$emergency_contact->save();
					}
					else {
						$this->where(['id' => $value['id']])->delete();
					}
				}
			}	
		}
		$emergency_contacts = $this->select(['id', 'phone_number', 'is_default', 'message', 'is_content'])
								   ->where(['user_id' => $user_id])
								   ->get();
        return ['status' => true, 'message' => 'Got emergency contacts', 'data' => $emergency_contacts];
    }

    public function getEmergencyContacts()
    {
		$user = \Auth::user();
        $records = $this->select(['id', 'phone_number', 'is_default', 'message', 'is_content'])
						->where(['user_id' => $user->id])
						->get();
        if(count($records)<=0) {
            return ['status' => true, 'message' => 'You haven\'t added any emergency contacts yet' ];
        }
        return ['status' => true, 'message' => 'Got emergency contatcs', 'data' => $records];
    }
}
