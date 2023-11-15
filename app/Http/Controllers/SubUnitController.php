<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubUnitRequest;
use App\Http\Resources\SubUnitResource;
use App\Models\Department;
use App\Models\SubUnit;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Request;

class SubUnitController extends Controller
{
    public function index(Request $request)
    {
        $status =  $request['status'];
        $rows =  (int) $request->input('rows', 10);
        $search =  $request['search'];
        $paginate = $request->input('paginate', 1);

        $subunit = SubUnit::withTrashed()
            ->where(function ($query) use ($status) {
            return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
        })->where(function ($query) use ($search) {
            $query->where('subunit', 'like', '%' . $search . '%')
                ->orWhere('code', 'like', '%' . $search . '%');
        })->select(['id','code', 'subunit','updated_at', 'deleted_at', 'department_id'])
            ->latest('updated_at');

        if ($paginate == 1) {
            $subunit = $subunit->paginate($rows);
        } else if ($paginate == 0) {
            $subunit = $subunit->get(['id','code', 'subunit']);
        }
        $subunit->transform(function ($value) {
            return [
                'id' => $value->id,
                'code' => $value->code,
                'sub_unit' => $value->subunit,
                'department' => [
                    'id' => $value->department->id,
                    'name' => $value->department->department,
                ],
                'updated_at' => $value->updated_at,
                'deleted_at' => $value->deleted_at,
            ];
        });

        if (count($subunit)) {
            return $this->resultResponse('fetch', 'Sub Unit', $subunit);
        } else {
            return $this->resultResponse('not-found', 'Sub Unit', []);
        }
    }

    public function store(SubUnitRequest $request)
    {
        $new_subunit = SubUnit::create([
            'department_id' => $request->department_id,
            'code' => $request->code,
            'subunit' => $request->sub_unit,
        ]);

        return $this->resultResponse('save','Sub Unit', $new_subunit);
    }

    public function show($id)
    {
        $subunit = SubUnit::where('id', $id)->first();
        return $subunit ? $this->resultResponse('fetch','Sub Unit', $subunit) : $this->resultResponse('not-found','Sub Unit', []);
    }

    public function update(SubUnitRequest $request, $id)
    {
        $subunit = SubUnit::where('id', $id)->first();

        if ($subunit) {
            $subunit->update([
                'department_id' => $request->department_id,
                'code' => $request->code,
                'subunit' => $request->sub_unit,
            ]);

            return $this->resultResponse('update','Sub Unit', $subunit);
        } else {
            return $this->resultResponse('not-found','Sub Unit', []);
        }
    }

    public function change_status($id)
    {
        $data = SubUnit::withTrashed()->find($id);

        if ($data) {
            if ($data->trashed()) {
                $data->restore();

                return $this->resultResponse("restore", 'Sub unit', []);
            } else {
                $data->delete();

                return $this->resultResponse("archive", 'Sub unit', []);
            }
        } else {

            return $this->resultResponse('not-found','Sub Unit', []);
        }
    }

    public function import(Request $request)
    {
        $subunit = $request->all();
        $errorBag = [];
        $department_list = Department::withTrashed()->get()->pluck('department')->toArray();
        $code_list = SubUnit::withTrashed()->pluck('code')->toArray();
        $subunit_list = SubUnit::withTrashed()->pluck('subunit')->toArray();

        date_default_timezone_set('Asia/Manila');

        $headers = "Code, Subunit, Department, Status";
        $template = ["code", "subunit", "department", "status"];
        $keys = array_keys(current($subunit));
        $this->validateHeader($template, $keys, $headers);

        $index = 2;
        foreach ($subunit as $sub) {

            $code = $sub['code'];
            $subunitName = $sub['subunit'];
            $department = $sub['department'];
            $status = $sub['status'];

            if (in_array($code, $code_list)) {
                $errorBag[] = (object) [
                    'error_type' => 'exist',
                    'line' => $index,
                    'description' => 'Code ' . $code . ' already exist.'
                ];
            }

            if (in_array($subunitName, $subunit_list)) {
                $errorBag[] = (object) [
                    'error_type' => 'exist',
                    'line' => $index,
                    'description' => 'Sub unit ' . $subunitName . ' already exist.'
                ];
            }


            if (!in_array($status, ['Active', 'Inactive'])) {
                $errorBag[] = (object)[
                    'error_type' => 'wrong-format',
                    'line' => $index,
                    'description' => 'Status must be Active or Inactive.'
                ];
            }

            foreach ($sub as $key => $value) {
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
//            $input_code = array_count_values(array_map('strval', array_column($subunit, 'code')));
////            $index = 2;
////            foreach ($input_code as $key => $value) {
////                if ($value > 1) {
////                    $errorBag[] = (object) [
////                        'error_type' => 'duplicate',
////                        'line' => $index,
////                        'description' => 'Code ' . $key . ' has a duplicate in your excel file.'
////                    ];
////                }
////                $index++;
////            }

            $input_code = array_column($subunit, 'code');
            $duplicate_code = array_keys(array_filter(array_count_values($input_code), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_code) > 0) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_code, $duplicate_code[0])))),
                    'description' => 'Code ' . $duplicate_code[0] . ' has a duplicate in your excel file.'
                ];
            }

//            $input_subunit = array_count_values(array_map('strval', array_column($subunit, 'subunit')));
//            $index = 2;
//            foreach ($input_subunit as $key => $value) {
//                if ($value > 1) {
//                    $errorBag[] = (object) [
//                        'error_type' => 'duplicate',
//                        'line' => $index,
//                        'description' => 'Sub unit ' . $key . ' has a duplicate in your excel file.'
//                    ];
//                }
//                $index++;
//            }
            $input_subunit = array_column($subunit, 'subunit');
            $duplicate_subunit = array_keys(array_filter(array_count_values($input_subunit), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_subunit) > 0) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_subunit, $duplicate_subunit[0])))),
                    'description' => 'Subunit ' . $duplicate_subunit[0] . ' has a duplicate in your excel file.'
                ];
            }

        }

        if (!count($errorBag)) {
            $subunitChunks = collect($subunit)->chunk(100);

            $subunitChunks->each(function ($chunk) {
                $transformedChunk = $chunk->map(function ($sub) {
                    return [
                        'code' => $sub['code'],
                        'subunit' => $sub['subunit'],
                        'department_id' => Department::where('department', $sub['department'])->first()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'deleted_at' => $sub['status'] === "Active" ? NULL : now(),
                    ];
                })->toArray();

                SubUnit::insert($transformedChunk);
            });

            $subunitCollection = collect($subunit);
            $active = $subunitCollection
                ->filter(function ($q) {
                    return $q["status"] == 'Active';
                })
                ->count();

            $inactive = $subunitCollection
                ->filter(function ($q) {
                    return $q["status"] == 'Inactive';
                })
                ->count();

            return response()->json([
                'status' => 'imported',
                'message' => 'Sub units successfully imported, '. $active . ' active rows and, ' . $inactive . ' inactive rows were added.',
            ], 201);

        } else {
            return $this->resultResponse("import-error", "sub unit", $errorBag);
        }
    }
}
