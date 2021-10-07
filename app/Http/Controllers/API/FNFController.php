<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use App\Models\FamilyTree;
use App\Models\Relationship;

class FNFController extends Controller {

    use \App\Traits\WebServicesDoc,        \App\Traits\FCM;

    public function search(Request $request) {
        $loggedUser = \Auth::user();
        $request->validate(['cnic' => 'required|digits:13']);
        $post = $request->all();
        if($loggedUser->cnic == $post['cnic']){
            return responseBuilder()->error('You cannot add your own CNIC', 400);
        }
        $myViralCnic = $loggedUser->id.'_'.$post['cnic'];
//        dump($myViralCnic);
//        die;
        $user = User::select(['id', 'dob', 'first_name', 'last_name', 'cnic', 'profile_pic', 'gender', 'is_viral', 'is_viral_claimed'])
                        ->where(['is_deleted' => '0', 'is_active' => '1'])
                ->whereIn('cnic', [$post['cnic'], $myViralCnic])
                ->where('cnic', '!=', $loggedUser->cnic)->first();

        if(!empty($user) && ($user->cnic == $myViralCnic && ($user->is_viral== 'Y' && $user->is_viral_claimed == 'N'))){
            return responseBuilder()->error('This user already exist in your FNF list as viral account', 400);
        }
        if (is_null($user)) {
            return responseBuilder()->success('The users\'s profile does not exist');
        }
        $fnfCount = FamilyTree::where(['parent_user_id'=> $loggedUser->id, 'associate_user_id' => $user->id])
                ->whereIn('status', ['Pending', 'Approved'])
                ->orWhere(function($fnfQuery) use($user, $loggedUser){
                    $fnfQuery->where(['parent_user_id'=> $user->id, 'associate_user_id' => $loggedUser->id])
                            ->whereIn('status', ['Pending', 'Approved']);
                })
                ->count();
        if($fnfCount>0){
            return responseBuilder()->error('This user already exist in your FNF list, this request may be in pending state', 400);
        }
        $userDP = getUserDP($user);
        $user->profile_pic = $userDP['pic'];
        $user->profile_pic_thumb = $userDP['thumb'];
        $user->age = ageCalculator($user->dob);
        $user->name = getUserName($user);
        $user->associate_user_id = $user->id;
        $response = responseBuilder()->success('Fetch user against provided CNIC', $user);
        $this->urlComponents('Search for FNF ', $response, 'Friends_AND_Family');
        return $response;
    }
    
    
    
    public function listFNF() {
        $user = \Auth::user();
        $familyTreeWhere = function($familyTreeQuery) use($user){
            $familyTreeQuery->where('parent_user_id', $user->id);
            $familyTreeQuery->where(['is_claimed' => 'N', 'status' => 'Approved']);
            $relationshipWhere = function($relationshipQuery){
                $relationshipQuery->select(['id', 'name', 'type_id']);
                $relationshipQuery->with(['relationship_type' => function($relationshipTypeWhere){
                    $relationshipTypeWhere->select(['id', 'name', 'policy_part']);
                }]);
            };
            $familyTreeQuery->with(['relationship' => $relationshipWhere])->whereHas('relationship', $relationshipWhere);
            $familyTreeQuery->with(['assc_relationship' => $relationshipWhere])->whereHas('relationship', $relationshipWhere);
            $familyTreeQuery->with(['fnf_user' => function($fnfUserQuery){
                $fnfUserQuery->select(['id', 'dob', 'first_name', 'last_name', 'email', 'gender', 'cnic', 'profile_pic']);
                $fnfUserQuery->where(['is_active' => '1', 'is_deleted' => '0']);
            }])->whereHas('fnf_user', function($fnfUserQuery){
                $fnfUserQuery->where(['is_active' => '1', 'is_deleted' => '0']);
            });
            
        };
        $fnfs = $user->select(['id', 'cnic', 'first_name', 'last_name'])->with(['family_trees'=> $familyTreeWhere])
                ->whereHas('family_trees', $familyTreeWhere)->first();
        $fnfData = [];
        if (!empty($fnfs)) {
            $fnfs = $fnfs->toArray();
            foreach ($fnfs['family_trees'] as $fnf) {
                $asscUser = $fnf['fnf_user']; 
                $asscUser['name'] = getUserName($asscUser);
                $asscUser['dob'] = (!empty($asscUser['dob'])) ? $asscUser['dob'] : null;
                $asscUser['age'] = ageCalculator($asscUser['dob']);
                if($fnf['associate_user_id'] !== $user->id && !empty($fnf['assc_relationship'])){
                $asscUser['relationship_id'] = $fnf['assc_relationship']['id'];
                $asscUser['relationship'] = $fnf['assc_relationship']['name'];
            }else{
                $asscUser['relationship_id'] = $fnf['relationship']['id'];
                $asscUser['relationship'] = $fnf['relationship']['name'];
            }
                $asscUser['created_at'] = $fnf['created_at'];
                $asscUser['status'] = $fnf['status'];
                $userObj = (object) $asscUser;
                $asscUser['fnf_id'] = $fnf['id'];
                $userDP = getUserDP($userObj);
                $asscUser['profile_pic'] = $userDP['pic'];
                $asscUser['profile_pic_thumb'] = $userDP['thumb'];
                $asscUser['permissions'] = ['shared_profile' => $fnf['assc_shared_profile'], 'read_medical_record' => $fnf['assc_read_medical_record'],
                        'write_medical_record' => $fnf['assc_write_medical_record']];
                $category = (isset($fnf['relationship']['name']) &&  !in_array($fnf['relationship']['name'], ['Friend', 'Colleague'])) ? 'Family' :'Friend'; 
                $fnfData[$category][] = $asscUser;
            }
        }
        $response = responseBuilder()->success('list of my approved fnf', $fnfData);
        $this->urlComponents('List my FNF approved users ', $response, 'Friends_AND_Family');
        return $response;
    }


    public function index() {
        $user = \Auth::user();
//        $fnfs = $user->family_trees()->get()->toArray();
//        $fnfUsersIds = array_column($fnfs, 'associate_user_id');
//        $fnfUsers = User::select(['id', 'dob', 'first_name', 'last_name', 'email', 'gender'])
//                        ->where(['is_active' => '1', 'is_deleted' => '0'])
//                        ->whereIn('id', $fnfUsersIds)->get()->toArray();
        $familyTreeWhere = function($familyTreeQuery) use($user){
            $familyTreeQuery->where('parent_user_id', $user->id);
            $familyTreeQuery->where('is_claimed', 'N');
            $relationshipWhere = function($relationshipQuery){
                $relationshipQuery->select(['id', 'name', 'type_id']);
                $relationshipQuery->with(['relationship_type' => function($relationshipTypeWhere){
                    $relationshipTypeWhere->select(['id', 'name', 'policy_part']);
                }]);
            };
            $familyTreeQuery->with(['relationship' => $relationshipWhere])->whereHas('relationship', $relationshipWhere);
            $familyTreeQuery->with(['assc_relationship' => $relationshipWhere])->whereHas('relationship', $relationshipWhere);
            $familyTreeQuery->with(['fnf_user' => function($fnfUserQuery){
                $fnfUserQuery->select(['id', 'dob', 'first_name', 'last_name', 'email', 'gender', 'cnic', 'profile_pic']);
                $fnfUserQuery->where(['is_active' => '1', 'is_deleted' => '0']);
            }])->whereHas('fnf_user', function($fnfUserQuery){
                $fnfUserQuery->where(['is_active' => '1', 'is_deleted' => '0']);
            });
            
        };
        $fnfs = $user->select(['id', 'cnic', 'first_name', 'last_name'])->with(['family_trees'=> $familyTreeWhere])
                ->whereHas('family_trees', $familyTreeWhere)->first();
        if (empty($fnfs)) {
            goto myPendingReq;
        }
        $fnfs = $fnfs->toArray();
        $fnfData = [];
        $relationshipCats = \App\Models\RelationshipType::get()->pluck('name', 'id')->toArray();
        foreach ($fnfs['family_trees'] as $fnf) {
            $asscUser = $fnf['fnf_user']; 
            $asscUser['name'] = getUserName($asscUser);
            $asscUser['dob'] = (!empty($asscUser['dob'])) ? $asscUser['dob'] : null;
            $asscUser['age'] = ageCalculator($asscUser['dob']);
            if($fnf['associate_user_id'] !== $user->id && !empty($fnf['assc_relationship'])){
                $asscUser['relationship_id'] = $fnf['assc_relationship']['id'];
                $asscUser['relationship'] = $fnf['assc_relationship']['name'];
            }else{
                $asscUser['relationship_id'] = $fnf['relationship']['id'];
                $asscUser['relationship'] = $fnf['relationship']['name'];
            }
            $asscUser['created_at'] = $fnf['created_at'];
            $asscUser['status'] = $fnf['status'];
            $userObj = (object) $asscUser;
            $asscUser['fnf_id'] = $fnf['id'];
            $userDP = getUserDP($userObj);
            $asscUser['profile_pic'] = $userDP['pic'];
            $asscUser['profile_pic_thumb'] = $userDP['thumb'];
            if($fnf['status'] == 'Approved'){
                $asscUser['permissions'] = ['shared_profile' => $fnf['assc_shared_profile'], 'read_medical_record' => $fnf['assc_read_medical_record'],
                        'write_medical_record' => $fnf['assc_write_medical_record']];
                $asscUser['my_permissions'] = ['shared_profile' => $fnf['shared_profile'], 'read_medical_record' => $fnf['read_medical_record'],
                        'write_medical_record' => $fnf['write_medical_record']];
                $category = (isset($fnf['relationship']['name']) &&  !in_array($fnf['relationship']['name'], ['Friend', 'Colleague'])) ? 'Family' :'Friend'; 
                if($category == 'Family'){
                    $asscUser['relationshipCategory'] =$fnf['relationship']['relationship_type'];
                }
                $fnfData[$category][] = $asscUser;
            } elseif ($fnf['status'] == 'Pending') {
                $fnfData['pendingSent'][] = $asscUser;
            }
        }
        myPendingReq: 
        $myPendingReqs = FamilyTree::getPendingRequestByUserId($user->id);
        $fnfData = (!empty($fnfData)) ? $fnfData : [];
        if(isset($myPendingReqs) && count($myPendingReqs) > 0){
            //$fnfData = array_merge($fnfData, $myPendingReqs)
            if(!empty( $myPendingReqs['pendingReceived'])){
                $fnfData['pendingReceived'] = $myPendingReqs['pendingReceived'];
            }
            if(!empty( $myPendingReqs['Family'])){
                $fnfData['Family'] = (!empty($fnfData['Family'])) ? $fnfData['Family'] : [];
                $fnfData['Family'] = array_merge($fnfData['Family'], $myPendingReqs['Family']);
            }
            if(!empty( $myPendingReqs['Friend'])){
                $fnfData['Friend'] = (!empty($fnfData['Friend'])) ? $fnfData['Friend'] : [];
                $fnfData['Friend'] = array_merge($fnfData['Friend'], $myPendingReqs['Friend']);
            }
        }
        
        $response = responseBuilder()->success('list of fnf', $fnfData);
        $this->urlComponents('List of my FNF users ', $response, 'Friends_AND_Family');
        return $response;
    }

    public function store(Request $request) {
        $user = \Auth::user();
        $rules = ['associate_user_id' => 'bail|required|exists:users,id', 'age' => 'numeric', 'relationship_id' => 'required'];
        $post = $request->all();
        if (!$request->has('associate_user_id')) {//CNIC case
            $cnicCount = User::where(['cnic' => $post['cnic']])->count();
            if($cnicCount>0){
                return responseBuilder()->error('This CNIC already exist', 400);
            }
            $newCnic = $user->id.'_'.$request->get('cnic');
            $post['cnic'] = $newCnic;
            $rules['cnic'] = 'bail|required|unique:users,cnic,';
            unset($rules['associate_user_id']);
        }else{
            unset($rules['cnic']);
            
        }
        $userData = null;
        \Validator::make($post, $rules)->validate();
        if (!$request->has('associate_user_id')) {
            $userData = User::select(['id', 'cnic'])->where(['cnic' => $post['cnic'], 'is_deleted' => '1'])->first();
        }
        if (!empty($post['age'])) {
            $post['dob'] = date('Y-m-d', strtotime("-{$post['age']} year"));
        }
        $userName = (!empty($user->first_name)) ? ($user->first_name.' '.$user->last_name) : $user->cnic;
        $relation = Relationship::select('name')->where('id',$post['relationship_id'])->first();
       
        $post['created_by'] = $user->id;
        $post['updated_by'] = $user->id;
        $familyTreeData = $post;
        $familyTreeData['parent_user_id'] = $fnfConstraints['parent_user_id'] = $user->id;
        if (is_null($userData) && empty($post['associate_user_id'])) {
            $request->validate(['name' => 'required|max:80']);
            $post['cnic'] = $post['cnic'];
            $post['password'] = bcrypt($post['cnic']);
            $post['first_name'] = $post['name'];
            $post['registration_source'] = 'viralAccountOf_'.$user->id;
            $cnicLastDigit = substr($post['cnic'], -1);
            $post['gender'] = ($cnicLastDigit%2==0)?'Female':'Male';
            $post['is_viral']='Y';
            $associateUser = User::create($post);
            $familyTreeData['shared_profile'] = ($post['shared_profile']) ?? 'Y';
            $familyTreeData['read_medical_record'] = ($post['read_medical_record']) ?? 'Y';
            $familyTreeData['write_medical_record'] = ($post['write_medical_record']) ?? 'Y';
            $familyTreeData['status'] = 'Approved';
            $familyTreeData['is_viral'] = 'Y';
            $familyTreeData['assc_relationship_id'] = Relationship::getAsscRelaitonshipId($post['relationship_id'], $post['gender']);
            $msg = 'New Friends & Family contact has been added successfully';
        } else {
            $associateUser = User::find($post['associate_user_id']);
            $familyTreeData['shared_profile'] = ($post['shared_profile']) ?? 'N';
            $familyTreeData['read_medical_record'] = ($post['read_medical_record']) ?? 'N';
            $familyTreeData['write_medical_record'] = ($post['write_medical_record']) ?? 'N';
            if(empty($post['id'])){
                $familyTreeData['status'] = 'Pending';
                $msg = 'Your request has been submitted successfully and is pending for approval.';
            }else{
                $msg = 'Request changes have been saved successfully';
            }    
        }
        if ($associateUser) {
            $familyTreeData['associate_user_id'] = $fnfConstraints['associate_user_id'] = $associateUser->id;
            $fnfData= FamilyTree::updateOrcreate($fnfConstraints, $familyTreeData);
            
            $pushReceiver = $associateUser->toArray();
            $pushSender = $user->toArray();
            $userAge = ageCalculator($user->dob);
            $genderCaption = 'himself';
            if($user->gender == 'Female'){
                $genderCaption = 'herself';
            }
            $userDP = getUserDP($user);
            $pushNote = ['data' => ['title' => env('APP_NAME')." - New FNF Request",
                'body' => "{$userName} has listed {$genderCaption} as your {$relation->name}", 
            'click_action' => 'RECEIVED_FNF_REQUEST',
                        'subTitle' => 'Friends & Family', 'name' => $userName, 'id' => $user->id, 
            'age' => $userAge, 'relationship' => '', 'profile_pic' => $userDP['pic'], 'profile_pic_thumb' => $userDP['thumb'],
                        'fnf_id'=> $fnfData->id],
            'user_id' => $associateUser->id, 'created_by' => $user->id];
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);    
            $response = responseBuilder()->success($msg);
            $this->urlComponents('Add New FNF ', $response, 'Friends_AND_Family');
            return $response;
        }

        return responseBuilder()->error('An error occured, unable to process request', 400);
    }

    public function show($id) {
        $pushNote = ['notification' => ['title' => 'You have added as FNF user', 'text' => 'Ali added you as Son in his FNF list', 
            'click_action' => 'OPEN_ACTIVITY_FNF_USER'],
            'data' => ['test' => 'name', 'user' => 'manual sending'],
            'to' => $id];
        return $this->pushFCMNotification($pushNote, true);
    }

    public function update(Request $request, $id) {
        $rules = ['status' => 'required|in:Approved,Reject,Decline'];
        $post = $request->all();
        if($post['status'] == 'Approved'){
            $rules['relationship_id'] = 'required';
        }
        $request->validate($rules);
        $user = \Auth::user();
        $familyTree = FamilyTree::where(['status' => 'Pending', 'associate_user_id' => $user->id, 'is_claimed' => 'N'])->findOrFail($id);
        $status = in_array($post['status'], ['Reject', 'Decline']) ? 'Decline' : $post['status'];
        $data = ['status' => $status];
        if($status== 'Approved'){
            $data = ['status' => $status, 'updated_by' => $user->id, 'assc_shared_profile' => $post['shared_profile'], 
                'assc_write_medical_record' => $post['write_medical_record'], 'assc_read_medical_record' => $post['read_medical_record'], 
                'assc_relationship_id' => $post['relationship_id']];
        }
        $familyTree->update($data);
        $userName = (!empty($user->first_name)) ? ($user->first_name.' '.$user->last_name) : $user->cnic;
        $reqStatus = ($status== 'Approved') ? 'Accepted' : 'Rejected';
            $pushNote = ['data' => ['title' => env('APP_NAME')." - Request {$reqStatus}",
                'body' => "{$userName} has {$reqStatus} your request.", 
            'click_action' => 'RESPOND_FNF_REQUEST',
            'subTitle' => 'Friends & Family', 'name' => $userName, 'id' => $user->id],
             'user_id' => $familyTree->parent_user_id, 'created_by' => $user->id];
            $pushReceiver = User::select(['id', 'cnic'])->find($familyTree->parent_user_id)->toArray();
            $pushSender = $user->toArray();
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);         
            
        $response = responseBuilder()->success("Request {$status} successfully");
        $this->urlComponents('Approved/Reject FNF Requests', $response, 'Friends_AND_Family');
        return $response;
    }
    
    public function fnfChangePermission(Request $request, $id) {
        $post = $request->only(['shared_profile', 'read_medical_record', 'write_medical_record']);
        $user = \Auth::user();
        $familyTree = FamilyTree::where(['is_claimed' => 'N'])->findOrFail($id);
        if($familyTree->parent_user_id != $user->id){
            if(!empty($post['shared_profile'])){ $newpost['assc_shared_profile']=$post['shared_profile'];}
            if(!empty($post['read_medical_record'])){ $newpost['assc_read_medical_record']=$post['read_medical_record'];}
            if(!empty($post['write_medical_record'])){ $newpost['assc_write_medical_record']=$post['write_medical_record'];}
            $post= $newpost;
        }
        $familyTree->update($post);
        $response = responseBuilder()->success("Permissions are updated successfully");
        $this->urlComponents('Change Permission for FNF Requests', $response, 'Friends_AND_Family');
        return $response;
    }
    
    
    public function destroy($id) {
        $user = \Auth::user();
        $familyTree = FamilyTree::where(['parent_user_id' => $user->id, 'associate_user_id' => $id])
            ->orWhere(function($fnfQuery) use($user, $id){
                $fnfQuery->where(['associate_user_id' => $user->id, 'parent_user_id' => $id]);
            })->first();
        if ($familyTree) {
            $familyTree->delete();
            $familyTree->update(['deleted_by' => $user->id]);
            if($familyTree->is_viral == 'Y' && $familyTree->is_claimed == "N"){
                User::deleteUserById($familyTree->associate_user_id);
            }
            $userName = (!empty($user->first_name)) ? ($user->first_name.' '.$user->last_name) : $user->cnic;
            $pushUserId = ($familyTree->parent_user_id == $user->id) ? $familyTree->associate_user_id : $familyTree->parent_user_id;    
            $pushNote = ['data' => ['title' => env('APP_NAME')." - {$user->first_name} has deleted you from FNF list",
                    'body' => "{$userName} has deleted you from Friends & Family list.", 
                'click_action' => 'RESPOND_FNF_REQUEST',
                            'subTitle' => 'Friends & Family', 'name' => $userName, 'id' => $user->id,],
                'user_id' => $pushUserId, 'created_by' => $user->id];
            $pushReceiver = User::select(['id', 'cnic'])->find($pushUserId)->toArray();
            $pushSender = $user->toArray();
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);  
            $response = responseBuilder()->success('User removed successfully');
            $this->urlComponents('Remove User from FNF list', $response, 'Friends_AND_Family');
            return $response;
        }
        return responseBuilder()->error('Unable to find your FNF user', 404);
    }
    
}
