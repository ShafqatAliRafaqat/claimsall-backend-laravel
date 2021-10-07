<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class MedicalRecord extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        
        $rules = [
            'name' => 'required',
//            'documents' => 'required'
            //'categories' => "required",
//            'categories.*.documents' => "required",
        ];
        if(Request::has('is_personal') && Input::get('is_personal')=='N'){
            $rules['relationship_id'] = 'required';
        }
        if(FormRequest::isMethod('PUT')){
            unset($rules['documents']);
        }
        return $rules;
    }
}
