<?php

namespace App\Http\Controllers;

use App\Exceptions\FistoException;

use App\Http\Requests\AccountTitleRequest;
use App\Models\AccountTitle;
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
          ->where(function ($query) use ($status) {
              return ($status == true) ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
          })
          ->where(function ($query) use ($search) {
              $query->where('code', 'like', '%' . $search . '%')
                  ->orWhere('title', 'like', '%' . $search . '%')
                  ->orWhere('category', 'like', '%' . $search . '%');
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
          'category' => $request['category']
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
        $account_title->category = $request['category'];

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

      $account_title = AccountTitle::withTrashed()->where('id',$id)->first();

      if ($account_title) {

          if ($account_title->trashed()) {
              $account_title->restore();
              return $this->resultResponse('restore','Account Title', $account_title);
          } else {
              $account_title->delete();
              return $this->resultResponse('archive','Account Title', $account_title);
          }

        } else {
            return $this->resultResponse('not-found','Account Title', []);
      }

//    $status = $request['status'];
//    $model = new AccountTitle();
//    return $this->change_masterlist_status($status,$model,$id,'Account Title');
  }

  public function import(Request $request) {
      $account_titles = $request->all();
      $errorBag = [];
      $code_list = AccountTitle::withTrashed()->pluck('code')->toArray();
      $title_list = AccountTitle::withTrashed()->pluck('title')->toArray();

      date_default_timezone_set('Asia/Manila');

      $headers =  "Code, Title, Category, Status";
      $template = ["code", "title", "category", "status"];
      $keys = array_keys(current($account_titles));
      $this->validateHeader($template, $keys, $headers);

      $index = 2;
      foreach ($account_titles as $account_title) {
          $code = $account_title['code'];
          $title = $account_title['title'];
          $category = $account_title['category'];
          $status = $account_title['status'];

         if (in_array($code, $code_list)) {
              $errorBag[] = [
                  "error_type" => "existing",
                  "line" => (string) $index,
                  "description" => "Code is already registered."
              ];
          }

         if (in_array($title, $title_list)) {
              $errorBag[] = [
                  "error_type" => "existing",
                  "line" => (string) $index,
                  "description" => "Title is already registered."
              ];
          }

          if(!in_array($status, ['Active', 'Inactive'])) {
              $errorBag[] = (object) [
                  "error_type" => "wrong-format",
                  "line" => $index,
                  "description" => "Status must be Active or Inactive.",
              ];
          }

          foreach ($account_title as $key => $value) {
              if (empty($value)) {
                  $errorBag[] = (object) [
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
                      'category' => $account_title['category'],
                      'created_at' => date('Y-m-d H:i:s'),
                      'updated_at' => date('Y-m-d H:i:s'),
                      'deleted_at' => (strtolower($account_title['status']) == 'active') ? null : date('Y-m-d H:i:s'),
                  ];
              })->toArray();

              foreach ($transformChunk as $chunk) {
                  $new_account_title = AccountTitle::create([
                      'code' => $chunk['code'],
                      'title' => $chunk['title'],
                      'category' => $chunk['category'],
                      'created_at' => $chunk['created_at'],
                      'updated_at' => $chunk['updated_at'],
                      'deleted_at' => $chunk['deleted_at'],
                  ]);
              }
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
