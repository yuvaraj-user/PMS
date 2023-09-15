@extends('layouts.back-end.app')

@section('title', \App\CPU\translate('wishlist_list'))

@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">
    <!-- Page Title -->
    <div class="mb-3">
        <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
            <img src="{{asset('/public/assets/back-end/img/subscribers.png')}}" width="20" alt="">
            {{\App\CPU\translate('wishlist_list')}}
            <span class="badge badge-soft-dark radius-50 fz-14 ml-1">{{ $wishlist->total() }}</span>
        </h2>
    </div>
    <!-- End Page Title -->

    <div class="row mt-20">
        <div class="col-md-12">
            <a href="{{ route('admin.business-settings.wishlist_push_notication') }}" class="text-white text-decoration-none btn btn-success float-right mb-2" type="button">Send push notification</a>
        </div>
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <!-- Search -->
                    <form action="{{ url()->current() }}" method="GET">
                        <div class="input-group input-group-merge input-group-custom">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <i class="tio-search"></i>
                                </div>
                            </div>
                            <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="{{ \App\CPU\translate('Search_by_email')}}" aria-label="Search orders" value="{{ $search }}" autocomplete="off">
                            <button type="submit" class="btn btn--primary">{{ \App\CPU\translate('Search')}}</button>
                        </div>
                    </form>
                    <!-- End Search -->


                    <div>
                        <button type="button" class="btn btn-outline--primary" data-toggle="dropdown">
                            <i class="tio-download-to"></i>
                            {{\App\CPU\translate('Export')}}
                            <i class="tio-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <form action="{{route('admin.business-settings.wishlist_excel')}}" method="POST" id="wishlist_excel_form">
                                @csrf
                                <input type="hidden" name="excel_search" value="{{ (Request::query('search') != null) ? Request::query('search') : ''}}">
                                <li><a class="dropdown-item" onclick="document.getElementById('wishlist_excel_form').submit();">{{\App\CPU\translate('Excel')}}</a></li>
                                <div class="dropdown-divider"></div>
                            </form>
                        </ul>
                    </div>
                </div>

                <div class="table-responsive">
                    <table style="text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}};" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                        <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th>{{ \App\CPU\translate('SL')}}</th>
                                <th>{{ \App\CPU\translate('customer_name')}}</th>
                                <th>{{ \App\CPU\translate('product_name')}}</th>
                                <th>{{ \App\CPU\translate('wishlist_date')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($wishlist as $key=> $val)
                            <tr>
                                <td>{{$wishlist->firstItem()+$key}}</td>
                                <td>{{!is_null($val->customer) ? $val->customer->f_name : ''}}</td>
                                <td>{{$val->product_full_info->name}}</td>
                                <td>
                                    {{date('d M Y, h:i A',strtotime($val->created_at))}}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>

                <div class="table-responsive mt-4">
                    <div class="px-4 d-flex justify-content-lg-end">
                        <!-- Pagination -->
                        {{$wishlist->links()}}
                    </div>
                </div>

                @if(count($wishlist)==0)
                <div class="text-center p-4">
                    <img class="mb-3 w-160" src="{{asset('public/assets/back-end')}}/svg/illustrations/sorry.svg" alt="Image Description">
                    <p class="mb-0">{{ \App\CPU\translate('No_data_to_show')}}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection