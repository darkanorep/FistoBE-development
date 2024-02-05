<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountTitleUnitRequest;
use App\Models\AccountTitleUnit;
use App\Services\AccountTitleUnitServices;
use Illuminate\Http\Request;

class AccountTitleUnitController extends Controller
{
    /**
     * @var AccountTitleUnitServices
     */
    private $accountTitleUnitServices;

    public function __construct(AccountTitleUnitServices $accountTitleUnitServices)
    {
        $this->accountTitleUnitServices = $accountTitleUnitServices;
    }

    public function index(Request $request)
    {
        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);

        $account_title_units = AccountTitleUnit::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        $paginate == 1
            ? $account_title_units = $account_title_units->paginate($rows)
            : $account_title_units = $account_title_units->get();

        if (count($account_title_units)) {
            return $this->resultResponse("fetch", "Unit", $account_title_units);
        } else {
            return $this->resultResponse("not-found", "Unit", []);
        }
    }

    public function store(AccountTitleUnitRequest $request)
    {
        $account_title_unit = $this->accountTitleUnitServices->store($request->validated());

        return $this->resultResponse('save', 'Unit', $account_title_unit);
    }

    public function update($id, AccountTitleUnitRequest $request)
    {
        $account_title_unit =  AccountTitleUnit::find($id);

        if ($account_title_unit) {
            $account_title_unit = $this->accountTitleUnitServices->update($account_title_unit, $request->validated());

            return $this->resultResponse('update', 'Unit', $account_title_unit);
        } else {
            return $this->resultResponse('not-found', 'Unit');
        }

    }

    public function change_status($id)
    {
        return $this->changeStatus($id, AccountTitleUnit::class, "Unit");
    }
}
