<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'account_titles' => $this->accounts->map(function ($document_coa) {
                return [
                    'entry' => $document_coa->entry,
                    'account_title' => [
                        'id' => $document_coa->account_title->id,
                        'name' => $document_coa->account_title->title,
                        'code' => $document_coa->account_title->code,
                    ],
                    'company' => [
                        'id' => $document_coa->company->id,
                        'name' => $document_coa->company->company,
                        'code' => $document_coa->company->code,
                    ],
                    'department' => [
                        'id' => $document_coa->department->id,
                        'name' => $document_coa->department->department,
                        'code' => $document_coa->department->code,
                    ],
                    'location' => [
                        'id' => $document_coa->location->id,
                        'name' => $document_coa->location->location,
                        'code' => $document_coa->location->code,
                    ]
                ];
            })
        ];
    }
}
