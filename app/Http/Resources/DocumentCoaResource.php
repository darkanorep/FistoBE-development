<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentCoaResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'entry' => $this->entry,
            'document' => [
                'id' => $this->document->id,
                'name' => $this->document->type,
            ],
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->company,
            ],
            'business_unit' => [
                'id' => $this->business_unit->id,
                'name' => $this->business_unit->business_unit,
            ],
            'department' => [
                'id' => $this->department->id,
                'name' => $this->department->department,
            ],
            'sub_unit' => [
                'id' => $this->sub_unit->id,
                'name' => $this->sub_unit->subunit,
            ],
            'location' => [
                'id' => $this->location->id,
                'name' => $this->location->location,
            ],
            'account_title' => [
                'id' => $this->account_title->id,
                'name' => $this->account_title->title,
            ],
        ];
    }
}
