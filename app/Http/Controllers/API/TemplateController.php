<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\RoleUser;

//use Illuminate\Validation\Validator;

class TemplateController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $template = new Template();
        $data = $template->getTemplates();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, false);
            $this->urlComponents('List Templates', $response, 'Template_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function store(\App\Http\Requests\Template $request)
    {
        $post = $request->all();
        $template = new Template();
        $response = $template->addTemplate($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Store Template', $response, 'Template_Management');
            return $response;
        }
        
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function update(\App\Http\Requests\Template $request, $id)
    {
        $template = new Template();
        $response = $template->updateTemplateById($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Update Template', $response, 'Template_Management');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function destroy($id)
    {
        $template = new Template();
        $res = $template->deleteByUserAndId($id);
        if($res['status']=== true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Remove Template', $response, 'Template_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
}
