<?php

namespace App\Services;

use App\Models\AccountTitleUnit;

class AccountTitleUnitServices
{
    public function store(array $data):AccountTitleUnit {

        return AccountTitleUnit::create($data);
    }

    public function update(AccountTitleUnit $accountTitleUnit, array $data):AccountTitleUnit {

        $accountTitleUnit->update($data);

        return $accountTitleUnit;
    }

}
