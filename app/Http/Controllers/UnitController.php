<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\Department;
use App\Models\Unit;
use App\Services\GenericServices;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    public function __construct(GenericServices $genericServices)
    {
        $this->genericServices = $genericServices;
    }

    public function index(Request $request)
    {
        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);
        $department_id = $request->input("department_id", null);

        $units = Unit::withTrashed()
            ->with([
                'department:sync_id,business_unit_sync_id,department as name',
                'subUnit'
            ])
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->when($department_id, function ($query) use ($department_id) {
                return $query->where("department_sync_id", $department_id);
            })
            ->where(function ($query) use ($search) {
                $query->where("code", "like", "%" . $search . "%")
                    ->orWhere("name", "like", "%" . $search . "%")
                    ->orWhereHas("department", function ($query) use ($search) {
                        $query->where("department", "like", "%" . $search . "%");
                    });
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $units = $units->paginate($rows);

        } elseif ($paginate == 0) {
            $units = $units->get();
        }

        if (count($units)) {
            $units = ["units" => $units];
        }


        if (count($units)) {
            return $this->resultResponse("fetch", "Unit", $units);
        } else {
            return $this->resultResponse("not-found", "Unit", []);
        }
    }

    public function store(Unit $unit, UnitRequest $request)
    {
        $unit = $this->genericServices->store($unit, $request->validated());

        return $this->resultResponse('save', 'Unit', $unit);
    }

    public function update($id, UnitRequest $request)
    {
        $unit = Unit::find($id);

        if ($unit) {
            $unit = $this->genericServices->update($unit, $request->validated());

            return $this->resultResponse('update', 'Unit', $unit);
        } else {
            return $this->resultResponse('not-found', 'Unit', null);
        }
    }

    public function change_status($id) {
        return $this->changeStatus($id, Unit::class, 'Unit');
    }

    public function import(Request $request) {
        $units = $request->all();
        $errorBag = [];
        $code_list = Unit::withTrashed()->pluck('code')->toArray();
        $unit_list = Unit::withTrashed()->pluck('name')->toArray();
        $department_list = Department::withTrashed()->pluck('department')->toArray();

        date_default_timezone_set('Asia/Manila');

        $headers = "Code, Unit, Department, Status";
        $template = ["code", "unit", "department", "status"];
        $keys = array_keys(current($units));
        $this->validateHeader($template, $keys, $headers);

        $index = 2;
        foreach ($units as $unit) {
            $code = $unit["code"];
            $unitName = $unit["unit"];
            $department = $unit['department'];
            $status = $unit["status"];

            if (in_array($code, $code_list)) {
                $errorBag[] = (object) [
                    'error_type' => 'exist',
                    'line' => $index,
                    'description' => 'Code ' . $code . ' already exist.'
                ];
            }

            if (in_array($unitName, $unit_list)) {
                $errorBag[] = (object) [
                    'error_type' => 'exist',
                    'line' => $index,
                    'description' => 'Unit ' . $unitName . ' already exist.'
                ];
            }

            if (!in_array($status, ['Active', 'Inactive'])) {
                $errorBag[] = (object)[
                    'error_type' => 'wrong-format',
                    'line' => $index,
                    'description' => 'Status must be Active or Inactive.'
                ];
            }

            foreach ($unit as $key => $value) {
                if (empty($value)) {
                    $errorBag[] = (object) [
                        'error_type' => 'empty',
                        'line' => $index,
                        'description' => 'Empty ' . $key . '.'
                    ];
                }
            }

            if (isset($department)) {
                if (!in_array($department, $department_list)) {
                    $errorBag[] = (object) [
                        'error_type' => 'unregistered',
                        'line' => $index,
                        'description' => 'Department ' . $department . ' not registered.'
                    ];
                }
            }

            $index++;
        }

        if (count($errorBag) || !count($errorBag)) {
            $input_code = array_column($units, 'code');
            $duplicate_code = array_keys(array_filter(array_count_values($input_code), function ($value) {
                return $value > 1;
            }));

            if(count($duplicate_code)) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_code, $duplicate_code[0])))),
                    'description' => 'Code ' . $duplicate_code[0] . ' has a duplicate in your excel file.'
                ];
            }

            $input_unit = array_column($units, 'unit');
            $duplicate_unit = array_keys(array_filter(array_count_values($input_unit), function ($value) {
                return $value > 1;
            }));

            if(count($duplicate_unit)) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_unit, $duplicate_unit[0])))),
                    'description' => 'Unit ' . $duplicate_unit[0] . ' has a duplicate in your excel file.'
                ];
            }
        }

        if (!count($errorBag)) {
            $unitChunk = collect($units)->chunk(1000);
            $unitChunk->each(function ($chunk) {
                $transformChunk = $chunk->map(function ($unit) {
                    return [
                        'code' => $unit['code'],
                        'name' => $unit['unit'],
                        'department_id' => Department::where('department', $unit['department'])->first()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'deleted_at' => $unit['status'] === "Active" ? NULL : now(),
                    ];
                });

                Unit::insert($transformChunk->toArray());
            });

            $unitCollection = collect($units);
            $active = $unitCollection
                ->filter(function ($q) {
                    return $q["status"] == 'Active';
                })
                ->count();

            $inactive = $unitCollection
                ->filter(function ($q) {
                    return $q["status"] == 'Inactive';
                })
                ->count();

            return response()->json([
                'status' => 'imported',
                'message' => 'Sub units successfully imported, '. $active . ' active rows and, ' . $inactive . ' inactive rows were added.',
            ], 201);

        }else {
            return $this->resultResponse("import-error", "sub unit", $errorBag);
        }
    }

//    public function store(Request $request)
//    {
////        return Unit::create([
////            'sync_id' => 1,
////            'code' => 'x',
////            'name' => 'x',
////            'department_sync_id' => 1,
////
////        ]);
//        $units = $request->input('result');
//        $errors = [];
//
//        collect($units)->each(function ($unit) use (&$errors) {
//            $sync_id = $unit['id'];
//            $code = $unit['code'];
//            $name = $unit['name'];
//            $department_sync_id = $unit['department']['id'];
//            $deleted_at = $unit['deleted_at'];
//
//            $departmentExist = Department::where('sync_id', $department_sync_id)->exists();
//
//            if (!$departmentExist) {
//                $errors[] = "Department with ID {$department_sync_id} does not exist.";
//                return; // Skip this iteration
//            }
//
//            Unit::updateOrCreate(
//                [
//                    'sync_id' => $sync_id,
//                    'code' => $code,
//                    'name' => $name,
//                ],
//                [
//                    'sync_id' => $sync_id,
//                    'code' => $code,
//                    'name' => $name,
//                    'department_sync_id' => $department_sync_id,
//                    'deleted_at' => $deleted_at ? now() : null,
//                ]
//            );
//        });
//
//        if (!empty($errors)) {
//            return response()->json([
//                'message' => 'Sync Department first before syncing Units.',
//            ], Response::HTTP_BAD_REQUEST);
//        }
//
//        return response()->json([
//            'message' => 'Units successfully synced.',
//        ], Response::HTTP_OK);
//    }

}
