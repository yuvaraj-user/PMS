<?php

namespace App\Http\Controllers;

use App\Exports\CustomerExport;
use App\Exports\ProductExport;
use App\Model\Brand;
use App\Model\Category;
use App\Model\Order;
use App\Model\OrderTransaction;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\User;
use App\Model\product;
use App\Model\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TallyintegrationController extends Controller
{
    public function customer_export(Request $request)
    {
        $finalized = [];
        $users = User::select('id as CusId','f_name as CusFirstName','l_name as CusLastName','email as CusEmail','phone as CusPhone')->get()->map(function($data){
            $data->CusId = "C-".$data->CusId;
            $data->role = "Customer";
            return $data;
        });

        $sellers = Seller::select('id as SelId','f_name as SelFirstName','l_name as SelLastName','email as SelEmail','phone as SelPhone')->get()->map(function($data){
            $data->SelId = "S-".$data->SelId;
            $data->role = "Seller";
            return $data;
        });

        $finalized['Users'] = array_merge($users->toArray(),$sellers->toArray());
        $finalized = json_encode($finalized,JSON_PRETTY_PRINT);
        File::put(storage_path('app/tally/customers/customer.json'),$finalized);
        // Excel::store(new CustomerExport, 'customerlist.xlsx', 'customer_uploads');
        // return response()->download(storage_path('app/tally/customers/customerlist.xlsx'));
    }

    // public function seller_export(Request $request)
    // {
    //     $finalized = [];
    //     $sellers = Seller::select('id as SelId','f_name as SelFirstName','l_name as SelLastName','email as SelEmail','phone as SelPhone')->get()->map(function($data){
    //         $data->role = "Seller";
    //         return $data;
    //     });
    //     $finalized['Seller'] = $sellers;
    //     $finalized = json_encode($finalized,JSON_PRETTY_PRINT);
    //     File::put(storage_path('app/tally/customers/sellers.json'),$finalized);
    // }

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
                'action'
        ];
         $products = product::select($select_arr)->where(['added_by' => 'admin'])->with(['category','sub_category','brand'])->get();

        $finalized = [];
        foreach($products as $key => $value) {
            $obj = new \stdClass;
            $obj->StkId             = $value->id;
            $obj->StkName           = $value->name;
            $obj->StkGroup          = !is_null($value->category) ? $value->category->name : null;
            $obj->StkCategory       = !is_null($value->sub_category) ? $value->sub_category->name : null;
            $obj->StkBrand          = !is_null($value->brand) ? $value->brand->name : null;
            $obj->UOM               = $value->unit;
            $obj->StkOpeningBal     = $value->current_stock;
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
        $finalized = stripslashes($finalized);
        File::put(storage_path('app/tally/customers/product.json'),$finalized);

        // foreach($products as $key => $val) {
        //     $pro_action = product::find($val->id);
        //     $pro_action->action = 'exported';
        //     $pro_action->save();
        // }
    }

    public function order_transactional_export(Request $request)
    {
        $finalized = [];
        $transaction = OrderTransaction::with(['refund_transaction','seller.shop', 'customer', 'order.delivery_man', 'order'])
        ->with(['order_details'=> function ($query) {
            $query->selectRaw("*, sum(qty*price) as order_details_sum_price, sum(discount) as order_details_sum_discount")
                ->groupBy('order_id');
        }])->get();

        $finalized['recipt']     = $transaction;
        $finalized               = json_encode($finalized,JSON_PRETTY_PRINT);
        File::put(storage_path('app/tally/customers/receipt.json'),$finalized);
    }

    public function order_user_invoice_export(Request $request)
    {
        $finalized = [];
        $user_invoice = Order::with(['refund_transaction','seller','shipping','customer'])->get();
        $finalized['invoice'] = $user_invoice;
        $finalized = json_encode($finalized,JSON_PRETTY_PRINT);
        File::put(storage_path('app/tally/customers/invoice.json'),$finalized);
    }

    public function product_import(Request $request) 
    {
        $file_con = file_get_contents(storage_path('app/tally/customers/product - Copy.json'));
        $products = json_decode($file_con);
        foreach ($products->items as $key => $value) {
            $category = [];
            $category_ids = ['id' => 'null','position' => 1];
            $sub_category_ids = ['id' => 'null','position' => 1];

            if(!is_null($value->StkGroup)) {
                $category_id = Category::where('name',$value->StkGroup)->first();
                if(is_null($category_id)) {
                    $category_id = Category::create(['name' => $value->StkGroup,'slug' => $value->StkGroup]);
                }
                $category_ids = ['id' => $category_id->id,'position' => 1];
            }
            
            if(!is_null($value->StkCategory)) {
                $sub_category_id = Category::where('name',$value->StkCategory)->first();
                if(is_null($sub_category_id)) {
                    $sub_category_id = Category::create(['name' => $value->StkCategory,'slug' => $value->StkCategory,'parent_id' => $category_id->id]);
                }
                $sub_category_ids = ['id' => $sub_category_id->id,'position' => 2];
            }
            array_push($category,$category_ids);
            array_push($category,$sub_category_ids);

            if(!is_null($value->StkBrand)) {
                $brand_id  = Brand::where('name',$value->StkBrand)->first();
                if(is_null($brand_id)) {
                    $brand_id = Brand::create(['name' => $value->StkBrand,'image' => '2020-12-30-5fec164a492ff.png','status' => 1]);
                }

            }

            $product = new Product;
            $product->added_by            = 'admin';
            $product->user_id             =  1;
            $product->name                = $value->StkName;
            $product->slug                = $value->StkName . '-' . Str::random(6);
            $product->brand_id            = isset($brand_id) ? $brand_id->id : null;
            $product->category_id         = isset($category_id) ? $category_id->id : null;
            $product->sub_category_id     = isset($sub_category_id) ? $sub_category_id->id : null;
            $product->category_ids        = json_encode($category);
            $product->unit                = $value->UOM;
            $product->current_stock       = $value->StkOpeningBal;
            $product->variation           = json_encode([]);
            $product->colors              = json_encode([]);
            $product->choice_options      = json_encode([]);
            $product->attributes          = json_encode([]);
            $product->images              = json_encode(['def.png']);
            $product->unit_price          = $value->StkUnitPrice;
            $product->purchase_price      = $value->StkPurchasePrice;
            $product->tax                 = $value->StkTax;
            $product->tax_model           = $value->StkTaxModel;
            $product->discount            = $value->StkDiscount;
            $product->shipping_cost       = $value->StkShippingCost;
            $product->minimum_order_qty   = $value->StkMinOrdQty;
            $product->action              = 'imported';
            $product->is_tally            = 1;
            $product->hsn_code            = $value->StkHsnCode;
            $product->save();

        }
    }

    public function customer_import(Request $request) {

    }



}
