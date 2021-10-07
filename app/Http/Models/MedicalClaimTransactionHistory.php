<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use App\Models\MedicalClaim;

class MedicalClaimTransactionHistory extends Eloquent {

    protected $connection = 'mysql';
    protected $table = 'medical_claim_transaction_history';

    protected $casts = [
        'organization_id' => 'int',
        'medical_claim_id' => 'int',
        'claimed_amount' => 'float',
        'approved_amount' => 'float',
        'special_amount' => 'float',
        'claimed_by' => 'int',
        'action_taken_by' => 'int',
        'policy_approval_process_id' => 'int',
        'claimed_at_level' => 'int',
        'action_taken_role_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int'
    ];
    protected $fillable = [
        
        'organization_id',
        'parent_transaction_id',
        'medical_claim_id',
        'medical_claim_serial_no',
        'medical_claim_room_rent',
        'medical_claim_room_days',
        'claim_exceeding_room_amount',
        'medical_claim_title',
        'medical_claim_created_at',
        'medical_claim_level',
        'medical_claim_type',
        'maternity_type',
        'claimed_amount',
        'ipd_consumed_amount',
        'opd_consumed_amount',
        'maternity_consumed_amount',
        'special_consumed_amount',
        'ipd_approved_amount',
        'opd_approved_amount',
        'maternity_approved_amount',
        'special_approved_amount',
        'comments',
        'external_comments',
        'special_limit_comments',
        'claimed_by',
        'claimed_by_name',
        'claimed_by_email',
        'claimed_by_contact_number',
        'claimed_by_employee_code',
        'claimed_by_basic_salary',
        'claimed_by_gross_salary',
        'action_taken_by',
        'action_taken_by_name',
        'policy_approval_process_id',
        'policy_approval_process_order',
        'action_taken_role_id',
        'action_taken_role_title',
        'action',
        'attachments',
        'is_completed',
        'lab_reports_doc_urls',
        'invoice_doc_urls',
        'prescription_doc_urls',
        'others_doc_urls',
        'claim_for_relationship',
        'claim_for_id',
        'claim_for_name',
        'claim_for_cnic',
        'in_patient_policy_id',
        'in_patient_policy_name',
        'in_patient_policy_short_code',
        'in_patient_policy_relationship_types',
        'in_patient_policy_level',
        'indoor_limit',
        'indoor_room_limit',
        'out_patient_policy_id',
        'out_patient_policy_name',
        'out_patient_policy_short_code',
        'out_patient_policy_relationship_types',
        'out_patient_policy_level',
        'outdoor_type',
        'outdoor_percentage',
        'outdoor_amount',
        'maternity_policy_id',
        'maternity_policy_name',
        'maternity_policy_short_code',
        'maternity_policy_relationship_types',
        'maternity_policy_level',
        'maternity_room_limit',
        'maternity_normal_case_limit',
        'maternity_csection_case_limit',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    public function policy_approval_process() {
        return $this->belongsTo(\App\Models\PolicyApprovalProcess::class);
    }

    public function organization() {
        return $this->belongsTo(\App\Models\Organization::class);
    }
    public function medical_claim() {
        return $this->belongsTo(\App\Models\MedicalClaim::class);
    }

    public static function getMedicalClaims($processData, $user) {
        if($processData['approval_order'] == 1){
            $claims = MedicalClaim::where(['status' => 'Pending', 'is_deleted' => '0'])
                    ->with([
                       'claim_type' => function($claimTypeQuery){
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
                    }
                ])->orderBy('created_at', 'DESC')
                            ->pagniate(10);
        dump($claims);
        }
        die;
    }
}
