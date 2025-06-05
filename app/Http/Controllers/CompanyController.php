<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Http\Requests\Sync\BusinessUnitRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Exceptions\FistoException;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->status;
        $rows = (int)$request->input('rows', 10);
        $search = $request->search;
        $paginate = $request->input('paginate', 1);
        $api_for = $request->input("api_for", "default");

        $companies = Company::withTrashed()
            ->with("associates")
            ->when($paginate === 1, function ($query) {
                return $query->with("associates");
            })
            ->where(function ($query) use ($status) {
                if ($status == "all") {
                    return $query;
                }

                if ($status == 1) {
                    return $query->whereNull("deleted_at");
                }

                if ($status == 0) {
                    return $query->whereNotNull("deleted_at");
                }
            })
            ->where(function ($query) use ($search) {
                $query->where("code", "like", "%" . $search . "%")->orWhere("company", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $companies = $companies->paginate($rows);
        } elseif ($paginate == 0) {
            $companies = $companies
                ->when($api_for == "vladimir", function ($query) {
                    return $query->get([
                        "id",
                        "sync_id",
                        "code",
                        "company as name",
                        DB::RAW("(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status"),
                    ]);
                })
                ->when($api_for == "genus_etd", function ($query) {
                    return $query->get(["id", "code", "company as name", "updated_at", "deleted_at"]);
                })
                ->when($api_for == "default", function ($query) {
                    return $query->get(["id",
//                        "sync_id",
                        "code",
                        "company as name"]);
                });

            if (count($companies)) {
                $companies = ["companies" => $companies];
            }
        }

        if (count($companies)) {
            return $this->resultResponse("fetch", "Company", $companies);
        } else {
            return $this->resultResponse("not-found", "Company", []);
        }
    }

    public function store(CompanyRequest $request)
    {

        $company = Company::create([
            'code' => $request->code,
            'company' => $request->company,
        ]);

        $company->attach($request->associates);

        return $this->resultResponse("save", "Company", $company);

        // $user_list = User::get();
//    $fields = $request->validate([
//      "code" => "required",
//      "company" => "required",
//      "associates" => "required",
//    ]);
//
//    $company_validateCodeDuplicate = Company::withTrashed()
//      ->where("code", $fields["code"])
//      ->first();
//    if (!empty($company_validateCodeDuplicate)) {
//      return $this->resultResponse("registered", "Code", [
//        "error_field" => "code",
//      ]);
//    }
//
//    $company_validateDescriptionDuplicate = Company::withTrashed()
//      ->where("company", $fields["company"])
//      ->first();
//    if (!empty($company_validateDescriptionDuplicate)) {
//      return $this->resultResponse("registered", "Company", [
//        "error_field" => "company",
//      ]);
//    }
//
//    $apExist = $this->validateIfObjectsExist(new User(), $fields["associates"], "AP Associate");
//    if ($apExist) {
//      return $this->resultResponse("not-registered", "AP Associate", []);
//    }
//    $new_company = Company::create([
//      "code" => $fields["code"],
//      "company" => $fields["company"],
//    ]);
//    $new_company->associates()->attach($fields["associates"]);
//
//    return $this->resultResponse("save", "Company", $new_company);
    }

    public function update(CompanyRequest $request, $id)
    {

        $company = Company::find($id);

        if ($company) {
            $company->associates()->detach();
            $company->associates()->attach($request->associates);

            $company->update([
                'code' => $request->code,
                'company' => $request->company,
            ]);

            $company->touch();

            return $this->resultResponse("update", "Company", $company);
        } else {
            return $this->resultResponse("not-found", "Company", []);
        }

//    $user = new User();
//    $specific_company = Company::withTrashed()->find($id);
//    $fields = $request->validate([
//      "code" => ["required"],
//      "company" => ["required"],
//      "associates" => ["required"],
//    ]);
//
//    $company_validateCodeDuplicate = Company::withTrashed()
//      ->where("code", $fields["code"])
//      ->where("id", "<>", $id)
//      ->first();
//    if (!empty($company_validateCodeDuplicate)) {
//      return $this->resultResponse("registered", "Code", [
//        "error_field" => "code",
//      ]);
//    }
//
//    $company_validateDescriptionDuplicate = Company::withTrashed()
//      ->where("company", $fields["company"])
//      ->where("id", "<>", $id)
//      ->first();
//    if (!empty($company_validateDescriptionDuplicate)) {
//      return $this->resultResponse("registered", "Company", [
//        "error_field" => "company",
//      ]);
//    }
//
//    $apExist = $this->validateIfObjectsExist($user, $fields["associates"], "AP Associate");
//    if ($apExist) {
//      return $this->resultResponse("not-registered", "AP Associate", []);
//    }
//
//    if (!$specific_company) {
//      return $this->resultResponse("not-found", "Company", []);
//    } else {
//      $specific_company->associates()->get();
//      $is_associates_modified = $this->isTaggedArrayModified(
//        $fields["associates"],
//        $specific_company->associates()->get(),
//        "id"
//      );
//
//      $specific_company->code = $fields["code"];
//      $specific_company->company = $fields["company"];
//      $specific_company->associates()->detach();
//      $specific_company->associates()->attach(array_unique($fields["associates"]));
//      return $this->validateIfNothingChangeThenSave($specific_company, "Company", $is_associates_modified);
//    }
    }

    public function change_status(Request $request, $id)
    {
        return $this->changeStatus($id, Company::class, "Company");
//    $status = $request["status"];
//    $model = new Company();
//    return $this->change_masterlist_status($status, $model, $id, "Company");
    }

//    public function store(Request $request)
//    {
//        $companies = $request->input('result');
//
//        collect($companies)->each(function ($company) {
//            $sync_id = $company['id'];
//            $name = $company['name'];
//            $code = $company['code'];
//            $deleted_at = $company['deleted_at'];
//
//            Company::updateOrCreate(
//                [
//                    'sync_id' => $sync_id,
//                    'code' => $code,
//                    'company' => $name,
//                ],
//                [
//                    'code' => $code,
//                    'company' => $name,
//                    'deleted_at' => $deleted_at ? now() : null,
//                    'created_at' => now(),
//                    'updated_at' => now(),
//                ]
//            );
//        });
//
//        return response()->json([
//            'message' => 'Companies successfully synced.',
//        ], Response::HTTP_OK);
//
//
//
////        collect($companies)->each(function ($company) {
////            DB::table('rdf_companies')->updateOrInsert(
////                ['code' => $company['code']],
////                [
////                    'code' => $company['code'],
////                    'company' => data_get($company, 'name'),
////                    'created_at' => now(),
////                    'updated_at' => now(),
////                ]
////            );
////        });
////
////        return response()->json([
////            'message' => 'Companies successfully synced.',
////        ], Response::HTTP_OK);
//    }
}
