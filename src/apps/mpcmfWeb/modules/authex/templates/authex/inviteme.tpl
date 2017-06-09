<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <title>SDS: инвайт</title>
    <!-- Bootstrap core CSS -->
    <link href="/bower_components/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #eee;
        }

        .form-signin {
            max-width: 640px;
            padding: 15px;
            margin: 0 auto;
        }

        .form-signin .form-signin-heading,
        .form-signin .checkbox {
            margin-bottom: 10px;
        }

        .form-signin .checkbox {
            font-weight: normal;
        }

        .form-signin .form-control {
            position: relative;
            height: auto;
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box;
            padding: 10px;
            font-size: 16px;
        }

        .form-signin .form-control:focus {
            z-index: 2;
        }

        .form-signin input[type="email"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }

        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }

        .alert {
            text-align: center;
        }

        div.alert pre {
            text-align: left;
        }
    </style>
</head>
<body>
<div class="container">
    <form class="form-signin" role="form" method="POST">
        <h2 class="form-signin-heading">
            SDS: инвайт<br>
            <small>регистрация</small>
        </h2>
        {if !$status}
            <div class="alert alert-danger" role="alert">
                <strong>Ошибка:</strong>&nbsp;
                <pre>{$data|json_encode:384}</pre>
            </div>
        {else}
            {assign var="user" value=$data.user}
            {assign var="saved" value=$data.saved}
            {if $saved}
                <div class="alert alert-success" role="alert">
                    <strong>Регистрация успешна!</strong>
                </div>
            {/if}
            <label for="inputLogin" class="sr-only">Логин</label>
            <input name="user[login]" type="text" id="inputLogin" class="form-control" placeholder="Логин"
                   value="{if isset($user.login)}{$user.login}{/if}" {if !$saved}required{else}readonly{/if} autofocus>
            <label for="inputFirstName" class="sr-only">Имя</label>
            <input name="user[first_name]" type="text" id="inputFirstName" class="form-control" placeholder="Имя"
                   value="{if isset($user.first_name)}{$user.first_name}{/if}" {if !$saved}required{else}readonly{/if}>
            <label for="inputLastName" class="sr-only">Фамилия</label>
            <input name="user[last_name]" type="text" id="inputLastName" class="form-control" placeholder="Фамилия"
                   value="{if isset($user.last_name)}{$user.last_name}{/if}" {if !$saved}required{else}readonly{/if}>
            <label for="inputEmail" class="sr-only">Email</label>
            <input name="user[email]" type="text" id="inputEmail" class="form-control" placeholder="E-mail"
                   value="{$user.email}" readonly>
            <label for="inputPassword" class="sr-only">Пароль</label>
            <input name="user[password]" type="password" id="inputPassword" class="form-control"
                   placeholder="Пароль" {if !$saved} required{else} value="{str_repeat('*', strlen($user.password))}" readonly{/if}>
            {if !$saved}
                <button class="btn btn-lg btn-primary btn-block" type="submit">Регистрация</button>
            {/if}
        {/if}
    </form>
</div>
<!-- /container -->
</body>
</html>