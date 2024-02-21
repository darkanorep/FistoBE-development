<?php

namespace App\Http\Controllers;

use App\Exceptions\FistoException;

use App\Http\Requests\AccountTitleRequest;
use App\Models\AccountTitle;
use App\Models\AccountTitleChild;
use App\Models\AccountTitleGrandParent;
use App\Models\AccountTitleGreatGrandParent;
use App\Models\AccountTitleParent;
use App\Models\AccountTitlePnL;
use App\Models\AccountTitleUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountTitleController extends Controller
{
  public function index(Request $request)
  {

      $status = $request['status'];
      $rows = (int) $request->input('rows', 10);
      $search = $request['search'];

      $account_titles = AccountTitle::withTrashed()
          ->with([
              'greatGrandParents:id,name',
              'grandParents:id,name',
              'parents:id,name',
              'children:id,name',
              'pnl:id,name',
              'units:id,name',
          ])
          ->where(function ($query) use ($status) {
              return ($status == true) ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
          })
          ->where(function ($query) use ($search) {
              $query->where('code', 'like', '%' . $search . '%')
                  ->orWhere('title', 'like', '%' . $search . '%')
//                  ->orWhere('category', 'like', '%' . $search . '%');
              ->orWhereHas('greatGrandParents', function ($query) use ($search) {
                  $query->where('name', 'like', '%' . $search . '%');
              })
                  ->orWhereHas('grandParents', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('parents', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('children', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('pnl', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('units', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  });
          })
          ->latest('updated_at')
          ->paginate($rows);

      if (!$account_titles->isEmpty()) {
          return $this->resultResponse('fetch', 'Account Title', $account_titles);
      }
      return $this->resultResponse('not-found', 'Account Title', []);

  }

  public function store(AccountTitleRequest $request)
  {

      $account_title = AccountTitle::create([
          'code' => $request['code'],
          'title' => $request['title'],
//          'category' => $request['category']
          'account_title_ggparent_id' => $request['account_title_ggparent_id'],
          'account_title_gparent_id' => $request['account_title_gparent_id'],
          'account_title_parent_id' => $request['account_title_parent_id'],
          'account_title_child_id' => $request['account_title_child_id'],
          'account_title_pnl_id' => $request['account_title_pnl_id'],
          'account_title_unit_id' => $request['account_title_unit_id'],
      ]);

      return $this->resultResponse('save','Account Title', $account_title);

//    $fields = $request->validate([
//      'code' => ['required','string'],
//      'title' => ['required','string'],
//      'category' => ['required','string']
//    ]);
//    $account_title_validateCodeDuplicate = AccountTitle::withTrashed()->firstWhere('code', $fields['code']);
//
//    if (!empty($account_title_validateCodeDuplicate)) {
//      return $this->resultResponse('registered','Code',["error_field" => "code"]);
//    }
//    $account_title_validateTitleDuplicate = AccountTitle::withTrashed()->firstWhere('title', $fields['title']);
//
//    if (!empty($account_title_validateTitleDuplicate)) {
//      return $this->resultResponse('registered','Title',["error_field" => "title"]);
//    }
//
//    $new_account_title = AccountTitle::create($fields);
//    return $this->resultResponse('save','Account Title',$new_account_title);
  }

  public function update(AccountTitleRequest $request,$id)
  {
    $account_title = AccountTitle::where('id',$id)->first();

    if ($account_title) {
        $account_title->code = $request['code'];
        $account_title->title = $request['title'];
//        $account_title->category = $request['category'];
        $account_title->account_title_ggparent_id = $request['account_title_ggparent_id'];
        $account_title->account_title_gparent_id = $request['account_title_gparent_id'];
        $account_title->account_title_parent_id = $request['account_title_parent_id'];
        $account_title->account_title_child_id = $request['account_title_child_id'];
        $account_title->account_title_pnl_id = $request['account_title_pnl_id'];
        $account_title->account_title_unit_id = $request['account_title_unit_id'];

        return $this->validateIfNothingChangeThenSave($account_title,'Account Title');

    } else {
        return $this->resultResponse('not-found','Account Title', []);
    }

//    $fields = $request->validate([
//      'code' => ['required','string'],
//      'title' => ['required','string'],
//      'category' => ['required','string']
//    ]);
//
//    if (!empty($account_title)) {
//      $account_title_validateCodeDuplicate = AccountTitle::withTrashed()->firstWhere([['id', '<>', $id],['code', $fields['code']]]);
//
//      if (!empty($account_title_validateCodeDuplicate)) {
//        return $this->resultResponse('registered','Code',["error_field" => "code"]);
//      }
//
//      $account_title_validateTitleDuplicate = AccountTitle::withTrashed()->firstWhere([['id', '<>', $id],['title', $fields['title']]]);
//
//      if (!empty($account_title_validateTitleDuplicate)) {
//        return $this->resultResponse('registered','Title',["error_field" => "title"]);
//      }
//
//      $account_title->code = $fields['code'];
//      $account_title->title = $fields['title'];
//      $account_title->category = $fields['category'];
//      return $this->validateIfNothingChangeThenSave($account_title,'Account Title');
//    }
//    else
//      return $this->resultResponse('not-found','Account Title',[]);
  }

  public function change_status($id)
  {

      return $this->changeStatus($id, AccountTitle::class, 'Account Title');


//    $status = $request['status'];
//    $model = new AccountTitle();
//    return $this->change_masterlist_status($status,$model,$id,'Account Title');
  }

  public function import(Request $request) {
      $account_titles = $request->all();
      $errorBag = [];
      $code_list = AccountTitle::withTrashed()->pluck('code')->toArray();
      $title_list = AccountTitle::withTrashed()->pluck('title')->toArray();
      $ggp_list = AccountTitleGreatGrandParent::withTrashed()->pluck('name')->toArray(); //account type
      $gp_list = AccountTitleGrandParent::withTrashed()->pluck('name')->toArray(); //account group
      $p_list = AccountTitleParent::withTrashed()->pluck('name')->toArray(); // account subgroup
      $c_list = AccountTitleChild::withTrashed()->pluck('name')->toArray(); // final statement
      $pnl_list = AccountTitlePnL::withTrashed()->pluck('name')->toArray(); // normal balance
      $unit_list = AccountTitleUnit::withTrashed()->pluck('name')->toArray(); // unit

      date_default_timezone_set('Asia/Manila');

//      $headers =  "Code, Title, Category, GreatGrandParent, GrandParent, Parent, Child, Unit, Status";
//      $template = ["code", "title", "category", "greatgrandparent", "grandparent", "parent", "child", "unit", "status"];

      $headers =  "Code, Title, Normal Balance, Account Type, Account Group, Account SubGroup, Financial Statement, Unit, Status";
      $template = ["code", "title", "normal_balance", "account_type", "account_group", "account_subgroup", "financial_statement", "unit", "status"];
      $keys = array_keys(current($account_titles));
      $this->validateHeader($template, $keys, $headers);

      $index = 2;
      foreach ($account_titles as $account_title) {
          $code = $account_title['code'];
          $title = $account_title['title'];
          $pnl = $account_title['normal_balance'];
          $greatgrandparent = $account_title['account_type'];
          $grandparent = $account_title['account_group'];
          $parent = $account_title['account_subgroup'];
          $child = $account_title['financial_statement'];
          $unit = $account_title['unit'];
          $status = $account_title['status'];

//         if (in_array($code, $code_list)) {
//              $errorBag[] = [
//                  "error_type" => "existing",
//                  "line" => (string) $index,
//                  "description" => "Code is already registered."
//              ];
//         }
//
//         if (in_array($title, $title_list)) {
//              $errorBag[] = [
//                  "error_type" => "existing",
//                  "line" => (string) $index,
//                  "description" => "Title is already registered."
//              ];
//         }

          if (!in_array($status, ['Active', 'Inactive'])) {
              $errorBag[] = (object)[
                  "error_type" => "wrong-format",
                  "line" => $index,
                  "description" => "Status must be Active or Inactive.",
              ];
          }

          if (!in_array($pnl, $pnl_list)) {
              $errorBag[] = (object)[
                  "error_type" => "not-registered",
                  "line" => $index,
                  "description" => "Normal Balance is not registered.",
              ];
          }

          if (!in_array($greatgrandparent, $ggp_list)) {
              $errorBag[] = (object)[
                  "error_type" => "not-registered",
                  "line" => $index,
                  "description" => "Account Type is not registered.",
              ];
          }

          if (!in_array($grandparent, $gp_list)) {
              $errorBag[] = (object)[
                  "error_type" => "not-registered",
                  "line" => $index,
                  "description" => "Account Group is not registered.",
              ];
          }

          if (!in_array($parent, $p_list)) {
              $errorBag[] = (object)[
                  "error_type" => "not-registered",
                  "line" => $index,
                  "description" => "Account Subgroup is not registered.",
              ];
          }

          if (!in_array($child, $c_list)) {
              $errorBag[] = (object)[
                  "error_type" => "not-registered",
                  "line" => $index,
                  "description" => "Financial Statement is not registered.",
              ];
          }

          if (!in_array($unit, $unit_list)) {
              $errorBag[] = (object)[
                  "error_type" => "not-registered",
                  "line" => $index,
                  "description" => "Unit is not registered.",
              ];
          }

          $excludeKeys = ['normal_balance', 'account_type', 'account_group', 'account_subgroup', 'financial_statement', 'unit'];

          foreach ($account_title as $key => $value) {
              if (in_array($key, $excludeKeys)) {
                  continue;
              }
              if (empty($value)) {
                  $errorBag[] = (object)[
                      "error_type" => "empty",
                      "line" => $index,
                      "description" => $key . " is empty.",
                  ];
              }
          }
          $index++;
      }

      if (count($errorBag) || !count($errorBag)) {

          $input_code = array_column($account_titles, 'code');
          $duplicate_code = array_keys(array_filter(array_count_values($input_code), function ($value) {
              return $value > 1;
          }));

          if (count($duplicate_code) > 0) {
              $errorBag[] = (object) [
                  'error_type' => 'duplicate',
                  'line' => implode(', ', array_map(function ($value) {
                      return $value + 2;
                  }, (array_keys($input_code, $duplicate_code[0])))),
                  'description' => 'Code ' . $duplicate_code[0] . ' has a duplicate in your excel file.'
              ];
          }

          $input_title = array_column($account_titles, 'title');
          $duplicate_title = array_keys(array_filter(array_count_values($input_title), function ($value) {
              return $value > 1;
          }));

          if (count($duplicate_title) > 0) {
              $errorBag[] = (object)[
                  'error_type' => 'duplicate',
                  'line' => implode(', ', array_map(function ($value) {
                      return $value + 2;
                  }, (array_keys($input_title, $duplicate_title[0])))),
                  'description' => 'Title ' . $duplicate_title[0] . ' has a duplicate in your excel file.'
              ];
          }
      }

      if (!count($errorBag)) {
          $accountTitleChunks = collect($account_titles)->chunk(300);
          $accountTitleChunks->each(function ($chunk) use ($account_titles) {
              $transformChunk = $chunk->transform(function ($account_title) {
                  return [
                      'code' => $account_title['code'],
                      'title' => $account_title['title'],
//                      'category' => $account_title['category'],
                      'account_title_ggparent_id' => AccountTitleGreatGrandParent::withTrashed()->where('name', $account_title['account_type'])->first()->id,
                      'account_title_gparent_id' => AccountTitleGrandParent::withTrashed()->where('name', $account_title['account_group'])->first()->id,
                      'account_title_parent_id' => AccountTitleParent::withTrashed()->where('name', $account_title['account_subgroup'])->first()->id,
                      'account_title_child_id' => AccountTitleChild::withTrashed()->where('name', $account_title['financial_statement'])->first()->id,
                      'account_title_pnl_id' => AccountTitlePnL::withTrashed()->where('name', $account_title['normal_balance'])->first()->id,
                      'account_title_unit_id' => AccountTitleUnit::withTrashed()->where('name', $account_title['unit'])->first()->id,
                      'status' => $account_title['status'],
//                      'created_at' => date('Y-m-d H:i:s'),
//                      'updated_at' => date('Y-m-d H:i:s'),
//                      'deleted_at' => (strtolower($account_title['status']) == 'active') ? null : date('Y-m-d H:i:s'),
                  ];
              })->toArray();

              foreach ($transformChunk as $chunk) {
//                  AccountTitle::create([
//                      'code' => $chunk['code'],
//                      'title' => $chunk['title'],
////                      'category' => $chunk['category'],
//                      'account_title_ggparent_id' => $chunk['account_title_ggparent_id'],
//                      'account_title_gparent_id' => $chunk['account_title_gparent_id'],
//                      'account_title_parent_id' => $chunk['account_title_parent_id'],
//                      'account_title_child_id' => $chunk['account_title_child_id'],
//                      'account_title_pnl_id' => $chunk['account_title_pnl_id'],
//                      'account_title_unit_id' => $chunk['account_title_unit_id'],
//                      'created_at' => $chunk['created_at'],
//                      'updated_at' => $chunk['updated_at'],
//                      'deleted_at' => $chunk['deleted_at'],
//                  ]);
                  $flattenedChunks = AccountTitle::updateOrCreate([
                      'code' => $chunk['code'],
                      'title' => $chunk['title'],
                  ], $chunk);
              }
          });

          $flattenedChunks = $accountTitleChunks->flatten(1);
          $flattenedChunks->each(function ($chunk) {
              AccountTitle::where('code', $chunk['code'])->where('title', $chunk['title'])
                  ->update([
                      'deleted_at' => (strtolower($chunk['status']) == 'active') ? null : date('Y-m-d H:i:s'),
                  ]);
          });

          $accountTitleCollections = collect($account_titles);

          $active = $accountTitleCollections->filter(function ($account_title) {
              return strtolower($account_title['status']) == 'active';
          })->count();

          $inactive = $accountTitleCollections->filter(function ($account_title) {
              return strtolower($account_title['status']) == 'inactive';
          })->count();

          return response()->json([
              'status' => 'imported',
              'message' => 'Locations successfully imported, '. $active . ' active rows and, ' . $inactive . ' inactive rows were added.',
          ], 201);

      } else {
          return $this->resultResponse("import-error", "account title", $errorBag);
      }
  }
}
