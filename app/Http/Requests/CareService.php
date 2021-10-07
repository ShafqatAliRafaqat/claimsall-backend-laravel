<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class CareService extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'care_services_type_id' => 'required',
            'name' => 'bail|required|between:2,250',
            //'contact_number' =>'required',
            'address' => 'required',
            //'gender' => 'required',
            'description' => 'required', // notes where fields will be added according to specific secondary service
            'email' => 'required|email|max:120',
            'start_date' => 'required'
        ];

        /*if(FormRequest::isMethod('PUT')){
            unset($rules['description']);
        }*/

        return $rules;
    }

    /*public function messages()
    {
        return [
                'title.unique' => 'This role has already been added in the system',
                //'description.unique' => 'This description has already been added in the system',
            ];
    }*/
}
