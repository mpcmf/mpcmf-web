{include file="index/header.tpl"}
<style type="text/css">
    .login-panel {
        margin-top: 5% !important;
    }
</style>
<div class="container">
    {if $status === false && isset($data.errors) && $data.errors|strpos:"not found" !== false}
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">Внимание</div>
                <div class="card-body">
                    <p>Инвайт не найден или был использован.</p>
                    <p>Регистрация будет продолжена без инвайта</p>
                </div>
            </div>
        </div>
    {/if}
    <div class="row">
        <div class="col-md-8 offset-md-2">
            {assign var="title" value="Регистрация пользователя"}
            {$_application->setTitle($title)}
            {assign var="submit" value="Зарегистрироваться"}
            {assign var="fields" value=$data.loginFields}
            {include file="authex/default_form.tpl"}
        </div>
    </div>
</div>
{include file="index/footer.tpl"}