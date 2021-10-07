<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class OrganizationType extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name'         => 'required|unique:organization_type',
            'code'         => 'required|unique:organization_type',
            //'description'   => 'required|unique:organization_type',
        ];

        /*if(FormRequest::isMethod('PUT')){
            unset($rules['description']);
        }*/

        return $rules;
    }

    public function messages()
    {
        return [
                'name.unique' => 'Organization Type with this name has already been added in the system',
                'code.unique' => 'Organization Type with this code has already been added in the system',
                //'description.unique' => 'This description has already been added in the system',
            ];
    }
}
