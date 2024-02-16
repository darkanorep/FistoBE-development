<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountTitleGrandParentRequest;
use App\Models\AccountTitleGrandParent;
use App\Services\GenericServices;
use Illuminate\Http\Request;

class AccountTitleGrandParentController extends Controller
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

        $account_title_grand_parents = AccountTitleGrandParent::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $account_title_grand_parents = $account_title_grand_parents->paginate($rows);

        } elseif ($paginate == 0) {
            $account_title_grand_parents = $account_title_grand_parents->get();
        }

        if (count($account_title_grand_parents)) {
            return $this->resultResponse("fetch", "Account Group", $account_title_grand_parents);
        } else {
            return $this->resultResponse("not-found", "Account Group", []);
        }
    }

    public function store(AccountTitleGrandParentRequest $request)
    {
        $account_title_grand_parent = $this->genericServices->store(AccountTitleGrandParent::class, $request->validated());

        return $this->resultResponse('save', 'Account Group', $account_title_grand_parent);
    }

    public function update($id, AccountTitleGrandParentRequest $request)
    {
        $account_title_grand_parent = AccountTitleGrandParent::find($id);

        if ($account_title_grand_parent) {
            $account_title_grand_parent = $this->genericServices->update($account_title_grand_parent, $request->validated());

            return $this->resultResponse('update', 'Account Group', $account_title_grand_parent);
        } else {
            return $this->resultResponse('not-found', 'Account Group');
        }

    }

    public function change_status($id) {
        return $this->changeStatus($id, AccountTitleGrandParent::class, 'Account Group');
    }
}
