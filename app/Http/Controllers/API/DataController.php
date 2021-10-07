<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Grade;
use Illuminate\Validation\Rule;
use App\User;
//
use Session;
use Excel;
use File;
use PhoneValidator;

class DataController extends Controller {

    use \App\Traits\WebServicesDoc;

    public function import(Request $request) {
        $loggedin_user = \Auth::user();
        $user_data = \App\Models\Organization::userOrganization($loggedin_user);
        if (!empty($user_data)) {
            $organization_id = $user_data->organization_user[0]->organization_id;
            if ($request->hasFile('file')) {
                $extension = File::extension($request->file->getClientOriginalName());
                if ($extension == "xlsx" || $extension == "xls" || $extension == "csv") {
                    $path = $request->file->getRealPath();
                    $data = Excel::selectSheets('Emp_Info')->load($path, function($reader) {
                                
                            })
                            ->get();
                    //echo "<pre>";print_r($data->toArray());exit;
                    $failed = [];
                    $failed_total = 0;

                    $imported = [];
                    $imported_total = 0;

                    $success = [];

                    if (!empty($data) && $data->count()) {
                        foreach ($data as $key => $value) {
                            //echo "<pre>";print_r($value);exit;
                            unset($value[0]);
                            $errorMessage = '';
                            $reqFields = ['employee_code', 'cnic', 'email', 'opd_limit', 'employee_name', 'contact_number'];
                            foreach ($value as $key => $val) {
                                if (in_array($key, $reqFields) && empty($val)) {
                                    $caption = str_replace('_', ' ', $key);
                                    $caption = ucfirst($caption);
                                    $errorMessage .= "'{$caption}',";
                                }
                            }
                            if (!empty($errorMessage)) {
                                $errorMessage = rtrim($errorMessage, ',');
                                $errorMessageArr = explode(',', $errorMessage);
                                if(count($errorMessageArr) === count($reqFields)){
                                    continue;
                                }
                                $value = $value->toArray();
                                $value['error'] = "Following fields are required: \n {$errorMessage}";
                                array_push($failed, $value);
                                $failed_total++;
                                continue;
                            }
                            
                            if (filter_var($value->email, FILTER_VALIDATE_EMAIL)) {
                                //echo "<pre>";print_r($value);exit;

                                $cnic = $value->cnic;
                                $cnic = str_replace('-', '', $cnic);
                                if (strlen(intval($cnic)) === 13) {
                                    $contact_number = $value->contact_number;
                                    //print_r($contact_number)
                                    //$phone_validator_obj = new PhoneValidator($contact_number);
                                    //$contact_number = $phone_validator_obj->validate();
                                    //print_r($contact_number);exit;
                                    if ($contact_number != -1) {
                                        $emp_code = $value->employee_code;
                                        $email = $value->email;
                                        $opd_limit = floatval($value->opd_limit);
                                        if (($opd_limit < 1000 || $opd_limit > 10000000)) {
                                            $value = $value->toArray();
                                            $value['error'] = "OPD Limit should be in range 1000 - 10000000";
                                            array_push($failed, $value);
                                            $failed_total++;
                                            continue;
                                        }
                                        $gross_salary = !empty($value->gross_salary)? floatval($value->gross_salary): null;
                                        if (!empty($gross_salary)) {
                                            if (($gross_salary < 1000 || $gross_salary > 10000000)) {
                                                $value = $value->toArray();
                                                $value['error'] = "Gross salary should be in range 1000 - 10000000";
                                                array_push($failed, $value);
                                                $failed_total++;
                                                continue;
                                            }
                                        }
                                        $basic_salary = !empty($value->basic_salary)? floatval($value->basic_salary): null;
                                        if (!empty($basic_salary)) {
                                            if (($basic_salary < 1000 || $basic_salary > 10000000)) {
                                                $value = $value->toArray();
                                                $value['error'] = "Basic salary should be in range 1000 - 10000000";
                                                array_push($failed, $value);
                                                $failed_total++;
                                                continue;
                                            }
                                        }

                                        $name_arr = explode(" ", $value->employee_name);
                                        if (count($name_arr) > 1) {
                                            $first_name = $name_arr[0];
                                            array_shift($name_arr);
                                            $last_name = implode(" ", $name_arr);
                                        } else {
                                            $first_name = $name_arr[0];
                                            $last_name = null;
                                        }

                                        $user_obj = \App\User::where('cnic', $cnic)
                                                ->orWhere('email', $email)
                                                ->orWhere('contact_number', $contact_number)
                                                ->first();
                                        $org_user_obj = \App\Models\OrganizationUser::where([
                                                    'organization_id' => $organization_id,
                                                    'employee_code' => $emp_code
                                                ])
                                                ->first();

                                        if (empty($user_obj) && empty($org_user_obj)) {
                                            $grade = !empty($value->grade) ? $value->grade : null;
                                            $grade_obj = \App\Models\Grade::where([
                                                        'organization_id' => $organization_id,
                                                        'title' => $grade
                                                    ])
                                                    ->first();

                                            if (!empty($grade_obj)) {
                                                $gender = "Other";
                                                if (!empty($value->gender)) {
                                                    if (!strcasecmp($value->gender, "Female")) {
                                                        $gender = "Female";
                                                    } elseif (!strcasecmp($value->gender, "Male")) {
                                                        $gender = "Male";
                                                    }
                                                } else {
                                                    $cnicLastDigit = substr($cnic, -1);
                                                    $gender = ($cnicLastDigit % 2 == 0) ? 'Female' : 'Male';
                                                }

                                                $designation = !empty($value->designation) ? $value->designation : null;
                                                //$department = !empty($value->department)? $value->department: null;
                                                $date_of_joining = !empty($value->date_of_joining) ? $value->date_of_joining : null;
                                                if (!empty($date_of_joining)) {
                                                    $date_of_joiningUx = strtotime($date_of_joining);
                                                    $date_of_joining = date('Y-m-d', $date_of_joiningUx);
                                                }
                                                $date_of_confirmation = !empty($value->date_of_confirmation) ? $value->date_of_confirmation : null;
                                                if (!empty($date_of_confirmation)) {
                                                    $date_of_confirmationUx = strtotime($date_of_confirmation);
                                                    $date_of_confirmation = date('Y-m-d', $date_of_confirmationUx);
                                                }
                                                $randNumber = mt_rand();
                                                $resetToken = User::getToken($email);
                                                $verification_code = route('user.verified_email_address', ['token' => $resetToken]);
                                                $user = [
                                                    'first_name' => $first_name,
                                                    'last_name' => $last_name,
                                                    'email' => $email,
                                                    'cnic' => $cnic,
                                                    'verification_code' => $verification_code,
                                                    'password' => bcrypt($randNumber),
                                                    'activation_code' => $randNumber,
                                                    'registration_source' => 'import',
                                                    'gender' => $gender,
                                                    'contact_number' => $contact_number,
                                                    'created_at' => now(),
                                                    'updated_at' => now(),
                                                    'created_by' => $loggedin_user->id
                                                ];
                                                $newUser = \App\User::create($user);

                                                $org_user = [
                                                    'user_id' => $newUser->id,
                                                    'organization_id' => $organization_id,
                                                    'employee_code' => $emp_code,
                                                    'designation' => $designation,
                                                    //'department'        => $department,
                                                    'date_joining' => $date_of_joining,
                                                    'date_confirmation' => $date_of_confirmation,
                                                    'grade_id' => $grade_obj->id,
                                                    'basic_salary' => $basic_salary,
                                                    'gross_salary' => $gross_salary,
                                                    'opd_limit'     => $opd_limit,
                                                    'status' => config('app.hospitallCodes')['Approved'],
                                                    'is_default' => 'Y',
                                                    'created_at' => now(),
                                                    'updated_at' => now(),
                                                    'created_by' => $loggedin_user->id
                                                ];
                                                $new_orgUser = \App\Models\OrganizationUser::create($org_user);

                                                if ($newUser && $new_orgUser) {
                                                    event(new \Illuminate\Auth\Events\Registered($newUser));
                                                    dispatch(new \App\Jobs\SendVerificationEmail($newUser));
                                                }
                                                $value = $value->toArray();
                                                array_push($imported, $value);
                                                $imported_total++;

                                                $temp['emp_code'] = $emp_code;
                                                $temp['cnic'] = $cnic;
                                                $temp['email'] = $email;
                                                //$temp['password'] = $cnic;
                                                array_push($success, $temp);
                                            } else {
                                                $value = $value->toArray();
                                                $value['error'] = "Grade doesn't exist";
                                                array_push($failed, $value);
                                                $failed_total++;
                                                //print_r("grade not exist");exit; 
                                            }// grade not exist
                                        } else {
                                            //print_r("expression2");exit;
                                            $value = $value->toArray();
                                            if (!empty($user_obj)) {
                                                if ($cnic == $user_obj->cnic) {
                                                    $value['error'] = "CNIC not unique";
                                                } elseif ($email == $user_obj->email) {
                                                    $value['error'] = "email not unique";
                                                } elseif ($contact_number == $user_obj->contact_number) {
                                                    $value['error'] = "contact# not unique";
                                                }
                                                //$value['error'] = "Either CNIC, email or contact# not unique";
                                            } else {
                                                $value['error'] = "Employee Code not unique";
                                            }
                                            array_push($failed, $value);
                                            $failed_total++;
                                            //print_r("cnic or email not unique or both");exit;
                                        }// cnic, email, emp_code not unique
                                    } else {
                                        $value = $value->toArray();
                                        $value['error'] = "Invalid Phone Number";
                                        array_push($failed, $value);
                                        $failed_total++;
                                        //print_r("Phone Number not valid");exit;
                                    }
                                } else {
                                    $value = $value->toArray();
                                    $value['error'] = "Invalid CNIC";
                                    array_push($failed, $value);
                                    $failed_total++;
                                    //print_r("cnic not valid");exit;
                                }// cnic not valid
                            } else {
                                $value = $value->toArray();
                                $value['error'] = "Invalid Email";
                                array_push($failed, $value);
                                $failed_total++;
                                //print_r("invalid email format");exit;
                            }// email not valid
                        } // foreach ends
                        //Download xlsx
                        $failedUsersArray = [];
                        $failedUsersArray[] = ['Employee Code', 'Employee Name', 'CNIC', 'Designation', 'Grade', 'Joining Date', 'Confirmation Date', 'Email', 'Contact Number', 'Basic Salary', 'Gross Salary', 'OPD Limit', 'Error'];
                        foreach ($failed as $value) {
                            $failedUsersArray[] = $value;
                        }

                        $successUsersArray = [];
                        $successUsersArray[] = ['Employee Code', 'CNIC', 'Email'];
                        foreach ($success as $value) {
                            $successUsersArray[] = $value;
                        }

                        $result = Excel::create('organization_users', function($excel) use ($failedUsersArray, $successUsersArray) {
                                    $excel->setTitle('Users Import Results');
                                    $excel->setCreator('Laravel')->setCompany('HospitAll');
                                    $excel->setDescription('Failed Users Rows');
                                    $excel->sheet('Failed', function($sheet) use ($failedUsersArray) {
                                        $sheet->fromArray($failedUsersArray, null, 'A1', false, false);
                                    });

                                    //
                                    $excel->sheet('Success', function($sheet) use ($successUsersArray) {
                                        $sheet->fromArray($successUsersArray, null, 'A1', false, false);
                                    });
                                    //
                                })->download('xlsx', ['Access-Control-Allow-Origin' => '*']);
                        //Download xlsx
                        //return responseBuilder()->success('import results', $result);
                        //print_r($result);exit;
                    }
                } else {
                    return responseBuilder()->error('Only csv, xlsx, xls allowed', 400, false);
                }
            } else {
                return responseBuilder()->error('File not found', 400, false);
            }
        } else {
            return responseBuilder()->error('Access denied', 400, false);
        }
    }

    public function importDependents(Request $request) {
        /* $cell = new PhoneValidator("03211-1234567");
          echo $cell->validate();exit; */
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);
        $loggedin_user = \Auth::user();
        $user_data = \App\Models\Organization::userOrganization($loggedin_user);
        if (empty($user_data)) {
            return responseBuilder()->error('Access denied', 400, false);
        }
        $organization_id = $user_data->organization_user[0]->organization_id;

        $path = $request->file->getRealPath();
        $data = Excel::selectSheets('Dependent_Info')->load($path, function($reader) {
                    
                })
                ->get();
        $data = $data->toArray();

        $failed = [];
        $failed_total = 0;

        $imported = [];
        $success = [];
        $imported_total = 0;
        $relationships = \App\Models\Relationship::pluck('id', 'name');
        //dump($relationships);die();
        if (empty($data)) {
            return responseBuilder()->error('An error occured while parsing a file', 400, false);
        }
        foreach ($data as $key => $value) {
            unset($value[0]);
            $errorMessage = '';
            $reqFields = ['parent_cnic', 'dependant_cnic', 'dependent_name', 'relation'];
            foreach ($value as $key => $val) {
                if (in_array($key, $reqFields) && empty($val)) {
                    $caption = str_replace('_', ' ', $key);
                    $caption = ucfirst($caption);
                    $errorMessage .= "'{$caption}',";
                }
            }
            if (!empty($errorMessage)) {
                $errorMessage = rtrim($errorMessage, ',');
                $errorMessageArr = explode(',', $errorMessage);
                if(count($errorMessageArr) === count($reqFields)){
                    continue;
                }
                $value['error'] = "Following fields are required: \n {$errorMessage}";
                array_push($failed, $value);
                $failed_total++;
                continue;
            }
            
            $parent_cnic = $value['parent_cnic'];
            $parent_cnic = str_replace('-', '', $parent_cnic);
            if (strlen(intval($parent_cnic)) !== 13) {
                $value['error'] = "Invalid Parent CNIC";
                array_push($failed, $value);
                $failed_total++;
                continue;
            }
            $dependant_cnic = $value['dependant_cnic'];
            $dependant_cnic = str_replace('-', '', $dependant_cnic);
            if (strlen(intval($dependant_cnic)) !== 13) {
                $value['error'] = "Invalid Dependent CNIC";
                array_push($failed, $value);
                $failed_total++;
                continue;
            }
            $relation = $value['relation'];
            $name_arr = explode(" ", $value['dependent_name']);
            if (count($name_arr) > 1) {
                $first_name = $name_arr[0];
                array_shift($name_arr);
                $last_name = implode(" ", $name_arr);
            } else {
                $first_name = $name_arr[0];
                $last_name = null;
            }

            $cnicCount = User::where(['cnic' => $dependant_cnic])->count();
            //print_r("expression");exit;
            if ($cnicCount > 0) { // Founded:::::(not viral)
                //print_r("if");exit;
                $cnicLastDigit = substr($dependant_cnic, -1);
                $gender = ($cnicLastDigit % 2 == 0) ? 'Female' : 'Male';
                $dependantUserData = User::select(['id'])->where(['cnic' => $dependant_cnic])->first();
                $parentUserData = User::select(['id'])->where(['cnic' => $parent_cnic])->first();
                $familyTreeData['shared_profile'] = 'Y';
                $familyTreeData['read_medical_record'] = 'Y';
                $familyTreeData['write_medical_record'] = 'Y';
                $familyTreeData['assc_relationship_id'] = $relationships[$value['relation']];
                $familyTreeData['parent_user_id'] = $parentUserData['id'];
                $familyTreeData['associate_user_id'] = $fnfConstraints['associate_user_id'] = $dependantUserData->id;
                $familyTreeData['relationship_id'] = \App\Models\Relationship::getAsscRelaitonshipId($familyTreeData['assc_relationship_id'], $gender);
                //dump($familyTreeData);die();
                $fnfData = \App\Models\FamilyTree::updateOrcreate($fnfConstraints, $familyTreeData);
            } else { // Save user as viral profile
                //print_r("else");exit;
                $parentUserData = User::where(['cnic' => $parent_cnic])->first();
                if (empty($parentUserData)) {
                    $value['error'] = "Parent CNIC Not found";
                    array_push($failed, $value);
                    $failed_total++;
                    //print_r("expression");exit;
                    continue;
                }
                //print_r("expression");exit;
                $newCnic = $parentUserData->id . '_' . $dependant_cnic;
                $viralUserData = User::where(['cnic' => $newCnic])->count();
                if ($viralUserData > 0) {
                    $value['error'] = "{$dependant_cnic} is already added as in fnf list of {$parent_cnic}/{$parentUserData->first_name} {{$parentUserData->last_name}}";
                    array_push($failed, $value);
                    $failed_total++;
                    continue;
                }
                //echo "<pre>";print_r('$failed');exit;
                //registered user as viral
                $post['cnic'] = $dependant_cnic;
                $post['password'] = bcrypt($post['cnic']);
                $post['first_name'] = $value['dependent_name'];
                $post['email'] = $value['email'];
                $post['contact_number'] = $value['contact_number'];
                $post['registration_source'] = 'viralAccountOf_' . $parentUserData->id;
                $cnicLastDigit = substr($post['cnic'], -1);
                $post['gender'] = ($cnicLastDigit % 2 == 0) ? 'Female' : 'Male';
                $post['is_viral'] = 'Y';
                $associateUser = User::create($post);
                $familyTreeData['assc_relationship_id'] = $relationships[$value['relation']];
                $familyTreeData['shared_profile'] = ($post['shared_profile']) ?? 'Y';
                $familyTreeData['read_medical_record'] = ($post['read_medical_record']) ?? 'Y';
                $familyTreeData['write_medical_record'] = ($post['write_medical_record']) ?? 'Y';
                $familyTreeData['status'] = 'Approved';
                $familyTreeData['is_viral'] = 'Y';
                $familyTreeData['relationship_id'] = \App\Models\Relationship::getAsscRelaitonshipId($familyTreeData['assc_relationship_id'], $post['gender']);
                //$msg = 'New friend and family saved successfully';
                $familyTreeData['parent_user_id'] = $parentUserData['id'];
                $familyTreeData['associate_user_id'] = $fnfConstraints['associate_user_id'] = $associateUser->id;
                $fnfData = \App\Models\FamilyTree::updateOrcreate($fnfConstraints, $familyTreeData);
            }
            if(!empty($fnfData)){
                $temp = ['parent_cnic' => $parent_cnic, 'dependent_cnic' => $dependant_cnic];
                array_push($success, $temp);
            }
        } // foreach ends
    
        $failedUsersArray = [];
       
                        $failedUsersArray[] = ['Parent CNIC', 'Dependent CNIC', 'Dependent Name', 'Relation', 'Email', 'Contact Number', 'Error'];
                        foreach ($failed as $value) {
                            $failedUsersArray[] = $value;
                        }

                        $successUsersArray = [];
                        $successUsersArray[] = ['Parent CNIC', 'Dependent CNIC'];
                        foreach ($success as $value) {
                            $successUsersArray[] = $value;
                        }

                        $result = Excel::create('organization_users_dependents', function($excel) use ($failedUsersArray, $successUsersArray) {
                                    $excel->setTitle('Users Dependent Import Results');
                                    $excel->setCreator('Laravel')->setCompany('HospitAll');
                                    $excel->setDescription('Failed Users Rows');
                                    $excel->sheet('Failed', function($sheet) use ($failedUsersArray) {
                                        $sheet->fromArray($failedUsersArray, null, 'A1', false, false);
                                    });

                                    //
                                    $excel->sheet('Success', function($sheet) use ($successUsersArray) {
                                        $sheet->fromArray($successUsersArray, null, 'A1', false, false);
                                    });
                                    //
                                })->download('xlsx', ['Access-Control-Allow-Origin' => '*']);
        if ($imported_total <= $failed_total) {
            return responseBuilder()->success("failed rows", $failed, false);
        } else {
            return responseBuilder()->success("imported rows", $imported, false);
        }
    }
}
