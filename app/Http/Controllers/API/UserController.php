<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Http\Requests\SignupRequest;
use Carbon\Carbon;
use App\User;
use App\Models\UserProfile;
use App\Models\RoleUser;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use App\Models\UserDevice;
use App\Models\UserViralProfile;

//
use Session;
use Excel;
use File;

class UserController extends Controller {

    use \App\Traits\WebServicesDoc;
    use \Illuminate\Foundation\Auth\SendsPasswordResetEmails,
        \App\Traits\EMails,        \App\Traits\FCM;

    public function __construct() {
        $this->middleware('auth', ['except' => ['userWebLogin', 'userLogin', 'userRegister', 'resetPassword', 'sendResetLinkEmail']]);
    }

    public function userRegister(SignupRequest $request) {
        $request->validate(['cnic' => ['regex:/^[1-9][0-9]*$/']]);
        $post = $request->all();
        $post['password'] = bcrypt($post['password']);

        if (!empty($post['dob'])) {
            $dobRaw = Carbon::parse($post['dob']);
            $post['dob'] = $dobRaw->format('Y-m-d');
        }
        $cnicLastDigit = substr($post['cnic'], -1);
        $post['gender'] = ($cnicLastDigit%2==0)?'Female':'Male';
        $resetToken = User::getToken($post['email']);
        $post['verification_code'] =route('user.verified_email_address', ['token' => $resetToken]);
        $user = User::create($post);
        event(new \Illuminate\Auth\Events\Registered($user));
        dispatch(new \App\Jobs\SendVerificationEmail($user));
        $role_user = new RoleUser();
        $role_user->user_id = $user['id'];

        $role = \App\Models\Role::where(['is_deleted' => '0', 'code' => config('app.hospitallCodes')['user']])->first();
        $role_user->role_id = $role->id; // default role as user
        $role_user->save();
        $userData = User::find($user['id']);
        if (!empty($post['fcm_token'])) {
            $userDevices = ['user_id' => $userData->id, 'fcm_token' => $post['fcm_token']]; //, 'device_id' => $post['device_id']
            UserDevice::insertOrUpdate($userDevices);
        }
        $userData = User::formatUserData($userData);
        $response = responseBuilder()->setAuthorization($user->createToken('MyApp')->accessToken)->success('You have succesfully signup.', $userData);
        $this->urlComponents('Signup', $response, 'Signup');
        return $response;
    }

    public function userLogin(\App\Http\Requests\LoginRequest $request) {
        $POST = $request->all();
        $POST['remember'] = $POST['remember'] ?? false;
        if (!empty($POST['fcm_token'])) {
            $fcmToken = $POST['fcm_token'];
            unset($POST['fcm_token']);
        }
        if ($request->has('fb_id')) {
            $data = User::loginWithFB($POST);
        } else if ($request->has('phone')) {
            $data = User::loginWithAccountKit($POST);
        } else if ($request->has('google_plus_id')) {
            $data = User::loginWithGooglePlus($POST);
        } else {
            $POST['loginMode'] = (in_array('cnic', array_keys($POST))) ? 'cnic' : 'email';
            $data = User::loginWithCredentials($POST);
        }

        if (isset($data['status']) && $data['status'] !== true) {
            return responseBuilder()->error($data['message'], $data['code']);
        }
        $Data = (!empty($data['data'])) ? $data['data'] : [];
        if (!empty($fcmToken)) {
            $userDevices = ['user_id' => $Data->id, 'fcm_token' => $fcmToken];
            UserDevice::insertOrUpdate($userDevices);
        }
        $response = responseBuilder()->setAuthorization($data['token'])->success($data['message'], $Data);
        $this->urlComponents('Login With Email & Password', $response, 'Login');
        return $response;
    }

    public function userWebLogin(\App\Http\Requests\LoginRequest $request) {
        $POST = $request->all();
        $POST['remember'] = $POST['remember'] ?? false;
        /*if (!empty($POST['fcm_token'])) {
            $fcmToken = $POST['fcm_token'];
            unset($POST['fcm_token']);
        }*/
        
        $POST['loginMode'] = 'email';
        $data = User::loginWebAdmin($POST);

        if (isset($data['status']) && $data['status'] !== true) {
            return responseBuilder()->error($data['message'], $data['code']);
        }
        $Data = (!empty($data['data'])) ? $data['data'] : [];
        /*if (!empty($fcmToken)) {
            $userDevices = ['user_id' => $Data->id, 'fcm_token' => $fcmToken];
            UserDevice::insertOrUpdate($userDevices);
        }*/
        $response = responseBuilder()->setAuthorization($data['token'])->success($data['message'], $Data);
        $this->urlComponents('Login With Email & Password', $response, 'Login');
        return $response;
    }

    public function resetPassword(Request $request) {
        $request->validate(['cnic' => 'bail|required|digits:13']);
        $post = $request->all();
        $user = User::where(['cnic' => $post['cnic'], 'is_deleted' => '0'])->first();
        if ($user['is_active'] == '0') {
            return responseBuilder()->error('Your account has been de-activated, Please contact to system administrator');
        }
        if (empty($user['email'])) {
            return responseBuilder()->error('Sorry we are unable to find account regarding your provided CNIC');
        }

        $EmailTemplate = \App\Models\Template::where(['is_active' => '1', 'is_deleted' => '0', 'code' => 'forgot_password'])->first();
        $randNumber = str_random(8) . '-' . time();
        $encodeTokenUrl = base64_encode($randNumber);
        \Mail::send('emails.common', array('emailContent' => $encodeTokenUrl), function($message) use ($user) {
            $message->to($user->email, $user->first_name)->subject('Welcome!');
        });
        dump($user->email);
        dump($encodeTokenUrl);
        die;
    }

    public function sendResetLinkEmail(Request $request) {
        $this->validate($request, ['email' => 'email', 'cnic' => 'required|digits:13'], ['cnic.required' => 'CNIC cannot be empty', 'cnic.digits' => 'Invalid CNIC please enter a valid 13 Digit CNIC Number']);
        $email = $request->get('cnic');
        $cnic = $request->get('cnic');
        $userData = User::select(['email'])->where(['cnic' => $cnic, 'is_deleted' => '0', 'is_active' => '1'])->first();
        if (empty($userData)) {
            return responseBuilder()->error('Couldn\'t find your Account', 400);
        }
        $email = $userData['email'];
        if (empty($email)) {
            return responseBuilder()->error('We are unable to find your email, please contact to adminstrator to reset password', 400);
        }
        $response = $this->broker()->sendResetLink(['email' => $email]);
        $response = Password::RESET_LINK_SENT ? responseBuilder()->success(trans($response)) : responseBuilder()->error(trans($response));
        $this->urlComponents("Forgot passowrd/Reset password", $response, 'Forgot_Password');
        return $response;
    }
    
    public function sendVerificationLinkEmail(Request $request) {
        $this->validate($request, ['email' => 'required|email']);
        $email = $request->get('email');
        $user = User::where(['email' => $email, 'email_verified' => 'N', 'is_deleted' => '0'])->first();
        if(empty($user)){
            return responseBuilder()->error('Unable to verify the given email address');
        }
        //if(empty($user->verification_code)){
            $resetToken = User::getToken($email);
            $oldToken = explode('/email-verified/', $user->verification_code);
            $resetToken = (count($oldToken)>1) ? end($oldToken) : $resetToken;
            $user->verification_code =route('user.verified_email_address', ['token' => $resetToken]);
            $user->save();
        //}
        event(new \Illuminate\Auth\Events\Registered($user));
        dispatch(new \App\Jobs\SendVerificationEmail($user));
        $response = responseBuilder()->success('An email has been sent to your account for verification');
        $this->urlComponents("Email Verification", $response, 'Verification_Email');
        return $response;
    }

    public function userLogout() {
        $params = \Illuminate\Support\Facades\Input::get();
        $user = \Auth::user();
        $accessToken = $user->token();
        \DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $accessToken->id)
                ->update([
                    'revoked' => true
        ]);
        if(empty($params['admin'])){
            $user->user_device()->delete();
        }
        $accessToken->revoke();
        $response = responseBuilder()->success('Logout successfull!');
        $this->urlComponents('Logout', $response, 'Logout');
        return $response;
    }

    public function requestForViralProfile(Request $request) {
        $user = \Auth::user();
        $request->validate(['user_id' => 'required|exists:users,id']);
        $code = 400;
        $message = 'Unable to process reqest, An Error occured';
        $id= $request->get('user_id');
        $status= $request->get('status');
        $ViralUserAccount = User::find($id);
        if(!$ViralUserAccount){
            $message = 'The profile you looking for is either deleted or invalid'; $code=404; 
            goto end;
        }
        $viralProfileCNIC = $ViralUserAccount->cnic;
        $tempArr = explode('_', $viralProfileCNIC);
        $viralProfileId = $tempArr[0];
        $status = ($status=='Decline') ? 'Decline': 'Pending';
        $viralProfileHolder = User::find($viralProfileId);
        if(!$viralProfileHolder){
            $message = 'This profile is no longer available'; $code=404; 
            goto end;
        }
        $userViralProifleData = ['viral_profile_id' => $ViralUserAccount->id,
            'status' => $status, 'created_by' => $user->id, 'updated_by' => $user->id];
        $userViralProifleData['owned_by'] = $viralProfileId;
        $userViralProfileObj = $user->user_viral_profiles();
        if($userViralProfileObj->where(['viral_profile_id' => $id])->whereIn('status', ['Pending', 'Approved'])->count()>0){
            $message = 'You have already responded for this profile.';
            goto end;
        }
        $viralprofileObj = new \App\Models\UserViralProfile($userViralProifleData);
        $res = $userViralProfileObj->save($viralprofileObj);
        if($res){
            if($status!=='Decline'){
                //send push to Viral_Profile_Holder
                $userDP = getUserDP($viralProfileHolder);
                $userName = (!empty($user->first_name)) ? $user->first_name .' '. $user->last_name : $user->cnic;
                $userAge = ageCalculator($viralProfileHolder->dob);
                    $pushNote = ['data' => ['title' => env('APP_NAME')." - Request for claim data",
                        'body' => "{$userName} want to claim data for profile ", 
                    'click_action' => 'REQUEST_OWN_VIRAL_PROFILE',
                                'subTitle' => 'Claim For Profile', 'name' => $userName, 'id' => $res->id, 
                    'age' => $userAge, 'profile_pic' => $userDP['pic'], 'profile_pic_thumb' => $user['thumb']],
                    'user_id' => $viralProfileHolder->id, 'created_by' => $user->id];
                $pushReceiver =$viralProfileHolder->toArray();
                $pushSender = $user->toArray();
                $this->sendNotification($pushReceiver, $pushSender, $pushNote);  
            }
            $response = responseBuilder()->success('You have sucessfully responded');
            $this->urlComponents('Respond Viral Profile(Accept/Reject)', $response, 'User_Management');
            return $response;
        }
        end:
        return responseBuilder()->error($message, $code);
    }
    
    public function respondViralProfile(Request $request, $id) {//viralProfileId
        $user = \Auth::user();
        $code = 400;
        $message = 'Unable to process reqest, An Error occured';
        $ViralUserAccount = UserViralProfile::find($id);
        if(empty($ViralUserAccount)){
            $message = 'You request has been expired';
            goto end;
        }
        $request->validate(['status' => 'required|in:Approved,Decline']);
        if($ViralUserAccount->status !== 'Pending'){
            $message = 'You have already responded for this request.';
            goto end;
        }
        $status = $request->get('status');
        $viralProfiler = User::findOrFail($ViralUserAccount->user_id);
        $res = $ViralUserAccount->update($request->only(['status']));
        if($res){
            \App\Models\NotificationHistory::where(['user_id' => $user, 'created_by' => $id, 'is_seen' => 'N'])
                    ->where('content', 'LIKE', '%REQUEST_OWN_VIRAL_PROFILE%')->update(['is_seen'=>'Y']);
            if($request->get('status')== 'Approved'){
                \App\Models\FamilyTree::where(['parent_user_id' => $ViralUserAccount->owned_by,
                    'associate_user_id' => $ViralUserAccount->viral_profile_id])
                        ->update(['is_claimed' => 'Y', 'updated_by' => $ViralUserAccount->owned_by]);
                $viralProfiler->update(['is_viral_claimed' => 'Y']);
                User::find($ViralUserAccount->viral_profile_id)->update(['is_viral_claimed' => 'Y']);
            }
            //send push to Viral_Profile_Holder
            $userName = (!empty($user->first_name)) ? $user->first_name .' '. $user->last_name : $user->cnic;
            $userAge = ageCalculator($viralProfiler->dob);
            $userDP = getUserDP($viralProfiler);
            $pushNote = ['data' => ['title' => env('APP_NAME')." - Request for claim data {$status}",
                'body' => "{$userName} has {$status} your data claim", 
            'click_action' => 'REPOND_OWN_VIRAL_PROFILE',
                        'subTitle' => 'Claim For Profile', 'name' => $userName, 'id' => $viralProfiler->id, 
            'age' => $userAge, 'relationship' => '', 'profile_pic' => $userDP['pic'], 'profile_pic_thumb' => $userDP['thumb']],
            'user_id' => $viralProfiler->id, 'created_by' => $user->id];
            $pushReceiver =$viralProfiler->toArray();
            $pushSender = $user->toArray();
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);  
            
            $response = responseBuilder()->success('You have sucessfully responded');
            $this->urlComponents('Respond Viral Profile(Accept/Reject)', $response, 'User_Management');
            return $response;
        }
        end:
        return responseBuilder()->error($message, $code);
    }

    public function store(\App\Http\Requests\User $request) {
        $user = \Auth::user();
        $post = $request->all();
        $superAdmin = User::isSuperAdmin($user);
        
        $business_pts = !empty($post['latlng']) ? $post['latlng'] : null;
        if (!empty($business_pts)) {
            $business_pts = str_replace(' ', '', $business_pts);
            $business_pts_arr = explode(',', $business_pts);
            $post['business_latitude'] = $business_pts_arr[0];
            $post['business_longitude'] = $business_pts_arr[1];
        }
        if (!empty($post['organization_id'])) {
            if ($superAdmin === false) {
                if (empty($post['employee_code'])) {
                    return responseBuilder()->error('employee_code is required', 400);
                } else {
                    $OrgUser_obj = \App\Models\OrganizationUser::where(['organization_id' => $post['organization_id'], 'employee_code' => $post['employee_code']])->first();
                    if (!empty($OrgUser_obj)) {
                        return responseBuilder()->error('employee_code must be unique', 400);
                    }
                }
                $post['opd_limit'] = (!empty($post['opd_limit'])) ? $post['opd_limit'] : 0;
                if ($post['opd_limit'] < 1000 || $post['opd_limit'] > 100000000) {
                    return responseBuilder()->error('OPD Limit must range (1000 - 99999999)', 400);
                }
                if (!empty($post['basic_salary'])) {
                    if ($post['basic_salary'] < 1000 || $post['basic_salary'] > 100000000) {
                        return responseBuilder()->error('Basic Salary must range (1000 - 99999999)', 400);
                    }
                }
                if (!empty($post['gross_salary'])) {
                    if ($post['gross_salary'] < 1000 || $post['gross_salary'] > 100000000) {
                        return responseBuilder()->error('Gross Salary must range (1000 - 99999999)', 400);
                    }
                }
            }
        }

        $randNumber = mt_rand(); //str_random(8);
        $post['created_by'] = $user->id;
        $post['updated_by'] = $user->id;
        $post['password'] = $post['activation_code']= $post['password'] ?? $randNumber;
        $post['password'] = bcrypt($post['password']);
        $resetToken = User::getToken($post['email']);
        $post['verification_code'] = route('user.verified_email_address', ['token' => $resetToken]);
        $post['registration_source'] = 'WebAdmin';
        $arrayKeys = array_keys($post);
        $profileItems = array_intersect(UserProfile::TB_FIELDS, $arrayKeys) ?? [];
        $countItems = count($profileItems);
        $newUser = User::create($post);
        event(new \Illuminate\Auth\Events\Registered($newUser));
        dispatch(new \App\Jobs\SendVerificationEmail($newUser));

        if (!empty($post['organization_id'])) {
            if ($superAdmin === false) {
                $time = now();
                $employee_code = $post['employee_code'];
                $grade_id = !empty($post['grade_id']) ? $post['grade_id'] : null;
                $basic_salary = !empty($post['basic_salary']) ? $post['basic_salary'] : 0;
                $gross_salary = !empty($post['gross_salary']) ? $post['gross_salary'] : 0;
                $ipd_limit = !empty($post['ipd_limit']) ? $post['ipd_limit'] : 0;
                $opd_limit = !empty($post['opd_limit']) ? $post['opd_limit'] : 0;
                $maternity_limit = !empty($post['maternity_limit']) ? $post['maternity_limit'] : 0;
                $maternity_csection_limit = !empty($post['maternity_csection_limit']) ? $post['maternity_csection_limit'] : 0;
                $maternity_room_limit = !empty($post['maternity_room_limit']) ? $post['maternity_room_limit'] : 0;
                $date_joining = !empty($post['date_joining']) ? $post['date_joining'] : null;
                $date_confirmation = !empty($post['date_confirmation']) ? $post['date_confirmation'] : null;
                $team = !empty($post['team']) ? $post['team'] : null;
            }
            else {
                $time = now();
                $org_users_count = \App\Models\OrganizationUser::where([
                                                'organization_id' => $post['organization_id'],
                                                'status' => 'Approved'
                                            ])
                                            ->count();
                $employee_code = $org_users_count+1;
                $employee_code = $employee_code."_autoassign";
                $grade_id = !empty($post['grade_id']) ? $post['grade_id'] : null;
                $basic_salary = !empty($post['basic_salary']) ? $post['basic_salary'] : 0;
                $gross_salary = !empty($post['gross_salary']) ? $post['gross_salary'] : 0;
                $ipd_limit = !empty($post['ipd_limit']) ? $post['ipd_limit'] : 0;
                $opd_limit = !empty($post['opd_limit']) ? $post['opd_limit'] : 0;
                $maternity_limit = !empty($post['maternity_limit']) ? $post['maternity_limit'] : 0;
                $maternity_csection_limit = !empty($post['maternity_csection_limit']) ? $post['maternity_csection_limit'] : 0;
                $maternity_room_limit = !empty($post['maternity_room_limit']) ? $post['maternity_room_limit'] : 0;
                $date_joining = !empty($post['date_joining']) ? $post['date_joining'] : null;
                $date_confirmation = !empty($post['date_confirmation']) ? $post['date_confirmation'] : null;
                $team = !empty($post['team']) ? $post['team'] : null;
            }

            if (\App\Models\OrganizationUser::where(['user_id' => $newUser->id, 'organization_id' => $post['organization_id'], 'status' => 'Approved'])->count() <= 0) {
                $organizationUsers = [
                    'user_id' => $newUser->id,
                    'organization_id' => $post['organization_id'],
                    'employee_code' => $employee_code,
                    'date_joining' => $date_joining,
                    'date_confirmation' => $date_confirmation,
                    'team' => $team,
                    'status' => 'Approved',
                    'grade_id' => $grade_id,
                    'basic_salary'  => $basic_salary,
                    'gross_salary'  => $gross_salary,
                    'ipd_limit'         => $ipd_limit,
                    'opd_limit'         => $opd_limit,
                    'maternity_limit'   => $maternity_limit,
                    'maternity_csection_limit' => $maternity_csection_limit,
                    'maternity_room_limit'  => $maternity_room_limit,
                    'is_default' => 'Y',
                    'created_at' => $time
                ];
                \App\Models\OrganizationUser::insert($organizationUsers);
            }

            if (!empty($post['role_id'])) { // here role_id must be the id of role with code orgAdmin
                $roles = \App\Models\Role::where('code', config('app.hospitallCodes')['hr'])
                        ->orWhere('code', config('app.hospitallCodes')['finance'])
                        ->get();
                $role_ids = [];
                foreach ($roles as $key => $role) {
                    array_push($role_ids, $role->id);
                }

                \App\Models\RoleUser::whereIn('role_id', $role_ids)->where('user_id', $newUser->id)->delete();
                $RoleUser = [
                    'user_id' => $newUser->id,
                    'role_id' => $post['role_id'],
                    //*
                    'status'  => 'Approved',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                \App\Models\RoleUser::insert($RoleUser);
            }
        }

        if ($countItems > 0) {
            $newUser->user_profile()->save(new UserProfile($post));
        }

        /* registering as doc/dentist */
        $code = !empty($post['role_code']) ? $post['role_code'] : null;
        if (!empty($code)) {
            $role = \App\Models\Role::where(['is_deleted' => '0', 'code' => $code])->first();
            $role_user = [
                'user_id' => $newUser->id,
                'role_id' => $role->id,
                'status'  => 'Approved',
                'created_at' => now(),
                'updated_at' => now()
            ];
            RoleUser::insert($role_user);
        }
        /**/

        if ($user) {
            if (!empty($code)) {
                $response = responseBuilder()->success('Record has been added successfully as ' . $code, $newUser->id);
            } else {
                $response = responseBuilder()->success('Record has been added successfully', $newUser->id);
            }

            $this->urlComponents('Add User', $response, 'User_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while saving', 400);
    }

    public function update(\App\Http\Requests\User $request, $id) {
        $user = User::findOrFail($id);
        $loginUser = \Auth::user();
        $superAdmin = User::isSuperAdmin($loginUser);
        $post = $request->all();
        $code = !empty($post['role_code']) ? $post['role_code'] : null;
        $doctorMsg = '.';
        $business_pts = !empty($post['latlng']) ? $post['latlng'] : null;
        if (!empty($business_pts)) {
            $business_pts = str_replace(' ', '', $business_pts);
            $business_pts_arr = explode(',', $business_pts);
            $post['business_latitude'] = $business_pts_arr[0];
            $post['business_longitude'] = $business_pts_arr[1];
        }
        
        $post['updated_by'] = $loginUser->id;
        $post['created_by'] = $loginUser->id;
        $post['created_at'] = time();
        $post['updated_at'] = time();

        $randNumber = str_random(8);
        if ($request->has('updatePassword') && $post['updatePassword'] == 'Y') {
            if(empty($post['password'])){
                $post['activation_code']  = $randNumber;
            }
            $post['password'] = $post['password'] ?? $randNumber;
            $post['password'] = bcrypt($post['password']);
        }
        $arrayKeys = array_keys($post);
        $profileItems = array_intersect(UserProfile::TB_FIELDS, $arrayKeys) ?? [];
        $countItems = count($profileItems);
        if((!empty($post['email'])) && $user->email !== $post['email']){
            $resetToken = User::getToken($post['email']);
            $post['verification_code'] =route('user.verified_email_address', ['token' => $resetToken]);
            $post['email_verified'] = 'N';
            event(new \Illuminate\Auth\Events\Registered($user));
            dispatch(new \App\Jobs\SendVerificationEmail($user));
        }
        //$user->update($post);
        //print_r("expression");exit;
        if (!empty($post['organization_id'])) {
            if ($superAdmin === false) {
                if (empty($post['employee_code'])) {
                    return responseBuilder()->error('employee_code is required', 400);
                } else {
                    $OrgUser_obj = \App\Models\OrganizationUser::where(['organization_id' => $post['organization_id'], 'employee_code' => $post['employee_code']])
                            ->where('user_id', '!=', $user->id)
                            ->first();
                    if (!empty($OrgUser_obj)) {
                        return responseBuilder()->error('employee_code must be unique', 400);
                    }
                }

                if ($post['opd_limit'] < 1000 || $post['opd_limit'] > 100000000) {
                    return responseBuilder()->error('OPD Limit must range (1000 - 99999999)', 400);
                }
                if (!empty($post['basic_salary'])) {
                    if ($post['basic_salary'] < 1000 || $post['basic_salary'] > 100000000) {
                        return responseBuilder()->error('Basic Salary must range (1000 - 99999999)', 400);
                    }
                }
                if (!empty($post['gross_salary'])) {
                    if ($post['gross_salary'] < 1000 || $post['gross_salary'] > 100000000) {
                        return responseBuilder()->error('Gross Salary must range (1000 - 99999999)', 400);
                    }
                }

                $organizationData = \App\Models\Organization::select(['id', 'name'])->where(['id' => $post['organization_id']])->first()->toArray();

                $org_user_obj = \App\Models\OrganizationUser::where(['user_id' => $user->id])
                        ->get();
                $time = now();
                $employee_code = $post['employee_code'];
                $grade_id = !empty($post['grade_id']) ? $post['grade_id'] : null;
                $basic_salary = !empty($post['basic_salary']) ? $post['basic_salary'] : 0;
                $gross_salary = !empty($post['gross_salary']) ? $post['gross_salary'] : 0;
                $ipd_limit = !empty($post['ipd_limit']) ? $post['ipd_limit'] : 0;
                $opd_limit = !empty($post['opd_limit']) ? $post['opd_limit'] : 0;
                $maternity_limit = !empty($post['maternity_limit']) ? $post['maternity_limit'] : 0;
                $maternity_csection_limit = !empty($post['maternity_csection_limit']) ? $post['maternity_csection_limit'] : 0;
                $maternity_room_limit = !empty($post['maternity_room_limit']) ? $post['maternity_room_limit'] : 0;
                if (!empty($org_user_obj[0])) {
                    $orgCurrentStatus = \App\Models\OrganizationUser::select(['employee_code', 'status'])
                                                     ->where('user_id', $org_user_obj[0]->user_id)
                                                     ->first();

                    //cannot update employee code if a claim has been made with the existing one
                    if ($orgCurrentStatus->employee_code != $employee_code) {
                        $existing_claim = \App\Models\MedicalClaim::where([
                                                'organization_id' => $org_user_obj[0]->organization_id,
                                                'employee_code'   => $orgCurrentStatus->employee_code
                                            ])
                                            ->first();
                        if (!empty($existing_claim)) {
                            return responseBuilder()->error('Employee code cannot be changed since claims have been filed using this Employee code.' , 400);
                        }
                    }

                    ///////////////////
                    $user->update($post);

                    \App\Models\OrganizationUser::where('user_id', $org_user_obj[0]->user_id)
                            ->update([
                                'organization_id' => $post['organization_id'],
                                'employee_code' => $employee_code,
                                'date_joining' => !empty($post['date_joining']) ? $post['date_joining'] : null,
                                'date_confirmation' => !empty($post['date_confirmation']) ? $post['date_confirmation'] : null,
                                'team' => !empty($post['team']) ? $post['team'] : null,
                                'grade_id' => $grade_id,
                                'basic_salary'  => $basic_salary,
                                'gross_salary'  => $gross_salary,
                                'ipd_limit'         => $ipd_limit,
                                'opd_limit'         => $opd_limit,
                                'maternity_limit'   => $maternity_limit,
                                'maternity_csection_limit'  => $maternity_csection_limit,
                                'maternity_room_limit'  => $maternity_room_limit,
                                'status' => 'Approved',
                                'is_default' => 'Y'
                    ]);
                    //================== push notification
                    $orgData = $user->organization_user()->first();
                    if(!empty($orgData)){
                        $org = $orgData->organization()->select(['name'])->first()->toArray();
                        $doctorMsg = " by {$org['name']}.";
                        $orgData = $orgData->toArray();
                        $orgData = array_merge($orgData, $org);
                    }
                    if(empty($code)){
                        $pushStatus = ($orgCurrentStatus->status == 'Pending') ? 'Request approved' : 'Record data updated';
                        $desigCaption = (!empty($org_user_obj[0]->designation)) ? " as {$org_user_obj[0]->designation}":'';
                        //$superAdmin = User::isSuperAdmin($loginUser);
                        $organizationName = ($superAdmin=== true) ? 'Hospitall Administrator': $organizationData['name'];
                        $subTitleCaption = ($superAdmin=== true) ? 'Profile Update': 'Request Company';
                        $titleCaption = ($superAdmin=== true) ? 'Hospitall Admin': 'Company';
                        $pushNote = ['data' => ['title' => env('APP_NAME')." - {$pushStatus} by {$titleCaption}",
                                'body' => ($orgCurrentStatus->status == 'Approved' || $superAdmin === true) ? "{$organizationName} has updated your profile info." : "Company has approved your Request. Now you are a Registered Employee of {$organizationData['name']}",//{$desigCaption} 
                            'click_action' => 'ORGANIZATION_LINK_RESPOND',
                                        'subTitle' => $subTitleCaption, 'organization' => $orgData],
                            'user_id' => $user->id, 'created_by' => $loginUser->id];
                        $pushReceiver =$user->toArray();
                        $pushSender = $loginUser->toArray();
                        $this->sendNotification($pushReceiver, $pushSender, $pushNote); 
                    }    
                } else {

                    ////////////////////////
                    $user->update($post);

                    $organizationUsers = [
                        'user_id' => $user->id,
                        'organization_id' => $post['organization_id'],
                        'employee_code' => $employee_code,
                        'basic_salary'  => $basic_salary,
                        'gross_salary'  => $gross_salary,
                        'ipd_limit'         => $ipd_limit,
                        'opd_limit'         => $opd_limit,
                        'maternity_limit'   => $maternity_limit,
                        'maternity_csection_limit' => $maternity_csection_limit,
                        'maternity_room_limit'  => $maternity_room_limit,
                        'grade_id' => $grade_id,
                        'status' => 'Approved',
                        'is_default' => 'Y',
                        'created_at' => $time,
                    ];
                    \App\Models\OrganizationUser::insert($organizationUsers);
                    //================== push notification
                     $orgData = $user->organization_user()->first();
                    if(!empty($orgData)){
                        $org = $orgData->organization()->select(['name'])->first()->toArray();
                        $doctorMsg = " by {$org['name']}.";
                        $orgData = $orgData->toArray();
                        $orgData = array_merge($orgData, $org);
                    }
                    if(empty($code)){
                        $pushNote = ['data' => ['title' => env('APP_NAME')." - Request approved by organization",
                                'body' => "Company has approved your Request. Now you are a Registered Employee of {$organizationData['name']}", 
                            'click_action' => 'ORGANIZATION_LINK_RESPOND',
                                        'subTitle' => 'Request Company', 'organization' =>$orgData],
                            'user_id' => $user->id, 'created_by' => $loginUser->id];
                        $pushReceiver =$user->toArray();
                        $pushSender = $loginUser->toArray();
                        $this->sendNotification($pushReceiver, $pushSender, $pushNote); 
                    }
                }

            }//superadmin check ends
            else {

                ///////////////////////////*
                if (empty($code)) {
                    # code...
                
                    $organizationData = \App\Models\Organization::select(['id', 'name'])->where(['id' => $post['organization_id']])->first()->toArray();

                    $org_user_obj = \App\Models\OrganizationUser::where(['user_id' => $user->id])
                            ->get();
                    $time = now();
                    $org_users_count = \App\Models\OrganizationUser::where([
                                                    'organization_id' => $post['organization_id'],
                                                    'status' => 'Approved'
                                                ])
                                                ->count();
                    $employee_code = $org_users_count+1;
                    $employee_code = $employee_code."_autoassign";
                    $grade_id = !empty($post['grade_id']) ? $post['grade_id'] : null;
                    $basic_salary = !empty($post['basic_salary']) ? $post['basic_salary'] : 0;
                    $gross_salary = !empty($post['gross_salary']) ? $post['gross_salary'] : 0;
                    $ipd_limit = !empty($post['ipd_limit']) ? $post['ipd_limit'] : 0;
                    $opd_limit = !empty($post['opd_limit']) ? $post['opd_limit'] : 0;
                    $maternity_limit = !empty($post['maternity_limit']) ? $post['maternity_limit'] : 0;
                    $maternity_csection_limit = !empty($post['maternity_csection_limit']) ? $post['maternity_csection_limit'] : 0;
                    $maternity_room_limit = !empty($post['maternity_room_limit']) ? $post['maternity_room_limit'] : 0;
                    if (!empty($org_user_obj[0])) {
                        $orgCurrentStatus = \App\Models\OrganizationUser::select(['employee_code', 'status'])
                                                         ->where('user_id', $org_user_obj[0]->user_id)
                                                         ->first();

                        $user->update($post);

                        \App\Models\OrganizationUser::where('user_id', $org_user_obj[0]->user_id)
                                ->update([
                                    'organization_id' => $post['organization_id'],
                                    'employee_code' => $employee_code,
                                    'date_joining' => !empty($post['date_joining']) ? $post['date_joining'] : null,
                                    'date_confirmation' => !empty($post['date_confirmation']) ? $post['date_confirmation'] : null,
                                    'team' => !empty($post['team']) ? $post['team'] : null,
                                    'grade_id' => $grade_id,
                                    'basic_salary'  => $basic_salary,
                                    'gross_salary'  => $gross_salary,
                                    'ipd_limit'         => $ipd_limit,
                                    'opd_limit'         => $opd_limit,
                                    'maternity_limit'   => $maternity_limit,
                                    'maternity_csection_limit' => $maternity_csection_limit,
                                    'maternity_room_limit'  => $maternity_room_limit,
                                    //'status' => 'Approved',
                                    'is_default' => 'Y'
                        ]);   
                    } else {

                        $user->update($post);

                        $organizationUsers = [
                            'user_id' => $user->id,
                            'organization_id' => $post['organization_id'],
                            'employee_code' => $employee_code,
                            'basic_salary'  => $basic_salary,
                            'gross_salary'  => $gross_salary,
                            'ipd_limit'         => $ipd_limit,
                            'opd_limit'         => $opd_limit,
                            'maternity_limit'   => $maternity_limit,
                            'maternity_csection_limit' => $maternity_csection_limit,
                            'maternity_room_limit'  => $maternity_room_limit,
                            'grade_id' => $grade_id,
                            'status' => 'Approved',
                            'is_default' => 'Y',
                            'created_at' => $time,
                        ];
                        \App\Models\OrganizationUser::insert($organizationUsers);
                    }
                    ///////////////////////////*

                    $pushStatus =  'Record data updated';
                    $organizationName = 'Hospitall Administrator';
                    $subTitleCaption = 'Profile Update';
                    $titleCaption =  'Hospitall Admin';
                    $orgData = $user->organization_user()->first();
                    $pushNote = ['data' => ['title' => env('APP_NAME')." - {$pushStatus} by {$titleCaption}",
                            'body' => "{$organizationName} has updated your profile info.", 
                        'click_action' => 'ORGANIZATION_LINK_RESPOND',
                                    'subTitle' => $subTitleCaption, 'organization' => $orgData],
                        'user_id' => $user->id, 'created_by' => $loginUser->id];
                    $pushReceiver =$user->toArray();
                    $pushSender = $loginUser->toArray();
                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                }
                else {
                    $user->update($post);
                }
                
                //$user->update($post);
            }

            $roles = \App\Models\Role::where('organization_id', $post['organization_id'])
                                       ->orWhere('code', config('app.hospitallCodes')['orgAdmin'])
                                       ->get();
            $role_ids = [];
            foreach ($roles as $key => $role) {
                array_push($role_ids, $role->id);
            }
            \App\Models\RoleUser::whereIn('role_id', $role_ids)->where('user_id', $user->id)->delete();

            if (!empty($post['role_id'])) { // here role_id must be the id of role with code orgAdmin
                $RoleUser = [
                    'user_id' => $user->id,
                    'role_id' => $post['role_id'],
                    'status'  => 'Approved',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                \App\Models\RoleUser::insert($RoleUser);
            }
        }
        else {
            ////////////////////////
            $user->update($post);
        }

        //print_r("expression");exit;

        // registering as doc/dentist
        if (!empty($code)) {
            $makeDoctor = true;

            if (!empty($post['role_codes']) && in_array($code, $post['role_codes'])) {
                $makeDoctor = false;
            }

            if ($makeDoctor) {
                $role = \App\Models\Role::where(['is_deleted' => '0', 'code' => $code])->first();
                $role_user = [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'status'  => 'Approved',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                RoleUser::insert($role_user);
                //================== push notification
                $tCaption = ucwords($code);
                $userRoles = $user->roles()->select(['id', 'title', 'code'])->get()->toArray();
                $pushNote = ['data' => ['title' => env('APP_NAME')." - Request approved for {$code}",
                    'body' => "Your request for registration as {$tCaption} has been approved. You are now a registered {$tCaption}", 
                'click_action' => 'DOCTOR_LINK_RESPOND',
                            'subTitle' => 'Register as '.$tCaption, 'userModel' => $userRoles ],
                'user_id' => $user->id, 'created_by' => $loginUser->id];
                $pushReceiver =$user->toArray();
                $pushSender = $loginUser->toArray();
                $this->sendNotification($pushReceiver, $pushSender, $pushNote);  
            }
            else {
                $role_approval = \App\Models\Role::where(['is_deleted' => '0', 'code' => $code])->first();
                $roleUserPvtObj = \App\Models\RoleUser::where([
                                            'user_id' => $user->id,
                                            'role_id' => $role_approval->id
                                        ]);
                $roleUserPvt = $roleUserPvtObj->first();
                $roleUserPvtObj->delete();
                $role_user_approval = [
                    'user_id' => $user->id,
                    'role_id' => $role_approval->id,
                    'status'  => 'Approved',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                RoleUser::insert($role_user_approval);

                 $captionTitleDr = 'Request approved';
//                $captionDr = "Your request for {$code} has been approved.";
                 $tCaption = ucwords($code);
                $captionDr ="Your request for registration as {$tCaption} has been approved. You are now a registered {$tCaption}";
                if($roleUserPvt->status == 'Approved'){
                    $captionTitleDr = 'Profile updated';
                    $captionDr = "Your {$code}'s profile has been updated.";
                }
                    $userRoles = $user->roles()->select(['id', 'title', 'code'])->get()->toArray();
                    $pushNote = ['data' => ['title' => env('APP_NAME')." - {$captionTitleDr} for {$code}",
                        'body' => $captionDr, 
                    'click_action' => 'DOCTOR_LINK_RESPOND', 'userModel' => $userRoles,
                                'subTitle' => 'Register as Doctor', ],
                    'user_id' => $user->id, 'created_by' => $loginUser->id];
                    $pushReceiver =$user->toArray();
                    $pushSender = $loginUser->toArray();
                    $this->sendNotification($pushReceiver, $pushSender, $pushNote);
            }
        }
        //////////////////

        if ($countItems > 0) {
            $userProfile = UserProfile::where('user_id', $user->id)->first();
            if ($userProfile) {
                $userProfile->update($post);
            }
            $response = responseBuilder()->success('Record has been updated successfully');
            $this->urlComponents('Update User', $response, 'User_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while updating', 400);
    }

    // register doc from mobile app
    public function updateUserfromApp(Request $request) {
        $user = \Auth::user();
        $post = $request->all();
        $user = User::findOrFail($user->id);
        $post['updated_by'] = $user->id;
        $post['created_by'] = $user->id;
        $user->update($post);

        /* registering as doc/dentist */
        $code = !empty($post['role_code'])? $post['role_code']: null;
        if (!empty($code)) {
            $makeDoctor = true;
            if(!empty($post['role_codes']) && in_array($code, $post['role_codes'])){
               $makeDoctor = false; 
            }            
            
            $role = \App\Models\Role::where(['is_deleted' => '0', 'code' => $code])->first();
            $role_user = \App\Models\RoleUser::where(['role_id' => $role->id, 'user_id' => $user->id])->first();

            if(empty($role_user)) {
                $role_user = [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    //*
                    //'status'  => 'Approved',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                   
                RoleUser::insert($role_user);

                $user_meta_arr = [];
                if (!empty($post['Specialization'])) {
                    foreach ($post['Specialization'] as $key => $value) {
                        $time = now();
                        if(!empty($value)) {
                            $user_meta_arr[] = [
                                'user_id'       => $user->id,
                                'type'          => 'Specialization',
                                'name'          => 'Specialization',
                                'meta_value'    => $value,
                                'created_at'    => $time,
                                'created_by'    => $user->id
                            ];
                        }
                    } 
                    \App\Models\UserMeta::insert($user_meta_arr);
                }

                $user_meta_arr = [];
                if (!empty($post['Qualification'])) {
                    foreach ($post['Qualification'] as $key => $value) {
                        $time = now();
                        if(!empty($value)) {
                            $user_meta_arr[] = [
                                'user_id'       => $user->id,
                                'type'          => 'Qualification',
                                'name'          => 'Qualification',
                                'meta_value'    => $value,
                                'created_at'    => $time,
                                'created_by'    => $user->id
                            ];
                        }
                    } 
                    \App\Models\UserMeta::insert($user_meta_arr);
                }

                $userData = \App\User::formatUserData($user);
                 $response = responseBuilder()->success('User registered as '.$code, $userData);
                $this->urlComponents('Register as doctor ', $response, 'User_Management');
                return $response;
                // return ['status' => true, 'status_code' => 200, 'message' => 'User registered as '.$code, 'data' => $userData];
            }
            else {
                // user is already doctor
                $userData = \App\User::formatUserData($user);
                 $response = responseBuilder()->success('User is already registered as '.$code, $userData);
                $this->urlComponents('Register as doctor ', $response, 'User_Management');
                return $response;
                //return ['status' => true, 'status_code' => 200, 'message' => 'User is already registered as '.$code, 'data' => $userData];
            }
        }
        return responseBuilder()->error('Something went wrong while updating', 400);
    }

    public function destroy($id) {
        $user = \Auth::user();
        $userData = User::findOrFail($id);
        $userData->is_deleted = '1';
        $userData->deleted_by = $user->id;
        // for soft delete users, append current timestamp so that unique constraint can be managed & upon adding the same fields(either cnic, email, contact_number, facebook_id, google_plus_id), we don't get prompt for uniqueness
        $date = date("D M d, Y G:i");
        $today = strtotime($date);
        $updateFields = ['cnic', 'email', 'contact_number', 'facebook_id', 'google_plus_id'];
        foreach ($updateFields as $updateField) {
            if (!empty($userData->$updateField)) {
                $userData->$updateField = $userData->$updateField . '_' . $today;
            }
        }
        $userData->save();
        if ($userData->delete()) {
            \App\Models\RoleUser::where(['user_id' => $userData->id])->delete();
            \App\Models\OrganizationUser::where('user_id', $userData->id)->delete();

            $response = responseBuilder()->success('Selected Record(s) have been deleted Successfully');
            $this->urlComponents('Delete User', $response, 'User_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while deleting', 400);
    }

    public function deleteUser(Request $request) {
        $post = $request->all();
        $role_code = !empty($post['role_code']) ? $post['role_code'] : null;
        if (!empty($role_code)) {
            $role = \App\Models\Role::where(['code' => $role_code])->first();
        }

        $organization_id = !empty($post['organization_id']) ? $post['organization_id'] : null;
        $user_Ids = (is_array($post['user_ids']) ? $post['user_ids'] : [$post['user_ids']]);
        $user = \Auth::user();
        if (empty($organization_id)) {
            foreach ($user_Ids as $user_id) {
                if (!empty($role)) {
                    \App\Models\RoleUser::where(['user_id' => $user_id, 'role_id' => $role->id])->delete();
                    if (!empty($role)) {
                        // Push Notification
                        //$push4User = User::formatUserData($user, false);
                        $orgUser = User::find($user_id);
                        $userRoles = $orgUser->roles()->select(['id', 'title', 'code'])->get()->toArray();
                        $pushNote = ['data' => ['title' => env('APP_NAME')." - Unregistered {$role->title}",
                            'body' => "You have been unregistered from {$role->title} list", 
                        'click_action' => 'DOCTOR_UNLINK_RESPOND',
                                    'subTitle' => 'Register as Doctor', 'userModel' => $userRoles],
                         'user_id' => $user_id, 'created_by' => $user->id];
                        $pushReceiver =['id' => $user_id];
                        $pushSender = $user->toArray();
                        $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                    }
                } else {
                    $userData = \App\User::findOrFail($user_id);
                    //$userData_roles = $userData->roles->toArray();
                    //print_r($userData_roles[0]['pivot']);exit;
                    $userData->is_deleted = '1';
                    $userData->deleted_by = $user->id;
                    // for soft delete users, append current timestamp so that unique constraint can be managed & upon adding the same fields(either cnic, email, contact_number, facebook_id, google_plus_id), we don't get prompt for uniqueness
                    $date = date("D M d, Y G:i");
                    $today = strtotime($date);
                    $updateFields = ['cnic', 'email', 'contact_number', 'facebook_id', 'google_plus_id'];
                    foreach ($updateFields as $updateField) {
                        if (!empty($userData->$updateField)) {
                            $userData->$updateField = $userData->$updateField . '_' . $today;
                        }
                    }
                    $userData->save();
                    $userData->delete();
                    \App\Models\RoleUser::where(['user_id' => $userData->id])->delete();
                    $organization_user = \App\Models\OrganizationUser::where([
                                                                        'user_id' => $userData->id
                                                                    ])
                                                                    ->first();
                    if (!empty($organization_user)) {
                        $organization_user->delete();
                        // Push Notification
                        //$push4User = User::formatUserData($user, false);
                        $organizationData = \App\Models\Organization::select(['id', 'name'])->where(['id' => $organization_user->organization_id])->first()->toArray();
                        $designationCaption = (!empty($organization_user->designation)) ? ' as '.$organization_user->designation:'';
                        $pushNote = ['data' => ['title' => env('APP_NAME')." - Request rejected by organization",
                            'body' => "{$organizationData['name']} has decline your request{$designationCaption}", 
                        'click_action' => 'ORGANIZATION_UNLINK_RESPOND',
                                    'subTitle' => 'Request for Company', //'user' => $push4User
                                    ],
                         'user_id' => $organization_user->user_id, 'created_by' => $user->id];
                        $pushReceiver =['id' => $organization_user->user_id];
                        $pushSender = $user->toArray();
                        $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                    }
                    
                     if (!empty($role)) {
                        // Push Notification
                        //$organizationData = \App\Models\Organization::select(['id', 'name'])->where(['id' => $organization_user->organization_id])->first()->toArray();
                        //$push4User = User::formatUserData($user, false);
                        $orgUser = User::find($organization_user->user_id);
                        $userRoles = $orgUser->roles()->select(['id', 'title', 'code'])->get()->toArray();
                        $pushNote = ['data' => ['title' => env('APP_NAME')." - Unregistered {$role->title}",
                            'body' => "You have been unregistered from {$role->title} list", 
                        'click_action' => 'DOCTOR_UNLINK_RESPOND',
                                    'subTitle' => 'Unregister as Doctor', 'user' => $userRoles],
                         'user_id' => $organization_user->user_id, 'created_by' => $user->id];
                        $pushReceiver =['id' => $organization_user->user_id];
                        $pushSender = $user->toArray();
                        $this->sendNotification($pushReceiver, $pushSender, $pushNote);
                    }
                }
            }
            if (!empty($role)) {
                $response = responseBuilder()->success("Selected Record(s) have been unregisted Successfully");
                $this->urlComponents('Records Unregistered', $response, 'User_Management');
                return $response;
            }
            return responseBuilder()->success("Selected Record(s) have been deleted Successfully");
        }

        $org_roles = \App\Models\Role::select(['id'])
                                    ->where('organization_id', $organization_id)
                                    ->orWhere('code', config('app.hospitallCodes')['orgAdmin'])
                                    ->get();
        $org_roles_arr = [];
        foreach ($org_roles as $key => $org_role) {
            array_push($org_roles_arr, $org_role->id);
        }

        foreach ($user_Ids as $user_id) {
            \App\Models\OrganizationUser::where([
                                            'user_id'         => $user_id,
                                            'organization_id' => $organization_id
                                        ])
                                        ->delete();
            \App\Models\RoleUser::where('user_id', $user_id)
                                  ->whereIn('role_id', $org_roles_arr)
                                  ->delete();
            $organizationData = \App\Models\Organization::select(['id', 'name'])
                    ->where(['id' => $organization_id])->first()->toArray();
                        $pushNote = ['data' => ['title' => env('APP_NAME')." - Profile Update",
                            'body' => "You have been unlinked from organization: {$organizationData['name']}", 
                        'click_action' => 'ORGANIZATION_UNLINK_RESPOND',
                                    'subTitle' => 'Request for Company',
                                    ],
                         'user_id' => $user_id, 'created_by' => $user->id];
                        $pushReceiver =['id' => $user_id];
                        $pushSender = $user->toArray();
                        $this->sendNotification($pushReceiver, $pushSender, $pushNote);
            
        }
        $response = responseBuilder()->success("Selected Record(s) have been unlinked Successfully");
        $this->urlComponents('Records unlinked', $response, 'User_Management');
        return $response;
    }

    public function registerDoctor(Request $request) {
        $post = $request->all();
        $user_id = $post['user_id'];
        $code = $post['code'];
        $user = \App\User::where('is_deleted', '0')->findOrFail($user_id);
        $role = \App\Models\Role::where(['is_deleted' => '0', 'code' => $code])->first();
        $role_user_count = \App\Models\RoleUser::where(['user_id' => $user_id, 'role_id' => $role->id])->count();
        if ($role_user_count > 0) {
            return responseBuilder()->error('Sorry, user has already been registered as doctor', 400);
        }
        $role_user = [
            'user_id' => $user_id,
            'role_id' => $role->id
        ];
        RoleUser::insert($role_user);
        $user_meta = $user->user_meta->toArray();
        $data = ['user_id' => $user_id];
        foreach ($user_meta as $key => $value) {
            $data[$value['type']][] = ["name" => $value['name'], "value" => $value['meta_value']];
        }
        $response = responseBuilder()->success("following user_meta found", $data, false);
        $this->urlComponents('Register Doctor', $response, 'User_Management');
        return $response;
    }

    public function addUserMeta(Request $request) {
        $user = \Auth::user();
        $post = $request->all();
        $user_id = $post['user_id'];
        $ignoreList_arr = ['user_id'];
        $user_meta_arr = [];
        foreach ($post as $type => $user_meta) {
            if (!in_array($type, $ignoreList_arr)) {
                \App\Models\UserMeta::where(['user_id' => $user_id, 'type' => $type])->delete();
                foreach ($user_meta as $key => $value) {
                    $time = now();
                    if (!empty($value)) {
                        $user_meta_arr[] = [
                            'user_id' => $user_id,
                            'type' => $type,
                            'name' => $type,
                            'meta_value' => $value,
                            'created_at' => $time,
                            'created_by' => $user->id
                        ];
                    }
                }
            }
        }
        if (\App\Models\UserMeta::insert($user_meta_arr)) {
            $response = responseBuilder()->success("User metdata added successfully");
            $this->urlComponents('Add User Meta', $response, 'User_Management');
            return $response;
        }
    }

    public function getUserMeta(Request $request) {
        $post = $request->all();
        $user_id = $post['user_id'];
        $type = $post['type'];
        $user_meta = \App\Models\UserMeta::select('meta_value')
                ->where(['user_id' => $user_id, 'type' => $type])
                ->get()
                ->toArray();
        $data = [];
        foreach ($user_meta as $key => $value) {
            array_push($data, $value['meta_value']);
        }
        $response = responseBuilder()->success("following user_meta found", $data, false);
        $this->urlComponents('Get User Meta', $response, 'User_Management');
        return $response;
    }

    public function show($id) {
        $userData = User::select(['id', 'first_name', 'last_name', 'dob', 'gender', 'contact_number', 'email', 'cnic', 'address', 'business_address', 'latitude', 'longitude', 'business_latitude', 'business_longitude', 'business_timing', 'medical_council_no'])
                ->where('is_deleted', '0')
                //->with('organizations')
                //->with('city.state.country')
                //->with('user_profile')
                ->with('roles')
                ->findOrFail($id);

        if (!empty($userData['business_latitude'])) {
            $userData['latlng'] = $userData['business_latitude'] . ', ' . $userData['business_longitude'];
            unset($userData['business_latitude']);
            unset($userData['business_longitude']);
        }

        $roles_arr = [];
        foreach ($userData['roles'] as $key => $role) {
            array_push($roles_arr, $role['code']);
        }

        $userData['role_codes'] = $roles_arr;
        unset($userData['roles']);

        if ($userData['organizations'] && count($userData['organizations'])) {
            $user_obj = \App\Models\Organization::userOrganization($userData, false);
            $userData['organization_id'] = $userData['organizations'][0]->id;
            $userData['employee_code'] = !empty($user_obj->organization_user[0]->employee_code) ? $user_obj->organization_user[0]->employee_code : null;
            $userData['basic_salary'] = !empty($user_obj->organization_user[0]->basic_salary) ? $user_obj->organization_user[0]->basic_salary : null;
            $userData['gross_salary'] = !empty($user_obj->organization_user[0]->gross_salary) ? $user_obj->organization_user[0]->gross_salary : null;
            $userData['ipd_limit'] = !empty($user_obj->organization_user[0]->ipd_limit) ? $user_obj->organization_user[0]->ipd_limit : null;
            $userData['opd_limit'] = !empty($user_obj->organization_user[0]->opd_limit) ? $user_obj->organization_user[0]->opd_limit : null;
            $userData['maternity_limit'] = !empty($user_obj->organization_user[0]->maternity_limit) ? $user_obj->organization_user[0]->maternity_limit : null;
            $userData['maternity_csection_limit'] = !empty($user_obj->organization_user[0]->maternity_csection_limit) ? $user_obj->organization_user[0]->maternity_csection_limit : null;
            $userData['maternity_room_limit'] = !empty($user_obj->organization_user[0]->maternity_room_limit) ? $user_obj->organization_user[0]->maternity_room_limit : null;
            $userData['date_joining'] = !empty($user_obj->organization_user[0]->date_joining) ? $user_obj->organization_user[0]->date_joining : null;
            $userData['date_confirmation'] = !empty($user_obj->organization_user[0]->date_confirmation) ? $user_obj->organization_user[0]->date_confirmation : null;
            $userData['team'] = !empty($user_obj->organization_user[0]->team) ? $user_obj->organization_user[0]->team : null;
            $userData['grade_id'] = !empty($user_obj->organization_user[0]->grade_id) ? $user_obj->organization_user[0]->grade_id : null;
            $userData['grade_title'] = !empty($user_obj->organization_user[0]->grade_id) ? $user_obj->organization_user[0]->grade->title : null;

            $user_roles = $userData['roles']->toArray();
            $user_roles_arr = [];
            foreach ($user_roles as $key => $user_role) {
                array_push($user_roles_arr, $user_role['id']);
            }

            $roles = \App\Models\Role::select(['id', 'title AS name'])
                    ->where('is_deleted', '0')
                    ->where('organization_id', $userData['organization_id'])
                    ->orWhere('code', config('app.hospitallCodes')['orgAdmin'])
                    ->orderBy('created_at', 'DESC')
                    ->get();

            $roles_arr = [];
            foreach ($roles as $key => $role) {
                array_push($roles_arr, $role->id);
            }

            $result = array_intersect($user_roles_arr, $roles_arr);
            if (!empty($result)) {
                foreach ($result as $key => $value) {
                    $userData['role_id'] = $value;
                }
            }
        }
        //$userData['dob'] = $userData['dob']->format('Y-m-d');
        /* dump(date('Y-m-d', $unixTime));
          dump($userData['dob']);
          die; */
        unset($userData['organizations']);
        //$userData['dob'] = date_format($userData['dob'], "Y-m-d"); 

        $response = responseBuilder()->success('user details here', $userData->toArray());
        $this->urlComponents('Details of User', $response, 'User_Management');
        return $response;
    }

    public function getUsers(Request $request)
    {
        //Superadmin should not be a doctor otherwise data key will be an object in response
        $post = $request->all();
        $user = \Auth::user();
        $superAdmin = \App\User::getSuperAdmin();
        $org_id = !empty($post['organization_id']) ? $post['organization_id'] : null;

        $per_page = 10;
        $page_no = !empty($post['page'])? $post['page']-1: 0;
        $offset = $page_no * $per_page;
        $query = "";
        $query_count_total = "";

        $org_id_flag = false;
        $role_code_key_flag = false;
        $oUserModel = new User();
        $userHiddenFields = $oUserModel->getHidden();

        if (empty($org_id)) {
            $role_code_key = !empty($post['filters']['role_code']) ? $post['filters']['role_code'] : null;
            $role_user_status = !empty($post['filters']['role_user_status']) ? $post['filters']['role_user_status'] : 'Approved';
            if (empty($role_code_key)) {
                $query             .= "SELECT * ";
                $query_count_total .= "SELECT count(*) AS user_count ";

                $query_string = "FROM `users`
                                 WHERE `users`.`is_deleted` = '0'
                                 AND `users`.`is_viral` = 'N'
                                 AND `users`.`id` != '{$superAdmin->id}' ";
                $query             .= $query_string;
                $query_count_total .= $query_string;
            }
            else {
                $role_code_key_flag = true;
                $query             .= "SELECT * ";
                $query_count_total .= "SELECT count(*) AS user_count ";
                $query_string = "FROM `role_user`, `roles`, `users`
                                 WHERE  `users`.`id` = `role_user`.`user_id`
                                 AND `users`.`is_deleted` = '0'
                                 AND `users`.`is_viral` = 'N'
                                 AND `users`.`id` != '{$superAdmin->id}'
                                 AND `roles`.`id` = `role_user`.`role_id` ";
                if (is_array($role_code_key)) {

                    $query_string .= "AND `roles`.`code` IN ('".implode(',', $role_code_key)."') ";
                }
                else {
                    $query_string .= "AND `roles`.`code` = '{$role_code_key}' ";
                }
                                 
                $query             .= $query_string;
                $query_count_total .= $query_string;

                $query             .= "AND `role_user`.`status` = '{$role_user_status}' ";
                $query_count_total .= "AND `role_user`.`status` = '{$role_user_status}' ";
            }
        }
        else {
            $org_id_flag = true;
            $role_code_key = !empty($post['filters']['role_code']) ? $post['filters']['role_code'] : null;
            $orgUser_status = !empty($post['filters']['status']) ? $post['filters']['status'] : null;
            
            $role_user_status = !empty($post['filters']['role_user_status']) ? $post['filters']['role_user_status'] : 'Approved';

            if (empty($role_code_key)) {
                $query             .= "SELECT * ";
                $query_count_total .= "SELECT count(*) AS user_count ";
                $query_string = "FROM `organizations` ,`organization_user`, `users`
                                 WHERE `users`.`id` = `organization_user`.`user_id`
                                 AND `users`.`is_deleted` = '0'
                                 AND `users`.`is_viral` = 'N'
                                 AND `users`.`id` != '{$superAdmin->id}'
                                 AND `organizations`.`id` = `organization_user`.`organization_id`
                                 AND `organization_user`.`organization_id` = {$org_id} ";
                $query             .= $query_string;
                $query_count_total .= $query_string;
                if (!empty($orgUser_status)) {
                    $query             .= "AND `organization_user`.`status` = '{$orgUser_status}' ";
                    $query_count_total .= "AND `organization_user`.`status` = '{$orgUser_status}' ";
                }
                else {
                    $query             .= "AND `organization_user`.`status` = 'Approved' ";
                    $query_count_total .= "AND `organization_user`.`status` = 'Approved' ";
                }
            }
            else {
                $query             .= "SELECT * ";
                $query_count_total .= "SELECT count(*) AS user_count ";
                $query_string = "FROM `organizations`, `organization_user`, `role_user`, `roles`, `users`
                                 WHERE `users`.`id` = `organization_user`.`user_id`
                                 AND `users`.`id` = `role_user`.`user_id`
                                 AND `roles`.`id` = `role_user`.`role_id` ";

                if (is_array($role_code_key)) {
                    $query_string .= "AND `roles`.`code` IN ('".implode(',', $role_code_key)."') ";
                }
                else {
                    $query_string .= "AND `roles`.`code` = '{$role_code_key}' ";
                }

                    $query_string .= "AND `users`.`is_deleted` = '0'
                                      AND `users`.`is_viral` = 'N'
                                    AND `users`.`id` != '{$superAdmin->id}'
                                    AND `organizations`.`id` = `organization_user`.`organization_id`
                                    AND `organization_user`.`organization_id` = {$org_id} ";
                $query             .= $query_string;
                $query_count_total .= $query_string;
                if (!empty($orgUser_status)) {
                    $query             .= "AND `organization_user`.`status` = '{$orgUser_status}' ";
                    $query_count_total .= "AND `organization_user`.`status` = '{$orgUser_status}' ";
                }
                else {
                    $query             .= "AND `organization_user`.`status` = 'Approved' ";
                    $query_count_total .= "AND `organization_user`.`status` = 'Approved' ";
                }
                $query             .= "AND `role_user`.`status` = '{$role_user_status}' ";
                $query_count_total .= "AND `role_user`.`status` = '{$role_user_status}' ";
            }   
        } 

        if (!empty($post['searchFilters'])) {
            $organization_users_cols = ['employee_code'];
            $i = 0;
            foreach ($post['searchFilters'] as $key => $value) {
                if (!in_array($key, $organization_users_cols)) {
                    if ($i == 0) {
                        $query             .= "AND ( `users`.{$key} LIKE '%{$value}%'";
                        $query_count_total .= "AND ( `users`.{$key} LIKE '%{$value}%'";
                    }
                    else {
                        $query             .= "OR `users`.{$key} LIKE '%{$value}%'";
                        $query_count_total .= "OR `users`.{$key} LIKE '%{$value}%'";
                    }
                    $i++;
                }
            }
            
            $i = 0;
            foreach ($post['searchFilters'] as $key => $value) {
                if (in_array($key, $organization_users_cols)) {
                    if ($i == 0) {
                        $query             .= "OR ( `organization_user`.{$key} LIKE '%{$value}%'";
                        $query_count_total .= "OR ( `organization_user`.{$key} LIKE '%{$value}%'";
                    }
                    else {
                        $query             .= "OR `organization_user`.{$key} LIKE '%{$value}%'";
                        $query_count_total .= "OR `organization_user`.{$key} LIKE '%{$value}%'";
                    }
                    $i++;
                }
            }
            $query .= ($org_id_flag)? ")) ": ") ";
            $query_count_total .= ($org_id_flag)? ")) ": ") ";
        }

        if (!empty($post['filters'])) {
            $organization_users_cols = ['grade_id', 'designation'];
            $filter_flag = false;
            
            foreach ($post['filters'] as $key => $value) {
                if (in_array($key, $organization_users_cols)) {
                    if (is_array($value)) {
                        if (count($value) > 0) {
                            //print_r(implode(',', $value));exit;
                            $filter_flag = true;
                            $query             .= "AND ( `organization_user`.{$key} IN (".implode(',', $value).") ";
                            $query_count_total .= "AND ( `organization_user`.{$key} IN (".implode(',', $value).") ";
                            
                            //print_r("expression");exit;
                            
                        }
                    } else {
                        //print_r("expression");exit;
                        $query             .= "AND `organization_user`.{$key} = '{$value}' ";
                        $query_count_total .= "AND `organization_user`.{$key} = '{$value}' ";
                        
                    }
                }
            }
            $query .= ($filter_flag)? ") ": " ";
            $query_count_total .= ($filter_flag)? ") ": " ";
        }

        if (!empty($post['sorter'])) {
            $field = $post['sorter']['field'];
            $order = $post['sorter']['order'];
            $db_order = "ascend" == $order ? "ASC" : "DESC";
            //////////////////
            if (isset($post['report'])) {
                $report = $post['report'];
                if (isset($report['start_date']) && isset($report['end_date'])) {
                    $start_date = $report['start_date'];
                    $end_date = $report['end_date'];
                    $query .= "AND created_at BETWEEN '{$start_date}' AND '{$end_date}' 
                                ORDER BY `users`.{$field} {$db_order}";
                }
                elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                    $start_date = $report['start_date'];
                    $query .= "AND created_at >= '{$start_date}'
                                ORDER BY `users`.{$field} {$db_order}";
                }
                elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                    $end_date = $report['end_date'];
                    $query .= "AND created_at <= '{$end_date}'
                                ORDER BY `users`.{$field} {$db_order}";
                }
                elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                    $query .= "ORDER BY `users`.{$field} {$db_order}";
                }
            }
            else {
                $query .= "ORDER BY `users`.{$field} {$db_order}
                                LIMIT {$per_page} OFFSET {$offset}";
            }
            //////////////////
        }
        else {
            //////////////////
            if (isset($post['report'])) {
                $report = $post['report'];
                if (isset($report['start_date']) && isset($report['end_date'])) {
                    $start_date = $report['start_date']." 00:00:00";
                    $end_date = $report['end_date']." 23:59:59";
                    $query .= "AND `users`.`created_at` BETWEEN '{$start_date}' AND '{$end_date}' 
                                ORDER BY `users`.`created_at` DESC";
                }
                elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                    $start_date = $report['start_date']." 00:00:00";
                    $query .= "AND `users`.`created_at` >= '{$start_date}'
                                ORDER BY `users`.`created_at` DESC";
                }
                elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                    $end_date = $report['end_date']." 23:59:59";
                    $query .= "AND `users`.`created_at` <= '{$end_date}'
                                ORDER BY `users`.`created_at` DESC";
                }
                elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                    $query .= "ORDER BY `users`.`created_at` DESC";
                }
            }
            else {
                $query .= "ORDER BY `users`.`created_at` DESC
                                LIMIT {$per_page} OFFSET {$offset}";
            }
            //////////////////
        }

        //print_r($query);exit;

        $users = \DB::select($query);

        //echo "<pre>";print_r($users);exit;


        $users_count = \DB::select($query_count_total);
        
        $last_page = $users_count[0]->user_count/$per_page;
        $last_page = ceil($last_page);
        $path = url()->current();
        $next_page_url = $path."?page_no=".($page_no+2);
        $prev_page_url = $path."?page_no=".(($page_no>1)?$page_no:1);
        $from = ((($page_no>1)?$page_no:0)*$per_page)+1;
        $to = ($from - 1) + count($users);
        if (isset($users) && count($users)<$per_page) {
            $next_page_url = "";
        }

        $superAdminKey = -1;
        foreach ($users as $key => $user) {
            $role_users = \App\Models\RoleUser::where('user_id', $user->id)->get();
            $role_codes_arr = [];
            foreach ($role_users as $key => $role_user) {
                if (!empty($role_user) && !empty($role_user->role)) {
                    array_push($role_codes_arr, $role_user->role->code);
                    if("superAdmin"==$role_user->role->code) {
                        $superAdminKey = $key;
                    }

                    // user administrative role under organization
                    if(!empty($role_user->role->organization_id) || !strcasecmp($role_user->role->code, config('app.hospitallCodes')['orgAdmin'])) {
                        $user->administrative_role['code'] = $role_user->role->code;
                        $user->administrative_role['title'] = $role_user->role->title;
                    }
                    ///////////////
                }
            }
            $user->role_codes = $role_codes_arr;

            if (!empty($user->grade_id)) {
                $user_grade = \App\Models\Grade::findOrFail($user->grade_id);
                $user->grade = $user_grade->title;
            }
        }

        $total = $users_count[0]->user_count;
        
        $msg = 'Following users are found';
        if (!$users) {
            $users = [];
            $msg = 'No users found';
        }
        if(!empty($users)){
            foreach ($users as $key => $user) {
                foreach ($user as $inx => $row) {
                   if(in_array($inx, $userHiddenFields)){
                       unset($user->$inx);
                   }
                }
                $users[$key] = $user;
            }
        }
        $response = responseBuilder()->success($msg, [
                                                    'data'          => $users,
                                                    'current_page'  => $page_no+1,
                                                    'per_page'      => $per_page,
                                                    'from'          => $from,
                                                    'last_page'     => $last_page,
                                                    'next_page_url' => $next_page_url,
                                                    'path'          => $path,
                                                    'prev_page_url' => $prev_page_url,
                                                    'to'            => $to,
                                                    'total'         => $total

                                                ], false);

        $this->urlComponents('List of all Users', $response, 'User_Management');
        return $response;
    }
}
