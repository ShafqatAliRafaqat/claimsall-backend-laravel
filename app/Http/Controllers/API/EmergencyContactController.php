<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use App\Models\RoleUser;

class EmergencyContactController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function index()
    {
        $emergency_contact = new EmergencyContact();
        $data = $emergency_contact->getEmergencyContacts();
        if(isset($data['status']) && $data['status']===true){
            $medicalRecords = (isset($data['data']))? $data['data'] :[];
            $response = responseBuilder()->success($data['message'], $medicalRecords, false);
            $this->urlComponents('List emergency contacts', $response, 'Emergency_Contacts');
            return $response;
        }
        return responseBuilder()->error('Something went wrong');
    }

    public function store(\App\Http\Requests\EmergencyContact $request)
    {
        $emergency_contact = new EmergencyContact();
        $response = $emergency_contact->manageEmergencyContact($request);
        if(isset($response['status']) && $response['status'] === true){
            $result = (isset($response['data']))? $response['data'] :[];
            $response = responseBuilder()->success($response['message'], $result, false);
            $this->urlComponents('Manage emergency contacts', $response, 'Emergency_Contacts');
            return $response;
        }
        return responseBuilder()->error($response['message'], $response['code'], false);
    }
}
