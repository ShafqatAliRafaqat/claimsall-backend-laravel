<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	private $authUser = null;

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
        'organization_id',
		'title',
        'code',
		'description',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	public function modules()
	{
		return $this->belongsToMany(\App\Models\Module::class)
					->withPivot('create', 'edit', 'view', 'report', 'approved', 'reject');
	}

	public function users()
	{
		return $this->belongsToMany(\App\User::class);
	}

	public function addRole($request)
	{
		$user = \Auth::user();
		$post = $request->all();
		$post['created_by'] = $user->id;
		$this->fill($post);
		if($this->save()) {
            return ['status' => true, 'message' => 'Role added successfully!'];
        }
    }

    public function updateRoleById($request, $id)
    {
		$user = \Auth::user();
		$post = $request->all();
        $post['updated_by'] = $user->id;
        $role = $this->where(['is_deleted'=>'0', 'id' =>$id])->first();
        if (!$role) {
            return ['status' => false, 'code' => 400, 'message' => 'The role you want to edit is not found'];
        }
        if ($role->update($post)) {
            return ['status' => true, 'message' => 'The role is updated successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function getRoles()
    {
        $user = \Auth::user();
        $org_data = \App\Models\Organization::userOrganization($user, false);
        if (!empty($org_data)) {
            $user_organization_id = $org_data->organization_user[0]->organization_id;
            //$records = $this->where(['is_deleted'=>'0'])->paginate(10);
            $records = $this->where(['organization_id' => $user_organization_id])->get();
            if(count($records)<=0) {
                return ['status' => true, 'message' => 'You haven\'t added any roles yet' ];
            }
            return ['status' => true, 'message' => 'Got roles', 'data' => $records];
        }
        else {
            return ['status' => false, 'code' => 422, 'message' => 'This logged in user does not have a default organization'];
        }
    }

    public function deleteByUserAndId($id)
    {
		$user = \Auth::user();
        $role = $this->where(['is_deleted' => '0', 'id' => $id])->first();
        if(!$role) {
            return ['status' => false, 'message' => 'Sorry, we did not find your record'];
        }

        $role_user = \App\Models\RoleUser::where('role_id', $role->id)->first();
        if (!empty($role_user)) {
            return ['status' => false, 'code' => 422, 'message' => 'You cannot delete this role as it has been assigned to some users'];
        }

        $approval_process = \App\Models\PolicyApprovalProcess::where('role_id', $role->id)->first();
        if (!empty($approval_process)) {
            return ['status' => false, 'code' => 422, 'message' => 'You cannot delete this role as it is part of policy approval process for claims'];
        }

        $role->is_deleted = '1';
        $role->deleted_by = $user->id;

        $date = date("D M d, Y G:i");
        $today = strtotime($date);
        $updateFields = ['title', 'code'];
        foreach($updateFields as $updateField){
            if(!empty($role->$updateField)){
                $role->$updateField = $role->$updateField.'_'.$today;  
            }
        }

        $role->save();
        $role->delete();
        return ['status'=> true, 'message' => 'Deleted successfully!'];
    }
    
    
    public static function getRoleID($code, $where=['id']) {
        return self::select($where)->where(['code'=> $code, 'is_deleted' => '0'])->whereNull('organization_id')->first();
    }
}
