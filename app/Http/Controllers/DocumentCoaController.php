<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentCoaRequest;
use App\Http\Resources\DocumentCoaResource;
use App\Models\DocumentCoa;
use Illuminate\Http\Request;

class DocumentCoaController extends Controller
{

    public function index()
    {
        return response('sheesh');
    }

    public function store(DocumentCoaRequest $request)
    {
        $document_coa = DocumentCoa::create([
            'entry' => $request->entry,
            'document_id' => $request->document_id,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'sub_unit_id' => $request->sub_unit_id,
            'location_id' => $request->location_id,
            'account_title_id' => $request->account_title_id,
        ]);
        return $this->resultResponse('save','Record', new DocumentCoaResource($document_coa));
    }

    public function show($id)
    {
        $document_coa = DocumentCoa::where('id', $id)->first();
        return $document_coa ? $this->resultResponse('fetch','Business Unit', $document_coa) : $this->resultResponse('not-found','Sub Unit', []);
    }

    public function update(DocumentCoaRequest $request, $id)
    {
        $document_coa = DocumentCoa::where('id', $id)->first();

        if ($document_coa) {
            $document_coa->update([
                'entry' => $request->entry,
                'document_id' => $request->document_id,
                'company_id' => $request->company_id,
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'sub_unit_id' => $request->sub_unit_id,
                'location_id' => $request->location_id,
                'account_title_id' => $request->account_title_id,
            ]);

            return $this->resultResponse('update','Record', $document_coa);
        } else {
            return $this->resultResponse('not-found','Record', []);
        }
    }

    public function change_status($id) {
        $data = DocumentCoa::withTrashed()->find($id);

        if ($data) {
            if ($data->trashed()) {
                $data->restore();

                return $this->resultResponse("restore", 'Record', []);
            } else {
                $data->delete();

                return $this->resultResponse("archive", 'Record', []);
            }
        } else {

            return $this->resultResponse('not-found','Record', []);
        }
    }
}
