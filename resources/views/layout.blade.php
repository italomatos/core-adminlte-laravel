
@extends('adminlte::page')

@section('title', 'Administração')

@section('content_header')
    <h1>@yield('header')</h1>
@stop

@section('content')
    @yield('content')
@stop

@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
@stop

@section('js')
    <script> console.log('Hi!'); </script>
@stop