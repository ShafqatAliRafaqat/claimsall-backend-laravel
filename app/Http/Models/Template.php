<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Reliese\Database\Eloquent\Model as Eloquent;

class Template extends Model
{
	use \Illuminate\Database\Eloquent\SoftDeletes;

	private $authUser = null;

	protected $casts = [
		'organization_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int',
		'deleted_by' => 'int'
	];

	protected $fillable = [
		'subject',
		'body',
		'event',
		'organization_id',
		'is_active',
		'type',
		'is_deleted',
		'created_by',
		'updated_by',
		'deleted_by'
	];

	/*public function user()
	{
		return $this->belongsTo(\App\Models\User::class);
	}*/

	public function organization()
	{
		return $this->belongsTo(\App\Models\Organization::class);
	}

	public function addTemplate($request)
	{
		$user = \Auth::user();
		$post = $request->all();
		if (!empty($post['organization_id'])) {
			$organization_id = $post['organization_id'];
		}
		else {
			$organization_id = '0';
		}
		$template = $this->where([
									'is_deleted'		=> '0',
									'organization_id' 			=> $organization_id,
									'event' 	=> $post['event']
								])
						 ->first();

		if (!empty($template)) {
			return ['status' => false, 'code' => 401, 'message' => 'Event must be unique against specific organization'];
		}
		else {
			$post['created_by'] = $user->id;
			$this->fill($post);
			if($this->save()) {
	            return ['status' => true, 'message' => 'Template is added successfully!'];
	        }
		}
    }

    public function updateTemplateById($request, $id)
    {
		$user = \Auth::user();
		$post = $request->all();
        $post['updated_by'] = $user->id;
        if (!empty($post['organization_id'])) {
			$organization_id = $post['organization_id'];
		}
		else {
			$organization_id = '0';
		}
        $template = $this->where(['is_deleted'=>'0', 'id' =>$id])->first();
        if (!$template) {
            return ['status' => false, 'code' => 400, 'message' => 'The template you want to edit not found'];
        }
        $template1 = $this->where([
									'is_deleted'		=> '0',
									'organization_id' 			=> $organization_id,
									'event' 	=> $post['event']
								])
                	  	  ->first();
		if (!empty($template1)) {
			return ['status' => false, 'code' => 401, 'message' => 'Event must be unique against specific organization'];
		}
        if ($template->update($post)) {
            return ['status' => true, 'message' => 'The template is updated successfully!'];
        }
        return ['status' => false, 'code' => 422, 'message' => 'An error occured while saving the data'];
    }

    public function getTemplates()
    {
		$user = \Auth::user();
        $records = $this->where(['is_deleted'=>'0'])->paginate(10);
        if(count($records)<=0) {
            return ['status' => true, 'message' => 'You haven\'t added any templates yet' ];
        }
        return ['status' => true, 'message' => 'Got templates', 'data' => $records];
    }

    public function deleteByUserAndId($id)
    {
		$user = \Auth::user();
        $template = $this->where(['is_deleted' => '0', 'id' => $id])->first();
        if(!$template) {
            return ['status' => false, 'message' => 'Sorry, we did not find your record'];
        }
        $template->is_deleted = '1';
        $template->deleted_by = $user->id;
        $template->save();
        $template->delete();
        return ['status'=> true, 'message' => 'Deleted successfully!'];
    }
}
