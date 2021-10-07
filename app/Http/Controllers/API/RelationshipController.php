<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Relationship;
use Illuminate\Support\Facades\Input;

class RelationshipController extends Controller
{
    use \App\Traits\WebServicesDoc;
    
    private $authUser = null;

    public function index()
    {
        $relationships = Relationship::select('name', 'category', 'created_at')->where('is_deleted', '0')->paginate(10);
        $response = responseBuilder()->success('All relationships', $relationships, false);
        $this->urlComponents('List Relationships', $response, 'Relationship_Management');
        return $response;
    }

    public function store(Request $request)
    {
        $this->authUser = \App\User::__HUID();
        $user = $this->authUser['user'];
        $request->validate([
            'name' => 'bail|required|max:200|unique:relationships',
            'type_id' => 'bail|required|exists:relationship_types,id',
        ]);
        $post = $request->all();
        $post['created_by'] = $user->id;
        Relationship::create($post);
        $response = responseBuilder()->success("New relationship with name:{$post['name']} added successfully!");
        $this->urlComponents('Store Relationship', $response, 'Relationship_Management');
        return $response;
    }

    public function show($id)
    {
        $relationship = Relationship::findOrFail($id)->toArray();
        $response = responseBuilder()->success('Find Details successfully', $relationship);
        $this->urlComponents('Show Relationship', $response, 'Relationship_Management');
        return $response;
    }

    public function update(Request $request, $id)
    {
        $this->authUser = \App\User::__HUID();
        $user = $this->authUser['user'];
        $relationship = Relationship::findOrFail($id);
        $request->validate([
            'name' => 'bail|required|max:200|unique:relationships,name,'.$relationship->id,
            'type_id' => 'bail|required|exists:relationship_types,id',
        ]);
        
        $relationship->update($request->all());
        $response = responseBuilder()->success('Updated successfully');
        $this->urlComponents('Update Relationship', $response, 'Relationship_Management');
        return $response;
    }

    public function destroy($id)
    {
        $relationship = Relationship::findOrFail($id);
        $relationship->delete();
        $response = responseBuilder()->success('Updated successfully');
        $this->urlComponents('Remove Relationship', $response, 'Relationship_Management');
        return $response;
        
    }
    
    public function relationshipList() {
        $data = Input::get();
        $cats = (!empty($data['type_id'])) ? explode(',', $data['type_id']) : null;
        $relationships = Relationship::select(['id', 'name', 'type_id'])
                ->with(['relationship_type' => function($relationshipTypeQuery) use($data, $cats){
                    $relationshipTypeQuery->select(['id', 'name']);
                }])->whereHas('relationship_type', function($relationshipTypeQuery) use($data, $cats){
                    if(!empty($data['type_id'])){
                        $relationshipTypeQuery->whereIn('type_id', $cats);
                    }
                })
                        ->get()->toArray();
        $relationshipData = [];
        foreach ($relationships as $relationship) {
            $relationshipData[] = ['id' => $relationship['id'], 'name' => $relationship['name'], 'category' => $relationship['relationship_type']['name']];
        }
        $response =  responseBuilder()->success('All relationships', $relationshipData);
        $this->urlComponents('Get List of relationship to add FNF', $response, 'Friends_AND_Family');
        return $response;
    }
}
