@include("_head",['title'=>"Login Screen"])
<body>
<form class="form-signin" method="post">
    <div class="text-center mb-4">
        <img class="mb-4" src="{{$logo}}" alt="">
        <h1 class="h3 mb-3 font-weight-normal">{!! $title !!}</h1>
        <p>{!! $subtitle !!}</p>
    </div>

    <div class="form-label-group">
        <input type="text" id="inputEmail" name="user" class="form-control" placeholder="user" required autofocus value="{{$user}}">
        <label for="inputEmail">User</label>
    </div>

    <div class="form-label-group">
        <input type="password" id="inputPassword" name="password" class="form-control" placeholder="Password" required value="{{$password}}">
        <label for="inputPassword">Password</label>
    </div>
    @if($useCookie)
    <div class="checkbox mb-3">
        <label>
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>
    </div>
    @endif()
    <input type="hidden" name="returnUrl" value="{{$returnUrl}}" />
    <button class="btn btn-lg btn-primary btn-block" name="button" value="login" type="submit">Sign in</button>
    <p class="mt-5 mb-3 text-muted text-center">{{$message}}</p>


</form>
</body>
</html>
