{include file="index/header.tpl"}
<div class="container">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            {if $status === false && isset($data.errors) && $data.errors|strpos:"not found" !== false}
                <div class="col-md-8 col-md-offset-2">
                    <div class="panel panel-default panel-danger">
                        <div class="panel-heading">Ошибка</div>
                        <div class="panel-body">Неверный логин или пароль</div>
                    </div>
                </div>
            {/if}
            {assign var="title" value="Авторизация"}
            {assign var="submit" value="Авторизоваться"}
            {assign var="fields" value=$data.loginFields}
            {include file="authex/default_form.tpl"}
            <span class="small"><a href="{$_application->getUrl('/authex/user/passwordRecovery')}">Забыли пароль?</a></span>
            <span class="small pull-right"><a href="{$_application->getUrl('/')}">Вернуться на главную</a></span>
        </div>
    </div>
</div>
{include file="index/footer.tpl"}