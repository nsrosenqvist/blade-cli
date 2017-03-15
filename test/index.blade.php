@extends('layouts.master')

@section('content')

Include sub view?
@include('sub.other')

PHP variables are loaded?
{{ isset($namePHP) ? 'yes: name = '.$namePHP : 'no' }}

JSON variables are loaded?
{{ isset($nameJSON) ? 'yes: name = '.$nameJSON : 'no' }}

JSON string variables are loaded?
{{ isset($nameString) ? 'yes: name = '.$nameString : 'no' }}

Extensions are loaded?
@no("yes")

@endsection
