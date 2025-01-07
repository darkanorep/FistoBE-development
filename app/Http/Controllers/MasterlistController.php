<?php

namespace App\Http\Controllers;

use App\Exceptions\FistoException;
use App\Http\Requests\DocumentCoaRequest;
use App\Http\Resources\ChargingResource;
use App\Http\Resources\DocumentCoaResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\UserResource;

use App\Models\AccountTitleChild;
use App\Models\AccountTitleGrandParent;
use App\Models\AccountTitleGreatGrandParent;
use App\Models\AccountTitleParent;
use App\Models\AccountTitlePnL;
use App\Models\AccountTitleUnit;
use App\Models\TransactionType;
use App\Models\TreasuryCheque;
use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\Referrence;
use App\Models\UtilityLocation;
use App\Models\UtilityCategory;
use App\Models\Sedar;
use App\Models\AccountTitle;
use App\Models\OrganizationDepartment;

use App\Models\VoucherCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

use App\Methods\GenericMethod;

class MasterlistController extends Controller
{
  public function documentDropdown(){
    $data =  array("documents"=>Document::whereNull('deleted_at')->with('categories')->get(['id','type','description']));
    return $this->resultResponse('fetch','Document',$data);
  }

  public function categoryDropdown(){
    $data =  array("categories"=>Category::whereNull('deleted_at')->get(['id','name']));
    return $this->resultResponse('fetch','Category',$data);

  }

  public function supplierRefDropdown(){
    $data =  array(
      "supplier_types"=>SupplierType::whereNull('deleted_at')->get(['id','type']),
      "references"=>Referrence::whereNull('deleted_at')->get(['id','type']));
      return $this->resultResponse('fetch','Supplier and Reference',$data);
  }

  public function loccatsupDropdown(){
    $data =  array(
      "locations"=>UtilityLocation::whereNull('deleted_at')->get(['id','location']),
      "categories"=>UtilityCategory::whereNull('deleted_at')->get(['id','category']),
      "suppliers"=>Supplier::whereNull('deleted_at')->get(['id','name']));
      return $this->resultResponse('fetch','Location, Category and Supplier',$data);
  }

  public function loccatDropdown(){
    $data =  array(
      "locations"=>UtilityLocation::whereNull('deleted_at')->get(['id','location']),
      "categories"=>UtilityCategory::whereNull('deleted_at')->get(['id','category']));
      return $this->resultResponse('fetch','Location and Category',$data);
  }

    public function accountTitleDropdown(){
        $data =  array(
            "account_titles"=>AccountTitle::whereNull('deleted_at')->get(['id','title', 'code']));
        return $this->resultResponse('fetch','Account Title',$data);
    }

    public function chequeTypesDropdown() {
    $treasuryCheques = TreasuryCheque::with('user')
        ->get()
        ->groupBy('type')
        ->map(function ($item) {
            return $item->groupBy('companies')
                ->map(function ($item) {
                    return [
                        'user' => [
                            'id' => $item[0]->user->id,
                            'name' => $item[0]->user->first_name . ' ' . $item[0]->user->last_name,
                        ]
                    ];
                });
        });

    return $this->resultResponse('fetch', 'Cheque Types', $treasuryCheques);
}

//    public function accountTitleDocumentDropdown($id){
//        $data = Document::where('id',$id)
//            ->with([
//                "categories:id,name"
//            ])->first();
//
//        if ($data) {
//            return $this->resultResponse('fetch', 'Account Title', new DocumentResource($data));
//        } else {
//            return $this->resultResponse('not-found', 'Account Title', []);
//        }
//    }

    public function accountTitleTransactionTypeDropdown($id) {
//      $data = TransactionType::with('accounts')->where('id', $id)
//          ->first();

        $data = TransactionType::with('accounts.account_title')->where('id', $id)
            ->first();

      if ($data) {
            return $this->resultResponse('fetch', 'Account Title', new DocumentResource($data));
        } else {
            return $this->resultResponse('not-found', 'Account Title', []);
      }
    }

  public function transactionAccountTitleDropdown(Request $request){
    $api_for = $request->api_for?$request->api_for: "default";
    $data =  array(
      "account_titles"=>
        AccountTitle::withTrashed()
        ->when($api_for == 'vladimir', function ($query) {
          return $query->get(['id','code', 'title as name',DB::RAW('(CASE WHEN (ISNULL(deleted_at)) THEN 1 ELSE 0 END) as status')]);
        }, function ($query){
          return $query->whereNull('deleted_at')
           ->get(['id','code','title as name']);
        })
       );
      return $this->resultResponse('fetch','Account Title',$data);
  }


  public function companyDropdown(){
    $data =  array("companies"=>Company::whereNull('deleted_at')->get(['id','code','company']));
    return $this->resultResponse('fetch','Company',$data);
  }

  public function associateDropdown(Request $request){
    $company_id = $request['company_id'];
    $data =  array("associates"=>User::with('companies')
    ->when(isset($company_id), function ($query) use($company_id) {
      $query->whereHas('companies', function ($query) use($company_id) {
          $query->where('companies.id',$company_id);
      })
      ->without('companies');
    })
     ->where(function ($query){
      $query->where('role','AP Associate')
      ->orWhere('role','AP Specialist');
    })
    ->whereNull('deleted_at')
    ->get(['id',DB::raw("CONCAT(users.first_name,' ',users.last_name)  AS name")]));


    if(count($data['associates'])==0){
      return $this->resultResponse('not-found','',[]);
    }

    return $this->resultResponse('fetch','AP Associate',$data);
  }

  public function approverDropdown(Request $request){
    $data =  array("approvers"=>User::where('role','Approver')->get(['id','position',DB::raw("CONCAT(users.first_name,' ',users.last_name)  AS name")]));
    if(count($data['approvers'])==0){
      return $this->resultResponse('not-found','',[]);
    }

    return $this->resultResponse('fetch','Approver',$data);
  }

    public function specialistDropdown(){
        $data =  array("specialists"=>User::where('role','AP Specialist')->get(['id','position',DB::raw("CONCAT(users.first_name,' ',users.last_name)  AS name")]));
        if(count($data['specialists'])==0){
            return $this->resultResponse('not-found','',[]);
        }

        return $this->resultResponse('fetch','Specialists',$data);
    }

    public function treasuriesDropdown(){
        $data =  array("treasuries"=>User::where('role','Treasury Associate')->get(['id','position',DB::raw("CONCAT(users.first_name,' ',users.last_name)  AS name")]));
        if(count($data['treasuries'])==0){
            return $this->resultResponse('not-found','',[]);
        }

        return $this->resultResponse('fetch','Treasuries',$data);
    }


  public function creditCardAccountNoDropdown(Request $request){
    $credit_card_account_no =  DB::table('credit_cards')
    ->get(['id','account_no as no']);
    $credit_card_account_no =  collect(['account_numbers' => $credit_card_account_no]);
    return $this->resultResponse('fetch','Credit Card Account No',$credit_card_account_no);
  }



  public function chargingDropdown(){
    $company =  DB::table('companies')
    ->get(['id','company']);
   return $company =  ChargingResource::collection($company);
    $company =  collect(['companies' => $company]);
    return $this->resultResponse('fetch','Charging',$company);
  }

  public function currentUser(){

    $categories = Category::all();
    $documents = Document::all();

    $user = User::withTrashed()
    ->select('id','id_prefix','id_no','role','position','first_name','middle_name','last_name','suffix','department','document_types')
    ->where('id',Auth::id())
    ->latest('updated_at')
    ->first();

    $new_document_type_list = [];
    $new_document_types = [];


      foreach($user['document_types'] as $document_type)
      {
        $new_category_list = [];
        $new_categories = [];

            if(count($documents->where('id',$document_type['id']))>0)
            {

                $document_description = $documents->where('id',$document_type['id']);
                $category_ids = $document_type['categories'];
                if(count($category_ids)>0)
                {
                    foreach($category_ids as $category_id)
                    {
                        if(count(($categories->where('id',$category_id)))>0)
                        {
                            $category_description = $categories->where('id',$category_id)->first()->name;
                            $new_category_list['id'] = $category_id;
                            $new_category_list['name'] = $category_description;
                            array_push($new_categories,$new_category_list);
                        }

                    }
                }
                $new_document_type_list['id'] = ($document_description->values()->first()->id);
                $new_document_type_list['type'] = ($document_description->values()->first()->type);
                $new_document_type_list['categories'] = $new_categories;
                array_push($new_document_types,$new_document_type_list);
            }

     }
        $user['document_types'] =  $new_document_types;
        return $this->resultResponse('fetch','User',$user);
  }

  public function departmentDropdown(Request $request){

    $departments = Department::when(isset($request['all']), function($query) {
      return $query->withTrashed();
    })
    ->get(['id','code','department as name']);

    $data = array(
      "departments" => $departments
    );

    return $this->resultResponse('fetch','Department',$data);
  }

  public function organizationDropdown(Request $request){
    $departments = OrganizationDepartment::when(isset($request['all']), function($query) {
      return $query->withTrashed();
    })
    ->get(['id','name']);

    $data = array(
      "departments" => $departments
    );

    return $this->resultResponse('fetch','Department',$data);
  }

  public function voucherCodeDropdown() {
      $voucher_code = array(
            "voucher_codes" => VoucherCode::whereNull('deleted_at')->get(['id','code'])
      );

      return $this->resultResponse('fetch', 'Voucher Code', $voucher_code);
  }

  public function transactionTypeDropdown() {
      $transaction_type = array(
            "transaction_types" => DB::table('transaction_types')->whereNull('deleted_at')->get(['id','transaction_type as name'])
      );

      return $this->resultResponse('fetch', 'Transaction Type', $transaction_type);
  }

    public function accountTitleGreatGrandParentsDropdown()
    {
        $account_title_ggparent = array(
            "account_title_ggparents" => AccountTitleGreatGrandParent::whereNull('deleted_at')->get(['id', 'name'])
        );

        return $this->resultResponse('fetch', 'Account Title Great Grand Parent', $account_title_ggparent);
    }

    public function accountTitleGrandParentsDropdown()
    {
        $account_title_gparent = array(
            "account_title_gparents" => AccountTitleGrandParent::whereNull('deleted_at')->get(['id', 'name'])
        );

        return $this->resultResponse('fetch', 'Account Title Grand Parent', $account_title_gparent);
    }

    public function accountTitleParentsDropdown()
    {
        $account_title_parent = array(
            "account_title_parents" => AccountTitleParent::whereNull('deleted_at')->get(['id', 'name'])
        );

        return $this->resultResponse('fetch', 'Account Title Parent', $account_title_parent);
    }

    public function accountTitleChildrenDropdown()
    {
        $account_title_child = array(
            "account_title_children" => AccountTitleChild::whereNull('deleted_at')->get(['id', 'name'])
        );

        return $this->resultResponse('fetch', 'Account Title Child', $account_title_child);
    }

    public function accountTitlePnlsDropdown()
    {
        $account_title_pnl = array(
            "account_title_pnls" => AccountTitlePnL::whereNull('deleted_at')->get(['id', 'name'])
        );

        return $this->resultResponse('fetch', 'Account Title PNL', $account_title_pnl);
    }

    public function accountTitleUnitsDropdown()
    {
        $account_title_unit = array(
            "account_title_units" => AccountTitleUnit::whereNull('deleted_at')->get(['id', 'name'])
        );

        return $this->resultResponse('fetch', 'Account Title Unit', $account_title_unit);
    }


  public static function coa(Request $request){

    $company = $request->company;
    $department= $request->department;
    $location= $request->location;

    $companies = Company::with('departments')
    ->select('id','code','company as name')
    ->whereHas('departments.locations', function ($query) use ($department, $location){
        $query->where('departments.department','like','%'.$department.'%')
        ->where('locations.location','like','%'.$location.'%');
    })
    ->get();

    if($companies->isEmpty()){
      return GenericMethod::resultResponse('not-found','Company',$companies);
    }

    return GenericMethod::resultResponse('fetch','Company',$companies);

  }

  public function genus_orders(){

    return Sedar::paginate(10);
  }

  public function sedar_employees(){

    $response = Http::withToken('8|AUeqUEdjU4ueJjtNRbWJZnzMIbSLeVcGGeWlMeFD')->get('http://rdfsedar.com/api/data/employees');
    $result = json_decode($response->body());

    return $result;
  }

    public function projectYmir(Request $request)
    {
        $rr_no = $request->rr_no;
        $po_no = $request->po_no;
//        $transaction = Http::withToken('1196|kLQEntbfJxoMkcrVuW36vuWYH00hqwQ9fe2lYBFK')
//            ->get('http://10.10.13.6:8080/api/fisto_api', [
//                'pagination' => 'none'
//            ]);

        $transaction = Http::withHeaders([
            'Token' => 'Bearer 1686|UeZtf76cXc4l4WU0EMMMTO1J7lZmd3EgCtGKfBYZ'
//            'Token' => 'Bearer 2150|MwO0CdFLtlZs41dLwvXRaYBhIy6VPHeqq3VtO4F3'
        ])
            ->get('https://rdfymir.com/backend/public/api/fisto_api', [
                'pagination' => 'none'
            ]);

//        ->get('http://10.10.13.6:8080/api/fisto_api', [
//        'pagination' => 'none'
//    ]);


        $data = json_decode($transaction->body(), true);

//        return $data;
        if (isset($data['result'])) {
            $data = $data['result'];
        } else {
            $data = [];
        }

        $data = array_map(function ($item) {
            return [
                'is_new_po' => true,
                'rr_year_number_id' => $item['rr_year_number_id'],
                'po_transaction' => [
                    'po_year_number_id' => $item['po_transaction']['po_year_number_id'],
                    'po_description' => $item['po_transaction']['po_description'],
                ],
                'rr_orders' => array_map(function ($rr) {
                    return [
                        'item_code' => $rr['item_code'],
                        'item_name' => $rr['item_name'],
                        'quantity_receive' => $rr['quantity_receive'],
                        'order' => [
                            'item_code' => $rr['order']['item_code'],
                            'item_name' => $rr['order']['item_name'],
                            'price' => $rr['order']['price'],
                            'uom' => [
                                'code' => $rr['order']['uom']['code'],
                                'name' => $rr['order']['uom']['name'],
                            ],
                        ],
                    ];
                }, $item['rr_orders']),
            ];
        }, $data);

        $filtered = collect($data)->filter(function ($value, $key) use ($rr_no, $po_no) {
            return ($value['rr_year_number_id'] == $rr_no && $value['po_transaction']['po_year_number_id'] == $po_no);
        });

        return response()->json([
            'code' => $filtered->isEmpty() ? 404 : 200,
            'message' => 'Po numbers has been fetched.',
            'result' => $filtered->values(),
        ], $filtered->isEmpty() ? 404 : 200);
    }

//    public function projectYmir(Request $request)
//    {
//        $rr_no = $request->rr_no;
//        $po_no = $request->po_no;
////        $transaction = Http::withToken('1196|kLQEntbfJxoMkcrVuW36vuWYH00hqwQ9fe2lYBFK')
////            ->get('http://10.10.13.6:8080/api/fisto_api', [
////                'pagination' => 'none'
////            ]);
//
//        $transaction = Http::withHeaders([
////            'Token' => 'Bearer 1686|UeZtf76cXc4l4WU0EMMMTO1J7lZmd3EgCtGKfBYZ'
//            'Token' => 'Bearer 2150|MwO0CdFLtlZs41dLwvXRaYBhIy6VPHeqq3VtO4F3'
//        ])
////            ->get('https://rdfymir.com/backend/public/api/fisto_api', [
////                'pagination' => 'none'
////            ]);
//
//            ->get('http://10.10.13.6:8080/api/fisto_api', [
//                'pagination' => 'none'
//            ]);
//
//
//        $data = json_decode($transaction->body(), true);
//
////        return $data;
//        if (isset($data['result'])) {
//            $data = $data['result'];
//        } else {
//            $data = [];
//        }
//
//        $data = array_map(function ($item) {
//            $rr = array_values(array_filter($item['rr_orders'], function ($rr) {
//                return $rr['f_tagged'] == 0;
//            }));
//
//            return [
//                'is_new_po' => true,
//                'id' => $item['id'],
//                'rr_year_number_id' => $item['rr_year_number_id'],
//                'po_transaction' => [
//                    'po_year_number_id' => $item['po_transaction']['po_year_number_id'],
//                    'po_description' => $item['po_transaction']['po_description'],
//                ],
//                'rr_orders' => array_map(function ($rr) {
//                    return [
//                        'f_tagged' => $rr['f_tagged'],
//                        'item_code' => $rr['item_code'],
//                        'item_name' => $rr['item_name'],
//                        'quantity_receive' => $rr['quantity_receive'],
//                        'order' => [
//                            'item_code' => $rr['order']['item_code'],
//                            'item_name' => $rr['order']['item_name'],
//                            'price' => $rr['order']['price'],
//                            'uom' => [
//                                'code' => $rr['order']['uom']['code'],
//                                'name' => $rr['order']['uom']['name'],
//                            ],
//                        ],
//                    ];
//                }, $rr),
//            ];
//        }, $data);
//
//        $filtered = collect($data)->filter(function ($value, $key) use ($rr_no, $po_no) {
//            return ($value['rr_year_number_id'] == $rr_no && $value['po_transaction']['po_year_number_id'] == $po_no);
//        });
//
//        return response()->json([
//            'code' => $filtered->isEmpty() ? 404 : 200,
//            'message' => 'Po numbers has been fetched.',
//            'result' => $filtered->values(),
//        ], $filtered->isEmpty() ? 404 : 200);
//    }
}
