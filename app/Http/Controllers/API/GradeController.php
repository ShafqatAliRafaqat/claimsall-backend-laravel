<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Grade;
use Illuminate\Validation\Rule;
use App\User;

//
use Session;
use Excel;
use File;
use PhoneValidator;

class GradeController extends Controller {

    use \App\Traits\WebServicesDoc;
    public function index() {
        $user = User::getOrgUserIfSuperAdmin();
        if(empty($user)){
            return responseBuilder()->error('Not a valid user to fetch grades');
        }
        //print_r($user);        exit('---');
        $organizationData = Organization::userDefaultOrganization($user);
        if (!$organizationData) {
            return responseBuilder()->error('Access denied to this user', 400);
        }
        $organizationData = $organizationData->toArray();
        if (count($organizationData['organization_user']) <= 0) {
            return responseBuilder()->error('Access denied to this user', 400);
        }
        $defaultCompany = $organizationData['organization_user'][0];
        $organizationGrades = $defaultCompany['organization']['grades'];
        $response = responseBuilder()->success("Your organization[{$defaultCompany['organization']['name']}] has following grades", $organizationGrades, false);
        $this->urlComponents('List grades', $response, 'Grades_Management');
        return $response;
    }

    public function store(Request $request) {
        $user = \Auth::user();
        $request->validate(['title' => 'required']);
        $superAdmin = User::isSuperAdmin($user);
        if ($superAdmin === true) {
            $request->validate(['organization' => 'bail|required|exists:mysql.organizations,id']);
            $company = Organization::find($request->get('organization_id'));
            goto end;
        }
        $organizationData = Organization::userDefaultOrganization($user);
        if (!$organizationData) {
            return responseBuilder()->error('You don\'t have any company added for this reqeust', 400);
        }
        $organizationData = $organizationData->organization_user;
        if (count($organizationData) <= 0) {
            return responseBuilder()->error('You don\'t have added any default company', 400);
        }
        $defaultCompany = $organizationData[0];
        $company = $defaultCompany['organization'];
        
        end:
        $request->validate(['title' => [
                Rule::unique('grades')->where(function($query) use($company) {
                            $query->where('organization_id', $company->id);
                            $query->whereNull('deleted_at');
                        })]
        ]);

        $Post = $request->all();
        $Post['created_by'] = $Post['updated_by'] = $user->id;
        $Grades = new \App\Models\Grade($Post);
        $result = $company->grades()->save($Grades);
        if ($result) {
            $response = responseBuilder()->success('New organization grade added');
            $this->urlComponents('Add grade', $response, 'Grades_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while saving');
    }

    public function show($id) {
        $user = User::getOrgUserIfSuperAdmin();
        //$user = \Auth::user();
        $organizationData = Organization::userDefaultOrganization($user, true);
        if (!$organizationData) {
            return responseBuilder()->error('You don\'t have any company added for this reqeust', 400);
        }
        $organizationData = $organizationData->organization_user;
        if (count($organizationData) <= 0) {
            return responseBuilder()->error('You don\'t have added any default company', 400);
        }
        $defaultCompany = $organizationData[0];
        $Grade = Grade::select(['id', 'title', 'description', 'created_at'])->where(['organization_id' => $defaultCompany['organization_id'], 'id' => $id])->first();
        if ($Grade) {
            $response = responseBuilder()->success('Organization\'s grade details', $Grade);
            $this->urlComponents('Show grade', $response, 'Grades_Management');
            return $response;
        }
        return responseBuilder()->error('Organiztion grade not found', 404);
    }

    public function update(Request $request, $id) {
        $user = \Auth::user();
        $request->validate(['title' => 'required']);
        $superAdmin = User::isSuperAdmin($user);
        if ($superAdmin === true) {
            $request->validate(['organization' => 'bail|required|exists:mysql.organizations,id']);
            $company = Organization::find($request->get('organization_id'));
            $companyId = $company->id;
            goto end;
        }
        $Post = $request->all();
        $organizationData = Organization::userDefaultOrganization($user, false);
        if (!$organizationData) {
            return responseBuilder()->error('You don\'t have any company added for this reqeust', 400);
        }
        $organizationData = $organizationData->organization_user;
        if (count($organizationData) <= 0) {
            return responseBuilder()->error('You don\'t have added any default company', 400);
        }
        $defaultCompany = $organizationData[0];
        $companyId =$defaultCompany['organization_id'];
        end:
        $request->validate(['title' => [
            Rule::unique('grades')->where(function($query) use($companyId, $id) {
                        $query->where('organization_id', $companyId);
                        $query->whereNull('deleted_at');
                        $query->where('id', '<>', $id);
                    })]
        ]);
        $Grade = \App\Models\Grade::where(['organization_id' => $defaultCompany['organization_id'], 'id' => $id])->first();
        if (!$Grade) {
            return responseBuilder()->error('Organiztion grade not found', 404);
        }
        $Post['created_by'] = $Post['updated_by'] = $user->id;
        $Grade->fill($Post);
        if ($Grade->save()) {
            $response = responseBuilder()->success('Organization grade updated successfully');
            $this->urlComponents('Update grade', $response, 'Grades_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while saving');
    }

    public function destroy($id) {
        $user = User::getOrgUserIfSuperAdmin();
        $organizationData = Organization::userDefaultOrganization($user, false);
        if (!$organizationData) {
            return responseBuilder()->error('You don\'t have any company added for this reqeust', 400);
        }
        $organizationData = $organizationData->organization_user;
        if (count($organizationData) <= 0) {
            return responseBuilder()->error('You don\'t have added any default company', 400);
        }
        $defaultCompany = $organizationData[0];
        $Grade = Grade::where(['organization_id' => $defaultCompany['organization_id'], 'id' => $id])->first();
        if ($Grade) {
            $organization_user = \App\Models\OrganizationUser::where('grade_id', $Grade->id)->first();
            if (!empty($organization_user)) {
                return responseBuilder()->error('This grade is already assigned to a user, first remove it from there', 404);
            }
            $Grade->update(['deleted_by' => $user->currentUserID]);
            $Grade->delete();
            $response = responseBuilder()->success('Organization grade deleted successfully!');
            $this->urlComponents('Remove grade', $response, 'Grades_Management');
            return $response;
        }
        return responseBuilder()->error('Organiztion grade not found', 404);
    }
}
