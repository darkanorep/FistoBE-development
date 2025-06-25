<?php

use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\SubUnitController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Controllers\TreasuryChequeController;
use App\Http\Controllers\VoucherCodeController;
use App\Methods\TransactionFlow;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\OrganizationDepartmentController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReasonController;
use App\Http\Controllers\ReferrenceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierTypeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MasterlistController;
use App\Http\Controllers\UtilityCategoryController;
use App\Http\Controllers\UtilityLocationController;
use App\Http\Controllers\TransactionFlowController;
use App\Http\Controllers\AccountNumberController;
use App\Http\Controllers\AccountTitleController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\PayrollClientController;
use App\Http\Controllers\PayrollCategoryController;
use App\Http\Controllers\PayrollTypeController;
use App\Http\Controllers\CounterReceiptController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

//  Public Routes
Route::post("/login", [UserController::class, "login"])->name("login");
Route::get("/coa", [MasterlistController::class, "coa"]);
Route::get("/sedar", [MasterlistController::class, "sedar_employees"]);
Route::get("/genus", [MasterlistController::class, "genus_orders"]);
Route::get('/ymir', [MasterlistController::class, 'projectYmir']);


//Route::middleware('api.key')->group(function () {
//    Route::patch('one-charging/{id}', [\App\Http\Controllers\ChargeController::class, 'change_status']);
//    Route::resource('one-charging', \App\Http\Controllers\ChargeController::class)->only(['index', 'store']);
//});

//Route::get('fix-year', [TransactionController::class, 'fixYearFormat']);
// Protected Routes
// Route::middleware('auth:sanctum')->get('/authenticated', function (Request $request) {
//     return $request->user();
// });

Route::group(["middleware" => "auth:sanctum"], function () {
//    Route::get('sync-sedar', [UserController::class, 'syncSedar']);
    Route::post("logout/", [UserController::class, "logout"]);
    Route::put("users/change-password", [UserController::class, "change_password"]);
    Route::post("users/username-validation", [UserController::class, "username_validation"]);
    Route::post("users/id-validation", [UserController::class, "id_validation"]);
    Route::get("/official-transactions", [TransactionController::class, "officialTransactions"]); // GIZMO API

    Route::group(["prefix" => "dropdown"], function () {
        Route::get("payroll-categories/", [PayrollCategoryController::class, "index"]);
        Route::get("payroll-clients/", [PayrollClientController::class, "index"]);
        Route::get("utility-categories/", [UtilityCategoryController::class, "index"]);
        Route::get("utility-locations/", [UtilityLocationController::class, "index"]);
        Route::get("suppliers/", [SupplierController::class, "index"]);
        Route::get("account-numbers/", [AccountNumberController::class, "index"]);
        Route::get("current-user/", [MasterlistController::class, "currentUser"]);
        Route::get("charging/", [MasterlistController::class, "chargingDropdown"]);
        Route::get("user/departments/", [TransactionController::class, "showUserDepartment"]);
        Route::get("references/", [ReferrenceController::class, "index"]);
        Route::get("reason/", [ReasonController::class, "index"]);
        Route::get("associate", [MasterlistController::class, "associateDropdown"]);
        Route::get("approver", [MasterlistController::class, "approverDropdown"]);
        Route::get("specialist", [MasterlistController::class, "specialistDropdown"]);
        Route::get("account-title", [MasterlistController::class, "transactionAccountTitleDropdown"]);
        Route::get("credit-card", [CreditCardController::class, "index"]);
//    Route::get("account-title/{id}", [MasterlistController::class, "accountTitleDocumentDropdown"]);
        Route::get("account-title/{id}", [MasterlistController::class, "accountTitleTransactionTypeDropdown"]);
        // TRANSACTION
        Route::get("company", [CompanyController::class, "index"]);
        Route::get("department", [DepartmentController::class, "index"]);
        Route::get("location", [LocationController::class, "index"]);
        Route::get("bank-account-title", [BankController::class, "index"]);
        Route::get("transaction-types", [MasterlistController::class, "transactionTypeDropdown"]);
        Route::get("business-unit", [BusinessUnitController::class, "index"]);
        Route::get("unit", [\App\Http\Controllers\UnitController::class, "index"]);
        Route::get("sub-unit", [SubUnitController::class, "index"]);
        Route::get("voucher-number", [TransactionController::class, "voucherNumberDropdown"]);
        Route::get('general-journals-numbers', [TransactionController::class, 'generalNumbersDropdown']);
        Route::get('cheque-types', [MasterlistController::class, 'chequeTypesDropdown']);
        Route::get('permissions', [\App\Http\Controllers\PermissionController::class, 'index']);
        Route::get('one-charging', [\App\Http\Controllers\ChargeController::class, 'index']);
    });

    Route::group(["prefix" => "admin", "middleware" => ["auth" => "is_admin"]], function () {
        Route::group(["prefix" => "dropdown"], function () {
            //MASTER LIST GENERIC METHOD
            Route::get("document", [MasterlistController::class, "documentDropdown"]);
            Route::get("category", [MasterlistController::class, "categoryDropdown"]);
            Route::get("supplier-reference", [MasterlistController::class, "supplierRefDropdown"]);
            Route::get("location-category-supplier", [MasterlistController::class, "loccatsupDropdown"]);
            Route::get("location-category", [MasterlistController::class, "loccatDropdown"]);
            Route::get("account-title", [MasterlistController::class, "accountTitleDropdown"]);
            Route::get("company", [MasterlistController::class, "companyDropdown"]);
            Route::get("business_unit", [MasterlistController::class, "businessUnitDropdown"]);
            Route::get("organization", [MasterlistController::class, "organizationDropdown"]);
            Route::get("department", [MasterlistController::class, "departmentDropdown"]);
            Route::get("associate", [MasterlistController::class, "associateDropdown"]);
            Route::get("treasuries", [MasterlistController::class, "treasuriesDropdown"]);
            Route::get("users", [MasterlistController::class, "usersDropdown"]);
            Route::get("voucher-code", [MasterlistController::class, "voucherCodeDropdown"]);
            Route::get("account-title-great-grand-parent", [MasterlistController::class, "accountTitleGreatGrandParentsDropdown"]);
            Route::get("account-title-grand-parent", [MasterlistController::class, "accountTitleGrandParentsDropdown"]);
            Route::get("account-title-parent", [MasterlistController::class, "accountTitleParentsDropdown"]);
            Route::get("account-title-child", [MasterlistController::class, "accountTitleChildrenDropdown"]);
            Route::get("account-title-pnl", [MasterlistController::class, "accountTitlePnlsDropdown"]);
            Route::get("account-title-unit", [MasterlistController::class, "accountTitleUnitsDropdown"]);
            Route::get('permissions', [MasterlistController::class, "permissionsDropdown"]);
        });

        //ONE CHARGING
        Route::patch('one-charging/{id}', [\App\Http\Controllers\ChargeController::class, 'change_status']);
        Route::resource('one-charging', \App\Http\Controllers\ChargeController::class)->only(['index', 'store']);

        //PERMISSIONS
        Route::patch("permissions/{id}", [\App\Http\Controllers\PermissionController::class, "change_status"]);
        Route::resource('permissions', \App\Http\Controllers\PermissionController::class)->only(['index', 'store']);
        // CATEGORY
        Route::patch("categories/{id}", [CategoryController::class, "change_status"]);
        Route::resource("categories", CategoryController::class);

        // DOCUMENTS
        Route::patch("documents/{id}", [DocumentController::class, "change_status"]);
        Route::resource("documents", DocumentController::class);

        // REASON
        Route::patch("reasons/{id}", [ReasonController::class, "change_status"]);
        Route::resource("reasons", ReasonController::class);

        // BANK
        Route::patch("banks/{id}", [BankController::class, "change_status"]);
        Route::post("banks/import/", [BankController::class, "import"]);
        Route::resource("banks", BankController::class);

        // SUPPLIER TYPE
        Route::patch("supplier-types/{id}", [SupplierTypeController::class, "change_status"]);
        Route::resource("supplier-types", SupplierTypeController::class);

        // SUPPLIER
        Route::patch("suppliers/{id}", [SupplierController::class, "change_status"]);
        Route::post("suppliers/import/", [SupplierController::class, "import"]);
        Route::resource("suppliers", SupplierController::class);

        // REFERRENCE
        Route::patch("referrences/{id}", [ReferrenceController::class, "change_status"]);
        Route::resource("referrences", ReferrenceController::class);

        // ACCOUNT TITLE
        Route::patch("account-title/{id}", [AccountTitleController::class, "change_status"]);
        Route::post("account-title/import", [AccountTitleController::class, "import"]);
        Route::resource("account-title", AccountTitleController::class);

        // ACCOUNT #
        Route::patch("account-number/{id}", [AccountNumberController::class, "change_status"]);
        Route::post("account-number/import/", [AccountNumberController::class, "import"]);
        Route::resource("account-number", AccountNumberController::class);

        // PAYROLL CLIENT
        Route::patch("payroll-client/{id}", [PayrollClientController::class, "change_status"]);
        Route::resource("payroll-client", PayrollClientController::class);

        // PAYROLL CATEGORY
        Route::patch("payroll-category/{id}", [PayrollCategoryController::class, "change_status"]);
        Route::resource("payroll-category", PayrollCategoryController::class);

        // UTILITY CATEGORY
        Route::patch("utility-category/{id}", [UtilityCategoryController::class, "change_status"]);
        Route::resource("utility-category", UtilityCategoryController::class);

        // UTILITY LOCATION
        Route::patch("utility-location/{id}", [UtilityLocationController::class, "change_status"]);
        Route::resource("utility-location", UtilityLocationController::class);

        // CREDIT CARD
        Route::patch("credit-card/{id}", [CreditCardController::class, "change_status"]);
        Route::resource("credit-card", CreditCardController::class);

        // USER
        Route::patch("users/{id}", [UserController::class, "change_status"]);
        Route::patch("users/reset/{id}", [UserController::class, "reset"]);
        Route::resource("users", UserController::class);

        // COMPANY
        Route::patch("companies/{id}", [CompanyController::class, "change_status"]);
        Route::post("companies/sync", [CompanyController::class, "sync"]);
        Route::resource("companies", CompanyController::class);

        // DEPARTMENT
        Route::post("departments/import", [DepartmentController::class, "import"]);
        Route::patch("departments/{id}", [DepartmentController::class, "change_status"]);
        Route::resource("departments", DepartmentController::class);

        // LOCATION
        Route::post("locations/import", [LocationController::class, "import"]);
        Route::patch("locations/{id}", [LocationController::class, "change_status"]);
        Route::resource("locations", LocationController::class);

        // ORGANIZATION
        Route::put("organization", [OrganizationDepartmentController::class, "import"]);
        Route::resource("organization", OrganizationDepartmentController::class);

        //BUSINESS UNIT
        Route::patch('business-units/{id}', [BusinessUnitController::class, 'change_status']);
        Route::post("business-units/sync", [BusinessUnitController::class, "sync"]);
        Route::resource("business-units", BusinessUnitController::class);

        //UNIT
        Route::patch('units/{id}', [\App\Http\Controllers\UnitController::class, 'change_status']);
        Route::post("units/import", [\App\Http\Controllers\UnitController::class, "import"]);
        Route::resource("units", \App\Http\Controllers\UnitController::class);

        //SUB UNIT
        Route::patch("sub-units/{id}", [SubUnitController::class, "change_status"]);
        Route::resource("sub-units", SubUnitController::class);
        Route::post("sub-units/import", [SubUnitController::class, "import"]);

        //DOCUMENT COA
//      Route::patch("document-coa/{id}", [\App\Http\Controllers\DocumentCoaController::class, "change_status"]);
//      Route::resource("document-coa", \App\Http\Controllers\DocumentCoaController::class);

        Route::patch('transaction-types/{id}', [TransactionTypeController::class, "change_status"]);
        Route::resource('transaction-types', TransactionTypeController::class);

        //VOUCHER CODE
        Route::patch('voucher-codes/{id}', [VoucherCodeController::class, "change_status"]);
        Route::resource("voucher-codes", VoucherCodeController::class);

        //ACCOUNT TITLE ACCOUNT TYPE
        Route::patch('account-title-great-grand-parents/{id}', [\App\Http\Controllers\AccountTitleGreatGrandParentController::class, "change_status"]);
        Route::resource("account-title-great-grand-parents", \App\Http\Controllers\AccountTitleGreatGrandParentController::class);

        //ACCOUNT TITLE ACCOUNT GROUP
        Route::patch('account-title-grand-parents/{id}', [\App\Http\Controllers\AccountTitleGrandParentController::class, "change_status"]);
        Route::resource("account-title-grand-parents", \App\Http\Controllers\AccountTitleGrandParentController::class);

        //ACCOUNT TITLE SUBGROUP
        Route::patch('account-title-parents/{id}', [\App\Http\Controllers\AccountTitleParentController::class, "change_status"]);
        Route::resource("account-title-parents", \App\Http\Controllers\AccountTitleParentController::class);

        //ACCOUNT TITLE FINANCIAL STATEMENT
        Route::patch('account-title-children/{id}', [\App\Http\Controllers\AccountTitleChildController::class, "change_status"]);
        Route::resource("account-title-children", \App\Http\Controllers\AccountTitleChildController::class);

        //ACCOUNT TITLE NORMAL BALANCE
        Route::patch('account-title-pnls/{id}', [\App\Http\Controllers\AccountTitlePnLController::class, "change_status"]);
        Route::resource("account-title-pnls", \App\Http\Controllers\AccountTitlePnLController::class);

        //ACCOUNT TITLE UNIT
        Route::patch('account-title-units/{id}', [\App\Http\Controllers\AccountTitleUnitController::class, "change_status"]);
        Route::resource("account-title-units", \App\Http\Controllers\AccountTitleUnitController::class);

        //BANK SERIES
        Route::patch('bank-series/{id}', [\App\Http\Controllers\BankSeriesController::class, "change_status"]);
        Route::resource('bank-series', \App\Http\Controllers\BankSeriesController::class);

        //TREASURY CHEQUE
        Route::patch('treasury-cheques/{id}', [\App\Http\Controllers\TreasuryChequeController::class, "change_status"]);
        Route::resource('treasury-cheques', \App\Http\Controllers\TreasuryChequeController::class);

        //DEBIT USER
        Route::patch('debit-users/{id}', [\App\Http\Controllers\DebitUserController::class, "change_status"]);
        Route::resource('debit-users', \App\Http\Controllers\DebitUserController::class);

        //JOURNAL USER
        Route::patch('journal-users/{id}', [\App\Http\Controllers\JournalUserController::class, "change_status"]);
        Route::resource('journal-users', \App\Http\Controllers\JournalUserController::class);

        //TRANSACTION REPORT
        Route::patch('transaction-reports/{id}', [\App\Http\Controllers\TransactionReportController::class, "change_status"]);
        Route::resource('transaction-reports', \App\Http\Controllers\TransactionReportController::class);

        //BOOK OF ACCOUNTS
        Route::patch('book-of-accounts/{id}', [\App\Http\Controllers\BookOfAccountController::class, "change_status"]);
        Route::resource('book-of-accounts', \App\Http\Controllers\BookOfAccountController::class);

    });

    //ONE CHARGING
    Route::get('one-charging/search', [\App\Http\Controllers\ChargeController::class, 'searchCharging']);

    // USER
    Route::post("users/department-validation/", [UserController::class, "departmentValidation"]);
    Route::get('debit-users', [\App\Http\Controllers\DebitUserController::class, 'index']);

    //MULTI
    Route::post("transactions/flow/receive", [TransactionFlowController::class, "multipleReceive"]);
    Route::post("transactions/flow/tag", [TransactionFlowController::class, "multipleTag"]);
    Route::post("transactions/flow/cheque", [TransactionFlowController::class, "multipleCheque"]);
    Route::post("cheques/flow/issue", [TransactionFlowController::class, "multipleChequeDateIssue"]);
    Route::post("cheques/flow/receive", [TransactionFlowController::class, "multipleChequeReceive"]);
    Route::post("cheques/flow/clear", [TransactionFlowController::class, "multipleChequeClear"]);

    // CHEQUES
    Route::get("cheques", [TransactionController::class, "chequeIndex"]);
    Route::get("clear-cheques", [TransactionController::class, "clearChequeIndex"]);
    Route::post("transactions/flow/clear-cheques/{id}", [TransactionController::class, "chequeClear"]);
    Route::post('transactions/flow/cheque-revert/{id}', [TransactionController::class, "chequeRevert"]);

    //Cheque Index distinct
    Route::get("cheques1", [TransactionController::class, "chequeIndex1"]);

    //Cheque Flow
    Route::group(["prefix" => "cheque"], function () {
        Route::post('flow', [TransactionFlow::class, "chequeFlow"]);
        Route::post('flow/multiple-process', [TransactionFlow::class, "multipleChequeProcess"]);
        Route::get('history/{id}', [TransactionController::class, "chequeHistory"]);
        Route::post('uncollected', [TransactionFlowController::class, 'uncollectedCheques']);
    });

    Route::get('/status-transactions-count', [TransactionController::class, 'statusTransactionCounter']);
    Route::get('/status-cheques-count', [TransactionController::class, 'statusChequeCounter']);
    Route::get("transactions-history", [TransactionController::class, "history"]);
    Route::get("transactions-history-export", [TransactionController::class, "exportHistory"]);
    Route::get("cheques-history", [TransactionController::class, "historyChequeIndex"]);
    Route::get("voucher-transaction/{id}", [TransactionController::class, 'voucherTransaction']);
    Route::get("cheque-transaction/{id}", [TransactionController::class, 'chequeTransaction']);
    Route::get('cheque-number', [\App\Http\Controllers\BankSeriesController::class, 'chequeNumberAvailable']);

//    Route::middleware('journal_user')->group(function () {
//    });

    //GENERAL JOURNAL - AP
    Route::patch('general-journals/post/{id}', [\App\Http\Controllers\GeneralJournalController::class, 'posted']);
    Route::post("general-journals/import", [\App\Http\Controllers\GeneralJournalController::class, 'import']);
    Route::resource("general-journals", \App\Http\Controllers\GeneralJournalController::class);
    Route::post('update/general-journals/{id}', [\App\Http\Controllers\GeneralJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - ACCRUAL REVERSAL
    Route::patch('accruals/reverse', [\App\Http\Controllers\AccrualsController::class, 'reverse']);
    Route::post('accruals/import', [\App\Http\Controllers\AccrualsController::class, 'import']);
    Route::resource('accruals', \App\Http\Controllers\AccrualsController::class);
    Route::post('update/accruals/{id}', [\App\Http\Controllers\AccrualsController::class, 'updateAccruals']);
//    Route::patch('accruals/reverse/{id}', [\App\Http\Controllers\AccrualsController::class, 'reverse']);

    //GENERAL JOURNAL - TREASURY
    Route::patch('treasury-journals/post/{id}', [\App\Http\Controllers\TreasuryJournalController::class, 'posted']);
    Route::post("treasury-journals/import", [\App\Http\Controllers\TreasuryJournalController::class, 'import']);
    Route::resource("treasury-journals", \App\Http\Controllers\TreasuryJournalController::class);
    Route::post('update/treasury-journals/{id}', [\App\Http\Controllers\TreasuryJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - COST AND BUDGET
    Route::patch('cost-and-budget-journals/post/{id}', [\App\Http\Controllers\CostAndBudgetJournalController::class, 'posted']);
    Route::post("cost-and-budget-journals/import", [\App\Http\Controllers\CostAndBudgetJournalController::class, 'import']);
    Route::resource("cost-and-budget-journals", \App\Http\Controllers\CostAndBudgetJournalController::class);
    Route::post('update/cost-and-budget-journals/{id}', [\App\Http\Controllers\CostAndBudgetJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - FIXED ASSET
    Route::patch('fixed-asset-journals/post/{id}', [\App\Http\Controllers\FixedAssetJournalController::class, 'posted']);
    Route::post("fixed-asset-journals/import", [\App\Http\Controllers\FixedAssetJournalController::class, 'import']);
    Route::resource("fixed-asset-journals", \App\Http\Controllers\FixedAssetJournalController::class);
    Route::post('update/fixed-asset-journals/{id}', [\App\Http\Controllers\FixedAssetJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - CONFIDENTIAL
    Route::patch('confidential-journals/post/{id}', [\App\Http\Controllers\ConfidentialJournalController::class, 'posted']);
    Route::post("confidential-journals/import", [\App\Http\Controllers\ConfidentialJournalController::class, 'import']);
    Route::resource("confidential-journals", \App\Http\Controllers\ConfidentialJournalController::class);
    Route::post('update/confidential-journals/{id}', [\App\Http\Controllers\ConfidentialJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - SALES
    Route::patch('sales-journals/post/{id}', [\App\Http\Controllers\SalesJournalController::class, 'posted']);
    Route::post("sales-journals/import", [\App\Http\Controllers\SalesJournalController::class, 'import']);
    Route::resource("sales-journals", \App\Http\Controllers\SalesJournalController::class);
    Route::post('update/sales-journals/{id}', [\App\Http\Controllers\SalesJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - FO
    Route::patch('fo-journals/post/{id}', [\App\Http\Controllers\FoJournalController::class, 'posted']);
    Route::post("fo-journals/import", [\App\Http\Controllers\FoJournalController::class, 'import']);
    Route::resource("fo-journals", \App\Http\Controllers\FoJournalController::class);
    Route::post('update/fo-journals/{id}', [\App\Http\Controllers\FoJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - LIVE
    Route::patch('live-journals/post/{id}', [\App\Http\Controllers\LiveJournalController::class, 'posted']);
    Route::post("live-journals/import", [\App\Http\Controllers\LiveJournalController::class, 'import']);
    Route::resource("live-journals", \App\Http\Controllers\LiveJournalController::class);
    Route::post('update/live-journals/{id}', [\App\Http\Controllers\LiveJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - ACCOUNT RECEIVABLE
    Route::patch('account-receivable-journals/post/{id}', [\App\Http\Controllers\AccountReceivableJournalController::class, 'posted']);
    Route::post("account-receivable-journals/import", [\App\Http\Controllers\AccountReceivableJournalController::class, 'import']);
    Route::resource("account-receivable-journals", \App\Http\Controllers\AccountReceivableJournalController::class);
    Route::post('update/account-receivable-journals/{id}', [\App\Http\Controllers\AccountReceivableJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - AP SPECIALIST
    Route::patch('ap-specialist-journals/post/{id}', [\App\Http\Controllers\ApSpecialistJournalController::class, 'posted']);
    Route::post("ap-specialist-journals/import", [\App\Http\Controllers\ApSpecialistJournalController::class, 'import']);
    Route::resource("ap-specialist-journals", \App\Http\Controllers\ApSpecialistJournalController::class);
    Route::post('update/ap-specialist-journals/{id}', [\App\Http\Controllers\ApSpecialistJournalController::class, 'updateGeneralJournal']);

    //GENERAL JOURNAL - PCF
    Route::patch('pcf-journals/post/{id}', [\App\Http\Controllers\PcfJournalController::class, 'posted']);
    Route::post("pcf-journals/import", [\App\Http\Controllers\PcfJournalController::class, 'import']);
    Route::resource("pcf-journals", \App\Http\Controllers\PcfJournalController::class);
    Route::post('update/pcf-journals/{id}', [\App\Http\Controllers\PcfJournalController::class, 'updateGeneralJournal']);

    //JOURNALS FOR APPROVAL
    Route::group(['prefix' => 'journal-books'], function () {

        //GENERAL JOURNAL - AP
        Route::get('general-journals', [\App\Http\Controllers\GeneralJournalController::class, 'indexForApproval']);
        Route::patch('general-journals/{id}', [\App\Http\Controllers\GeneralJournalController::class, 'action']);

        //GENERAL JOURNAL - ACCRUALS REVERSAL
        Route::get('accruals', [\App\Http\Controllers\AccrualsController::class, 'indexForApproval']);
        Route::patch('accruals/{id}', [\App\Http\Controllers\AccrualsController::class, 'action']);

        //GENERAL JOURNAL - TREASURY
        Route::get('treasury-journals', [\App\Http\Controllers\TreasuryJournalController::class, 'indexForApproval']);
        Route::patch('treasury-journals/{id}', [\App\Http\Controllers\TreasuryJournalController::class, 'action']);

        //GENERAL JOURNAL - COST AND BUDGET
        Route::get('cost-and-budget-journals', [\App\Http\Controllers\CostAndBudgetJournalController::class, 'indexForApproval']);
        Route::patch('cost-and-budget-journals/{id}', [\App\Http\Controllers\CostAndBudgetJournalController::class, 'action']);

        //GENERAL JOURNAL - FIXED ASSET
        Route::get('fixed-asset-journals', [\App\Http\Controllers\FixedAssetJournalController::class, 'indexForApproval']);
        Route::patch('fixed-asset-journals/{id}', [\App\Http\Controllers\FixedAssetJournalController::class, 'action']);

        //GENERAL JOURNAL - CONFIDENTIAL
        Route::get('confidential-journals', [\App\Http\Controllers\ConfidentialJournalController::class, 'indexForApproval']);
        Route::patch('confidential-journals/{id}', [\App\Http\Controllers\ConfidentialJournalController::class, 'action']);

        //GENERAL JOURNAL - SALES
        Route::get('sales-journals', [\App\Http\Controllers\SalesJournalController::class, 'indexForApproval']);
        Route::patch('sales-journals/{id}', [\App\Http\Controllers\SalesJournalController::class, 'action']);

        //GENERAL JOURNAL - FO
        Route::get('fo-journals', [\App\Http\Controllers\FoJournalController::class, 'indexForApproval']);
        Route::patch('fo-journals/{id}', [\App\Http\Controllers\FoJournalController::class, 'action']);

        //GENERAL JOURNAL - LIVE
        Route::get('live-journals', [\App\Http\Controllers\LiveJournalController::class, 'indexForApproval']);
        Route::patch('live-journals/{id}', [\App\Http\Controllers\LiveJournalController::class, 'action']);

        //GENERAL JOURNAL - ACCOUNT RECEIVABLE
        Route::get('account-receivable-journals', [\App\Http\Controllers\AccountReceivableJournalController::class, 'indexForApproval']);
        Route::patch('account-receivable-journals/{id}', [\App\Http\Controllers\AccountReceivableJournalController::class, 'action']);

        //GENERAL JOURNAL - AP SPECIALIST
        Route::get('ap-specialist-journals', [\App\Http\Controllers\ApSpecialistJournalController::class, 'indexForApproval']);
        Route::patch('ap-specialist-journals/{id}', [\App\Http\Controllers\ApSpecialistJournalController::class, 'action']);

        //GENERAL JOURNAL - PCF
        Route::get('pcf-journals', [\App\Http\Controllers\PcfJournalController::class, 'indexForApproval']);
        Route::patch('pcf-journals/{id}', [\App\Http\Controllers\PcfJournalController::class, 'action']);
    });

    Route::resource("transactions", TransactionController::class);
//    Route::post('transactions-test', [TransactionController::class, "store1"]);

    Route::group(["prefix" => "transactions"], function () {
        //TRANSACTION
//        Route::get("logs/request", [TransactionController::class, "viewRequestorLogs"]);
//        Route::get("status_group/", [TransactionController::class, "status_group"]);
        Route::post("void/{id}", [TransactionController::class, "voidTransaction"]);
        Route::post("validate-po-no", [TransactionController::class, "getPODetails"]);
        Route::post("validate-document-no", [TransactionController::class, "validateDocumentNo"]);
        Route::post("validate-reference-no", [TransactionController::class, "validateReferenceNo"]);
        Route::post("validate-pcf-name/", [TransactionController::class, "validatePCFName"]);
        Route::post("validate-soa-no/", [TransactionController::class, "validateSOANumber"]);

        // TRANSACTION FLOW
        Route::group(["prefix" => "flow"], function () {
            Route::post("update-transaction/{id}", [TransactionFlowController::class, "updateInTransactionFlow"]);
            Route::put("update-receipt/{id}", [TransactionFlowController::class, "updateReceiptTypeTransaction"]);
            Route::put('update-remarks/{id}', [TransactionFlowController::class, 'updateTransactionRemarks']);
            Route::post("validate-voucher-no", [TransactionFlowController::class, "validateVoucherNo"]);
            Route::post("validate-cheque-no", [TransactionFlowController::class, "validateChequeNo"]);
            Route::put("transfer/{id}", [TransactionFlowController::class, "transfer"]);

            //BANK SERIES
            Route::get('bank-documents', [TransactionFlow::class, 'bankDocuments']);
            Route::get('available-cheque-number', [TransactionFlow::class, 'availableChequeNo']);

            //MULTI
            Route::post("receive", [TransactionFlowController::class, "multipleReceive"]);
            Route::post("tag", [TransactionFlowController::class, "multipleTag"]);
            ROute::post('multiple-process', [TransactionFlowController::class, "multipleProcess"]);
            Route::post("cheque", [TransactionFlowController::class, "multipleCheque"]);
            Route::post("mcloan", [TransactionFlowController::class, "applicationForLoan"]);
            Route::get('payment-vouchers', [TransactionFlowController::class, "paymentVouchers"]);

            //CHEQUE
            Route::post("clear-cheques/{id}", [TransactionController::class, "chequeClear"]);
            Route::post('cheque-revert/{id}', [TransactionController::class, "chequeRevert"]);

            Route::post('cheque-revert', [TransactionController::class, "chequeRevert1"]);
//            Route::get('multiple-vouchers', [TransactionController::class, 'multipleVouchers']);

            //REPORT
            Route::get('cash-outflow-report', [TransactionController::class, 'cashOutflowReport']);
            Route::get('report', [TransactionController::class, 'generateAPReport']);

            //SPECIAL CASE
            Route::get('search-cheque', [TransactionController::class, 'searchBankCheque']);
            Route::post('adjust-date', [TransactionController::class, 'adjustDate']);
        });
    });

    Route::group(["prefix" => "counter-receipts"], function () {
        Route::get("", [CounterReceiptController::class, "index"]);
        Route::get("counter/{counter}", [CounterReceiptController::class, "showCounter"]);
        Route::get("receipt/{receipt}", [CounterReceiptController::class, "showReceipt"]);
        Route::post("", [CounterReceiptController::class, "store"]);
        Route::put("{counter}", [CounterReceiptController::class, "update"]);
        Route::post("download", [CounterReceiptController::class, "download"]);
        Route::post("validate", [CounterReceiptController::class, "check"]);
        Route::post("flow/{id}", [CounterReceiptController::class, "flow"]);
    });

    //SETTINGS
    Route::patch('toggle/{id}', [\App\Http\Controllers\SettingController::class, 'toggleEntry']);
    Route::resource('settings', \App\Http\Controllers\SettingController::class)->only(['index', 'update']);
});
