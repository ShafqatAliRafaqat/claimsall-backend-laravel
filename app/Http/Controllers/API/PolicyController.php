<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\OrganizationPolicy;
use App\User;
use App\Models\PolicyCoveredPerson;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use App\Models\PolicyApprovalProcess;
use App\Models\ProcessStatus;

class PolicyController extends Controller {

    use \App\Traits\WebServicesDoc;

    public function getPolicies(Request $request) {
        $post = $request->all();
        $user = \Auth::user();
        $org_data = Organization::userDefaultOrganization($user, false);
        if (!empty($org_data)) {
            $user_organization_id = $org_data->organization_user[0]->organization_id;
            $grade_ids = \App\Models\Grade::where('organization_id', $user_organization_id)->get();
            $grade_ids_arr = [];
            foreach ($grade_ids as $key => $value) {
                array_push($grade_ids_arr, $value->id);
            }
        }

        DB::connection()->enableQueryLog();
        $organization_policies = OrganizationPolicy::query();
        $organization_policies = $organization_policies->where(function ($query) use ($post) { // put bracket around multiple where clauses
            if (!empty($post['searchFilters'])) {
                foreach ($post['searchFilters'] as $key => $value) {
                    /*if (!strcasecmp($key, 'policy_type')) {
                        $query = $query->orWhere($key, 'LIKE', '%' . lcfirst($value) . '%');
                        
                    } else {
                        $query = $query->orWhere($key, 'LIKE', '%' . $value . '%');
                    }*/
                    $query = $query->orWhere($key, 'LIKE', '%' . $value . '%');
                }
            }
        });

        if (!empty($post['filters'])) {
            foreach ($post['filters'] as $key => $value) {
                if (is_array($value)) {
                    if (count($value) > 0) {
                        $organization_policies = $organization_policies->whereIn($key, $value);
                    }
                } else {
                    /*if (!strcasecmp($key, 'policy_type')) {
                        $organization_policies = $organization_policies->where($key, lcfirst($value));
                    } else {
                        $organization_policies = $organization_policies->where($key, $value);
                    }*/
                    $organization_policies = $organization_policies->where($key, $value);
                }
            }
        }

        $organization_policies = $organization_policies->where('organization_id', $user_organization_id);
        //$organization_policies = $organization_policies->orWhereIn('grade_id', $grade_ids_arr);

        if (empty($post['sorter'])) {
            $organization_policies = $organization_policies->orderBy("created_at", "DESC");
        } else {
            $sorter = $post['sorter'];
            $organization_policies = $organization_policies->orderBy($sorter['field'], $sorter['order'] == 'descend' ? 'DESC' : 'ASC');
        }

        $organization_policies = $organization_policies->paginate(10);
        //return DB::getQueryLog();

        foreach ($organization_policies as $key => $value) {
            $value['policy_type'] = ucfirst($value['policy_type']);
        }

        $msg = 'Found organization policies';
        if (!$organization_policies) {
            $organization_policies = [];
            $msg = 'No organizations found';
        }

        $response = responseBuilder()->success($msg, $organization_policies, false);
        $this->urlComponents('List of Organizations (Search| Filtration| Sorter| pagination)', $response, 'Organization_Management');
        return $response;
    }

    public function index() {
        $user = User::getOrgUserIfSuperAdmin();
        $oganizationGrades = Organization::defaultOrganizationGrades($user);
        if (empty($oganizationGrades->grades)) {
            return responseBuilder()->error('You have not added any grades yet, Please create grades before to add policy', 404);
        }

        $policiesData = [];
        foreach ($oganizationGrades->grades as $grade) {
            $gradePolicy = $grade->organization_policy()->get();
            $gradePolicy = $gradePolicy->toArray();
            if (!empty($gradePolicy)) {
                $gradePolicy[0]['grade_title'] = $grade->title;
                unset($gradePolicy[0]['created_by'], $gradePolicy[0]['updated_by'], $gradePolicy[0]['deleted_by'], $gradePolicy[0]['deleted_at'], $gradePolicy[0]['is_deleted']);
                $policiesData['gradePolicy'][] = $gradePolicy[0];
            }
        }
        $policiesData['organization'] = $oganizationGrades->organization_policies()->get()->toArray();
        $response = responseBuilder()->success('Here we found your organization policies', $policiesData, false);
        $this->urlComponents('Organization\'s Policy', $response, 'Organizations_Policy_Management');
        return $response;
    }

    public function store(Request $request) {
        $user = \Auth::user();
        $post = $request->all();
        if (empty($post['policy_covered_persons']['relationship_type_ids'])) {
            return responseBuilder()->error('Mandatory field(s) are missing', 400, false);
        }
        $user_data = Organization::userOrganization($user);
        if (!empty($user_data)) {
            $organization_id = $user_data->organization_user[0]->organization_id;
            $policy_level = $post['policy_level']; // organization or grade
            $policy_type = $post['policy_type']; // in_patient or out_patient or maternity
            $type = !empty($post['type'])? $post['type']: config('app.hospitallCodes')['fixed']; // Fixed or Percentage

            $grade_id = !empty($post['grade_id'])? $post['grade_id']: null; // null if policy_level is organization

            $flag = false;
            if (!strcasecmp($policy_level, config('app.hospitallCodes')['organization'])) { // organization level
                $organization_level_policy = OrganizationPolicy::where([
                                                    'organization_id' => $organization_id,
                                                    'policy_level'    => $policy_level,
                                                    'policy_type'     => $policy_type,
                                                    'type'            => $type
                                                ])
                                                ->first();
                if (!empty($organization_level_policy)) {
                    return responseBuilder()->error('policy already exists at company level with type '.$policy_type , 400, false);
                }
            }
            else { // grade level
                $flag = true;
                $grade_level_policy = OrganizationPolicy::where([
                                                    'organization_id' => $organization_id,
                                                    'policy_level'    => $policy_level,
                                                    'policy_type'     => $policy_type,
                                                    'grade_id'        => $grade_id,
                                                    'type'            => $type
                                                ])
                                                ->first();
                if (!empty($grade_level_policy)) {
                    return responseBuilder()->error('policy already exists at grade level against grade '.$grade_id.' with type '.$policy_type , 400, false);
                }
            }

            $name = !empty($post['name'])? $post['name']: null;
            $description = !empty($post['description'])? $post['description']: null;
            $short_code = !empty($post['short_code'])? $post['short_code']: null;

            switch ($policy_type) {
                case config('app.hospitallCodes')['in_patient']:
                    $indoor_limit = !empty($post['policy_covered_persons']['indoor_limit'])? $post['policy_covered_persons']['indoor_limit']: null;
                    if (strcasecmp($policy_level, config('app.hospitallCodes')['user'])) {
                        if ($indoor_limit < 1000 || $indoor_limit > 10000000) {
                            return responseBuilder()->error('IPD must range (1000 - 99999999)', 400);
                        }
                    }
                    $claim_type_id = $post['policy_covered_persons']['claim_type_id'];
                    $indoor_room_limit = !empty($post['policy_covered_persons']['indoor_room_limit'])? $post['policy_covered_persons']['indoor_room_limit']: null;
                    $relationship_type_ids = $post['policy_covered_persons']['relationship_type_ids'];
                    $time = now();
                    $policy = [
                            'organization_id'  => $organization_id,
                            'policy_level'     => $policy_level,
                            'grade_id'         => ($flag)? $grade_id: null,
                            'policy_type'      => $policy_type,
                            'type'             => $type,
                            'name'             => $name,
                            'description'      => $description,
                            'short_code'       => $short_code,
                            'indoor_limit'     => $indoor_limit,
                            'indoor_room_limit'=> $indoor_room_limit,
                            'created_at'       => $time,
                            'created_by'       => $user->id
                        ];
                    $policy = OrganizationPolicy::create($policy);
                    break;

                case config('app.hospitallCodes')['out_patient']:
                    $outdoor_amount = !empty($post['policy_covered_persons']['outdoor_amount'])? $post['policy_covered_persons']['outdoor_amount']: null;
                    /*$outdoor_type = !empty($post['policy_covered_persons']['outdoor_type'])? $post['policy_covered_persons']['outdoor_type']: config('app.hospitallCodes')['percentage'];*/
                    if (strcasecmp($policy_level, config('app.hospitallCodes')['user'])) {
                        if ($outdoor_amount < 1000 || $outdoor_amount > 10000000) {
                            return responseBuilder()->error('OPD must range (1000 - 99999999)', 400);
                        }
                    }
                    
                    $claim_type_id = $post['policy_covered_persons']['claim_type_id'];
                    $relationship_type_ids = $post['policy_covered_persons']['relationship_type_ids'];
                    $time = now();
                    $policy = [
                            'organization_id'  => $organization_id,
                            'policy_level'     => $policy_level,
                            'grade_id'         => ($flag)? $grade_id: null,
                            'policy_type'      => $policy_type,
                            'type'             => $type,
                            'name'             => $name,
                            'description'      => $description,
                            'short_code'       => $short_code,
                            'outdoor_amount'   => $outdoor_amount,
                            //'outdoor_type'     => $outdoor_type,
                            'created_at'       => $time,
                            'created_by'       => $user->id
                        ];
                    $policy = OrganizationPolicy::create($policy);
                    break;

                case config('app.hospitallCodes')['maternity']:
                    $maternity_room_limit = !empty($post['policy_covered_persons']['maternity_room_limit'])? $post['policy_covered_persons']['maternity_room_limit']: null;
                    $maternity_normal_case_limit = !empty($post['policy_covered_persons']['maternity_normal_case_limit'])? $post['policy_covered_persons']['maternity_normal_case_limit']: null;
                    $maternity_csection_case_limit = !empty($post['policy_covered_persons']['maternity_csection_case_limit'])? $post['policy_covered_persons']['maternity_csection_case_limit']: null;

                    if (strcasecmp($policy_level, config('app.hospitallCodes')['user'])) {
                        if ($maternity_csection_case_limit < 1000 || $maternity_csection_case_limit > 10000000) {
                            return responseBuilder()->error('Maternity C-section case limit must range (1000 - 10000000)', 400);
                        }
                        if ($maternity_csection_case_limit < $maternity_room_limit) {
                            return responseBuilder()->error('Maternity C-section case limit must be greater or equal to Maternity room limit', 400);
                        }
                        if ($maternity_csection_case_limit < $maternity_normal_case_limit) {
                            return responseBuilder()->error('Maternity C-section case limit must be greater or equal to Maternity Normal case limit', 400);
                        }
                    }

                    $claim_type_id = $post['policy_covered_persons']['claim_type_id'];
                    $relationship_type_ids = $post['policy_covered_persons']['relationship_type_ids'];
                    $time = now();
                    $policy = [
                            'organization_id'               => $organization_id,
                            'policy_level'                  => $policy_level,
                            'grade_id'                      => ($flag)? $grade_id: null,
                            'policy_type'                   => $policy_type,
                            'type'                          => $type,
                            'name'                          => $name,
                            'description'                   => $description,
                            'short_code'                    => $short_code,
                            'maternity_room_limit'          => $maternity_room_limit,
                            'maternity_normal_case_limit'   => $maternity_normal_case_limit,
                            'maternity_csection_case_limit' => $maternity_csection_case_limit,
                            'created_at'                    => $time,
                            'created_by'                    => $user->id
                        ];
                    $policy = OrganizationPolicy::create($policy);
                    break;
                
                default:
                    return responseBuilder()->error('policy type is other than (IPD, OPD, Maternity)' , 400, false);
                    break;
            }
            $policy_covered_persons = [];
            $unique_relationship_type_ids = array_unique($relationship_type_ids);
            foreach ($unique_relationship_type_ids as $key => $relationship_type_id) {
                $time = now();
                $policy_covered_persons[] = [
                    'organization_policy_id'    => $policy->id,
                    'relationship_type_id'      => $relationship_type_id,
                    'claim_type_id'             => $claim_type_id,
                    'created_at'                => $time,
                    'created_by'                => $user->id
                ];
            }
            \App\Models\PolicyCoveredPerson::insert($policy_covered_persons);
            $response = responseBuilder()->success('Policy created successfully');
            $this->urlComponents('Create Organization\'s Policy', $response, 'Organizations_Policy_Management');
            return $response;
        }
        return responseBuilder()->error('user is not part of any organization' , 400, false);
    }

    public function update(Request $request, $id) {
        $user = \Auth::user();
        $policy = OrganizationPolicy::findOrFail($id);
        $post = $request->all();
        if (empty($post['policy_covered_persons']['relationship_type_ids'])) {
            return responseBuilder()->error('Policy must cover some relations ', 400, false);
        }
        $policy_covered_persons = $policy->policy_covered_persons;
        $policy_level = $post['policy_level']; // organization or grade
        $policy_type = $post['policy_type']; // in_patient or out_patient or maternity
        $grade_id = !empty($post['grade_id'])? $post['grade_id']: null; // null if policy_level is organization
        $claim_type_id = $policy_covered_persons[0]->claim_type_id;

        $name = !empty($post['name'])? $post['name']: null;
        $description = !empty($post['description'])? $post['description']: null;
        $short_code = !empty($post['short_code'])? $post['short_code']: null;
        $relationship_type_ids = $post['policy_covered_persons']['relationship_type_ids'];

        switch ($policy_type) {
            case config('app.hospitallCodes')['in_patient']:
                $indoor_limit = $post['policy_covered_persons']['indoor_limit'];
                if (strcasecmp($policy_level, config('app.hospitallCodes')['user'])) {
                    if ($indoor_limit < 1000 || $indoor_limit > 10000000) {
                        return responseBuilder()->error('IPD must range (1000 - 99999999)', 400);
                    }
                }
                $indoor_room_limit = $post['policy_covered_persons']['indoor_room_limit'];
                $time = now();
                $policyData = [
                        
                        'name'             => $name,
                        'description'      => $description,
                        'short_code'       => $short_code,
                        'indoor_limit'     => $indoor_limit,
                        'indoor_room_limit'=> $indoor_room_limit,
                        'updated_at'       => $time,
                        'updated_by'       => $user->id
                    ];

                $policy->update($policyData);
                break;

            case config('app.hospitallCodes')['out_patient']:
                $outdoor_amount = $post['policy_covered_persons']['outdoor_amount'];
                if (strcasecmp($policy_level, config('app.hospitallCodes')['user'])) {
                    if ($outdoor_amount < 1000 || $outdoor_amount > 10000000) {
                        return responseBuilder()->error('OPD must range (1000 - 99999999)', 400);
                    }
                }
                $time = now();
                $policyData = [
                        
                        'name'             => $name,
                        'description'      => $description,
                        'short_code'       => $short_code,
                        'outdoor_amount'   => $outdoor_amount,
                        'updated_at'       => $time,
                        'updated_by'       => $user->id
                    ];
                $policy->update($policyData);
                break;

            case config('app.hospitallCodes')['maternity']:
                $maternity_room_limit = $post['policy_covered_persons']['maternity_room_limit'];
                $maternity_normal_case_limit = $post['policy_covered_persons']['maternity_normal_case_limit'];
                $maternity_csection_case_limit = $post['policy_covered_persons']['maternity_csection_case_limit'];

                if (strcasecmp($policy_level, config('app.hospitallCodes')['user'])) {
                    if ($maternity_csection_case_limit < 1000 || $maternity_csection_case_limit > 10000000) {
                        return responseBuilder()->error('Maternity C-section case limit must range (1000 - 10000000)', 400);
                    }
                    if ($maternity_csection_case_limit < $maternity_room_limit) {
                        return responseBuilder()->error('Maternity C-section case limit must be greater or equal to Maternity room limit', 400);
                    }
                    if ($maternity_csection_case_limit < $maternity_normal_case_limit) {
                        return responseBuilder()->error('Maternity C-section case limit must be greater or equal to Maternity Normal case limit', 400);
                    }
                }

                $time = now();
                $policyData = [
                        
                        'name'                          => $name,
                        'description'                   => $description,
                        'short_code'                    => $short_code,
                        'maternity_room_limit'          => $maternity_room_limit,
                        'maternity_normal_case_limit'   => $maternity_normal_case_limit,
                        'maternity_csection_case_limit' => $maternity_csection_case_limit,
                        'updated_at'                    => $time,
                        'updated_by'                    => $user->id
                    ];
                $policy->update($policyData);
                break;
            
            default:
                return responseBuilder()->error('policy type is other than (IPD, OPD, Maternity)' , 400, false);
                break;
        }

        \App\Models\PolicyCoveredPerson::where('organization_policy_id', $policy->id)->delete();
        $policy_covered_persons = [];
        foreach ($relationship_type_ids as $key => $relationship_type_id) {
            $time = now();
            $policy_covered_persons[] = [
                'organization_policy_id'    => $policy->id,
                'relationship_type_id'      => $relationship_type_id,
                'claim_type_id'             => $claim_type_id,
                'created_at'                => $time,
                'created_by'                => $user->id
            ];
        }
        \App\Models\PolicyCoveredPerson::insert($policy_covered_persons);
        $response = responseBuilder()->success('Policy updated successfully');
        $this->urlComponents('Update Organization\'s Policy', $response, 'Organizations_Policy_Management');
        return $response;
    }

    public function show($id) {
        $organizationPolicy = OrganizationPolicy::select(['id', 'grade_id', 'user_id', 'organization_id', 'name',
                            'description', 'short_code', 'advance_payment_option', 'start_month', 'end_month',
                            'indoor_limit', 'indoor_duration', 'indoor_room_limit', 'outdoor_type', 'outdoor_amount',
                            'maternity_room_limit', 'maternity_normal_case_limit', 'maternity_csection_case_limit', 'maternity_max_cases', 'policy_type', 'policy_level'])
                        ->where(['is_deleted' => '0'])->find($id);
        if (!$organizationPolicy) {
            goto end;
        }
        $organizationPolicyDetail = $organizationPolicy->policy_covered_person()->get();
        if (!$organizationPolicyDetail) {
            goto end;
        }
        $organizationPolicyDetail = $organizationPolicyDetail->toArray();
        $claimTypeIds = array_column($organizationPolicyDetail, 'claim_type_id');
        $relationshipTypeId = array_column($organizationPolicyDetail, 'relationship_type_id');
        $claimTypeIds = array_unique($claimTypeIds);
        $relationshipTypeId = array_unique($relationshipTypeId);
        $claimTypesData = \App\Models\ClaimType::select(['id', 'name'])->whereIn('id', $claimTypeIds)->get()->toArray();
        $relationshipTypeData = \App\Models\RelationshipType::select(['id', 'name'])
                        ->whereIn('id', $relationshipTypeId)->get()->toArray();
        $claimTypesDataList = array_column($claimTypesData, 'name', 'id');
        $relationshipTypeDataList = array_column($relationshipTypeData, 'name', 'id');
        $relationshipTypes = [];
        foreach ($organizationPolicyDetail as $detail) {
            $relationshipTypes[$detail['claim_type_id']][$detail['relationship_type_id']] = $relationshipTypeDataList[$detail['relationship_type_id']];
        }

        foreach ($organizationPolicyDetail as $detail) {
            $policies[$detail['claim_type_id']] = ['claimType' => $claimTypesDataList[$detail['claim_type_id']],
                'relationshipTypes' => $relationshipTypes[$detail['claim_type_id']]
            ];
        }

        $organizationPolicy['policies'] = $policies;
        if ($organizationPolicy['policy_type'] === 'user' && !empty($organizationPolicy['user_id'])) {
            $organizationPolicy['userDetail'] = User::find($organizationPolicy['user_id']);
        }
        if ($organizationPolicy['policy_type'] === 'grade' && !empty($organizationPolicy['grade_id'])) {
            $grade = \App\Models\Grade::where('id', $organizationPolicy['grade_id'])->pluck('title');
            $organizationPolicy['grade_title'] = $grade[0];
        }
        $response = responseBuilder()->success('Fetch organization policy details', $organizationPolicy);
        $this->urlComponents('Show Organization\'s Policy', $response, 'Organizations_Policy_Management');
        return $response;

        end:
        return responseBuilder()->error('Unable to fetch policy detail', 404);
    }

    public function destroy($id) {
        $user = User::getOrgUserIfSuperAdmin();
        $organizationPolicy = OrganizationPolicy::where(['is_deleted' => '0'])->find($id);
        if (!$organizationPolicy) {
            goto end;
        }
        $organizationPolicy->update(['is_deleted' => '1', 'deleted_by' => $user->id]);
        $organizationPolicy->delete();
        $response = responseBuilder()->success('Organization policy deleted successfully');
        $this->urlComponents('Remove Organization\'s Policy', $response, 'Organizations_Policy_Management');
        return $response;
        end:
        return responseBuilder()->error('Something went wrong, Unable to delete policy', 404);
    }

    public function policyStatuses() {
        $params = Input::get();
        $all = (!empty($params['all'])) ? $params['all'] : 'N';
        $fields = ['id', 'name', 'description'];
        if ($all == 'Y') {
            $fields[] = 'is_default';
        }
        $statusesObj = \App\Models\PolicyApprovalStatus::select($fields);
        if (strtoupper($all) == 'Y') {
            $statusesObj = $statusesObj->get();
        } else {
            $statusesObj = $statusesObj->where('is_default', 'N')->get();
        }
        $statuses = $statusesObj->toArray();
        $response = responseBuilder()->success('Policy approval statuses: ', $statuses, false);
        $this->urlComponents('List of Policy Statuses', $response, 'Organizations_Policy_Management');
        return $response;
    }

    public function policyApprovalProcess(Request $request) {
        $user = \Auth::user();
        $rules = ['data' => 'required' ,  'data.*.role_id' => 'bail|required|exists:roles,id|unique:policy_approval_process,role_id'];
        $superAdmin = User::isSuperAdmin($user);
        if ($superAdmin === true) {
            $rules['organization_id'] = 'required|exists:mysql.organizations,id';
        }
        $message = 'An error occured, unable to add new approval process';
        
        $post = $request->all();
        if ($superAdmin === true) {
            $userOrgData = Organization::where(['status' => 'Approved'])->findOrFail($post['organization_id']);
        } else {
            $userOrgData = Organization::defaultOrganizationData($user);
            if (isset($userOrgData['status']) && $userOrgData['status'] === false) {
                $message = $userOrgData['message'];
                goto end;
            }
        }

        $organization_id = $userOrgData['id'];

        $new_approval_process_count = count($post['data']);

        $approval_process_count = PolicyApprovalProcess::where('organization_id', $organization_id)
                                                    ->count();

        if ($new_approval_process_count < $approval_process_count) {
            $action_arr = ['Pending', 'On Hold'];
            $transactions_count = \App\Models\MedicalClaimTransactionHistory::where('policy_approval_process_order', '>', $new_approval_process_count)
                                    ->where('organization_id', $organization_id)
                                    ->whereIn('action', $action_arr)
                                    ->count();
            if ($transactions_count > 0) {
                return responseBuilder()->error('Some Pending/On Hold claims are already in process queue', 400);
            }
        }
    
        $org_roles = \App\Models\Role::select(['id'])
                                    ->where('organization_id', $organization_id)
                                    ->orWhere('code', config('app.hospitallCodes')['orgAdmin'])
                                    ->get();
        $org_roles_arr = [];
        if (count($org_roles) > 0) {
            foreach ($org_roles as $key => $org_role) {
                array_push($org_roles_arr, $org_role->id);
            }
        }

        $claim_modules = \App\Models\Module::select(['id'])->where('claim_action', '1')->get();
        $claim_modules_arr = [];
        if (count($claim_modules) > 0) {
            foreach ($claim_modules as $key => $claim_module) {
                array_push($claim_modules_arr, $claim_module->id);
            }
        }

        \App\Models\ModuleRole::whereIn('module_id', $claim_modules_arr)
                                ->whereIn('role_id', $org_roles_arr)
                                ->delete();

        PolicyApprovalProcess::where('organization_id', $organization_id)->delete();

        $itr = 0;
        $latest_roles_arr = [];
        foreach ($post['data'] as $process) {
            $itr++;

            $process['approval_order'] = $itr;
            $process['organization_id'] = $organization_id;
            $process['created_by'] = $process['updated_by'] = $user->id;

            if(count($post['data']) == $itr){
                $process['is_ready_to_pay'] = 'Y';
            }

            $policyProcess = PolicyApprovalProcess::create($process);

            array_push($latest_roles_arr, $process['role_id']);

            if (!$policyProcess) {
                goto end;
            }           
        }

        $unique_latest_roles_arr = array_unique($latest_roles_arr);

        if (!empty($claim_modules_arr)) {
            foreach ($unique_latest_roles_arr as $key => $role_id) {
                $module_roles = [];
                foreach ($claim_modules_arr as $key => $module_id) {
                    $module_roles[] = [
                        'role_id' => $role_id,
                        'module_id' => $module_id
                    ];
                }
                \App\Models\ModuleRole::insert($module_roles);
            }
        }

        $response = responseBuilder()->success('New process for approval added successfully');
        
        $this->urlComponents('Add New Policy Approval Process', $response, 'Organizations_Policy_Management');
        
        return $response;
        
        end:
        // TODO: Delete, Rollback all activity
        return responseBuilder()->error($message);
    }

    public function viewPolicyApprovalProcess(Request $request) {
        $user = \Auth::user();
        $userOrgData = Organization::defaultOrganizationData($user);
        if (!$userOrgData['status']) {
            return ['status' => false, 'code' => 401, 'message' => 'Access denied to this user'];
        }

        $org_id = $userOrgData['id'];
        $policy_approval_process = PolicyApprovalProcess::select(['role_id', 'description'])
                                                            ->where(['organization_id' => $org_id])
                                                            ->orderBy('approval_order', 'ASC')
                                                            ->get();

        $msg = 'Following users are found';
        if (!$policy_approval_process) {
            $policy_approval_process = [];
            $msg = 'No users found';
        }
        $response = responseBuilder()->success($msg, $policy_approval_process, false);
        $this->urlComponents('Policy Approval Process', $response, 'Organizations_Policy_Management');
        return $response;
    }
    
    public function updatePolicyApprovalProcess(Request $request, $id) {
        $user = \Auth::user();
        $rules = ['role_id' => 'required|exists:roles,id|unique:policy_approval_process,role_id,'.$id,
            'status_id' => 'required|array'];
        $superAdmin = User::isSuperAdmin($user);
        if ($superAdmin === true) {
            $rules['organization_id'] = 'required|exists:mysql.organizations,id';
        }
        $message = 'An error occured, unable to add new approval process';
        $request->validate($rules);
        $policyProcess = PolicyApprovalProcess::findOrFail($id);
        $post = $request->all();
        if ($superAdmin === true) {
            $userOrgData = Organization::where(['status' => 'Approved'])->findOrFail($post['organization_id']);
        } else {
            $userOrgData = Organization::defaultOrganizationData($user);
            if (isset($userOrgData['status']) && $userOrgData['status'] === false) {
                $message = $userOrgData['message'];
                goto end;
            }
        }
        $statuses_ids = $post['status_id'];
        unset($post['status_id']);
        $post['updated_by'] = $user->id;
        $post['organization_id'] = $userOrgData->id;
        
        $policyProcessRes = $policyProcess->update($post);
        if ($policyProcessRes) {
            if(!empty($statuses_ids)){
                ProcessStatus::where('approval_process_id', $id)->delete();
                $processStatusData = [];
                foreach ($statuses_ids as $id) {
                    $processStatusData[] = ['approval_process_id' => $policyProcess->id, 'approval_status_id' => $id];
                }
                ProcessStatus::insert($processStatusData);
            }
            $response = responseBuilder()->success('Process for approval updated successfully');
            $this->urlComponents('Update Policy Approval Process', $response, 'Organizations_Policy_Management');
            return $response;
        }
        end:
        return responseBuilder()->error($message);
    }
    
    public function policyApprovalProcessList() {
        $user = User::getOrgUserIfSuperAdmin();
        $message = 'An error occured, unable to get approval process listing';
         $userOrgData = Organization::defaultOrganizationData($user);
            if (isset($userOrgData['status']) && $userOrgData['status'] === false) {
                $message = $userOrgData['message'];
                goto end;
            }
        $processes = PolicyApprovalProcess::where(['organization_id' => $userOrgData->id])
                ->orWhereNull('organization_id')
                ->with(['role' => function($roleQuery){
                    $roleQuery->select(['id', 'title']);
                }])->with(['statuses'=> function($statusQuery){
                    $statusQuery->select(['id', 'name']);
                }])
                ->orderBy('approval_order')->paginate(10);
        if(empty($processes)){
            $message = 'No data found';
            goto end;
        }
        
        $policyProcesses = $processes->toArray();
        $response= responseBuilder()->success('Policy approval list', $policyProcesses);
        $this->urlComponents('List of Policy Approval', $response, 'Organizations_Policy_Management');
        return $response;
        end:
        return responseBuilder()->error($message);
    }
    
    public function getPolicyCoveredPersons($claimId) {
        \Validator::make(['claim_type_id' => $claimId], ['claim_type_id'=> 'bail|required|exists:claim_types,id'])->validate();
        $user= \Auth::user();
        $userOrgnization = Organization::defaultOrganizationData($user);
        if(isset($userOrgnization['status']) && $userOrgnization['status']===false){
            return responseBuilder()->error($userOrgnization['message']);
        }
        $message = 'Fetch covered persons details';
        $organizationId =  $userOrgnization->id;

        $org_user_obj = \App\Models\OrganizationUser::where([
                                                        'user_id' => $user->id,
                                                        'organization_id' => $organizationId
                                                      ])
                                                      ->first();
        $grade_id = $org_user_obj->grade_id;
        if ($claimId == '1') {
            $policy_type = config('app.hospitallCodes')['in_patient'];
        }
        elseif ($claimId == '2') {
            $policy_type = config('app.hospitallCodes')['out_patient'];
        }
        elseif ($claimId == '3') {
            $policy_type = config('app.hospitallCodes')['maternity'];
        }
        if (!empty($grade_id)) {
            $policies = OrganizationPolicy::where('organization_id', $organizationId)
                                               ->where('policy_type', $policy_type)
                                               ->where(function($orgPolicy) use ($grade_id) {
                                                    $orgPolicy->where('grade_id', $grade_id);
                                                    $orgPolicy->orWhere('policy_level', config('app.hospitallCodes')['organization']);
                                                    $orgPolicy->orWhere('policy_level', config('app.hospitallCodes')['user']);
                                                })
                                               ->get();
        }
        else {
            $policies = OrganizationPolicy::where('organization_id', $organizationId)
                                               ->where('policy_type', $policy_type)
                                               ->where(function($orgPolicy) {
                                                    $orgPolicy->where('policy_level', config('app.hospitallCodes')['organization']);
                                                    $orgPolicy->orWhere('policy_level', config('app.hospitallCodes')['user']);
                                                })
                                               ->get();
        }

        $relationship_ids = [];
        if (!empty($policies)) {
            foreach ($policies as $key => $policy) {
                $covered_persons = $policy->policy_covered_persons;
                foreach ($covered_persons as $key => $covered_person) {
                    $relationships = $covered_person->relationship_type->relationships;
                    foreach ($relationships as $key => $relationship) {
                        array_push($relationship_ids, $relationship->id);
                    }
                }
            }
        }
        $relationship_ids= array_unique($relationship_ids);

        $relationships = \App\Models\Relationship::select(['id', 'name'])
                                                    ->whereIn('id', $relationship_ids)
                                                    ->get();

        $response = responseBuilder()->success('Covered relationships', $relationships, false);
        $this->urlComponents('Get Policy Covered Persons By claim id', $response, 'Organizations_Policy_Management');
        return $response;
    }
}
