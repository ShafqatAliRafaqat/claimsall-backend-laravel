<?php

namespace App\Http\Requests;

//use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class EmergencyContact extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            //'phone_number'         => 'required|unique:emergency_contact',
            //'description'   => 'required|unique:roles',
        ];

        /*if(Request::has('_method') && Request::get('_method')=='PUT'){
            unset($rules['description']);
        }*/

        return $rules;
    }

    /*public function messages()
    {
        return [
                'phone_number.unique' => 'This phone number has already been added in the system',
                //'description.unique' => 'This description has already been added in the system',
            ];
    }*/
}
