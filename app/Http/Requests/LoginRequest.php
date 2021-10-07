<?php

namespace App\Http\Requests;
use \Illuminate\Support\Facades\Request;
//use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if(Request::has('fb_id')|| Request::has('phone')|| Request::has('google_plus_id')){
            return ['access_token' => 'required'];
        }
        $rules = ['password' => 'required', 'email' => 'required|email'];
        if(Request::input('cnic')){
            unset($rules['email']);
            $rules['cnic'] = 'required|digits:13';
        }
        //$rules['device_id'] = 'required|unique:user_devices';
        return $rules;
    }
    
    public function messages() {
        return [
            'cnic.required' => 'CNIC cannot be empty',
            'email.required' => 'Email cannot be empty',
            'cnic.digits' => 'Invalid CNIC please enter a valid 13 Digit CNIC Number'
            ];
    }
}
