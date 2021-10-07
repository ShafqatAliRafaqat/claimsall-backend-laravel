<?php

namespace App\Http\Requests;

//use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class Template extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'event'         => 'required',
            'subject'         => 'required',
            'body'         => 'required'
            
        ];

        return $rules;
    }

    /*public function messages()
    {
        return [
                'event.unique' => 'This subject has already been added in the system',
            ];
    }*/
}
