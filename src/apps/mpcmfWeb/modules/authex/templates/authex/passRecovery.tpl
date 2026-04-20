{include file="index/header.tpl"}
<div class="container">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            {if !$status}
                {if $status !== null}
                    <div class="col-md-8 offset-md-2">
                        <div class="card">
                            <div class="card-header">Ошибка</div>
                            <div class="card-body">
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
                {$_application->setTitle($title)}
                {assign var="submit" value="Восстановить пароль"}
                {assign var="fields" value=$data.loginFields}
                {include file="authex/default_form.tpl"}
                <span class="small"><a href="/">Вернуться на главную</a></span>
                <span class="small float-end"><a href="{$_application->getUrl('/authex/user/login')}">Авторизоваться</a></span>
            {elseif $status}
                <div class="col-md-8 offset-md-2">
                    <div class="login-panel card">
                        <div class="card-header">Успешно!</div>
                        <div class="card-body">
                            Письмо с дальнейшими инструкциями выслано на email
                        </div>
                        <div class="card-footer">
                            <a class="btn btn-light btn-sm" href="/">
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