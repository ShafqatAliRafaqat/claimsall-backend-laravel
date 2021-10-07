<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = [
        'department_id' => 'int',
        'city_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'name',
        'description',
        'department_id',
        'organization_type_id',
        'type',
        'email',
        'short_code',
        'address',
        'additional_address',
        'latitude',
        'longitude',
        'timing',
        'city_id',
        'contact_number',
        'ntn_number',
        'website',
        'status',
        'contact_person',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
    protected $connection='mysql';

    public function department() {
        return $this->belongsTo(\App\Models\Department::class);
    }

    public function documents() {
        return $this->hasMany(\App\Models\Document::class);
    }

    public function email_templates() {
        return $this->hasMany(\App\Models\EmailTemplate::class);
    }

    public function medical_claims() {
        return $this->hasMany(\App\Models\MedicalClaim::class);
    }

    public function organization_policies() {
        return $this->hasMany(\App\Models\OrganizationPolicy::class);
    }
    
    public function policy_approval_processes() {
        return $this->hasMany(\App\Models\PolicyApprovalProcess::class);
    }

    public function users() {
        return $this->belongsToMany(\App\User::class)
                        ->withPivot('status')
                        ->withTimestamps();
    }

    public function city() {
        return $this->belongsTo(\App\Models\City::class);
    }

    public function organization_type() {
        return $this->belongsTo(\App\Models\OrganizationType::class);
    }

    public function grades() {
        return $this->hasMany(\App\Models\Grade::class);
    }

    public function organization_common_policy() {
        return $this->hasOne(\App\Models\OrganizationCommonPolicy::class, 'organization_id');
    }

    public static function userDefaultOrganization($user, $allData = true, $additionalParams = []) {
        if ($allData === false) {
            $organizationUserWhere = function($organizationUserQuery) use ($user) {
                $organizationUserQuery->where(['status' => 'Approved', 'is_default' => 'Y', 'user_id' => $user->id]);
            };
            goto end;
        }
        $organizationUserWhere = function($organizationUserQuery) use ($user) {
            $organizationUserQuery->where(['status' => 'Approved', 'is_default' => 'Y', 'user_id' => $user->id]);
            $organizationUserQuery->with(['organization' => function($organizationQuery) {
                    $organizationQuery->with('grades');
                }]);
        };
        end:
        return $user->with([
                            'organization_user' => $organizationUserWhere,
                        ])
                        ->whereHas('organization_user', $organizationUserWhere)
                        ->first();
    }

    public static function userOrganization($user, $allData = true, $additionalParams = []) {
        if ($allData === false) {
            $organizationUserWhere = function($organizationUserQuery) use ($user) {
                $organizationUserQuery->where(['is_default' => 'Y', 'user_id' => $user->id]);
            };
            goto end;
        }
        $organizationUserWhere = function($organizationUserQuery) use ($user) {
            $organizationUserQuery->where(['is_default' => 'Y', 'user_id' => $user->id]);
            $organizationUserQuery->with(['organization' => function($organizationQuery) {
                    $organizationQuery->with('grades');
                }]);
        };
        end:
        return $user->with([
                            'organization_user' => $organizationUserWhere,
                        ])
                        ->whereHas('organization_user', $organizationUserWhere)
                        ->first();
    }

    public static function defaultOrganizationData($user, $addionalParams = []) {
        $orgnizations = self::userDefaultOrganization($user, false, $addionalParams);
        if (empty($orgnizations->organization_user)) {
            return ['status' => false, 'message' => 'No company found in user'];
        }
        $userOrganizations = $orgnizations->organization_user;
        $defaultOrganization = $userOrganizations[0]->organization;
        return $defaultOrganization;
    }

    public static function defaultOrganizationGrades($user, $addionalParams = []) {
        $orgnizations = self::userDefaultOrganization($user, true, $addionalParams);
        if (empty($orgnizations->organization_user)) {
            return ['status' => false, 'message' => 'No company found in user'];
        }
        $userOrganizations = $orgnizations->organization_user;
        $defaultOrganization = $userOrganizations[0]->organization;
        return $defaultOrganization;
    }

}
