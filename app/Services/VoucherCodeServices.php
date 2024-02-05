<?php
namespace App\Services;

use App\Models\VoucherCode;

class VoucherCodeServices {

    public function store(array $voucherCodeData): VoucherCode
    {
        return VoucherCode::create($voucherCodeData);
    }

    public function update(VoucherCode $voucherCode, array $voucherCodeData): VoucherCode
    {
        $voucherCode->update($voucherCodeData);

        return $voucherCode;
    }

}
