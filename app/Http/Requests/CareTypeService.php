<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class CareTypeService extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        //dump(FormRequest::isMethod('PUT'));die;
        if(FormRequest::isMethod('PUT')) {
            $tempArr = explode('/', FormRequest::path());
            //print_r($tempArr);exit;
            $userId = end($tempArr);
            $rules = [
                //'name' => 'bail|required|between:2,250|unique:care_type_services,id,'.$userId,
                /*'name' => 'bail|required|between:2,250|unique:care_type_services,id,'.$userId,
                'document' => 'required|image'*/
                'document' => 'image'
            ];
        }
        else
        {
            $rules = [
                //'name' => 'bail|required|between:2,250|unique:care_type_services,deleted_at,null',
                'name' => 'bail|required|between:2,250|unique:care_type_services',
                'document' => 'required|image'
            ];
        }
        //dump($rules);die;
        return $rules;
    }

    public function messages()
    {
        return [
                'name.unique' => 'Care Service Type with this name has already been added in the system',
                //'description.unique' => 'This description has already been added in the system',
            ];
    }
}
