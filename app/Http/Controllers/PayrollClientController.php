<?php

namespace App\Http\Controllers;

use App\Exceptions\FistoException;
use App\Http\Requests\PayrollClientRequest;
use App\Models\PayrollClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollClientController extends Controller
{
  public function index(Request $request)
  {
      $status = $request->status;
      $rows = (int)$request->input('rows', 10);
      $search = $request->search;
      $paginate = $request->input('paginate', 1);

      $payroll_client = PayrollClient::withTrashed()
          ->where(function ($query) use ($status) {
              return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
          })
          ->where('client', 'like', '%' . $search . '%')
          ->latest('updated_at');

      if ($paginate == 1) {
          $payroll_client = $payroll_client
              ->paginate($rows);
      } else if ($paginate == 0) {
          $payroll_client = $payroll_client
              ->get(['id', 'client as name']);
          if (count($payroll_client)) {
              $payroll_client = array("payroll_clients" => $payroll_client);;
          }
      }

      if (count($payroll_client)) {
          return $this->resultResponse('fetch', 'Payroll Client', $payroll_client);
      }
      return $this->resultResponse('not-found', 'Payroll Client', []);
  }

    public function store(PayrollClientRequest $request)
    {

        $payroll_client = PayrollClient::create([
            'client' => $request->client
        ]);

        return $this->resultResponse('save', 'Payroll Client', $payroll_client);

//        $fields = $request->validate([
//            'client' => ['required', 'string']
//        ]);
//
//        $validateDuplicatePayrollClient = PayrollClient::withTrashed()->firstWhere('client', $fields['client']);
//        if (!empty($validateDuplicatePayrollClient))
//            return $this->resultResponse('registered', 'Payroll Client', ["error_field" => "client"]);
//
//        $payroll_client = PayrollClient::create($fields);
//        return $this->resultResponse('save', 'Payroll Client', $payroll_client);
    }

    public function update(PayrollClientRequest $request, $id)
    {

        $payroll_client = PayrollClient::find($id);

        if ($payroll_client) {
            $payroll_client->client = $request->client;

            return $this->validateIfNothingChangeThenSave($payroll_client, 'Payroll client');

        } else {
            return $this->resultResponse('not-found', 'Payroll Client', []);
        }


//        $model = new PayrollClient();
//        $fields = $request->validate([
//            'client' => ['required', 'string']
//        ]);
//
//        $payroll_client = PayrollClient::withTrashed()->find($id);
//        $is_unique = $this->isUnique($model, 'Payroll client', ['client'], [$fields['client']], $id);
//        if (!empty($payroll_client) == true) {
//            $payroll_client->client = $fields['client'];
//            return $this->validateIfNothingChangeThenSave($payroll_client, 'Payroll client');
//        }
//        return $this->resultResponse('not-found', 'Payroll Client', []);
    }

    public function change_status($id)
    {
//    $status = $request['status'];
//    $model = new PayrollClient();
//    return $this->change_masterlist_status($status,$model,$id,'Payroll client');

        return $this->changeStatus($id, PayrollClient::class, 'Payroll client');
    }

}
