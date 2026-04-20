{include file="index/header.tpl" title="MPCMF" title2="нет доступа"}

<div>
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="login-panel card">
                <div class="card-header">
                    <h3 class="card-title text-center">
                        <strong>Внимание!</strong>&nbsp;Нет доступа!<br>
                    </h3>
                </div>
                <div class="card-body">
                    <a href="{$_application->getUrl('/authex/user/login', ['redirectUrl' => $data.redirectUrl])}">
                        <button class="btn btn-lg btn-success w-100 mt-2">Авторизоваться</button>
                    </a>
                    <br/>
                    <a class="text-muted float-end m5" href="{$data.redirectUrl|base64_decode}">
                        Вернуться на предыдущую страницу
                    </a>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
  $(window).bind('storage', function (e) {
    if(localStorage.getItem('authorizationTime')) {
      setTimeout(function () {
        location.reload();
      }, 5000);
    }
    ;
  });
</script>


{include file="index/footer.tpl"}