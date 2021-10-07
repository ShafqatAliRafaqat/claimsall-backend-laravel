<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization;
use App\Models\OrganizationUser;
use App\Models\Organization as OrganizationModel;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller {
    use \App\Traits\WebServicesDoc;

    public function getOrganizations(Request $request)
    {
        $post = $request->all();

        //DB::connection()->enableQueryLog();
        $orgs = OrganizationModel::query();
        $orgs = $orgs->where(function ($query) use ($post) { // put bracket around multiple where clauses
                    if (!empty($post['searchFilters'])) {
                        foreach ($post['searchFilters'] as $key => $value) {
                            if (!strcasecmp($key, 'organization_type')) {
                                $organization_typeQueryWhere = function ($organization_typeQuery) use ($value) {
                                    $organization_typeQuery->orWhere('name', 'LIKE', '%' . $value . '%');
                                };
                                $query = $query->with(["organization_type" => $organization_typeQueryWhere])->whereHas('organization_type', $organization_typeQueryWhere);
                            } else {
                                $query = $query->orWhere($key, 'LIKE', '%' . $value . '%');
                            }
                        }
                    }
                });

        if (!empty($post['filters'])) {
            foreach ($post['filters'] as $key => $value) {
                if (is_array($value)) {
                    if (count($value) > 0) {
                        $orgs = $orgs->whereIn($key, $value);
                    }
                } else {
                    $orgs = $orgs->where($key, $value);
                }
            }
        }

        $orgs = $orgs->where('is_deleted', '0');
        if (empty($post['sorter'])) {
            $orgs = $orgs->orderBy("created_at", "DESC");
        } else {
            $sorter = $post['sorter'];
            $orgs = $orgs->orderBy($sorter['field'], $sorter['order'] == 'descend' ? 'DESC' : 'ASC');
        }

        if (isset($post['report'])) {
            $report = $post['report'];
            if (isset($report['start_date']) && isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $end_date = $report['end_date']." 23:59:59";
                $orgs = $orgs->whereBetween('created_at', array($start_date, $end_date));
            }
            elseif (isset($report['start_date']) && !isset($report['end_date'])) {
                $start_date = $report['start_date']." 00:00:00";
                $orgs = $orgs->where('created_at', '>=', $start_date);
            }
            elseif (!isset($report['start_date']) && isset($report['end_date'])) {
                $end_date = $report['end_date']." 23:59:59";
                $orgs = $orgs->where('created_at', '<=', $end_date);
            }
            $orgs = $orgs->get();
        }
        else {
            $orgs = $orgs->paginate(10);
        }
        //return DB::getQueryLog();

        foreach ($orgs as $key => $value) {
            $value['key'] = $value->id;
            if (!empty($value->additional_address)) {
                $address_arr = unserialize($value->additional_address);
                $arr = [];
                array_push($arr, $value->address);
                foreach ($address_arr as $key => $address_val) {
                    array_push($arr, $address_val);
                }
                $value['address'] = $arr;
                unset($value['additional_address']);
            } else {
                $default_address = [];
                array_push($default_address, $value['address']);
                $value['address'] = $default_address;
                if (empty($value['address'][0])) {
                    unset($value['address']);
                }
            }

            $org_users = \App\Models\OrganizationUser::where('organization_id', '=', $value->id)
                                                       ->where('status', 'Approved')
                                                       ->get();
            $org_users_count = $org_users->count();
            $value['employee'] = $org_users_count;

            $orgtype = \App\Models\OrganizationType::select(['name'])
                                                        ->where('id', $value->organization_type_id)
                                                        ->first();

            $value['organization_type_name'] = $orgtype->name;
            unset($value['organization_type']);

            if(!empty($value['latitude'])) {
                $value['latlng'] = $value['latitude'].', '.$value['longitude'];
                unset($value['latitude']);
                unset($value['longitude']);
            }
        }

        $msg = 'Found following organizations';
        if (!$orgs) {
            $orgs = [];
            $msg = 'No organizations found';
        }

        $response = responseBuilder()->success($msg, $orgs, false);
        $this->urlComponents('List of Organizations (Search| Filtration| Sorter| pagination)', $response, 'Organization_Management');
        return $response;
    }

    public function show($id)
    {
        $organizationData = OrganizationModel::where('is_deleted', '0')
                                                ->with('city.state.country')
                                                ->findOrFail($id);

        if(!empty($organizationData['latitude'])) {
            $organizationData['latlng'] = $organizationData['latitude'].', '.$organizationData['longitude'];
            unset($organizationData['latitude']);
            unset($organizationData['longitude']);
        }

        $organizationData['key'] = $organizationData->id;
        if (!empty($organizationData->additional_address)) {
            $address_arr = unserialize($organizationData->additional_address);
            $arr = [];
            array_push($arr, $organizationData->address);
            foreach ($address_arr as $key => $address_val) {
                array_push($arr, $address_val);
            }
            $organizationData['address'] = $arr;
            unset($organizationData['additional_address']);
        }
        else {
            $default_address = [];
            array_push($default_address, $organizationData['address']);
            $organizationData['address'] = $default_address;
            if (empty($organizationData['address'][0])) {
                unset($organizationData['address']);
            }
            if (empty($organizationData['additional_address'])) {
                unset($organizationData['additional_address']);
            }
        }
        $org_users = \App\Models\OrganizationUser::where('organization_id', '=', $organizationData->id)->get();
        $org_users_count = $org_users->count();
        $organizationData['employee'] = $org_users_count;

        $orgtype = \App\Models\OrganizationType::select(['name'])
                                                    ->where('id', $organizationData->organization_type_id)
                                                    ->first();
        $organizationData['organization_type'] = $orgtype->name;
        if (empty($organizationData['city_id'])) {
            unset($organizationData['city_id']);
            unset($organizationData['city']);
        }

        //return responseBuilder()->success('Organization details here', $organizationData->toArray());

        $response = responseBuilder()->success('Organization details here', $organizationData->toArray());
        $this->urlComponents('Detail of An Organization', $response, 'Organization_Management');
        return $response;
    }

    public function store(Organization $request)
    {
        $user = \Auth::user();
        $post = $request->all();
        $business_pts = !empty($post['latlng'])? $post['latlng']: null;
        if(!empty($business_pts)) {
            $business_pts = str_replace(' ', '', $business_pts);
            $business_pts_arr = explode(',', $business_pts);
            $post['latitude'] = $business_pts_arr[0];
            $post['longitude'] = $business_pts_arr[1];
        }

        $post['created_by'] = $user->id;
        $post['status']= 'Approved';
        if (!empty($post['address'])) {
            $post['address'] = array_values(array_filter($post['address']));
            if(!empty($post['address'])) {
                $address = $post['address'][0];
                unset($post['address'][0]);
                if (!empty($post['address'])) {
                    $post['additional_address'] = serialize($post['address']);
                }
                $post['address'] = $address;
            }
            else {
                unset($post['address']);
            }
        }

        if (!empty($post['short_code'])) {
            $post['short_code'] = strtoupper($post['short_code']);
        }

        $org_obj = OrganizationModel::where([
                                        'name'                  => $post['name'],
                                        'organization_type_id'  => $post['organization_type_id']
                                      ])
                                      ->first();

        if (!empty($org_obj)) {
            return responseBuilder()->error('Record should be unique', 400);
        }

        if (OrganizationModel::create($post)) {
            //return responseBuilder()->success('Request processed successfully');
            $response = responseBuilder()->success('Record has been added Successfully');
            $this->urlComponents('Add An Organization', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while saving', 400);
    }

    public function update(Organization $request, $id)
    {
        $user = \Auth::user();
        $organizationData = OrganizationModel::where('is_deleted', '0')
                                                ->with('city.state.country')
                                                ->findOrFail($id);
        $post = $request->all();
        $business_pts = !empty($post['latlng'])? $post['latlng']: null;
        if(!empty($business_pts)) {
            $business_pts = str_replace(' ', '', $business_pts);
            $business_pts_arr = explode(',', $business_pts);
            $post['latitude'] = $business_pts_arr[0];
            $post['longitude'] = $business_pts_arr[1];
        }
        $post['updated_by'] = $user->id;
        /**/
        if (!empty($post['address'])) {
            $post['address'] = array_values(array_filter($post['address']));
            if(!empty($post['address'])) {
                $address = $post['address'][0];
                unset($post['address'][0]);
                if (!empty($post['address'])) {
                    $post['additional_address'] = serialize($post['address']);
                }
                else {
                    $post['additional_address'] = NULL;
                }
                $post['address'] = $address;
            }
            else {
                unset($post['address']);
            }
        }
       
        if (!empty($post['short_code'])) {
            $post['short_code'] = strtoupper($post['short_code']);
        }
        
        if ($organizationData->update($post)) {
            $response = responseBuilder()->success('Record has been Updated Successfully');
            $this->urlComponents('Update An Organization', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while saving', 400);
    }

    public function destroy($id)
    {
        $user = \Auth::user();
        $orgData = OrganizationModel::where('is_deleted', '0')->findOrFail($id);

        if (\App\Models\OrganizationUser::where('organization_id', $orgData->id)->count() > 0) {
            return responseBuilder()->error('You cannot delete selected record because it has some data associated with', 400);
        }

        //\App\Models\OrganizationUser::where('organization_id', $orgData->id)->delete();
        $orgData->is_deleted = '1';
        $orgData->deleted_by = $user->id;
        $orgData->save();
        if ($orgData->delete()) {
            $response = responseBuilder()->success('Selected Record(s) have been deleted Successfully');
            $this->urlComponents('Delete An Organization', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('Something went wrong while deleting', 400);
    }

    public function deleteOrganization(Request $request)
    {
        $post = $request->all();
        $user = \Auth::user();
        $org_Ids = (is_array($post['organization_ids']) ? $post['organization_ids'] : [$post['organization_ids']]);
        $organizations = [];
        foreach ($org_Ids as $org_id) {
            $organizationData = OrganizationModel::where('is_deleted', '0')->findOrFail($org_id);
            \App\Models\OrganizationUser::where('organization_id', $organizationData->id)->delete();
            $organizationData->is_deleted = '1';
            $organizationData->deleted_by = $user->id;
            $organizationData->save();
            $organizationData->delete();
        }
        //return responseBuilder()->success("deleted selected Records");
        $response = responseBuilder()->success("Selected Record(s) have been deleted Successfully");
        $this->urlComponents('Delete Single/Multiple Organization(s)', $response, 'Organization_Management');
        return $response;
    }

    public function approvedOrganization()
    {
        $organizations = OrganizationModel::select(['id', 'name'])
                                                ->where(['status' => 'Approved', 'is_deleted' => '0'])
                                                ->get();
        if(!empty($organizations)) {
            $response =  responseBuilder()->success('Here is list of organizations', $organizations, false);
            $this->urlComponents('List of Approved Organizations(id/name)', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('Sorry we did not found any organization', 404, false);
    }

    public function getAdministrativeRoles(Request $request)
    {
        $get = $request->all();
        $organization_id = !empty($get['organization_id'])? $get['organization_id']: null;
        $roles = \App\Models\Role::select(['id', 'title AS name', 'code'])
                                    ->where('is_deleted', '0')
                                    ->where('organization_id', $organization_id)
                                    ->orWhere('code', config('app.hospitallCodes')['orgAdmin'])
                                    ->orderBy('created_at', 'DESC')
                                    ->get();

        $response = responseBuilder()->success("All administrative roles", $roles, false);
        $this->urlComponents('All Administrative Roles of an Organization', $response, 'Organization_Management');
        return $response;
    }

    public function addUserToOrganization(Request $request)
    {
        $post = $request->all();
        $organization = OrganizationModel::where(['is_deleted' => '0'])->findOrFail($post['organization_id']);
        $users = (is_array($post['users']) ? $post['users'] : [$post['users']]);
        $organizationUsers = [];
        foreach ($users as $user) {
            $time = now();
            if (OrganizationUser::where(['user_id' => $user['user_id'], 'organization_id' => $organization->id, 'status' => 'Approved'])->count() <= 0) {
                $organizationUsers[] = [
                    'user_id' => $user['user_id'],
                    'organization_id' => $organization->id,
                    'designation' => $user['designation'],
                    'grade_id' => $user['grade_id'],
                    'status' => 'Approved',
                    'is_default' => !empty($user['is_default'])? $user['is_default']: 'N',
                    'created_at' => $time,
                    'updated_at' => $time
                ];
            }
        }
        if (OrganizationUser::insert($organizationUsers)) {
            //return responseBuilder()->success("Users added in Organization[{$organization->name}]");
            $response = responseBuilder()->success("Record(s) added in Organization[{$organization->name}]");
            $this->urlComponents('Add Multiple Users in An Organization', $response, 'Organization_Management');
            return $response;
        }
        return responseBuilder()->error('An error occured while assigning users');
    }

    public function index()
    {
        $orgs = OrganizationModel::select(['id', 'name', 'description', 'organization_type_id', 'email', 'address', 'additional_address', 'contact_number', 'ntn_number', 'website', 'latitude', 'longitude'])
                                    ->where('is_deleted', '0')
                                    ->orderBy('created_at', 'DESC')
                                    ->with('city')
                                    ->paginate(10);

        foreach ($orgs as $key => $value) {
            $value['key'] = $value->id;
            if (!empty($value->additional_address)) {
                $address_arr = unserialize($value->additional_address);
                $arr = [];
                array_push($arr, $value->address);
                foreach ($address_arr as $key => $address_val) {
                    array_push($arr, $address_val);
                }
                $value['address'] = $arr;
                unset($value['additional_address']);
            } else {
                $default_address = [];
                array_push($default_address, $value['address']);
                $value['address'] = $default_address;
            }

            $org_users = \App\Models\OrganizationUser::where('organization_id', '=', $value->id)->get();
            $org_users_count = $org_users->count();
            $value['employee'] = $org_users_count;

            $orgtype = \App\Models\OrganizationType::select(['name'])
                    ->where('id', $value->organization_type_id)
                    ->first();
            $value['organization_type'] = $orgtype->name;
            unset($value['city']);
        }

        $msg = 'Found following organizations';
        if (!$orgs) {
            $orgs = [];
            $msg = 'No organizations found';
        }

        $response = responseBuilder()->success($msg, $orgs, false);
        $this->urlComponents('List of Organizations', $response, 'Organization_Management');
        return $response;
    }
}
