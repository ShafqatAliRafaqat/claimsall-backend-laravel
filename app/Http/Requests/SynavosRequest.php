<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SynavosRequest extends FormRequest {

    public function response(array $errors) {
        if ($this->expectsJson()) {
            $firstErrorMessage = array_first($errors);
            $response = ['status_code' => 400, 'message' => $firstErrorMessage[0], 'data' => [], 'pagination' => new \stdClass()];
            return response($response);
        }
        return $this->redirector->to($this->getRedirectUrl())
                        ->withInput($this->except($this->dontFlash))
                        ->withErrors($errors, $this->errorBag);
    }

}
