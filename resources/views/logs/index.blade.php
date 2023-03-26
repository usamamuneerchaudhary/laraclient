<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lara Client Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/prismjs@1.28.0/themes/prism.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.28.0/prism.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }

        pre {
            border-radius: 5px;
            padding: 15px;
        }

        .code-container {
            display: flex;
            justify-content: space-between;
        }

        .code-block {
            width: 45%;
        }

        hr {
            border: none;
            border-top: 2px solid #ddd;
            margin: 30px 0;
        }
    </style>
</head>

<body>
@if(count($logs)>=1)
    @foreach($logs as $log)
        <h2>Endpoint: {{$log->endpoint}}(<span>{{$log->method}}</span>)</h2>
        <small>Created: {{\Carbon\Carbon::createFromTimeStamp(strtotime($log->created_at))->diffForHumans()}}</small>
        <div class="code-container">
            <div class="code-block">
                <h3>Request</h3>
                <pre><code class="language-html">
                {!! $log->request_payload !!}
            </code></pre>
            </div>
            <div class="code-block">
                <h3>Response</h3>
                <pre><code class="language-html">
                    {!! $log->response_body !!}
            </code></pre>
            </div>
        </div>
        <hr>
    @endforeach
    {{$logs->links('pagination::bootstrap-4')}}
@else
    <h2>No logs found</h2>
@endif
</body>
</html>
