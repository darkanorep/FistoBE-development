<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReferrenceRequest;
use App\Models\Referrence;
use App\Methods\GenericMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exceptions\FistoException;

class ReferrenceController extends Controller
{
    public function index(Request $request)
    {
        $status =  $request['status'];
        $rows =  (int) $request->input('rows', 10);
        $search =  $request['search'];
        $paginate = $request->input('paginate', 1);

//        $status =  $request['status'];
//        $rows =  (empty($request['rows']))?10:(int)$request['rows'];
//        $search =  $request['search'];
//        $paginate = (isset($request['paginate']))? $request['paginate']:$paginate = 1;
//
        $referrences = Referrence::withTrashed()
            ->when($paginate, function ($query) use ($search) {
                $query->select(['id', 'type', 'description', 'updated_at', 'deleted_at'])
                    ->where(function ($query) use ($search) {
                        $query->where('type', 'like', '%' . $search . '%')
                            ->orWhere('description', 'like', '%' . $search . '%');
                    });
            }, function ($query) {
                $query->select(['id', 'type']);
            })
            ->where(function ($query) use ($status) {
                return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
            })
            ->latest('updated_at');

        if ($paginate == 1) {
            $referrences = $referrences
                ->paginate($rows);
        } else if ($paginate == 0) {
            $referrences = $referrences
                ->get();
            if (count($referrences)) {
                $referrences = array("referrences" => $referrences);;
            }
        }

        if (count($referrences)) {
            return $this->resultResponse('fetch', 'Reference', $referrences);
        }
        return $this->resultResponse('not-found', 'Reference', []);
    }
    public function store(ReferrenceRequest $request)
    {

        $referrence = Referrence::create([
            'type' => $request->type,
            'description' => $request->description
        ]);

        return $this->resultResponse('save','Reference', $referrence);
//        $fields = $request->validate([
//            'type' => 'required|string',
//            'description' => 'required|string'
//        ]);
//
//       $duplicateValues= GenericMethod::validateDuplicateByIdAndTable($fields['type'],'type','referrences');
//
//       if(count($duplicateValues)>0) {
//        return $this->resultResponse('registered','Reference',[]);
//        }
//
//        $new_referrence = Referrence::create([
//            'type' => $fields['type']
//            , 'description' => $fields['description']
//        ]);
//
//        if (!$new_referrence->count() == 0) {
//            return $this->resultResponse('save','Reference',$new_referrence);
//        }
    }


    public function update(ReferrenceRequest $request, $id)
    {
        $referrence = Referrence::where('id',$id)->first();

        if ($referrence) {
//            $referrence->update([
//                'type' => $request->type,
//                'description' => $request->description
//            ]);
            $referrence->type = $request->type;
            $referrence->description = $request->description;

            return $this->validateIfNothingChangeThenSave($referrence,'Reference');

            return $this->resultResponse('update','Reference', $referrence);
        } else {
            return $this->resultResponse('not-found','Reference',[]);
        }

//        $specific_referrence = Referrence::find($id);
//        $fields = $request->validate([
//            'type' => 'required|string',
//            'description' => 'required|string',
//        ]);
//
//        if (!$specific_referrence) {
//            return $this->resultResponse('not-found','Reference',[]);
//
//        } else {
//
//            $validateDuplicateInUpdate =  GenericMethod::validateDuplicateInUpdate($fields['type'],'type','referrences',$id);
//            if(count($validateDuplicateInUpdate)>0) {
//                return $this->resultResponse('registered','Reference',[]);
//            }
//
//            $specific_referrence->type = $request->get('type');
//            $specific_referrence->description = $request->get('description');
//            return $this->validateIfNothingChangeThenSave($specific_referrence,'Reference');
//        }
    }
    public function change_status($id)
    {

        return $this->changeStatus($id, Referrence::class, 'Reference');

//        $status = $request['status'];
//        $model = new Referrence();
//        return $this->change_masterlist_status($status,$model,$id,'Reference');
    }
}
