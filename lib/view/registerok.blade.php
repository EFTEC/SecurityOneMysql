@include("_head",['title'=>"Register Screen"])
<body>
<form class="form-signin" method="post">
    <div class="text-center mb-4">
        <img class="mb-4 img-fluid" src="{{$logo}}" alt="" >
        <h1 class="h3 mb-3 font-weight-normal">{!! $title !!}</h1>
        <p>{!! $subtitle !!}</p>
        <hr>
        <div>Correo enviado {{$email}}. Confirme su correo.</div>
    </div>
</form>
</body>
</html>
