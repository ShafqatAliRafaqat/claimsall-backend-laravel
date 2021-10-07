<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;

class DocumentController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function addUpdateDocument(Request $request) {
        $rules = [
            'notes' => 'between:3,200',
            'document' => 'image',
        ];
        if(!$request->has('document_id')){
            $rules['document'] = 'required|image';
        }
        $request->validate($rules);
        $post = $request->all();
        $res = (new Document())->createOrUpdateUserDocument($post);
        if($res['status'] === true){
            $data = $res['data']??[];
            return responseBuilder()->success($res['message'], $data);
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }
    
    public function getDocumentCategory() {
        $documentCategories = DocumentCategory::select('id', 'name as category')->whereNull('deleted_at')->get();
        return responseBuilder()->success('Data fetched successfully!', $documentCategories);
    }
    
    public function addDocument(Request $request) {
        $rules = [
            'document' => 'required|mimes:jpeg,png,jpg,doc,docx,pdf,xls,xlsx,txt'//image
        ];
        $request->validate($rules, ['document.mimes' => "The document must be a file of type: jpeg, png, jpg, doc, docx, pdf, xls, xlsx, txt. Or make sure File is not empty."]);
        $post = $request->all();
        $res = (new Document())->uploadUserDocument($post);
        if($res['status'] === true){
            $data = $res['data']??[];
            $response = responseBuilder()->success($res['message'], $data);
            $this->urlComponents('Upload new document', $response, 'Medical_Record_Management');
            return $response;
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }

    public function addCareserviceDocument(Request $request)
    {
        $rules = [
            'document' => 'required|mimes:jpeg,png,jpg,doc,docx,pdf,xls,xlsx,txt'//image
        ];
        $request->validate($rules, ['document.mimes' => "The document must be a file of type: jpeg, png, jpg, doc, docx, pdf, xls, xlsx, txt. Or make sure File is not empty."]);
        $post = $request->all();

        $res = (new Document())->uploadCareserviceDocument($post);
        if($res['status'] === true){
            $data = $res['data']??[];
            $response = responseBuilder()->success($res['message'], $data);
            $this->urlComponents('Upload new document', $response, 'Medical_Record_Management');
            return $response;
        }
        return responseBuilder()->error($res['message'], $res['code']);
    }
}
