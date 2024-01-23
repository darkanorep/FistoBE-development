<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherCodeRequest;
use App\Models\VoucherCode;
use App\Services\VoucherCodeServices;
use Illuminate\Http\Request;

class VoucherCodeController extends Controller
{
    private $voucherCodeServices;

    public function __construct(VoucherCodeServices $voucherCodeServices)
    {
        $this->voucherCodeServices = $voucherCodeServices;
    }
    public function index(Request $request)  {

        $status =  $request['status'];
        $rows =  (int) $request->input('rows', 10);
        $search =  $request['search'];
        $paginate = $request->input('paginate', 1);

        $voucher_code = VoucherCode::withTrashed()->where(function ($query) use ($status) {
            return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
        })->where(function ($query) use ($search) {
            $query->where('code', 'like', '%' . $search . '%');
        })->latest('updated_at');

        if ($paginate == 1) {
            $voucher_code = $voucher_code->paginate($rows);
        } else if ($paginate == 0) {
            $voucher_code = $voucher_code->get();
        }

//        $voucher_code->transform(function ($value) {
//            return [
//                'id' => $value->id,
//                'code' => $value->code,
//                'departments' => $value->departments->map(function ($department) {
//                    return [
//                        'id' => $department->id,
//                        'code' => $department->code,
//                        'name' => $department->department,
//                    ];
//                }),
//                'updated_at' => $value->updated_at,
//                'deleted_at' => $value->deleted_at,
//            ];
//        });

        if (count($voucher_code)) {
            return $this->resultResponse('fetch', 'Voucher Code', $voucher_code);
        } else {
            return $this->resultResponse('not-found', 'Voucher Code', []);
        }
    }

    //Traditional way
//    public function store(VoucherCodeRequest $request) {
//
//        $new_voucher_code = VoucherCode::create([
//            'code' => $request->code,
//        ]);
//
//        return $this->resultResponse('save', 'Voucher Code', $new_voucher_code);
//    }

    public function store(VoucherCodeRequest $request) {

        $voucherCode = $this->voucherCodeServices->store($request->validated());

        return $this->resultResponse('save', 'Voucher Code', $voucherCode);
    }

    //Traditional way
//    public function update(VoucherCodeRequest $request, $id) {
//        $voucher_code = VoucherCode::where('id', $id)->first();
//
//        if ($voucher_code) {
//            $voucher_code->update([
//                'code' => $request->code,
//            ]);
//
//            return $this->resultResponse('update', 'Voucher Code', $voucher_code);
//        } else {
//            return $this->resultResponse('not-found', 'Voucher Code', []);
//        }
//    }

    public function update(VoucherCodeRequest $request, VoucherCode $voucherCode) {

        $voucher_Code = $this->voucherCodeServices->update($voucherCode, $request->validated());

        return $this->resultResponse('update', 'Voucher Code', $voucher_Code);
    }

    public function change_status($id) {
        return $this->changeStatus($id, VoucherCode::class, 'Voucher Code');
    }
}
