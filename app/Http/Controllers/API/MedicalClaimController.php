<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Libraries\Uploader;
use File;
use App\Models\Document;
use App\Models\MedicalClaim;
use App\Models\OrganizationPolicy;
use App\Http\Requests\MedicalClaim as MedicalClaimRequest;

class MedicalClaimController extends Controller
{
    use \App\Traits\WebServicesDoc,        \App\Traits\FCM;

    public function getClaims(Request $request)
    {
        $user = \Auth::user();
        $user_id = $user->id;
        $post = $request->all();

        $org_data = \App\Models\Organization::userOrganization($user, false);
        if (empty($org_data)) {
            return responseBuilder()->error('User doesn\'t belong to any organization', 404, false);
        }
        $user_org_id = $org_data->organization_user[0]->organization_id;


        //$ignore_cols_arr = ['self_processed']; // processed by me

       // \DB::connection('hosMysql')->enableQueryLog();
        $claims = \App\Models\MedicalClaim::query();

        $claims = $claims->where("hos_v11.medical_claims.organization_id", $user_org_id);
        $claims = $claims->where(function ($query) use ($post) { // put bracket around multiple where clauses
                    if (!empty($post['searchFilters'])) {
                        /*if (!in_array(needle, haystack)) {
                            # code...
                        }*/
                        foreach ($post['searchFilters'] as $key => $value) {
                            $query = $query->orWhere($key, 'LIKE', '%' . $value . '%');
                        }
                    }
                });
        if (!empty($post['filters'])) {
            foreach ($post['filters'] as $key => $value) {
                if (is_array($value)) {
                    if (count($value) > 0) {
                        $claims = $claims->whereIn($key, $value);
                    }
                } else {
                    $claims = $claims->where($key, $value);
                }
            }
        }
        if (empty($post['sorter'])) {
            $claims = $claims->orderBy("hos_v11.medical_claims.created_at", "ASC");
        } else {
            $sorter = $post['sorter'];
            $claims = $claims->orderBy($sorter['field'], $sorter['order'] == 'descend' ? 'DESC' : 'ASC');
        }
        
        $claims = $claims->paginate(10);
        //return \DB::connection('hosMysql')->getQueryLog();
        
        $response = responseBuilder()->success("Found following claims", $claims, false);
        $this->urlComponents('Open Claims', $response, 'Policy_Approval_Process_Management');
        return $response;
    }

    public function store(MedicalClaimRequest $request)
    {
        $oMedicalClaim = new MedicalClaim();
        $response = $oMedicalClaim->addMedicalClaim($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Add Medical Claim', $response, 'Medical_Claim_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    // open claims(against specific orders/role/organization)
    public function getOpenClaimsTransactions(Request $request)
    {
        $user = \Auth::user();
        $post = $request->all();

        $org_data = \App\Models\Organization::userOrganization($user, false);
        if (empty($org_data)) {
            return responseBuilder()->error('User doesn\'t belong to any organization', 404, false);
        }
        $user_org_id = $org_data->organization_user[0]->organization_id;

        $user_roles = $user->roles;
        $user_roles_arr = [];
        foreach ($user_roles as $key => $user_role) {
            array_push($user_roles_arr, $user_role->id);
        }

        $approval_process = \App\Models\PolicyApprovalProcess::where('organization_id', $user_org_id)
                                                               ->whereIn('role_id', $user_roles_arr)
                                                               ->get();
        if (empty($approval_process)) {
            return responseBuilder()->error('Sorry, you are not part of policy approval process', 404,  false);
        }

        $approval_process_orders_arr = [];
        foreach ($approval_process as $key => $value) {
            array_push($approval_process_orders_arr, $value->approval_order);
        }

        $open_claims = \App\Models\MedicalClaimTransactionHistory::query();
        $open_claims = $open_claims->select(['id', 'medical_claim_id', 'medical_claim_serial_no', 'medical_claim_title', 'medical_claim_level', 'medical_claim_type', 'maternity_type', 'claimed_amount', 'claimed_by_name', 'claimed_by_employee_code', 'created_at']);

        $open_claims = $open_claims->where(function ($query) use ($post) { // put bracket around multiple where clauses
                    if (!empty($post['searchFilters'])) {
                        foreach ($post['searchFilters'] as $key => $value) {
                            $query = $query->orWhere($key, 'LIKE', '%' . $value . '%');
                        }
                    }
                });

        if (!empty($post['filters'])) {
            foreach ($post['filters'] as $key => $value) {
                if (is_array($value)) {
                    if (count($value) > 0) {
                        $open_claims = $open_claims->whereIn($key, $value);
                    }
                } else {
                    $open_claims = $open_claims->where($key, $value);
                }
            }
        }

        $open_claims = $open_claims->where([
                            'organization_id' => $user_org_id,
                            'is_completed'  => 'N'
                         ]);
        $open_claims = $open_claims->whereIn('policy_approval_process_order', $approval_process_orders_arr);
        if (empty($post['sorter'])) {
            $open_claims = $open_claims->orderBy("created_at", "ASC");
        } else {
            $sorter = $post['sorter'];
            $open_claims = $open_claims->orderBy($sorter['field'], $sorter['order'] == 'descend' ? 'DESC' : 'ASC');
        }

        $open_claims = $open_claims->paginate(10);
        
        $response = responseBuilder()->success("Found following open claims", $open_claims, false);
        $this->urlComponents('Open Claims Transactions', $response, 'Medical_Claim_Management');
        return $response;
    }

    // all transactions of a specific claim in an organization
    public function getClaimTransactions(Request $request)
    {
        $user = \Auth::user();
        $get = $request->all();
        if (empty($get['claim_id'])) {
            return responseBuilder()->error('Please provide claim-id', 404, false);
        }
        $medical_claim_id = $get['claim_id'];

        $org_data = \App\Models\Organization::userOrganization($user, false);
        if (empty($org_data)) {
            return responseBuilder()->error('User doesn\'t belong to any organization', 404, false);
        }
        $user_org_id = $org_data->organization_user[0]->organization_id;

        $user_roles = $user->roles;
        $user_roles_arr = [];
        foreach ($user_roles as $key => $user_role) {
            array_push($user_roles_arr, $user_role->id);
        }

        $approval_process = \App\Models\PolicyApprovalProcess::where('organization_id', $user_org_id)
                                                               ->whereIn('role_id', $user_roles_arr)
                                                               ->get();
        $approval_process_orders_arr = [];
        if (!empty($approval_process)) {
            foreach ($approval_process as $key => $value) {
                array_push($approval_process_orders_arr, $value->approval_order);
            }
        }

        $claims = \App\Models\MedicalClaimTransactionHistory::where([
                                                        'medical_claim_id' => $medical_claim_id,
                                                        'organization_id' => $user_org_id,
                                                     ])
                                                     ->orderBy('created_at', 'ASC')
                                                     ->get();

        foreach ($claims as $key => $claim) {
            $claim->lab_reports_doc_urls = unserialize($claim->lab_reports_doc_urls);
            $claim->invoice_doc_urls = unserialize($claim->invoice_doc_urls);
            $claim->prescription_doc_urls = unserialize($claim->prescription_doc_urls);
            $claim->others_doc_urls = unserialize($claim->others_doc_urls);
            $claim->in_patient_policy_relationship_types = unserialize($claim->in_patient_policy_relationship_types);
            $claim->out_patient_policy_relationship_types = unserialize($claim->out_patient_policy_relationship_types);
            $claim->maternity_policy_relationship_types = unserialize($claim->maternity_policy_relationship_types);
            $claim->attachments = unserialize($claim->attachments);
        }

        $result =[
            'orders' => $approval_process_orders_arr,
            'claims' => $claims
        ];
        
        $response = responseBuilder()->success("Found following claims", $result, false);
        $this->urlComponents('Get Claim Transactions', $response, 'Medical_Claim_Management');
        return $response;
    }

    public function calculateMedicalConsumptions(Request $request)
    {
        $user = \Auth::user();
        $get = $request->all();
        $employee_code = $get['employee_code'];
        $year = $get['year'];
        
        $org_data = \App\Models\Organization::userOrganization($user, false);
        if (empty($org_data)) {
            return responseBuilder()->error('User doesn\'t belong to any organization', 404, false);
        }
        $user_org_id = $org_data->organization_user[0]->organization_id;

        $start_date = $year.'-01-01 00:00:00';
        $end_date = $year.'-12-31 00:00:00';

        $amount_consumptions = \App\Models\MedicalClaimTransactionHistory::select(\DB::raw('`claim_for_cnic`,
                        SUM(ipd_approved_amount) AS total_ipd_approved_amount,
                        SUM(opd_approved_amount) AS total_opd_approved_amount, 
                        SUM(maternity_approved_amount) AS total_maternity_approved_amount,
                        SUM(special_approved_amount) AS total_special_approved_amount'))
                                ->whereBetween('medical_claim_created_at', [$start_date, $end_date])
                                ->where([
                                    'organization_id'           => $user_org_id,
                                    'claimed_by_employee_code'  => $employee_code,
                                    'action'                    => 'Completed'
                                ])
                                ->groupBy('claim_for_cnic')
                                ->get();

        $result = [];
        foreach ($amount_consumptions as $key => $amount_consumption) {
            if (!empty($amount_consumption->total_ipd_approved_amount)) {
                $result['IPD'][$amount_consumption->claim_for_cnic] = $amount_consumption->total_ipd_approved_amount;
            }
            if (!empty($amount_consumption->total_opd_approved_amount)) {
                $result['OPD'][$amount_consumption->claim_for_cnic] = $amount_consumption->total_opd_approved_amount;
            }
            if (!empty($amount_consumption->total_maternity_approved_amount)) {
                $result['Maternity'][$amount_consumption->claim_for_cnic] = $amount_consumption->total_maternity_approved_amount;
            }
            if (!empty($amount_consumption->total_special_approved_amount)) {
                $result['Special'][$amount_consumption->claim_for_cnic] = $amount_consumption->total_special_approved_amount;
            }
        }

        $response = responseBuilder()->success("Found following claims", $result);
        $this->urlComponents('Calculate Medical Consumptions', $response, 'Medical_Claim_Management');
        return $response;
    }

    public function processClaim(Request $request)
    {
        $user = \Auth::user();
        $post = $request->all();
        $id = $post['parent_transaction_id'];
        $claim_transaction_history = \App\Models\MedicalClaimTransactionHistory::findorFail($id);
        $next_claim_transaction_history = $claim_transaction_history->toArray();

        $action_taken_by = $user->id;
        $action_taken_by_name = $user->first_name.' '.$user->last_name;

        $policy_approval_process = \App\Models\PolicyApprovalProcess::where([
                    'approval_order' => $claim_transaction_history->policy_approval_process_order,
                    'organization_id'   => $claim_transaction_history->organization_id
                ])
                ->first();

        $sum = $post['ipd_approved_amount'] + $post['opd_approved_amount'] + $post['maternity_approved_amount'] + $post['special_approved_amount'];
        if ($sum > $claim_transaction_history->claimed_amount) {
            return responseBuilder()->error('Sorry claimed amount is lesser than approved', 404, false);
        }

        $next_policy_approval_process = \App\Models\PolicyApprovalProcess::where([
                    'approval_order' => ($claim_transaction_history->policy_approval_process_order)+1,
                    'organization_id'   => $claim_transaction_history->organization_id
                ])
                ->first();


        if (empty($next_policy_approval_process) && !strcasecmp($post['action'], 'Approved')) {
            $action = 'Completed';
        }
        else {
            $action = $post['action'];
        }

        $time = now();
        $claim_transaction = [
            "action"                    => $action,
            "ipd_approved_amount"       => $post['ipd_approved_amount'],
            "opd_approved_amount"       => $post['opd_approved_amount'],
            "maternity_approved_amount" => $post['maternity_approved_amount'],
            "special_approved_amount"   => $post['special_approved_amount'],
            "ipd_consumed_amount"       => $post['ipd_consumed_amount'],
            "opd_consumed_amount"       => $post['opd_consumed_amount'],
            "maternity_consumed_amount" => $post['maternity_consumed_amount'],
            "special_consumed_amount"   => $post['special_consumed_amount'],
            "comments"                  => !empty($post['comments'])? $post['comments']: null,
            "external_comments"         => !empty($post['external_comments'])? $post['external_comments']: null,
            "special_limit_comments"    => !empty($post['special_limit_comments'])? $post['special_limit_comments']: null,
            "attachments"               => !empty($post['attachments'])? serialize($post['attachments']): serialize([]),
            "is_completed"              => "Y",
            'action_taken_by'           => $user->id,
            'action_taken_by_name'      => $user->first_name.' '.$user->last_name,
            'action_taken_role_id'      => $policy_approval_process->role_id,
            'action_taken_role_title'   => $policy_approval_process->role->title,
            'updated_at'                => $time,
            'updated_by'                => $user->id
        ];

        $claim_transaction_history->update($claim_transaction);
        
        if (empty($next_policy_approval_process) || !strcasecmp($post['action'], 'On Hold') || !strcasecmp($post['action'], 'Decline')) {
            $claim = \App\Models\MedicalClaim::findorFail($claim_transaction_history->medical_claim_id);
            if (!strcasecmp($post['action'], 'Approved')) {
                $claim->status = $post['action']; // means Completed
                $claim->approved_amount = $sum;
            }
            elseif (!strcasecmp($post['action'], 'Decline')) {
                $claim->status = $post['action']; // means DECLINE
            }
            else {
                $claim->status = $post['action']; // means On Hold
            }
            $pushNote = ['data' => ['title' => env('APP_NAME')." - Claim Status has been changed to {$post['action']}",
                'body' => "Claim {$claim_transaction_history->medical_claim_serial_no} status has been changed to {$post['action']}", 
            'click_action' => 'CLAIM_STATUS_PUSH',
                        'subTitle' => 'Medical Claim'],
             'user_id' => $claim_transaction_history->claimed_by, 'created_by' => $user->id];
            $pushReceiver =['id' => $claim_transaction_history->claimed_by];
            $pushSender = $user->toArray();
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);
            
            $claim->updated_at = time();
            $claim->update($claim->toArray());
            $response = responseBuilder()->success('Claim '.$claim_transaction_history->medical_claim_serial_no.' processed');
            $this->urlComponents('Claim Process', $response, 'Medical_Claim_Management');
            return $response;
        }
        else {
            unset($next_claim_transaction_history['id']);
            $next_claim_transaction_history['parent_transaction_id'] = $claim_transaction_history->id;
            $next_claim_transaction_history['policy_approval_process_order'] = $next_policy_approval_process->approval_order;
            $next_claim_transaction_history['created_at'] = now();
            $next_claim_transaction_history['updated_at'] = now();
            $next_claim_transaction_history['created_by'] = $user->id;
            $next_claim_transaction_history['updated_by'] = $user->id;
            $next_claim_transaction = \App\Models\MedicalClaimTransactionHistory::create($next_claim_transaction_history);
            $response = responseBuilder()->success('Claim '.$claim_transaction_history->medical_claim_id.' processed');
            $this->urlComponents('Claim Process', $response, 'Medical_Claim_Management');
            return $response;
        }
    }

    public function update(\App\Http\Requests\MedicalClaim $request, $id)
    {
        $claim = new MedicalClaim();
        $response = $claim->updateMedicalClaimById($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Update Claim', $response, 'Medical_Claim_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function index()
    {
        $oMedicalClaim = new MedicalClaim();
        $data = $oMedicalClaim->getMyMedicalClaims();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, false);
            $this->urlComponents('ALL Medical Claims List', $response, 'Medical_Claim_Management');
            return $response;
        }
        $code = (!empty($data['code'])) ? $data['code'] : 422;
        return responseBuilder()->error('Something went wrong', $code, false);
    }

    public function availablePolicyLimits(Request $request)
    {
        $user = \Auth::user();
        $org_data = \App\Models\Organization::userDefaultOrganization($user, false);
        if (!empty($org_data)) {
            $employee_code = $org_data->organization_user[0]->employee_code;
            $employee_opd_limit = $org_data->organization_user[0]->opd_limit;
            $user_org_id = $org_data->organization_user[0]->organization_id;
            $IPD_total = -1;
            $OPD_total = -1;
            $maternity_total = -1;
            if (!empty($org_data->organization_user[0]->grade_id)) {
                $user_grade_id = $org_data->organization_user[0]->grade_id;
                $policies = OrganizationPolicy::where('organization_id', $user_org_id)
                                                ->where('grade_id', $user_grade_id)
                                                ->orWhere(function($policyQuery) use ($user_org_id) {
                                                    $policyQuery->whereNull('grade_id')
                                                    ->where('organization_id', $user_org_id);
                                                })
                                                ->orderBy('grade_id', 'desc')
                                                ->get();
            }
            else {
                $policies = OrganizationPolicy::where('organization_id', $user_org_id)
                                                ->where('policy_level', '!=', config('app.hospitallCodes')['grade'])
                                                ->get();
            }

            $basic_salary = !empty($org_data->organization_user[0]->basic_salary)? $org_data->organization_user[0]->basic_salary: 0;

            foreach ($policies as $key => $policy) {
                switch ($policy->policy_type) {
                    case config('app.hospitallCodes')['in_patient']:
                        if ($IPD_total == -1) {
                            $IPD_total = $policy->indoor_limit;
                        }
                        break;

                    case config('app.hospitallCodes')['out_patient']:
                        if ($OPD_total == -1) {
                            if (!strcasecmp($policy->policy_level, config('app.hospitallCodes')['user'])) {
                                $OPD_total = $employee_opd_limit;
                            }
                            else {
                                if (!strcasecmp($policy->type, config('app.hospitallCodes')['percentage'])) {
                                    $outdoor_amount = ($policy->outdoor_amount*$basic_salary)/100;
                                    $OPD_total = $outdoor_amount;
                                }
                                else {
                                    $OPD_total = $policy->outdoor_amount;
                                }
                            }
                        }
                        break;

                    case config('app.hospitallCodes')['maternity']:
                        if ($maternity_total == -1) {
                            $maternity_total = $policy->maternity_csection_case_limit;
                        } 
                        break;
                }
            }

            $IPD_total = ($IPD_total == -1)? 0: $IPD_total;
            $OPD_total = ($OPD_total == -1)? 0: $OPD_total;
            $maternity_total = ($maternity_total == -1)? 0: $maternity_total;

            $IPD_consumed = 0;
            $OPD_consumed = 0;
            $maternity_consumed = 0;

            $query = "SELECT SUM(ipd_approved_amount) AS IPD_consumed, SUM(opd_approved_amount) AS OPD_consumed, SUM(maternity_approved_amount) AS maternity_consumed
                        FROM medical_claim_transaction_history
                        WHERE `action` = 'Completed'
                        AND `organization_id` = '{$user_org_id}'
                        AND claimed_by_employee_code = '{$employee_code}'";
            $consumed_amounts = \DB::select($query);

            $IPD_consumed = !empty($consumed_amounts[0]->IPD_consumed)? $consumed_amounts[0]->IPD_consumed: 0;
            $OPD_consumed = !empty($consumed_amounts[0]->OPD_consumed)? $consumed_amounts[0]->OPD_consumed: 0;
            $maternity_consumed = !empty($consumed_amounts[0]->maternity_consumed)? $consumed_amounts[0]->maternity_consumed: 0;

            $result['ipd']['total_limit'] = $IPD_total;
            $result['ipd']['consumed_limit'] = $IPD_consumed;
            $result['opd']['total_limit'] = $OPD_total;
            $result['opd']['consumed_limit'] = $OPD_consumed;
            $result['maternity']['total_limit'] = $maternity_total;
            $result['maternity']['consumed_limit'] = $maternity_consumed;

            $response = responseBuilder()->success('Available Policy Limits', $result, false);
            $this->urlComponents('Available Policy Limits', $response, 'Medical_Claim_Management');
            return $response;  
        }
        return responseBuilder()->error('please link yourself to an organization', 400, false);
    }
}
