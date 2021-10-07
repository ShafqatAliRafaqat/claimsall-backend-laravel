<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use DB;
use App\User;

class MedicalClaim extends Eloquent {

    use \Illuminate\Database\Eloquent\SoftDeletes;

    private $authUser = null;

    public function __construct() {
        $this->authUser = \App\User::__HUID();
    }

    protected $connection = 'hosMysql';
    protected $casts = [
        'relationship_id' => 'int',
        'organization_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'deleted_by' => 'int'
    ];
    protected $fillable = [
        'huid',
        'name',
        'employee_code',
        'employee_name',
        'serial_no',
        'description',
        'claim_type_id',
        'maternity_type',
        'room_rent',
        'room_days',
        'exceeding_room_amount',
        'claim_date',
        'claim_amount',
        'approved_amount',
        'is_personal',
        'relationship_id',
        'organization_id',
        'is_deleted',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function claim_type() {
        return $this->belongsTo(\App\Models\ClaimType::class);
    }

    public function organization() {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function relationship() {
        return $this->belongsTo(\App\Models\Relationship::class);
    }

    public function user() {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function document_categories() {
        return $this->belongsTo(\App\Models\DocumentCategory::class);
    }

    public function document_category() {
        return $this->belongsTo(\App\Models\DocumentCategory::class);
    }

    public function documents() {
        return $this->belongsToMany(\App\Models\Document::class)
                        ->withPivot('document_category_id');
    }

    public function document_medical_claim() {
        return $this->hasMany(\App\Models\DocumentMedicalClaim::class);
    }

    public function claim_transaction_history() {
        return $this->hasMany(\App\Models\MedicalClaimTransactionHistory::class);
    }

    public static function getClaimSerialNumber($user_org, $employee_code, $org_id)
    {
        $org_short_code = !empty($user_org->organization->short_code)? $user_org->organization->short_code: null;
        $org_short_code_format = $org_short_code;
        if (strlen($org_short_code) < 3) {
            $org_short_code_format = str_pad($org_short_code, 3, "0", STR_PAD_LEFT);
        }
        $claimed_by_employee_code_format = $employee_code;
        if (strlen($employee_code) < 4) {
            $claimed_by_employee_code_format = str_pad($employee_code, 4, "0", STR_PAD_LEFT);
        }
        $total_claims = self::where(['organization_id' => $org_id])->count();
        $new_claim_id_format = $total_claims;
        if (strlen($total_claims) < 3) {
            $new_claim_id_format = str_pad($total_claims, 3, "0", STR_PAD_LEFT);
        }
        return $org_short_code_format.'-'.$claimed_by_employee_code_format.'-'.$new_claim_id_format;
    }

    public function getMyMedicalClaims() {
        $user = $this->authUser['user'];
        $currentUserHUID = $this->authUser['__'];
        $path = getUserDocumentPath($user, FALSE);
        $absPath = getUserDocumentPath($user);
        $defaultOrganization = Organization::defaultOrganizationData($user);
        if(empty($defaultOrganization)){
            return ['status' => true, 'message' => 'No organization found for this user'];
        }
        
        $organizationWhere = function($organizationQuery) use ($defaultOrganization){
            $organizationQuery->select(['id', 'name']);
            //$organizationQuery->where('id', $defaultOrganization->id);
        };
        
        $fnfIds = FamilyTree::getFNFuserIdsByUserId($user->id, true);
        $fnfUserIds = $fnfIds['user_ids'];
        $fnfUserHuids = array_merge($fnfIds['huids'], [$currentUserHUID]);
        $fnfUserHuids = array_unique($fnfUserHuids);
        $records = $this->select(['id', 'huid', 'name', 'serial_no', 'description', 'is_personal', 'organization_id', 'claim_type_id', 'maternity_type', 'room_rent', 'room_days', 'claim_date', 'claim_amount', 'approved_amount', 'status', 'created_at'])
                ->where(['is_deleted' => '0', 'created_by'=> $currentUserHUID])
                ->whereIn('huid', $fnfUserHuids)
                ->with([
                    'claim_type' => function($claimTypeQuery) {
                        $claimTypeQuery->select(['id', 'name']);
                    },
                    'document_medical_claim' => function($documentMedicalClaim) {
                        $documentsWhere = function($documentQuery) {
                                    $documentQuery->select(['id', 'notes', 'file_name', 'price', 'created_at']);
                                    $documentQuery->where('is_deleted', '0');
                                };
                        $documentCategoriesWhere = function ($documentCategoryQuery) {
                                    $documentCategoryQuery->select(['id', 'name', 'code']);
                                };
                        $documentMedicalClaim->with([
                            'document' => $documentsWhere, 'document_category' => $documentCategoriesWhere
                        ]);
                    },
                    'organization' => $organizationWhere
                ])
                ->orderBy('created_at', 'DESC')
                ->paginate(10);
        $recordsDataArr = $records->toArray();

        if (!isset($recordsDataArr['data']) || count($recordsDataArr['data']) <= 0) {
            return ['status' => true, 'message' => 'You haven\'t added any medical records yet'];
        }
        
        $recordsData = $recordsDataArr['data'];
        foreach ($recordsData as $record) {
            if($record['huid'] != $currentUserHUID){
                $decHuid = ___HQ($record['huid']);
                $record['relationshipData'] = User::select(['id', 'first_name', 'last_name'])->find($decHuid['id']);
                $record['relationshipData']['name'] = getUserName($record['relationshipData']);
            }
            $categoryMedicalRecordData = $record['document_medical_claim'];
            unset($record['document_medical_claim']);
            foreach ($categoryMedicalRecordData as $key => $categoryMedicalRecord) {
                if (isset($categoryMedicalRecord['document']) && count($categoryMedicalRecord['document']) > 0) {
                    $absFullPath = $absPath .'/'. $categoryMedicalRecord['document']['file_name'];
                    if(file_exists($absFullPath)){
                        $tempExt = explode('.', $categoryMedicalRecord['document']['file_name']);
                        $categoryMedicalRecord['document']['path'] = $path . '/' . $categoryMedicalRecord['document']['file_name'];
                        $categoryMedicalRecord['document']['extension'] = '.'.end($tempExt);
                        $categoryMedicalRecord['document']['mime_type'] = \File::mimeType($absFullPath);
                        $categoryMedicalRecord['document']['document_id'] = $categoryMedicalRecord['document']['id'];
                        $record[$categoryMedicalRecord['document_category']['code']]['documents'][] = $categoryMedicalRecord['document'];
                    }
                }
            }
            if (isset($record['claim_type'])) {
                $record['claim_type_id'] = $record['claim_type']['id'];
                $record['claim_type'] = $record['claim_type']['name'];
            }
            $claim_transaction_history = \App\Models\MedicalClaimTransactionHistory::where([
                                                   'medical_claim_id' => $record['id'],
                                                   'action'           => "On Hold"
                                               ])
                                               ->orderBy('id', 'DESC')
                                               ->first();
            if(!empty($claim_transaction_history)){
               $record['comments'] = $claim_transaction_history->comments;
               $record['special_limit_comments'] = $claim_transaction_history->special_limit_comments;
               $record['attachments'] = !empty($claim_transaction_history->attachments)? unserialize($claim_transaction_history->attachments): [];
            }
            if($record['status'] === 'Decline'){
                $record['history'] = \App\Models\MedicalClaimTransactionHistory::select(['id', 'external_comments'])
                    ->where([
                    'medical_claim_id' => $record['id'],
                    'action'           => "Decline"
                ])
                    ->orderBy('id', 'DESC')
                    ->first();
            }

            
            $data[] = $record;
        }
        $recordsDataArr['data'] = $data;
        return ['status' => true, 'message' => 'Got medical claims', 'data' => $recordsDataArr];
    }

    public function getMyMedicalClaimsOLD() {
        $records = $this->where(['is_deleted' => '0', 'huid' => $this->authUser['__']])->paginate(10);
        if (count($records) <= 0) {
            return ['status' => true, 'message' => 'You haven\'t added any medical claim yet'];
        }
        $user = $this->authUser['user'];
        $path = getUserDocumentPath($user, FALSE);
        foreach ($records as $key => $record) {
            $records[$key]['documents'] = $document = Document::select('id', 'name', 'description', 'category', 'file_name', 'created_at')
                            ->where(['is_deleted' => '0', 'medical_record_id' => $record->id])
                            ->limit(10)->get();
            if ($document) {
                foreach ($document as $key => $doc) {
                    $document[$key]['file_path'] = $path . '/' . $doc->file_name;
                }
            }
        }
        return ['status' => true, 'message' => 'Got medical records', 'data' => $records];
    }

    public function getRecentClaim() {
        $claimData = $this->select(['id', 'name', 'description', 'claim_type_id', 'claim_amount', 'claim_date', 'organization_id'])
                ->where(['huid' => $this->authUser['__']])
                ->with([
                    'claim_type' => function($claimTypeQuery) {
                        $claimTypeQuery->select(['id', 'name']);
                    },
                    'document_medical_claim' => function($documentMedicalClaim) {
                        $documentsWhere = function($documentQuery) {
                                    $documentQuery->select(['id', 'notes', 'file_name', 'price', 'created_at']);
                                    $documentQuery->where('is_deleted', '0');
                                };
                        $documentCategoriesWhere = function ($documentCategoryQuery) {
                                    $documentCategoryQuery->select(['id', 'name', 'code']);
                                };
                        $documentMedicalClaim->with([
                            'document' => $documentsWhere, 'document_category' => $documentCategoriesWhere
                        ]);
                    },
                    'organization' => function ($organizationQuery) {
                        $organizationQuery->select(['id', 'name']);
                    }
                ])
                ->orderBy('updated_at', 'DESC')
                ->first();
        if (empty($claimData)) {
            return ['status' => true, 'message' => 'No medical record found'];
        }
        $record = $claimData->toArray();
        $user = $this->authUser['user'];
        $path = getUserDocumentPath($user, FALSE);
        //$recordsData = $claimData['data'];
        if(!empty($record['document_medical_claim'])){
            $categoryMedicalRecordData = $record['document_medical_claim'];
            unset($record['document_medical_claim']);
            foreach ($categoryMedicalRecordData as $key => $categoryMedicalRecord) {
                if (isset($categoryMedicalRecord['document']) && count($categoryMedicalRecord['document']) > 0) {
                    $categoryMedicalRecord['document']['path'] = $path . '/' . $categoryMedicalRecord['document']['file_name'];
                    $record[$categoryMedicalRecord['document_category']['code']]['documents'][] = $categoryMedicalRecord['document'];
                    //unset($record['document_category']);
                }
            }
            if (isset($record['claim_type'])) {
                $record['claim_type_id'] = $record['claim_type']['id'];
                $record['claim_type'] = $record['claim_type']['name'];
            }
            $record['status'] = 'Pending';
        }
        return $record;
    }

    public static function getRecentClaimbyID($new_medical_claim_id) {
        $record = self::select(['id'])
                        ->where('id', $new_medical_claim_id)
                        ->with([
                            'document_medical_claim' => function($documentMedicalClaim) {
                                $documentsWhere = function($documentQuery) {
                                            $documentQuery->select(['id', 'notes', 'file_name', 'price', 'created_at']);
                                            $documentQuery->where('is_deleted', '0');
                                        };
                                $documentCategoriesWhere = function ($documentCategoryQuery) {
                                            $documentCategoryQuery->select(['id', 'name', 'code']);
                                        };
                                $documentMedicalClaim->with([
                                    'document' => $documentsWhere, 'document_category' => $documentCategoriesWhere
                                ]);
                            }
                        ])
                        ->first()
                        ->toArray();
        return $record;
    }

    public function addMedicalClaim($request) {
        $user = $this->authUser['user'];
        //echo "<pre>";print_r($user->gender);exit;
        $post = $request->all();
       
        if ($post['claim_type_id'] == 1) {
            $room_rent = $post['room_rent'] = 0;
            $room_days = $post['room_days'] = 0;
        } elseif ($post['claim_type_id'] == 2) {
            $room_rent = $post['room_rent'] = 0;
            $room_days = $post['room_days'] = 0;
        } elseif ($post['claim_type_id'] == 3) {
            if (isset($post['room_rent']) && !empty($post['room_rent'])) {
                if ($post['room_rent'] < 100 || $post['room_rent'] >= 100000) {
                    return ['status' => false, 'message' => 'Room rent must be between 100 and 99999', 'code' => 400];
                }
                $room_rent = $post['room_rent'];
                if (!isset($post['room_days']) || empty($post['room_days'])) {
                    return ['status' => false, 'message' => 'Please provide number of days stayed', 'code' => 404];
                }
                if ($post['room_days'] < 1 || $post['room_days'] > 365) {
                    return ['status' => false, 'message' => 'The days room was hired for must be between 1 and 365', 'code' => 400];
                }
                $room_days = $post['room_days'];
            }
            else {
                $room_rent = 0;
                $room_days = 0;
                $post['room_rent'] = null;
                $post['room_days'] = null;

            }
        }

        if (($room_rent*$room_days)>$post['claim_amount']) {
            $amnt = ($room_rent*$room_days);
            return ['status' => false, 'message' => "Claim amount must not be less than {$amnt}", 'code' => 400];
        }
        
        //start::find employee(user) data of his/her belonging organization
        $org_id = !empty($post['organization_id']) ? $post['organization_id'] : null;
        if (empty($org_id)) {
            return ['status' => false, 'message' => 'Please provide organization_id', 'code' => 404];
        }
        $user_org = \App\Models\OrganizationUser::where([
                                                        'organization_id' => $org_id,
                                                        'user_id' => $user->id
                                                    ])
                                                    ->first();

        if (empty($user_org)) {
            return ['status' => false, 'message' => 'User must belong to an organization', 'code' => 404];
        }

        $claimed_by_employee_name = null;
        if (!empty($user->first_name) && !empty($user->last_name)) {
            $claimed_by_employee_name = $user->first_name.' '.$user->last_name;
        }
        elseif (!empty($user->first_name) && empty($user->last_name)) {
            $claimed_by_employee_name = $user->first_name;
        }
        elseif (empty($user->first_name) && !empty($user->last_name)) {
            $claimed_by_employee_name = $user->last_name;
        }

        $user_org_grade_id = !empty($user_org->grade_id)? $user_org->grade_id: null;
        $claimed_by_employee_code = !empty($user_org->employee_code)? $user_org->employee_code: null;
        $claimed_by_basic_salary = !empty($user_org->basic_salary)? $user_org->basic_salary: null;
        $claimed_by_gross_salary = !empty($user_org->gross_salary)? $user_org->gross_salary: null;
        $claimed_by_opd_limit = !empty($user_org->opd_limit)? $user_org->opd_limit: null;
        //end::find employee(user) data of his/her belonging organization

        //start::finding applicable policy on current claim
        $claim_type_id = !empty($post['claim_type_id']) ? $post['claim_type_id'] : null;
        $maternity_type = null;
        if ($claim_type_id == 1) {
            $policy_type = config('app.hospitallCodes')['in_patient'];
        } elseif ($claim_type_id == 2) {
            $policy_type = config('app.hospitallCodes')['out_patient'];
        } elseif ($claim_type_id == 3) {
            $policy_type = config('app.hospitallCodes')['maternity'];
            $maternity_type = !empty($post['maternity_type'])? $post['maternity_type']: config('app.hospitallCodes')['normal'];
        }
        $organization_policies = \App\Models\OrganizationPolicy::where([
                    'organization_id' => $org_id,
                    'policy_type' => $policy_type
                ])
                ->get();
        
        $user_relationship_id = !empty($post['relationship_id'])? $post['relationship_id']: null;

        if (count($organization_policies) > 0) {
            $applied_policy = \App\Models\OrganizationPolicy::checkApplicableOPD($organization_policies, $user_org_grade_id, $user_relationship_id);
            if (empty($applied_policy)) {
                return ['status' => false, 'message' => 'relationship is not covered in the applicable policy.', 'code' => 403];
            }
        }
        else {
            return ['status' => false, 'message' => $policy_type . ' policy doesn\'t exist in your organization', 'code' => 404];
        }

        //end::finding applicable policy on current claim

        //start::claim for person from NOK list or claim for self
        if (!empty($post['relationship_id'])) { // claim for person from NOK list
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);
            if (!$getUser) {
                return ['status' => false, 'message' => 'Selected user is not in your friends and family list.', 'code' => 403];
            }
            
            if ($getUser['parent_user_id'] == $post['relationship_id']) {
                $claim_for_relationship = $getUser->relationship;
            } else {
                $claim_for_relationship = $getUser->assc_relationship;
            }

            $nokUser = User::__HUID($post['relationship_id']);
            $post['huid'] = $nokUser['__'];
            $post['relationship_id'] = $user->id;
            $post['is_personal'] = '0';

            $claim_for_id = $post['relationship_id'];
            $claim_for_name = $nokUser['user']['first_name'] . ' ' . $nokUser['user']['last_name'];
            $claim_for_cnic = $nokUser['user']['cnic'];
            if (strpos($claim_for_cnic, '_') !== false) {
                $claim_for_cnic_arr = explode("_", $claim_for_cnic);
                $claim_for_cnic = $claim_for_cnic_arr[1];
            }
            $claim_for_relationship_title = $claim_for_relationship->name;
        }
        else { // claim for self
            $post['huid'] = $this->authUser['__'];

            $claim_for_id = $user->id;
            $claim_for_name = $user->first_name . ' ' . $user->last_name;
            $claim_for_cnic = $user->cnic;
            $claim_for_relationship_title = config('app.hospitallCodes')['self'];
        }
        //end::claim for person from NOK list or claim for self
        
        $claimed_amount = $post['claim_amount'];
        $post['created_by'] = $post['updated_by'] = $this->authUser['__'];
        $post['claim_date'] = $post['claim_date'] ?? now();
        $Errors = ['status' => false, 'code' => 422, 'message' => 'An error occured while saving medical record'];
        $documentCategories = DocumentCategory::select('id', 'code')->get()->toArray();
        $documentCategories = array_column($documentCategories, 'id', 'code');

        $post['employee_code'] = $claimed_by_employee_code;
        $post['employee_name'] = $claimed_by_employee_name;
        $claim_serial_no = self::getClaimSerialNumber($user_org, $claimed_by_employee_code, $org_id);
        $post['serial_no'] = $claim_serial_no;

        //////////
        $exceeding_room_amount = 0;
        if (!strcasecmp($applied_policy->policy_type, config('app.hospitallCodes')['maternity'])) {
            $room_amount_limit = $applied_policy->maternity_room_limit * $room_days;
            $claimed_room_amount = $room_rent * $room_days;
            if ($claimed_room_amount > $room_amount_limit) {
                $exceeding_room_amount = $claimed_room_amount - $room_amount_limit;
                $post['exceeding_room_amount'] = $exceeding_room_amount;
            }
        }
        //////////

        $con = DB::connection($this->connection);
        $con->beginTransaction();
        $this->fill($post);
        $medicalRecordStatus = $this->save();
        if (!$medicalRecordStatus) {
            goto end;
        }
        $new_medical_claim_id = $this->id;
        $new_medical_claim_title = $this->name;

        //new change
        $new_medical_claim_created_at = $this->created_at;
        //$new_medical_claim_created_at = $this->claim_date;
        
        $path = getUserDocumentPath($user, FALSE);
        foreach ($post['categories'] as $cat => $catData) {
            if (empty($catData['documents'])) {
                continue;
            }
            $cat_id = $documentCategories[$cat];
            //Update Documents regarding medical_records_category_id
            foreach ($catData['documents'] as $key => $document) {
                $Document = Document::where(['is_deleted' => '0', 'id' => $document['document_id'], 'owner_id' => $this->authUser['__']])->first();
                if (!$Document) {
                    continue;
                }
                $documentsArr[$document['document_id']] = ['document_category_id' => $cat_id];
                $document['is_public'] = (!empty($post['is_public'])) ? $post['is_public'] : 'N';
                $document['is_completed'] = 1;
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
                $this->documents()->sync($documentsArr);
            }
        }

        //start::1st entry in transaction history table
        //start::newly added claim details
        $record = self::getRecentClaimbyID($new_medical_claim_id);

        $lab_reports_docs_urls = [];
        $prescription_docs_urls = [];
        $invoice_docs_urls = [];
        $others_docs_urls = [];
        $relative_path = getUserDocPathforClaimTransaction($user, FALSE);
        foreach ($record['document_medical_claim'] as $key => $value) {
            if (!strcasecmp($value['document_category']['code'], config('app.hospitallCodes')['reports'])) {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($lab_reports_docs_urls, $value['document']);
            } elseif (!strcasecmp($value['document_category']['code'], config('app.hospitallCodes')['invoices'])) {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($invoice_docs_urls, $value['document']);
            } elseif (!strcasecmp($value['document_category']['code'], config('app.hospitallCodes')['prescriptions'])) {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($prescription_docs_urls, $value['document']);
            } else {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($others_docs_urls, $value['document']);
            }
        }
        //end::newly added claim details

        //PolicyApprovalProcess object
        //$policy_approval_process = \App\Models\PolicyApprovalProcess::where([
        //            'organization_id' => $org_id
        //        ])
        //        ->first();

        $claim_for_id = !empty($relationship_id) ? $relationship_id : $user->id; // here relatioship_id is the id of user for which claim has been made, do not get confused as its not the id from relationship table

        //start::find other two policies so that amount can be taken from those as well
        if (!strcasecmp($applied_policy->policy_type, config('app.hospitallCodes')['in_patient'])) {
            $organization_policies = \App\Models\OrganizationPolicy::where([
                                        'organization_id' => $org_id,
                                        'policy_type' => config('app.hospitallCodes')['out_patient']
                                    ])
                                    ->get();
            //echo "<pre>";print_r($organization_policies);exit;
            if (count($organization_policies) > 0) {
                $opd_applied_policy = \App\Models\OrganizationPolicy::checkApplicableOPD($organization_policies, $user_org_grade_id, $user_relationship_id);
            }
            else {
                $opd_applied_policy = null;
            }

            ///////////////*
            if ( ( !strcasecmp($user->gender, config('app.hospitallCodes')['Male']) &&
                   !strcasecmp($claim_for_relationship_title, "Self") ) ||
                 ( !strcasecmp($user->gender, config('app.hospitallCodes')['Female']) &&
                    strcasecmp($claim_for_relationship_title, "Self") ) ) {
                $maternity_applied_policy = null;
            }
            else {
                $organization_policies = \App\Models\OrganizationPolicy::where([
                                            'organization_id' => $org_id,
                                            'policy_type' => config('app.hospitallCodes')['maternity']
                                        ])
                                        ->get();
                if (count($organization_policies) > 0) {
                    $maternity_applied_policy = \App\Models\OrganizationPolicy::checkApplicablePolicy($organization_policies, $user_org_grade_id, $user_relationship_id);
                }
                else {
                    $maternity_applied_policy = null;
                }
            }
            ///////////////*

            $ipd_applied_policy = $applied_policy;  
        }
        elseif (!strcasecmp($applied_policy->policy_type, config('app.hospitallCodes')['out_patient'])) {
            $organization_policies = \App\Models\OrganizationPolicy::where([
                                        'organization_id' => $org_id,
                                        'policy_type' => config('app.hospitallCodes')['in_patient']
                                    ])
                                    ->get();
            if (count($organization_policies) > 0) {
                $ipd_applied_policy = \App\Models\OrganizationPolicy::checkApplicablePolicy($organization_policies, $user_org_grade_id, $user_relationship_id);
            }
            else {
                $ipd_applied_policy = null;
            }

            ///////////////*
            if ( ( !strcasecmp($user->gender, config('app.hospitallCodes')['Male']) &&
                   !strcasecmp($claim_for_relationship_title, "Self") ) ||
                 ( !strcasecmp($user->gender, config('app.hospitallCodes')['Female']) &&
                    strcasecmp($claim_for_relationship_title, "Self") ) ) {
                $maternity_applied_policy = null;
            }
            else {
                $organization_policies = \App\Models\OrganizationPolicy::where([
                                            'organization_id' => $org_id,
                                            'policy_type' => config('app.hospitallCodes')['maternity']
                                        ])
                                        ->get();
                if (count($organization_policies) > 0) {
                    $maternity_applied_policy = \App\Models\OrganizationPolicy::checkApplicablePolicy($organization_policies, $user_org_grade_id, $user_relationship_id);
                }
                else {
                    $maternity_applied_policy = null;
                }
            }
            ///////////////*

            $opd_applied_policy = $applied_policy;
        }
        else {
            $organization_policies = \App\Models\OrganizationPolicy::where([
                                        'organization_id' => $org_id,
                                        'policy_type' => config('app.hospitallCodes')['in_patient']
                                    ])
                                    ->get();
            if (count($organization_policies) > 0) {
                $ipd_applied_policy = \App\Models\OrganizationPolicy::checkApplicablePolicy($organization_policies, $user_org_grade_id, $user_relationship_id);
            }
            else {
                $ipd_applied_policy = null;
            }
            $organization_policies = \App\Models\OrganizationPolicy::where([
                                        'organization_id' => $org_id,
                                        'policy_type' => config('app.hospitallCodes')['out_patient']
                                    ])
                                    ->get();
            if (count($organization_policies) > 0) {
                $opd_applied_policy = \App\Models\OrganizationPolicy::checkApplicableOPD($organization_policies, $user_org_grade_id, $user_relationship_id);
            }
            else {
                $opd_applied_policy = null;
            }
            $maternity_applied_policy = $applied_policy;
        }
        //end::find other two policies so that amount can be taken from those as well

        //IPD policy data
        $ipd_relationship_types = [];
        if (!empty($ipd_applied_policy)) {
            $ipd_covered_persons = $ipd_applied_policy->policy_covered_persons;
            foreach ($ipd_covered_persons as $key => $ipd_covered_person) {
                array_push($ipd_relationship_types, $ipd_covered_person->relationship_type->name);
            }
            $in_patient_policy_id = $ipd_applied_policy->id;
            $in_patient_policy_name = $ipd_applied_policy->name;
            $in_patient_policy_short_code = $ipd_applied_policy->short_code;
            $in_patient_policy_relationship_types = serialize($ipd_relationship_types);
            $in_patient_policy_level = $ipd_applied_policy->policy_level;
            $indoor_limit = $ipd_applied_policy->indoor_limit;
            $indoor_room_limit = $ipd_applied_policy->indoor_room_limit;
        }
        else {
            $in_patient_policy_id = null;
            $in_patient_policy_name = null;
            $in_patient_policy_short_code = null;
            $in_patient_policy_relationship_types = serialize($ipd_relationship_types);
            $in_patient_policy_level = null;
            $indoor_limit = null;
            $indoor_room_limit = null;
        }

        //OPD policy data
        $opd_relationship_types = [];
        if (!empty($opd_applied_policy)) {
            $opd_covered_persons = $opd_applied_policy->policy_covered_persons;
            foreach ($opd_covered_persons as $key => $opd_covered_person) {
                array_push($opd_relationship_types, $opd_covered_person->relationship_type->name);
            }
            $out_patient_policy_id = $opd_applied_policy->id;
            $out_patient_policy_name = $opd_applied_policy->name;
            $out_patient_policy_short_code = $opd_applied_policy->short_code;
            $out_patient_policy_relationship_types = serialize($opd_relationship_types);
            $out_patient_policy_level = $opd_applied_policy->policy_level;

            $outdoor_type = $opd_applied_policy->type;
            if (!strcasecmp($opd_applied_policy->policy_level, config('app.hospitallCodes')['user'])) {
                $outdoor_amount = $claimed_by_opd_limit;
            }
            else {
                if (!strcasecmp($opd_applied_policy->type, config('app.hospitallCodes')['percentage'])) {
                    $outdoor_percentage = $opd_applied_policy->outdoor_amount;
                    $outdoor_amount = ($opd_applied_policy->outdoor_amount*$claimed_by_basic_salary)/100;
                }
                else { // Fixed
                    $outdoor_percentage = $opd_applied_policy->outdoor_amount;
                    $outdoor_amount = $opd_applied_policy->outdoor_amount;
                }
            }
        }
        else {
            $out_patient_policy_id = null;
            $out_patient_policy_name = null;
            $out_patient_policy_short_code = null;
            $out_patient_policy_relationship_types = serialize($opd_relationship_types);
            $out_patient_policy_level = null;
            $outdoor_amount = null;
        }
        
        //Maternity policy data
        $maternity_relationship_types = [];
        if (!empty($maternity_applied_policy)) {
            $maternity_covered_persons = $maternity_applied_policy->policy_covered_persons;
            foreach ($maternity_covered_persons as $key => $maternity_covered_person) {
                array_push($maternity_relationship_types, $maternity_covered_person->relationship_type->name);
            }
            $maternity_policy_id = $maternity_applied_policy->id;
            $maternity_policy_name = $maternity_applied_policy->name;
            $maternity_policy_short_code = $maternity_applied_policy->short_code;
            $maternity_policy_relationship_types = serialize($maternity_relationship_types);
            $maternity_policy_level = $maternity_applied_policy->policy_level;
            $maternity_room_limit = $maternity_applied_policy->maternity_room_limit;
            $maternity_normal_case_limit = $maternity_applied_policy->maternity_normal_case_limit;
            $maternity_csection_case_limit = $maternity_applied_policy->maternity_csection_case_limit;
        }
        else {
            $maternity_policy_id = null;
            $maternity_policy_name = null;
            $maternity_policy_short_code = null;
            $maternity_policy_relationship_types = serialize($maternity_relationship_types);
            $maternity_policy_level = null;
            $maternity_room_limit = null;
            $maternity_normal_case_limit = null;
            $maternity_csection_case_limit = null;
        }

        //MedicalClaimTransactionHistory object
        $time = now();
        $claim_transaction_history = [
                'organization_id'   => $org_id,
                'medical_claim_id'          => $new_medical_claim_id,
                'medical_claim_serial_no'   => $claim_serial_no,
                'medical_claim_room_rent'   => $room_rent,
                'medical_claim_room_days'   => $room_days,
                'claim_exceeding_room_amount'   => $exceeding_room_amount,
                'medical_claim_title'       => $new_medical_claim_title,
                'medical_claim_created_at'  => $new_medical_claim_created_at,
                'medical_claim_level' => $applied_policy->policy_level,
                'medical_claim_type'  => $applied_policy->policy_type,
                'maternity_type'      => $maternity_type,
                'claimed_amount'      => $claimed_amount,
                //'comments' => ,
                //'special_limit_comments' => ,
                'claimed_by'                => $user->id,
                //'claimed_by_name'           => $user->first_name.' '.$user->last_name,
                'claimed_by_name'           => $claimed_by_employee_name,
                'claimed_by_email'          => $user->email,
                'claimed_by_contact_number' => !empty($user->contact_number)? $user->contact_number: null,
                'claimed_by_employee_code'  => $claimed_by_employee_code,
                'claimed_by_basic_salary'   => $claimed_by_basic_salary,
                'claimed_by_gross_salary'   => $claimed_by_gross_salary,
                //'action_taken_by' => ,
                //'action_taken_by_name' => ,
                //'policy_approval_process_id'    => !empty($policy_approval_process->id)? $policy_approval_process->id: null,
                //'policy_approval_process_order' => $policy_approval_process->approval_order,
                'policy_approval_process_order' => 1,
                //'action_taken_role_id' => $policy_approval_process->role_id,
                //'action_taken_role_title' => $policy_approval_process->role->title,
                //'action' => ,
                //'is_completed' => ,
                'lab_reports_doc_urls'  => serialize($lab_reports_docs_urls),
                'invoice_doc_urls'      => serialize($invoice_docs_urls),
                'prescription_doc_urls' => serialize($prescription_docs_urls),
                'others_doc_urls'       => serialize($others_docs_urls),
                'claim_for_relationship' => $claim_for_relationship_title,
                'claim_for_id'           => $claim_for_id,
                'claim_for_name'         => $claim_for_name,
                'claim_for_cnic'         => $claim_for_cnic,
                'in_patient_policy_id'                      => $in_patient_policy_id,
                'in_patient_policy_name'                    => $in_patient_policy_name,
                'in_patient_policy_short_code'              => $in_patient_policy_short_code,
                'in_patient_policy_relationship_types'      => $in_patient_policy_relationship_types,
                'in_patient_policy_level'                   => $in_patient_policy_level,
                'indoor_limit'                              => $indoor_limit,
                'indoor_room_limit'                         => $indoor_room_limit,
                'out_patient_policy_id'                     => $out_patient_policy_id,
                'out_patient_policy_name'                   => $out_patient_policy_name,
                'out_patient_policy_short_code'             => $out_patient_policy_short_code,
                'out_patient_policy_relationship_types'     => $out_patient_policy_relationship_types,
                'out_patient_policy_level'                  => $out_patient_policy_level,
                'outdoor_type'                              => !empty($outdoor_type)? $outdoor_type: null,
                'outdoor_percentage'                        => !empty($outdoor_percentage)? $outdoor_percentage: null,
                'outdoor_amount'                            => $outdoor_amount,
                'maternity_policy_id'                       => $maternity_policy_id,
                'maternity_policy_name'                     => $maternity_policy_name,
                'maternity_policy_short_code'               => $maternity_policy_short_code,
                'maternity_policy_relationship_types'       => $maternity_policy_relationship_types,
                'maternity_policy_level'                    => $maternity_policy_level,
                'maternity_room_limit'                      => $maternity_room_limit,
                'maternity_normal_case_limit'               => $maternity_normal_case_limit,
                'maternity_csection_case_limit'             => $maternity_csection_case_limit,
                'created_at'  => $time,
                'updated_at'  => $time,
                'created_by'  => $user->id,
                'updated_by'  => $user->id
            ];

        if (!\App\Models\MedicalClaimTransactionHistory::insert($claim_transaction_history)) {
            goto end;
        }
        //end::1st entry in transaction history table

        $con->commit();

        return ['status' => true, 'message' => 'Your medical claim submitted successfully!'];

        end:
        $con->rollBack();
        return $Errors;
    }

    public function updateMedicalClaimById($request, $id) {
        $user = $this->authUser['user'];
        $post = $request->all();
        $claim = \App\Models\MedicalClaim::findOrFail($id);

        if ($claim->claim_type_id == 1) {
            $room_rent = $post['room_rent'] = 0;
            $room_days = $post['room_days'] = 0;
        } elseif ($claim->claim_type_id == 2) {
            $room_rent = $post['room_rent'] = 0;
            $room_days = $post['room_days'] = 0;
        } elseif ($claim->claim_type_id == 3) {
            if (isset($post['room_rent']) && !empty($post['room_rent'])) {
                if ($post['room_rent'] < 100 || $post['room_rent'] > 10000000) {
                    return responseBuilder()->error('Room rent must range (100 - 9999999)', 400);
                }
                $room_rent = $post['room_rent'];
                if (!isset($post['room_days']) || empty($post['room_days'])) {
                    return ['status' => false, 'message' => 'Please provide number of days stayed', 'code' => 404];
                }
                if ($post['room_days'] < 1 || $post['room_days'] > 365) {
                    return responseBuilder()->error('Room days must range (1 - 365)', 400);
                }
                $room_days = $post['room_days'];
            }
            else {
                $room_rent = 0;
                $room_days = 0;
                $post['room_rent'] = null;
                $post['room_days'] = null;
            }
        }

        if (($room_rent*$room_days)>$post['claim_amount']) {
            $amnt = ($room_rent*$room_days);
            return ['status' => false, 'message' => "Claim amount must not be less than {$amnt}", 'code' => 400];
        }

        if (strcasecmp($claim->status, config('app.hospitallCodes')['on_hold'])) {
            return ['status' => false, 'message' => 'You cannot edit this claim', 'code' => 404];
        }

        //start::find employee(user) data of his/her belonging organization
        $org_data = \App\Models\Organization::userOrganization($user, false);
        if (empty($org_data)) {
            return responseBuilder()->error('User doesn\'t belong to an organization', 404, false);
        }
        $org_id = $org_data->organization_user[0]->organization_id;

        $user_org_grade_id = !empty($org_data->organization_user[0]->grade_id)? $org_data->organization_user[0]->grade_id: null;
        $claimed_by_employee_code = !empty($org_data->organization_user[0]->employee_code)? $org_data->organization_user[0]->employee_code: null;
        $claimed_by_basic_salary = !empty($org_data->organization_user[0]->basic_salary)? $org_data->organization_user[0]->basic_salary: null;
        $claimed_by_gross_salary = !empty($org_data->organization_user[0]->gross_salary)? $org_data->organization_user[0]->gross_salary: null;
        //end::find employee(user) data of his/her belonging organization

        //start::finding applicable policy on current claim
        $claim_type_id = !empty($post['claim_type_id']) ? $post['claim_type_id'] : null;
        if ($claim->claim_type_id == 1) {
            $policy_type = config('app.hospitallCodes')['in_patient'];
        } elseif ($claim->claim_type_id == 2) {
            $policy_type = config('app.hospitallCodes')['out_patient'];
        } elseif ($claim->claim_type_id == 3) {
            $policy_type = config('app.hospitallCodes')['maternity'];
        }
        $organization_policies = \App\Models\OrganizationPolicy::where([
                    'organization_id' => $org_id,
                    'policy_type' => $policy_type
                ])
                ->get();
        
        $user_relationship_id = !empty($post['relationship_id'])? $post['relationship_id']: null;
        if (count($organization_policies) > 0) {
            $applied_policy = \App\Models\OrganizationPolicy::checkApplicableOPD($organization_policies, $user_org_grade_id, $user_relationship_id);
            if (empty($applied_policy)) {
                return ['status' => false, 'message' => 'relationship is not covered in the applicable policy.', 'code' => 403];
            }
        }
        else {
            return ['status' => false, 'message' => $policy_type . ' policy doesn\'t exist in your organization', 'code' => 404];
        }
        //end::finding applicable policy on current claim

        //start::claim for person from NOK list or claim for self
        if (!empty($post['relationship_id'])) { // claim for person from NOK list
            $getUser = FamilyTree::checkAssociateUserRights($user, ['associate_user_id' => $post['relationship_id']]);

            if (!$getUser) {
                return ['status' => false, 'message' => 'Selected user is not in your friends and family list.', 'code' => 403];
            }

            if ($getUser['parent_user_id'] == $post['relationship_id']) {
                $claim_for_relationship = $getUser->relationship;
            } else {
                $claim_for_relationship = $getUser->assc_relationship;
            }

            $nokUser = User::__HUID($post['relationship_id']);
            $post['huid'] = $nokUser['__'];
            $post['relationship_id'] = $user->id;
            $post['is_personal'] = '0';

            $claim_for_id = $post['relationship_id'];
            $claim_for_name = $nokUser['user']['first_name'] . ' ' . $nokUser['user']['last_name'];
            $claim_for_cnic = $nokUser['user']['cnic'];
            $claim_for_relationship_title = $claim_for_relationship->name;
        }
        else { // claim for self
            $post['huid'] = $this->authUser['__'];

            $claim_for_id = $user->id;
            $claim_for_name = $user->first_name . ' ' . $user->last_name;
            $claim_for_cnic = $user->cnic;
            $claim_for_relationship_title = config('app.hospitallCodes')['self'];
        }
        //end::claim for person from NOK list or claim for self
        
        $claimed_amount = $post['claim_amount'];
        $post['updated_by'] = $this->authUser['__'];
        $Errors = ['status' => false, 'code' => 422, 'message' => 'An error occured while saving medical record'];
        $documentCategories = DocumentCategory::select('id', 'code')->get()->toArray();
        $documentCategories = array_column($documentCategories, 'id', 'code');

        $post['employee_code'] = $claimed_by_employee_code;
        $post['status'] = 'Pending';

        //////////
        $exceeding_room_amount = 0;
        if (!strcasecmp($applied_policy->policy_type, config('app.hospitallCodes')['maternity'])) {
            $room_amount_limit = $applied_policy->maternity_room_limit * $room_days;
            $claimed_room_amount = $room_rent * $room_days;
            if ($claimed_room_amount > $room_amount_limit) {
                $exceeding_room_amount = $claimed_room_amount - $room_amount_limit;
                $post['exceeding_room_amount'] = $exceeding_room_amount;
            }
        }
        //////////


        $con = DB::connection($this->connection);
        $con->beginTransaction();
        if (!$claim->update($post)) {
            goto end;
        }
        $new_medical_claim_id = $claim->id;
        $new_medical_claim_title = $claim->name;

        //new change
        //$new_medical_claim_created_at = $claim->updated_at;

        $new_medical_claim_claimed_amount = $claim->claim_amount;
        
        $path = getUserDocumentPath($user, FALSE);
        foreach ($post['categories'] as $cat => $catData) {
            if (empty($catData['documents'])) {
                continue;
            }
            $cat_id = $documentCategories[$cat];
            //Update Documents regarding medical_records_category_id
            foreach ($catData['documents'] as $key => $document) {
                $Document = Document::where(['is_deleted' => '0', 'id' => $document['document_id'], 'owner_id' => $this->authUser['__']])->first();
                if (!$Document) {
                    continue;
                }
                $documentsArr[$document['document_id']] = ['document_category_id' => $cat_id];
                $document['is_public'] = (!empty($post['is_public'])) ? $post['is_public'] : 'N';
                $document['is_completed'] = 1;
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
                $claim->documents()->sync($documentsArr);
            }
        }

        //start::next entry in transaction history table
        //start::newly added claim details
        $record = self::getRecentClaimbyID($new_medical_claim_id);

        $lab_reports_docs_urls = [];
        $prescription_docs_urls = [];
        $invoice_docs_urls = [];
        $others_docs_urls = [];
        $relative_path = getUserDocPathforClaimTransaction($user, FALSE);
        foreach ($record['document_medical_claim'] as $key => $value) {
            if (!strcasecmp($value['document_category']['code'], config('app.hospitallCodes')['reports'])) {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($lab_reports_docs_urls, $value['document']);
            } elseif (!strcasecmp($value['document_category']['code'], config('app.hospitallCodes')['invoices'])) {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($invoice_docs_urls, $value['document']);
            } elseif (!strcasecmp($value['document_category']['code'], config('app.hospitallCodes')['prescriptions'])) {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($prescription_docs_urls, $value['document']);
            } else {
                $value['document']['url'] = $relative_path . '/' . $value['document']['file_name'];
                array_push($others_docs_urls, $value['document']);
            }
        }
        //end::newly added claim details

        //PolicyApprovalProcess object
        $policy_approval_process = \App\Models\PolicyApprovalProcess::where([
                    'organization_id' => $org_id
                ])
                ->first();

        $claim_for_id = !empty($relationship_id) ? $relationship_id : $user->id; // here relatioship_id is the id of user for which claim has been made, do not get confused as its not the id from relationship table

        //MedicalClaimTransactionHistory object
        $claim_transaction_history = \App\Models\MedicalClaimTransactionHistory::where([
                                                    'medical_claim_id' => $new_medical_claim_id,
                                                    'action'           => config('app.hospitallCodes')['on_hold']
                                                ])
                                                ->orderBy('id', 'DESC')
                                                ->first();

        $next_claim_transaction_history = $claim_transaction_history->toArray();
        unset($next_claim_transaction_history['id']);
        $next_claim_transaction_history['parent_transaction_id'] = $claim_transaction_history->id;
        $next_claim_transaction_history['medical_claim_id'] = $new_medical_claim_id;
        $next_claim_transaction_history['medical_claim_title'] = $new_medical_claim_title;
        $next_claim_transaction_history['medical_claim_room_rent'] = $room_rent;
        $next_claim_transaction_history['medical_claim_room_days'] = $room_days;
        $next_claim_transaction_history['claim_exceeding_room_amount'] = $exceeding_room_amount;

        // new change
        //$next_claim_transaction_history['medical_claim_created_at'] = $new_medical_claim_created_at;
        //$next_claim_transaction_history['medical_claim_created_at'] = $claim_transaction_history->medical_claim_created_at;

        $next_claim_transaction_history['claimed_amount'] = $new_medical_claim_claimed_amount;
        $next_claim_transaction_history['claimed_by'] = $user->id;
        $next_claim_transaction_history['claimed_by_name'] = $user->first_name.' '.$user->last_name;
        $next_claim_transaction_history['claimed_by_email'] = $user->email;
        $next_claim_transaction_history['claimed_by_contact_number'] = !empty($user->contact_number)? $user->contact_number: null;
        $next_claim_transaction_history['claimed_by_employee_code'] = $claimed_by_employee_code;
        $next_claim_transaction_history['claimed_by_basic_salary'] = $claimed_by_basic_salary;
        $next_claim_transaction_history['claimed_by_gross_salary'] = $claimed_by_gross_salary;
        $next_claim_transaction_history['lab_reports_doc_urls'] = serialize($lab_reports_docs_urls);
        $next_claim_transaction_history['invoice_doc_urls'] = serialize($invoice_docs_urls);
        $next_claim_transaction_history['prescription_doc_urls'] = serialize($prescription_docs_urls);
        $next_claim_transaction_history['others_doc_urls'] = serialize($others_docs_urls);
        //$next_claim_transaction_history['claim_for_relationship'] = $claim_for_relationship_title;
        //$next_claim_transaction_history['claim_for_id'] = $claim_for_id;
        //$next_claim_transaction_history['claim_for_name'] =  $claim_for_name;
        //$next_claim_transaction_history['claim_for_cnic'] =  $claim_for_cnic;
        $next_claim_transaction_history['created_at'] =  now();
        $next_claim_transaction_history['updated_at'] =  now();
        $next_claim_transaction_history['created_by'] =  $user->id;
        $next_claim_transaction_history['updated_by'] =  $user->id;
        $next_claim_transaction_history['is_completed'] =  'N';
        $next_claim_transaction_history['action'] =  'Pending';

        if (!\App\Models\MedicalClaimTransactionHistory::insert($next_claim_transaction_history)) {
            goto end;
        }
        //end::next entry in transaction history table

        $con->commit();

        return ['status' => true, 'message' => 'Your medical claim is updated successfully!'];

        end:
        $con->rollBack();
        return $Errors;
    }
}
