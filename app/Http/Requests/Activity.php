<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class Activity extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if(FormRequest::isMethod('PUT')) {
            $rules = [
                'title'         => 'required',
                'start_date'    => 'required',
                'end_date'      => 'required'
            ];
        }
        else {
            $rules = [
                //'title'         => 'required|unique:activity',
                'start_date'    => 'required',
                'end_date'      => 'required'
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return [
                'title.unique' => 'This activity has already been added in the system',
            ];
    }
}
