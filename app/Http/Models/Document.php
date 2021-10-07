<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Http\Libraries\Uploader;
use App\Models\DocumentMeta;

class Document extends Model {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    private $authUser = null;

    public function __construct() {
        $this->authUser = \App\User::__HUID();
    }

    protected $connection = 'hosMysql';
    protected $casts = [
        'category_medical_record_id' => 'int'
    ];
    protected $fillable = [
        'owner_id',
        'name',
        'notes',
        'file_name',
        'category_medical_record_id',
        'careservice_id',
        'price',
        'is_deleted',
        'is_public',
        'is_completed',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function category_medical_record() {
        return $this->belongsTo(\App\Models\CategoryMedicalRecord::class);
    }

    public function document_access_lists() {
        return $this->hasMany(\App\Models\DocumentAccessList::class);
    }

    public function tags() {
        return $this->belongsToMany(\App\Models\Tag::class);
    }

    public function document_meta() {
        return $this->belongsToMany(DocumentMeta::class);
    }

    public function document_category() {
        return $this->belongsTo(\App\Models\DocumentCategory::class);
    }
   
    public function medical_claims() {
        return $this->belongsToMany(\App\Models\MedicalClaim::class);
    }

    public function care_services() {
        return $this->belongsTo(\App\Models\CareService::class, 'careservice_id');
    }

    public function createOrUpdateUserDocument($post) {
        $user = $this->authUser['user'];
        $post['owner_id'] = $this->authUser['__'];
        $post['document_id'] = $post['document_id'] ?? null;
        $whereClause = ['id' => $post['document_id'],
            'owner_id' => $post['owner_id']];
        if (isset($post['medical_record_id'])) {
            $whereClause['medical_record_id'] = $post['medical_record_id'];
        }
        if (isset($post['medical_claim_id'])) {
            $whereClause['medical_claim_id'] = $post['medical_claim_id'];
        }
        if (!empty($post['medicines'])) {
            foreach ($post['medicines'] as $key => $medicine) {
                $post['medicines'][$key]['created_at'] = now();
                $post['medicines'][$key]['updated_at'] = now();
                $post['medicines'][$key]['created_by'] = $post['owner_id'];
                $post['medicines'][$key]['updated_by'] = $post['owner_id'];
            }
        }

        $document = $this->where($whereClause)->first();
        if (!empty($post['document'])) {
            $uploader = new Uploader();
            $uploader->setFile($post['document']);
            if ($uploader->isValidFile()===false) {
                return ['status' => false, 'message' => $uploader->getMessage(), 'code' => 400];
            }
                $path = getUserDocumentPath($user);
                $uploader->upload($path, $uploader->fileName);
                $post['file_name'] = $uploader->getUploadedPath(false);
        }
        $userDocumentpath = getUserDocumentPath($user, FALSE);

        if ($document) {
            $post['updated_at'] = now();
            foreach ($post as $key => $val) {
                if (in_array($key, $this->fillable)) {
                    $document->$key = $val;
                }
            }
            if (!empty($post['is_deleted']) && $post['is_deleted'] == 1) {
                $document->is_deleted = '1';
            }
            if ($document->save()) {
                if (!empty($post['is_deleted']) && $post['is_deleted'] == 1) {
                    $document->delete();
                }
                if (!empty($post['medicines'])) {
                    //Delete medicines for specific user
                    DocumentMeta::where(['created_by' => $post['owner_id'], 'document_id' => $document->id])
                                    ->update(['deleted_at' => now(), 'deleted_by' => $post['owner_id']]);
                    foreach ($post['medicines'] as $key => $medicine) {
                        $post['medicines'][$key]['document_id'] = $document->id;
                    }
                }
                DocumentMeta::insert($post['medicines']);
                return ['status' => true, 'message' => 'Data updated successfully!', 'data' => ['id' => $document->id, 'path' => $userDocumentpath . '/' . $document->file_name]];
            }
        }
        $this->fill($post);
        if ($this->save()) {
            if (!empty($post['medicines'])) {
                foreach ($post['medicines'] as $key => $medicine) {
                    $post['medicines'][$key]['document_id'] = $this->id;
                }
            }
            DocumentMeta::insert($post['medicines']);
            return ['status' => true, 'message' => 'New document added successfully!', 'data' => ['id' => $this->id, 'path' => $userDocumentpath . '/' . $post['file_name']]];
        }

        return ['status' => false, 'message' => 'Something went wrong while saving data', 'code' => 421];
    }

    public function uploadUserDocument($post) {
        $user = $this->authUser['user'];
        $userHuid = $this->authUser['__'];
        
        if(!empty($post['medical_record_id'])){
            $medicalRecordHUID = MedicalRecord::select(['huid'])->find($post['medical_record_id']);
            $userHuid = $medicalRecordHUID['huid'];
            $user = ___HQ($userHuid);
        }elseif(!empty($post['relationship_id'])){
            //$user = \App\User::select(['id', 'cnic'])->where(['id' => $post['relationship_id'], 'is_active' => '1', 'is_deleted' => '0'])->first();
            $nokUser = \App\User::__HUID($post['relationship_id']);
            $userHuid = $nokUser['__'];
            $user = $nokUser['user'];
        }
        $post['owner_id'] = $userHuid;
        $post['created_by'] = $this->authUser['__'];
        if (!empty($post['document'])) {
            $uploader = new Uploader();
            $uploader->setFile($post['document']);
            if ($uploader->isValidFile()===false) {
                return ['status' => false, 'message' => $uploader->getMessage(), 'code' => 400];
            }
            $path = getUserDocumentPath($user);
            $uploader->upload($path, $uploader->fileName);
            if ($uploader->isUploaded()) {
                $post['file_name'] = $uploader->getUploadedPath(false);
                //$uploader->compression($post['file_name'], $path);
            }else{
                return ['status' => false, 'message' => 'An error occured while file uploading '. $uploader->getMessage(), 'code' => 400];
            }
        }
        $userDocumentpath = getUserDocumentPath($user, FALSE);
        $this->fill($post);
        $document = $this->save();
        if ($document) {
            $extArr = explode('.', $post['file_name']);
                $ext = end($extArr);
                if(in_array($ext, ['jpg', 'png', 'jpeg'])){
                    $uploader->compress();
                }
            return ['status' => true, 'message' => 'New document added successfully!', 'data' => ['id' => $this->id, 'path' => $userDocumentpath . '/' . $post['file_name']]];
        }
        return ['status' => false, 'message' => 'Something went wrong while saving data', 'code' => 421];
    }

    public function uploadCareserviceDocument($post) {
        $user = $this->authUser['user'];
        $post['owner_id'] = $this->authUser['__'];
        if (!empty($post['document'])) {
            $uploader = new Uploader();
            $uploader->setFile($post['document']);
            if ($uploader->isValidFile()===false) {
                return ['status' => false, 'message' => $uploader->getMessage(), 'code' => 400];
            }
                $path = getCareServiceTypeDocumentPath();
                //$path = getCareServiceDocumentPath();
                $uploader->upload($path, $uploader->fileName);
                if ($uploader->isUploaded()) {
                    $post['file_name'] = $uploader->getUploadedPath(false);
                }else{
                    return ['status' => false, 'message' => 'An error occured while file uploading '. $uploader->getMessage(), 'code' => 400];
                }
        }
        //$care_service_type_url = getCareServiceTypeDocumentPath(FALSE);
        $care_service_type_url = getCareServiceDocumentPath(FALSE);

        $post['created_by'] = $user->id;
        $this->fill($post);
        if ($this->save()) {
            return ['status' => true, 'message' => 'New Careservice document added successfully!', 'data' => ['id' => $this->id, 'path' => $care_service_type_url . '/' . $post['file_name']]];
        }

        return ['status' => false, 'message' => 'Something went wrong while saving data', 'code' => 421];
    }
}
