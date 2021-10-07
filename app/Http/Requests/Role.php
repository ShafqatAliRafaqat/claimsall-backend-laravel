<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class Role extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if(FormRequest::isMethod('PUT')){
            $rules = [
                'title'         => 'required',
                //'description'   => 'required|unique:roles',
            ];
        }
        else {
            $rules = [
                'title'         => 'required|unique:roles',
                //'description'   => 'required|unique:roles',
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return [
                'title.unique' => 'This role has already been added in the system',
                //'description.unique' => 'This description has already been added in the system',
            ];
    }
}
