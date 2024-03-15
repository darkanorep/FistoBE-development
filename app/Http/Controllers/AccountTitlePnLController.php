<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountTitlePnLRequest;
use App\Models\AccountTitlePnL;
use App\Services\AccountTitlePnLServices;
use Illuminate\Http\Request;

class AccountTitlePnLController extends Controller
{
    /**
     * @var AccountTitlePnLServices
     */
    private $accountTitlePnLServices;

    public function __construct(AccountTitlePnLServices $accountTitlePnLServices)
    {
        $this->accountTitlePnLServices = $accountTitlePnLServices;
    }

    public function index(Request $request)
    {
        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);

        $account_title_pnls = AccountTitlePnL::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $account_title_pnls = $account_title_pnls->paginate($rows);

        } elseif ($paginate == 0) {
            $account_title_pnls = $account_title_pnls->get();
        }

        if (count($account_title_pnls)) {
            return $this->resultResponse("fetch", "Normal Balance", $account_title_pnls);
        } else {
            return $this->resultResponse("not-found", "Normal Balance", []);
        }
    }

    public function store(AccountTitlePnLRequest $request) {
        $account_title_pnl = $this->accountTitlePnLServices->store($request->validated());

        return $this->resultResponse('save', 'Normal Balance', $account_title_pnl);
    }

    public function update($id, AccountTitlePnLRequest $request) {
        $account_title_pnl =  AccountTitlePnL::find($id);
        if ($account_title_pnl) {
            $account_title_pnl = $this->accountTitlePnLServices->update($account_title_pnl, $request->validated());

            return $this->resultResponse('update', 'Normal Balance', $account_title_pnl);
        } else {
            return $this->resultResponse('not-found', 'Normal Balance');
        }
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, AccountTitlePnL::class, 'Normal Balance');
    }
}
