<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\Model\Product;


class ProductExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $select_arr = [
            'name',
            'product_type',
            'category_id',
            'sub_category_id',
            'sub_sub_category_id',
            'brand_id',
            'unit',
            'min_qty',
            'refundable',
            'video_url',
            'unit_price',
            'purchase_price',
            'tax',
            'discount',
            'discount_type',
            'current_stock',
            'details',
            'thumbnail',
            'status',
        ];
        return product::select($select_arr)->where(['added_by' => 'admin'])->with(['category','brand'])->get()->map(function($data) {
            $data->category_id = $data->category->name;
            $data->brand_id    = $data->brand->name;
            dd($data);
            return $data;
        });
        
    }
}
