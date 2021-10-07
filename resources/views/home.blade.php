@extends('main')

@section('content')
<div class="container">
    <h2>List of Modules's API</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                <?php $i = 1; ?>
                @foreach($docs as $key => $doc) 
                <div class="panel panel-default">
                    <div class="panel-heading" role="tab" id="headingOne">
                        <h4 class="panel-title">
                            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#{{ $key }}" aria-expanded="true" aria-controls="collapseOne">
                                {{ $i++ }}-  {!! str_replace('_', ' ', $key) !!} ({{count($doc) }})
                            </a>
                        </h4>
                    </div>
                    <div id="{{ $key }}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
                        <div class="panel-body">

                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Method</th>
                                        <th>URL</th>
                                        <th>Auth Required</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($doc as $row)
                                    <tr>
                                        <td><a href="{{ route('api-detail', $row->id) }}" target="_blank">{{ $row->title }}</a></td>
                                        <td>{{ $row->method }}</td>
                                        <td>{{ $row->url }}</td>
                                        <td>{{ ucfirst($row->auth) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>


                        </div>
                    </div>
                </div>

                @endforeach  


            </div>


            <script>
                if (window.location.hash != "") {
                    $(window.location.hash).addClass('in');
                }
            </script>
        </div>
    </div>
</div>
@endsection
