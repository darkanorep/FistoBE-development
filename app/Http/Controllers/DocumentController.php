<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentCoaRequest;
use App\Http\Resources\DocumentResource;
use App\Methods\GenericMethod;
use App\Models\Document;
use App\Models\DocumentCoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Facades\Route;
use App\Exceptions\FistoException;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $status =  $request['status'];
        $rows =  (empty($request['rows']))?10:(int)$request['rows'];
        $search =  $request['search'];

        $documents = Document::withTrashed()
        ->with([
//            "categories" => function ($query) {
//                $query->select('categories.id', 'categories.name');
//            }
            "categories:id,name",
            "accounts" => function ($query) {
                $query->with([
                    "company:id,company as name",
                    "business_unit:id,business_unit as name",
                    "department:id,department as name",
                    "account_title:id,code,title as name",
                    "business_unit:id,business_unit as name",
                    "sub_unit:id,subunit as name",
                    "location:id,location as name",
                ]);
            }
        ])
        ->where(function ($query) use ($status){
          return ($status==true)?$query->whereNull('deleted_at'):$query->whereNotNull('deleted_at');
        })
        ->where(function ($query) use ($search) {
            $query->where('documents.type', 'like', '%' . $search . '%')
                ->orWhere('documents.description', 'like', '%' . $search . '%');
        })
        ->select(['id','type','description','created_at','updated_at','deleted_at'])
        ->latest('updated_at')
        ->paginate($rows);

        if(count($documents)==true){
            return $this->resultResponse('fetch','Document', $documents);
          }
          return $this->resultResponse('not-found','Document',[]);

    }

    public function store(Request $request, DocumentCoaRequest $documentCoaRequest)
    {
        $fields = $request->validate([
            'type' => 'required|string',
            'description' => 'required|string',
        ]);

        $duplicateDocumentType =  GenericMethod::validateDuplicateDocumentType($fields['type']);
        if(count($duplicateDocumentType)>0) {
            return $this->resultResponse('registered','Document',[]);
        }

        $document_categories = DB::table('categories AS c')
        ->whereNull('c.deleted_at')
            ->select('c.name', 'c.id AS cid')
            ->get();
        $active_categories = $document_categories->pluck('cid');

        $category_details = collect();
        foreach ($request['categories'] as $cat) {

            if ($active_categories->contains($cat)) {
                $category_details->push(['category_is_active' => 1, 'cat_id' => $cat]);
            } else {
                $category_details->push(['category_is_active' => 0, 'cat_id' => $cat]);
            }
        }

        $unregistered_category_detail = [];
        foreach ($category_details as $specific_category_detail) {
            if ($specific_category_detail['category_is_active'] == 0) {
                array_push($unregistered_category_detail, $specific_category_detail['cat_id']);
            }
        }

        if ($unregistered_category_detail) {
            $unregistered_category = implode(',', $unregistered_category_detail);
            return $this->resultResponse('not-registered','Category',$unregistered_category);
        } else {
            $new_document = Document::create([
                'type' => $fields['type'],
                'description' => $fields['description'],
            ]);
            $category_ids = $request['categories'];
            $new_document->categories()->attach($category_ids);

            $accounts = $documentCoaRequest['account'];
            if (isset($accounts)) {
                foreach ($accounts as $account) {
                    DocumentCoa::create([
                        'document_id' => $new_document->id,
                        'entry' => $account['entry'],
                        'company_id' => $account['company_id'],
                        'business_unit_id' => $account['business_unit_id'],
                        'department_id' => $account['department_id'],
                        'sub_unit_id' => $account['sub_unit_id'],
                        'location_id' => $account['location_id'],
                        'account_title_id' => $account['account_title_id'],
                    ]);
                }
            }

            return $this->resultResponse('save','Document',$new_document);
         }
    }

    public function update(Request $request, DocumentCoaRequest $documentCoaRequest, $id)
    {
        $specific_document = Document::find($id);
        $fields = $request->validate([
            'type' => [
                'required',
                Rule::unique('documents')->ignore($specific_document->id),
            ],
            'description' => 'required|string',

        ]);

//        $validateDuplicateDocumentTypeInUpdate =  GenericMethod::validateDuplicateDocumentTypeInUpdate($fields['type'],$id);
//        if(count($validateDuplicateDocumentTypeInUpdate)>0) {
//            return $this->resultResponse('registered','Document',[]);
//        }

        if (!$specific_document) {
            return $this->resultResponse('not-found','Document',[]);
        }

        $specific_document->type = $request->get('type');
        $specific_document->description = $request->get('description');
        $category_ids = $request['categories'];

        $is_tagged_modified = $this->isTaggedArrayModified($category_ids,  $specific_document->categories()->get(),'id');

        $specific_document->categories()->detach();
        $specific_document->categories()->attach($category_ids);

        $accounts = $documentCoaRequest['account'];

        if (isset($accounts)) {
            DocumentCoa::where('document_id', $id)->delete();
            foreach ($accounts as $account) {
                $test = DocumentCoa::create([
                    'document_id' => $specific_document->id,
                    'entry' => $account['entry'] ?? null,
                    'company_id' => $account['company_id'] ?? null,
                    'business_unit_id' => $account['business_unit_id'] ?? null,
                    'department_id' => $account['department_id'],
                    'sub_unit_id' => $account['sub_unit_id'] ?? null,
                    'location_id' => $account['location_id'] ?? null,
                    'account_title_id' => $account['account_title_id'] ?? null,
                ]);
            }
        }
//        return $this->validateIfNothingChangeThenSave($specific_document,'Document',$is_tagged_modified);
        return $this->resultResponse('update','Document',$specific_document);
    }

    public function change_status(Request $request,$id){
        $status = $request['status'];
        $model = new Document();
        return $this->change_masterlist_status($status,$model,$id,'Document');
    }

}
