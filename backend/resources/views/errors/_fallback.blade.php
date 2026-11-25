{{-- Framework minimal error page for non-central hosts. Inputs: $code, $message. --}}
@extends('errors::minimal')

@section('title', $message)
@section('code', $code)
@section('message', $message)
