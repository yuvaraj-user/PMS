@extends('layouts.back-end.app')

@section('title', \App\CPU\translate('wishlist_list'))

@push('css_or_js')
<style>
.select2-selection__choice {
    background-color : #0020ff !important;
}
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <!-- Page Title -->
    <div class="mb-3">
        <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
            <img width="20" src="{{asset('/public/assets/back-end/img/push_notification.png')}}" alt="">
            {{\App\CPU\translate('push_notification')}}
        </h2>
    </div>
    <!-- End Page Title -->

    <!-- End Page Header -->
    <div class="row gx-2 gx-lg-3">
        <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
            <div class="card">
                <div class="card-body">
                    <form action="{{route('admin.business-settings.wishlist_send_notification')}}" method="post" style="text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}};" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="title-color text-capitalize" for="exampleFormControlInput1">{{\App\CPU\translate('Title')}} </label>
                                    <input type="text" name="title" class="form-control" placeholder="{{\App\CPU\translate('New notification')}}" required>
                                </div>
                                <div class="form-group">
                                    <label class="title-color text-capitalize" for="exampleFormControlInput1">{{\App\CPU\translate('Description')}} </label>
                                    <textarea name="description" class="form-control" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-check-label" for="type">{{\App\CPU\translate('Type')}}</label>
                                    <div class="row col-md-12 mb-2 mt-2">
                                        <div class="form-check col-md-6">
                                            <label class="form-check-label">
                                                <input type="radio" name="type" value="customer" class="form-check-input notification-type" checked>Customer
                                            </label>
                                        </div>
                                        <div class="form-check col-md-6">
                                            <label class="form-check-label">
                                                <input type="radio" name="type" value="product" class="form-check-input notification-type">Product
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group customer-list">
                                    <select name="customer[]" class="form-control js-select2-custom customer-select" multiple="multiple">
                                        @foreach($customer as $key => $value)
                                        <option value="selectall">{{\App\CPU\translate('Select All')}}</option>
                                        <option value="{{ $value->customer->cm_firebase_token }}">{{\App\CPU\translate($value->customer->f_name)}}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group product-list d-none">
                                    <select name="product[]" class="form-control js-select2-custom product-select" disabled multiple="multiple">
                                        <option value="selectall">{{\App\CPU\translate('Select All')}}</option>
                                        @foreach($product as $key => $value)
                                        <option value="{{ $value->customer->cm_firebase_token }}">{{\App\CPU\translate($value->product_full_info->name)}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <center>
                                        <img class="upload-img-view mb-4" id="viewer" onerror="this.src='{{asset('public/assets/front-end/img/image-place-holder.png')}}'" src="{{asset('public/assets/admin/img/900x400/img1.jpg')}}" alt="image" />
                                    </center>
                                    <label class="title-color text-capitalize">{{\App\CPU\translate('Image')}} </label>
                                    <span class="text-info">({{\App\CPU\translate('Ratio_1:1')}})</span>
                                    <div class="custom-file text-left">
                                        <input type="file" name="image" id="customFileEg1" class="custom-file-input" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                        <label class="custom-file-label" for="customFileEg1">{{\App\CPU\translate('Choose file')}}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-3">
                            <button type="reset" class="btn btn-secondary reset">{{\App\CPU\translate('reset')}} </button>
                            <button type="submit" class="btn btn--primary">{{\App\CPU\translate('Send')}} {{\App\CPU\translate('Notification')}} </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- End Table -->
    </div>
</div>
@endsection

@push('script')
<script>
    $(function() {
        $('.customer-select').select2({
          placeholder: "Select a customers",
          allowClear: true
        }).on('change',function(e){
            if($(this).find(':selected').val() == 'selectall') {
                $('.customer-select > option').prop('selected',true);
                var selected_arr = $('.customer-select').val();
                selected_arr.shift();
                $('.customer-select').val(selected_arr).trigger('change');
            }
        });
        $('.product-select').select2({
          placeholder: "Select a products",
          allowClear: true
        }).on('change',function(e){
            if($(this).find(':selected').val() == 'selectall') {
                $('.product-select > option').prop('selected',true);
                var selected_arr = $('.product-select').val();
                selected_arr.shift();
                $('.product-select').val(selected_arr).trigger('change');
            }
        });
    });
    $(document).on('change', '.notification-type', function() {
        $('.product-list').addClass('d-none');
        $('.customer-list').removeClass('d-none');
        $('.product-select').attr('disabled',true);
        $('.customer-select').removeAttr('disabled');
        $('.product-select').val('').trigger('change');
        if($(this).val() == 'product') {
            $('.product-list').removeClass('d-none');
            $('.customer-list').addClass('d-none');
            $('.product-select').removeAttr('disabled');
            $('.customer-select').attr('disabled',true);
            $('.customer-select').val('').trigger('change');
        }
    });
    $('.reset').on('click',function(){
        $('.product-select').val('').trigger('change');
        $('.customer-select').val('').trigger('change');
        $('.product-list').addClass('d-none');
        $('.customer-list').removeClass('d-none');
        $('.product-select').attr('disabled',true);
        $('.customer-select').removeAttr('disabled');
    });

</script>
@endpush