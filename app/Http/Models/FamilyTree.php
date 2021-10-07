<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use App\User;

class FamilyTree extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'family_tree';
    protected $casts = [
        'relationship_id' => 'int',
        'parent_user_id' => 'int',
        'associate_user_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'title',
        'description',
        'relationship_id',
        'parent_user_id',
        'associate_user_id',
        'shared_profile',
        'read_medical_record',
        'write_medical_record',
        'read_health_care',
        'assc_shared_profile',
        'assc_read_medical_record',
        'assc_write_medical_record',
        'assc_relationship_id',
        'status',
        'is_viral',
        'is_claimed',
        'is_deleted',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function user() {
        return $this->belongsTo(\App\User::class, 'parent_user_id');
    }

    public function fnf_user() {
        return $this->belongsTo(\App\User::class, 'associate_user_id');
    }

    public function relationship() {
        return $this->belongsTo(\App\Models\Relationship::class, 'relationship_id');
    }
    public function assc_relationship() {
        return $this->belongsTo(\App\Models\Relationship::class, 'assc_relationship_id');
    }

    public static function checkAssociateUserRights($user, $data) {
        $whereClause = $data;
        $whereClause['parent_user_id'] = $user->id;
        $whereClause['status'] = 'Approved';
        $whereClause['is_claimed'] = 'N';
        $inverseCluase['associate_user_id'] = $user->id;
        $inverseCluase['parent_user_id'] = $data['associate_user_id'];
        //        if(!empty($data['shared_profile'])){ $inverseCluase['assc_shared_profile']=$data['shared_profile'];}
        //        if(!empty($data['read_medical_record'])){ $inverseCluase['assc_read_medical_record']=$data['read_medical_record'];}
        //        if(!empty($data['write_medical_record'])){ $inverseCluase['assc_write_medical_record']=$data['write_medical_record'];}
            return self::where($whereClause)
                            ->orWhere(function($query)use($inverseCluase){
                                            $query->where($inverseCluase);
                                        })
                                        ->first();
        }
    
    public static function getFNFuserIdsByUserId($userId, $huids = false, $permission = []) {
        $fnfUsers = self::where(['parent_user_id' => $userId, 'is_claimed' => 'N', 'status' => 'Approved'])
                ->orWhere(function($fnfQuery) use($userId){
                    $fnfQuery->where(['associate_user_id' => $userId, 'is_claimed' => 'N', 'status' => 'Approved']);
                })->get();
        $r['user_ids']= [];
        if($huids){
            $r['huids'] = [];
        } 
        $permisssions = array_intersect($permission, ['shared_profile', 'read_medical_record', 'write_medical_record']);
        $ids = [];
        $fnfIds = $fnfUsers->toArray();
        if(empty($fnfIds)){
            return $r;
        }
        foreach ($fnfIds as $val) {
            if(empty($permisssions)){
                $ids[] = $val['parent_user_id'];
                $ids[] = $val['associate_user_id'];
                continue;
            }
            foreach($permisssions as $perm){
                if($val[$perm] == 'Y'){
                    $ids[] = $val['parent_user_id'];
                }
                $key = 'assc_'.$perm;
                if($val[$key] == 'Y'){
                    $ids[] = $val['associate_user_id'];
                }
            }
        }
        
        $ids = array_unique($ids);
        $fnfIds = [ 'user_ids' => $ids];
        if($huids===true){
            $huIds = [];
            if(!empty($ids)){
                $users = User::select(['id', env('H_'), env('H__'), env('H___')])
                        ->whereIn('id', $ids)->where(['is_active' => '1', 'is_deleted' => '0'])->get()->toArray();
                $huIds = User::__HUID_($users);
            }
            $fnfIds['huids'] = $huIds;
        }
        return $fnfIds;
    }
    
    public static function getViralProfileIds($userId, $isOwned = true, $huids = true) {
        $userIds = (is_array($userId))? $userId : [$userId];
        $viralProfiles = UserViralProfile::select(['viral_profile_id', 'owned_by'])
                ->where(['status' => 'Approved'])
                ->whereIn('user_id', $userIds)
                ->get()->toArray();
        $ids = $viralProfileIds = array_column($viralProfiles, 'viral_profile_id');
        if($isOwned === true){
            $viralProfileOwnerIds = array_column($viralProfiles, 'owned_by');
            $ids = array_merge($viralProfileIds, $viralProfileOwnerIds);
        }
        $ids = array_merge($ids, $userIds);
        $ids =  array_unique($ids);

        if($huids===true){
            $huIds = [];
            if(!empty($ids)){
                $users = User::select(['id', env('H_'), env('H__'), env('H___')])
                        ->whereIn('id', $ids)->where(['is_active' => '1', 'is_deleted' => '0'])->get()->toArray();
                $huIds = User::__HUID_($users);
            }
            $data['huids'] = $huIds;
        }
        $data['user_ids'] = $ids;
        return $data;
    }

    public static function getPendingRequestByUserId($userId) {
        $user = \Auth::user();
        $myPendingReqs = self::where(['associate_user_id' => $userId, 'is_claimed' => 'N'])
                        ->with(['user' => function($userQuery) {
                                $userQuery->select(['id', 'dob', 'first_name', 'last_name', 'email', 'gender', 'cnic', 'profile_pic']);
                                $userQuery->where(['is_deleted' => '0', 'is_active' => '1']);
                            }])->with(['relationship' => function($relationshipQuery) {
                        $relationshipQuery->select(['id', 'name']);
                    }])->with(['assc_relationship' => function($relationshipQuery2) {
                        $relationshipQuery2->select(['id', 'name']);
                    }])->whereHas('user', function($userQuery) {
                                $userQuery->select(['id', 'dob', 'first_name', 'last_name', 'email', 'gender', 'cnic', 'profile_pic']);
                                $userQuery->where(['is_deleted' => '0', 'is_active' => '1']);
                            })->get();
        if (!is_null($myPendingReqs)) {
            $myPendingReqs = $myPendingReqs->toArray();
            $fnfData = [];
            foreach ($myPendingReqs as $fnf) {
                $asscUser = $fnf['user'];
                $asscUser['name'] = getUserName($asscUser);
                $asscUser['dob'] = (!empty($asscUser['dob'])) ? $asscUser['dob'] : null;
                $asscUser['age'] = ageCalculator($asscUser['dob']);
                $asscUser['created_at'] = $fnf['created_at'];
                $userObj = (object) $asscUser;
                $userDP = getUserDP($userObj);
                $asscUser['profile_pic'] = $userDP['pic'];
                $asscUser['profile_pic_thumb'] = $userDP['thumb'];
                $asscUser['fnf_id'] = $fnf['id'];
                $asscUser['status'] = $fnf['status'];
                 if($fnf['associate_user_id'] !== $user->id && !empty($fnf['assc_relationship'])){
                        $asscUser['relationship_id'] = $fnf['assc_relationship']['id'];
                        $asscUser['relationship'] = $fnf['assc_relationship']['name'];
                    }else{
                        $asscUser['relationship_id'] = $fnf['relationship']['id'];
                        $asscUser['relationship'] = $fnf['relationship']['name'];
                    }
                if ($fnf['status'] == 'Approved') {
//                    $asscUser['relationship'] = $fnf['assc_relationship']['name'];
//                    $asscUser['relationship_id'] = $fnf['assc_relationship']['id'];
                    $asscUser['permissions'] = ['shared_profile' => $fnf['shared_profile'], 'read_medical_record' => $fnf['read_medical_record'],
                        'write_medical_record' => $fnf['write_medical_record']];
                    $asscUser['my_permissions'] = ['shared_profile' => $fnf['assc_shared_profile'], 'read_medical_record' => $fnf['assc_read_medical_record'],
                        'write_medical_record' => $fnf['assc_write_medical_record']];
                    $category = (isset($fnf['assc_relationship']['name']) &&  !in_array($fnf['assc_relationship']['name'], ['Friend', 'Colleague'])) ? 'Family' : 'Friend';
                    $fnfData[$category][] = $asscUser;
                } elseif ($fnf['status'] == 'Pending') {
//                    $asscUser['relationship'] = $fnf['relationship']['name'];
//                    $asscUser['relationship_id'] = $fnf['relationship']['id'];
                    $asscUser['permissions'] = ['shared_profile' => $fnf['shared_profile'], 'read_medical_record' => $fnf['read_medical_record'],
                        'write_medical_record' => $fnf['write_medical_record']];
                    $fnfData['pendingReceived'][] = $asscUser;
                }
            }
            return $fnfData;
        }
        return [];
    }

}
