<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\OrganizationType;
use App\Models\RoleUser;

class OrganizationTypeController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $role = new OrganizationType();
        $data = $role->getOrganizationTypes();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, false);
            $this->urlComponents('Organizations Types', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function getIndustries(Request $request)
    {
        $role = new OrganizationType();
        $data = $role->getSpecificIndustries($request);
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, false);
            $this->urlComponents('List of Industries', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function store(\App\Http\Requests\OrganizationType $request)
    {
        $role = new OrganizationType();
        $response = $role->addOrganizationType($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Store Organization', $response, 'Organization_Management');
            return $response;
        }
        
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function update(\App\Http\Requests\OrganizationType $request, $id)
    {
        $role = new OrganizationType();
        $response = $role->updateOrganizationTypeById($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Update Organization', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function destroy($id)
    {
        $role = new OrganizationType();
        $res = $role->deleteByUserAndId($id);
        if($res['status']=== true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Delete Organization', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
}
