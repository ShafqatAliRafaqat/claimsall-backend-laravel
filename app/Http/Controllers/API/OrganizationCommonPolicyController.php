<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\OrganizationPolicy;
use App\User;
use App\Models\Organization;
use App\Models\OrganizationCommonPolicy;
use App\Models\PolicyCoveredPerson;

class OrganizationCommonPolicyController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $user = \Auth::user();
        $defaultCompany = Organization::defaultOrganizationGrades($user);
        if(!$defaultCompany){
            return responseBuilder()->error('Sorry we unable to find your company', 404, false);
        }
       
        dump($defaultCompany->organization_common_policy()->get()->toArray());
        dump($defaultCompany->toArray());
        die;
        
    }

    public function store(Request $request)
    {
        $user = \Auth::user();
        $rules = [
            'name' => 'required', 'policies' => 'required'];
        $superAdmin = User::isSuperAdmin($user);
        if ($superAdmin === true) {
            $rules['organization_id'] = 'required|exists:mysql.organizations,id|unique';
        }
        $request->validate($rules);
        $Post = $request->all();
        $policyCoveredPersonsData= OrganizationPolicy::validatePolicyData($Post, $user);
        
        $defaultOrganization = Organization::defaultOrganizationGrades($user);
        $commonOrgPolicies = $defaultOrganization->organization_common_policy();
        if($commonOrgPolicies->count()>0){
            return responseBuilder()->error('Sorry, You cannot create multiple policies in same company', 400);
        }
        $Post['created_by'] = $Post['updated_by'] = $user->id;
        $Post['organization_id'] = $defaultOrganization->id;
        $orgPolicy = OrganizationCommonPolicy::create($Post);
        if ($orgPolicy) {
            foreach ($policyCoveredPersonsData as $policyData) {
                $orgPolicy->policy_covered_persons()->save(new PolicyCoveredPerson($policyData));
            }
            $response = responseBuilder()->success('New organization policy created successfully');
            $this->urlComponents('Create New Organization\'s Policy', $response, 'Organizations_Policy_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while creating new policy', 400);
    }
}
