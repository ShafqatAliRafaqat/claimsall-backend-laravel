<?php

namespace App;

use Reliese\Database\Eloquent\Model as Eloquent;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\MailResetPasswordToken;
use App\Models\OrganizationUser;
use Illuminate\Support\Facades\Input;

class User extends Authenticatable {

    use \Illuminate\Database\Eloquent\SoftDeletes;
    use HasApiTokens,
        Notifiable;

    const ALLOWED_LOGIN_TYPES = ['cnic', 'email', 'facebook_id', 'google_plus_id'];

    protected $casts = [
        'policy_id' => 'int',
        'latitude' => 'float',
        'longitude' => 'float',
        'attempts' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $dates = [
        //'dob',
        'last_login'
    ];
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'activation_code',
        'remember_token'
    ];
    protected $fillable = [
        'first_name',
        'last_name',
        'designation',
        'contact_verified',
        'contact_number',
        'policy_id',
        'email',
        'email_verified',
        'cnic',
        'dob',
        'gender',
        'blood_group',
        'medical_conditions',
        'profile_pic',
        'verification_code',
        'activation_code',
        'facebook_id',
        'google_plus_id',
        'twitter_id',
        'instagram_id',
        'latitude',
        'longitude',
        'business_latitude',
        'business_longitude',
        'business_timing',
        'medical_council_no',
        'registration_source',
        'user_status',
        'attempts',
        'address',
        'business_address',
        'city_id',
        'password',
        'is_active',
        'created_by',
        'updated_by',
        'viral_account_id',
        'deleted_by',
        'remember_token',
        'last_login',
        'reset_token', 
        'reset_time',
        'is_viral',
        'is_viral_claimed'
    ];
    
    public function organization_policy() {
        return $this->belongsTo(\App\Models\OrganizationPolicy::class, 'policy_id');
    }

    public function role_user() {
        return $this->belongsTo(\App\Models\RoleUser::class, 'id', 'user_id');
    }

    public function document_access_lists() {
        return $this->hasMany(\App\Models\DocumentAccessList::class, 'shared_user_id');
    }

    public function documents() {
        return $this->hasMany(\App\Models\Document::class, 'owner_id');
    }

    public function email_templates() {
        return $this->hasMany(\App\Models\EmailTemplate::class);
    }

    public function emergency_contacts() {
        return $this->hasMany(\App\Models\EmergencyContact::class);
    }

    public function family_trees() {
        return $this->hasMany(\App\Models\FamilyTree::class, 'parent_user_id');
    }

    public function health_monitoring_data() {
        return $this->hasMany(\App\Models\HealthMonitoringData::class);
    }

    public function medical_claims() {
        return $this->hasMany(\App\Models\MedicalClaim::class);
    }

    public function medical_records() {
        return $this->hasMany(\App\Models\MedicalRecord::class);
    }

    public function orders() {
        return $this->hasMany(\App\Models\Order::class);
    }

    public function organizations() {
        return $this->belongsToMany(\App\Models\Organization::class)
                        ->withPivot(['status', 'grade_id', 'designation', 'employee_code', 'is_default'])
                        ->withTimestamps();
    }

    public function policy_covered_people() {
        return $this->hasMany(\App\Models\PolicyCoveredPerson::class);
    }

    public function roles() {
        return $this->belongsToMany(\App\Models\Role::class)
                        ->withPivot(['status']);
    }

    public function user_meta() {
        return $this->hasMany(\App\Models\UserMeta::class);
    }

    public function user_notifications() {
        return $this->hasMany(\App\Models\UserNotification::class);
    }

    public function user_profile() {
        return $this->hasOne(\App\Models\UserProfile::class, 'user_id');
    }

    public function city() {
        return $this->belongsTo(\App\Models\City::class);
    }

    public function organization_user() {
        return $this->hasMany(\App\Models\OrganizationUser::class);
    }
    
    public function notifications_history() {
        return $this->hasMany(\App\Models\NotificationHistory::class);
    }
    
    public function unseen_notifications_history() {
        return $this->notifications_history()->where('is_seen', '=', 'N');
    }

    public function user_device() {
        return $this->hasOne(\App\Models\UserDevice::class, 'user_id');
    }

    public function user_viral_profiles()
	{
		return $this->hasMany(\App\Models\UserViralProfile::class);
	}

    public static function getSuperAdmin() {

        $rolesQueryWhere = function($rolesQuery) {
            $rolesQuery->where(['code' => config('app.hospitallCodes')['superAdmin']]);
        };
        return self::with([
                            'roles' => $rolesQueryWhere,
                        ])
                        ->whereHas('roles', $rolesQueryWhere)
                        ->first();
    }
    
    public static function __HUID($userId=null) {
        if(intval($userId)>0){
            $user = self::findOrFail($userId);
        }else{
            $user = \Auth::user();
        }
        $v1 = env('H_');
        $v2 = env('H__');
        $v3 = env('H___');
        $__ = $user->$v1;
        $plainV1 = explode('_', $__);
        if(isset($plainV1[1])){
            $__ = $plainV1[1];
        }
        $___ = $user->$v2;
        $____ = strtotime($user->$v3);
        return ['user' => $user, '__' => (bin2hex($user->id) . base_convert($__, 10, 16) . '-' . bin2hex($___) . '-' . $____)];
    }
    
    public static function __HUID_($users) {
        $v1 = env('H_');
        $v2 = env('H__');
        $v3 = env('H___');
        $huids = [];
        foreach ($users as $user) {
            $__ = $user[$v1];
            $plainV1 = explode('_', $__);
            if(isset($plainV1[1])){
                $__ = $plainV1[1];
            }
            $___ = $user[$v2];
            $____ = strtotime($user[$v3]);
            $huids[] = (bin2hex($user['id']) . base_convert($__, 10, 16) . '-' . bin2hex($___) . '-' . $____);
        }
        return $huids;
    }
    
    public static function getToken($str)
    {
        $strArr = explode('@', $str);
        $encStr = base64_encode($strArr[0]).str_random(40);
        return hash_hmac('sha256', $encStr, config('app.key'));
    }

    public static function loginWithFB($post) {
        $fbAppId = env('FB_APP_ID');
        $fbAccessTokenURI = 'https://graph.facebook.com/oauth/access_token?client_id=' . env('FB_APP_ID') . '&client_secret=' . env('FB_SECRET') . '&grant_type=client_credentials';
        $jResponse = getDataByCURL($fbAccessTokenURI);
        if (empty($jResponse->access_token)) {
            return ['message' => $jResponse->error->message, 'code' => $jResponse->error->code, 'status' => false];
        }

        $fbRequestURI = "https://graph.facebook.com/debug_token?input_token={$post['access_token']}&access_token={$jResponse->access_token}";
        $jResponse = getDataByCURL($fbRequestURI);
        if (!empty($jResponse->error)) {
            return ['message' => $jResponse->error->message, 'code' => $jResponse->error->code, 'status' => false];
        }
        $data = $jResponse->data;
        return self::loginWithCredentials(['facebook_id' => $data->user_id, 'remember' => $post['remember'],  'loginMode'=> 'facebook_id'], true);
    }

    public static function loginWithAccountKit($post) {
        //$cleanedPhoneNumber = str_replace([' ', '-', '+'], [''], $post['phone']);
        //$cleanedPhoneNumber = ltrim($cleanedPhoneNumber, 0);

        $fbAppId = env('FB_APP_ID');
        $accessTokenURI = 'https://graph.accountkit.com/v1.3/me/?access_token=' . $post['access_token'];
        $jResponse = getDataByCURL($accessTokenURI);
        if (empty($jResponse->phone)) {
            return ['message' => $jResponse->error->message, 'code' => 421, 'status' => false];
        }
        if ($jResponse->application->id !== $fbAppId) {
            return ['message' => 'Invalid application id', 'code' => 421, 'status' => false];
        }
        $completePhoneNumber = $jResponse->phone->number;
        $phoneNumber = $jResponse->phone->national_number;
        $phoneNumber = ltrim($phoneNumber, 0);
        $user = User::where('contact_number', 'like', "{$completePhoneNumber}")->where('contact_verified', 'Y')->first();

        if (!$user) {
            return ['status' => false, 'code' => 401, 'message' => 'The phone number you’re trying to sign in with is not linked. Please signup first using your CNIC/Email and then go to profile section to link phone number'];
        }
        $token = $user->createToken('hospitall')->accessToken;
        $userData = self::formatUserData($user);
        return ['status' => true, 'code' => 200, 'message' => 'You have succesfully loggedin', 'token' => $token, 'data' => $userData];
    }

    public static function loginWithGooglePlus($post) {

        $gpAppId = env('GPLUS_APP_ID');
        $accessTokenURI = 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $post['access_token'];
        //$accessTokenURI = 'https://www.googleapis.com/oauth2/v3/tokeninfo?access_token='.$post['access_token'];
        $jResponse = getDataByCURL($accessTokenURI);
        if (empty($jResponse->sub)) {
            return ['message' => ($jResponse->error_description ?? 'Something went wrong'), 'code' => 421, 'status' => false];
        }
        if ($jResponse->aud !== $gpAppId) {
            return ['message' => 'Invalid application id', 'code' => 421, 'status' => false];
        }
        if ($jResponse->sub !== $post['google_plus_id']) {
            return ['message' => 'Invalid google credentials', 'code' => 401, 'status' => false];
        }

        $user = User::where('google_plus_id', $jResponse->sub)->first();
        if (!$user) {
            return ['status' => false, 'code' => 401, 'message' => 'The social account you’re trying to sign in with is not linked. Please signup first using your CNIC/Email and then go to profile section to link social accounts'];
        }
        $token = $user->createToken('hospitall')->accessToken;
        $userData = self::formatUserData($user);
        return ['status' => true, 'code' => 200, 'message' => 'You have succesfully login', 'token' => $token, 'data' => $userData];
    }

    public static function loginWithCredentials($POST, $isSocial = false)
    {
        $loginMode = $POST['loginMode'] ?? 'email';
        if (!in_array($loginMode, self::ALLOWED_LOGIN_TYPES)) {
            return ['status' => false, 'message' => 'Login method is not as described; Invalid login attempt', 'code' => 400];
        }
        $vpnKey = $POST['vpnKey'] ?? '';
//        $POST['is_active'] = 1;
        $rememberMe = $POST['remember'] ?? false;
        unset($POST['remember'], $POST['loginMode'], $POST['vpnKey']);
        if ($isSocial === true) {
            $user = User::where($POST)->first();
            if (!$user) {
                return ['status' => false, 'code' => 401, 'message' => 'The social account you’re trying to sign in with is not linked. Please signup first using your CNIC/Email and then go to profile section to link social accounts'];
            }
            if ($user && ($user->is_active==0)) {
                return ['status' => false, 'code' => 401, 'message' => 'Your account has been blocked. Please contact to hospitAll administrator at: '.env('APP_ADMIN_EMAIL')];
            }
            goto end;
        }
        if (\Auth::attempt($POST, $rememberMe) === false) {
            self::updateFailAttempts($POST);
            return ['status' => false, 'code' => 401, 'message' => 'Invalid Username or password. Please enter valid username and password.'];
        }
        $user = \Auth::user();
        if ($user && ($user->is_active==0)) {
            return ['status' => false, 'code' => 401, 'message' => 'Your account has been blocked. Please retry after 15 minutes.'];
        }
        if($loginMode == 'email' && $user->email_verified == 'N'){
            return ['status' => false, 'code' => 401, 'message' => 'Login failed, Please verify your email first'];
        }

        end:
        $token = $user->createToken('hospitall')->accessToken;

        $userRole = 'user';
        if ($loginMode === 'email') {
            $userData = $user->roles()->get()->toArray();
            //dd($userData);
            $userRole = (isset($userData[0]['code'])) ? $userData[0]['code'] : 'user';
            if ($userRole === 'superAdmin' && env('vpnKey') !== $vpnKey) {
                return ['status' => false, 'code' => 401, 'message' => "You are not permit to access as Super Admin from public Internet"];
            }
        }
        $userData = self::formatUserData($user);
        return ['status' => true, 'code' => 200, 'message' => 'You have successfully login', 'token' => $token,
            'data' => $userData];
    }

    public static function updateFailAttempts(array $where)
    {
        unset($where['password'], $where['is_active']);
       $userObj = self::where($where)->first();
       if($userObj){
           $attempts = ($userObj->attempts>=0) ? $userObj->attempts +1 : 0;
           if($attempts>5){
                return $userObj->update(['is_active' => '0', 'updated_at' => now()]);
           }
           return $userObj->update(['attempts' => $attempts,  'updated_at' => now()]);
       }

    }

    public static function loginWebAdminCopy($POST, $isSocial = false) {
        $loginMode = $POST['loginMode'] ?? 'email';
        if (!in_array($loginMode, self::ALLOWED_LOGIN_TYPES)) {
            return ['status' => false, 'message' => 'Login method is not as described; Invalid login attempt', 'code' => 400];
        }
        if($loginMode == 'email'){
            $POST['email_verified'] = 'Y';
        }
        $vpnKey = $POST['vpnKey'] ?? '';
//        $POST['is_active'] = 1;
        $rememberMe = $POST['remember'] ?? false;
        unset($POST['remember'], $POST['loginMode'], $POST['vpnKey']);
        
        if (\Auth::attempt($POST, $rememberMe) === false) {
            self::updateFailAttempts($POST);
            return ['status' => false, 'code' => 401, 'message' => 'Invalid user credentials'];
        }
        $user = \Auth::user();
        if ($user && ($user->is_active==0)) {
            return ['status' => false, 'code' => 401, 'message' => 'Your account has been blocked. Please retry after 15 minutes.'];
        }
        $superAdmin = self::isSuperAdmin($user);
        if ($superAdmin === false) {
            $org_data = \App\Models\Organization::userDefaultOrganization($user, false);
            if (empty($org_data)) {
                return ['status' => false, 'code' => 404, 'message' => 'You are not authorized to access this panel'];
            }
        }

        $token = $user->createToken('hospitall')->accessToken;

        $userRole = 'user';
        if ($loginMode === 'email') {
            $userData = $user->roles()->get()->toArray();
            //dd($userData);
            $userRole = (isset($userData[0]['code'])) ? $userData[0]['code'] : 'user';
            if ($userRole === 'superAdmin' && env('vpnKey') !== $vpnKey) {
                return ['status' => false, 'code' => 401, 'message' => "You are not permit to access as Super Admin from public Internet"];
            }
        }
        $userData = self::formatUserData($user);

        if ($superAdmin === false && empty($userData->adminPanelMenu)) {
            return ['status' => false, 'code' => 404, 'message' => 'You are not authorized to access this panel'];
        }

        return ['status' => true, 'code' => 200, 'message' => 'You have succesfully login', 'token' => $token,
            'data' => $userData];
    }

    public static function loginWebAdmin($POST, $isSocial = false)
    {
        $loginMode = $POST['loginMode'] ?? 'email';
        if (!in_array($loginMode, self::ALLOWED_LOGIN_TYPES)) {
            return ['status' => false, 'message' => 'Login method is not as described; Invalid login attempt', 'code' => 400];
        }
        $vpnKey = $POST['vpnKey'] ?? '';
//        $POST['is_active'] = 1;
        $rememberMe = $POST['remember'] ?? false;
        unset($POST['remember'], $POST['loginMode'], $POST['vpnKey']);
        if ($isSocial === true) {
            $user = User::where($POST)->first();
            if (!$user) {
                return ['status' => false, 'code' => 401, 'message' => 'This Social Account is not Registered with the System. Please link this Account with you Profile to Sign In via this Account'];
            }
            goto end;
        }
        //attempts check
        if(User::where(['email' => $POST['email'], 'is_active' => '0'])->first()){
            return ['status' => false, 'code' => 401, 'message' => 'Your account has been blocked. Please retry after 15 minutes.'];
        }

        if (\Auth::attempt($POST, $rememberMe) === false) {
            self::updateFailAttempts($POST);
            return ['status' => false, 'code' => 401, 'message' => 'Invalid Username or password. Please enter valid username and password.'];
        }
        $user = \Auth::user();
        if($loginMode == 'email' && $user->email_verified == 'N'){
            return ['status' => false, 'code' => 401, 'message' => 'Login failed, Please verify your email first'];
        }

        ///
        $superAdmin = self::isSuperAdmin($user);
        if ($superAdmin === false) {
            $org_data = \App\Models\Organization::userDefaultOrganization($user, false);
            if (empty($org_data)) {
                return ['status' => false, 'code' => 404, 'message' => 'You are not authorized to access this panel'];
            }
        }
        ///

        end:
        $token = $user->createToken('hospitall')->accessToken;

        $userRole = 'user';
        if ($loginMode === 'email') {
            $userData =$userRolesData = $user->roles()->get()->toArray();
            $userRole = (isset($userData[0]['code'])) ? $userData[0]['code'] : 'user';
            if ($userRole === 'superAdmin' && env('vpnKey') !== $vpnKey) {
                return ['status' => false, 'code' => 401, 'message' => "You are not permit to access as Super Admin from public Internet"];
            }
        }
        $userData = self::formatUserData($user);

        if(count($userRolesData)>1){
            $rolesArr = array_column($userRolesData, 'code');
            $rolesArrFinal = array_diff($rolesArr, ['user', 'doctor']);
            $rolesArrFinal = array_values($rolesArrFinal);
            $userRole = $rolesArrFinal[0] ?? 'user';
            $user->userRole = $userRole;
        }
        ///
        if ($superAdmin === false && empty($userData->adminPanelMenu)) {
            return ['status' => false, 'code' => 404, 'message' => 'You are not authorized to access this panel'];
        }
        ///

        return ['status' => true, 'code' => 200, 'message' => 'You have succesfully login', 'token' => $token,
            'data' => $userData];
    }

    public function getStats_currDay($type) {  // stats for each hour of day individually
        $count_arr = \DB::select("SELECT HOUR(created_at) as hour, COUNT(created_at) as count
                                        FROM " . $type . "
                                        WHERE DAY(created_at) = DAY(NOW())
                                        AND is_deleted = '0'
                                        GROUP BY HOUR(created_at)
                                        ORDER BY HOUR(created_at)");
        $result = [];
        foreach ($count_arr as $key => $value) {
            if ($value->hour > 12) {
                $value->hour = (($value->hour) - 12) . ' PM';
            } else {
                $value->hour = $value->hour . ' AM';
            }
            $result[$value->hour] = intval($value->count);
        }
        return $result;
    }

    public function getStats_currWeek($type) { // stats for each day of week individually
        $count_arr = \DB::select("SELECT 
                                   SUM(DATE(created_at) = CURDATE() - INTERVAL 6 DAY) AS last_7_days,
                                   SUM(DATE(created_at) = CURDATE() - INTERVAL 5 DAY) AS last_6_days,
                                   SUM(DATE(created_at) = CURDATE() - INTERVAL 4 DAY) AS last_5_days,
                                   SUM(DATE(created_at) = CURDATE() - INTERVAL 3 DAY) AS last_4_days,
                                   SUM(DATE(created_at) = CURDATE() - INTERVAL 2 DAY) AS last_3_days,
                                   SUM(DATE(created_at) = CURDATE() - INTERVAL 1 DAY) AS last_2_days,
                                   SUM(DATE(created_at) = CURDATE()) AS today,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 6 DAY), 1, 3) AS last_7_dayname,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 5 DAY), 1, 3) AS last_6_dayname,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 4 DAY), 1, 3) AS last_5_dayname,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 3 DAY), 1, 3) AS last_4_dayname,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 2 DAY), 1, 3) AS last_3_dayname,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 1 DAY), 1, 3) AS last_2_dayname,
                                    SUBSTR(DAYNAME(CURDATE() - INTERVAL 0 DAY), 1, 3) AS last_1_dayname
                                        FROM " . $type . " WHERE is_deleted = '0';");
        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->last_1_dayname] = intval($value->today);
            $result[$value->last_2_dayname] = intval($value->last_2_days);
            $result[$value->last_3_dayname] = intval($value->last_3_days);
            $result[$value->last_4_dayname] = intval($value->last_4_days);
            $result[$value->last_5_dayname] = intval($value->last_5_days);
            $result[$value->last_6_dayname] = intval($value->last_6_days);
            $result[$value->last_7_dayname] = intval($value->last_7_days);
        }
        return $result;
    }

    public function getStats_currMonth($type) { // stats for each day of month individually
        $count_arr = \DB::select("SELECT MONTHNAME(NOW()) AS month, DAY(created_at) AS day, COUNT(created_at) AS count
                                        FROM " . $type . "
                                        WHERE MONTH(created_at) = MONTH(NOW())
                                        AND is_deleted = '0'
                                        GROUP BY DAY(created_at)
                                        ORDER BY DAY(created_at);");

        $result = [];
        foreach ($count_arr as $key => $value) {
            $result[$value->day . ' ' . $value->month] = intval($value->count);
        }
        return $result;
    }

    public function getStats_total($type) { // 
        $count = \DB::select("SELECT count(*) AS total FROM " . $type . " WHERE is_deleted = '0'");
        return $count[0]->total;
    }

    public function getServiceProviderStats() { // Stats for serviceProviders
        $roles_count_arr = \DB::select("SELECT ru.`status`, r.`title` AS role, COUNT(u.`id`) AS total
                                            FROM users u, role_user ru, roles r
                                            WHERE u.`id` = ru.`user_id`
                                            AND r.`id` = ru.`role_id`
                                            AND u.`is_deleted` = '0'
                                            AND u.`deleted_at` IS NULL
                                            AND r.`code` = 'doctor'
                                            GROUP BY r.`title`, r.`code`, ru.`status`;");

        //echo "<pre>";print_r($roles_count_arr);exit;
        $result = [];
        foreach ($roles_count_arr as $key => $value) {
            $result[$value->status.' doctor'] = $value->total;
        }
        
        $service_providers_arr = \DB::select("SELECT ot.`name` AS service_provider, COUNT(o.`id`) AS total
                                            FROM organizations o, organization_type ot
                                            WHERE o.`organization_type_id` = ot.`id`
                                            AND o.`is_deleted` = '0'
                                            AND o.`deleted_at` IS NULL
                                            AND ot.`code` IN ('".config('app.hospitallCodes')['hospital']."', '".config('app.hospitallCodes')['clinic']."', '".config('app.hospitallCodes')['pharmacy']."', '".config('app.hospitallCodes')['lab']."')
                                            GROUP BY ot.`name`;");
        foreach ($service_providers_arr as $key => $value) {
            $result[$value->service_provider] = $value->total;
        }
        return $result;
    }

    public function getServiceProviderStats1() { // Stats for serviceProviders

        /*$query = "SELECT   
                      r.`title` AS role,
                      ru.`status` AS role_status,
                      COUNT(u.`id`) AS total, DATE(ru.`created_at`) AS currDate
                    FROM
                      users u,
                      role_user ru,
                      roles r 
                    WHERE u.id = ru.`user_id` 
                      AND r.id = ru.`role_id` 
                      AND u.`is_deleted` = '0' 
                      AND u.`deleted_at` IS NULL 
                      AND r.`code` = 'doctor'";

        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";
                $query .= "WHERE ru.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY r.`title`, ru.`status`, DATE(ru.`created_at`);";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $query .= "WHERE ru.`created_at` >= '{$start_date}'
                            GROUP BY r.`title`, ru.`status`, DATE(ru.`created_at`);";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $query .= "WHERE ru.`created_at` <= '{$end_date}'
                            GROUP BY r.`title`, ru.`status`, DATE(ru.`created_at`);";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $query .= "GROUP BY r.`title`, ru.`status`, DATE(ru.`created_at`);";
            }
        }
        else {
            //print_r("expression");exit;
            $query .= "GROUP BY r.`title`, ru.`status`, DATE(ru.`created_at`);";
        }*/

        /*$roles_count_arr = \DB::select($query);

        //echo "<pre>";print_r($roles_count_arr);exit;

        $result = [];
        foreach ($roles_count_arr as $key => $value) {
            $result[$value->currDate][$value->role][$value->role_status] = $value->total;
        }  */     

        //echo "<pre>";print_r($result);exit;

        $sp_query = "SELECT ot.`code`, o.`status`, COUNT(o.`id`) AS newRecords, DATE(o.`created_at`) currDate
                      FROM organizations o, organization_type ot
                      WHERE o.`organization_type_id` = ot.`id` ";

        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("both ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";
                $sp_query .= "AND o.`created_at` BETWEEN '{$start_date}' AND '{$end_date}'
                            GROUP BY DATE(o.`created_at`), o.`status`, ot.`code`;";
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("start_date ");exit;
                $start_date = $report['start_date']." 00:00:00";
                $sp_query .= "AND o.`created_at` >= '{$start_date}'
                            GROUP BY DATE(o.`created_at`), o.`status`, ot.`code`;";
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                //print_r("end_date ");exit;
                $end_date = $report['end_date']." 23:59:59";
                $sp_query .= "AND o.`created_at` <= '{$end_date}'
                            GROUP BY DATE(o.`created_at`), o.`status`, ot.`code`;";
            }
            elseif (!isset($report['start_date']) && !isset($report['end_date'])) {
                //print_r("both not ");exit;
                $sp_query .= "GROUP BY DATE(o.`created_at`), o.`status`, ot.`code`;";
            }
        }
        else {
            //print_r("expression");exit;
            $sp_query .= "GROUP BY DATE(o.`created_at`), o.`status`, ot.`code`;";
        }

        //print_r($sp_query);exit;

        $service_providers_arr = \DB::select($sp_query);

        //echo "<pre>";print_r($service_providers_arr);exit;

        $result = [];
        foreach ($service_providers_arr as $key => $value) {
            $result[$value->currDate][$value->code][$value->status] = $value->newRecords;
        }

        return $result;
    }

    public static function getUserRoles($user) {
        return $user->roles()->get()->toArray();
    }

    public static function isSuperAdmin($user) {
        $userRoles = self::getUserRoles($user);
        if (count($userRoles) > 0) {
            $roleAlias = array_column($userRoles, 'code');
            if (in_array('superAdmin', $roleAlias)) {
                return true;
            }
        }
        return false;
    }

    public function sendPasswordResetNotification($token) {
        $this->notify(new MailResetPasswordToken($token));
    }

    public static function formatUserData($user, $allData = true) {
        $baseImgPath = getUserDocumentPath($user, false);
        $user->name = getUserName($user);
        $profilePic = getUserDP($user);
        $user->profile_pic = $profilePic['pic'];
        $user->profile_pic_thumb = $profilePic['thumb'];
        $user->organization = OrganizationUser::getDefaultCompanyByUser($user);
        $user->user_profile = $user->user_profile;
        if($allData===true){
            $userData = $user->roles()->select(['id', 'title', 'code'])->get()->toArray();
            $user->all_user_roles = $userData;
            $user->userRole = (isset($userData[0]['code'])) ? $userData[0]['code'] : 'user';
            /*if(!in_array($user->userRole, ['user', 'doctor', 'superAdmin'])){*/
                $user->adminPanelMenu = self::getAdminPanelMenuData($user);
            /*}*/
        }

        return $user;
    }
    
    
    public static function getAdminPanelMenuData($user) {
        $userDataObj = $user->roles()->get();
        $userData = $userDataObj->toArray();
        if (count($userData) <= 0) {
            return;
        }
        $allUserModules = [];
        foreach ($userDataObj as $userRoleObj) {
            //$modules = $userRoleObj->modules()->with(['parentModules'])->get()->toArray();
            $modules = $userRoleObj->modules()->get()->toArray();
            $allUserModules = array_merge($allUserModules, $modules);
        }
        $parentModules = [];
        $menuItems = [];
        foreach ($allUserModules as $module) {
            $parentMenu = 0;
            if(!empty($module['parent_id'])){
                $parentModules[] = $parentMenu = $module['parent_id'];
            }
            $menuItems[$parentMenu][$module['id']] = ['id' => $module['id'], 'icon' => $module['icon'], 'text' => $module['title'], 
                'href' => $module['url'], 'permissions' => $module['pivot']];
        }
        $parentModules = array_unique($parentModules);
        $parentItems[0] = ['id' => 0, 'icon' => '', 'text' => 'Others', 'href' => '' ];
        if(count($parentModules) > 0){
            $parentModulesData = Models\Module::whereIn('id', $parentModules)->get()->toArray();
            foreach ($parentModulesData as $module) {
                $parentItems[$module['id']] = $module;
            }
        }
        
        $menuItemsData = [];
        foreach ($menuItems as $key => $menuItem) {
            $parentItems[$key]['href'] = (isset($parentItems[$key]['href'])) ? $parentItems[$key]['href'] : '';
            $tempData = ['id' => $parentItems[$key]['id'], 'icon' => $parentItems[$key]['icon'], 
                'text' => (isset($parentItems[$key]['title']) ? $parentItems[$key]['title'] : $parentItems[$key]['text']), 
                'href' =>  (isset($parentItems[$key]['url']) ? $parentItems[$key]['url'] : $parentItems[$key]['href']),
                'menu_order' => (isset($parentItems[$key]['menu_order']) ? $parentItems[$key]['menu_order'] : 1000)];
            $tempData['childern'] =  $menuItem;
            $menuItemsData[] = $tempData;
        }
        return $menuItemsData;
    }
    
    // this function check Either user is super Admin if true then return organization's Admin user If not SuperAdmin then return Current user
    public static function getOrgUserIfSuperAdmin() {
        $user = $currentUser= \Auth::user();
        $superAdmin = self::isSuperAdmin($user);
        if ($superAdmin === true) {
            $GET = Input::get();
            \Validator::make($GET, ['organization_id' => 'bail|required|exists:mysql.organizations,id'])->validate();
            $user = \DB::table('users')
                    ->select('users.id', 'organization_user.user_id')
                    ->join('organization_user', function($join) use ($GET){
                    $join->on('users.id', '=', 'organization_user.user_id')
                        ->where(['organization_user.organization_id' => $GET['organization_id'], 'status' => 'Approved', 'is_default' => 'Y' ]);
                    })
                    ->join('role_user', 'users.id', '=', 'role_user.user_id')
                    ->join('roles', function($join){
                        $join->on('role_user.role_id', '=', 'roles.id')
                                ->where(['code' => 'orgAdmin']);
                    })->first();
                    if(empty($user)){
                        return;
                    }
            $user = User::find($user->id);       
        }
        $user->currentUser = $currentUser;
        $user->currentUserID = $currentUser->id;
        return $user;
    }
    
    
    public static function  fetchConstraints($where) {
        $user  = self::select(['id', 'cnic', 'uuid'])->where(['uuid' => $where['uuid'], 'cnic' => $where, 'is_deleted' => '0'])->first();
        if(!empty($user)){
            return $user;
        }
        $user  = self::select(['id', 'cnic', 'uuid'])->where(['uuid' => $where['uuid'], 'is_deleted' => '0'])
                ->orWhere(['cnic' => $where, 'is_deleted' => '0'])->first();
        return $user;
    }

    
    public static function deleteUserById($id) {
        $user = \Auth::user();
        $userData = self::findOrFail($id);
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
        return $userData->save();
    }
}
