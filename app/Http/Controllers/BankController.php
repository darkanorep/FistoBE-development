<?php

namespace App\Http\Controllers;

use App\Exceptions\FistoException;

use App\Http\Requests\BankRequest;
use App\Models\Bank;
use App\Models\AccountTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
  public function index(Request $request)
  {
    $status =  $request['status'];
    $rows =  (empty($request['rows']))?10:(int)$request['rows'];
    $search =  $request['search'];
    $paginate = (isset($request['paginate']))? $request['paginate']:$paginate = 1;
    $account_title_id = (isset($request['account_title_id']))? $request['account_title_id']:NULL;

    $banks = Bank::withTrashed()
//    ->with('AccountTitleOne')
//    ->with('AccountTitleTwo')
        ->with([
            'AccountTitleOne',
            'AccountTitleTwo',
            'CompanyOne',
            'CompanyTwo',
            'BusinessUnitOne',
            'BusinessUnitTwo',
            'DepartmentOne',
            'DepartmentTwo',
            'SubUnitOne',
            'SubUnitTwo',
            'LocationOne',
            'LocationTwo'
        ])
    ->where(function ($query) use ($status){
      return ($status==true) ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
    })
    ->where(function ($query) use ($search) {
      $query->where('banks.code', 'like', '%'.$search.'%')
      ->orWhere('banks.name', 'like', '%'.$search.'%')
      ->orWhere('banks.branch', 'like', '%'.$search.'%')
      ->orWhere('banks.account_no', 'like', '%'.$search.'%')
      ->orWhere('banks.location', 'like', '%'.$search.'%');
    })
    ->latest('updated_at');

   if($paginate == 0){
     $banks = $banks
    //  ->without('AccountTitleOne')
    //  ->without('AccountTitleTwo')
     ->get(['account_title_1','account_title_2','id','name','branch', 'code', 'account_no', 'location', 'account_title_1', 'account_title_2', 'company_id_1', 'company_id_2', 'business_unit_id_1', 'business_unit_id_2', 'department_id_1', 'department_id_2', 'sub_unit_id_1', 'sub_unit_id_2', 'location_id_1', 'location_id_2']);
     $banks = ["banks"=>$banks];
    }else{
      $banks = $banks->paginate($rows);

    }


    if(count($banks)==true){
      return $this->resultResponse('fetch','Bank',$banks);
    }
    return $this->resultResponse('not-found','Bank',[]);
  }

  public function store(BankRequest $request)
  {
      $new_bank = Bank::create([
          'code' => $request->code,
          'name' => $request->name,
          'branch' => $request->branch,
          'account_no' => $request->account_no,
          'location' => $request->location,
          'account_title_1' => $request->account_title_1,
          'account_title_2' => $request->account_title_2,
          'company_id_1' => $request->company_id_1,
          'business_unit_id_1' => $request->business_unit_id_1,
          'business_unit_id_2' => $request->business_unit_id_2,
          'department_id_1' => $request->department_id_1,
          'department_id_2' => $request->department_id_2,
          'sub_unit_id_1' => $request->sub_unit_id_1,
          'sub_unit_id_2' => $request->sub_unit_id_2,
          'location_id_1' => $request->location_id_1,
          'location_id_2' => $request->location_id_2,
      ]);

      return $this->resultResponse('save','Bank', $new_bank);
  }

  public function update(BankRequest $request, $id)
  {
      $specific_bank = Bank::where('id', $id)->first();

      if($specific_bank) {
          $specific_bank->update([
              'code' => $request->code,
              'name' => $request->name,
              'branch' => $request->branch,
              'account_no' => $request->account_no,
              'location' => $request->location,
              'account_title_1' => $request->account_title_1,
              'account_title_2' => $request->account_title_2,
              'company_id_1' => $request->company_id_1,
              'company_id_2' => $request->company_id_2,
              'business_unit_id_1' => $request->business_unit_id_1,
              'business_unit_id_2' => $request->business_unit_id_2,
              'department_id_1' => $request->department_id_1,
              'department_id_2' => $request->department_id_2,
              'sub_unit_id_1' => $request->sub_unit_id_1,
              'sub_unit_id_2' => $request->sub_unit_id_2,
              'location_id_1' => $request->location_id_1,
              'location_id_2' => $request->location_id_2,
          ]);

          return $this->resultResponse('update','Bank', $specific_bank);
      } else {
          return $this->resultResponse('not-found','Bank',[]);
      }
  }

  public function change_status(Request $request,$id){
    $status = $request['status'];
    $model = new Bank();
    return $this->change_masterlist_status($status,$model,$id,'Bank');
  }

  public function import(Request $request)
  {
    $bank_masterlist = Bank::withTrashed()->get();
    $account_title_masterlist = AccountTitle::withTrashed()->get();
    $account_title_masterlist_array = $account_title_masterlist->toArray();
    $account_title_titles =  array_column($account_title_masterlist_array,'title');
    $timezone = "Asia/Dhaka";
    date_default_timezone_set($timezone);
    $date = date("Y-m-d H:i:s", strtotime('now'));

    $errorBag = [];
    $data = $request->all();
    $data_validation_fields = $request->all();
    $index = 2;

    $headers = 'Code, Name, Branch, Account No, Location, Account Title 1, Account Title 2, Status';
    $template = ['code','name','branch','account_no','location','account_title_1','account_title_2','status'];
    $keys = array_keys(current($data));
    $this->validateHeader($template,$keys,$headers);

    foreach ($data as $bank) {
      $code = $bank['code'];
      $name = $bank['name'];
      $branch = $bank['branch'];
      $account_no = $bank['account_no'];
      $location = $bank['location'];
      $account_title_1 = $bank['account_title_1'];
      $account_title_2 = $bank['account_title_2'];
      foreach($bank as $key=>$value){
        if(empty($value)){
          $errorBag[] = [
            "error_type" => "empty",
            "line" => $index,
            "description" => $key." is empty."
          ];
        }
      }
      if (!empty($code)) {

        $duplicateCode = $this->getDuplicateInputs($bank_masterlist,$code,'code');
        if ($duplicateCode->count() > 0)
          $errorBag[] = (object) [
            "error_type" => "existing",
            "line" => $index,
            "description" => $code. " is already registered."
          ];
      }
      // if (!empty($branch)) {
      //   $duplicateBranch = $this->getDuplicateInputs($bank_masterlist,$branch,'branch');
      //   if ($duplicateBranch->count() > 0)
      //     $errorBag[] = (object) [
      //       "error_type" => "existing",
      //       "line" => $index,
      //       "description" => $branch. " is already registered."
      //     ];
      // }
      if (!empty($account_no)) {
        $duplicateAccountNo = $this->getDuplicateInputs($bank_masterlist,$account_no,'account_no');
        if ($duplicateAccountNo->count() > 0)
          $errorBag[] = (object) [
            "error_type" => "existing",
            "line" => $index,
            "description" => $account_no. " is already registered."
          ];
      }


      if (!empty($account_title_1)) {
        if(!in_array($account_title_1,$account_title_titles)){
          $errorBag[] = (object) [
            "error_type" => "unregistered",
            "line" => $index,
            "description" => $account_title_1. " is not registered."
          ];
        };
      }

      if (!empty($account_title_2)) {
        if(!in_array($account_title_2,$account_title_titles)){
          $errorBag[] = (object) [
            "error_type" => "unregistered",
            "line" => $index,
            "description" => $account_title_2. " is not registered."
          ];
        };
      }
      $index++;
    }

    $original_lines = array_keys($data_validation_fields);
    $duplicate_code = array_values(array_diff($original_lines,array_keys($this->unique_multidim_array($data_validation_fields,'code'))));

    foreach($duplicate_code as $line){
      $input_code = $data_validation_fields[$line]['code'];
      $duplicate_data =  array_filter($data_validation_fields, function ($query) use($input_code){
        return ($query['code'] == $input_code);
      });
      $duplicate_lines =  implode(",",array_map(function($query){return $query+2;},array_keys($duplicate_data)));
      $firstDuplicateLine =  array_key_first($duplicate_data);

      if((empty($data_validation_fields[$line]['code']))){
      }else{
        $errorBag[] = [
          "error_type" => "duplicate",
          "line" => (string) $duplicate_lines,
          "description" =>  $data_validation_fields[$firstDuplicateLine]['code'].' code has a duplicate in your excel file.'
        ];
      }
    }

    // $duplicate_branch = array_values(array_diff($original_lines,array_keys($this->unique_multidim_array($data_validation_fields,'branch'))));
    // foreach($duplicate_branch as $line){
    //   $input_branch = $data_validation_fields[$line]['branch'];
    //   $duplicate_data =  array_filter($data_validation_fields, function ($query) use($input_branch){
    //     return ($query['branch'] == $input_branch);
    //   });
    //   $duplicate_lines =  implode(",",array_map(function($query){
    //     return $query+2;
    //   },array_keys($duplicate_data)));
    //   $firstDuplicateLine =  array_key_first($duplicate_data);

    //   if((empty($data_validation_fields[$line]['branch']))){
    //   }else{
    //     $errorBag[] = [
    //       "error_type" => "duplicate",
    //       "line" => (string) $duplicate_lines,
    //       "description" =>  $data_validation_fields[$firstDuplicateLine]['branch'].' Branch has a duplicate in your excel file.'
    //     ];
    //   }
    // }

    $errorBag = array_values(array_unique($errorBag,SORT_REGULAR));

    $duplicate_account_no = array_values(array_diff($original_lines,array_keys($this->unique_multidim_array($data_validation_fields,'account_no'))));
    foreach($duplicate_account_no as $line){

      $input_account_no = $data_validation_fields[$line]['account_no'];
      $duplicate_data =  array_filter($data_validation_fields, function ($query) use($input_account_no){
        return ($query['account_no'] == $input_account_no);
      });
      $duplicate_lines =  implode(",",array_map(function($query){
        return $query+2;
      },array_keys($duplicate_data)));
      $firstDuplicateLine =  array_key_first($duplicate_data);

      if((empty($data_validation_fields[$line]['account_no']))){
      }else{
        $errorBag[] = [
          "error_type" => "duplicate",
          "line" => (string) $duplicate_lines,
          "description" =>  $data_validation_fields[$firstDuplicateLine]['account_no'].' Account Number has a duplicate in your excel file.'
        ];
      }
    }

    if (empty($errorBag)) {
      foreach ($data as $bank) {
        $status_date = (strtolower($bank['status'])=="active"?NULL:$date);
        $fields = [
          'code' => $bank['code'],
          'name' => $bank['name'],
          'branch' => $bank['branch'],
          'account_no' => $bank['account_no'],
          'location' => $bank['location'],
          'account_title_1' => AccountTitle::firstWhere('title',$bank['account_title_1'])->id,
          'account_title_2' => AccountTitle::firstWhere('title',$bank['account_title_2'])->id,
          'created_at' => $date,
          'updated_at' => $date,
          'deleted_at' => $status_date,
        ];

        $inputted_fields[] = $fields;
      }
      $inputted_fields = collect($inputted_fields);
      $chunks = $inputted_fields->chunk(100);
      $count_upload = count($inputted_fields);

      $active =  $inputted_fields->filter(function ($q){
        return $q['deleted_at']==NULL;
      })->count();

      $inactive =  $inputted_fields->filter(function ($q){
        return $q['deleted_at']!=NULL;
      })->count();

      foreach($chunks as $chunk)
      {
        Bank::insert($chunk->toArray()) ;
      }
      return $this->resultResponse('import','Bank',$count_upload,$active,$inactive);
    }
    else
      return $this->resultResponse('import-error','Bank',$errorBag);
  }

  public function bankAccountTitleDropdown(Request $request){
    $id = $request['id'];
    $status = $request['status'];
    $paginate = $request['paginate'];

    $bank_details = Bank::where('account_title_1',$id)->select('id','name','branch')->get();
    if(!($bank_details)->isEmpty()){
      return $this->resultResponse('fetch','Bank',["banks"=>$bank_details]);
    }
    return $this->resultResponse('not-found','Bank',[]);
  }
}
