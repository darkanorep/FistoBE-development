<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\VoucherCode;
use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->status;
        $rows = (int)$request->input('rows', 10);
        $search = $request->search;
        $paginate = $request->input('paginate', 1);
        $company_id = $request->input('company_id', 1);

        // System Name
        $api_for = $request->input("api_for", "default");

//        $departments = Department::withTrashed()
//            ->when(isset($status), function ($query) use ($status) {
//                return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
//            })
//            ->where(function ($query) use ($search) {
//                return $query->where('code', 'like', '%' . $search . '%')
//                    ->orWhere('department', 'like', '%' . $search . '%');
//            })
//            ->when($company_id, function ($query) use ($company_id) {
//                $query->where('company', $company_id);
//            })
//            ->latest('updated_at');
//
//        if ($paginate == 1) {
//            $departments = $departments
//                ->with([
//                    'Company',
//                    'voucherCode:id,code'
//                ])
//                ->paginate($rows);
//        } else if ($paginate == 0) {
//            $departments = $departments
//                ->when($api_for == "vladimir", function ($query) {
//                    return $query
//                        ->with('businessUnit')
//                        ->get([
//                            "id",
//                            "code",
//                            "department as name",
//                            "company",
//                            DB::RAW("(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status"),
//                        ]);
//                })
//                ->when($api_for == "genus_etd", function ($query) {
//                    return $query
//                        ->with("Company")
//                        ->get(["id", "code", "department as name", "company", "updated_at", "deleted_at"]);
//                })
//                ->when($api_for == "default", function ($query) {
//                    return $query->get(["id", "code", "department as name"]);
//                });
//
//            $departments = array("departments" => $departments);
//        }


        $departments = Department::withTrashed()
            ->when($paginate == 1, function ($query) {
                return $query->with([
                    'Company',
                    'voucherCode:id,code'
                ]);
            })
            ->where(function ($query) use ($status) {
                $query->when($status == 'all', function ($query) {
                    return $query;
                }, function ($query) use ($status) {
                    return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
                });
//        if ($status == "all") {
//          return $query;
//        }
//
//        if ($status == 1) {
//          return $query->whereNull("deleted_at");
//        }
//
//        if ($status == 0) {
//          return $query->whereNotNull("deleted_at");
//        }
            })
            ->where(function ($query) use ($search) {
                return $query->where("code", "like", "%" . $search . "%")
                    ->orWhere("department", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $departments = $departments->paginate($rows);
        } else {
            $departments = $departments
                ->when(!empty($company_id), function ($query) use ($company_id) {
                    return $query->where("company", $company_id);
                })
                ->when($api_for == "vladimir", function ($query) {
                    return $query
//          ->with("Company")
                        ->get([
                            "id",
                            "code",
                            "department as name",
                            "company",
                            DB::RAW("(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status"),
                            "deleted_at"
                        ]);
                })
                ->when($api_for == "genus_etd", function ($query) {
                    return $query
                        ->with("Company")
                        ->get(["id", "code", "department as name", "company", "updated_at", "deleted_at"]);
                })
                ->when($api_for == "default", function ($query) {
                    return $query->get(["id", "code", "department as name"]);
                });

            if (count($departments)) {
                $departments = ["departments" => $departments];
            }
        }

        if (count($departments)) {
            return $this->resultResponse("fetch", "Department", $departments);
        } else {
            return $this->resultResponse("not-found", "Department", []);
        }
    }

    public function store(DepartmentRequest $request)
    {

        $new_department = Department::create([
            "code" => $request->code,
            "department" => $request->department,
            "company" => $request->company,
            "voucher_code_id" => $request->voucher_code_id ?? null,
        ]);

//    $fields = $request->validate([
//      "code" => "required",
//      "department" => "required",
//      "company" => "required",
//        "voucher_code_id" => [
//            "nullable",
//            Rule::exists("voucher_codes", "id")->where(function ($query) {
//                return $query->whereNull("deleted_at");
//            })
//        ]
//    ]);
//
//    $department_validateCodeDuplicate = Department::withTrashed()
//      ->where("code", $fields["code"])
//      ->first();
//    if (!empty($department_validateCodeDuplicate)) {
//      return $this->resultResponse("registered", "Code", ["error_field" => "code"]);
//    }
//    $department_validateDescriptionDuplicate = Department::withTrashed()
//      ->where("department", $fields["department"])
//      ->first();
//    if (!empty($department_validateDescriptionDuplicate)) {
//      return $this->resultResponse("registered", "Department", ["error_field" => "department"]);
//    }
//    $companyExist = $this->validateIfObjectExist(new Company(), $fields["company"], "Company");
//    if (!$companyExist) {
//      return $this->resultResponse("not-found", "Company", []);
//    }
//    $new_department = Department::create([
//      "code" => $fields["code"],
//      "department" => $fields["department"],
//      "company" => $fields["company"],
//        "voucher_code_id" => $fields["voucher_code_id"] ?? null,
//    ]);
        return $this->resultResponse("save", "Department", $new_department);
    }

    public function update(DepartmentRequest $request, $id)
    {
        $specific_department = Department::where('id', $id)->first();

        if ($specific_department) {
            $specific_department->code = $request->code;
            $specific_department->department = $request->department;
            $specific_department->company = $request->company;
            $specific_department->voucher_code_id = $request->voucher_code_id ?? null;

            return $this->validateIfNothingChangeThenSave($specific_department, "Department");
        } else {
            return $this->resultResponse("not-found", "Department", []);
        }
//    $specific_department = Department::find($id);
//
//    $fields = $request->validate([
//      "code" => "required",
//      "department" => "required",
//      "company" => "required",
//        "voucher_code_id" => [
//            "nullable",
//            Rule::exists("voucher_codes", "id")->where(function ($query) {
//                return $query->whereNull("deleted_at");
//            })
//        ]
//    ]);
//
//    $department_validateCodeDuplicate = Department::withTrashed()
//      ->where("code", $fields["code"])
//      ->where("id", "<>", $id)
//      ->first();
//    if (!empty($department_validateCodeDuplicate)) {
//      return $this->resultResponse("registered", "Code", ["error_field" => "code"]);
//    }
//    $department_validateDescriptionDuplicate = Department::withTrashed()
//      ->where("department", $fields["department"])
//      ->where("id", "<>", $id)
//      ->first();
//    if (!empty($department_validateDescriptionDuplicate)) {
//      return $this->resultResponse("registered", "Department", ["error_field" => "department"]);
//    }
//    $companyExist = DB::table("departments")
//      ->where("company", "=", $fields["company"])
//      ->first();
//    if (!$companyExist) {
//      return $this->resultResponse("not-registered", "Company", []);
//    }
//
//    if (!$specific_department) {
//      return $this->resultResponse("not-found", "Department", []);
//    } else {
//      $specific_department->code = $fields["code"];
//      $specific_department->department = $fields["department"];
//      $specific_department->company = $fields["company"];
//        $specific_department->voucher_code_id = $fields["voucher_code_id"] ?? null;
//      return $this->validateIfNothingChangeThenSave($specific_department, "Department");
//    }
    }

    public function change_status($id)
    {

        return $this->changeStatus($id, Department::class, "Department");

//    $status = $request["status"];
//    $model = new Department();
//    return $this->change_masterlist_status($status, $model, $id, "Department");
    }

    public function import(Request $request)
    {
        $departments = $request->all();
        $errorBag = [];
        $code_list = Department::withTrashed()->pluck('code')->toArray();
        $department_list = Department::withTrashed()->pluck('department')->toArray();
        $company_list = Company::withTrashed()->pluck('company')->toArray();
        $voucher_code_list = VoucherCode::withTrashed()->pluck('code')->toArray();

        date_default_timezone_set('Asia/Manila');

        $headers = "Code, Department, Company, Voucher Code, Status";
        $template = ["code", "department", "company", "voucher_code", "status"];
        $keys = array_keys(current($departments));
        $this->validateHeader($template, $keys, $headers);

        $index = 2;
        foreach ($departments as $department) {
            $code = $department['code'];
            $dept = $department['department'];
            $company = $department['company'];
            $voucher_code = $department['voucher_code'];
            $status = $department['status'];

            if (in_array($code, $code_list)) {
                $errorBag[] = (object)[
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $code . " is already registered.",
                ];
            }

            if (in_array($dept, $department_list)) {
                $errorBag[] = (object)[
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $dept . " is already registered.",
                ];
            }

            if (!in_array($company, $company_list)) {
                $errorBag[] = (object)[
                    "error_type" => "unregistered",
                    "line" => $index,
                    "description" => $company . " is not registered.",
                ];
            }

            if (!in_array($voucher_code, $voucher_code_list)) {
                $errorBag[] = (object)[
                    "error_type" => "unregistered",
                    "line" => $index,
                    "description" => $voucher_code . " is not registered.",
                ];
            }

            if (!in_array($status, ['active', 'inactive'])) {
                $errorBag[] = (object)[
                    "error_type" => "wrong-format",
                    "line" => $index,
                    "description" => "Status must be Active or Inactive.",
                ];
            }

            foreach ($departments as $key => $value) {
                if (empty($value)) {
                    $errorBag[] = (object)[
                        "error_type" => "empty",
                        "line" => $index,
                        "description" => $key . " is empty.",
                    ];
                }
            }

            $index++;
        }

        if (count($errorBag) || !count($errorBag)) {

            $input_code = array_column($departments, 'code');
            $duplicate_code = array_keys(array_filter(array_count_values($input_code), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_code) > 0) {
                $errorBag[] = (object)[
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_code, $duplicate_code[0])))),
                    'description' => 'Code ' . $duplicate_code[0] . ' has a duplicate in your excel file.'
                ];
            }

            $input_department = array_column($departments, 'department');
            $duplicate_department = array_keys(array_filter(array_count_values($input_department), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_department) > 0) {
                $errorBag[] = (object)[
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_department, $duplicate_department[0])))),
                    'description' => 'Department ' . $duplicate_department[0] . ' has a duplicate in your excel file.'
                ];
            }
        }

        if (!count($errorBag)) {
            $departmentChunks = array_chunk($departments, 300);
            $departmentChunks->each(function ($chunk) use ($departmentChunks) {
                $transformChunk = $chunk->transform(function ($value) {
                    return [
                        'code' => $value['code'],
                        'department' => $value['department'],
                        'company' => Company::where('company', $value['company'])->first()->id,
                        'voucher_code_id' => VoucherCode::where('code', $value['voucher_code'])->first()->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'deleted_at' => strtolower($value['status']) == 'active' ? null : date('Y-m-d H:i:s'),
                    ];
                })->toArray();

                foreach ($transformChunk as $chunk) {
                    Department::create([
                        'code' => $chunk['code'],
                        'department' => $chunk['department'],
                        'company' => $chunk['company'],
                        'voucher_code_id' => $chunk['voucher_code_id'],
                        'created_at' => $chunk['created_at'],
                        'updated_at' => $chunk['updated_at'],
                        'deleted_at' => $chunk['deleted_at'],
                    ]);
                }
            });

            $departmentCollection = collect($departments);

            $active = $departmentCollection->filter(function ($q) {
                return $q['status'] == 'Active';
            })->count();

            $inactive = $departmentCollection->filter(function ($q) {
                return $q['status'] == 'Inactive';
            })->count();

            return response()->json([
                'status' => 'imported',
                'message' => 'Departments successfully imported, ' . $active . ' active rows and, ' . $inactive . ' inactive rows were added.',
            ], 201);
        } else {
            return $this->resultResponse("import-error", "location", $errorBag);
        }
    }
//  public function import(Request $request)
//  {
//    $timezone = "Asia/Dhaka";
//    date_default_timezone_set($timezone);
//
//    $date = date("Y-m-d H:i:s", strtotime("now"));
//    $errorBag = [];
//    $data = $request->all();
//    $data_validation_fields = $request->all();
//    $index = 2;
//    $department_list = Department::withTrashed()->get();
//    $company_list = Company::get();
//    $voucher_code_list = VoucherCode::withTrashed()->pluck("code")->toArray();
//
//    $headers = "Code, Department, Company, Voucher Code, Status";
//    $template = ["code", "department", "company", "voucher_code", "status"];
//    $keys = array_keys(current($data));
//    $this->validateHeader($template, $keys, $headers);
//
//    foreach ($data as $department) {
//      $code = $department["code"];
//      $department_name = $department["department"];
//      $company = $department["company"];
//      $voucher_code = $department["voucher_code"];
//
//      foreach ($department as $key => $value) {
//        if (empty($value)) {
//          $errorBag[] = (object) [
//            "error_type" => "empty",
//            "line" => $index,
//            "description" => $key . " is empty.",
//          ];
//        }
//      }
//
//      if (!empty($code)) {
//        $duplicatedepartmentCode = $this->getDuplicateInputs($department_list, $code, "code");
//        if ($duplicatedepartmentCode->count() > 0) {
//          $errorBag[] = (object) [
//            "error_type" => "existing",
//            "line" => $index,
//            "description" => $code . " is already registered.",
//          ];
//        }
//      }
//
//      if (!empty($department_name)) {
//        $duplicatedepartmentDepartment = $this->getDuplicateInputs($department_list, $department_name, "department");
//        if ($duplicatedepartmentDepartment->count() > 0) {
//          $errorBag[] = (object) [
//            "error_type" => "existing",
//            "line" => $index,
//            "description" => $department_name . " is already registered.",
//          ];
//        }
//      }
//
//      if (!empty($company)) {
//        $unregistercompany = $this->getDuplicateInputs($company_list, $company, "company");
//        if ($unregistercompany->count() == 0) {
//          $errorBag[] = (object) [
//            "error_type" => "unregistered",
//            "line" => $index,
//            "description" => $company . " is not registered.",
//          ];
//        }
//      }
//
//      if (!in_array($voucher_code, $voucher_code_list)) {
//        $errorBag[] = (object) [
//          "error_type" => "unregistered",
//          "line" => $index,
//          "description" => $voucher_code . " voucher code is not registered.",
//        ];
//      }
//      $index++;
//    }
//
//    $original_lines = array_keys($data_validation_fields);
//    $duplicate_code = array_values(
//      array_diff($original_lines, array_keys($this->unique_multidim_array($data_validation_fields, "code")))
//    );
//
//    foreach ($duplicate_code as $line) {
//      $input_code = $data_validation_fields[$line]["code"];
//      $duplicate_data = array_filter($data_validation_fields, function ($query) use ($input_code) {
//        return $query["code"] == $input_code;
//      });
//      $duplicate_lines = implode(
//        ",",
//        array_map(function ($query) {
//          return $query + 2;
//        }, array_keys($duplicate_data))
//      );
//      $firstDuplicateLine = array_key_first($duplicate_data);
//
//      if (empty($data_validation_fields[$line]["code"])) {
//      } else {
//        $errorBag[] = [
//          "error_type" => "duplicate",
//          "line" => (string) $duplicate_lines,
//          "description" =>
//            $data_validation_fields[$firstDuplicateLine]["code"] . " code has a duplicate in your excel file.",
//        ];
//      }
//    }
//
//    $duplicate_department = array_values(
//      array_diff($original_lines, array_keys($this->unique_multidim_array($data_validation_fields, "department")))
//    );
//    foreach ($duplicate_department as $line) {
//      $input_name = $data_validation_fields[$line]["department"];
//      $duplicate_data = array_filter($data_validation_fields, function ($query) use ($input_name) {
//        return $query["department"] == $input_name;
//      });
//      $duplicate_lines = implode(
//        ",",
//        array_map(function ($query) {
//          return $query + 2;
//        }, array_keys($duplicate_data))
//      );
//      $firstDuplicateLine = array_key_first($duplicate_data);
//
//      if (empty($data_validation_fields[$line]["department"])) {
//      } else {
//        $errorBag[] = [
//          "error_type" => "duplicate",
//          "line" => (string) $duplicate_lines,
//          "description" =>
//            $data_validation_fields[$firstDuplicateLine]["department"] .
//            " department has a duplicate in your excel file.",
//        ];
//      }
//    }
//    $errorBag = array_values(array_unique($errorBag, SORT_REGULAR));
//    if (empty($errorBag)) {
//      foreach ($data as $department) {
//        $status_date = strtolower($department["status"]) == "active" ? null : $date;
//        $fields = [
//          "code" => $department["code"],
//          "department" => $department["department"],
//          "company" => Company::where("company", $department["company"])->first()->id,
//            "voucher_code_id" => VoucherCode::where("code", $department["voucher_code"])->first()->id,
//          "created_at" => $date,
//          "updated_at" => $date,
//          "deleted_at" => $status_date,
//        ];
//
//        $inputted_fields[] = $fields;
//      }
//      $count_upload = count($inputted_fields);
//      $inputted_fields = collect($inputted_fields);
//      $chunks = $inputted_fields->chunk(300);
//
//      $active = $inputted_fields
//        ->filter(function ($q) {
//          return $q["deleted_at"] == null;
//        })
//        ->count();
//
//      $inactive = $inputted_fields
//        ->filter(function ($q) {
//          return $q["deleted_at"] != null;
//        })
//        ->count();
//
//      foreach ($chunks as $specific_chunk) {
//        $new_department = DB::table("departments")->insert($specific_chunk->toArray());
//      }
//      return $this->resultResponse("import", "department", $count_upload, $active, $inactive);
//    } else {
//      return $this->resultResponse("import-error", "department", $errorBag);
//    }
//  }
}
