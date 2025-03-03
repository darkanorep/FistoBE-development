<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalUserRequest;
use App\Models\JournalUser;
use Illuminate\Http\Request;

class JournalUserController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->search;
        $row = $request->row ?? 10;
        $status = $request->status;

        $journalUsers = JournalUser::with(['approver','users'])
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->whereLike(['approver.first_name',
                'approver.middle_name',
                'approver.last_name',
                'users.first_name',
                'users.middle_name',
                'users.last_name'],
                $search)
            ->paginate($row);

        if (count($journalUsers) > 0) {
            return $this->resultResponse('fetch', 'Journal Users', $journalUsers);
        } else {
            return $this->resultResponse('not-found', 'Journal Users', []);
        }
    }

    public function store(JournalUserRequest $request)
    {
        $approver_id = $request->approver_id;
        $user_id = $request->user_id;

        $journalUser = JournalUser::create([
            'approver_id' => $approver_id,
            'user_id' => $user_id
        ]);

        return $this->resultResponse('save', 'Journal User', $journalUser);
    }

    public function show($id)
    {
        $journalUser = JournalUser::where('id', $id)->with(['approver', 'users'])->first();
        return $journalUser ? $this->resultResponse('fetch','Journal User', $journalUser) : $this->resultResponse('not-found','Journal User', []);
    }


    public function update(JournalUserRequest $request, $id)
    {
        $journalUser = JournalUser::where('id', $id)
            ->with(['approver', 'users'])
            ->first();

        if ($journalUser) {
            $journalUser->update([
                'approver_id' => $request->approver_id,
                'user_id' => $request->user_id
            ]);

            return $this->resultResponse('update','Journal User', $journalUser->load(['approver', 'users']));
        } else {
            return $this->resultResponse('not-found','Journal User', []);
        }

    }

    public function change_status($id)
    {
        return $this->changeStatus($id, JournalUser::class, 'Journal User');
    }
}
