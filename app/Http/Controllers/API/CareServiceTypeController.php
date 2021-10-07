<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CareTypeService;
use App\Models\RoleUser;

class CareServiceTypeController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $CareTypeService = new CareTypeService();
        $data = $CareTypeService->getCareTypeServices();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords);
            $this->urlComponents('List all care services', $response, 'Secondary_Care_services');
            return $response;
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function store(\App\Http\Requests\CareTypeService $request)
    {
        $CareTypeService = new CareTypeService();
        $res = $CareTypeService->addCareTypeService($request);

        if($res['status'] === true){
            $data = $res['data']??[];
            $response = responseBuilder()->success($res['message'], $data);
            $this->urlComponents('Add new care service', $response, 'Secondary_Care_services');
            return $response;
        }
    }

    public function update(\App\Http\Requests\CareTypeService $request, $id)
    {
        $CareTypeService = new CareTypeService();
        $response = $CareTypeService->updateCareTypeServiceById($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Update care service', $response, 'Secondary_Care_services');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function destroy($id)
    {
        $CareTypeService = new CareTypeService();
        $res = $CareTypeService->deleteByUserAndId($id);
        if($res['status']=== true){
            $response = responseBuilder()->success($res['message']);
            $this->urlComponents('Remove care service', $response, 'Secondary_Care_services');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
}
