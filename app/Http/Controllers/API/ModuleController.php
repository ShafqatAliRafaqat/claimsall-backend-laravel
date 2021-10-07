<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleRole;

//use Illuminate\Validation\Validator;

class ModuleController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function show($id) // here id is basically role_id in the table module_role
    {
        $result = \App\Models\Module::getDetails($id); 
        $response = responseBuilder()->success('Details of Modules against Role', $result);
        $this->urlComponents('Details of Modules against Role', $response, 'ACL_Modules_Management');
        return $response;
    }

    public function store(\App\Http\Requests\Module $request)
    {
        $post = $request->all();
        $module = new Module();
        $response = $module->manageRoleModule($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Add Module', $response, 'ACL_Modules_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function updateModule(\App\Http\Requests\Module $request)
    {
        $post = $request->all();
        $module = new Module();
        $response = $module->manageRoleModule($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Update Module', $response, 'ACL_Modules_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }
}
