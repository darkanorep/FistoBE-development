<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountTitleChildRequest;
use App\Models\AccountTitleChild;
use App\Services\GenericServices;
use Illuminate\Http\Request;

class AccountTitleChildController extends Controller
{
    /**
     * @var GenericServices
     */
    private $genericServices;

    public function __construct(GenericServices $genericServices)
    {
        $this->genericServices = $genericServices;
    }

    public function index(Request $request) {
        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);

        $account_title_childs = AccountTitleChild::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $account_title_childs = $account_title_childs->paginate($rows);

        } elseif ($paginate == 0) {
            $account_title_childs = $account_title_childs->get();
        }

        if (count($account_title_childs)) {
            return $this->resultResponse("fetch", "Unit", $account_title_childs);
        } else {
            return $this->resultResponse("not-found", "Unit", []);
        }
    }

    public function store(AccountTitleChildRequest $request) {
        $account_title_child = $this->genericServices->store(AccountTitleChild::class, $request->validated());

        return $this->resultResponse('save', 'Account Title Child', $account_title_child);
    }

    public function update($id, AccountTitleChildRequest $request) {
        $account_title_child = AccountTitleChild::find($id);

        if ($account_title_child) {
            $account_title_child = $this->genericServices->update($account_title_child, $request->validated());

            return $this->resultResponse('update', 'Account Title Child', $account_title_child);
        } else {
            return $this->resultResponse('not-found', 'Account Title Child');
        }

    }

    public function change_status($id)
    {
        return $this->changeStatus($id, AccountTitleChild::class, 'Account Title Child');
    }
}
