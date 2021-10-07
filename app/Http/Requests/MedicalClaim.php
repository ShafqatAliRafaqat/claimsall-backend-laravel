<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class MedicalClaim extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        
        $rules = [
            //'name' => 'required|between:5,220',
            'categories' => "required",
            //'documents' => 'required',
            //'care_type' => 'required|in:In-Patient,Out-Patient',
            'claim_type_id' => 'bail|required|exists:mysql.claim_types,id',
            'claim_amount' => 'required|numeric',
            'organization_id' => 'bail|required|exists:organizations,id',
        ];
        if(Request::has('is_personal') && Input::get('is_personal')=='N'){
            $rules['relationship_id'] = 'required';
        }
       if(FormRequest::isMethod('PUT')){
           unset($rules['organization_id']);
           unset($rules['claim_type_id']);
       }
        return $rules;
    }
}
