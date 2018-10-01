@include("_head",['title'=>"Register Screen"])
<body>
<form class="form-signin" method="post">
    <div class="text-center mb-4">
        <img class="mb-4 img-fluid" src="{{$logo}}" alt="">
        <h1 class="h3 mb-3 font-weight-normal">{!! $title !!}</h1>
        <p>{!! $subtitle !!}</p>
    </div>
@if($valid)
    <div class="form-label-group">
        <input type="text" id="user" name="user" class="form-control" placeholder="user" readonly  value="{{$user}}">
        <label for="user">User</label>
        @if($error->get('user')->countError())<em class="text-danger">{{$error->get('user')->firstError()}}</em>@endif
    </div>

    <div class="form-label-group">
        <input type="password" id="password" name="password" class="form-control" placeholder="Password" autofocus required value="{{@$obj['password']}}">
        <label for="password">Password</label>
        @if($error->get('password')->countError())<em class="text-danger">{{$error->get('password')->firstError()}}</em>@endif
    </div>

    <div class="form-label-group">
        <input type="password" id="password2" name="password2" class="form-control" placeholder="Password" required value="{{@$obj['password2']}}">
        <label for="password2">Repeat Password</label>
        @if($error->get('password')->countError())<em class="text-danger">{{$error->get('password')->firstError()}}</em>@endif
    </div>

    <button class="btn btn-lg btn-primary btn-block" name="button" value="1" type="submit">Change the password</button>
@endif()
    <p class="text-danger h4 text-center">{!! $message !!}</p>
@if(!$valid)
        <a href="{!! $home !!}" class="btn btn-lg btn-primary btn-block" name="button" value="1" type="submit">Go to login screen</a>
@endif()


</form>
</body>
</html>
