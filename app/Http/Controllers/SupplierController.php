<?php

namespace App\Http\Controllers;

use App\Exceptions\FistoException;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Models\Referrence;
use App\Models\SupplierType;
use App\Methods\GenericMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{

  public function index(Request $request)
  {
//    $status =  $request['status'];
//    $rows =  (empty($request['rows']))?10:(int)$request['rows'];
//    $search =  $request['search'];
//    $paginate = (isset($request['paginate']))? $request['paginate']:$paginate = 1;
//    $api_for = strtolower((isset($request['api_for']))? $request['api_for']: "default");

    $status = $request->status;
    $rows = (int) $request->input('rows', 10);
    $search = $request->search;
    $paginate = (isset($request['paginate']))? $request['paginate']:$paginate = 1;
    $api_for = $request->input('api_for', 'default');

    $suppliers = Supplier::withTrashed()
      ->with('references')
      ->with('supplier_type')
      ->when(isset($status), function ($query) use ($status) {
        return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
      })
      ->where(function ($query) use ($search) {
        $query->where('suppliers.code', 'like', '%'.$search.'%')
          ->orWhere('suppliers.name', 'like', '%'.$search.'%')
          ->orWhere('suppliers.terms', 'like', '%'.$search.'%');
      })
      ->latest('updated_at');

      if ($paginate == 1){
        $suppliers = $suppliers->paginate($rows);
      }else if ($paginate == 0){
        $suppliers = $suppliers
        ->with('references')
        ->without('supplier_type')
        ->when($api_for == 'vladimir', function ($query) {
          return $query->without('references')
              ->get(['id','code', 'name',DB::RAW('(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status')]);
        }
        ,function ($query) {
              return $query->get(['id','name']);
        });
        if(count($suppliers)){
            $suppliers = array("suppliers"=>$suppliers);;
        }
      }

      if(count($suppliers)){
          return $this->resultResponse('fetch','Supplier',$suppliers);
      }

      return $this->resultResponse('not-found','Supplier',[]);
  }

  public function store(SupplierRequest $request)
  {
      $code = $request->code;
      $name = $request->name;
      $terms = $request->terms;
      $supplier_type_id = $request->supplier_type_id;
      $references = $request->references;
      $receipt_type = $request->receipt_type;

      $supplier = Supplier::create([
          'code' => $code,
          'name' => $name,
          'receipt_type' => $receipt_type,
          'terms' => $terms,
          'supplier_type_id' => $supplier_type_id
      ]);

      $supplier->references()->attach($references);

      return $this->resultResponse('save','Supplier', $supplier);

//    $fields = $request->validate([
//      'code' => ['required','string'],
//      'name' => ['required','string'],
//      'terms' => ['required','string'],
//      'supplier_type_id' => ['required','numeric'],
//      'references' => ['required','array']
//    ]);
//
//    $supplier_validateDuplicateCode = Supplier::withTrashed()->firstWhere('code', $fields['code']);
//
//    if (!empty($supplier_validateDuplicateCode))
//      return $this->resultResponse('registered','Code',["error_field" => "code"]);
//
//    $supplier_validateDuplicateName = Supplier::withTrashed()->firstWhere('name', $fields['name']);
//
//    if (!empty($supplier_validateDuplicateName))
//      return $this->resultResponse('registered','Name',["error_field" => "name"]);
//
//    $new_supplier = Supplier::create($fields);
//    $new_supplier->references()
//      ->attach($fields['references']);
//    return $this->resultResponse('save','Supplier',$new_supplier);
  }
  public function update(Request $request, $id)
  {
    $supplier = Supplier::withTrashed()->find($id);

    if ($supplier) {

        $code = $request->code;
        $name = $request->name;
        $receipt_type = $request->receipt_type;
        $terms = $request->terms;
        $supplier_type_id = $request->supplier_type_id;
        $references = $request->references;

        $supplier->code = $code;
        $supplier->name = $name;
        $supplier->receipt_type = $receipt_type;
        $supplier->terms = $terms;
        $supplier->supplier_type_id = $supplier_type_id;

        $supplier->references()->detach();
        $supplier->references()->attach($references);

        return $this->validateIfNothingChangeThenSave($supplier,'Supplier',[]);

    } else {
        return $this->resultResponse('not-found','Supplier',[]);
    }

//    $fields = $request->validate([
//      'code' => ['required','string'],
//      'name' => ['required','string'],
//      'terms' => ['required','string'],
//      'supplier_type_id' => ['required','numeric'],
//      'references' => ['required','array']
//    ]);
//
//    if (!empty($supplier)) {
//
//      $supplier_validateDuplicateCode = Supplier::withTrashed()->firstWhere([['id', '<>', $id],['code', $fields['code']]]);
//      if (!empty($supplier_validateDuplicateCode))
//      return $this->resultResponse('registered','Code',["error_field" => "code"]);
//
//      $supplier_validateDuplicateName = Supplier::withTrashed()->firstWhere([['id', '<>', $id],['name', $fields['name']]]);
//
//      if (!empty($supplier_validateDuplicateName))
//      return $this->resultResponse('registered','Name',["error_field" => "name"]);
//
//      $is_reference_modified = $this->isTaggedArrayModified($fields['references'],  $supplier->references()->get(),'id');
//
//      $supplier->code = $fields['code'];
//      $supplier->name = $fields['name'];
//      $supplier->terms = $fields['terms'];
//      $supplier->supplier_type_id = $fields['supplier_type_id'];
//      $supplier->references()->detach();
//      $supplier->references()->attach(array_unique($fields['references']));
//      return $this->validateIfNothingChangeThenSave($supplier,'Supplier',$is_reference_modified);
//    }
//    else
//      return $this->resultResponse('not-found','Supplier',[]);
  }

    public function import(Request $request) {
        $timezone = "Asia/Dhaka";
        date_default_timezone_set($timezone);
        $date = date("Y-m-d H:i:s", strtotime('now'));
        $errorBag = [];
        $suppliers = $request->all();

        $headers = 'Supplier Code, Supplier Name, Receipt Type, Terms, Supplier Type, Referrences, Status';
        $template = ["code", "name", "receipt_type", "terms", "supplier_type", "referrences", "status"];
        $keys = array_keys(current($suppliers));
        $this->validateHeader($template, $keys, $headers);

        $supplier_code_list = Supplier::withTrashed()->pluck('code')->toArray();
        $supplier_name_list = Supplier::withTrashed()->pluck('name')->toArray();
        $supplier_type_list = SupplierType::withTrashed()->pluck('type')->toArray();
        $referrence_list = Referrence::withTrashed()->pluck('type')->toArray();

        $index = 2;

        foreach ($suppliers as $supplier) {
            $code = $supplier['code'];
            $name = $supplier['name'];
            $receipt_type = $supplier['receipt_type'];
            $type = $supplier['supplier_type'];
            $references = $supplier['referrences'];
            $terms = $supplier['terms'];
            $status = $supplier['status'];

            if (in_array($code, $supplier_code_list)) {
                $errorBag[] = (object)[
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $code . " is already registered."
                ];
            }

            if (in_array($name, $supplier_name_list)) {
                $errorBag[] = (object)[
                    "error_type" => "existing",
                    "line" => $index,
                    "description" => $name . " is already registered."
                ];
            }

            if (!in_array($type, $supplier_type_list)) {
                $errorBag[] = (object)[
                    "error_type" => "unregistered",
                    "line" => $index,
                    "description" => $type . " is not registered."
                ];
            }


            $ref = explode(",", $references);
            if (count($ref) > 0) {
                foreach ($ref as $reference) {
                    if (!in_array($reference, $referrence_list)) {
                        $errorBag[] = (object)[
                            "error_type" => "unregistered",
                            "line" => $index,
                            "description" => $reference . " is not registered."
                        ];
                    }
                }
            }

            if(!in_array($status, ['Active', 'Inactive'])) {
                $errorBag[] = (object) [
                    "error_type" => "wrong-format",
                    "line" => $index,
                    "description" => "Status must be Active or Inactive.",
                ];
            }

            if(!in_array($receipt_type, ['Official', 'Unofficial'])) {
                $errorBag[] = (object) [
                    "error_type" => "wrong-format",
                    "line" => $index,
                    "description" => "Receipt Type must be Official or Unofficial.",
                ];
            }

            foreach ($supplier as $key => $value) {
                if (empty($value))
                    $errorBag[] = (object) [
                        "error_type" => "empty",
                        "line" => $index,
                        "description" => $key . " is empty."
                ];
            }

            $index++;
        }

        if (count($errorBag) || !count($errorBag)) {
            $input_code = array_column($suppliers, 'code');
            $duplicate_code = array_keys(array_filter(array_count_values($input_code), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_code)) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_code, $duplicate_code[0])))),
                    'description' => 'Code ' . $duplicate_code[0] . ' has a duplicate in your excel file.'
                ];
            }

            $input_name = array_column($suppliers, 'name');
            $duplicate_name = array_keys(array_filter(array_count_values($input_name), function ($value) {
                return $value > 1;
            }));

            if (count($duplicate_name)) {
                $errorBag[] = (object) [
                    'error_type' => 'duplicate',
                    'line' => implode(', ', array_map(function ($value) {
                        return $value + 2;
                    }, (array_keys($input_name, $duplicate_name[0])))),
                    'description' => 'Name ' . $duplicate_name[0] . ' has a duplicate in your excel file.'
                ];
            }
        }

        if (!count($errorBag)) {
            $supplierChunks = collect($suppliers)->chunk(100);
            $supplierChunks->each(function ($chunk) use ($suppliers){
                $transformChunk = $chunk->transform(function ($supplier) {
                    return [
                        'code' => $supplier['code'],
                        'name' => $supplier['name'],
                        'receipt_type' => $supplier['receipt_type'],
                        'terms' => $supplier['terms'],
                        'supplier_type_id' => SupplierType::where('type', $supplier['supplier_type'])->first()->id,
                        'created_at' => date("Y-m-d H:i:s", strtotime('now')),
                        'updated_at' => date("Y-m-d H:i:s", strtotime('now')),
                        'deleted_at' => (strtolower($supplier['status']) == "active" ? NULL : date("Y-m-d H:i:s", strtotime('now')))
                    ];
                })->toArray();

                foreach ($transformChunk as $key => $value) {
                    $supplier = Supplier::create($value);
                    $supplier->references()->attach(Referrence::whereIn('type', explode(",", $suppliers[$key]['referrences']))->pluck('id'));
                }
            });

            $suppliersCollection = collect($suppliers);
            $active = $suppliersCollection->filter(function ($supplier) {
                return strtolower($supplier['status']) == "active";
            })->count();

            $inactive = $suppliersCollection->filter(function ($supplier) {
                return strtolower($supplier['status']) == "inactive";
            })->count();

            return response()->json([
                'status' => 'imported',
                'message' => 'Supplier successfully imported, '. $active . ' active rows and, ' . $inactive . ' inactive rows were added.',
            ], 201);

        } else {
            return $this->resultResponse("import-error", "supplier", $errorBag);
        }
    }




//  public function import(Request $request)
//  {
//    $timezone = "Asia/Dhaka";
//    date_default_timezone_set($timezone);
//    $date = date("Y-m-d H:i:s", strtotime('now'));
//    $errorBag = [];
//    $data = $request->all();
//    $data_validation_fields = $request->all();
//    $index = 2;
//    $supplier_type_list = SupplierType::withTrashed()->get();
//    $referrence_list = Referrence::withTrashed()->get();
//    $supplier_list = Supplier::withTrashed()->get();
//    $supplier_type_list_no_trash = SupplierType::get();
//    $referrence_list_no_trash = Referrence::get();
//
//    $headers = 'Supplier Code, Supplier Name, Terms, Supplier Type, Referrences, Status';
//    $template = ["code","name","terms","supplier_type","referrences", "status"];
//    $keys = array_keys(current($data));
//    $this->validateHeader($template,$keys,$headers);
//
//    foreach ($data as $supplier) {
//      $code = $supplier['code'];
//      $name = $supplier['name'];
//      $supplier_type = $supplier['supplier_type'];
//      $supplier_references = $supplier['referrences'];
//
//          foreach ($supplier as $key => $value) {
//              if (empty($value))
//                  $errorBag[] = (object) [
//                  "error_type" => "empty",
//                  "line" => $index,
//                  "description" => $key . " is empty."
//                  ];
//          }
//          if (!empty($supplier_type)) {
//            $unregisterSupplierType = $this->getDuplicateInputs($supplier_type_list,$supplier_type,'type');
//              if ($unregisterSupplierType->count() == 0)
//                  $errorBag[] = (object) [
//                  "error_type" => "unregistered",
//                  "line" => $index,
//                  "description" => $supplier_type . " is not registered."
//                  ];
//          }
//          if (!empty($supplier_references)) {
//              foreach (explode(",", $supplier_references) as $reference_type) {
//                  $unregisterSupplierReference = $this->getDuplicateInputs($referrence_list,$reference_type,'type');
//                  if ($unregisterSupplierReference->count() == 0)
//                  $errorBag[] = (object) [
//                      "error_type" => "unregistered",
//                      "line" => $index,
//                      "description" => $reference_type . " is not registered."
//                  ];
//              }
//          }
//          if (!empty($code)) {
//              $duplicateSupplierCode = $this->getDuplicateInputs($supplier_list,$code,'code');
//              if ($duplicateSupplierCode->count() > 0)
//              $errorBag[] = (object) [
//                  "error_type" => "existing",
//                  "line" => $index,
//                  "description" => $code . " is already registered."
//                  ];
//          }
//          if (!empty($name)) {
//              $duplicateSupplierName =$supplier_list->filter(function ($supplier) use ($name){return strtolower($supplier['name']) == strtolower($name);});
//              if ($duplicateSupplierName->count() > 0)
//              $errorBag[] = (object) [
//                  "error_type" => "existing",
//                  "line" => $index,
//                  "description" => $name . " is already registered."
//                  ];
//          }
//          $index++;
//    }
//
//
//    $original_lines = array_keys($data_validation_fields);
//
//    $duplicate_code = array_values(array_diff($original_lines,array_keys($this->unique_multidim_array($data_validation_fields,'code'))));
//
//    foreach($duplicate_code as $line){
//      $input_code = $data_validation_fields[$line]['code'];
//      $duplicate_data =  array_filter($data_validation_fields, function ($query) use($input_code){
//        return ($query['code'] == $input_code);
//      });
//      $duplicate_lines =  implode(",",array_map(function($query){
//        return $query+2;
//      },array_keys($duplicate_data)));
//      $firstDuplicateLine =  array_key_first($duplicate_data);
//
//      if((empty($data_validation_fields[$line]['code']))){
//
//      }else{
//        $errorBag[] = [
//          "error_type" => "duplicate",
//          "line" => (string) $duplicate_lines,
//          "description" =>  $data_validation_fields[$firstDuplicateLine]['code'].' code has a duplicate in your excel file.'
//        ];
//      }
//    }
//
//    $duplicate_name = array_values(array_diff($original_lines,array_keys($this->unique_multidim_array($data_validation_fields,'name'))));
//
//    foreach($duplicate_name as $line){
//      $input_name = $data_validation_fields[$line]['name'];
//      $duplicate_data =  array_filter($data_validation_fields, function ($query) use($input_name){
//        return ($query['name'] == $input_name);
//      });
//      $duplicate_lines =  implode(",",array_map(function($query){
//        return $query+2;
//      },array_keys($duplicate_data)));
//      $firstDuplicateLine =  array_key_first($duplicate_data);
//
//      if((empty($data_validation_fields[$line]['name']))){
//
//      }else{
//        $errorBag[] = [
//          "error_type" => "duplicate",
//          "line" => (string) $duplicate_lines,
//          "description" =>  $data_validation_fields[$firstDuplicateLine]['name'].' name has a duplicate in your excel file.'
//        ];
//      }
//    }
//    $errorBag = array_values(array_unique($errorBag,SORT_REGULAR));
//
//    if (empty($errorBag)) {
//      foreach ($data as $supplier) {
//          $status_date = (strtolower($supplier['status'])=="active"?NULL:$date);
//          $supplier_type = $supplier['supplier_type'];
//        $fields = [
//          'code' => $supplier['code'],
//          'name' => $supplier['name'],
//          'terms' => $supplier['terms'],
//          'supplier_type_id' => SupplierType::where('type',$supplier_type)->first()->id,
//          'created_at' => $date,
//          'updated_at' => $date,
//          'deleted_at' => $status_date,
//        ];
//
//        $references = explode(",", $supplier['referrences']);
//        $references_ids = Referrence::whereIn('type', $references)->pluck('id');
//        $fields['references_ids']= $references_ids;
//        $inputted_fields[] = $fields;
//      }
//
//
//      $inputted_fields = collect($inputted_fields);
//      $chunks = $inputted_fields->chunk(1000);
//      $count_upload = count($inputted_fields);
//
//      $active =  $inputted_fields->filter(function ($q){
//        return $q['deleted_at']==NULL;
//      })->count();
//
//      $inactive =  $inputted_fields->filter(function ($q){
//        return $q['deleted_at']!=NULL;
//      })->count();
//
//      foreach ($chunks as $specific_chunk)
//      {
//        $specific_chunk_to_insert = [];
//        foreach($specific_chunk as $key=>$chunk){
//
//          $specific_chunk_to_insert[$key]['code'] = $chunk['code'];
//          $specific_chunk_to_insert[$key]['name'] = $chunk['name'];
//          $specific_chunk_to_insert[$key]['terms'] = $chunk['terms'];
//          $specific_chunk_to_insert[$key]['supplier_type_id'] = $chunk['supplier_type_id'];
//          $specific_chunk_to_insert[$key]['created_at'] = $chunk['created_at'];
//          $specific_chunk_to_insert[$key]['updated_at'] = $chunk['updated_at'];
//          $specific_chunk_to_insert[$key]['deleted_at'] = $chunk['deleted_at'];
//        }
//
//        $new_supplier = DB::table('suppliers')->insert($specific_chunk_to_insert);
//        foreach($specific_chunk->toArray() as $chunk){
//
//          $supplier= Supplier::withTrashed()->where('code',$chunk)->first();
//          $supplier->references()->attach($chunk['references_ids']);
//        }
//      }
//      return $this->resultResponse('import','Supplier',$count_upload,$active,$inactive);
//    }
//    else
//      return $this->resultResponse('import-error','Supplier',$errorBag);
//  }
  public function change_status(Request $request,$id)
  {
//    $status = $request['status'];
//    $model = new Supplier();
//    return $this->change_masterlist_status($status,$model,$id,'Supplier');

      return $this->changeStatus($id, Supplier::class, 'Supplier');
  }
}
