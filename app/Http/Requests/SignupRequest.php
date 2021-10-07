<?php

namespace App\Http\Requests;

//use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;

class SignupRequest extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules=  [
            'first_name' => 'min:2|max:80',
            'last_name' => 'min:2|max:80',
            'designation' => 'between:2,120', 
            'cnic' => 'bail|required|digits:13|unique:users',
            'gender' => 'in:Male,Female,Others',
            'dob' => 'date',
            'password' => 'required|between:6,20',
            'confirm_password' => 'required|same:password'
        ];
        if(Request::has('contact_number')){
            $rules['contact_number'] = 'bail|required|between:7,15|unique:users';//numeric|
        }
            //$rules['device_id'] = 'required|unique:user_devices';
        //if(Request::has('email')){
            $rules['email'] = 'bail|required|email|unique:users';
        //}
        return $rules;
    }
    
    public function messages() {
        return [
            'cnic.required' => 'CNIC cannot be empty',
            'cnic.digits' => 'Invalid CNIC please enter a valid 13 Digit CNIC Number',
            'cnic.unique' => 'The CNIC you have entered has already been taken. Please enter a valid CNIC.',
            'password.required' => 'Password cannot be empty',
            'password.between' => 'Password cannot be less than 6 Characters or Greater than 20 Characters',
            'email.required' => 'Email cannot be empty',
            'email.email' => 'Invalid Email, Please Enter a Valid Email Address',
            'contact_number.required' => 'Contact Number Cannot be Empty',
            'contact_number.between' => 'Invalid Contact. Please Enter a Valid Contact No. with a format +92XXXXXXXXXX',
            'contact_number.unique' => 'Contact Number already taken',            
        ];
    }
}
