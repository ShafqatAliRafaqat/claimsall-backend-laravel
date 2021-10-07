<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\MedicalClaimTransactionHistory;

class ClaimHistoryController extends Controller {

    public function index() {
        $user = \Auth::user();
        $message = 'No data found';
        $userRoles = $user->roles()->select(['id', 'title'])->get()->toArray();
        $userOrgData = Organization::defaultOrganizationData($user);
        if (isset($userOrgData['status']) && $userOrgData['status'] === false) {
            $message = $userOrgData['message'];
            goto end;
        }
        $orgAdminId = \App\Models\Role::getRoleID('orgAdmin')->id;
        $myRoleIds = array_column($userRoles, 'id');

//        \DB::enableQueryLog();
        $approvalProcesses = $userOrgData->policy_approval_processes()->whereIn('role_id', $myRoleIds);
        if(in_array($orgAdminId, $myRoleIds)){
            $approvalProcesses->orWhereNull('organization_id');
        }
        $approvalProcessData = $approvalProcesses->get()->toArray();
//        $queries = \DB::getQueryLog();
//        dump($queries);
        if(empty($approvalProcessData)){
            $message = 'Create your Policy approval process first to see data.'; 
            goto end;
        }
        $medicalClaims = MedicalClaimTransactionHistory::getMedicalClaims($approvalProcessData, $user);
        dump($approvalProcessData);
        die;
        dump($userRoles);
        dump($userOrgData);
        die;
        


        end:

        return responseBuilder()->error($message, 400);
    }
}
