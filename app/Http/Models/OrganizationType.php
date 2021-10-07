<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationType extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'organization_type';

    private $authUser = null;

    protected $casts = [
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];

    protected $fillable = [
        'name',
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

    public function addOrganizationType($request)
    {
        $user = \Auth::user();
        $post = $request->all();
        $post['created_by'] = $user->id;
        $this->fill($post);
        if($this->save()) {
            return ['status' => true, 'message' => 'OrganizationType added successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function updateOrganizationTypeById($request, $id)
    {
        $user = \Auth::user();
        $post = $request->all();
        $post['updated_by'] = $user->id;
        $OrganizationType = $this->where(['is_deleted'=>'0', 'id' =>$id])->first();
        if (!$OrganizationType) {
            return ['status' => false, 'code' => 400, 'message' => 'OrganizationType you want to edit not found'];
        }
        if ($OrganizationType->update($post)) {
            return ['status' => true, 'message' => 'OrganizationType updated successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function getSpecificIndustries($request)
    {
        $user = \Auth::user();
        $post = $request->all();
        $code = !empty($post['code'])? $post['code'] :null;
        $records = \App\Models\OrganizationType::query();
        if(!empty($code)) {
            $records = $records->where('code', $code);
        }
        $records = $records->where(['is_deleted'=>'0']);
        $records = $records->get();
        if(count($records) <= 0) {
            return ['status' => true, 'message' => 'You haven\'t added any OrganizationTypes yet' ];
        }
        return ['status' => true, 'message' => 'Got OrganizationTypes', 'data' => $records];
    }

    public function getOrganizationTypes()
    {
        $user = \Auth::user();
        $records = $this->where(['is_deleted'=>'0'])->get();
        if(count($records)<=0) {
            return ['status' => true, 'message' => 'You haven\'t added any OrganizationTypes yet' ];
        }
        return ['status' => true, 'message' => 'Got OrganizationTypes', 'data' => $records];
    }

    public function deleteByUserAndId($id)
    {
        $user = \Auth::user();
        $role = $this->where(['is_deleted' => '0', 'id' => $id])->first();
        if(!$role) {
            return ['status' => false, 'message' => 'Sorry we did not find your record'];
        }
        $role->is_deleted = '1';
        $role->deleted_by = $user->id;
        $role->save();
        $role->delete();
        return ['status'=> true, 'message' => 'Deleted successfully!'];
    }
}
