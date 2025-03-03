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
        $settings = $this->db->select('key', 'value')->get();
        return response()->json($settings);
    }

    public function toggleEntry(Request $request)
    {
        $entryEnabled = $this->db->where('key', 'entry_enabled')->first();

        if ($entryEnabled->value == 1) {
            DB::table('settings')->where('key', 'entry_enabled')->update(['value' => 0]);
        } else {
            DB::table('settings')->where('key', 'entry_enabled')->update(['value' => 1]);
        }

        return response()->json(['message' => 'Entry status updated']);
    }
}
