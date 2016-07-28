{include file="index/header.tpl"}
<div class="container">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            {if !$status}
                {if $status !== null}
                    <div class="col-md-8 col-md-offset-2">
                        <div class="panel panel-default panel-danger">
                            <div class="panel-heading">Ошибка</div>
                            <div class="panel-body">
                                {if isset($data.errors[0]) && $data.errors[0]|strpos:"not found" !== false}
                                    Неверный email
                                {else}
                                    {$data.message|htmlentities}
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}
                {assign var="title" value="Восстановление пароля"}
                {assign var="submit" value="Восстановить пароль"}
                {assign var="fields" value=$data.loginFields}
                {include file="authex/default_form.tpl"}
                <span class="small"><a href="{$_application->getUrl('/')}">Вернуться на главную</a></span>
                <span class="small pull-right"><a href="{$_application->getUrl('/authex/user/login')}">Авторизоваться</a></span>
            {elseif $status}
                <div class="col-md-8 col-md-offset-2">
                    <div class="login-panel panel panel-default panel-success">
                        <div class="panel-heading">Успешно!</div>
                        <div class="panel-body">
                            Письмо с дальнейшими инструкциями выслано на email
                        </div>
                        <div class="panel-footer">
                            <a class="btn btn-default btn-sm" href="{$_application->getUrl('/')}">
                                Вернуться на главную
                            </a>
                        </div>
                    </div>
                </div>
            {/if}
        </div>
    </div>
</div>
{include file="index/footer.tpl"}