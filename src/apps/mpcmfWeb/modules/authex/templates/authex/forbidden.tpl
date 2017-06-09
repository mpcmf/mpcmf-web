{include file="index/header.tpl" title="Social Data Stream" title2="нет доступа"}

<div class="col-md-offset-4 col-md-4">
    <div class="alert alert-danger" role="alert">
        <strong>Внимание!</strong>&nbsp;
        Нет доступа!<br>
    </div>

    <ul class="nav nav-pills">
        <li role="presentation"><a href="{$_application->getUrl('/authex/user/login', ['redirectUrl' => $data.redirectUrl])}">Авторизоваться</a></li>
        <li role="presentation"><a href="{$data.redirectUrl|base64_decode}">Вернуться на предыдущую страницу</a></li>
    </ul>
</div>

{include file="index/footer.tpl"}