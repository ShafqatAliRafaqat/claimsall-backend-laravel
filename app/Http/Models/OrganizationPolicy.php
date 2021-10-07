<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use App\Models\PolicyCoveredPerson;

class OrganizationPolicy extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $casts = [
		'grade_id' => 'int',
		'start_month' => 'int',
		'end_month' => 'int',
        //'indoor_limit' => 'float',
        //'indoor_room_limit' => 'float',
        //'outdoor_amount' => 'float',
        //'maternity_room_limit' => 'float',
        //'maternity_normal_case_limit' => 'float',
        //'maternity_csection_case_limit' => 'float',
        //'maternity_max_cases' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
        'organization_id',
        'user_id',
        'policy_level',
        'policy_type',
        'type',
		'grade_id',
		'name',
		'description',
		'short_code',
        'advance_payment_option',
		'start_month',
		'end_month',
        'indoor_type',
		'indoor_limit',
		'indoor_duration',
		'indoor_room_limit',
		'outdoor_type',
		'outdoor_amount',
        'outdoor_salary_type',
        'maternity_type',
		'maternity_room_limit',
		'maternity_normal_case_limit',
		'maternity_csection_case_limit',
        'maternity_max_cases',
		'is_active',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];
    
    public function grade() {
        return $this->belongsTo(\App\Models\Grade::class);
    }

    public function policy_covered_person() {
        return $this->hasOne(\App\Models\PolicyCoveredPerson::class);
    }

    public function policy_covered_persons() {
        return $this->hasMany(\App\Models\PolicyCoveredPerson::class);
    }
    
    public function organizations() {
        return $this->belongsTo(\App\Models\Organization::class);
    }
    
    public static function gradePolicyDetail($organizationPolicy) {
        $relationshipTypeWhere = function($relationshipTypeQuery){
            $relationshipTypeQuery->select(['id', 'name', 'policy_part']);
        };
        $claimTypeWhere = function($claimTypeWhereQuery){
            $claimTypeWhereQuery->select(['id', 'name']);
        };
        $policyCoveredPersonWhere = function($policyCoveredPersonQuery) use($relationshipTypeWhere, $claimTypeWhere, $organizationPolicy){
            //$policyCoveredPersonQuery->where('organization_policy_id', $organizationPolicy->id);
            $policyCoveredPersonQuery->with(['relationship_type' => $relationshipTypeWhere, 'claim_type' => $claimTypeWhere]);
        };
        $policyCoveredData = $organizationPolicy->with(['policy_covered_person' => $policyCoveredPersonWhere])
                ->whereHas('policy_covered_person', $policyCoveredPersonWhere);
        return $policyCoveredData->get();
    }
    
    
    public static function validatePolicyData($Post, $user){
        if($Post['policy_type'] == 'organization'){
            $orgCount = OrganizationPolicy::where(['is_deleted' => '0', 'organization_id' => $Post['organization_id']])->whereNull('user_id');
            if(!empty($Post['policyID'])){ $orgCount->where('id', '!=', $Post['policyID']);}
            if($orgCount->count()>0){
                return ['status' => false, 'message'=> 'Sorry, You cannot create multiple policies for a company'];
            }
        }elseif ($Post['policy_type'] == 'user') {
            $orgCount = OrganizationPolicy::where(['is_deleted' => '0', 'organization_id' => $Post['organization_id'],
                    'user_id' => $user->id ]);
            if(!empty($Post['policyID'])){ $orgCount->where('id', '!=', $Post['policyID']);}
            if($orgCount->count()>0){
                return ['status' => false, 'message'=> 'Sorry, You cannot create multiple policies for same user'];
            }
        }
        $policies = $Post['policies'];
        $policyCoveredPersons = [];
        foreach ($policies as $key => $policy) {
            $rules = ['relationship_type_id' => 'required|exists:mysql.relationship_types,id'];
            $policy['claim_type_id'] = $key;
            if ($key == '1') { // In-Patients
                $rules['indoor_room_limit'] = 'required|numeric';
                $rules['indoor_limit'] = 'required|numeric';
                $rules['indoor_duration'] = 'in:Monthly,Yearly';
            } elseif ($key == '2') { // Out-Patients
                $rules['outdoor_type'] = 'in:Fixed,Percentage';
                $rules['outdoor_salary_type'] = 'in:Basic,Gross';
                $rules['outdoor_amount'] = 'required|numeric';
            } else {
                $policy['relationship_type_id'] = (!empty($policy['policy_covered_types'])) ? $policy['policy_covered_types'] : [1, 2];
                $rules['maternity_room_limit'] = 'required|numeric';
                $rules['maternity_normal_case_limit'] = 'required|numeric';
                $rules['maternity_csection_case_limit'] = 'required|numeric';
            }
            if (!empty($policy['policy_covered_types'])) {
                $policy['relationship_type_id'] = $policy['policy_covered_types'];
                unset($policy['policy_covered_types']);
            }
            $Post = array_merge($Post, $policy);
            
            \Validator::make($policy, $rules)->validate();
            $policyCoveredPersons[] = $policy;
        }
        if (count($policyCoveredPersons) <= 0) {
            return ['status' => false, 'message'=> 'An error occured, policy covered cannot intialized, Error:101'];
        }
        
        $coveredPersons=  PolicyCoveredPerson::setDataFormat($policyCoveredPersons, $user);
        return ['data' => $Post, 'policyPersons' => $coveredPersons];
    }

    public static function checkApplicablePolicy($organization_policies, $user_org_grade_id, $user_relationship_id = null) {

        $user = \Auth::user();
        if (count($organization_policies) == 0) {
            return null;
        }

        if (count($organization_policies) > 1) {
            if (!empty($organization_policies[1]->grade_id)) { // grade level policy
                if ($organization_policies[1]->grade_id == $user_org_grade_id) {
                    $applied_policy = $organization_policies[1];
                    $temp_applied_policy = $organization_policies[0];
                }
                else {
                    $applied_policy = $organization_policies[0];
                }
            }
            else {
                if ($organization_policies[0]->grade_id == $user_org_grade_id) {
                    $applied_policy = $organization_policies[0];
                    $temp_applied_policy = $organization_policies[1];
                }
                else {
                    $applied_policy = $organization_policies[1];
                }
            }
        }
        else {
            if (!empty($organization_policies[0]->grade_id)) { // grade level policy
                if ($organization_policies[0]->grade_id != $user_org_grade_id) {
                    return null;
                }
            }
            $applied_policy = $organization_policies[0];
        }

        if(!empty($user_relationship_id)) {
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $user_relationship_id]);
            if ($getUser['parent_user_id'] == $user_relationship_id) {
                $claim_for_relationship = $getUser->relationship;
            }
            else {
                $claim_for_relationship = $getUser->assc_relationship;
            }

            $covered_person_arr = [];
            foreach ($applied_policy->policy_covered_persons as $key => $covered_person) {
                array_push($covered_person_arr, $covered_person->relationship_type_id);
            }

            if (!in_array($claim_for_relationship->relationship_type->id, $covered_person_arr)) {
                if (!empty($temp_applied_policy)) {
                    $temp_covered_person_arr = [];
                    foreach ($temp_applied_policy->policy_covered_persons as $key => $covered_person) {
                        array_push($temp_covered_person_arr, $covered_person->relationship_type_id);
                    }
                    if (!in_array($claim_for_relationship->relationship_type->id, $temp_covered_person_arr)) {
                        return null;
                    }
                    else {
                        $applied_policy = $temp_applied_policy;
                    }
                }
                else {
                    return null;
                }  
            }
        }
        
        return $applied_policy;
    }

    public static function checkApplicableOPD($organization_policies, $user_org_grade_id, $user_relationship_id = null) {

        $user = \Auth::user();
        if (count($organization_policies) == 0) {
            return null;
        }

        if (count($organization_policies) > 2) {
            if (!strcasecmp($organization_policies[2]->policy_level, config('app.hospitallCodes')['user'])) { // user level policy
                $applied_policy = $organization_policies[2];
                //$temp_applied_policy = $organization_policies[1];
                //$temp_applied_policy1 = $organization_policies[0];
                if (!strcasecmp($organization_policies[1]->policy_level, config('app.hospitallCodes')['grade'])) {
                    if ($organization_policies[1]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[1];
                        $temp_applied_policy1 = $organization_policies[0];
                    }
                    else {
                        $temp_applied_policy = $organization_policies[0];
                    }
                }
                else {
                    if ($organization_policies[0]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[0];
                        $temp_applied_policy1 = $organization_policies[1];
                    }
                    else {
                        $temp_applied_policy = $organization_policies[1];
                    }
                }
            }
            elseif (!strcasecmp($organization_policies[2]->policy_level, config('app.hospitallCodes')['grade'])) { // grade level policy
                if (!strcasecmp($organization_policies[1]->policy_level, config('app.hospitallCodes')['user'])) {
                    $applied_policy = $organization_policies[1];
                    if ($organization_policies[2]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[2];
                        $temp_applied_policy1 = $organization_policies[0];
                    }
                    else {
                        $temp_applied_policy = $organization_policies[0];
                    } 
                }
                else {
                    $applied_policy = $organization_policies[0];
                    if ($organization_policies[2]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[2];
                        $temp_applied_policy1 = $organization_policies[1];
                    }
                    else {
                        $temp_applied_policy = $organization_policies[1];
                    } 
                }
            }
            else { // organization level policy
                if (!strcasecmp($organization_policies[1]->policy_level, config('app.hospitallCodes')['user'])) {
                    $applied_policy = $organization_policies[1];
                    if ($organization_policies[0]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[0];
                        $temp_applied_policy1 = $organization_policies[2];
                    }
                    else {
                        $temp_applied_policy = $organization_policies[2];
                    } 
                }
                else {
                    $applied_policy = $organization_policies[0];
                    if ($organization_policies[1]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[1];
                        $temp_applied_policy1 = $organization_policies[2];
                    }
                    else {
                        $temp_applied_policy = $organization_policies[2];
                    } 
                }
            }
        }
        elseif (count($organization_policies) > 1) {
            if (!strcasecmp($organization_policies[0]->policy_level, config('app.hospitallCodes')['user'])) { // user level policy
                $applied_policy = $organization_policies[0];
                if (!strcasecmp($organization_policies[1]->policy_level, config('app.hospitallCodes')['grade'])) { // grade level policy
                    if ($organization_policies[1]->grade_id == $user_org_grade_id) {
                        $temp_applied_policy = $organization_policies[1];
                    }
                }
                else {
                    $temp_applied_policy = $organization_policies[1];
                }
            }
            else {
                if (!strcasecmp($organization_policies[1]->policy_level, config('app.hospitallCodes')['user'])) { // user level policy
                    $applied_policy = $organization_policies[1];
                    if (!strcasecmp($organization_policies[0]->policy_level, config('app.hospitallCodes')['grade'])) { // grade level policy
                        if ($organization_policies[0]->grade_id == $user_org_grade_id) {
                            $temp_applied_policy = $organization_policies[0];
                        }
                    }
                    else {
                        $temp_applied_policy = $organization_policies[0];
                    }
                }
                ///
                else {
                    if (!strcasecmp($organization_policies[0]->policy_level, config('app.hospitallCodes')['grade'])) { // grade level policy
                        if ($organization_policies[0]->grade_id == $user_org_grade_id) {
                            $applied_policy = $organization_policies[0];
                        }
                        else {
                            $applied_policy = $organization_policies[1];
                        }
                    }
                    else {
                        if ($organization_policies[1]->grade_id == $user_org_grade_id) {
                            $applied_policy = $organization_policies[1];
                        }
                        else {
                            $applied_policy = $organization_policies[0];
                        }
                    }
                }
                ///
            }
        }
        else {
            if (!empty($organization_policies[0]->grade_id)) { // grade level policy
                if ($organization_policies[0]->grade_id != $user_org_grade_id) {
                    return ['status' => false, 'message' => $policy_type . ' policy doesn\'t exist in your organization', 'code' => 404];
                }
            }
            $applied_policy = $organization_policies[0];
        }

        if(!empty($user_relationship_id)) {
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $user_relationship_id]);
            if ($getUser['parent_user_id'] == $user_relationship_id) {
                $claim_for_relationship = $getUser->relationship;
            }
            else {
                $claim_for_relationship = $getUser->assc_relationship;
            }

            $covered_person_arr = [];
            foreach ($applied_policy->policy_covered_persons as $key => $covered_person) {
                array_push($covered_person_arr, $covered_person->relationship_type_id);
            }

            if (!in_array($claim_for_relationship->relationship_type->id, $covered_person_arr)) {
                if (!empty($temp_applied_policy)) {
                    $temp_covered_person_arr = [];
                    foreach ($temp_applied_policy->policy_covered_persons as $key => $covered_person) {
                        array_push($temp_covered_person_arr, $covered_person->relationship_type_id);
                    }
                    if (!in_array($claim_for_relationship->relationship_type->id, $temp_covered_person_arr)) {
                        ////
                        if (!empty($temp_applied_policy1)) {
                            $temp_covered_person_arr1 = [];
                            foreach ($temp_applied_policy1->policy_covered_persons as $key => $covered_person) {
                                array_push($temp_covered_person_arr1, $covered_person->relationship_type_id);
                            }
                            if (!in_array($claim_for_relationship->relationship_type->id, $temp_covered_person_arr1)) {
                                return ['status' => false, 'message' => $claim_for_relationship->relationship_type->name . ' relationship is not covered in applicable policy.', 'code' => 403];
                            } else {
                                $applied_policy = $temp_applied_policy1;
                            }
                        } else {
                            return ['status' => false, 'message' => $claim_for_relationship->relationship_type->name . ' relationship is not covered in the applicable policy.', 'code' => 403];
                        }
                        ////
                    }
                    else {
                        $applied_policy = $temp_applied_policy;
                    }
                }
                else {
                    return null;
                }  
            }
        }
        
        return $applied_policy;
    }
}
