<?php

namespace App\Http\Controllers;

use App\Exports\CustomerExport;
use App\Exports\ProductExport;
use App\Model\Brand;
use App\Model\Category;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrderTransaction;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\User;
use App\Model\product;
use App\Model\RefundRequest;
use App\Model\RefundTransaction;
use App\Model\Seller;
use App\Model\ShippingAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\CPU\OrderManager;

class TallyintegrationController extends Controller
{
    public function customer_export(Request $request)
    {
        $finalized = [];
        $users = User::select(DB::raw('concat("C-",id) as Id'), 'id', 'f_name as MailingName', 'l_name as LastName', 'email as Email', 'phone as Phone', 'action as Action', 'full_address', 'state', 'country', 'tally_unique_id as TallyId', 'zip')->get()->map(function ($data) {
            $data->FirstName     = 'Web' . $data->id . '-' . $data->MailingName;
            $data->Address       = $data->full_address;
            $data->State         = $data->state;
            $data->Country       = $data->country;
            $data->PinCode       = !empty($data->zip) ? $data->zip : null;
            $data->Role          = "Customer";
            unset($data->full_address);
            unset($data->state);
            unset($data->country);
            unset($data->tally_unique_id);
            unset($data->zip);
            unset($data->id);
            return $data;
        });


        // foreach($users as $key => $val) {
        //     $user_action = user::find(ltrim($val->Id,'C-'));
        //     $user_action->action = 'exported';
        //     $user_action->save();
        // }
        $finalized['Users'] = $users->toArray();
        $finalized = json_encode($finalized, JSON_PRETTY_PRINT);
        File::put(storage_path('app/tally/customers/customer.json'), $finalized);
        // File::put('/home2/pmserhe9/public_html/integration/Web/customer.json',$finalized);
        // chmod('/home2/pmserhe9/public_html/integration/Web/customer.json',0777);
    }

    public function product_export(Request $request)
    {
        $select_arr = [
            'id',
            'name',
            'brand_id',
            'category_id',
            'unit',
            'current_stock',
            'sub_category_id',
            'unit_price',
            'purchase_price',
            'tax',
            'tax_model',
            'discount',
            'shipping_cost',
            'minimum_order_qty',
            'hsn_code',
            'action',
            'tally_unique_id'
        ];
        $products = product::select($select_arr)->where(['added_by' => 'admin'])->with(['category', 'sub_category', 'brand'])->get();

        $finalized = [];
        foreach ($products as $key => $value) {
            $obj = new \stdClass;
            $obj->StkId             = $value->id;
            $obj->TallyStockID      = $value->tally_unique_id;
            $obj->StkName           = $value->name;
            $obj->StkGroup          = !is_null($value->category) ? $value->category->name : null;
            $obj->StkCategory       = !is_null($value->sub_category) ? $value->sub_category->name : null;
            $obj->StkBrand          = !is_null($value->brand) ? $value->brand->name : null;
            $obj->UOM               = $value->unit;
            $obj->STKClosingBal     = $value->current_stock;
            $obj->StkUnitPrice      = $value->unit_price;
            $obj->StkPurchasePrice  = $value->purchase_price;
            $obj->StkTax            = $value->tax;
            $obj->StkTaxModel       = $value->tax_model;
            $obj->StkDiscount       = $value->discount;
            $obj->StkShippingCost   = $value->shipping_cost;
            $obj->StkMinOrdQty      = $value->minimum_order_qty;
            $obj->StkHsnCode        = $value->hsn_code;
            $obj->StkAction         = $value->action;
            $it_arr[]               = $obj;
            $finalized['items']     = $it_arr;
        }

        $finalized = json_encode($finalized, JSON_PRETTY_PRINT);
        File::put(storage_path('app/tally/customers/product.json'),$finalized);
        // File::put('/home2/pmserhe9/public_html/integration/Web/product.json', $finalized);
        // chmod('/home2/pmserhe9/public_html/integration/Web/product.json', 0777);
        // foreach($products as $key => $val) {
        //     $pro_action = product::find($val->id);
        //     $pro_action->action = 'exported';
        //     $pro_action->save();
        // }
    }

    public function invoice_export(Request $request)
    {
        $finalized = [];
        $user_invoice = Order::select('id', 'customer_id', 'created_at', 'order_amount', 'shipping_cost', 'action', 'sales_tally_unique_id', 'order_status', 'billing_address_data', 'shipping_address_data', 'payment_status')->with(['customer', 'details'])->get()->map(function ($data, $key) {
            $refund_check           = RefundTransaction::where('order_id', $data->id)->first();
            $billing                = json_decode($data->billing_address_data);
            $shipping               = json_decode($data->shipping_address_data);
            $data->SalVchNo         = '#' . $data->id;
            $data->SalesDate        = date('d-m-Y', strtotime($data->created_at));
            $data->SalesTime        = date('H:i:s', strtotime($data->created_at));
            $data->TotalAmount      = sprintf('%.2f', $data->order_amount);
            $data->SalesTallyID     = $data->sales_tally_unique_id;
            $data->SalesWebID       = $data->id;

            $data->Cancel           = ($data->order_status == 'canceled' || $data->order_status == 'failed' ||  $data->order_status == 'returned') ? "CANCELLED" : null;

            $data->Refund           = !is_null($refund_check) ? "REFUNDED" : null;
            $data->CustomerWebID    = !is_null($data->customer) ? $data->customer->id : '';
            $data->CustomerTallyID  = !is_null($data->customer) ? $data->customer->tally_unique_id : '';
            $data->CustomerName     = !is_null($data->customer) ? 'Web' . $data->customer->id . '-' . 
            $data->customer->f_name : null;
            $data->MailingName      = !is_null($data->customer) ? $data->customer->f_name : null;

            $data->BillingAddress   = !is_null($billing) ? $billing->address : null;

            $data->BillingPhoneNo   = !is_null($billing) ? $billing->phone : null;

            $data->BillingState     = !is_null($billing) ? (isset($billingstate) ? $billing->state : null) : null;

            $data->BillingCountry   = !is_null($billing) ? $billing->country : null;

            $data->BillingPinCode   = !is_null($billing) ? $billing->zip : null;

            $data->ConsigneeName    = !is_null($shipping) ? $shipping->contact_person_name : null;

            $data->ShippingAddress  = !is_null($shipping) ? $shipping->address : null;

            $data->ShippingPhoneNo  = !is_null($shipping) ? $shipping->phone : null;

            $data->ShippingState    = !is_null($shipping) ? (isset($shipping->state) ? $shipping->state : null) : null;

            $data->ShippingCountry  = !is_null($shipping) ? $shipping->country : null;

            $data->ShippingPinCode  = !is_null($shipping) ? $shipping->zip : null;

            $data->Action           = $data->action;

            $data->PaymentStatus    = $data->payment_status;

            $arr = [];
            $item_details = [];
            $ledger_details = [];
            $ledg = [];
            $ext = [];
            $cgst = 0;
            $sgst = 0;
            $discount = 0;
            foreach ($data->details as $key => $value) {
                if(!is_null($refund_check)) {
                    if($refund_check->order_details_id == $value->id) {
                        $product_det            = json_decode($value->product_details);
                        $arr['InvStockWebID']   = !is_null($product_det) ? $product_det->id : '';
                        $arr['InvStockTallyID'] = isset($value->tally_unique_id) ? $value->tally_unique_id : "NULL";
                        $arr['ItemName']        = !is_null($product_det) ? $product_det->name : '';
                        $arr['Tax']             = !is_null($product_det) ? $product_det->tax : '';
                        $arr['Quantity']        = $value->qty;
                        $arr['Rate']            = !is_null($product_det) ? $product_det->unit_price : '';
                        $arr['UOM']             = !is_null($product_det) ? $product_det->unit : '';
                        $arr['Amount']          = $value->qty * $value->price;
        
                        array_push($item_details, $arr);
                        $cgst                += $value->tax / 2;
                        $sgst                += $value->tax / 2;
                        $discount            += $value->discount;
                    }
                } else {
                    $product_det            = json_decode($value->product_details);
                    $arr['InvStockWebID']   = !is_null($product_det) ? $product_det->id : '';
                    $arr['InvStockTallyID'] = isset($value->tally_unique_id) ? $value->tally_unique_id : "NULL";
                    $arr['ItemName']        = !is_null($product_det) ? $product_det->name : '';
                    $arr['Tax']             = !is_null($product_det) ? $product_det->tax : '';
                    $arr['Quantity']        = $value->qty;
                    $arr['Rate']            = !is_null($product_det) ? $product_det->unit_price : '';
                    $arr['UOM']             = !is_null($product_det) ? $product_det->unit : '';
                    $arr['Amount']          = $value->qty * $value->price;
    
                    array_push($item_details, $arr);
                    $cgst                += $value->tax / 2;
                    $sgst                += $value->tax / 2;
                    $discount            += $value->discount;
                }
            }

            $billing_state = isset(json_decode($data->billing_address_data)->state) ? json_decode($data->billing_address_data)->state : '';
            if(!is_null($billing_state) && $billing_state != '' && $billing_state != "Tamil Nadu") {
                $ext['IGST']         = $cgst + $sgst;
            } else {                
                $ext['CGST']         = $cgst;
                $ext['SGST']         = $sgst;
            }

            $ext['DISCOUNT']     = $discount;
            $ext['ShippingCost'] = $data->shipping_cost;

            foreach ($ext as $key => $value) {
                $ledg['Ledgername'] = $key;
                $ledg['Amount']     =  sprintf('%.2f', $value);
                array_push($ledger_details, $ledg);
            }
            $data['Itemdetails'] = $item_details;
            $data['Ledgerdetails'] = $ledger_details;
            unset($data->id);
            unset($data->customer_id);
            unset($data->created_at);
            unset($data->order_amount);
            unset($data->order_status);
            unset($data->action);
            unset($data->sales_tally_unique_id);
            unset($data->payment_status);
            unset($data->customer);
            unset($data->details);
            unset($data->shipping_cost);
            unset($data->billing_address_data);
            unset($data->shipping_address_data);
            return $data;
        });
        $finalized['File']         = 'Invoice';
        $finalized['SalesVoucher'] = $user_invoice;
        $finalized = json_encode($finalized, JSON_PRETTY_PRINT);
        // foreach($user_invoice as $key => $val) {
        //     $invoice_action         = Order::find(ltrim($val->SalVchNo,'#'));
        //     $invoice_action->action = 'exported';
        //     $invoice_action->save();
        // }
        File::put(storage_path('app/tally/customers/invoice.json'), $finalized);
    }

    public function receipt_export(Request $request)
    {
        $finalized = [];
        $transaction = OrderTransaction::select('id as ReceiptWebId', 'tally_receipt_id as ReceiptTallyId', 'order_id as InvoiceNo', 'created_at', 'payment_method', 'order_amount', 'action as Action', 'customer_id')->with('customer')->get()->map(function ($data) {
            $data->ReceiptDate  = date('d-m-Y', strtotime($data->created_at));
            $data->CustomerName = !is_null($data->customer) ? 'Web' . $data->customer->id . '-' . 
            $data->customer->f_name : null;
            $data->MailingName  = !is_null($data->customer) ? $data->customer->f_name : null;
            $data->PaymentType  = $data->payment_method;
            $data->TotalAmount  = sprintf('%.2f', $data->order_amount);
            unset($data->created_at);
            unset($data->payment_method);
            unset($data->order_amount);
            unset($data->customer_id);
            unset($data->customer);
            return $data;
        });

        $finalized['recipts']     = $transaction;
        $finalized                = json_encode($finalized, JSON_PRETTY_PRINT);
        foreach ($transaction as $key => $value) {
            $or_transac = OrderTransaction::find($value->ReceiptWebId);
            $or_transac->action = "exported";
            $or_transac->save();
        }
        File::put(storage_path('app/tally/customers/receipt.json'), $finalized);
    }

    public function product_import(Request $request)
    {
        $mode = chmod('/home2/pmserhe9/public_html/integration/Tally/StockExport.JSON', 0777);
        $file_con = file_get_contents('/home2/pmserhe9/public_html/integration/Tally/StockExport.JSON');
        // $file_con = file_get_contents(storage_path('app/tally/customers/StockExport.json'));
        $products = json_decode($file_con);
        // ini_set('max_execution_time', '300');
        if (isset($products->ENVELOPE->Item)) {
            foreach ($products->ENVELOPE->Item as $key => $value) {
                $category = [];
                $category_ids = ['id' => 'null', 'position' => 1];
                $sub_category_ids = ['id' => 'null', 'position' => 1];

                if ($value->StkGroup != "NULL") {
                    $category_id = Category::where('name', $value->StkGroup)->first();
                    if (is_null($category_id)) {
                        $category_id = Category::create(['name' => $value->StkGroup, 'slug' => $value->StkGroup]);
                    }
                    $category_ids = ['id' => $category_id->id, 'position' => 1];
                }

                $sub_category_id = null;
                if ($value->StkCategory != "NULL") {
                    $sub_category_id = Category::where('name', $value->StkCategory)->first();
                    if (is_null($sub_category_id)) {
                        $sub_category_id = Category::create(['name' => $value->StkCategory, 'slug' => $value->StkCategory, 'parent_id' => $category_id->id]);
                    }
                    $sub_category_ids = ['id' => $sub_category_id->id, 'position' => 2];
                }
                array_push($category, $category_ids);
                array_push($category, $sub_category_ids);

                if ($value->StkBrand != "NULL") {
                    $brand_id  = Brand::where('name', $value->StkBrand)->first();
                    if (is_null($brand_id)) {
                        $brand_id = Brand::create(['name' => $value->StkBrand, 'image' => '2020-12-30-5fec164a492ff.png', 'status' => 1]);
                    }
                }

                $product = new Product;
                if ($value->StkAction != "NULL" && $value->StkAction == "update" && !empty($value->StkId)) {
                    $product = Product::find($value->StkId);
                }
                $product->added_by            = 'admin';
                $product->user_id             =  1;
                $product->name                = $value->StkName;
                $product->slug                = Str::slug($value->StkName, '-') . '-' . Str::random(6);
                $product->brand_id            = isset($brand_id) ? $brand_id->id : 1;
                $product->category_id         = isset($category_id) ? $category_id->id : null;
                $product->sub_category_id     = !is_null($sub_category_id) ? $sub_category_id->id : null;
                $product->category_ids        = json_encode($category);
                $product->unit                = $value->UOM;
                $product->current_stock       = $value->STKClosingBal;
                $product->variation           = json_encode([]);
                $product->colors              = json_encode([]);
                $product->choice_options      = json_encode([]);
                $product->attributes          = json_encode([]);
                $product->images              = json_encode(['def.png']);
                $product->unit_price          = $value->STKunitprice;
                $product->purchase_price      = $value->StkPurchasePrice;
                $product->tax                 = $value->StkTax;
                $product->tax_model           = $value->StkTaxmodel;
                $product->discount            = $value->StkDiscount;
                $product->shipping_cost       = $value->StkShippingCost;
                $product->minimum_order_qty   = 1;
                $product->action              = 'imported';
                if ($value->StkAction == 'Create') {
                    $product->is_tally       = 1;
                }
                $product->hsn_code            = $value->StkHsnCode;
                $product->tally_unique_id     = ($value->TallyStockID != "NULL") ? $value->TallyStockID : null;
                $product->save();

                // stock closing update
                if ($value->StkAction != "NULL" && $value->StkAction == "Exported" && !empty($value->StkId)) {
                    $stock = Product::find($value->StkId);
                    $stock->current_stock = $value->STKClosingBal;
                    $stock->save();
                }
            }
        }
        //unlink('/home2/pmserhe9/public_html/integration/Tally/StockExport.JSON');
        unlink(storage_path('app/tally/customers/StockExport.json'));
    }

    public function customer_import(Request $request)
    {
        // $mode = chmod('/home2/pmserhe9/public_html/integration/Tally/LedgerExport.JSON',0777);
        // $file_con = file_get_contents('/home2/pmserhe9/public_html/integration/Tally/LedgerExport.JSON');
        $file_con = file_get_contents(storage_path('app/tally/customers/LedgerExport.json'));
        $data     = json_decode($file_con);
        if (isset($data->ENVELOPE->Users)) {
            foreach ($data->ENVELOPE->Users as $key => $value) {
                if ($value->Role == 'Customer') {
                    $users_det = new User;
                    if ($value->Action == 'update' && $value->Action != 'NULL' && !empty($value->ID)) {
                        $users_det = User::find(ltrim($value->ID, 'C-'));
                    }
                    $users_det->name            = ($value->FirstName != "NULL") ? $value->FirstName : null;
                    $users_det->f_name          = ($value->FirstName != "NULL") ? $value->FirstName : null;
                    $users_det->l_name          = ($value->LastName != "NULL") ? $value->LastName : null;
                    $users_det->email           = ($value->Email != "NULL") ? $value->Email : null;
                    $users_det->phone           = ($value->Phone != "NULL") ? $value->Phone : null;
                    $users_det->full_address    = ($value->Address != "NULL") ? $value->Address : null;
                    $users_det->action          = 'imported';
                    $users_det->state           = ($value->State != "NULL") ? $value->State : null;
                    $users_det->country         = ($value->Country != "NULL") ? $value->Country : null;
                    $users_det->tally_unique_id = $value->TallyID;
                    $users_det->zip             = ($value->PinCode != "NULL") ? $value->PinCode : null;
                    if ($value->Action == 'Create') {
                        $users_det->is_tally    = 1;
                    }
                    $users_det->save();
                }
            }
        }
        //unlink('/home2/pmserhe9/public_html/integration/Tally/LedgerExport.JSON');
        unlink(storage_path('app/tally/customers/LedgerExport.json'));
    }

    public function invoice_import(Request $request)
    {
        // $mode = chmod('/home2/pmserhe9/public_html/integration/Tally/SalesTransactionExport.JSON',0777);
        // $file_con = file_get_contents('/home2/pmserhe9/public_html/integration/Tally/SalesTransactionExport.JSON');
        // ini_set('max_execution_time', '200');
        // $file_con = file_get_contents(storage_path('app/tally/customers/SalesTransactionExport.json'));
        $file_con = file_get_contents(storage_path('app/tally/customers/test.json'));
        $data     = json_decode($file_con);
        if (isset($data->ENVELOPE->SalesVoucher)) {
            foreach ($data->ENVELOPE->SalesVoucher as $key => $value) {
                $order      = new Order;
                if (isset($value->Action) && $value->Action == "update" || $value->Action == "NULL" ) {
                    if ($value->WebID != "NULL") {
                        $order = Order::find($value->WebID);
                    } elseif ($value->SalesTallyID != "NULL") {
                        $order = Order::where('sales_tally_unique_id', $value->SalesTallyID)->first();
                        $order_det  = OrderDetail::where('order_id', $value->SalesTallyID)->first();
                    }
                }

                $sal_date_time = $value->SalesDate;
                if ($value->SalTime) {
                    $sal_date_time = $value->SalesDate . ' ' . $value->SalTime;
                }

                if ($value->CustomerWebID != "NULL") {
                    $user = User::find($value->CustomerWebID);
                } elseif ($value->CustomerTallyID != "NULL") {
                    $user = User::where('tally_unique_id', $value->CustomerWebID)->first();
                } else {
                    $user = User::where('f_name', $value->CustomerName)->first();
                }

                $order->id                      = $value->SalVchNo;
                $order->sales_tally_unique_id   = $value->SalesTallyID;
                $order->customer_id             = isset($user->id) ? $user->id : 1;
                $order->seller_is               = 'admin';
                $order->customer_type           = 'customer';
                $order->payment_status          = 'unpaid';
                $order->order_status            = ($value->Cancel == "CANCELLED") ? 'canceled' : 'delivered';
                $order->payment_method          = 'cash_on_delivery';
                $order->order_amount            = $value->TotalAmount;
                $order->created_at              = date('Y-m-d H:i:s', strtotime($sal_date_time));
                $order->updated_at              = date('Y-m-d H:i:s', strtotime($sal_date_time));
                $order->order_type              = 'Tally';
                $order->action                  = 'imported';

                if (isset($value->Action) && ($value->Action == "Create")) {
                    $order->is_tally            = 1;
                }

                //billing address insert 
                $bill_addr                      = new ShippingAddress;
                $bill_addr->customer_id         = isset($user->id) ? $user->id : $user->id; //
                $bill_addr->contact_person_name = $value->CustomerName;
                $bill_addr->address_type        = 'others';
                $bill_addr->address             = ($value->BillingAddress != 'NULL') ? $value->BillingAddress : null;
                $bill_addr->zip                 = ($value->BillingPinCode != 'NULL') ? $value->BillingPinCode : null;
                $bill_addr->phone               = ($value->BillingPhoneNo != 'NULL') ? $value->BillingPhoneNo : null;
                $bill_addr->country             = ($value->BillingCountry != 'NULL') ? $value->BillingCountry : null;
                $bill_addr->is_billing          = 1;
                $bill_addr->save();

                //shipping address insert 
                $ship_addr                      = new ShippingAddress;
                $ship_addr->customer_id         = isset($user->id) ? $user->id : $user->id; //
                $ship_addr->contact_person_name = ($value->ConsigneeName != 'NULL') ? $value->ConsigneeName : null;
                $ship_addr->address_type        = 'others';
                $ship_addr->address             = $value->ShippingAddress;
                $ship_addr->zip                 = isset($value->ShippingPinCode) ? $value->ShippingPinCode : null;
                $ship_addr->phone               = isset($value->ShippingPhoneNo) ? $value->ShippingPhoneNo : null;
                $ship_addr->country             = $value->ShippingCountry;
                $ship_addr->is_billing          = 0;
                $ship_addr->save();


                $order->shipping_address        = $ship_addr->id;
                $order->shipping_address_data   = json_encode($ship_addr);
                $order->billing_address         = $bill_addr->id;
                $order->billing_address_data    = json_encode($bill_addr);


                if (isset($value->Ledgerdetails)) {
                    foreach ($value->Ledgerdetails as $lkey => $lvalue) {
                        if ($lvalue->Ledgername == "DISCOUNT A/C") {
                            $order->discount_amount         = $lvalue->Amount;
                        }
                        if ($lvalue->Ledgername == "ROUNDED OFF") {
                            $order->tally_roundoff_amount   = $lvalue->Amount;
                        }
                        if ($lvalue->Ledgername == "ShippingCost") {
                            $order->shipping_cost  = $lvalue->Amount;
                        }
                    }
                }


                if (isset($value->Itemdetails)) {
                    foreach ($value->Itemdetails as $k => $val) {
                        if ($val->InvStockWebID != 0) {
                            $product = product::find($val->InvStockWebID);
                        } elseif ($val->InvStockTallyID != 0) {
                            $product = product::where('tally_unique_id', $val->InvStockTallyID)->first();
                        } else {
                            $product = product::where('name', $val->ItemName)->first();
                        }

                        if ($value->Refund == "REFUNDED") {
                            $order_det  = OrderDetail::where('order_id', $value->SalVchNo)->where('product_id', $product->id)->first();
                            $order_det->refund_request = 1;
                            $order_det->save();
                            $this->refund($order_det);
                        } else {
                            $order_det  = new OrderDetail;
                            if (isset($value->Action) && $value->Action == "update") {
                                $order_det  = OrderDetail::where('order_id', $value->SalVchNo)->where('product_id', $product->id)->first();
                            }
                            $order_det->order_id         = $value->SalVchNo;
                            $order_det->product_id       = $product->id; //
                            $order_det->seller_id       = 1; 
                            $order_det->product_details  = json_encode($product);
                            $order_det->qty              = $val->Quantity;
                            $order_det->price            = $val->Rate;
                            $order_det->tax              = isset($val->Tax) ? ($val->Quantity * $val->Rate) * ($val->Tax / 100) : null;
                            $order_det->tax_model        = 'exclude';
                            $order_det->delivery_status  = ($value->Cancel == "CANCELLED") ? 'canceled' : 'delivered';
                            $order_det->payment_status   = 'unpaid';
                            $order_det->discount         = $val->DisValue;
                            $order_det->discount_type    = ($val->DisValue > 0) ? 'discount_on_product' : null;
                            $order_det->variation        = json_encode([]);
                            $order_det->save();

                            Product::where(['id' => $product->id])->update([
                                'current_stock' => $product->current_stock - $val->Quantity
                            ]);
                        }
                        $this->stock_update($value->Refund, $value->SalVchNo, $product->id);
                    }
                }

                $order->save();
            }
        }
        //unlink('/home2/pmserhe9/public_html/integration/Tally/SalesTransactionExport.JSON');
        unlink(storage_path('app/tally/customers/SalesTransactionExport.json'));
    }


    public function stock_update($status, $order_id, $product_id)
    {
        if ($status == 'REFUNDED' || $status == 'CANCELLED') {
            $order_details = OrderDetail::where('order_id', $order_id)->where('product_id', $product_id)->first();
            if ($order_details) {
                if ($order_details->is_stock_decreased == 1) {
                    $product = Product::find($order_details->product_id);
                    $product->current_stock = $product->current_stock + $order_details->qty;
                    $product->save();

                    $order_details->is_stock_decreased = 0;
                    $order_details->save();
                }
            }
        }
    }

    public function refund($order_det)
    {
        $refund_request                   = new RefundRequest;
        $refund_request->order_details_id = $order_det->id;
        $refund_request->customer_id      = $order_det->order->customer_id;
        $refund_request->status           = 'refunded';
        $refund_request->amount           = ($order_det->qty * $order_det->price) + $order_det->tax - $order_det->discount;
        $refund_request->product_id       = $order_det->product_id;
        $refund_request->order_id         = $order_det->order_id;
        $refund_request->refund_reason    = 'refund';
        $refund_request->change_by        = 'tally_admin';
        $refund_request->save();

        $refund_add                         = new RefundTransaction;
        $refund_add->order_id               = $order_det->order_id;
        $refund_add->payment_for            = "Refund Request";
        $refund_add->payer_id               = 1;
        $refund_add->payment_receiver_id    = $order_det->order->customer_id;
        $refund_add->paid_by                = 'admin';
        $refund_add->paid_to                = 'customer';
        $refund_add->payment_method         = 'cash';
        $refund_add->payment_status         = 'paid';
        $refund_add->amount                 = $refund_request->amount;
        $refund_add->transaction_type       = 'Refund';
        $refund_add->order_details_id       = $order_det->id;
        $refund_add->refund_id              = $refund_request->id;
        $refund_add->save();
    }

    public function receipt_import(Request $request)
    {
        // $mode = chmod('/home2/pmserhe9/public_html/integration/Tally/TallyReceipt.JSON',0777);
        // $file_con = file_get_contents('/home2/pmserhe9/public_html/integration/Tally/TallyReceipt.JSON');
        // ini_set('max_execution_time', '200');
        $file_con = file_get_contents(storage_path('app/tally/customers/TallyReceipt.json'));
        $data     = json_decode($file_con);

        if (isset($data->ENVELOPE->Receipts)) {
            foreach ($data->ENVELOPE->Receipts as $key => $value) {
                $order = Order::find($value->InvoiceNo);    
                if(is_null($order)) {
                    $cust_name = $value->CustomerName;
                    if(substr($value->CustomerName,0,3) == 'Web') {
                        $cust_name = explode('-',$value->CustomerName)[1];
                    }
                    
                    $user = User::where('f_name', $cust_name)->first();
                    $order = Order::where('customer_id',$user->id)->where('order_amount',$value->TotalAmount)->first();    

                    if(is_null($order)) {
                        $user = User::where('f_name', $value->MailingName)->first();
                        $order = Order::where('customer_id',$user->id)->where('order_amount',$value->TotalAmount)->first();  
                    }

                }
                
                $order_summary = OrderManager::order_summary($order);

                $order_transac = new OrderTransaction;
                if($value->Action == "update") {
                    if($value->ReceiptWebID != "NULL") {
                        $order_transac = OrderTransaction::find($value->ReceiptWebID);
                    } elseif ($value->ReceiptTallyID != "NULL") {
                        $order_transac = OrderTransaction::where('tally_receipt_id',$value->ReceiptTallyID)->first();
                    } else if ($value->InvoiceNo != "NULL") {
                        $order_transac = OrderTransaction::where('order_id',$value->InvoiceNo)->first();
                    }
                }
                
                $order_transac->customer_id     = $order->customer_id;
                $order_transac->seller_id       = 1;
                $order_transac->seller_is       = 'admin';
                $order_transac->order_id        = $order->id;
                $order_transac->order_amount    = $value->TotalAmount;
                $order_transac->received_by     = 'admin';
                $order_transac->status          = 'disburse';
                $order_transac->delivery_charge = $order->shipping_cost;
                $order_transac->tax             = $order_summary['total_tax'];
                $order_transac->delivered_by    = 'admin';
                $order_transac->payment_method  = $value->PaymentType;
                $order_transac->created_at      = date('Y-m-d H:i:s',strtotime($value->ReceiptDate));
                $order_transac->updated_at      = date('Y-m-d H:i:s',strtotime($value->ReceiptDate));
                $order_transac->action          = 'imported';
                $order_transac->tally_receipt_id= $value->ReceiptTallyID;
                if($value->Action == "Create" || $value->Action == "Exported") {
                    $order_transac->is_tally = 1;
                }
                $order_transac->save();
                
                //order and order details paid status change
                $order_det = Orderdetail::where('order_id',$order->id)->get();
                foreach($order_det as $key => $val) {
                    $val->payment_status = 'paid';
                    $val->save();
                }
                    
                $order->payment_status = 'paid';
                $order->save();
            }
        }
        //unlink('/home2/pmserhe9/public_html/integration/Tally/TallyReceipt.JSON');
        unlink(storage_path('app/tally/customers/TallyReceipt.json'));
    }
}
