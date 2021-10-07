<?php

namespace App\Traits;

use App\Models\WebService;
use App\Models\AuditTrail;

trait WebServicesDoc {

    public function urlComponents($title, $responseSample, $module = '') {
        try{
            $this->logResponse($title, $responseSample);
        } catch (\Exception $e){}
        $headerParams = request()->header();
        if (isset($headerParams['documentation'])) {
            if ($headerParams['documentation'][0] == 'Y') {
                $webServicesDoc = [];
                $webServicesDoc['title'] = $title;
                $currentMiddleware = request()->route()->computedMiddleware;
                $bodyParams = request()->all();
                $headers = [];
                foreach ($headerParams as $key => $header) {
                    if (in_array($key, ['authorization', 'accept'])) {
                        $headers[$key] = $header[0];
                    }
                }
                foreach ($currentMiddleware as $key => $middleware) {
                    if (!is_object($middleware)) {
                        $webServicesDoc['auth'] = ($middleware == "auth:api") ? 'yes' : 'no';
                    }
                }
                $webServicesDoc['method_name'] = \Route::getCurrentRoute()->getActionMethod();
                $webServicesDoc['url'] = str_replace(config('app.locale') . '/api/', '', request()->path());
                $webServicesDoc['method'] = request()->method();
                $webServicesDoc['module'] = $module;
                $webServicesDoc['header_params'] = json_encode($headers);
                $webServicesDoc['body_params'] = json_encode($bodyParams);
                $webServicesDoc['response_sample'] = $responseSample->getContent();
                WebService::updateOrCreate([
                    'method_name' => $webServicesDoc['method_name'],
                    'url' => $webServicesDoc['url'],
                    'method' => $webServicesDoc['method']], $webServicesDoc);
                return $webServicesDoc;
            }
        }
    }

    public function logResponse($title, $responseSample) {
        $headerParams = request()->header();
        $bodyParams = request()->all();
        $url =  str_replace(config('app.locale') . '/api/', '', request()->path());
        $requestType = request()->method();
        $createdBy= null;
        $currentMiddleware = request()->route()->computedMiddleware;
        foreach ($currentMiddleware as $key => $middleware) {
            if (!is_object($middleware) && ($middleware == "auth:api")) {
                $createdBy = \Auth::user()->id;
            }
        }
        $response = $responseSample->getContent();
        AuditTrail::create(['activity_title' => $title, 'request_header' => json_encode($headerParams),
            'request_body' => json_encode($bodyParams),
            'request_method' => $requestType, 'request_url' => $url, 'response_data' => $response,
            'ip_address' => request()->ip(), 'created_at' => now(), 'created_by' => $createdBy]);
    }
}
