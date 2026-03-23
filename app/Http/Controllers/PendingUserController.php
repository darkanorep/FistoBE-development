<?php

namespace App\Http\Controllers;

use App\Models\PendingUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PendingUserController extends Controller
{

    public function index(Request $request) {

        $status = $request->get('status');
        $row = (int) $request->get('rows', 10);
        $search = $request->get('search');
        $paginate = $request->get('paginate', 1);


        $pendingUsers = PendingUser::when(isset($status), function ($query) use ($status) {
            return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
        })
            ->where(function ($query) use ($search) {
//                $query->where('id_no', 'like', '%' . $search . '%')
//                    ->orWhere('first_name', 'like', '%' . $search . '%')
//                    ->orWhere('last_name', 'like', '%' . $search . '%');
                $query->whereLike(['id_no', 'first_name', 'last_name'], $search);
            })
            ->latest('updated_at');


        if ($paginate == 1) {
            $pendingUsers = $pendingUsers->paginate($row);
        } elseif ($paginate == 0) {
            $pendingUsers = $pendingUsers->get();
        }

        if (count($pendingUsers) > 0) {
            return response()->json(['pending_users' => $pendingUsers], 200);
        } else {
            return response()->json(['message' => 'No pending users found.'], 404);
        }
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'id_no' => 'required',
            'id_prefix' => 'required',
            'first_name' => 'required',
            'middle_name' => 'nullable',
            'last_name' => 'required',
            'suffix' => 'nullable',
            'department' => 'nullable',
            'position' => 'nullable',
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::withTrashed()
            ->where('id_no', $validated['id_no'])
            ->where('id_prefix', $validated['id_prefix'])
            ->first();

        if (!$user) {
            // User doesn't exist, create pending user
            PendingUser::withTrashed()->updateOrCreate(
                [
                    'id_no' => $validated['id_no'],
                    'id_prefix' => $validated['id_prefix'],
                ],
                [
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'suffix' => $validated['suffix'] ?? null,
                    'department' => $validated['department'] ?? null,
                    'position' => $validated['position'] ?? null,
                    'username' => $validated['username'],
                    'password' => Hash::make($validated['password']),
                    'deleted_at' => null, // Restore if soft-deleted
                ]
            );

            return response()->json(['message' => 'Pending user created successfully.'], 201);
        }

        // User exists, update username and password
        $user->update([
            'username' => $validated['username'],
            'password' => bcrypt($validated['password']),
        ]);

        return response()->json(['message' => 'User updated successfully.'], 200);
    }

    public function changePassword(Request $request, $id_prefix_id_no) {
        $validated = $request->validate([
            'old_password' => 'required',
            'password' => 'required',
        ]);
        // Split prefix and ID number
        list($id_prefix, $id_no) = explode('-', $id_prefix_id_no);

        $user = User::withTrashed()
            ->where('id_prefix', $id_prefix)
            ->where('id_no', $id_no)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!Hash::check($validated['old_password'], $user->password)) {
            return response()->json(['message' => 'Old password is incorrect.'], 404);
        }

        $user->password = $request->password;
        $user->save();

        if ($user) {
            $user->update([
                'password' => bcrypt($request->password),
            ]);

            return response()->json(['message' => 'Password changed successfully.'], 200);
        }
    }

    public function resetPassword($id_prefix_id_no) {
        list($id_prefix, $id_no) = explode('-', $id_prefix_id_no);

        $user = User::withTrashed()
            ->where('id_prefix', $id_prefix)
            ->where('id_no', $id_no)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->password = bcrypt($user->username);
        $user->save();

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }
}
