<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountTitleParentRequest;
use App\Models\AccountTitleParent;
use App\Services\GenericServices;
use Illuminate\Http\Request;

class AccountTitleParentController extends Controller
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

        $account_title_parents = AccountTitleParent::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $account_title_parents = $account_title_parents->paginate($rows);

        } elseif ($paginate == 0) {
            $account_title_parents = $account_title_parents->get();
        }

        if (count($account_title_parents)) {
            return $this->resultResponse("fetch", "Account Title Parents", $account_title_parents);
        } else {
            return $this->resultResponse("not-found", "Account Title Parents", []);
        }
    }

    public function store(AccountTitleParentRequest $request)
    {
        $account_title_parent = $this->genericServices->store(AccountTitleParent::class, $request->validated());

        return $this->resultResponse('save', 'Account Title Parent', $account_title_parent);
    }

    public function update($id, AccountTitleParentRequest $request)
    {
        $account_title_parent = AccountTitleParent::find($id);

        if ($account_title_parent) {
            $account_title_parent = $this->genericServices->update($account_title_parent, $request->validated());

            return $this->resultResponse('update', 'Account Title Parent', $account_title_parent);
        } else {
            return $this->resultResponse('not-found', 'Account Title Parent');
        }
    }

    public function change_status($id) {
        return $this->changeStatus($id, AccountTitleParent::class, 'Account Title Parent');
    }
}
