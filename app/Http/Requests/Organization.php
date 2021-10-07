<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;

class Organization extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'bail|required|between:2,250',
            'organization_type_id' => 'required',
            'email' => 'required|email|max:120',
            //'address' => 'required',
            //'city_id' => 'bail|exists:cities,id',
            //'contact_number' =>'required',
        ];

        if(FormRequest::isMethod('PUT')) {
            unset($rules['address']);
        }

        return $rules;
    }
}
