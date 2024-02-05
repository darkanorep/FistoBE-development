<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountTitleGreatGrandParentRequest;
use App\Models\AccountTitleGreatGrandParent;
use App\Services\GenericServices;
use Illuminate\Http\Request;

class AccountTitleGreatGrandParentController extends Controller
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

        $account_title_ggp = AccountTitleGreatGrandParent::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $account_title_ggp = $account_title_ggp->paginate($rows);

        } elseif ($paginate == 0) {
            $account_title_ggp = $account_title_ggp->get();
        }

        if (count($account_title_ggp)) {
            return $this->resultResponse("fetch", "Unit", $account_title_ggp);
        } else {
            return $this->resultResponse("not-found", "Unit", []);
        }
    }

    public function store(AccountTitleGreatGrandParentRequest $request)
    {
        $account_title_ggp = $this->genericServices->store(AccountTitleGreatGrandParent::class, $request->validated());

        return $this->resultResponse('save', 'Account Title Grand Parent', $account_title_ggp);
    }

    public function update($id, AccountTitleGreatGrandParentRequest $request)
    {
        $greatGrandParent = AccountTitleGreatGrandParent::find($id);

        if ($greatGrandParent) {
            $greatGrandParent = $this->genericServices->update($greatGrandParent, $request->validated());

            return $this->resultResponse('update', 'Account Title Grand Parent', $greatGrandParent);
        } else {
            return $this->resultResponse('not-found', 'Account Title Grand Parent');
        }

    }

    public function change_status($id) {
        return $this->changeStatus($id, AccountTitleGreatGrandParent::class, 'Account Title Grand Parent');
    }
}
