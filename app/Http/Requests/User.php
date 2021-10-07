<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;

class User extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules =  [
            'first_name' => 'required|between:2,80',
            //'last_name' => 'required|between:2,80',
            //'contact_number' => 'required',
            'email' => 'bail|required|email|max:120|unique:users',
            'cnic' => 'bail|required|numeric|digits:13|unique:users',
            //'cnic' => 'bail|required|numeric|digits:13', //test
            'contact_number' => 'unique:users',
            'facebook_id' => 'unique:users',
            'google_plus_id' => 'unique:users',
            //'dob' => 'date',
            //'address' => 'required',
            //'city_id' => 'bail|required|exists:cities,id',
            'designation' => 'between:2,120', 
            'gender' => 'in:Male,Female,Other',
            //'basic_salary' => 'numeric',
            //'gross_salary' => 'numeric'
        ];
        //dd(FormRequest::isMethod('PUT'));
        if(FormRequest::isMethod('PUT')){
            $tempArr = explode('/', FormRequest::path());
            $userId = end($tempArr);

            if(intval($userId)>0){
                $rules['email'] = $rules['email'].',email,'.$userId;
                $rules['cnic'] = $rules['cnic'].',cnic,'.$userId;
                $rules['contact_number'] = $rules['contact_number'] . ',contact_number,' . $userId;
                $rules['facebook_id'] = $rules['facebook_id'] . ',facebook_id,' . $userId;
                $rules['google_plus_id'] = $rules['google_plus_id'] . ',google_plus_id,' . $userId;
            }
        }

        //dd($rules);
        //dd($);
        return $rules;
    }
}
