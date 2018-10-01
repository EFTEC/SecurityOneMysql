@include("_head",['title'=>"Register Screen"])
<body>
<form class="form-signin" method="post">
    <div class="text-center mb-4">
        <img class="mb-4 img-fluid" src="{{$logo}}" alt="">
        <h1 class="h3 mb-3 font-weight-normal">{!! $title !!}</h1>
        <p>{!! $subtitle !!}</p>
    </div>

    <div class="form-label-group">
        <input type="text" id="user" name="user" class="form-control" placeholder="user" required autofocus value="{{@$obj['user']}}">
        <label for="user">User</label>
        @if($error->get('user')->countError())<em class="text-danger">{{$error->get('user')->firstError()}}</em>@endif
    </div>

    <div class="form-label-group">
        <input type="password" id="password" name="password" class="form-control" placeholder="Password" required value="{{@$obj['password']}}">
        <label for="password">Password</label>
        @if($error->get('password')->countError())<em class="text-danger">{{$error->get('password')->firstError()}}</em>@endif
    </div>

    <div class="form-label-group">
        <input type="password" id="password2" name="password2" class="form-control" placeholder="Password" required value="{{@$obj['password2']}}">
        <label for="password2">Repeat Password</label>
        @if($error->get('password2')->countError())<em class="text-danger">{{$error->get('password2')->firstError()}}</em>@endif
    </div>

    <div class="form-label-group">
        <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Nombre" required value="{{@$obj['fullname']}}">
        <label for="fullname">Name</label>
        @if($error->get('fullname')->countError())<em class="text-danger">{{$error->get('fullname')->firstError()}}</em>@endif
    </div>

    <div class="form-label-group">
        <input type="email" id="email" name="email" class="form-control" placeholder="Email" required value="{{@$obj['email']}}">
        <label for="email">Email</label>
        @if($error->get('email')->countError())<em class="text-danger">{{$error->get('email')->firstError()}}</em>@endif
    </div>

    <input type="hidden" name="returnUrl" value="{{$returnUrl}}" />
    <button class="btn btn-lg btn-primary btn-block" name="button" value="1" type="submit">Sign in</button>
    <p class="mt-5 mb-3 text-muted text-center">{{$message}}</p>


</form>
</body>
</html>
