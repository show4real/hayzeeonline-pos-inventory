<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\CreditorController;
use App\Http\Controllers\Api\CreditorPaymentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\IpAddressController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ProductAttributeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderSerialController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Loaded by withRouting() in bootstrap/app.php and wrapped in the "api"
| middleware group. Controllers are named by class: Laravel dropped the
| implicit App\Http\Controllers namespace that the old string actions
| ('Api\FooController@bar') relied on.
|
*/ 
// Route::post('/register', [RegisterController::class, 'register']);
// Route::prefix('item')->group(function(){
//   Route::post('/{id}', [RegisterController::class, 'store']);
// });
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
// -----------------------------------------------------------------------------
// Password recovery — DEAD ENDPOINTS, left commented deliberately.
//
// All three named App\Http\Controllers\Api\ForgotPasswordController, which
// does not exist in this repository and appears in no commit in its history.
// The only thing that ever did was the stock scaffold under Controllers/Auth,
// which had none of these methods and was removed by this upgrade: it was
// unreachable, and it depended on the Illuminate\Foundation\Auth traits that
// Laravel 8 moved out of the framework into laravel/ui.
//
// So these have never worked: on Laravel 6 they registered and then failed at
// request time with "Target class does not exist". The POS UI calls
// `sendrecovery` from src/services/authService.js, so "forgot password" is a
// broken feature in the UI today, and was before this upgrade.
//
// They are commented out rather than converted because on Laravel 12 a string
// action with no class behind it throws when the route file is *loaded*, which
// would take down every other route here. Restore them together with the
// controller — the User model still carries recovery_code and recovery_expiry.
// -----------------------------------------------------------------------------
// Route::get('/recoverpassword/{recovery_code}', [Api\ForgotPasswordController::class, 'recover']);
Route::post('update/client/payments', [ClientController::class, 'updateClientOnPayment']);


Route::post('/test', [IpAddressController::class, 'index']);

// Route::post('/changepassword', [Api\ForgotPasswordController::class, 'changepassword']);
// Route::post('/sendrecovery', [Api\ForgotPasswordController::class, 'sendrecovery']);
 Route::post('all-products', [ProductController::class, 'getAllProducts']);


Route::group(['middleware'=>['jwt.auth','CheckAdmin']],
function(){

    Route::post('dashboards', [DashboardController::class, 'index']);
    Route::post('addcompany', [CompanySettingsController::class, 'save']);
    Route::get('company', [CompanySettingsController::class, 'index']);

    Route::post('users', [UserController::class, 'index']);
    Route::get('user/{user}', [UserController::class, 'show']);
    Route::post('adduser', [UserController::class, 'save']);
    Route::post('deleteuser/{user}', [UserController::class, 'delete']);
    Route::post('searchusers', [UserController::class, 'search']);
    Route::post('updateuser/{user}', [UserController::class, 'update']);


    Route::post('categories', [CategoryController::class, 'index']);
    Route::post('addcategories', [CategoryController::class, 'save']);
    Route::post('updatecategory/{category}', [CategoryController::class, 'update']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::delete('categories/{category}', [CategoryController::class, 'delete']);

    Route::post('branches', [BranchController::class, 'index']);
    Route::post('addbranches', [BranchController::class, 'save']);
    Route::post('updatebranch/{branch}', [BranchController::class, 'update']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
    Route::delete('branches/{branch}', [BranchController::class, 'delete']);

    Route::post('brands', [BrandController::class, 'index']);
    Route::post('addbrands', [BrandController::class, 'save']);
    Route::post('updatebrand/{brand}', [BrandController::class, 'update']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);
    Route::delete('brands/{brand}', [BrandController::class, 'delete']);
    
    Route::post('suppliers', [SupplierController::class, 'index']);
    Route::post('addsuppliers', [SupplierController::class, 'save']);
    Route::post('updatesupplier/{supplier}', [SupplierController::class, 'update']);
    Route::get('supplier/{supplier}', [SupplierController::class, 'show']);
    Route::post('deletesupplier/{supplier}', [SupplierController::class, 'delete']);

    Route::post('attributes', [AttributeController::class, 'index']);
    Route::post('addattributes', [AttributeController::class, 'save']);
    Route::put('attributes/{attribute}', [AttributeController::class, 'update']);
    Route::get('attributes/{attribute}', [AttributeController::class, 'show']);
    Route::delete('attributes/{attribute}', [AttributeController::class, 'delete']);

    Route::post('productattributes', [ProductAttributeController::class, 'index']);
    Route::post('addproductattributes', [ProductAttributeController::class, 'save']);
    Route::get('productattributes/{attribute}', [ProductAttributeController::class, 'show']);
    Route::delete('productattributes/{attribute}', [ProductAttributeController::class, 'delete']);

    Route::post('products', [ProductController::class, 'index']);
    Route::post('product', [ProductController::class, 'save']);
    Route::put('product/{product}', [ProductController::class, 'update']);
    Route::get('product/{product}', [ProductController::class, 'show']);
    Route::post('deleteproduct/{product}', [ProductController::class, 'delete']);

    Route::post('product/image/{image}', [ProductImageController::class, 'store']);



    Route::post('product/{product_id}/purchase_orders', [PurchaseOrderController::class, 'index']);
    Route::post('purchase_orders', [PurchaseOrderController::class, 'purchaseOrders']);
    
    Route::post('stocks', [StockController::class, 'stocks']);

    Route::post('branch_stocks', [StockController::class, 'branchStocks']);
    Route::post('stocks2', [StockController::class, 'stocks2']);

    Route::post('stock/{stock}', [StockController::class, 'show']);
    Route::post('saveserial', [StockController::class, 'saveSerial']);
    Route::post('editserial/{id}', [StockController::class, 'editSerial']);
    Route::post('serials', [StockController::class, 'serials']);

    Route::post('returnstock', [StockController::class, 'returnStock']);



    Route::post('purchase_order', [PurchaseOrderController::class, 'stocks']);
// NOTE: four routes below call methods PurchaseOrderController does not define
// — getSalesOrder, saleOrder, multSaleOrder and delete. They are registered
// here exactly as they were on Laravel 6, where calling one failed with
// BadMethodCallException; a missing *method* (unlike a missing class) does not
// break route registration or route:cache, so this upgrade leaves the
// behaviour untouched rather than guessing at an implementation. Delete the
// routes or add the methods — see SalesOrderController for the nearest code.
    Route::post('sales', [PurchaseOrderController::class, 'getSalesOrder']);
    
    Route::post('confirm_order/{order}', [PurchaseOrderController::class, 'confirmOrder']);
    Route::post('return_order/{order}', [PurchaseOrderController::class, 'returnOrder']);
    Route::post('move_order/{order}', [PurchaseOrderController::class, 'moveOrder']);
    Route::post('purchase_order/editserial/{id}', [PurchaseOrderController::class, 'editSerial']);
    Route::post('purchase_order/serials', [PurchaseOrderSerialController::class, 'getSerials']);
    Route::post('purchase_order/editprice', [PurchaseOrderController::class, 'editPrice']);
    Route::post('purchase_order/add-more', [PurchaseOrderController::class, 'addMoreOrder']);

    


    Route::post('sale_order/{order}', [PurchaseOrderController::class, 'saleOrder']);
    Route::post('sale_order', [PurchaseOrderController::class, 'multSaleOrder']);


    Route::post('filtered_attributes', [PurchaseOrderController::class, 'filterAttributes']);

    Route::post('pos_order', [PosController::class, 'multPosOrder']);
     Route::post('edit/pos_order', [PosController::class, 'editMultPosOrder']);

    
    Route::post('pos/products', [PosController::class, 'products']);
    Route::post('pos_sales', [PosController::class, 'getPosSales']);
    Route::post('transaction_details', [PosController::class, 'getTransactionDetails']);

    Route::post('purchase_order', [PurchaseOrderController::class, 'save']);
    Route::post('purchase_order/{order}', [PurchaseOrderController::class, 'show']);
    Route::post('updatepurchase_order/{order}', [PurchaseOrderController::class, 'update']);
    Route::delete('purchase_order/{order}', [PurchaseOrderController::class, 'delete']);

    Route::post('invoices', [InvoiceController::class, 'index']);
    Route::get('invoice/{invoice}', [InvoiceController::class, 'show']);
    Route::get('last_invoice', [InvoiceController::class, 'lastInvoice']);
    Route::post('addinvoice', [InvoiceController::class, 'save']);
    Route::post('deleteinvoice/{invoice}', [InvoiceController::class, 'delete']);
    Route::post('updateinvoice/{invoice}', [InvoiceController::class, 'update']);

    Route::post('addpayment', [PaymentController::class, 'save']);
    Route::post('updatepayment', [PaymentController::class, 'update']);

    Route::post('clients', [ClientController::class, 'index']);
     Route::post('all/clients', [ClientController::class, 'allClients']);
    Route::get('client/{client}', [ClientController::class, 'show']);
    Route::post('addclient', [ClientController::class, 'save']);
    Route::post('deleteclient/{client}', [ClientController::class, 'delete']);
    Route::post('updateclient/{client}', [ClientController::class, 'update']);
    Route::post('cashiers', [ClientController::class, 'cashiers']);

    Route::post('clients/payments', [ClientController::class, 'clientPayments']);

    Route::post('creditors', [CreditorController::class, 'index']);
    Route::get('creditor/{id}', [CreditorController::class, 'show']);

    Route::post('add/creditor/payment', [CreditorPaymentController::class, 'save']);
    Route::post('update/creditor/payment', [CreditorPaymentController::class, 'update']);

    Route::post('add/expense', [ExpenseController::class, 'save']);
    Route::post('expenses', [ExpenseController::class, 'index']);
    Route::post('update/expense', [ExpenseController::class, 'update']);

    }
  );


  Route::group(['middleware'=>['jwt.auth']],
function(){
  Route::post('dashboards', [DashboardController::class, 'index']);
  Route::post('invoices2', [InvoiceController::class, 'index2']);
  Route::get('invoice2/{invoice}', [InvoiceController::class, 'show2']);
  Route::get('last_invoice', [InvoiceController::class, 'lastInvoice']);
  Route::post('addinvoice', [InvoiceController::class, 'save']);
  Route::post('updateinvoice/{invoice}', [InvoiceController::class, 'update']);

  Route::post('addpayment', [PaymentController::class, 'save']);
  Route::post('updatepayment', [PaymentController::class, 'update']);

  Route::post('branch_stocks', [StockController::class, 'branchStocks']);

  Route::post('clients', [ClientController::class, 'index']);
  Route::get('client/{client}', [ClientController::class, 'show']);
  Route::post('addclient', [ClientController::class, 'save']);
  Route::post('deleteclient/{client}', [ClientController::class, 'delete']);
  Route::post('updateclient/{client}', [ClientController::class, 'update']);

  Route::post('cashiers', [ClientController::class, 'cashiers']);

  Route::post('branch_stocks', [StockController::class, 'branchStocks']);
  Route::post('pos_order', [PosController::class, 'multPosOrder']);
  Route::post('pos_sales2', [PosController::class, 'getPosSales2']);
  Route::post('transaction_details2', [PosController::class, 'getTransactionDetails2']);
  Route::get('company', [CompanySettingsController::class, 'index']);


   
    }
  );



