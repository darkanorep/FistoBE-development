<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset22Journal;
use App\Services\JournalServices;
use Illuminate\Http\Request;

class FixedAsset22JournalController extends Controller
{
    /**
     * @var JournalServices
     */
    private $journalService;

    public function __construct(FixedAsset22Journal $fixedAsset22Journal) {
        $this->journalService = (new JournalServices($fixedAsset22Journal));
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
