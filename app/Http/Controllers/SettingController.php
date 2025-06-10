<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    protected $db;
    public function __construct()
    {
        $this->db = DB::table('settings');
    }

    public function index()
    {
        $settings = $this->db->select('id', 'key', 'value', 'value1')->get();
        return response()->json($settings);
    }

    public function update(Request $request, $id)
    {
        $data = $request->only(['value1']);
        $this->db->where('id', $id)->update($data);
        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function toggleEntry($id)
    {
        $entryEnabled = $this->db
            ->where('id', $id)
            ->first();

        if ($entryEnabled->value == 1) {
            DB::table('settings')->where('id', $id)
                ->update(['value' => 0]);
        } else {
            DB::table('settings')->where('id', $id)
                ->update(['value' => 1]);
        }

        return response()->json(['message' => 'Status updated']);
    }
}
