<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleUser;

class RoleController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $role = new Role();
        $data = $role->getRoles();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, false);
            $this->urlComponents('List Roles', $response, 'Roles_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function store(\App\Http\Requests\Role $request)
    {
        $role = new Role();
        $response = $role->addRole($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Store Role', $response, 'Roles_Management');
            return $response;
        }
        
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function update(\App\Http\Requests\Role $request, $id)
    {
        $role = new Role();
        $response = $role->updateRoleById($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('update Role', $response, 'Roles_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function destroy($id)
    {
        $role = new Role();
        $res = $role->deleteByUserAndId($id);
        if($res['status']=== true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Remove Role', $response, 'Roles_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
}
