@extends('main')

@section('content')
<style>
    .margin-tb-15{
        margin-top: 15px;
    margin-bottom: 15px;
    }
    pre {
display: flex;
white-space: normal;
word-break: break-word;
}
.response{
        border: 1px solid #ccc;
    border-radius: 8px;
    box-shadow: 0px 2px 4px 3px #ccc;
    margin-bottom: 25px;
}
</style>
<div class="container">
    <h2 class="text-center">Detail API FOR: <em>{{ $doc->title }}</em></h2>
    <div class="row">
        @if($doc->description)
        <div class="col-md-12">
            <div class="col-sm-3">
                <h3>API Details:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{!! nl2br($doc->description) !!}</pre>
            </div>
        </div>
        @endif
        <div class="col-md-12">
            <div class="col-sm-3">
                <h3>API URL:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{{ url('/').'/'. $doc->url }}</pre>
            </div>
        </div>
        
        
        <div class="col-md-12">
            <div class="col-sm-3">
                <h3>Request Type/Method:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{{ $doc->method }}</pre>
            </div>
        </div>
        
        <div class="col-md-12">
            <div class="col-sm-3">
                <h3>Authentication Required:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{{ ucfirst($doc->auth) }}</pre>
            </div>
        </div>
        
        <div class="col-md-12">
            <div class="col-sm-3">
                <h3>Header Parameters:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{{  $doc->header_params }}</pre>
            </div>
        </div>
        <div class="clearfix"></div>
        <br />
        <div class="col-md-12 margin-tb-15">
            <div class="col-sm-3">
                <h3>Body Parameters:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{{ (empty($doc->body_params) || $doc->body_params == '[]') ? "No Body Params" : $doc->body_params }}</pre>
            </div>
        </div>
        
        <div class="clearfix"></div>
        <div class="col-md-12 response">
            <div class="col-sm-3">
                <h3>Response Format:</h3>
            </div>
            <div class="col-sm-9 margin-tb-15">
                <pre>{{  $doc->response_sample }}</pre>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js"></script>
@endsection
