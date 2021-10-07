<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationUser extends Model {

    protected $table = 'organization_user';
    protected $casts = [
        'user_id' => 'int',
        'organization_id' => 'int'
    ];
    protected $fillable = [
        'user_id',
        'organization_id',
        'status',
        'grade_id',
        'basic_salary',
        'gross_salary',
        'ipd_limit',
        'opd_limit',
        'maternity_limit',
        'maternity_csection_limit',
        'maternity_room_limit',
        'employee_code',
        'date_joining',
        'date_confirmation',
        'team',
        'designation',
        'department',
        'is_default'
    ];

    public function organization() {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function user() {
        return $this->belongsTo(\App\User::class);
    }

    public function grade() {
        return $this->belongsTo(\App\Models\Grade::class);
    }
    
    public static function getDefaultCompanyByUser($user, $cols = ['name', 'id']) {
        $organizationUser = self::where(['user_id' => $user->id, 'is_default' => 'Y'])->first();
        if(!empty($organizationUser['organization_id'])){
            $data= Organization::find($organizationUser['organization_id'], $cols);
            return array_merge($organizationUser->toArray(), $data->toArray());
        }
    }

}
