<?php

namespace App\Services;

use App\Models\AccountTitlePnL;

class AccountTitlePnLServices
{
    public function store(array $data): AccountTitlePnL {
        return AccountTitlePnL::create($data);
    }

    public function update(AccountTitlePnL $accountTitlePnL, array $data): AccountTitlePnL {
        $accountTitlePnL->update($data);

        return $accountTitlePnL;
    }
}
