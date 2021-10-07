<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class Appointment extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        if(FormRequest::isMethod('PUT')) {
            $rules = [
                
            ];
        }
        else {
            $rules = [
                'user_id' => 'bail|required|numeric|exists:users,id',
                'name' => 'required',
                'appointment_date' => 'bail|required|date_format:Y-m-d H:i:s'
            ];
        }
        return $rules;
    }

    public function messages()
    {
        return [
                
            ];
    }
}
