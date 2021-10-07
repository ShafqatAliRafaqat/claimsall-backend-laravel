<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use File;
use App\Http\Libraries\Uploader;
use DB;
use Illuminate\Support\Facades\Input;
use App\Models\CategoryMedicalRecord;
use App\User;

class MedicalRecord extends Model {

    use \Illuminate\Database\Eloquent\SoftDeletes,        \App\Traits\FCM;

    private $authUser = null;

    public function __construct() {
        $this->authUser = \App\User::__HUID();
    }

    protected $connection = 'hosMysql';
    protected $casts = [
        'relationship_id' => 'int',
        'huid' => 'string',
        'created_date' => 'datetime:Y-m-d H:00',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string'
    ];
    protected $fillable = [
        'name',
        'huid',
        'description',
        'category',
        'is_personal',
        'is_public',
        'relationship_id',
        'created_date',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_deleted'
    ];

    public function relationship() {
        return $this->belongsTo(\App\Models\Relationship::class);
    }

    public function user() {
        return $this->belongsTo(\App\User::class);
    }

    public function orders() {
        return $this->hasMany(\App\Models\Order::class);
    }

    public function category_medical_records() {
        return $this->hasMany(\App\Models\CategoryMedicalRecord::class);
    }

    public function addMedicalRecordsNew($request) {
        $user = $this->authUser['user'];
        $path = getUserDocumentPath($user);
        $post = $request->all();
        $post['huid'] = $post['created_by'] = $this->authUser['__'];
        $documents = $post['documents'];
        $this->fill($post);
        $medicalRecordStatus = $this->save();
        if (count($documents) > 0 && $medicalRecordStatus) {
            foreach ($documents as $doc) {
                $document = Document::where(['owner_id' => $this->authUser['__'], 'id' => $doc, 'is_deleted' => '0'])->first();
                $document->medical_record_id = $this->id;
                $document->is_completed = '1';
                $document->save();
            }

            return ['status' => true, 'message' => 'Medical record is added successfully!'];
        }

        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function updateMedicalRecordsById($request, $id) {
        $user = $this->authUser['user'];
        $path = getUserDocumentPath($user);
        $attachDocuments = [];
        $images = $request->file('documents');
        $post = $request->all();
        $medicalRecord = $this->where(['is_deleted' => '0', 'huid' => $this->authUser['__'], 'id' => $id])->first();
        if (!$medicalRecord) {
            return ['status' => false, 'code' => 400, 'message' => 'The document you want to edit is not found'];
        }
        if ($medicalRecord->update($post)) {
            return ['status' => true, 'message' => 'Medical record update successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function getMedicalRecords() {
        $params = Input::get();
        $medicalRecordUserHUID = $loggedUserId = $this->authUser['__'];
        $user = $this->authUser['user'];
        $userId = $user->id;
        //$path = getUserDocumentPath($user, FALSE);
        //        var_dump($params);
        if(!empty($params['relationship_id'])){
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $params['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $params['relationship_id']) ? 'read_medical_record': 'assc_read_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'This you don\'t have permission to view medical records for this user', 'code' => 403];
            }
            $nokUser = \App\User::__HUID($params['relationship_id']);
            $medicalRecordUserHUID= $nokUser['__'];
            $userId = $params['relationship_id'];
            //            dump($userId);
            //            dump($medicalRecordUserHUID);
            //            dump(___HQ($medicalRecordUserHUID));
            //            die;
            //$path = getUserDocumentPath($nokUser['user'], FALSE);
        }
        //        dump(___HQ('31333233418c72c36-37633264646464312d366165302d313165382d393762332d353235343030353164353664-1528437341'));//38
        //        //dump(___HQ('313736e5f4c8f3ca-32623836643835352d373531352d313165382d393762332d353235343030353164353664-1529559481'));//38
        //                dump(___HQ('333811f71fb04cb-30666138393532352d356630632d313165382d623463302d353235343030376164393835-1525438006'));//38
        //                dump($user->id);
        //                $loggedUserHUID = $this->authUser['__'];
        //                dump($loggedUserHUID);
        //                dump(___HQ($loggedUserHUID));
        //                die;
//        \DB::connection($this->connection)->enableQueryLog();
        
        //User ids + viral profile's user ids
        $currentUserProfilesWithOwners = FamilyTree::getViralProfileIds($userId);
        //        echo '---> viralProfile+owners';
        //        dump($currentUserProfilesWithOwners);
        $currentUserProfiles = FamilyTree::getViralProfileIds($userId, FALSE);
        //        echo '---> viralProfile-exclusive owners';
        //        dump($currentUserProfiles);
        
        $viralProfilesFNFIds = array_diff($currentUserProfilesWithOwners['user_ids'], $currentUserProfiles['user_ids']);
        
        //        echo 'inter===';
        //        dump($viralProfilesFNFIds);
        $currentUserProfileIds = $currentUserProfiles['user_ids'];
        $currentUserProfileHuids = $currentUserProfiles['huids'];
        if(empty($viralProfilesFNFIds)){
            $viralProfilesFNFIds = [];
        }
        $fnfIds = FamilyTree::getFNFuserIdsByUserId($userId, true, ['read_medical_record', 'write_medical_record']);
        $fnfUserIds = array_merge($fnfIds['user_ids'], $viralProfilesFNFIds);
        $fnfUserHuids = array_merge($fnfIds['huids'], $viralProfilesFNFIds);

        //        echo '===ALL MERGED FNF id';
        //        dump($fnfUserIds);
        $fnfUserIds = array_diff($fnfUserIds, [$userId]);
        //        echo '<h3>=====Pure FNF ids for user="'.$userId.'" LoggedIn=|'.$this->authUser['user']->id.'"</h3>';
        //        dump($fnfUserIds);
        $recordQuery= $this->select(['id','huid', 'name', 'description', 'is_personal', 'relationship_id',  'is_public', 'is_deleted', 'created_at', 'created_date'])
                ->where(['is_deleted' => '0'])
                ->whereIn('huid', $currentUserProfileHuids)
                //->whereNull('relationship_id')
                ->whereNull('deleted_at');
                if(!empty($params['relationship_id'])){
                    #fetch record for relational_user's fnf
                    #relational_user self added (Personal's) medical record
                     $recordQuery->where(['is_personal' => 'Y', 'is_public' => 'Y', 'is_deleted' => '0']);
                    $recordQuery->orWhere(function($relationshipQuery) use($fnfUserIds, $currentUserProfileHuids, $fnfUserHuids) {
                        //$relationshipQuery->whereIn('relationship_id', $fnfUserIds);
                        $relationshipQuery->whereIn('huid', $currentUserProfileHuids);
                        $relationshipQuery->where(['is_personal'=> 'N',  'is_public'=>'Y', 'is_deleted' => '0']);
                        //                        $relationshipQuery->whereNull('relationship_id');
                        $relationshipQuery->whereNull('deleted_at');
//                        if(!empty($fnfUserHuids)){
//                            $relationshipQuery->whereIn('huid', $fnfUserHuids);
//                            $relationshipQuery->where(['is_public'=>'Y', 'is_deleted' => '0']);
//                            $relationshipQuery->whereNull('relationship_id');
//                            $relationshipQuery->whereNull('deleted_at');
//                        }                        
                    });    
                }else{
                    //$recordQuery->whereNull('relationship_id');
                    //                    echo '---> fnf userids:  '; dump($fnfUserIds);
                    if(!empty($fnfUserIds)){ // fnf ids
                        $recordQuery->orWhere(function($fnfQueryMr) use($currentUserProfileHuids, $fnfUserIds){
                            $fnfQueryMr->whereIn('huid', $currentUserProfileHuids)
                            //->whereIn('relationship_id', $fnfUserIds)
                            ->where(['is_personal' => 'N', 'is_public' => 'Y', 'is_deleted' => '0'])
                            ->whereNull('deleted_at');
                        });
                    }
                }
               $records= $recordQuery->with([
                    'category_medical_records' => function($categoryMedicalRecordsQuery) {
                        //$categoryMedicalRecordsQuery->select('id');
                        $documentsWhere = function($documentQuery) {
                                    $documentQuery->select(['id', 'category_medical_record_id', 'notes', 'file_name']);
                                    $documentQuery->where('is_deleted', '0');
                                };
                        $categoryMetaWhere = function($categoryQuery) {
                                    $categoryQuery->select(['id', 'category_medical_record_id', 'medicine_name', 'medicine_type', 'quantity', 'created_at']);
                                };
                        $documentCategoriesWhere = function ($documentCategoryQuery) {
                                    $documentCategoryQuery->select(['id', 'name', 'code']);
                                };
                        $categoryMedicalRecordsQuery->with([
                            'documents' => $documentsWhere,
                            'document_category_meta' => $categoryMetaWhere,
                            'document_category' => $documentCategoriesWhere
                        ]);
                    }
                ])
                ->orderBy('created_at', 'DESC')
                ->paginate(10);
                
        $recordsDataArr = $records->toArray();
//                $queries = \DB::connection($this->connection)->getQueryLog();	
//                dump($queries);
//                        dump($recordsDataArr);
//                        die;
        if (!isset($recordsDataArr['data']) || count($recordsDataArr['data']) <= 0) {
            return ['status' => true, 'message' => 'This You haven\'t add any medical records yet'];
        }
        $recordsData = $recordsDataArr['data'];
        foreach ($recordsData as $record) {
            if(!empty($params['relationship_id'])){
                $record['relationship_id'] =$params['relationship_id'];
            }
            $decHuid = ___HQ($record['huid']);
            $path = getUserDocumentPath($decHuid, FALSE);
            $absPath = getUserDocumentPath($decHuid);
            $categoryMedicalRecordData = $record['category_medical_records'];
            unset($record['category_medical_records']);
            foreach ($categoryMedicalRecordData as $key => $categoryMedicalRecord) {
                if (isset($categoryMedicalRecord['documents']) && count($categoryMedicalRecord['documents']) > 0) {
                    foreach ($categoryMedicalRecord['documents'] as $docIndex => $doc) {
                        $absFullPath = $absPath .'/'. $doc['file_name'];
                        if(!file_exists($absFullPath)){
                            unset($categoryMedicalRecord['documents'][$docIndex]);
                            continue;
                        }
                            $tempExt = explode('.', $doc['file_name']);
                            $categoryMedicalRecord['documents'][$docIndex]['extension'] = '.'. end($tempExt);
                            $categoryMedicalRecord['documents'][$docIndex]['path'] = $path . '/' . $doc['file_name'];
                            $categoryMedicalRecord['documents'][$docIndex]['mime_type'] = \File::mimeType($absFullPath);
                            $categoryMedicalRecord['documents'][$docIndex]['document_id'] = $doc['id'];
                            unset($categoryMedicalRecord['documents'][$docIndex]['id'], $categoryMedicalRecord['documents'][$docIndex]['category_medical_record_id'], $categoryMedicalRecord['documents'][$docIndex]['file_name']);
                    }
                    if(count($categoryMedicalRecord['documents']) > 0)
                        $record[$categoryMedicalRecord['document_category']['code']]['documents'] = $categoryMedicalRecord['documents'];
                }
                if (!empty($categoryMedicalRecord['document_category_meta'])) {
                    foreach ($categoryMedicalRecord['document_category_meta'] as $indx => $medicine) {
                        $categoryMedicalRecord['document_category_meta'][$indx]['medicine_id'] = $medicine['id'];
                        unset($categoryMedicalRecord['document_category_meta'][$indx]['id'], $categoryMedicalRecord['document_category_meta'][$indx]['category_medical_record_id'], $categoryMedicalRecord['document_category_meta'][$indx]['created_at']);
                    }
                    $record[$categoryMedicalRecord['document_category']['code']]['medicines'] = $categoryMedicalRecord['document_category_meta'];
                }
            }
            $data[] = $record;
        }
        $recordsDataArr['data'] = $data;
        return ['status' => true, 'message' => 'Got medical records', 'data' => $recordsDataArr];
    }
    
    public function getMyMedicalRecords() {
        $params = Input::get();
        $medicalRecordUserID = $loggedUserId = $this->authUser['__'];
        $user = $this->authUser['user'];
        $userId = $user->id;
        if(!empty($params['relationship_id'])){
            //            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $params['relationship_id'], 'read_medical_record' => 'Y']);
            //            if(!$getUser){
            //                return ['status' => false, 'message' => 'This you don\'t have permission to view medical records for this user', 'code' => 403];
            //            }
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $params['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $params['relationship_id']) ? 'read_medical_record': 'read_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to view medical records for this user', 'code' => 403];
            }
            $nokUser = \App\User::__HUID($params['relationship_id']);
            $medicalRecordUserID= $nokUser['__'];
            $userId = $params['relationship_id'];
        }
        //        dump(___HQ('31333233418c72c36-37633264646464312d366165302d313165382d393762332d353235343030353164353664-1528437341'));//38
        //        //dump(___HQ('313736e5f4c8f3ca-32623836643835352d373531352d313165382d393762332d353235343030353164353664-1529559481'));//38
                dump(___HQ('3133308159b108e38-64303433613064662d366135352d313165382d393762332d353235343030353164353664-1528377782'));//38
                dump($user->id);
                $loggedUserHUID = $this->authUser['__'];
                dump($loggedUserHUID);
                echo '---->';
                dump($medicalRecordUserID);
                
        \DB::connection($this->connection)->enableQueryLog();
        $path = getUserDocumentPath($user, FALSE);
        $fnfIds = FamilyTree::getFNFuserIdsByUserId($userId, true, ['read_medical_record', 'write_medical_record']);
        dump($fnfIds);
        $orginalFnfUsers = $fnfIds['huids'];
        $fnfIds['huids'][] =$loggedUserId;
        if(!empty($params['relationship_id'])){
            $fnfIds['huids'][] =$medicalRecordUserID;
        }
        $allHuids = array_unique($fnfIds['huids']);
        $recordQuery= $this->select(['id','huid', 'name', 'description', 'is_personal', 'relationship_id',  'is_public', 'is_deleted', 'created_at', 'created_date'])
                ->where(['is_deleted' => '0'])
                ->whereIn('huid', $allHuids);
                //                ->where(['relationship_id' => $user->id, 'is_public' => 'Y']);//fetch my data who added by others
                if(!empty($nokUser)){
                    $recordQuery->where('is_public', 'Y');
                    $recordQuery->whereNull('deleted_at');
                }
                if(empty($params['relationship_id'])){
                //                    $recordQuery->orWhere('relationship_id', $user->id);//fetch my data who added by others
                    $recordQuery->where(function($recordQueryS) use($user, $loggedUserId){
                        $recordQueryS->where('is_personal', 'N');
                        $recordQueryS->where('relationship_id', $user->id);
                        $recordQueryS->orWhere(function($recordQueryT) use($loggedUserId){
                           $recordQueryT->whereNull('relationship_id');
                           $recordQueryT->where('is_personal', 'Y');
                           $recordQueryT->where('huid', $loggedUserId);
                        });
                    });//fetch my data who added by others
                }else{
                    #fetch record for relational_user's fnf
                    $onlyFnfUser = array_diff($allHuids, [$medicalRecordUserID]);
                    $recordQuery->whereNull('deleted_at');
                    $recordQuery->where(['is_personal' => 'N', 'relationship_id' => $params['relationship_id'], 'is_deleted' => '0']);
                    #relational_user self added (Personal's) medical record
                    $recordQuery->orWhere(function($relationshipQuery) use($onlyFnfUser, $medicalRecordUserID) {
                        $relationshipQuery->where(['huid'=> $medicalRecordUserID, 'is_public' => 'Y']);
                        $relationshipQuery->whereNotIn('huid', $onlyFnfUser);
                        $relationshipQuery->where(['is_personal'=> 'Y', 'is_deleted' => '0']);
                        $relationshipQuery->whereNull('relationship_id');
                        $relationshipQuery->whereNull('deleted_at');
                    });    
                }
               $records= $recordQuery->with([
                    'category_medical_records' => function($categoryMedicalRecordsQuery) {
                        //$categoryMedicalRecordsQuery->select('id');
                        $documentsWhere = function($documentQuery) {
                                    $documentQuery->select(['id', 'category_medical_record_id', 'notes', 'file_name']);
                                    $documentQuery->where('is_deleted', '0');
                                };
                        $categoryMetaWhere = function($categoryQuery) {
                                    $categoryQuery->select(['id', 'category_medical_record_id', 'medicine_name', 'medicine_type', 'quantity', 'created_at']);
                                };
                        $documentCategoriesWhere = function ($documentCategoryQuery) {
                                    $documentCategoryQuery->select(['id', 'name', 'code']);
                                };
                        $categoryMedicalRecordsQuery->with([
                            'documents' => $documentsWhere,
                            'document_category_meta' => $categoryMetaWhere,
                            'document_category' => $documentCategoriesWhere
                        ]);
                                /*->whereHas('documents', function($documentsQuery) {
                                    
                                });*/
                    }
                ])
                    /*->whereHas('category_medical_records', function($categoryMedicalRecordsQuery){
                        $categoryMedicalRecordsQuery->whereHas('documents', function($documentsQuery) {      
                                });
                    })*/
                ->orderBy('created_at', 'DESC')
                ->paginate(10);
                
        $recordsDataArr = $records->toArray();
        $queries = \DB::connection($this->connection)->getQueryLog();	
        dump($queries);
        //die;
                dump($recordsDataArr);
                die;
        if (!isset($recordsDataArr['data']) || count($recordsDataArr['data']) <= 0) {
            return ['status' => true, 'message' => 'You haven\'t added any medical records yet'];
        }
        $recordsData = $recordsDataArr['data'];
        foreach ($recordsData as $record) {
            if(!empty($params['relationship_id'])){
                $record['relationship_id'] =$params['relationship_id'];
            }
            //$timeUx = strtotime($record['created_at']);
            //$record['created_at'] = date('d-m-Y H:i A', $timeUx);
            if(!empty($record['relationship_id'])){
                $decHuid = \App\User::select(['cnic'])->where(['id' => $record['relationship_id']])->first();
            }else{
                $decHuid = ___HQ($record['huid']);
            }
            $path = getUserDocumentPath($decHuid, FALSE);
            $categoryMedicalRecordData = $record['category_medical_records'];
            unset($record['category_medical_records']);
            foreach ($categoryMedicalRecordData as $key => $categoryMedicalRecord) {
                if (isset($categoryMedicalRecord['documents']) && count($categoryMedicalRecord['documents']) > 0) {
                    foreach ($categoryMedicalRecord['documents'] as $docIndex => $doc) {
                        $categoryMedicalRecord['documents'][$docIndex]['path'] = $path . '/' . $doc['file_name'];
                        $categoryMedicalRecord['documents'][$docIndex]['document_id'] = $doc['id'];
                        unset($categoryMedicalRecord['documents'][$docIndex]['id'], $categoryMedicalRecord['documents'][$docIndex]['category_medical_record_id'], $categoryMedicalRecord['documents'][$docIndex]['file_name']);
                    }
                    $record[$categoryMedicalRecord['document_category']['code']]['documents'] = $categoryMedicalRecord['documents'];
                }
                if (!empty($categoryMedicalRecord['document_category_meta'])) {
                    foreach ($categoryMedicalRecord['document_category_meta'] as $indx => $medicine) {
                        $categoryMedicalRecord['document_category_meta'][$indx]['medicine_id'] = $medicine['id'];
                        unset($categoryMedicalRecord['document_category_meta'][$indx]['id'], $categoryMedicalRecord['document_category_meta'][$indx]['category_medical_record_id'], $categoryMedicalRecord['document_category_meta'][$indx]['created_at']);
                    }
                    $record[$categoryMedicalRecord['document_category']['code']]['medicines'] = $categoryMedicalRecord['document_category_meta'];
                }
            }
            $data[] = $record;
        }
        $recordsDataArr['data'] = $data;
        return ['status' => true, 'message' => 'Got medical records', 'data' => $recordsDataArr];
    }

    public function getMyMedicalRecordDetails($id) {
        $params = Input::get();
        $medicalRecordUserID = $this->authUser['__'];
        $user = $this->authUser['user'];
        if(!empty($params['relationship_id'])){
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $params['relationship_id'], 'read_medical_record' => 'Y']);
            if(!$getUser){
                return ['status' => false, 'message' => 'You don\'t have permission to view medical records for this user', 'code' => 403];
            }
            $nokUser = \App\User::__HUID($params['relationship_id']);
            $medicalRecordUserID= $nokUser['__'];
        }
        $path = getUserDocumentPath($user, FALSE);
        $record = $this->select(['id', 'name', 'description', 'is_public', 'created_at', 'created_date'])
                ->where(['is_deleted' => '0', 'huid' => $this->authUser['__'], 'id' => $id])
                ->with([
                    'category_medical_records' => function($categoryMedicalRecordsQuery) {
                        //$categoryMedicalRecordsQuery->select('id');
                        $documentsWhere = function($documentQuery) {
                                    $documentQuery->select(['id', 'category_medical_record_id', 'notes', 'file_name']);
                                    $documentQuery->where('is_deleted', '0');
                                };
                        $categoryMetaWhere = function($categoryQuery) {
                                    $categoryQuery->select(['id', 'category_medical_record_id', 'medicine_name', 'medicine_type', 'quantity', 'created_at']);
                                };
                        $documentCategoriesWhere = function ($documentCategoryQuery) {
                                    $documentCategoryQuery->select(['id', 'name', 'code']);
                                };
                        $categoryMedicalRecordsQuery->with([
                            'documents' => $documentsWhere,
                            'document_category_meta' => $categoryMetaWhere,
                            'document_category' => $documentCategoriesWhere
                        ]);
                    }
                ])
                ->first();
        if (!$record) {
            return ['status' => false, 'message' => 'Sorry we did not find your record'];
        }

        $recordArr = $record->toArray();
        if (isset($recordArr['category_medical_records'])) {
            $categoryMedicalRecordData = $recordArr['category_medical_records'];
            unset($recordArr['category_medical_records']);
            foreach ($categoryMedicalRecordData as $key => $categoryMedicalRecord) {
                if (isset($categoryMedicalRecord['documents']) && count($categoryMedicalRecord['documents']) > 0) {
                    foreach ($categoryMedicalRecord['documents'] as $docIndex => $doc) {
                        $categoryMedicalRecord['documents'][$docIndex]['path'] = $path . '/' . $doc['file_name'];
                        $categoryMedicalRecord['documents'][$docIndex]['document_id'] = $doc['id'];
                        unset($categoryMedicalRecord['documents'][$docIndex]['id'], $categoryMedicalRecord['documents'][$docIndex]['category_medical_record_id'], $categoryMedicalRecord['documents'][$docIndex]['file_name']);
                    }
                    $recordArr[$categoryMedicalRecord['document_category']['code']]['documents'] = $categoryMedicalRecord['documents'];
                }
                if (!empty($categoryMedicalRecord['document_category_meta'])) {
                    foreach ($categoryMedicalRecord['document_category_meta'] as $indx => $medicine) {
                        $categoryMedicalRecord['document_category_meta'][$indx]['medicine_id'] = $medicine['id'];
                        unset($categoryMedicalRecord['document_category_meta'][$indx]['id'], $categoryMedicalRecord['document_category_meta'][$indx]['category_medical_record_id'], $categoryMedicalRecord['document_category_meta'][$indx]['created_at']);
                    }
                    $recordArr[$categoryMedicalRecord['document_category']['code']]['medicines'] = $categoryMedicalRecord['document_category_meta'];
                }
            }
        }
        return ['status' => true, 'message' => 'Got medical records', 'data' => $recordArr];
    }

    public function deleteMedicalRecord($id) {
        $user = $this->authUser['user'];
        $medicalRecord = $this->where(['is_deleted' => '0'])->findOrFail($id);
        $medicalRecordUserHUId = $medicalRecord->huid;
        $recordUserHuid = $this->authUser['__'];
        if($medicalRecordUserHUId != $this->authUser['__']){
            $medicalRecordUser = ___HQ($medicalRecordUserHUId);
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $medicalRecordUser['id'], 'write_medical_record' => 'Y']);
            if(empty($getUser)){
                $medicalRecordHuidData = ___HQ($medicalRecord->huid);
                $recordUser = User::find($medicalRecordHuidData['id']);
                $getUser = FamilyTree::checkAssociateUserRights($recordUser, ['associate_user_id' => $medicalRecord['relationship_id']]);
            }
            if(!$getUser){
                return ['status' => false, 'message' => 'You don\'t have permission to delete medical record for this user', 'code' => 403];
            }
            $recordUserHuid = $medicalRecordUserHUId;
            if($medicalRecord['is_public'] == 'N'){
                return ['status' => false, 'message' => 'Sorry,This Medical Record is no more shared', 'code' => 403];
            }
        }
        $decHuid = ___HQ($medicalRecordUserHUId);
        $currentUserProfiles = FamilyTree::getViralProfileIds($decHuid['id']);
        $currentUserProfileIds = $currentUserProfiles['user_ids'];
        $currentUserProfileHuids = $currentUserProfiles['huids'];
        $medicalRecord = $this->where(['is_deleted' => '0', 'id' => $id])->whereIn('huid', $currentUserProfileHuids)->first();
        if (!$medicalRecord) {
            return ['status' => false, 'message' => 'Sorry we did not find your record'];
        }
        $medicalRecord->deleted_by = $recordUserHuid;
        $medicalRecord->is_deleted = '1';
        $medicalRecord->save();
        $medicalRecord->delete();
        $post = $medicalRecord->toArray();
        $this->sendPushMR($user, $post, 'deleted your medical record');
        return ['status' => true, 'message' => 'Deleted successfully!'];
    }
    
    public function deleteByUserAndId($id) {
        $user = $this->authUser['user'];
        $medicalRecord = $this->where(['is_deleted' => '0'])->findOrFail($id);
        $medicalRecordUserHUId = $medicalRecord->huid;
        $recordUserHuid = $this->authUser['__'];
        if($medicalRecordUserHUId != $this->authUser['__']){
            $medicalRecordUser = ___HQ($medicalRecordUserHUId);
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $medicalRecordUser['id'], 'write_medical_record' => 'Y']);
            if(!$getUser){
                return ['status' => false, 'message' => 'You don\'t have permission to delete medical record for this user', 'code' => 403];
            }
            $recordUserHuid = $medicalRecordUserHUId;
        }
        
        $medicalRecord = $this->where(['is_deleted' => '0', 'huid' => $recordUserHuid, 'id' => $id])->first();
        if (!$medicalRecord) {
            return ['status' => false, 'message' => 'Sorry we did not find your record'];
        }
        $medicalRecord->is_deleted = '1';
        $medicalRecord->save();
        $medicalRecord->delete();
        $post = $medicalRecord->toArray();
        $this->sendPushMR($user, $post, 'deleted your medical record');
        return ['status' => true, 'message' => 'Deleted successfully!'];
    }

    public function addMedicalRecords($request) {
        $user = $this->authUser['user'];
        $post = $request->all();
        $Errors = ['status' => false, 'code' => 422, 'message' => 'An error occured while saving medical record'];
        $documentCategories = DocumentCategory::select('id', 'code')->get()->toArray();
        $documentCategories = array_column($documentCategories, 'id', 'code');
        if (!empty($post['created_date'])) {
            $post['created_date'] = strtotime($post['created_date']);
            $post['created_date'] = date('Y-m-d H:i:s', $post['created_date']);
        } else {
            $post['created_date'] = now();
        }
        $medicalRecordUserHuid = $post['created_by'] = $post['updated_by']=  $this->authUser['__'];
        $medicalRecordUserId = $user->id;
        if(!empty($post['relationship_id'])){
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $post['relationship_id']) ? 'write_medical_record': 'assc_write_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to update medical records for this user', 'code' => 403];
            }
            
            $nokUser = User::__HUID($post['relationship_id']);
            $medicalRecordUserHuid= $nokUser['__'];
            $medicalRecordUserId = $post['relationship_id'];
            $post['relationship_id'] = $user->id;
        }
        $post['huid']  = $medicalRecordUserHuid;
        DB::beginTransaction();
        $this->fill($post);
        $medicalRecordStatus = $this->save();
        if (!$medicalRecordStatus) {
            goto end;
        }
        $medicalRecordId = $this->id;
        foreach ($post['categories'] as $cat => $catData) {
            if (empty($catData['documents']) && empty($catData['medicines'])) {
                continue;
            }
            $cat_id = $documentCategories[$cat];
            //save in Piviot table
            $categoryMedicalRecord = CategoryMedicalRecord::create(['medical_record_id' => $medicalRecordId, 'document_category_id' => $cat_id]);
            if (!$categoryMedicalRecord) {
                $Errors = ['status' => false, 'message' => 'Something went wrong, Unable to proccess request for error cause 101'];
                goto end;
            }
            if (!empty($catData['documents'])) {
                //Update Documents regarding medical_records_category_id
                foreach ($catData['documents'] as $key => $document) {
                    $Document = Document::where(['is_deleted' => '0', 'id' => $document['document_id'], 'owner_id' => $medicalRecordUserHuid])->first();
                    if (!$Document) {
                        continue;
                    }
                    $document['is_public'] = (!empty($post['is_public'])) ? $post['is_public'] : 'Y';
                    $document['is_completed'] = 1;
                    $document['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $document['updated_by'] = $post['huid'];
                    $deleteDocument = $document['is_deleted'] ?? 'N';
                    $document['is_deleted'] = 0;
                    if ($deleteDocument === 'Y') {
                        $Document->delete();
                        $document['deleted_by'] = $post['huid'];
                        $document['is_deleted'] = 1;
                    }
                    $Document->fill($document);
                    $Document->save();
                }
            }

            // Save Medicines  Meta
            if (isset($catData['medicines'])) {
                foreach ($catData['medicines'] as $medicine) {
                    $medicine['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $medicine['created_by'] = $medicine['updated_by'] = $post['huid'];
                    DocumentCategoryMeta::create($medicine);
                }
            }
        }
        DB::commit();
        
        $this->sendPushMR($user, $post, 'added your medical record');
        return ['status' => true, 'message' => 'Medical record saved successfully!'];

        end:
        DB::rollBack();
        return $Errors;
    }

    public function addMedicalRecordData($request) {
        $user = $this->authUser['user'];
        $post = $request->all();
        
        $post['huid'] = $post['created_by'] = $post['updated_by'] = $this->authUser['__'];
        $Errors = ['status' => false, 'code' => 422, 'message' => 'An error occured while saving medical record'];
        $documentCategories = DocumentCategory::select('id', 'code')->get()->toArray();
        $documentCategories = array_column($documentCategories, 'id', 'code');
        if (!empty($post['created_date'])) {
            $post['created_date'] = strtotime($post['created_date']);
            $post['created_date'] = date('Y-m-d H:i:s', $post['created_date']);
        } else {
            $post['created_date'] = now();
        }
        //$post['created_date'] = $post['created_date'] ?? now();
        if(!empty($post['relationship_id'])){
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $post['relationship_id']) ? 'write_medical_record': 'assc_write_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to update medical records for this user', 'code' => 403];
            }
        }
        DB::beginTransaction();
        $this->fill($post);
        $medicalRecordStatus = $this->save();
        if (!$medicalRecordStatus) {
            goto end;
        }
        $medicalRecordId = $this->id;
        foreach ($post['categories'] as $cat => $catData) {
            if (empty($catData['documents']) && empty($catData['medicines'])) {
                continue;
                //$Errors = ['status' => false, 'message' => 'You have\'nt attached any document for category: '.$cat];
                //goto end;
            }
            $cat_id = $documentCategories[$cat];
            //save in Piviot table
            $categoryMedicalRecord = CategoryMedicalRecord::create(['medical_record_id' => $medicalRecordId, 'document_category_id' => $cat_id]);
            if (!$categoryMedicalRecord) {
                $Errors = ['status' => false, 'message' => 'Something went wrong, Unable to proccess request for error cause 101'];
                goto end;
            }
            if (!empty($catData['documents'])) {
                //Update Documents regarding medical_records_category_id
                foreach ($catData['documents'] as $key => $document) {
                    $Document = Document::where(['is_deleted' => '0', 'id' => $document['document_id'], 'owner_id' => $this->authUser['__']])->first();
                    if (!$Document) {
                        continue;
                        //                    $Errors = ['status' => false, 'message' => "Document id:{$document['document_id']} not exist or invalid"];
                        //                    goto end;
                    }
                    $document['is_public'] = (!empty($post['is_public'])) ? $post['is_public'] : 'N';
                    $document['is_completed'] = 1;
                    $document['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $document['created_by'] = $document['updated_by'] = $post['huid'];
                    $deleteDocument = $document['is_deleted'] ?? 'N';
                    $document['is_deleted'] = 0;
                    if ($deleteDocument === 'Y') {
                        $Document->delete();
                        $document['deleted_by'] = $post['huid'];
                        $document['is_deleted'] = 1;
                    }
                    $Document->fill($document);
                    $Document->save();
                }
            }

            // Save Medicines  Meta
            if (isset($catData['medicines'])) {
                foreach ($catData['medicines'] as $medicine) {
                    $medicine['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $medicine['created_by'] = $medicine['updated_by'] = $post['huid'];
                    DocumentCategoryMeta::create($medicine);
                }
            }
        }
        DB::commit();
        
        $this->sendPushMR($user, $post, 'added your medical record');
        return ['status' => true, 'message' => 'Medical record saved successfully!'];

        end:
        DB::rollBack();
        return $Errors;
    }
    
    public function sendPushMR($user, $post, $caption) {
        if(empty($post['huid'])){return false;}
        $notifyUser = ___HQ($post['huid']);
        $isPublic = (!empty($post['is_public'])) ? $post['is_public'] : 'N';
        //&& $isPublic=='Y'
        if(($user->id != $notifyUser['id'])){
            $pushReceiver = User::select(['id', 'cnic'])->find($notifyUser['id'])->toArray();
            $pushSender = $user->toArray();
            $userName = (!empty($user->first_name)) ? ($user->first_name.' '.$user->last_name) : $user->cnic;
            $pushNote = ['data' => ['title' => env('APP_NAME')." - {$post['name']}",
                        'body' => "{$userName} {$caption} with title {$post['name']}", 
                    'click_action' => 'FNF_MEDICAL_RECORD',
                                'subTitle' => 'Medical Record', 'name' => $userName, 'id' => $user->id, 
                                ],
                     'user_id' =>$notifyUser['id'], 'created_by' => $user->id];
            $this->sendNotification($pushReceiver, $pushSender, $pushNote);
        }
        return FALSE;
    }
    
    public function updateMedicalRecord($request, $id) {
        $user = $this->authUser['user'];
        $post = $request->all();
        $whereMedicalClause = ['is_deleted'=> '0', 'id' => $id];
        $userId = $user->id;
        $medicalRecordQuery = $this->where($whereMedicalClause);
        $instantMR = $medicalRecordQuery->first();
        $huid = $post['updated_by'] = $this->authUser['__'];
        if(!empty($post['relationship_id']) && ($instantMR['huid']!= $this->authUser['__'])){
            if($post['relationship_id'] == $user->id){ goto skipPermissions;}
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if(empty($getUser)){
                $medicalRecordHuidData = ___HQ($instantMR['huid']);
                $recordUser = User::find($medicalRecordHuidData['id']);
                $getUser = FamilyTree::checkAssociateUserRights($recordUser, ['associate_user_id' => $post['relationship_id']]);
            }
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $post['relationship_id']) ? 'write_medical_record': 'assc_write_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to update medical records for this user', 'code' => 403];
            }
            skipPermissions:
           $fnfIds = FamilyTree::getFNFuserIdsByUserId($user->id, true, ['write_medical_record']);
            //           dump($fnfIds);
            //           dump(\DB::getQueryLog());
            //           die;
            $nokUser = User::__HUID($post['relationship_id']);
            $huid= $nokUser['__'];
            $userId = $post['relationship_id'];
            //$post['relationship_id'] = $user->id;
            if($instantMR['is_public'] == 'N'){
                return ['status' => false, 'message' => 'Sorry,This Medical Record is no more shared', 'code' => 403];
            }
        }
        $userIDs = [$userId, $user->id];
        $currentUserProfiles = FamilyTree::getViralProfileIds($userIDs);
        $currentUserProfileIds = $currentUserProfiles['user_ids'];
        $currentUserProfileHuids = $currentUserProfiles['huids'];
        //\DB::connection($this->connection)->enableQueryLog();
        //$medicalRecordQuery= $medicalRecordQuery->where('id', $id);
        $medicalRecord = $medicalRecordQuery->whereIn('huid', $currentUserProfileHuids)->first();
        //$queries = \DB::connection($this->connection)->getQueryLog();	
        //        dump(___HQ('31353534ea829ae8b-31623561316461342d386232392d313165382d613666632d353235343030376164393835-1531987010'));
        //        dump($queries);
        //        dump($user->id);
        //        dump($currentUserProfileHuids);
        //        dump($this->authUser['__']);
        if(is_null($medicalRecord)){
          return ['status' => false, 'message' => 'We did not find your medical record', 'code' => 404];
        }
        
        $Errors = ['status' => false, 'code' => 422, 'message' => 'An error occured while saving medical record'];
        $documentCategories = DocumentCategory::select('id', 'code')->get()->toArray();
        $documentCategories = array_column($documentCategories, 'id', 'code');
        DB::beginTransaction();
        if (!empty($post['created_date'])) {
            $post['created_date'] = strtotime($post['created_date']);
            $post['created_date'] = date('Y-m-d H:i:s', $post['created_date']);
        } else {
            $post['created_date'] = now();
        }
        $orgPost = $post;
        $orgPost['huid'] =$medicalRecord->huid;
        //unset($post['huid']);
        unset($post['relationship_id']);
        $medicalRecord->fill($post);
        $medicalRecordStatus = $medicalRecord->update();
        if (!$medicalRecordStatus) {
            goto end;
        }
        
        $medicalRecordId = $id;
        foreach ($post['categories'] as $cat => $catData) {
            $cat_id = $documentCategories[$cat];
            //save OR update in Piviot table
            $categoryMedicalRecord = CategoryMedicalRecord::where(['medical_record_id' => $medicalRecordId, 'document_category_id' => $cat_id])->first();
            if (!$categoryMedicalRecord) {
                $categoryMedicalRecord = CategoryMedicalRecord::create(['medical_record_id' => $medicalRecordId, 'document_category_id' => $cat_id]);
            }
            if (!$categoryMedicalRecord) {
                $Errors = ['status' => false, 'message' => 'Something went wrong, Unable to proccess request for error cause 101'];
                goto end;
            }

            if (!empty($catData['documents'])) {
                //Update Documents regarding medical_records_category_id
                foreach ($catData['documents'] as $key => $document) {
                    $Document = Document::where(['is_deleted' => '0', 'id' => $document['document_id']])->first();
                    if (!$Document) {
                        $Errors = ['status' => false, 'message' => "Document id:{$document['document_id']} not exist or invalid"];
                        goto end;
                    }
                    $document['is_public'] = (!empty($post['is_public'])) ? $post['is_public'] : 'N';
                    $document['is_completed'] = 1;
                    $document['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $document['updated_by'] = $huid;
                    $deleteDocument = $document['is_deleted'] ?? 'N';
                    $document['is_deleted'] = 0;
                    if ($deleteDocument === 'Y') {
                        $Document->delete();
                        $document['deleted_by'] = $huid;
                        $document['is_deleted'] = 1;
                    }
                    $Document->fill($document);
                    $Document->save();
                }
            }

            // Save Medicines  Meta
            if (isset($catData['medicines'])) {
                foreach ($catData['medicines'] as $medicine) {
                    $medicine['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $medicine['created_by'] = $medicine['updated_by'] = $huid;
                    if (isset($medicine['medicine_id'])) {
                        $DocumentMeta = DocumentCategoryMeta::find($medicine['medicine_id']);
                        if ($DocumentMeta) {
                            if(isset($medicine['is_deleted']) && in_array($medicine['is_deleted'], ['Y', '1'])){
                                $DocumentMeta->delete();
                            }
                            $DocumentMeta->update($medicine);
                        }
                    } else {
                        DocumentCategoryMeta::create($medicine);
                    }
                }
            }
        }
        DB::commit();
        $res =  $this->sendPushMR($user, $orgPost, 'edited your medical record');
        return ['status' => true, 'message' => 'Medical record updated successfully!'];

        end:
        DB::rollBack();
        return $Errors;
    }

    public function updateMedicalRecordData($request, $id) {
        $user = $this->authUser['user'];
        $post = $request->all();
       
        //         \DB::connection($this->connection)->enableQueryLog();
        //         \DB::enableQueryLog();
        $whereMedicalClause = ['is_deleted'=> '0', 'huid' => $this->authUser['__'], 'id' => $id];
        $medicalRecordQuery = $this->where($whereMedicalClause);
        if(!empty($post['relationship_id'])){
            if($post['relationship_id'] == $user->id){ goto skipPermissions;}
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $post['relationship_id']) ? 'write_medical_record': 'assc_write_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to update medical records for this user', 'code' => 403];
            }
            skipPermissions:
           $fnfIds = FamilyTree::getFNFuserIdsByUserId($user->id, true, ['write_medical_record']);
            //           dump($fnfIds);
            //           dump(\DB::getQueryLog());
            //           die;
           $nokUser = \App\User::__HUID($post['relationship_id']);
           $fnfIds['huids'][]= $nokUser['__'];
           $allHuids = array_unique($fnfIds['huids']);
           $medicalRecordQuery = $medicalRecordQuery->where('relationship_id', $post['relationship_id'])
                   ->orWhere(function($relationQuery) use($allHuids, $id){
                       $relationQuery->whereIn('huid', $allHuids);
                       $relationQuery->where('id', $id);
                   });
        }
        //$medicalRecordQuery= $medicalRecordQuery->where('id', $id);
        $medicalRecord = $medicalRecordQuery->first();
        //        $queries = \DB::connection($this->connection)->getQueryLog();	
        //dump($user->id);
        //dump($queries);
        //        dump($medicalRecord);
        //        die;
        if(is_null($medicalRecord)){
          return ['status' => false, 'message' => 'We did not find your medical record', 'code' => 404];
        }
        
        $huid = $post['huid'] =  $post['updated_by'] = $this->authUser['__'];
        $Errors = ['status' => false, 'code' => 422, 'message' => 'An error occured while saving medical record'];
        $documentCategories = DocumentCategory::select('id', 'code')->get()->toArray();
        $documentCategories = array_column($documentCategories, 'id', 'code');
        DB::beginTransaction();
        if (!empty($post['created_date'])) {
            $post['created_date'] = strtotime($post['created_date']);
            $post['created_date'] = date('Y-m-d H:i:s', $post['created_date']);
        } else {
            $post['created_date'] = now();
        }
        $orgPost = $post;
        unset($post['huid']);
        unset($post['relationship_id']);
        $medicalRecord->fill($post);
        $medicalRecordStatus = $medicalRecord->update();
        if (!$medicalRecordStatus) {
            goto end;
        }
        
        $medicalRecordId = $id;
        foreach ($post['categories'] as $cat => $catData) {
            $cat_id = $documentCategories[$cat];
            //save OR update in Piviot table
            $categoryMedicalRecord = CategoryMedicalRecord::where(['medical_record_id' => $medicalRecordId, 'document_category_id' => $cat_id])->first();
            if (!$categoryMedicalRecord) {
                $categoryMedicalRecord = CategoryMedicalRecord::create(['medical_record_id' => $medicalRecordId, 'document_category_id' => $cat_id]);
            }
            if (!$categoryMedicalRecord) {
                $Errors = ['status' => false, 'message' => 'Something went wrong, Unable to proccess request for error cause 101'];
                goto end;
            }

            if (!empty($catData['documents'])) {
                //Update Documents regarding medical_records_category_id
                foreach ($catData['documents'] as $key => $document) {
                    $Document = Document::where(['is_deleted' => '0', 'id' => $document['document_id']])->first();
                    if (!$Document) {
                        $Errors = ['status' => false, 'message' => "Document id:{$document['document_id']} not exist or invalid"];
                        goto end;
                    }
                    $document['is_public'] = (!empty($post['is_public'])) ? $post['is_public'] : 'N';
                    $document['is_completed'] = 1;
                    $document['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $document['created_by'] = $document['updated_by'] = $huid;
                    $deleteDocument = $document['is_deleted'] ?? 'N';
                    $document['is_deleted'] = 0;
                    if ($deleteDocument === 'Y') {
                        $Document->delete();
                        $document['deleted_by'] = $huid;
                        $document['is_deleted'] = 1;
                    }
                    $Document->fill($document);
                    $Document->save();
                }
            }

            // Save Medicines  Meta
            if (isset($catData['medicines'])) {
                foreach ($catData['medicines'] as $medicine) {
                    $medicine['category_medical_record_id'] = $categoryMedicalRecord->id;
                    $medicine['created_by'] = $medicine['updated_by'] = $huid;
                    if (isset($medicine['medicine_id'])) {
                        $DocumentMeta = DocumentCategoryMeta::find($medicine['medicine_id']);
                        if ($DocumentMeta) {
                            if(isset($medicine['is_deleted']) && in_array($medicine['is_deleted'], ['Y', '1'])){
                                $DocumentMeta->delete();
                            }
                            $DocumentMeta->update($medicine);
                        }
                    } else {
                        DocumentCategoryMeta::create($medicine);
                    }
                }
            }
        }
        DB::commit();
        $res =  $this->sendPushMR($user, $orgPost, 'edited your medical record');
        dump($res);
        die;
        return ['status' => true, 'message' => 'Medical record updated successfully!'];

        end:
        DB::rollBack();
        return $Errors;
    }

    public function deleteByUserAndIdBulk($request) {
        $user = $this->authUser['user'];
        $post = $request->all();
        if(!empty($post['relationship_id']) && $post['relationship_id'] == $user->id){
            $post['relationship_id'] = null;
        }
        $medicalRecordQuery = $this->where(['huid' => $this->authUser['__'], 'is_deleted' => "0"])->whereIn('id', $request->ids);
        if(!empty($post['relationship_id'])){
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $post['relationship_id']) ? 'write_medical_record': 'assc_write_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to update medical records for this user', 'code' => 403];
            }
           $nokUser = \App\User::__HUID($post['relationship_id']);
           $medicalRecordQuery = $medicalRecordQuery->orWhere('huid', $nokUser['__']);
        }
        $delResp = $medicalRecordQuery->update(['is_deleted' => '1', 'deleted_at' => now()]);
        if ($delResp) {
            $this->sendPushMR($user, $post, "deleted your {$delResp} medical records");
            return ['status' => true, 'message' => 'Medical records deleted successfully!'];
        }
        return ['status' => false, 'message' => 'An error occured while deleting medical reocrds', 'code' => 400];
    }
    
    public function deleteMedicalRecordsBulk($request) {
        $user = $this->authUser['user'];
        $post = $request->all();
        if(!empty($post['relationship_id']) && $post['relationship_id'] == $user->id){
            $post['relationship_id'] = null;
        }
        $userId = $user->id;
        $medicalRecordQuery = $this->where(['huid' => $this->authUser['__'], 'is_deleted' => "0"])->whereIn('id', $request->ids);
        if(!empty($post['relationship_id'])){
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if(!$getUser){
                return ['status' => false, 'message' => 'This user is not in your friends and family list.', 'code' => 403];
            }
            $getUser = $getUser->toArray();
            $permissonPrefix = ($getUser['parent_user_id'] == $post['relationship_id']) ? 'write_medical_record': 'assc_write_medical_record';
            if($getUser[$permissonPrefix] == 'N'){
                return ['status' => false, 'message' => 'You don\'t have permission to update medical records for this user', 'code' => 403];
            }
           $post['huid'] = $nokUser = \App\User::__HUID($post['relationship_id']);
           $userId = $post['relationship_id'];
        //User ids + viral profile's user ids
        }
        $currentUserProfiles = FamilyTree::getViralProfileIds($userId);
        $currentUserProfileIds = $currentUserProfiles['user_ids'];
        $currentUserProfileHuids = $currentUserProfiles['huids'];
           $medicalRecordQuery = $medicalRecordQuery->orWhereIn('huid', $currentUserProfileHuids);
        $delResp = $medicalRecordQuery->update(['is_deleted' => '1', 'deleted_at' => now()]);
        if ($delResp) {
            $this->sendPushMR($user, $post, "deleted your {$delResp} medical records");
            return ['status' => true, 'message' => 'Medical records deleted successfully!'];
        }
        return ['status' => false, 'message' => 'An error occured while deleting medical reocrds', 'code' => 400];
    }
    
    public function getRecentRecord() {
        $user = $this->authUser['user'];
        $currentUserHuid = $this->authUser['__'];
        $record= $this->select(['id','huid', 'name', 'description', 'is_personal', 'relationship_id',  'is_public', 'is_deleted', 'created_at', 'created_date'])
                ->where(['is_deleted' => '0'])
                ->where('huid', $currentUserHuid)
                ->whereNull('deleted_at')
                ->with([
                    'category_medical_records' => function($categoryMedicalRecordsQuery) {
                        //$categoryMedicalRecordsQuery->select('id');
                        $documentsWhere = function($documentQuery) {
                                    $documentQuery->select(['id', 'category_medical_record_id', 'notes', 'file_name']);
                                    $documentQuery->where('is_deleted', '0');
                                };
                        $categoryMetaWhere = function($categoryQuery) {
                                    $categoryQuery->select(['id', 'category_medical_record_id', 'medicine_name', 'medicine_type', 'quantity', 'created_at']);
                                };
                        $documentCategoriesWhere = function ($documentCategoryQuery) {
                                    $documentCategoryQuery->select(['id', 'name', 'code']);
                                };
                        $categoryMedicalRecordsQuery->with([
                            'documents' => $documentsWhere,
                            'document_category_meta' => $categoryMetaWhere,
                            'document_category' => $documentCategoriesWhere
                        ]);
                    }
                ])
                ->orderBy('updated_at', 'DESC')
                ->first();
          if(empty($record)){
              return ['status' => true, 'message' => 'No medical record found'];
          }      
          $record = $record->toArray();
         $path = getUserDocumentPath($user, FALSE);
            $categoryMedicalRecordData = $record['category_medical_records'];
            unset($record['category_medical_records']);
            foreach ($categoryMedicalRecordData as $key => $categoryMedicalRecord) {
                if (isset($categoryMedicalRecord['documents']) && count($categoryMedicalRecord['documents']) > 0) {
                    foreach ($categoryMedicalRecord['documents'] as $docIndex => $doc) {
                        $categoryMedicalRecord['documents'][$docIndex]['path'] = $path . '/' . $doc['file_name'];
                        $categoryMedicalRecord['documents'][$docIndex]['document_id'] = $doc['id'];
                        unset($categoryMedicalRecord['documents'][$docIndex]['id'], $categoryMedicalRecord['documents'][$docIndex]['category_medical_record_id'], $categoryMedicalRecord['documents'][$docIndex]['file_name']);
                    }
                    $record[$categoryMedicalRecord['document_category']['code']]['documents'] = $categoryMedicalRecord['documents'];
                }
                if (!empty($categoryMedicalRecord['document_category_meta'])) {
                    foreach ($categoryMedicalRecord['document_category_meta'] as $indx => $medicine) {
                        $categoryMedicalRecord['document_category_meta'][$indx]['medicine_id'] = $medicine['id'];
                        unset($categoryMedicalRecord['document_category_meta'][$indx]['id'], $categoryMedicalRecord['document_category_meta'][$indx]['category_medical_record_id'], $categoryMedicalRecord['document_category_meta'][$indx]['created_at']);
                    }
                    $record[$categoryMedicalRecord['document_category']['code']]['medicines'] = $categoryMedicalRecord['document_category_meta'];
                }
            }       
                
       return $record;
    }

}
