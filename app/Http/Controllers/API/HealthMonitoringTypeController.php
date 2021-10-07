<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\HealthMonitoringType;
use App\Http\Libraries\Uploader;

class HealthMonitoringTypeController extends Controller {

    use \App\Traits\WebServicesDoc;

    public function index() {
        $message = 'Health monitoring types are following';
        $types = HealthMonitoringType::all();
        if (!$types) {
            $message = 'Sorry no type found';
            $types = [];
        }
        else {
            foreach ($types as $key => $type) {
                $healthMonitoringType_url = getDocRelativePath(FALSE);   
                $type['url'] = $healthMonitoringType_url.$type->icon;
            }
        }

        $response = responseBuilder()->success($message, $types, false);
        $this->urlComponents('List of Health Monitoring Types', $response, 'Health_Monitoring_Types');
        return $response;
    }

    public function store(Request $request) {
        $user = \Auth::user();
        $request->validate(['name' => 'required|between:2,200|unique:hosMysql.health_monitoring_types',
            'code' => 'min:3|max:100|unique:hosMysql.health_monitoring_types',
            'unit' => 'required|between:1,30', 'icon' => 'required|image']);
        $post = $request->all();
        $uploader = new Uploader();
        $uploader->setFile($post['icon']);
        if ($uploader->isValidFile()===false) {
            return responseBuilder()->error($uploader->getMessage(), 400);
        }
        $path = getDocPath();
        $uploader->upload($path, $uploader->fileName);
        if ($uploader->isUploaded()) {
            $post['icon'] = $uploader->getUploadedPath(false);
        }
        $post['code'] = $code = (!empty($post['code'])) ? $post['code'] : HealthMonitoringType::setCode($post['name']);
        $post['created_by'] = $post['updated_by'] = $user->id;
        $healthMonitoringType = HealthMonitoringType::create($post);
        if ($healthMonitoringType) {
            $response = responseBuilder()->success('Health monitoring type created successfully');
            $this->urlComponents('Create Health Monitoring Type', $response, 'Health_Monitoring_Types');
            return $response;
        }
        return responseBuilder()->error('Something went wrong, error while saving new monitoring type');
    }

    public function show($id) {
        $healthMonitoringType = HealthMonitoringType::findOrFail($id);
        $healthMonitoringType_url = getDocRelativePath(FALSE);   
        $healthMonitoringType['url'] = $healthMonitoringType_url.$healthMonitoringType->icon;
        $response = responseBuilder()->success('Fetch detail below', $healthMonitoringType);
        $this->urlComponents('Detail of Health Monitoring Type', $response, 'Health_Monitoring_Types');
        return $response;
    }

    public function update(Request $request, $id) {
        $user = \Auth::user();
        $healthMonitoringType = HealthMonitoringType::findOrFail($id);
        $request->validate(['name' => 'required|between:2,200|unique:hosMysql.health_monitoring_types,name,' . $id,
            'code' => 'min:3|max:100|unique:hosMysql.health_monitoring_types,code,' . $id, 'unit' => 'required|between:1,30']);
        $post = $request->all();
        $post['code'] = $code = (!empty($post['code'])) ? $post['code'] : HealthMonitoringType::setCode($post['name']);
        if (!empty($post['icon'])) {
            $uploader = new Uploader();
            $uploader->setFile($post['icon']);
            if ($uploader->isValidFile()===false) {
                return responseBuilder()->error($uploader->getMessage(), 400);
            }
            $path = getUserDocumentPath($user);
            $uploader->upload($path, $uploader->fileName);
            if ($uploader->isUploaded()) {
                $post['icon'] = $uploader->getUploadedPath(false);
            }else{
                return responseBuilder()->error('An error occured while file uploading '. $uploader->getMessage(), 400);
            }
        }
        $post['updated_by'] = $user->id;
        $healthMonitoringType->fill($post);
        if ($healthMonitoringType->save()) {
            $response = responseBuilder()->success('Health monitoring type updated successfully');
            $this->urlComponents('Update Health Monitoring Type', $response, 'Health_Monitoring_Types');
            return $response;
        }
        return responseBuilder()->error('Something went wrong, error while updating monitoring type');
    }

    public function destroy($id) {
        $user = \Auth::user();
        $healthMonitoringType = HealthMonitoringType::findOrFail($id);
        $date = date("D M d, Y G:i");
        $today = strtotime($date);
        $healthMonitoringType->update([
                                'name'       => $healthMonitoringType->name . '_' . $today,
                                'code'       => $healthMonitoringType->code . '_' . $today,
                                'deleted_by' => $user->id
                            ]);
        $healthMonitoringType->delete();
        $response = responseBuilder()->success('Health monitoring type deleted successfully');
        $this->urlComponents('Delete Health Monitoring Type', $response, 'Health_Monitoring_Types');
        return $response;
    }

}
