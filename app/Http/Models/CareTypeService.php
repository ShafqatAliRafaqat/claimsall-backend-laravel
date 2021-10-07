<?php

namespace App\Models;

use App\Http\Libraries\Uploader;
use Illuminate\Database\Eloquent\Model as Eloquent;

class CareTypeService extends Eloquent
{
	use \Illuminate\Database\Eloquent\SoftDeletes;
	protected $table = 'care_type_services';

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'name',
		'is_active',
		'description',
		'file_name',
		'created_by',
		'updated_by',
		'deleted_by',
		'lookup_url',
	];

	public function care_services()
	{
		return $this->hasMany(\App\Models\CareService::class);
	}

	public function addCareTypeService($request)
	{
		$user = \Auth::user();
		$post = $request->all();
		if (!empty($post['document'])) {
            $uploader = new Uploader();
            $uploader->setFile($post['document']);
            if ($uploader->isValidFile() === false) {
                 return ['status' => false, 'message' => $uploader->getMessage(), 'code' => 400];
            }
                $path = getCareServiceTypeDocumentPath();
                $uploader->upload($path, $uploader->fileName);
                if ($uploader->isUploaded()) {
                    $post['file_name'] = $uploader->getUploadedPath(false);
                }else{
                    return ['status' => false, 'message' => 'An error occured while file uploading '. $uploader->getMessage(), 'code' => 400];
                }
        }
        $care_service_type_url = getCareServiceTypeDocumentPath(FALSE);
		$post['created_by'] = $user->id;
		$this->fill($post);
        if ($this->save()) {
            return ['status' => true, 'message' => 'New CareTypeService added successfully!', 'data' => ['id' => $this->id, 'path' => $care_service_type_url . '/' . $post['file_name']]];
        }
        return ['status' => false, 'message' => 'Something went wrong while saving data', 'code' => 421];
    }

    public function updateCareTypeServiceById($request, $id)
    {
		$user = \Auth::user();
		$post = $request->all();
        $post['updated_by'] = $user->id;
        if (!empty($post['document'])) {
            $uploader = new Uploader();
            $uploader->setFile($post['document']);
            if ($uploader->isValidFile()=== false) {
                 return ['status' => false, 'message' => $uploader->getMessage(), 'code' => 400];
            }
                $path = getCareServiceTypeDocumentPath();
                $uploader->upload($path, $uploader->fileName);
                if ($uploader->isUploaded()) {
                    $post['file_name'] = $uploader->getUploadedPath(false);
                }else{
                    return ['status' => false, 'message' => 'An error occured while file uploading '. $uploader->getMessage(), 'code' => 400];
                }
        }
        $care_service_type_url = getCareServiceTypeDocumentPath(FALSE);
        $CareService = $this->where(['is_deleted'=>'0', 'id' =>$id])->first();
        if (!$CareService)
        {
            return ['status' => false, 'code' => 400, 'message' => 'CareTypeService you want to edit not found'];
        }
        if ($CareService->update($post)) {
            return ['status' => true, 'message' => 'CareTypeService updated successfully!', 'data' => ['id' => $this->id, 'path' => $care_service_type_url . '/' . $post['file_name']]];
        }
        return ['status' => false, 'message' => 'Something went wrong while saving data', 'code' => 421];
    }

    public function getCareTypeServices()
    {
		$user = \Auth::user();
        $records = $this->select(['id', 'file_name', 'name', 'description', 'lookup_url'])->where(['is_deleted'=>'0'])->get();
        foreach ($records as $key => $record) {
        	$care_service_type_url = getCareServiceTypeDocumentPath(FALSE);
        	if (!empty($record->file_name)) {
        		$record['url'] = $care_service_type_url.'/'.$record->file_name;
        	}
        }
        if(count($records)<=0) {
            return ['status' => true, 'message' => 'You haven\'t added any Care Service yet' ];
        }
        return ['status' => true, 'message' => 'Got CareTypeServices', 'data' => $records];
    }

    public function deleteByUserAndId($id)
    {
		$user = \Auth::user();
        if (!strcasecmp($user->role_user->role->title, 'super admin')) {
	        $CareTypeService = $this->where(['is_deleted' => '0', 'id' => $id])->first();
	        if(!$CareTypeService) {
	            return ['status' => false, 'message' => 'Sorry we did not find your record'];
	        }
	        $CareTypeService->is_deleted = '1';
	        $CareTypeService->deleted_by = $user->id;
	        $CareTypeService->save();
	        $CareTypeService->delete();
	        return ['status'=> true, 'message' => 'Deleted successfully!'];
	    }
	    else {
			return ['status' => false, 'code' => 401, 'message' => 'This user don\'t have permission to perform this activity'];
		}
    }
}
