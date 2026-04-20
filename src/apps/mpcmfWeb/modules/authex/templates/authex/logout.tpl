{include file="index/header.tpl" title="MPCMF" title2="выход из системы"}


<div>
    <div class="row">
        <div class="m-auto col-md-6 offset-md-3">
            <div class="login-panel card">
                <div class="card-header">
                    <h3 class="card-title text-center">
                        <strong>Внимание!</strong>&nbsp;Вы вышли из системы!<br>
                    </h3>
                </div>

                <div class="card-body">
                    <a class="d-flex justify-content-center" href="{$_application->getUrl('/authex/user/login')}">
                        <button class="btn btn-lg btn-success w-100 mt-2">Авторизоваться снова</button>
                    </a>
                    <br>
                    <a class="d-block w-100 text-muted float-end text-center"
                       href="{$data.redirectUrl|base64_decode}">
                        Вернуться на предыдущую страницу
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>


{include file="index/footer.tpl"}