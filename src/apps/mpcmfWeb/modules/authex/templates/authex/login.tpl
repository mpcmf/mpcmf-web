{include file="index/header.tpl"}

<div >
    <div class="row">
        <div class="m-auto col-md-6 offset-md-3">
            {if $status === false && isset($data.errors) && $data.errors|strpos:"not found" !== false}
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><strong>Ошибка</strong></h3>
                    </div>
                    <div class="card-body text-center bg-warning bg-opacity-25">
                        <h4 class="text-bold">Неверный логин или пароль</h4>
                    </div>
                </div>
            {/if}
            {include
                file="authex/default_form.tpl"
                title="<strong>Авторизация</strong>"
                submit="Войти в систему"
                fields=$data.loginFields
                forgot_pass=true
            }
        </div>
    </div>
</div>
{include file="index/footer.tpl"}