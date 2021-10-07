<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareService extends Model
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	//
	use \Illuminate\Foundation\Auth\SendsPasswordResetEmails,
        \App\Traits\EMails;

	//protected $table = 'care_services';
	private $authUser = null;

	protected $casts = [
		'care_services_type_id' => 'int',
		'user_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $dates = [
		//'start_date',
		//'start_time'
	];

	
	protected $fillable = [
		'care_services_type_id',
		'name',
		'contact_number',
		'address',
		'gender',
		'email',
		'preference',
        'preference_email',
		'start_date',
		'start_time',
		'description',
		'status',
        'feedback',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

        
	public function care_type_services()
	{
        // here we passed care_services_type_id manually as by default the relation was trying to find care_type_services_id but in this case its a bit different as care_services_type_id
		return $this->belongsTo(\App\Models\CareTypeService::class, 'care_services_type_id');
	}

	public function user()
	{
		return $this->belongsTo(\App\Models\User::class);
	}
	public function modules()
	{
		return $this->belongsToMany(\App\Models\Module::class)
					->withPivot('create', 'edit', 'view', 'report', 'approved', 'reject');
	}

	public function users()
	{
		return $this->belongsToMany(\App\User::class);
	}

    //test
    public function documents() {
        return $this->hasMany(\App\Models\Document::class, 'careservice_id');
    }

	public function addCareService($request)
	{
		$user = \Auth::user();
		$post = $request->all();
		extract($post);
		$CareService = [
                        'care_services_type_id'	=> $care_services_type_id, 
                        'name'  				=> $name,
                        'contact_number'       	=> !empty($contact_number)? $contact_number: null,
                        'address'          		=> $address,
                        'email'            		=> $email,
                        'preference'        	=> !empty($preference)? $preference: null,
                        'preference_email'      => !empty($preference_email)? $preference_email: null,
                        'start_date'			=> $start_date,
                        'start_time'            => $start_time,
                        'description'        	=> $description,
                        'created_by'			=> $user->id
                    ];
        $care_service_obj = CareService::create($CareService);
        $care_service_type_name = $care_service_obj->care_type_services->name;
        if(!empty($documents)) {
        	foreach ($documents as $key => $document) {
        		$id = $document['document_id'];
        		$notes = !empty($document['notes'])? $document['notes']: null;
            	Document::where(['id' => $id])->update([
            											'careservice_id' => $care_service_obj->id,
            											'notes'			 => $notes
            										]);
            }
        }

        $superAdmin = \App\User::getSuperAdmin();
        if (empty($care_service_obj->preference)) {
            $this->sendMail([
                            'receiver_email'         => $superAdmin->email,
                            'receiver_name'          => $superAdmin->first_name.' '.$superAdmin->last_name,
                            'sender_name'            => env('APP_NAME'),
                            'care_service_type_name' => $care_service_type_name,
                            'care_service_id'        => $care_service_obj->id,
                            'start_date'             => $care_service_obj->start_date,
                            'start_time'             => $care_service_obj->start_time,
                            'name'                   => $care_service_obj->name,
                            'contact_number'         => $care_service_obj->contact_number,
                            'address'                => $care_service_obj->address,
                            'description'            => $care_service_obj->description,
                            'cc_flag'                => false,
                            'cc_requester_flag'      => true,
                            'cc_requester_email'     => $email
                        ], 'Care Service Order', 'emails.careservice');
        }
        else {
            $this->sendMail([
                            'receiver_email'         => $care_service_obj->preference_email,
                            'receiver_name'          => $care_service_obj->preference,
                            'sender_name'            => env('APP_NAME'),
                            'care_service_type_name' => $care_service_type_name,
                            'care_service_id'        => $care_service_obj->id,
                            'start_date'             => $care_service_obj->start_date,
                            'start_time'             => $care_service_obj->start_time,
                            'name'                   => $care_service_obj->name,
                            'contact_number'         => $care_service_obj->contact_number,
                            'address'                => $care_service_obj->address,
                            'description'            => $care_service_obj->description,
                            'cc_flag'                => true,
                            'cc_email'               => $superAdmin->email,
                            'cc_name'                => $superAdmin->first_name.' '.$superAdmin->last_name,
                            'cc_requester_flag'      => true,
                            'cc_requester_email'     => $email
                        ], 'Care Service Order', 'emails.careservice');
        }

        

        return ['status' => true, 'message' => 'Your request for Service has been forwarded'];
    }

    public function updateCareServiceById($request, $id)
    {
		$user = \Auth::user();
		$post = $request->all();
        $post['updated_by'] = $user->id;
        $CareService = $this->where(['is_deleted'=>'0', 'id' =>$id])->first();
        if (!$CareService) {
            return ['status' => false, 'code' => 400, 'message' => 'CareService you want to edit not found'];
        }
        if ($CareService->update($post)) {
            return ['status' => true, 'message' => 'CareService updated successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function getCareServices()
    {
		$user = \Auth::user();
        $records = $this->paginate(10);
        if(count($records)<=0) {
            return ['status' => true, 'message' => 'You haven\'t added any Care Service yet' ];
        }
        return ['status' => true, 'message' => 'Got CareServices', 'data' => $records];
    }

    public function deleteByUserAndId($id)
    {
		$user = \Auth::user();
        $CareService = $this->where(['is_deleted' => '0', 'id' => $id])->first();
        if(!$CareService) {
            return ['status' => false, 'message' => 'Sorry, we did not find your record'];
        }
        $CareService->is_deleted = '1';
        $CareService->deleted_by = $user->id;
        $CareService->save();
        $CareService->delete();
        return ['status'=> true, 'message' => 'Deleted successfully!'];
    }
}
