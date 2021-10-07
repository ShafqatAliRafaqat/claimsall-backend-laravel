<?php

use Illuminate\Http\Request;

/*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
 */
//Auth::routes();
Route::group(['middleware' => 'nocache'], function() {

    Route::get('city/list', ['uses' => 'API\CityController@countryCities', 'as' => 'api.city.list']);
    Route::get('activity/cron', ['uses' => 'API\ActivityController@activityCron', 'as' => 'api.activity.cron']);
    Route::get('appointment/cron', ['uses' => 'API\AppointmentController@appointmentCron', 'as' => 'api.appointment.cron']);

    Route::post('web-login', 'API\UserController@userWebLogin');
    Route::post('login', 'API\UserController@userLogin');
    Route::post('register', 'API\UserController@userRegister');
    Route::post('reset-password', 'API\UserController@resetPassword');
    Route::post('password/email', 'API\UserController@sendResetLinkEmail');
});
// GROUP

Route::group(['middleware' => 'auth:api', 'nocache'], function() {
    // Stats API
    Route::post('serviceproviders-stats', 'API\IndexController@serviceproviderStats'); // stats for serviceproviders
    Route::post('claim-stats', 'API\IndexController@claimStats');
    Route::post('stats', 'API\IndexController@getStats'); // stats for users/organizations
    Route::post('careservice-stats', 'API\IndexController@careserviceStats');
    Route::post('user-stats', 'API\IndexController@getUserStats');
    Route::post('org-user-stats', 'API\IndexController@orgUserStats');
    Route::post('org-user-count', 'API\IndexController@orgUserCount');
    Route::post('orgs-stats', 'API\IndexController@getOrgsStats');
    Route::post('request-stats', 'API\IndexController@requestStats');
    Route::post('role-stats', 'API\IndexController@roleStats');

    // RESOURCES

    Route::resource('module', 'API\ModuleController');
    Route::resource('medical-claim', 'API\MedicalClaimController');
    Route::resource('medical-record', 'API\MedicalRecordController');
    Route::resource('role', 'API\RoleController');
    Route::resource('emergency-contact', 'API\EmergencyContactController');
    Route::resource('activity', 'API\ActivityController');
    Route::resource('claim-history', 'API\ClaimHistoryController');
    Route::resource('notification', 'API\NotificationController');
    Route::resource('care-service', 'API\CareServiceController');
    Route::resource('care-service-type', 'API\CareServiceTypeController');
    Route::resource('grade', 'API\GradeController');
    Route::resource('fnf', 'API\FNFController');

    Route::resource('user', 'API\UserController')->middleware('checkUserRole');
    Route::resource('template', 'API\TemplateController')->middleware('checkUserRole');
    Route::resource('role', 'API\RoleController')->middleware('checkUserRole');
    Route::resource('relationship', 'API\RelationshipController')->middleware('checkUserRole');
    Route::resource('appointment', 'API\AppointmentController');
    Route::resource('organization', 'API\OrganizationController')->middleware('checkUserRole');
    Route::resource('organization-type', 'API\OrganizationTypeController')->middleware('checkUserRole');
    Route::resource('organization-policy', 'API\PolicyController')->middleware('checkUserRole');
    Route::resource('health-monitoring-type', 'API\HealthMonitoringTypeController')->middleware('checkUserRole');

    // GET requests

    Route::get('settings', ['uses' => 'API\IndexController@settings', 'as' => 'api.index.settings']);
    Route::get('check-auth', ['uses' => 'API\IndexController@validateToken', 'as' => 'api.index.check_auth']);

    Route::get('getUserRole', ['uses' => 'API\IndexController@getUserRole', 'as' => 'api.index.userRole']);

    Route::get('available-policy-limits', [// availablePolicyLimits
        'uses' => 'API\MedicalClaimController@availablePolicyLimits',
        'as' => 'api.medical-claim.availablePolicyLimits'
    ]);

    Route::get('get-claim-transactions', [// transactions details against a claim
        'uses' => 'API\MedicalClaimController@getClaimTransactions',
        'as' => 'api.medical-claim.getClaimTransactions'
    ])->middleware('checkUserRole');

    Route::get('calculate-medical-consumptions', [// live consumptions(OPD/IPD/Maternity)
        'uses' => 'API\MedicalClaimController@calculateMedicalConsumptions',
        'as' => 'api.medical-claim.calculateMedicalConsumptions'
    ])->middleware('checkUserRole');

    Route::get('document-categories', ['uses' => 'API\DocumentController@getDocumentCategory', 'as' => 'api.get_document_category']);

    Route::get('relationship-list', ['uses' => 'API\RelationshipController@relationshipList', 'as' => 'api.relationship.list']);

    Route::get('get-organization-types', [// generic Industries
        'uses' => 'API\OrganizationTypeController@getIndustries',
        'as' => 'api.OrganizationType.getIndustries'
    ])->middleware('checkUserRole');

    Route::get('get-administrative-roles', [// get Administrative Roles
        'uses' => 'API\OrganizationController@getAdministrativeRoles',
        'as' => 'api.organization.getAdministrativeRoles'
    ])->middleware('checkUserRole');

    Route::get('lookup-orgs', [// lookup organizations
        'uses' => 'API\IndexController@lookupOrgs',
        'as' => 'api.index.lookupOrgs'
    ]);

    Route::get('lookup-careservicestype', [// lookup Careservices type
        'uses' => 'API\IndexController@lookupCareservices',
        'as' => 'api.index.lookupCareservices'
    ]);

    Route::get('lookup-relationship-types', [// lookup Relationship Types
        'uses' => 'API\IndexController@lookupRelationshipTypes',
        'as' => 'api.index.lookupRelationshipTypes'
    ]);

    Route::get('lookup-modules', [// lookup Modules
        'uses' => 'API\IndexController@lookupModules',
        'as' => 'api.index.lookupModules'
    ]);

    Route::get('get-recent-data', [
        'uses' => 'API\IndexController@recentDataForType',
        'as' => 'api.index.recent_data',
    ]);

    Route::get('policy/covered-persons/{claimId}', [
        'uses' => 'API\PolicyController@getPolicyCoveredPersons',
        'as' => 'api.policy.getPolicyCovered',
    ]);

    Route::get('policy/view-approval-process', [
        'uses' => 'API\PolicyController@viewPolicyApprovalProcess',
        'as' => 'api.policy.viewPolicyApprovalProcess',
    ]);

    Route::get('policy/approval-process', [
        'uses' => 'API\PolicyController@policyApprovalProcessList',
        'as' => 'api.policy.approval_process_list',
    ]);

    Route::get('policy/status', [
        'uses' => 'API\PolicyController@policyStatuses',
        'as' => 'api.policy.approval_statuses',
    ]);

    Route::get('careservice-requests', [// for mobile side
        'uses' => 'API\CareServiceController@careserviceRequests',
        'as' => 'api.careservice.careserviceRequests'
    ]);

    Route::get('careservice-request', [// for mobile side
        'uses' => 'API\CareServiceController@careserviceRequest',
        'as' => 'api.careservice.careserviceRequest'
    ]);

    Route::get('organization-list', ['uses' => 'API\OrganizationController@approvedOrganization', 'as' => 'api.organization.list']);

    Route::get('fnf/list', [
        'uses' => 'API\FNFController@listFNF',
        'as' => 'api.fnf.my_user',
    ]);

    Route::get('my-viral-accounts', [
        'uses' => 'API\IndexController@getUserViralAccounts',
        'as' => 'api.index.user_viral_account_status',
    ]);

    Route::get('my-pending-viral-accounts', [
        'uses' => 'API\IndexController@getPendingViralAccounts',
        'as' => 'api.index.pending_viral_account_list',
    ]);

    Route::get('medical-lab-tests', [
        'uses' => 'API\IndexController@getMedicalLabTest',
        'as' => 'api.index.medical_lab_test',
    ]);

    Route::get('health-care/types', [
        'uses' => 'API\HealthCareController@getHealthCareTypes',
        'as' => 'api.healthcare.types',
    ]);

    Route::get('health-care/{fnfUserId}', [
        'uses' => 'API\HealthCareController@getHealthCareByRelationshipID',
        'as' => 'api.healthcare.familyTreeData',
    ]);

    Route::get('logout', ['uses' => 'API\UserController@userLogout', 'as' => 'api.user.logout']);

    // POST requests

    Route::post('import', [
        'uses' => 'API\DataController@import',
        'as' => 'api.data.import'
    ])->middleware('checkUserRole');

    Route::post('import-dependents', [
        'uses' => 'API\DataController@importDependents',
        'as' => 'api.data.importDependents'
    ])->middleware('checkUserRole');

    Route::post('get-open-claims-transactions', [// get my open claims transactions for processing
        'uses' => 'API\MedicalClaimController@getOpenClaimsTransactions',
        'as' => 'api.medical-claim.getOpenClaimsTransactions'
    ])->middleware('checkUserRole');

    Route::post('get-claims', [// transactions details against a claim
        'uses' => 'API\MedicalClaimController@getClaims',
        'as' => 'api.medical-claim.getClaims'
    ])->middleware('checkUserRole');

    Route::post('process-claim', [// process claim
        'uses' => 'API\MedicalClaimController@processClaim',
        'as' => 'api.medical-claim.processClaim'
    ])->middleware('checkUserRole');

    Route::post('manage-document', ['uses' => 'API\DocumentController@addUpdateDocument', 'as' => 'api.manage_document']);

    Route::post('upload-document', ['uses' => 'API\DocumentController@addDocument', 'as' => 'api.add_document']);

    Route::post('upload-careservice-document', ['uses' => 'API\DocumentController@addCareserviceDocument', 'as' => 'api.add_careservice_document']);

    Route::post('get-appointments', [// get appointments
        'uses' => 'API\AppointmentController@getAppointments',
        'as' => 'api.appointment.getAppointments'
    ]);

    Route::post('get-activities', [// get activities
        'uses' => 'API\ActivityController@getActivities',
        'as' => 'api.activity.getActivities'
    ]);

    Route::post('add-organization-user', [//Update User Profile
        'uses' => 'API\OrganizationController@addUserToOrganization',
        'as' => 'api.organization.addUser'
    ])->middleware('checkUserRole');

    Route::post('get-organizations', [// generic organizations (filtration, search, sorter, pagination)
        'uses' => 'API\OrganizationController@getOrganizations',
        'as' => 'api.organization.getOrganizations'
    ])->middleware('checkUserRole');

    Route::post('delete-organization', [// delete all selected organizations
        'uses' => 'API\OrganizationController@deleteOrganization',
        'as' => 'api.organization.deleteOrganization'
    ])->middleware('checkUserRole');

    Route::post('get-users', [// generic users (filtration, search, sorter, pagination)
        'uses' => 'API\UserController@getUsers',
        'as' => 'api.user.getUsers'
    ])->middleware('checkUserRole');

    Route::post('register-doc', [
        'uses' => 'API\UserController@updateUserfromApp',
        'as' => 'api.user.updateUserfromApp'
    ]);

    Route::post('delete-user', [// delete all selected users
        'uses' => 'API\UserController@deleteUser',
        'as' => 'api.user.deleteUser'
    ])->middleware('checkUserRole');

    Route::post('register-doctor', [// register doctor/dentist
        'uses' => 'API\UserController@registerDoctor',
        'as' => 'api.user.registerDocotr'
    ])->middleware('checkUserRole');

    Route::post('add-user-meta', [// add/update doctor meta
        'uses' => 'API\UserController@addUserMeta',
        'as' => 'api.user.addUserMeta'
    ])->middleware('checkUserRole');

    Route::post('get-user-meta', [// get user meta
        'uses' => 'API\UserController@getUserMeta',
        'as' => 'api.user.getUserMeta'
    ])->middleware('checkUserRole');

    Route::post('update-profile', [//Update User Profile
        'uses' => 'API\IndexController@updateProfile',
        'as' => 'api.index.update_profile'
    ]);

    Route::post('update-profile-pic', [//Update User Profile Picture
        'uses' => 'API\IndexController@updateUserProfilePic',
        'as' => 'api.index.update_profile_pic',
    ]);

    Route::post('lookup-data', [
        'uses' => 'API\IndexController@lookupData',
        'as' => 'api.index.lookup',
    ]);

    Route::post('update-module', [// update module
        'uses' => 'API\ModuleController@updateModule',
        'as' => 'api.module.updateModule'
    ]);

    Route::post('update-fcm', [
        'uses' => 'API\IndexController@updateFCM',
        'as' => 'api.index.fcm_token',
    ]);

    Route::post('get-policies', [// generic policies (filtration, search, sorter, pagination)
        'uses' => 'API\PolicyController@getPolicies',
        'as' => 'api.policy.getPolicies'
    ])->middleware('checkUserRole');

    Route::post('policy/approval-process', [
        'uses' => 'API\PolicyController@policyApprovalProcess',
        'as' => 'api.policy.save_approval_process',
    ]);

    Route::post('get-careservices', [// get careservices
        'uses' => 'API\CareServiceController@getCareServices',
        'as' => 'api.careservice.getCareServices'
    ]);

    Route::post('change-careservice-status', [// change careservice status
        'uses' => 'API\CareServiceController@changeCareServiceStatus',
        'as' => 'api.careservice.changeCareServiceStatus'
    ]);

    Route::post('search-fnf', [
        'uses' => 'API\FNFController@search',
        'as' => 'api.fnf.search_user',
    ]);

    Route::post('user/claim-profile', [
        'uses' => 'API\UserController@requestForViralProfile',
        'as' => 'api.user.request_for_viral_profile',
    ]);

    Route::post('health-care', [
        'uses' => 'API\HealthCareController@store',
        'as' => 'api.healthcare.store',
    ]);

    Route::post('verify/email', [
        'uses' => 'API\UserController@sendVerificationLinkEmail',
        'as' => 'api.user.verify_email_address'
    ]);

    // PUT requests

    Route::put('policy/approval-process/{id}', [
        'uses' => 'API\PolicyController@updatePolicyApprovalProcess',
        'as' => 'api.policy.update_approval_process',
    ]);

    Route::put('fnf/change-permission/{id}', [
        'uses' => 'API\FNFController@fnfChangePermission',
        'as' => 'api.fnf.change_permissions',
    ]);

    Route::put('user/claim-profile/{id}', [
        'uses' => 'API\UserController@respondViralProfile',
        'as' => 'api.user.respond_viral_profile',
    ]);

    // DELETE requests

    Route::delete('medical-record-bulk-delete', [//Bulk deletion
        'uses' => 'API\MedicalRecordController@bulkDelete',
        'as' => 'medical-record.bluk_delete'
    ]);
});
