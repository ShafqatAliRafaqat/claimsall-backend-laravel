<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Libraries\Uploader;
use App\Models\UserProfile;
use App\User;
use App\Models\OrganizationUser;
use App\Models\Organization as OrganizationModel;
use \Illuminate\Support\Facades\Input;

class IndexController extends Controller {

    use \App\Traits\WebServicesDoc;
    public function settings() {
        $data = \App\User::__HUID();
        $huid = ___HQ($data['__']);
        $response = responseBuilder()->success('Site settings', \App\User::paginate(10));
        //$this->urlComponents('Get site settings', [], $response);
        return $response;
    }
    
    private function escape($input){
        return \DB::escape($input);
    }

    public function getUserRole() {
        $user = \Auth::user();
//        $userData = $user->with(['roles' => function($q) {
//                                $q->where(['code' => config('app.hospitallCodes')['superAdmin'], 'is_deleted' => '0']);
//                            }])
//                         ->first()
//                         ->toArray();
        $userData = $user->roles()->select(['id', 'title'])->get();
        $response = responseBuilder()->success('User details is following', $userData);
        $this->urlComponents('Get User Role Detail', $response, 'User_Management');
        return $response;
    }

    public function updateProfile(Request $request) {
        $user = \Auth::user();
        $post = $request->all();
        $oUserRequest = new \App\Http\Requests\User();
        $rules = $oUserRequest->rules();
        $rules['email'] = $rules['email'] . ',email,' . $user->id;
        $rules['cnic'] = $rules['cnic'] . ',cnic,' . $user->id;
        if(!empty($post['contact_number']) && $post['contact_number']!= 'N'){
            $rules['contact_number'] = $rules['contact_number'] . ',contact_number,' . $user->id;
        }else{
            $post['contact_number'] = null;
        }
        if(!empty($post['facebook_id']) && $post['facebook_id']!= 'N'){
            $rules['facebook_id'] = $rules['facebook_id'] . ',facebook_id,' . $user->id;
        }else{
            $post['facebook_id'] = null;
        }
        if(!empty($post['google_plus_id']) && $post['google_plus_id']!= 'N'){
            $rules['google_plus_id'] = $rules['google_plus_id'] . ',google_plus_id,' . $user->id;
        }else{
            $post['google_plus_id'] = null;
        }
        $rules['organization_id'] = 'exists:organizations,id';
         if(!empty($post['organization_id'])) {
            $rules['employee_code'] = 'unique:organization_user,employee_code,'.$user->id.',user_id,organization_id,'.$post['organization_id'];
         }
        $request->validate($rules);
//        dd('all good to go');
        $randNumber = str_random(8);
        if ($request->has('password') && !empty($post['password'])) {
            $post['password'] = $post['password'] ?? $randNumber;
            $post['password'] = bcrypt($post['password']);
        }
        $msg = '';
        //$post['activation_code'] = $post['verification_code'] = str_shuffle($randNumber);
        if((!empty($post['email'])) && $user->email !== $post['email']){
            $resetToken = User::getToken($post['email']);
            $post['verification_code'] =route('user.verified_email_address', ['token' => $resetToken]);
            $post['email_verified'] = 'N';
            $msg = ', and email verification is required';
            event(new \Illuminate\Auth\Events\Registered($user));
            dispatch(new \App\Jobs\SendVerificationEmail($user));
        }
        $arrayKeys = array_keys($post);
        $profileItems = array_intersect(UserProfile::TB_FIELDS, $arrayKeys) ?? [];
        $countItems = count($profileItems);
        $res = $user->update($post);
        $defualtCompany = OrganizationModel::defaultOrganizationData($user);
        if($res===false){ goto end; }
        if(!empty($post['unlink_organization_id'])) {
            OrganizationUser::where(['user_id' => $user->id, 'organization_id' => $post['unlink_organization_id']])->delete();
            $comRolesIds = \App\Models\Role::where(['organization_id' => $post['unlink_organization_id'], 'is_deleted' => '0'])->pluck('id')->toArray();
            if(!empty($comRolesIds)){
                \App\Models\RoleUser::where(['user_id' => $user->id])->whereIn('role_id', $comRolesIds)->delete();
            }
        }
        if(!empty($post['organization_id'])) {
            $orgnizationsUsers = OrganizationUser::where(['user_id' => $user->id, 'organization_id' => $post['organization_id']])->get();
            if(isset($orgnizationsUsers)){
                $orgnizationsUsers = $orgnizationsUsers->toArray();
                $orgUserData = ['user_id' => $user->id, 'organization_id' => $post['organization_id'], 'status' => 'Pending'];
                if(empty($orgnizationsUsers)){
                    $orgUserData['is_default'] = 'Y';
                }
                $orgUserData['employee_code'] = (empty($post['employee_code']) ? null : $post['employee_code']);
                $orgUserData['designation'] = (empty($post['designation']) ? null : $post['designation']);
                $orgUserData['department'] = (empty($post['department']) ? null : $post['department']);
                if(!empty($defualtCompany->id) && $defualtCompany->id == $post['organization_id']){
                    unset($orgUserData['status']);
                }
                OrganizationUser::updateOrCreate(['user_id' => $user->id], $orgUserData);
            }
        }
        if ($countItems > 0) {
            $userProfile = UserProfile::where('user_id', $user->id)->first();
            if(!empty($post['dependant'])){
                $post['dependant'] = (is_array($post['dependant'])) ? serialize($post['dependant']) : $post['dependant'];
            }
            if(!empty($post['kids'])){
                $post['kids'] = (is_array($post['kids'])) ? serialize($post['kids']) : $post['kids'];
            }
            if ($userProfile) {
                $userProfile->update($post);
            } else {
                $user->user_profile()->save(new UserProfile($post));
            }
        }
        $userData = User::formatUserData($user);
        $response = responseBuilder()->success('Your profile updated successfully'.$msg, $userData);
        $this->urlComponents('Update Profile for loggedin user', $response, 'Update_User_Profile');
        return $response;
        end:
        return responseBuilder()->error('Something went wrong while updating', 400);
    }

    public function updateUserProfilePic(Request $request) {
        $user = \Auth::user();
        $request->validate(['document' => 'required|image']);
        $post = $request->all();
        $post['updated_by'] = $user->id;
        $uploader = new Uploader();
        $uploader->setFile($post['document']);
        if ($uploader->isValidFile()===false) {
            return responseBuilder()->error($uploader->getMessage(), 400);
        }
        if ($uploader->isValidFile()) {
            $path = getUserDocumentPath($user);
            $imgReadPath = getUserDocumentPath($user, false);
            $uploader->upload($path, $uploader->fileName, $imgReadPath, [200, 200]);
            if ($uploader->isUploaded()) {
                $profilePic = $post['profile_pic'] = $uploader->getUploadedPath(false);
                $data['profile_pic_path'] = getUserDocumentPath($user, false) . '/' . $profilePic;
                $data['profile_pic_thumb_path'] = getUserDocumentPath($user, false) . '/s_' . $profilePic;
            }else{
                return responseBuilder()->error('An error occured while file uploading '. $uploader->getMessage(), 400);
            }
            $user->update($post);
            unset($post['updated_by']);
            $response = responseBuilder()->success('Your profile updated successfully', $data);
            $this->urlComponents('Update Profile Picture for loggedin user', $response, 'Update_Profile_Image_LoggedIn_User');
            return $response;
        }

        return responseBuilder()->error('Something went wrong while updating', 400);
    }

    //
    public function getOrgsStats1(Request $request) {
//        $query = "SELECT COUNT(id) AS newRecords, DATE(created_at) currDate
//                    FROM organizations
//                    WHERE deleted_at IS NULL ";
        $query = \DB::table('organizations')->select(\DB::raw('COUNT(id) AS newRecords, DATE(created_at) currDate'))
                ->whereNull('deleted_at');
        $post = $request->all();
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";
                $query = $query->whereBetween('created_at', [$start_date, $end_date])
                        ->groupBy('currDate');
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $query = $query->where('created_at', '>=', $start_date)
                        ->groupBy('currDate');
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                $end_date = $report['end_date']." 23:59:59";
//                $query .= "AND created_at <= :end_date
//                            GROUP BY DATE(created_at)";
                $query = $query->where('created_at', '<=', $end_date)
                        ->groupBy('currDate');
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                $query = $query->groupBy('currDate');
//                $query .= "GROUP BY DATE(created_at)";
            }
        }
        else {
            $query = $query->groupBy('currDate');
//            $query .= "GROUP BY DATE(created_at)";
        }
        dd($query->get());
//                  $res = \DB::statement($query, $params);
//                  dump($res);
//        $count_arr = \DB::select($res);
//                  dump($res);
//                  die();

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->currDate] = $value->newRecords;
        }
    
        $response = responseBuilder()->success('Organizations stats', $result, false);
        $this->urlComponents('Organization Stats', $response, 'Statistics');
        return $response;
    }
    
    public function getOrgsStats(Request $request) {
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date']);
        
        $query = "SELECT COUNT(id) AS newRecords, DATE(created_at) currDate
                    FROM organizations
                    WHERE deleted_at IS NULL ";
        $post = $request->all();
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND created_at BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY DATE(created_at)";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND created_at >= '{$start_date}'
                            GROUP BY DATE(created_at)";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND created_at <= '{$end_date}'
                            GROUP BY DATE(created_at)";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                $query .= "GROUP BY DATE(created_at)";
            }
        }
        else {
            $query .= "GROUP BY DATE(created_at)";
        }

        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->currDate] = $value->newRecords;
        }
    
        $response = responseBuilder()->success('Organizations stats', $result, false);
        $this->urlComponents('Organization Stats', $response, 'Statistics');
        return $response;
    }

    public function claimStats(Request $request) {
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date', 'organization_id' =>'bail|required|exists:organizations,id']);
        $post = $request->all();
        $org_id = $post['organization_id'];
        $query = "SELECT COUNT(hos_v11.`medical_claims`.`id`) AS total, hos_v11.`medical_claims`.`status` AS claim_status
                    FROM hos_v11.`medical_claims`
                    WHERE hos_v11.`medical_claims`.`organization_id` = '{$org_id}' ";
        $post = $request->all();
        //print_r($request->all());//exit;
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "AND hos_v11.`medical_claims`.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY hos_v11.`medical_claims`.`status`";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND hos_v11.`medical_claims`.`created_at` >= '{$start_date}'
                            GROUP BY hos_v11.`medical_claims`.`status`";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND hos_v11.`medical_claims`.`created_at` <= '{$end_date}'
                            GROUP BY hos_v11.`medical_claims`.`status`";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY hos_v11.`medical_claims`.`status`";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY hos_v11.`medical_claims`.`status`";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->claim_status] = $value->total;
        }
    
        $response = responseBuilder()->success('Users stats', $result, false);
        $this->urlComponents('Claim Stats', $response, 'Statistics');
        return $response;
    }

    public function getUserStats(Request $request) {
        $superAdmin = \App\User::getSuperAdmin();
        $query = "SELECT COUNT(id) AS newRecords, DATE(created_at) currDate
                    FROM users
                    WHERE deleted_at IS NULL
                    AND is_viral = 'N'
                    AND id != '{$superAdmin->id}' ";
        $post = $request->all();
        //print_r($request->all());//exit;
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "AND created_at BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY DATE(created_at)";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND created_at >= '{$start_date}'
                            GROUP BY DATE(created_at)";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND created_at <= '{$end_date}'
                            GROUP BY DATE(created_at)";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY DATE(created_at)";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY DATE(created_at)";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->currDate] = $value->newRecords;
        }
    
        $response = responseBuilder()->success('Users stats', $result, false);
        $this->urlComponents('Get User Stats', $response, 'Statistics');
        return $response;
    }

    public function orgUserStats(Request $request) {
        $post = $request->all();
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date', 'organization_id' =>'bail|required|exists:organizations,id']);
        //print_r($request->all());//exit;
        $org_id = $post['organization_id'];
        $superAdmin = \App\User::getSuperAdmin();
        $query = "SELECT COUNT(`users`.`id`) AS total, DATE(`organization_user`.`created_at`) AS currDate 
                    FROM `organizations` ,`organization_user`, `users`
                     WHERE `users`.`id` = `organization_user`.`user_id`
                     AND `users`.`is_deleted` = '0'
                     AND `users`.`is_viral` = 'N'
                     AND `users`.`id` != '{$superAdmin->id}'
                     AND `organizations`.`id` = `organization_user`.`organization_id`
                     AND `organization_user`.`organization_id` = '{$org_id}' ";

        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "AND `organization_user`.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY DATE(`organization_user`.`created_at`);";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND `organization_user`.`created_at` >= '{$start_date}'
                            GROUP BY DATE(`organization_user`.`created_at`);";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND `organization_user`.`created_at` <= '{$end_date}'
                            GROUP BY DATE(`organization_user`.`created_at`);";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY DATE(`organization_user`.`created_at`);";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY DATE(`organization_user`.`created_at`);";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->currDate] = $value->total;
        }
    
        $response = responseBuilder()->success('Users stats', $result, false);
        $this->urlComponents('Organization Users Stats', $response, 'Statistics');
        return $response;
    }

    public function orgUserCount(Request $request) {
        $post = $request->all();
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date', 'organization_id' =>'bail|required|exists:organizations,id']);
        //print_r($request->all());//exit;
        $org_id = $post['organization_id'];
        $superAdmin = \App\User::getSuperAdmin();
        $query = "SELECT `organization_user`.`status`, COUNT(`users`.`id`) AS total
                    FROM `organizations` ,`organization_user`, `users`
                     WHERE `users`.`id` = `organization_user`.`user_id`
                     AND `users`.`is_deleted` = '0'
                     AND `users`.`is_viral` = 'N'
                     AND `users`.`id` != '{$superAdmin->id}'
                     AND `organizations`.`id` = `organization_user`.`organization_id`
                     AND `organization_user`.`organization_id` = '{$org_id}' ";

        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "AND `organization_user`.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY `organization_user`.`status`;";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND `organization_user`.`created_at` >= '{$start_date}'
                            GROUP BY `organization_user`.`status`;";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND `organization_user`.`created_at` <= '{$end_date}'
                            GROUP BY `organization_user`.`status`;";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY `organization_user`.`status`;";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY `organization_user`.`status`;";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->status] = $value->total;
        }
    
        $response = responseBuilder()->success('Users stats', $result, false);
        $this->urlComponents('Organization Users Count', $response, 'Statistics');
        return $response;
    }

    public function serviceproviderStats(Request $request)
    {
        $obj = new \App\User();
        $res = $obj->getServiceProviderStats();
        $response = responseBuilder()->success('Service Providers Count', $res);
        $this->urlComponents('Service Providers Count', $response, 'Statistics');
        return $response;
    }

    public function careserviceStats(Request $request) {
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date']);
        $query = "SELECT `status`, COUNT(`id`) AS newRecords
                    FROM care_services ";
        $post = $request->all();
        //print_r($request->all());//exit;
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "WHERE created_at BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY `status`;";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "WHERE created_at >= '{$start_date}'
                            GROUP BY `status`;";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "WHERE created_at <= '{$end_date}'
                            GROUP BY `status`;";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY `status`;";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY `status`;";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->status] = $value->newRecords;
        }
    
        $response = responseBuilder()->success('careservices stats', $result, false);
        $this->urlComponents('Care Services Stats', $response, 'Statistics');
        return $response;
    }

    public function requestStats(Request $request) { // careservices request date wise
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date']);
        $query = "SELECT cts.`name`, COUNT(cs.`id`) AS newRecords
                      FROM care_services cs, care_type_services cts
                      WHERE cs.`care_services_type_id` = cts.`id` ";
        $post = $request->all();
        //print_r($request->all());//exit;
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "AND cs.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY cts.`name`;";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND cs.`created_at` >= '{$start_date}'
                            GROUP BY cts.`name`;";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND cs.`created_at` <= '{$end_date}'
                            GROUP BY cts.`name`;";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY cts.`name`;";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY cts.`name`;";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->name] = $value->newRecords;
        }
    
        $response = responseBuilder()->success('Request stats', $result, false);
        $this->urlComponents('Requests Stats', $response, 'Statistics');
        return $response;
    }

    public function roleStats(Request $request) { // careservices request date wise
        $request->validate(['report.start_date' => 'date', 'report.end_date' => 'date']);
        $query = "SELECT 
                      COUNT(u.`id`) AS total,
                      DATE(ru.`created_at`) AS currDate 
                    FROM
                      users u,
                      role_user ru,
                      roles r 
                    WHERE u.`id` = ru.`user_id` 
                      AND r.`id` = ru.`role_id` 
                      AND u.`is_deleted` = '0' 
                      AND u.`deleted_at` IS NULL 
                      AND r.`code` = 'doctor' ";
        $post = $request->all();
        //print_r($request->all());//exit;
        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";

                //print_r($start_date);exit;

                $query .= "AND ru.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY r.`title`, DATE(ru.`created_at`);";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "AND ru.`created_at` >= '{$start_date}'
                            GROUP BY r.`title`, DATE(ru.`created_at`);";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "AND ru.`created_at` <= '{$end_date}'
                            GROUP BY r.`title`, DATE(ru.`created_at`);";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY r.`title`, DATE(ru.`created_at`);";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY r.`title`, DATE(ru.`created_at`);";
        }
                  
        $count_arr = \DB::select($query);

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->currDate] = $value->total;
        }
    
        $response = responseBuilder()->success('Role stats', $result, false);
        $this->urlComponents('Role Stats', $response, 'Statistics');
        return $response;
    }

    public function lookupData(Request $request)
    {
        $data = [];
        $type = ($request->type) ?? 'all';
        if ($type == 'claimType') {
            $data['claimType'] = \App\Models\ClaimType::pluck('name', 'id')->all();
        } elseif ($type == 'relationshipType') {
            $data['relationType'] = \App\Models\RelationshipType::pluck('name', 'id')->all();
        } elseif ($type == 'documentCategory') {
            $data['documentCategory'] = \App\Models\DocumentCategory::pluck('name', 'id')->all();
        } elseif ($type == 'careTypeService') {
            $data['careTypeService'] = \App\Models\CareTypeService::pluck('name', 'id')->all();
        } elseif ($type == 'organizationType') {
            $data['organizationType'] = \App\Models\OrganizationType::pluck('name', 'id')->all();
        } else {
            $data['claimType'] = \App\Models\ClaimType::pluck('name', 'id')->all();
            $data['relationType'] = \App\Models\RelationshipType::where('policy_part', 'Y')->pluck('name', 'id')->all();
            $data['documentCategory'] = \App\Models\DocumentCategory::pluck('name', 'id')->all();
            $data['careTypeService'] = \App\Models\CareTypeService::pluck('name', 'id')->all();
            $data['organizationType'] = \App\Models\OrganizationType::pluck('name', 'id')->all();
        }

        $response = responseBuilder()->success('Data here: ', $data);
        $this->urlComponents('Look Up Data(Claim Types | Relationship Types | Document Category | Care Type Service | Organization Type)', $response, 'LooK_UP_APIs');
        return $response;
    }
    
    public function recentDataForType(Request $request) {
        //$request->validate(['type' => 'required|in:claim,medicalrecord']);
        //$user = \Auth::user();
        $data =[];
        $oMedicalClaim = new \App\Models\MedicalClaim();
        $data['claim'] = $oMedicalClaim->getRecentClaim();
        $oMedicalRecord = new \App\Models\MedicalRecord();
        $data['medicalRecord'] = $oMedicalRecord->getRecentRecord();
         $activity = new \App\Models\Activity();
        $data['upcommingActivity'] = $activity->getActivities(['flag' => 1]);
        $response = responseBuilder()->success('Your recent data', $data);
        $this->urlComponents('Recent Medical Claim', $response, 'LooK_UP_APIs');
         return $response;
    }

    public function lookupOrgs(Request $request)
    {
        $post = $request->all();
        $code = !empty($post['code'])? $post['code']: null;
        $orgs = OrganizationModel::query();
        $orgs = $orgs->select(['id', 'name', 'email']);
        if(!empty($code)) {
            $organization_typeQueryWhere = function ($organization_typeQuery) use ($code) {
                        $organization_typeQuery->where('code', $code);
                    };
            $orgs = $orgs->with(["organization_type" => $organization_typeQueryWhere])->whereHas('organization_type', $organization_typeQueryWhere);
        }
        $orgs = $orgs->where('is_deleted', '0');
        $orgs = $orgs->get();
        foreach ($orgs as $key => $org) {
            unset($org['organization_type']);
        }
        $msg = 'Found following organizations';
        if(!$orgs) {
        $orgs = [];
        $msg = 'No organizations found';
        }
        
        $response = responseBuilder()->success($msg, $orgs, false);
        $this->urlComponents('Lookup (Hospitals| Companies| Clinics| Pharmacies| Laboratories| Ambulance Services| Posture Cares| Homecare Elderly)', $response, 'LooK_UP_APIs');
        return $response;
    }

    public function lookupCareservices(Request $request)
    {
        $post = $request->all();
        $code = !empty($post['code'])? $post['code']: null;
        $CareTypeServices = \App\Models\CareTypeService::query();
        $CareTypeServices = $CareTypeServices->select(['id', 'name']);
        $CareTypeServices = $CareTypeServices->get();
        $msg = 'Found following organizations';
        if(!$CareTypeServices) {
            $CareTypeServices = [];
            $msg = 'No CareTypeServices found';
        }
        
        $response = responseBuilder()->success($msg, $CareTypeServices, false);
        $this->urlComponents('Lookup (Hospitals| Companies| Clinics| Pharmacies| Laboratories| Ambulance Services| Posture Cares| Homecare Elderly)', $response, 'LooK_UP_APIs');
        return $response;
    }

    public function lookupRelationshipTypes(Request $request)
    {
        $post = $request->all();
        $code = !empty($post['code'])? $post['code']: null;
        $relationship_type = \App\Models\RelationshipType::query();
        $relationship_type = $relationship_type->select(['id', 'name'])->where('policy_part', 'Y');
        $relationship_type = $relationship_type->get();
        $msg = 'Found following organizations';
        if(!$relationship_type) {
            $relationship_type = [];
            $msg = 'No relationship type found';
        }
        
        $response = responseBuilder()->success($msg, $relationship_type, false);
        $this->urlComponents('Lookup Relationship Type', $response, 'LooK_UP_APIs');
        return $response;
    }

    public function lookupModules(Request $request)
    {
        $post = $request->all();
        $modules = \App\Models\Module::query();
        $modules = $modules->select(['id', 'title AS name']);
        $modules = $modules->where(['parent_id' => null, 'organization_level' => '1']);
        $modules = $modules->get();
        //echo "<pre>";print_r($modules);exit;

        foreach ($modules as $key => $module) {
            $module_children = \App\Models\Module::query();
            //print_r($module->id);exit;
            $module_children = $module_children->select(['id', 'title AS name', 'view_route']);
            $module_children = $module_children->where([
                                                    'parent_id' => $module->id,
                                                    'organization_level' => '1'
                                                ]);
            $module_children = $module_children->get();
            //echo "<pre>";print_r($module_children);exit;
            $module_children_arr = [];
            foreach ($module_children as $key => $module_child) {
                array_push($module_children_arr, $module_child);
            }
            $module['children'] = $module_children_arr;
        }

        $msg = 'Found following Modules';
        if(!$modules) {
            $modules = [];
            $msg = 'No module found';
        }
        
        $response = responseBuilder()->success($msg, $modules, false);
        $this->urlComponents('Lookup (Hospitals| Companies| Clinics| Pharmacies| Laboratories| Ambulance Services| Posture Cares| Homecare Elderly)', $response, 'LooK_UP_APIs');
        return $response;
    }
    
    public function updateFCM(Request $request) {
        $user = \Auth::user();
        $request->validate(['fcm_token' => 'required']);
        $post = $request->all();
        \App\Models\UserDevice::insertOrUpdate(['user_id' => $user->id, 'fcm_token' => $post['fcm_token']]);
        $response = responseBuilder()->success('FCM token updated successfully');
        $this->urlComponents('Update FCM Token', $response, 'Update_User_Profile');
        return $response;
    }
    
    
    public function getUserViralAccounts() {
        $user = \Auth::user();
        $params = Input::get();
        $message = 'No data found';
        $viralProfilesUserId = $user->user_viral_profiles()->select(['viral_profile_id'])->whereIn('status', ['Approved', 'Decline'])->get()->toArray();
        $viralProfilesUserId = array_column($viralProfilesUserId, 'viral_profile_id');
        if(!empty($params['status'])){
            $viralAccounts = User::select(['id'])->where(['is_deleted'=> '0', 'is_active' => '1'])
                    ->where('cnic', 'LIKE', '%_'.$user->cnic)
                    ->whereNotIn('id', $viralProfilesUserId)->count();
//            $viralAccounts = \App\Models\MedicalRecord::select(['huid'])
//                    ->where(['relationship_id' => $user->id, 'is_personal' => 'N', 'is_public' => 'Y'])
//                    ->distinct('relationship_id')->count('relationship_id');

            $message = 'No viral profile to claim';
            $status = false;
            if($viralAccounts>0){
                $message = 'Found viral profiles';
                $status = TRUE;
            }
            $data= ['status' => $status, 'profile_count' => $viralAccounts];
            $response =responseBuilder()->success($message, $data);
            goto end;
        }
            $viralAccounts = User::select(['id', 'first_name', 'last_name', 'dob', 'gender', 'profile_pic'])->where(['is_deleted'=> '0', 'is_active' => '1'])
                    ->where('cnic', 'LIKE', '%_'.$user->cnic)->whereNotIn('id', $viralProfilesUserId)->get();
            
            $data =[];
            $userDP = getUserDP($user);
            foreach ($viralAccounts as $user) {
                $userData = $user->toArray();
                $userData['age'] = ageCalculator($user->dob);
                $userData['profile_pic'] = $userDP['pic'];
                $userData['profile_pic_thumb'] = $userDP['thumb'];
                $userData['name'] = $user->first_name.' '.$user->last_name;
                $data[] = $userData;
            }
            $message='Your viral profile is owning following peoples';
            $response =responseBuilder()->success($message, $data, false);
        
        end:
        $this->urlComponents('Viral Profile Status & Viral Profiles', $response, 'User_Management');
        return $response;
    }
    
    public function getMedicalLabTest() {
        $params = Input::get();
        $testsObj = \App\Models\MedicalLabTest::select(['test_name', 'unit'])->distinct();
                if(!empty($params['test'])){
                    $testsObj = $testsObj->where('test_name', 'LIKE', "%{$params['test']}%");
                }
            $tests =    $testsObj->get()->toArray();
                
        $response = responseBuilder()->success('Lab test listing..', $tests, false);
        $this->urlComponents('Medical Lab Tests', $response, 'LooK_UP_APIs');
        return $response;   
    }   
    
    public function validateToken() {
        $user = \Auth::user();
        $response = responseBuilder()->success('User is authenticated');
        $this->urlComponents('Auth Token Check', $response, 'API_Token_Check');
        return $response;
    }
}
