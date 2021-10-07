<?php

namespace App\Http\Requests;

//use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\SynavosRequest;
use \Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
class Module extends SynavosRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
        ];

        return $rules;
    }
}
