<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function index(Request $request) {

        $status = $request->input('status');
        $rows = (int)$request->input('rows', 10);
        $search = $request->input('search', '');

        $charges = Charge::withTrashed()
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
            })
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%');
            })
            ->latest('updated_at')
            ->paginate($rows);

        if (count($charges)) {
            return $this->resultResponse("fetch", "Charges", $charges);
        } else {
            return $this->resultResponse("not-found", "Charges", []);
        }
    }

    public function store(Request $request) {
        $data = $request->all();

        collect($data)->chunk(300)->each(function ($item, $key) {
            Charge::updateOrCreate(
                ['sync_id' => $item['sync_id']],
                [
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'company_id' => $item['company_id'],
                    'company_code' => $item['company_code'],
                    'company_name' => $item['company_name'],
                    'business_unit_id' => $item['business_unit_id'],
                    'business_unit_code' => $item['business_unit_code'],
                    'business_unit_name' => $item['business_unit_name'],
                    'department_id' => $item['department_id'],
                    'department_code' => $item['department_code'],
                    'department_name' => $item['department_name'],
                    'unit_id' => $item['department_unit_id'],
                    'unit_code' => $item['department_unit_code'],
                    'unit_name' => $item['department_unit_name'],
                    'sub_unit_id' => $item['sub_unit_id'],
                    'sub_unit_code' => $item['sub_unit_code'],
                    'sub_unit_name' => $item['sub_unit_name'],
                    'location_id' => $item['location_id'],
                    'location_code' => $item['location_code'],
                    'location_name' => $item['location_name']
                ]
            );
        });

        return response()->json([
            'message' => 'Charges successfully saved.'
        ], 200);
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, Charge::class, "Charge");
    }
}
