{assign var="user" value=$data.user}

<style>
    #tokenInfo {
        display: none;
    }
</style>
<script type="application/javascript" async>
    $(document).ready(function () {
        var generateTokenButton = $('#generateAccessToken');
        generateTokenButton.click(function () {
            generateTokenButton.addClass('disabled');
            $.ajax({
                url: '{$_slim->urlFor('/authex/token/api.token.generate')}',
                type: "POST",
                dataType: "json",
                data: {
                    updateToken: true
                },
                success: function(content) {
                    $('#accessToken').val(content.token);
                    generateTokenButton.removeClass('disabled');
                },
                error: function() {
                    generateTokenButton.removeClass('disabled');
                }
            });
        });
        var infoTokenButton = $('#tokenInfoButton');
        infoTokenButton.click(function () {
            infoTokenButton.addClass('disabled');
            $.ajax({
                url: '{$_slim->urlFor('/authex/token/api.token.getInfo')}',
                type: "POST",
                dataType: "text",
                data: {
                    token: $('#accessToken').val()
                },
                success: function(content) {
                    console.log('success');
                    $('#tokenInfo').text(content).show();
                    infoTokenButton.removeClass('disabled');
                },
                error: function() {
                    console.log('error');
                    console.log(content);
                    $('#tokenInfo').hide();
                    infoTokenButton.removeClass('disabled');
                }
            });
        });

        var changePasswordForm = $('#changePasswordForm');
        changePasswordForm.submit(function () {
            event.preventDefault();

            $('.change-password').removeClass('has-error');
            $('#passwordErrorMessagePanel').addClass('hidden');

            $.ajax({
                url: changePasswordForm.attr('action'),
                data: changePasswordForm.serialize(),
                type: changePasswordForm.attr('method'),
                dataType: 'json',
                success: function(response) {
                    if (response.status) {
                        alert('Пароль успешно изменён. После нажатия "ОК" Вы будете перенаправлены на страницу авторизации');
                        setTimeout(function () {
                            location.href ='{$_application->getUrl('/authex/user/login', [
                                'redirectUrl' => {$_application->getUrl('/authex/user/profile')|base64_encode}
                            ])}';
                        }, 300);
                        return;
                    }

                    $('.change-password').addClass('has-success');

                    $('#passwordErrorMessagePanel').removeClass('hidden');
                    for (var fieldId in response.data.errorFields) {
                        var field = response.data.errorFields[fieldId];

                        $('#' + field + 'Password').addClass('has-error');
                    }
                    $('#passwordErrorMessage').text(response.data.message);
                },
                error: function(jqXHR, textStatus) {
                    showInfoModal(textStatus, jqXHR.responseText);
                }
            });
        });

        $('#invite-form').submit(function () {
            event.preventDefault();

            $.ajax({
                url: '{$_application->getUrl($_route->getName())}',
                data: $("#invite-form").serialize(),
                type: 'POST',
                dataType: "json",
                success: function (content) {
                    $('#item-groups').val('').trigger("chosen:updated");
                    $('#invite-form').reset();

                    showInfoModal('Успешно!', 'Инвайт отправлен');
                    var inviteList = $('#inviteList');

                    inviteList.html('');
                    for(var inviteId in content.invites) {
                        var inviteItem = content.invites[inviteId];
                        var inviteHtml = '<li title="' + inviteItem.invite + '" class="list-group-item';

                        if (inviteItem.used) {
                            inviteHtml += ' list-group-item-success';
                        }
                        inviteHtml += '">';

                        if (inviteItem.used) {
                            inviteHtml += inviteItem.email;
                        } else {
                            inviteHtml += '<a data-toggle="collapse" href="#' + inviteItem.invite + '" aria-expanded="false" aria-controls="' + inviteItem.invite + '">' + inviteItem.email + '</a>'
                                    + '<div class="collapse" id="' + inviteItem.invite + '">'
                                    + '<br>'
                                    + 'http://filter01.sdstream.ru/invite/' + inviteItem.invite
                                    + '</div>';
                        }
                        inviteHtml += '</li>';

                        inviteList.append(inviteHtml);
                    }
                },
                error: function (jqXHR, textStatus) {
                    showInfoModal(textStatus, jqXHR.responseText);
                }
            });
        });
    });
</script>

<section style="padding: 20px 0;">
    <div class="row">
        <div class="col-lg-12">
            <div class="col-lg-2">
                <img src="{$user->getAvatarLink(144)}" class="img-rounded" style="width: 144px;">
            </div>
            <div class="col-lg-5 alert alert-info">
                <h2>{$user->getFirstName()} {$user->getLastName()}</h2>
                <h4>Состоит в группах:</h4>
                <ul>
                    {foreach from=$user->getGroups() item="userGroup"}
                    <li>
                        {$userGroup->getName()}
                    </li>
                    {/foreach}
                </ul>
            </div>

            <div class="col-lg-5">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        Ура! Нововведения!
                    </div>
                    <div class="panel-body">
                        <h4>28.10.2015</h4>
                        <p>Теперь можно самостоятельно изменить пароль на вкладке <b>"Изменить пароль"</b></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section style="padding: 20px 0;">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <ul class="nav nav-tabs">
                        <li class=""><a href="#home" data-toggle="tab" aria-expanded="true">Общее</a>
                        </li>
                        <li class="active"><a href="#settings" data-toggle="tab" aria-expanded="false">Настройки</a>
                        </li>
                        {if $user->isAdmin() || $user->isRoot()}
                        <li class=""><a href="#invites" data-toggle="tab" aria-expanded="false">Инвайты</a>
                        </li>
                        {/if}
                        <li class=""><a href="#set-password" data-toggle="tab" aria-expanded="false">Изменить пароль</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade" id="home">
                            <h4>JIRA</h4>

                            <p>Открытые задачи</p>
                        </div>
                        <div class="tab-pane fade  active in form-horizontal form-group" id="settings">
                            <h4>Settings Tab</h4>

                            <div class="row">
                                <label class="control-label col-sm-2">Access code</label>

                                <div class="col-sm-7">
                                    <input type="text" id="accessToken" class="form-control" readonly
                                           placeholder="AccessCode" value="{$data.token}">
                                </div>
                                <div class="col-sm-3">
                                    <a href="javascript:void(0);" id="generateAccessToken"
                                                         class="btn btn-success">Сгенерировать новый</a>
                                    <a href="javascript:void(0);" id="tokenInfoButton" class="btn btn-info">Инфо</a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-2"></div>
                                <div class="col-sm-7">
                                    <pre id="tokenInfo"></pre>
                                </div>
                            </div>
                        </div>
                        {if $user->isAdmin() || $user->isRoot()}
                        <div class="tab-pane fade" id="invites">
                            <h4>Инвайты</h4>

                            <div class="col-sm-6">
                                <label>Созданные инвайты</label>
                                <ul class="list-group" id="inviteList">
                                    {foreach from=$data.invites item="invite"}
                                        <li title="{$invite['invite']}" class="list-group-item{if $invite['used']} list-group-item-success{/if}">
                                            {if $invite['used']}
                                                {$invite['email']}
                                            {else}
                                                <a data-toggle="collapse" href="#{$invite['invite']}" aria-expanded="false" aria-controls="{$invite['invite']}">{$invite['email']}</a>
                                                <div class="collapse" id="{$invite['invite']}">
                                                    <br>
                                                    <a href="{$_application->getUrl('/authex/invite/invite', ['invite' => $invite['invite']])}">
                                                        ссылка
                                                    </a>
                                                </div>
                                            {/if}
                                        </li>
                                    {/foreach}
                                </ul>
                            </div>
                            <div class="col-sm-6">
                                <form role="form" method="post" id="invite-form">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input name="item[email]" class="form-control" type="email">
                                    </div>
                                    <div class="form-group">
                                        <label>Группы</label>
                                        {assign var="map" value=$data.inviteEntity->getMapper()->getMap()}
                                        {include file="forms/generate/type_searcheblemultiselect.tpl" fieldName='groups' field=$map.groups item=null}
                                    </div>
                                    <button class="btn btn-success" type="submit">Пригласить</button>
                                </form>
                            </div>
                        </div>
                        {/if}
                        <div class="tab-pane fade form-horizontal" id="set-password">
                            <div class="col-sm-6">
                                <form action="{$_slim->urlFor('/authex/user/changePassword')}" method="post" id="changePasswordForm">
                                    <br>
                                    <div class="form-group hidden" id="passwordErrorMessagePanel">
                                        <div class="panel panel-danger">
                                            <div class="panel-heading" id="passwordErrorMessage"></div>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="form-group change-password" id="oldPassword">
                                        <label class="control-label col-sm-5">Старый пароль</label>

                                        <div class="col-sm-7">
                                            <input type="password" class="form-control" name="password[old]">
                                        </div>
                                    </div>
                                    <div class="form-group change-password" id="newPassword">
                                        <label class="control-label col-sm-5">Новый пароль</label>

                                        <div class="col-sm-7">
                                            <input type="password" class="form-control" name="password[new]">
                                        </div>
                                    </div>
                                    <div class="form-group change-password" id="confirmPassword">
                                        <label class="control-label col-sm-5">Повторите новый пароль</label>

                                        <div class="col-sm-7">
                                            <input type="password" class="form-control" name="password[confirm]">
                                        </div>
                                    </div>
                                    <br>

                                    <div class="form-group">
                                        <div class="col-sm-offset-5 col-sm-7">
                                            <button type="submit" class="btn btn-warning">Изменить пароль</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</section>