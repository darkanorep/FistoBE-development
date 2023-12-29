<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollCategoryRequest;
use App\Models\PayrollCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Routing\ResponseFactory;
use App\Exceptions\FistoException;

class PayrollCategoryController extends Controller
{

    public function index(Request $request)
    {

        $status =  $request['status'];
        $rows =  (int) $request->input('rows', 10);
        $search =  $request['search'];
        $paginate = $request->input('paginate', 1);

        $payroll_categories = PayrollCategory::withTrashed()->where(function ($query) use ($status) {
            return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
        })->where(function ($query) use ($search) {
            $query->where('category', 'like', '%' . $search . '%');
        })->latest('updated_at');

        if ($paginate == 1) {
            $payroll_categories = $payroll_categories->paginate($rows);
        } else if ($paginate == 0) {
            $payroll_categories = array("payroll_categories" => $payroll_categories->get(['id', 'category as name']));
        }

        if (count($payroll_categories)) {
            return $this->resultResponse('fetch', 'Payroll Category', $payroll_categories);
        } else {
            return $this->resultResponse('not-found', 'Payroll Category', []);
        }

//        $status = $request['status'];
//        $rows = (empty($request['rows'])) ? 10 : (int)$request['rows'];
//        $search = $request['search'];
//        $paginate = (isset($request['paginate'])) ? $request['paginate'] : $paginate = 1;
//
//        $payroll_category = PayrollCategory::withTrashed()
//            ->where(function ($query) use ($status) {
//                return ($status == true) ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
//            })
//            ->where('category', 'like', '%' . $search . '%')
//            ->latest('updated_at');
//
//        if ($paginate == 1) {
//            $payroll_category = $payroll_category
//                ->paginate($rows);
//        } else if ($paginate == 0) {
//            $payroll_category = $payroll_category
//                ->get(['id', 'category as name']);
//            if (count($payroll_category) == true) {
//                $payroll_category = array("payroll_categories" => $payroll_category);;
//            }
//        }
//
//        if (count($payroll_category) == true) {
//            return $this->resultResponse('fetch', 'Payroll Category', $payroll_category);
//        }
//        return $this->resultResponse('not-found', 'Payroll Category', []);
    }

    public function store(PayrollCategoryRequest $request)
    {
        $payroll_category = PayrollCategory::create([
            'category' => $request->category
        ]);

        return $this->resultResponse('save', 'Payroll Category', $payroll_category);
//      $fields = $request->validate([
//        'category' => ['required','string']
//      ]);
//
//      $validateDuplicatePayrollCategory = PayrollCategory::withTrashed()->firstWhere('category', $fields['category']);
//
//      if (!empty($validateDuplicatePayrollCategory))
//        return $this->resultResponse('registered','Payroll Category',["error_field" => "category"]);
//        $payroll_category = PayrollCategory::create($fields);
//        return $this->resultResponse('save','Payroll Category',$payroll_category);
    }

    public function update(PayrollCategoryRequest $request, $id)
    {
        $payroll_category = PayrollCategory::where('id', $id)->first();

        if ($payroll_category) {

            $payroll_category->category = $request->category;

            return $this->validateIfNothingChangeThenSave($payroll_category, 'Payroll category');

        } else {
            return $this->resultResponse('not-found', 'Payroll Category', []);
        }
//    $model = new PayrollCategory();
//    $fields = $request->validate([
//      'category' => ['required','string']
//    ]);
//
//    $payroll_category = PayrollCategory::withTrashed()->find($id);
//    $is_unique = $this->isUnique($model,'Payroll category',['category'],[$fields['category']],$id);
//
//    if(!empty($payroll_category) == true){
//      $payroll_category->category = $fields['category'];
//      return $this->validateIfNothingChangeThenSave($payroll_category,'Payroll category');
//    }
//    return $this->resultResponse('not-found','Payroll Category',[]);
    }

    public function change_status($id)
    {

        $payroll_category = PayrollCategory::withTrashed()->find($id);

        if ($payroll_category) {

            if ($payroll_category->trashed()) {
                $payroll_category->restore();

                return $this->resultResponse('restore', 'Payroll Category', []);

            } else {

                $payroll_category->delete();

                return $this->resultResponse('delete', 'Payroll Category', []);
            }

        } else {
            return $this->resultResponse('not-found', 'Payroll Category', []);
        }
    }

}
