<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

//use Reliese\Database\Eloquent\Model as Eloquent;

class Module extends Model
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'organization_level',
		'view_route',
		'claim_action',
		'title',
		'route',
		'discription',
		'created_by',
		'updated_by',
		'is_delelted',
		'deleted_by',
            'parent_id',
            'url', 'alias', 'menu_order', 'selected_menu', 'menu_order'
	];

	public function roles()
	{
		return $this->belongsToMany(\App\Models\Role::class)
					->withPivot('create', 'edit', 'view', 'report', 'approved', 'reject');
	}

    /*public function manageRoleModule($request) // add or update role with modules list
	{
		$user = \Auth::user();
		$org_data = \App\Models\Organization::userOrganization($user, false);
		if (!empty($org_data)) {
            $user_organization_id = $org_data->organization_user[0]->organization_id;
            $post = $request->all();
			if (empty($post['role']['title'])) {
				return ['status' => false, 'code' => 401, 'message' => 'title for role must be provided'];
			}

			$title = $post['role']['title'];
			$code = strtolower(str_replace(' ', '', $title)); // remove spaces & change to lowercase as well

			//////////////////////
			$role_obj = \App\Models\Role::where([
												'organization_id' => $user_organization_id,
												'title'			  => $title
											])
											->first();
			//echo "<pre>";print_r($role_obj);exit;
			if (!empty($role_obj) && $role_obj->id != $post['role']['id']) {
				return ['status' => false, 'code' => 401, 'message' => 'title for role must be unique'];
			}
			//////////////////////
			
			$description = !empty($post['role']['description'])? $post['role']['description']: null;
			$flag = 'update';
			if (!empty($post['role']['id']) && isset($post['role']['id'])) {
				$role_id = $post['role']['id'];
				$role = \App\Models\Role::where(['is_deleted' => '0', 'id' => $role_id])->first();
				if (empty($role)) {
					return ['status' => false, 'code' => 401, 'message' => 'Role(with Modules list) you want to edit does not exist'];
				}
				$role_obj['title'] = $title;
				$role_obj['description'] = $description;
				$role_obj['code'] = $code;
				$role_obj['updated_by'] = $user->id;
				$role->update($role_obj);
				\App\Models\ModuleRole::where('role_id', $role->id)->delete();
			}
			else {
				$flag = 'add';
				$role_obj = [
					'title'				=> $title,
					'description' 		=> $description,
					'code' 				=> $code,
					'organization_id'	=> $user_organization_id,
					'created_by'		=> $user->id
				];
				$role = \App\Models\Role::create($role_obj);
			}
			$module_roles = [];
			foreach ($post['modules'] as $key => $module) {
				$parent_module_id = $module['id'];
				$module_roles[] = [
					'module_id' => $parent_module_id,
					'role_id'	=> $role->id
				];
				foreach ($module['children'] as $key => $child_id) {
					$module_roles[] = [
						'module_id' => $child_id,
						'role_id'	=> $role->id
					];
				}
			}
			if (\App\Models\ModuleRole::insert($module_roles)) {
				if (!strcasecmp($flag, 'add')) {
					return ['status' => true, 'message' => 'Role with Module ACL added successfully!'];
				}
	            return ['status' => true, 'message' => 'Role with Module ACL updated successfully!'];
	        }
	        return responseBuilder()->error('An error occured while assigning users');
        }
        return ['status' => false, 'code' => 401, 'message' => 'User is super admin thus does not belong to any organization, so first provide an organization_id for which you want to create role'];
    }*/

    public function manageRoleModule($request) // add or update role with modules list
	{
		$user = \Auth::user();
		$org_data = \App\Models\Organization::userOrganization($user, false);
		if (!empty($org_data)) {

            $user_organization_id = $org_data->organization_user[0]->organization_id;
            $post = $request->all();
			if (empty($post['role']['title'])) {
				return ['status' => false, 'code' => 401, 'message' => 'title for role must be provided'];
			}

			$title = $post['role']['title'];
			$code = strtolower(str_replace(' ', '', $title)); // remove spaces & change to lowercase as well

			//print_r("expression");exit;

			//////////////////////
			$role_obj = \App\Models\Role::where([
												'organization_id' => $user_organization_id,
												'title'			  => $title
											])
											->first();

			//echo "<pre>";print_r($role_obj);exit;
			if (!empty($role_obj)) {
				$role_obj = $role_obj->toArray();
				if ($role_obj['id'] != $post['role']['id']) {
					return ['status' => false, 'code' => 401, 'message' => 'title for role must be unique'];
				}
			}
			//////////////////////

			
			$description = !empty($post['role']['description'])? $post['role']['description']: null;
			$flag = 'update';
			if (!empty($post['role']['id']) && isset($post['role']['id'])) {
				$role_id = $post['role']['id'];
				$role = \App\Models\Role::where(['is_deleted' => '0', 'id' => $role_id])->first();
				if (empty($role)) {
					return ['status' => false, 'code' => 401, 'message' => 'Role(with Modules list) you want to edit does not exist'];
				}
				$role_obj['title'] = $title;
				$role_obj['description'] = $description;
				$role_obj['code'] = $code;
				$role_obj['updated_by'] = $user->id;
				$role->update($role_obj);
				\App\Models\ModuleRole::where('role_id', $role->id)->delete();
			}
			else {
				$flag = 'add';
				$role_obj = [
					'title'				=> $title,
					'description' 		=> $description,
					'code' 				=> $code,
					'organization_id'	=> $user_organization_id,
					'created_by'		=> $user->id
				];
				$role = \App\Models\Role::create($role_obj);
			}
			$module_roles = [];
			foreach ($post['modules'] as $key => $module) {
				$parent_module_id = $module['id'];
				$module_roles[] = [
					'module_id' => $parent_module_id,
					'role_id'	=> $role->id
				];
				foreach ($module['children'] as $key => $child_id) {
					$module_roles[] = [
						'module_id' => $child_id,
						'role_id'	=> $role->id
					];
				}
			}
			if (\App\Models\ModuleRole::insert($module_roles)) {
				if (!strcasecmp($flag, 'add')) {
					return ['status' => true, 'message' => 'Role with Module ACL added successfully!'];
				}
	            return ['status' => true, 'message' => 'Role with Module ACL updated successfully!'];
	        }
	        return responseBuilder()->error('An error occured while assigning users');
        }
        return ['status' => false, 'code' => 401, 'message' => 'Access denied to this user'];
    }

    public static function getDetails($id) // here id is basically role_id in the table module_role
    {
    	$role = \App\Models\Role::select(['id', 'title', 'description'])->findOrFail($id);
        $module_roles = \App\Models\ModuleRole::where('role_id', $id)->get();
        $module_ids_arr = [];
        foreach ($module_roles as $key => $module_role) {
            array_push($module_ids_arr, $module_role->module_id);
        }
        $module_parents = \App\Models\Module::where([
                                                    'parent_id' => null,
                                                    'organization_level' => '1'
                                                ])
                                              ->get();
        $module_parent_ids_arr = [];
        foreach ($module_parents as $key => $module_parent) {
            array_push($module_parent_ids_arr, $module_parent->id);
        }
        $selected_parents = array_intersect($module_ids_arr, $module_parent_ids_arr);
        $result['role'] = [
            'id' => $role->id,
            'title' => $role->title,
            'description' => $role->description
        ];
        $result['modules'] = [];
        foreach ($selected_parents as $key => $selected_parent) {
            $module_children = \App\Models\Module::where([
                                                    'parent_id' => $selected_parent,
                                                    'organization_level' => '1'
                                                ])
                                              ->get();
            $module_children_ids_arr = [];
            foreach ($module_children as $key => $module_child) {
                array_push($module_children_ids_arr, $module_child->id);
            }
            $selected_children = array_intersect($module_ids_arr, $module_children_ids_arr);
            $result['modules'][] = [
                'id' => $selected_parent,
                'children' => array_values($selected_children)
            ];
        }
        return $result;
    }
}
