<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\Department;
use App\Models\Unit;
use App\Services\GenericServices;
use Illuminate\Http\Request;

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

        $units = Unit::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
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

        $units->transform(function ($unit) {
            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'department' => [
                    'id' => $unit->department_id,
                    'name' => $unit->department->department
                ],
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at,
                'deleted_at' => $unit->deleted_at,
            ];
        });

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

}
