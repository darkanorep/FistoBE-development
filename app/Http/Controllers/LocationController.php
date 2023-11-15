<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
  public function index(Request $request)
  {
    $status = $request["status"];
    $rows = empty($request["rows"]) ? 10 : (int) $request["rows"];
    $search = $request["search"];
    $paginate = isset($request["paginate"]) ? $request["paginate"] : ($paginate = 1);
    $department_id = $request["department_id"];

    // System Name
    $api_for = $request->input("api_for", "default");

    $locations = Location::withTrashed()
      ->when($paginate === 1, function ($query) {
        return $query->with("departments");
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
        $query
          ->where("code", "like", "%" . $search . "%")
          ->orWhere("location", "like", "%" . $search . "%")
          ->orWhereHas("departments", function ($query) use ($search) {
            return $query->where("department", "like", "%" . $search . "%");
          });
      })
      ->latest("updated_at");

    if ($paginate == 1) {
      $locations = $locations->paginate($rows);
    } else {
      $locations = $locations
        ->when(!empty($department_id), function ($query) use ($department_id) {
          return $query->whereHas("departments", function ($query) use ($department_id) {
            return $query->where("departments.id", $department_id);
          });
        })
        ->when($api_for == "vladimir", function ($query) {
          return $query->with("departments")->get(["id", "code", "location as name",
          DB::RAW("(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status"),
          "deleted_at"]);

          // return $query->get([
          //   "id",
          //   "code",
          //   "location as name",
          //   DB::RAW("(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status"),
          // ]);
        })
        ->when($api_for == "genus_etd", function ($query) {
          return $query->with("departments")->get(["id", "code", "location as name", "updated_at", "deleted_at"]);
        })
        ->when($api_for == "default", function ($query) {
          return $query->get(["id", "code", "location as name"]);
        });

      if (count($locations)) {
        $locations = ["locations" => $locations];
      }
    }

    if (count($locations)) {
      return $this->resultResponse("fetch", "Location", $locations);
    }
    return $this->resultResponse("not-found", "Location", []);
  }

  public function store(Request $request)
  {
    $fields = $request->validate([
      "code" => "required",
      "location" => "required",
      "departments" => "required",
    ]);

    $location_validateCodeDuplicate = Location::withTrashed()
      ->where("code", $fields["code"])
      ->first();
    if (!empty($location_validateCodeDuplicate)) {
      return $this->resultResponse("registered", "Code", ["error_field" => "code"]);
    }
    $location_validateDescriptionDuplicate = Location::withTrashed()
      ->where("location", $fields["location"])
      ->first();
    if (!empty($location_validateDescriptionDuplicate)) {
      return $this->resultResponse("registered", "Location", ["error_field" => "location"]);
    }
    $departmentExist = $this->validateIfObjectsExistByLocationStore(
      new Department(),
      $fields["departments"],
      "Department"
    );

    $new_location = Location::create([
      "code" => $fields["code"],
      "location" => $fields["location"],
    ]);
    $new_location->departments()->attach($fields["departments"]);
    return $this->resultResponse("save", "Location", $new_location);
  }

  public function update(Request $request, $id)
  {
    $company = new Company();
    $specific_location = Location::find($id);

    if (!$specific_location) {
      return $this->resultResponse("not-found", "Location", []);
    }

    $fields = $request->validate([
      "code" => "required",
      "location" => "required",
      "departments" => "required",
    ]);

    $location_validateCodeDuplicate = Location::withTrashed()
      ->where("code", $fields["code"])
      ->where("id", "<>", $id)
      ->first();
    if (!empty($location_validateCodeDuplicate)) {
      return $this->resultResponse("registered", "Code", ["error_field" => "code"]);
    }
    $location_validateDescriptionDuplicate = Location::withTrashed()
      ->where("location", $fields["location"])
      ->where("id", "<>", $id)
      ->first();
    if (!empty($location_validateDescriptionDuplicate)) {
      return $this->resultResponse("registered", "Location", ["error_field" => "location"]);
    }

    $departmentExist = $this->validateIfObjectsExistByLocationStore(
      new Department(),
      $fields["departments"],
      "Department"
    );

    $is_associates_modified = $this->isTaggedArrayModified(
      $fields["departments"],
      $specific_location->departments()->get(),
      "id"
    );
    $specific_location->code = $fields["code"];
    $specific_location->location = $fields["location"];
    $specific_location->departments()->sync(array_unique($fields["departments"]));
    return $this->validateIfNothingChangeThenSave($specific_location, "Location", $is_associates_modified);
  }

  public function change_status(Request $request, $id)
  {
    $status = $request["status"];
    $model = new Location();
    return $this->change_masterlist_status($status, $model, $id, "Location");
  }

//  public function group_and_merge($raw_location_data)
//  {
//    $locations = $raw_location_data->unique("location")->values();
//    $department_list_raw = $raw_location_data->pluck("department");
//    $department_list = Department::all();
//
//    $errorBag = $this->validateIfObjectsExistByLocation(new Department(), $department_list_raw, "Department");
//    $department_per_locations = $raw_location_data->mapToGroups(function ($item, $key) use ($department_list) {
//      $dep = $item["department"];
//      return [
//        $item["location"] => $department_list
//          ->filter(function ($query) use ($dep) {
//            return strtolower($query["department"]) == strtolower($dep);
//          })
//          ->values()
//          ->first()["id"],
//      ];
//    });
//
//    $location_object = collect();
//    foreach ($locations as $k => $v) {
//      $location_code = $locations[$k]["code"];
//      $location_name = $locations[$k]["location"];
//      $departments = $department_per_locations["$location_name"]->unique()->values();
//      $location_object->push([
//        "code" => $location_code,
//        "location" => $location_name,
//        "department" => $departments,
//        "status" => $locations[$k]["status"],
//      ]);
//    }
//
//    return collect(["errorBag" => $errorBag, "location" => $location_object]);
//  }
//
//  public function import(Request $request)
//  {
//    $groupAndMergeResult = $this->group_and_merge(collect($request));
//    $timezone = "Asia/Dhaka";
//    date_default_timezone_set($timezone);
//    $date = date("Y-m-d H:i:s", strtotime("now"));
//    $errorBag = [];
//    $data = $groupAndMergeResult["location"];
//    $index = 2;
//    $location_list = Location::withTrashed()->get();
//    $department_list = Department::all();
//    $headers = "Code, Location, Department, Status";
//    $template = ["code", "location", "department", "status"];
//    $keys = array_keys(current(current($data)));
//
//    $errorBag = $groupAndMergeResult["errorBag"];
//    $this->validateHeader($template, $keys, $headers);
//
//    foreach ($data as $location) {
//      $code = $location["code"];
//      $location_name = $location["location"];
//      $departments = $location["department"];
//
//      foreach ($location as $key => $value) {
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
//        $duplicatelocationCode = $this->getDuplicateInputs($location_list, $code, "code");
//        if ($duplicatelocationCode->count() > 0) {
//          $errorBag[] = (object) [
//            "error_type" => "existing",
//            "line" => $index,
//            "description" => $code . " is already registered.",
//          ];
//        }
//      }
//
//      if (!empty($location_name)) {
//        $duplicatelocationLocation = $this->getDuplicateInputs($location_list, $location_name, "location");
//        if ($duplicatelocationLocation->count() > 0) {
//          $errorBag[] = (object) [
//            "error_type" => "existing",
//            "line" => $index,
//            "description" => $location_name . " is already registered.",
//          ];
//        }
//      }
//
//      $index++;
//    }
//
//    $errorBag = array_values(array_unique($errorBag, SORT_REGULAR));
//    if (empty($errorBag)) {
//      foreach ($data as $location) {
//        $status_date = strtolower($location["status"]) == "active" ? null : $date;
//
//        $fields = [
//          "code" => $location["code"],
//          "location" => $location["location"],
//          "created_at" => $date,
//          "updated_at" => $date,
//          "deleted_at" => $status_date,
//        ];
//
//        $fields["departments"] = $location["department"];
//        $inputted_fields[] = $fields;
//      }
//
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
//        $specific_chunk_to_insert = [];
//        foreach ($specific_chunk as $key => $chunk) {
//          $specific_chunk_to_insert[$key]["code"] = $chunk["code"];
//          $specific_chunk_to_insert[$key]["location"] = $chunk["location"];
//          $specific_chunk_to_insert[$key]["created_at"] = $chunk["created_at"];
//          $specific_chunk_to_insert[$key]["updated_at"] = $chunk["updated_at"];
//          $specific_chunk_to_insert[$key]["deleted_at"] = $chunk["deleted_at"];
//        }
//
//        $new_location = DB::table("locations")->insert($specific_chunk_to_insert);
//        foreach ($specific_chunk->toArray() as $chunk) {
//          $location = Location::withTrashed()
//            ->where("code", $chunk["code"])
//            ->first();
//          $location->departments()->attach($chunk["departments"]);
//        }
//      }
//      return $this->resultResponse("import", "location", $count_upload, $active, $inactive);
//    } else {
//      return $this->resultResponse("import-error", "location", $errorBag);
//    }
//  }

    public function import(Request $request) {

        $locations = $request->all();
        $errorBag = [];
        $code_list = Location::withTrashed()->pluck("code")->toArray();
        $location_list = Location::withTrashed()->pluck("location")->toArray();
        $department_list = Department::withTrashed()->pluck("department")->toArray();

        date_default_timezone_set('Asia/Manila');

        $headers = "Code, Location, Department, Status";
        $template = ["code", "location", "department", "status"];
        $keys = array_keys(current($locations));
        $this->validateHeader($template, $keys, $headers);

        $index = 2;
        foreach ($locations as $loc) {
            $location = $loc['location'];
            $code = $loc['code'];
            $department = $loc['department'];
            $status = $loc['status'];

            if(in_array($code, $code_list)) {
                $errorBag[] = (object) [
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $code . " is already registered.",
                ];
            }

            if(in_array($location, $location_list)) {
                $errorBag[] = (object) [
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $location . " is already registered.",
                ];
            }

            if(!in_array($department, $department_list)) {
                $errorBag[] = (object) [
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $department . " is not registered.",
                ];
            }

            if(!in_array($status, ['Active', 'Inactive'])) {
                $errorBag[] = (object) [
                    "error_type" => "wrong-format",
                    "line" => $index,
                    "description" => "Status must be Active or Inactive.",
                ];
            }

            foreach ($loc as $key => $value) {
                if (empty($value)) {
                    $errorBag[] = (object) [
                        "error_type" => "empty",
                        "line" => $index,
                        "description" => $key . " is empty.",
                    ];
                }
            }

            $index++;
        }

        if (count($errorBag) || !count($errorBag)) {

            $input_code = array_column($locations, 'code');
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

            $input_location = array_column($locations, 'location');
            $duplicate_location = array_keys(array_filter(array_count_values($input_location), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_location) > 0) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, array_keys($input_location, $duplicate_location[0]))),
                    'description' => 'Location ' . $duplicate_location[0] . ' has a duplicate in your excel file.'
                ];
            }
        }

        if (!count($errorBag)) {
            $locationChunks = collect($locations)->chunk(300);
            $locationChunks->each(function ($chunk) use ($locations) {
                $transformChunk = $chunk->map(function ($value) {
                    return [
                        'code' => $value['code'],
                        'location' => $value['location'],
                        'department' => Department::withTrashed()->where('department', $value['department'])->first()->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'deleted_at' => strtolower($value['status']) == 'active' ? null : date('Y-m-d H:i:s'),
                    ];
                })->toArray();

                foreach ($transformChunk as $chunk) {
                    $new_location = Location::create([
                        'code' => $chunk['code'],
                        'location' => $chunk['location'],
                        'created_at' => $chunk['created_at'],
                        'updated_at' => $chunk['updated_at'],
                        'deleted_at' => $chunk['deleted_at'],
                    ]);
                    $new_location->departments()->attach($chunk['department']);
                }
            });
            $locationCollection = collect($locations);
            $active = $locationCollection
                ->filter(function ($q) {
                    return $q["status"] == 'Active';
                })
                ->count();

            $inactive = $locationCollection
                ->filter(function ($q) {
                    return $q["status"] == 'Inactive';
                })
                ->count();

            return response()->json([
                'status' => 'imported',
                'message' => 'Locations successfully imported, '. $active . ' active rows and, ' . $inactive . ' inactive rows were added.',
            ], 201);

        } else {
            return $this->resultResponse("import-error", "location", $errorBag);
        }

    }
}
