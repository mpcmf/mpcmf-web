{include file="index/header.tpl"}
<style type="text/css">
    .login-panel {
        margin-top: 5% !important;
    }
</style>
<div class="container">
    {if $status === false && isset($data.errors) && $data.errors|strpos:"not found" !== false}
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default panel-danger">
                <div class="panel-heading">Внимание</div>
                <div class="panel-body">
                    <p>Инвайт не найден или был использован.</p>
                    <p>Регистрация будет продолжена без инвайта</p>
                </div>
            </div>
        </div>
    {/if}
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            {assign var="title" value="Регистрация пользователя"}
            {assign var="submit" value="Зарегистрироваться"}
            {assign var="fields" value=$data.loginFields}
            {include file="authex/default_form.tpl"}
        </div>
    </div>
</div>
{include file="index/footer.tpl"}