<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Charge;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\SubUnit;
use App\Models\Unit;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    private $companies;
    private $businessUnits;
    private $departments;
    private $units;
    private $subUnits;
    private $locations;

    public function __construct()
    {
        $this->companies = Company::whereNotNull('sync_id')->select('sync_id', 'code', 'company as name', 'id')->get();
        $this->businessUnits = BusinessUnit::whereNotNull('sync_id')->select('sync_id', 'code', 'business_unit as name', 'id')->get();
        $this->departments = Department::whereNotNull('sync_id')->select('sync_id', 'code', 'department as name', 'id')->get();
        $this->units = Unit::whereNotNull('sync_id')->select('sync_id', 'code', 'name', 'id')->get();
        $this->subUnits = SubUnit::whereNotNull('sync_id')->select('sync_id', 'code', 'name', 'id')->get();
        $this->locations = Location::whereNotNull('sync_id')->select('sync_id', 'code', 'location as name', 'id')->get();
    }

    public function index(Request $request) {

        $status = $request->input('status');
        $rows = (int)$request->input('rows', 10);
        $search = $request->input('search', '');
        $paginate = $request->input('paginate', true);

        $charges = Charge::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
            })
            ->whereLike(['code', 'name'], $search)
            ->latest('updated_at');

        if ($paginate) {
            $charges = $charges->paginate($rows);
        } else {
            $charges = $charges->get();
        }

        if (count($charges)) {
            return $this->resultResponse("fetch", "Charges", $charges);
        } else {
            return $this->resultResponse("not-found", "Charges", []);
        }
    }

    public function sync_from_one_rdf(Request $request) {
        $data = $request->all();
        $errors = [];

        foreach ($data as $item) {
            Charge::updateOrCreate(
                ['sync_id' => $item['sync_id']],
                [
                    'code' => $item['code'] ?? null,
                    'name' => $item['name'] ?? null,
                    'company_id' => $this->companies->where('name', $item['company_name'])->first()->id ?? null,
                    'company_code' => $item['company_code'] ?? null,
                    'company_name' => $item['company_name'] ?? null,
                    'business_unit_id' => $this->businessUnits->where('name', $item['business_unit_name'])->first()->id ?? null,
                    'business_unit_code' => $item['business_unit_code'] ?? null,
                    'business_unit_name' => $item['business_unit_name'] ?? null,
                    'department_id' => $this->departments->where('name', $item['department_name'])->first()->id ?? null,
                    'department_code' => $item['department_code'] ?? null,
                    'department_name' => $item['department_name'] ?? null,
                    'unit_id' => $this->units->where('name', $item['department_unit_name'])->first()->id ?? null,
                    'unit_code' => $item['department_unit_code'] ?? null,
                    'unit_name' => $item['department_unit_name'] ?? null,
                    'sub_unit_id' => $this->subUnits->where('name', $item['sub_unit_name'])->first()->id ?? null,
                    'sub_unit_code' => $item['sub_unit_code'] ?? null,
                    'sub_unit_name' => $item['sub_unit_name'] ?? null,
                    'location_id' => $this->locations->where('name', $item['location_name'])->first()->id ?? null,
                    'location_code' => $item['location_code'] ?? null,
                    'location_name' => $item['location_name'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Charges successfully saved.'
        ], 200);
    }
    public function store(Request $request) {
        $data = $request->all();
        $errors = [];

        collect($data['data'])->chunk(300)->each(function ($chunk) use (&$errors) {
            foreach ($chunk as $item) {
                if (!isset($item['id'])) {
                    $errors[] = [
                        'column' => 'id',
                        'description' => 'Missing id in input data.',
                        'data' => $item
                    ];
                    continue;
                }
                Charge::withTrashed()->updateOrCreate(
                    ['sync_id' => $item['id']],
                    [
                        'code' => $item['code'] ?? null,
                        'name' => $item['name'] ?? null,
                        'company_id' => $this->companies->where('name', $item['company_name'])->first()->id ?? null,
                        'company_code' => $item['company_code'] ?? null,
                        'company_name' => $item['company_name'] ?? null,
                        'business_unit_id' => $this->businessUnits->where('name', $item['business_unit_name'])->first()->id ?? null,
                        'business_unit_code' => $item['business_unit_code'] ?? null,
                        'business_unit_name' => $item['business_unit_name'] ?? null,
                        'department_id' => $this->departments->where('name', $item['department_name'])->first()->id ?? null,
                        'department_code' => $item['department_code'] ?? null,
                        'department_name' => $item['department_name'] ?? null,
                        'unit_id' => $this->units->where('name', $item['unit_name'])->first()->id ?? null,
                        'unit_code' => $item['unit_code'] ?? null,
                        'unit_name' => $item['unit_name'] ?? null,
                        'sub_unit_id' => $this->subUnits->where('name', $item['sub_unit_name'])->first()->id ?? null,
                        'sub_unit_code' => $item['sub_unit_code'] ?? null,
                        'sub_unit_name' => $item['sub_unit_name'] ?? null,
                        'location_id' => $this->locations->where('name', $item['location_name'])->first()->id ?? null,
                        'location_code' => $item['location_code'] ?? null,
                        'location_name' => $item['location_name'] ?? null
                    ]
                );


                if (!empty($item['deleted_at'])) {
                    $charge = Charge::withTrashed()->where('code', $item['code'])->get();
                    foreach ($charge as $c) {
                        if (!$c->trashed()) {
                            $c->delete();
                        }
                    }
                } else {
                    $charge = Charge::withTrashed()->where('code', $item['code'])->get();
                    foreach ($charge as $c) {
                        if ($c->trashed()) {
                            $c->restore();
                        }
                    }
                }
            }
        });

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Some records have missing id.',
                'errors' => $errors
            ], 422);
        }

            return response()->json([
                'message' => 'Charges successfully saved.'
            ], 200);
    }

    public function show($id) {
        $charge = Charge::withTrashed()->find($id);

        if ($charge) {
            return $this->resultResponse("fetch", "Charge", $charge);
        } else {
            return $this->resultResponse("not-found", "Charge", []);
        }
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, Charge::class, "Charge");
    }

    public function searchCharging(Request $request) {
        $code = $request->input('one_charging_code');

        $data = Charge::where('code', $code)->first();

        if ($data) {
            return $this->resultResponse("fetch", "Charge", $data);
        } else {
            return $this->resultResponse("not-found", "Charge", []);
        }
    }
}
