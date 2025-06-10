<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessUnitRequest;
use App\Http\Requests\Sync\BusinessUnitRequestSync;
use App\Models\BusinessUnit;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BusinessUnitController extends Controller
{
    public function index(Request $request)
    {
        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);
        $api_for = $request->input("api_for", "default");

        $business_unit = BusinessUnit::withTrashed()
            ->with("company")
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("business_unit", "like", "%" . $search . "%")->orWhere("code", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $business_unit = $business_unit->paginate($rows);
            $business_unit->transform(function ($value) {
                return [
                    "id" => $value->id,
                    "sync_id" => $value->sync_id,
                    "code" => $value->code,
                    "company" => [
                        "id" => $value->company->id,
                        "name" => $value->company->company,
                    ],
                    "business_unit" => $value->business_unit,
                    "associates" => $value->users->map(function ($user) {
                        return [
                            "id" => $user->id,
                            "name" => $user->first_name . " " . $user->last_name,
                        ];
                    }),
                    "updated_at" => $value->updated_at,
                    "deleted_at" => $value->deleted_at,
                ];
            });
        } elseif ($paginate == 0) {
            //            $business_unit = $business_unit->get();
            $business_unit = $business_unit
                ->when($api_for == "vladimir", function ($query) {
                    return $query
                        ->with(["company:id,code,company"])
                        ->get([
                            "id",
                            "sync_id",
                            "code",
                            "business_unit as name",
                            DB::RAW("(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status"),
                        ]);
                })
                ->when($api_for == "default", function ($query) {
                    return $query
                        ->with("company")
                        ->get(["id", "sync_id", "code", "business_unit as name"]);
                });
            $business_unit = array("business_units" => $business_unit);
        }

        if (count($business_unit)) {
            return $this->resultResponse("fetch", "Business Unit", $business_unit);
        } else {
            return $this->resultResponse("not-found", "Business Unit", []);
        }
    }

//    public function store(BusinessUnitRequest $request)
//    {
//        $new_business_unit = BusinessUnit::create([
//            "company_id" => $request->company_id,
//            "code" => $request->code,
//            "business_unit" => $request->business_unit,
//        ]);
//
//        $associates = $request->associates;
//
//        if (isset($associates)) {
//            foreach ($associates as $associate) {
//                $new_business_unit->users()->attach($associate);
//            }
//        }
//
//        return $this->resultResponse("save", "Business Unit", $new_business_unit);
//    }

    public function show($id)
    {
        $business_unit = BusinessUnit::where("id", $id)->first();
        return $business_unit
            ? $this->resultResponse("fetch", "Business Unit", $business_unit)
            : $this->resultResponse("not-found", "Sub Unit", []);
    }

    public function update(BusinessUnitRequest $request, $id)
    {
        $businessunit = BusinessUnit::where("id", $id)->first();

        if ($businessunit) {
            $businessunit->update([
                "company_id" => $request->company_id,
                "code" => $request->code,
                "business_unit" => $request->business_unit,
            ]);

            $associates = $request->associates;

            $businessunit->users()->detach();
            $businessunit->users()->attach($associates);

            return $this->resultResponse("update", "Business Unit", $businessunit);
        } else {
            return $this->resultResponse("not-found", "Business Unit", []);
        }
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, BusinessUnit::class, "Business Unit");
    }

    public function store(Request $request) {
        $businessUnits = $request->all();
        $errors = [];

        collect($businessUnits)->each(function ($businessUnit) use (&$errors) {
            $sync_id = $businessUnit['id'];
            $sync_company_id = $businessUnit['company_id'];
            $name = $businessUnit['name'];
            $code = $businessUnit['code'];
            $deleted_at = $businessUnit['deleted_at'];

            // Validate if the company_id exists in the database
            $companyExists = Company::where('sync_id', $sync_company_id)->exists();

            if (!$companyExists) {
                $errors[] = "Company with ID {$sync_company_id} does not exist.";
                return; // Skip this iteration
            }

            // Update or create the BusinessUnit
            BusinessUnit::updateOrCreate([
                'sync_id' => $sync_id,
                'company_sync_id' => $sync_company_id,
                'business_unit' => $name,
                'code' => $code,
            ], [
                'business_unit' => $name,
                'code' => $code,
                'deleted_at' => $deleted_at ? now() : null,
            ]);
        });

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Sync Company first before syncing Business Units.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'message' => 'Business Units successfully synced.',
        ], Response::HTTP_OK);
    }
}
