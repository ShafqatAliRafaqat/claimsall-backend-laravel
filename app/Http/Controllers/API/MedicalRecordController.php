<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Libraries\Uploader;
use File;
use App\Models\Document;
use App\Models\MedicalRecord;

class MedicalRecordController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $oMedicalRecord = new MedicalRecord();
        $data = $oMedicalRecord->getMedicalRecords();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, FALSE);
            $this->urlComponents('ALL Medical Records List', $response, 'Medical_Record_Management');
            return $response;
        }
        $code = (!empty($data['code'])) ? $data['code'] : 422;
        $msg = (!empty($data['message'])) ? $data['message'] : 'Something went wrong';
        return responseBuilder()->error($msg, $code, false);
    }

    public function store(\App\Http\Requests\MedicalRecord $request)
    {
        $oMedicalRecord = new MedicalRecord();
        $response = $oMedicalRecord->addMedicalRecords($request);
        if(isset($response['status']) && $response['status'] === true){
            $response = responseBuilder()->success($response['message']);
            $this->urlComponents('Add New Medical Records', $response, 'Medical_Record_Management');
            return $response;
        }
        $response['code'] = $response['code'] ?? 422;
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function show($id)
    {
        $oMedicalRecord = new MedicalRecord();
        $res = $oMedicalRecord->getMyMedicalRecordDetails($id);
        if($res){
            $message = ($res['message']) ?? 'Fetch medical record details';
            $data= $res['data'] ?? [];
            $response =  responseBuilder()->success($message, $data);
            $this->urlComponents('Show Detail of Medical Records', $response, 'Medical_Record_Management');
            return $response;
        }
        return responseBuilder()->error('Medical record not found');
    }

    public function update(\App\Http\Requests\MedicalRecord $request, $id)
    {
        $oMedicalRecord = new MedicalRecord();
        $response = $oMedicalRecord->updateMedicalRecord($request, $id);
        if(isset($response['status']) && $response['status'] === true){
            $response= responseBuilder()->success($response['message']);
            $this->urlComponents('Update of Medical Records', $response, 'Medical_Record_Management');
            return $response;
        }
        $response['code'] = $response['code'] ?? 422;
        return responseBuilder()->error($response['message'], $response['code']);
    }

    public function destroy($id)
    {
        $oMedicalRecord = new MedicalRecord();
        $res = $oMedicalRecord->deleteMedicalRecord($id);
        if($res['status']=== true){
            $response =  responseBuilder()->success($res['message']);
            $this->urlComponents('Delete Medical Records', $response, 'Medical_Record_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
    
    public function bulkDelete(Request $request) {
        $request->validate(['ids' => 'required|array']);
        $oMedicalRecord = new MedicalRecord();
        $res = $oMedicalRecord->deleteMedicalRecordsBulk($request);
          if($res['status']=== true){
            $response =  responseBuilder()->success($res['message']);
            $this->urlComponents('Delete Medical Records In BULK', $response, 'Medical_Record_Management');
            return $response;
        }
        return responseBuilder()->error($res['message']);
    }
}
