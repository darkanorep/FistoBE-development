<?php

namespace App\Http\Controllers;

use App\Models\ApSpecialist22Journal;
use App\Services\JournalServices;
use Illuminate\Http\Request;

class ApSpecialist22JournalController extends Controller
{
    private $journalService;

    public function __construct(ApSpecialist22Journal $apSpecialistJournal22)
    {
        $this->journalService = (new JournalServices($apSpecialistJournal22));
    }

    public function index(Request $request)
    {
        return $this->journalService->index($request);
    }

    public function indexForApproval(Request $request)
    {
        return $this->journalService->indexForApproval($request);
    }

    public function store(Request $request)
    {
        return $this->journalService->store($request->all());
    }

    public function updateGeneralJournal($id, Request $request)
    {
        return $this->journalService->updateGeneralJournal($request->all(), $id);
    }

    public function destroy($id)
    {
        return $this->journalService->destroy($id);
    }

    public function action($id)
    {
        return $this->journalService->action($id);
    }

    public function import(Request $request)
    {
        return $this->journalService->import($request);
    }

    public function posted($id)
    {
        return $this->journalService->posted($id);
    }
}
