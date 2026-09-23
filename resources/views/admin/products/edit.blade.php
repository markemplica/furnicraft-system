@extends('layouts.app')
@section('title','Edit '.$product->name)
@section('content')<header class="page-heading"><div><p class="section-kicker">Catalog records</p><h1>Edit {{ $product->name }}</h1><p>Changes affect future recommendations and customer searches.</p></div></header><form class="panel" method="POST" action="{{ route('admin.products.update',$product) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.products._form')</form>@endsection
