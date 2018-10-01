@include("_head",['title'=>"Register Screen"])
<body>
<form class="form-signin" method="post">

    <div class="text-center mb-4">
        <img class="mb-4 img-fluid" src="{{$logo}}" alt="">
        <h1 class="h3 mb-3 font-weight-normal">{!! $title !!}</h1>
        <p>{!! $subtitle !!}</p>
        @if($message)<p class="text-danger">{{$message}}</p>@endif
    </div>

    <div class="form-label-group">
        <input type="text" id="user" name="user" class="form-control" placeholder="user" autofocus value="{{@$obj['user']}}">
        <label for="user">User</label>
        @if($error->get('user')->countError())<em class="text-danger">{{$error->get('user')->firstError()}}</em>@endif
    </div>
    <button class="btn btn-lg btn-primary btn-block" name="button" value="user" type="submit">Recover by User</button>
    <hr>
    <div class="form-label-group">
        <input type="email" id="email" name="email" class="form-control" placeholder="Email" value="{{@$obj['email']}}">
        <label for="email">Email</label>
        @if($error->get('email')->countError())<em class="text-danger">{{$error->get('email')->firstError()}}</em>@endif
    </div>

    <button class="btn btn-lg btn-primary btn-block" name="button" value="email" type="submit">Recover By Email</button>



</form>
</body>
</html>
