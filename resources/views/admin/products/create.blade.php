@extends('layouts.app')
@section('title','Add furniture')
@section('content')<header class="page-heading"><div><p class="section-kicker">Catalog records</p><h1>Add furniture</h1><p>Use exact materials, finish, price, and measurements so recommendations remain trustworthy.</p></div></header><form class="panel" method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">@csrf @include('admin.products._form')</form>@endsection
