<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CareService;
use App\Models\RoleUser;
use Illuminate\Support\Facades\DB;

class CareServiceController extends Controller
{
    use \App\Traits\WebServicesDoc;

    public function getCareServices(Request $request)
    {
        $post = $request->all();
        //DB::connection()->enableQueryLog();
        $CareServices = CareService::query();
        $CareServices = $CareServices->where(function ($query) use ($post) { // put bracket around multiple where clauses
                    if (!empty($post['searchFilters'])) {
                        foreach ($post['searchFilters'] as $key => $value) {
                            $query = $query->orWhere($key, 'LIKE', '%' . $value . '%');
                        }
                    }
                });

        $ignoreList_arr = ['history'];
        $history = null;
        if (!empty($post['filters'])) {
            foreach ($post['filters'] as $key => $value) {
                if(!in_array($value, $ignoreList_arr)) {
                    if (is_array($value)) {
                        if (count($value) > 0) {
                            $CareServices = $CareServices->whereIn($key, $value);
                        }
                    } else {
                        $CareServices = $CareServices->where($key, $value);
                    }
                }
            }
            $history = !empty($post['filters']['history']) ? $post['filters']['history']: null;
        }

        if (empty($post['sorter'])) {
            $CareServices = $CareServices->orderBy("updated_at", "DESC");
        } else {
            $sorter = $post['sorter'];
            $CareServices = $CareServices->orderBy($sorter['field'], $sorter['order'] == 'descend' ? 'DESC' : 'ASC');
        }

        if (!empty($history)) {
            $CareServices = $CareServices->where('status', '!=', 'Pending');
        }
        else {
            $CareServices = $CareServices->where('status', 'Pending');
        }

        $CareServices = $CareServices->with('care_type_services');
        $CareServices = $CareServices->with('documents');

        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";
                $CareServices = $CareServices->whereBetween('created_at', array($start_date, $end_date));
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $CareServices = $CareServices->where('created_at', '>=', $start_date);
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                $end_date = $report['end_date']." 23:59:59";
                $CareServices = $CareServices->where('created_at', '<=', $end_date);
            }
            $CareServices = $CareServices->get();
        }
        else {
            $CareServices = $CareServices->paginate(10);
        }
        //return DB::getQueryLog();

        if (count($CareServices) > 0) {
            foreach ($CareServices as $key => $CareService) {
                $CareService['care_services_type'] = $CareService->care_type_services->name;
                unset($CareService['care_type_services']);
                if (!empty($CareService['documents'])) {
                    foreach ($CareService['documents'] as $key => $document) {
                        $care_service_type_url = getCareServiceDocumentPath(FALSE);
                        $absFullPath = storage_path('app/public') .'/common/care_service_type_docs/'.$document->file_name;
                        if (!empty($document->file_name) && file_exists($absFullPath)) {
                            $document['url'] = $care_service_type_url.'/'.$document->file_name;
                            $tempExt = explode('.', $document->file_name);
                            $document['extension'] = '.'. end($tempExt);
                            //$absFullPath = getCareServiceDocumentPath(false);
                            //dump($absFullPath); die;
                            $document['mime_type'] = \File::mimeType($absFullPath);
                        }
                    }
                }
            }
        }

        $msg = 'Found Careservices Requests';
        if (!$CareServices) {
            $CareServices = [];
            $msg = 'No Careservice Request found';
        }

        $response = responseBuilder()->success($msg, $CareServices, false);
        $this->urlComponents('List of Careservices Requests', $response, 'Secondary_CareServices_Requests');
        return $response;
    }

    public function careserviceRequests(Request $request)
    {
        $post = $request->all();
        $user = \Auth::user();
        //DB::connection()->enableQueryLog();
        $CareServices = CareService::query();
        $CareServices = $CareServices->where('created_by', $user->id);
        $CareServices = $CareServices->orderBy("updated_at", "DESC");
        $CareServices = $CareServices->with('care_type_services');
        $CareServices = $CareServices->with('documents');
        $CareServices = $CareServices->paginate(10);
        //return DB::getQueryLog();

        if (count($CareServices) > 0) {
            foreach ($CareServices as $key => $CareService) {
                $CareService['care_services_type'] = $CareService->care_type_services->name;
                unset($CareService['care_type_services']);
                if (!empty($CareService['documents'])) {
                    foreach ($CareService['documents'] as $key => $document) {
                        $care_service_type_url = getCareServiceTypeDocumentPath(FALSE);
                        $absFullPath = storage_path('app/public') .'/common/care_service_type_docs/'.$document->file_name;
                        if (!empty($document->file_name) && file_exists($absFullPath)) {
                            $document['path'] = $care_service_type_url.'/'.$document->file_name;
                            $tempExt = explode('.', $document->file_name);
                            $document['extension'] = '.'. end($tempExt);
                            $absFullPath = getCareServiceDocumentPath(false);
                            //dump($absFullPath); die;
                            $document['mime_type'] = \File::mimeType($absFullPath);
                        }
                    }
                }
            }
        }

        $msg = 'Found Careservices Requests';
        if (!$CareServices) {
            $CareServices = [];
            $msg = 'No Careservice Request found';
        }

        $response = responseBuilder()->success($msg, $CareServices, false);
        $this->urlComponents('List of Careservices Requests', $response, 'Secondary_CareServices_Requests');
        return $response;
    }

    public function careserviceRequest(Request $request)
    {
        $get = $request->all();
        $id = $get['id'];
        $CareService = CareService::with('care_type_services')->with('documents')->findOrFail($id);
        $CareService['care_services_type'] = $CareService->care_type_services->name;
        unset($CareService['care_type_services']);
        if (!empty($CareService['documents'])) {
            foreach ($CareService['documents'] as $key => $document) {
                $care_service_type_url = getCareServiceDocumentPath(FALSE);
                if (!empty($document->file_name)) {
                    $document['url'] = $care_service_type_url.'/'.$document->file_name;
                }
            }
        }
        $response = responseBuilder()->success('Details of CareService Request', $CareService->toArray());
        $this->urlComponents('Details of CareService Request', $response, 'Secondary_CareServices_Requests');
        return $response;
    }

    public function changeCareServiceStatus(Request $request)
    {
        $rules = ['id' => 'required', 'status' => 'required', 'feedback' => 'required'];
        $request->validate($rules);
        $post = $request->all();
        $id = $post['id'];
        $status = $post['status'];
        $feedback = $post['feedback'];
        $CareService = \App\Models\CareService::where(['id' =>$id])->first();
        if (!$CareService) {
            return ['status' => false, 'code' => 400, 'message' => 'CareService Request whose status you want to change not found'];
        }
        if ($CareService->status == $status) {
            $response = responseBuilder()->success('Status of CareService Request is already changed to '.$status);
            $this->urlComponents('Change CareService Request Status', $response, 'Secondary_CareServices_Requests');
            return $response;
        }
        else {
            $CareService->update($post);
            $care_service_type_name = $CareService->care_type_services->name;
            $user = \App\User::where(['email' => $CareService->email])->first();
            $parameters = [
                'receiver_email'         => $user->email,
                'receiver_name'          => $user->first_name.' '.$user->last_name,
                'sender_name'            => env('APP_NAME'),
                'care_service_type_name' => $care_service_type_name,
                'care_service_id'        => $CareService->id,
                'start_date'             => $CareService->start_date,
                'start_time'             => $CareService->start_time,
                'name'                   => $CareService->name,
                'contact_number'         => $CareService->contact_number,
                'address'                => $CareService->address,
                'description'            => $CareService->description,
                'status'                 => $CareService->status,
                'feedback'               => $CareService->feedback,
                'cc_flag'                => false
            ];

            event(new \Illuminate\Auth\Events\Registered($parameters));
            dispatch(new \App\Jobs\CareServiceRespond($parameters));
            
            $response = responseBuilder()->success('Status of CareService Request is changed to '.$status);
            $this->urlComponents('Change CareService Request Status', $response, 'Secondary_CareServices_Requests');
            return $response;
        }
    }

    public function show($id)
    {
        $CareService = CareService::with('care_type_services')->with('documents')->findOrFail($id);
        $CareService['care_services_type'] = $CareService->care_type_services->name;
        unset($CareService['care_type_services']);
        if (!empty($CareService['documents'])) {
            foreach ($CareService['documents'] as $key => $document) {
                $care_service_type_url = getCareServiceDocumentPath(FALSE);
                if (!empty($document->file_name)) {
                    $document['url'] = $care_service_type_url.'/'.$document->file_name;
                }
            }
        }
        $response = responseBuilder()->success('Details of CareService Request', $CareService->toArray());
        $this->urlComponents('Details of CareService Request', $response, 'Secondary_CareServices_Requests');
        return $response;
    }

    public function index()
    {
        $CareService = new CareService();
        $data = $CareService->getCareServices();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            return responseBuilder()->success($data['message'], $medicalRecords);
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function store(\App\Http\Requests\CareService $request)
    {
        $CareService = new CareService();
        $response = $CareService->addCareService($request);
        if(isset($response['status']) && $response['status'] === true){
            return responseBuilder()->success($response['message']);
        }
        
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function update(\App\Http\Requests\CareService $request, $id)
    {
        $CareService = new CareService();
        $response = $CareService->updateCareServiceById($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            return responseBuilder()->success($response['message']);
        }
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function destroy($id)
    {
        $CareService = new CareService();
        $res = $CareService->deleteByUserAndId($id);
        if($res['status']=== true){
            return responseBuilder()->success($res['message']);
        }
        return responseBuilder()->error($res['message']);
    }
}
