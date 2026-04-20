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
        success: function (content) {
          $('#accessToken').val(content.token);
          generateTokenButton.removeClass('disabled');
        },
        error: function () {
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
        success: function (content) {
          console.log('success');
          $('#tokenInfo').text(content).show();
          infoTokenButton.removeClass('disabled');
        },
        error: function () {
          console.log('error');
          console.log(content);
          $('#tokenInfo').hide();
          infoTokenButton.removeClass('disabled');
        }
      });
    });

    var changePasswordForm = $('#changePasswordForm');
    changePasswordForm.submit(function (event) {
      event.preventDefault();

      $('.change-password').removeClass('has-error');
      $('#passwordErrorMessagePanel').addClass('hidden');

      $.ajax({
        url: changePasswordForm.attr('action'),
        data: changePasswordForm.serialize(),
        type: changePasswordForm.attr('method'),
        dataType: 'json',
        success: function (response) {
          if(response.status) {
            alert('Пароль успешно изменён. После нажатия "ОК" Вы будете перенаправлены на страницу авторизации');
            setTimeout(function () {
              location.href = '{$_application->getUrl('/authex/user/login', [
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
        error: function (jqXHR, textStatus) {
          showInfoModal(textStatus, jqXHR.responseText);
        }
      });
    });

    var inviteForm = $('#invite-form');
    inviteForm.submit(function (event) {
      event.preventDefault();

      $.ajax({
        url: '{$_application->getUrl($_route->getName())}',
        data: inviteForm.serialize(),
        type: 'POST',
        dataType: "json",
        success: function (content) {
          $('#item-groups').val('').trigger("chosen:updated");
          inviteForm.reset();

          showInfoModal('Успешно!', 'Инвайт отправлен');

          var inviteList = $('#inviteList');
          inviteList.html('');
          for (var inviteId in content.invites) {
            var inviteItem = content.invites[inviteId];
            var inviteHtml = '<li title="' + inviteItem.invite + '" class="list-group-item';

            if(inviteItem.used) {
              inviteHtml += ' list-group-item-success';
            }
            inviteHtml += '">';

            if(inviteItem.used) {
              inviteHtml += inviteItem.email;
            } else {
              inviteHtml += '<a data-bs-toggle="collapse" href="#' + inviteItem.invite + '" aria-expanded="false" aria-controls="' + inviteItem.invite + '">' + inviteItem.email + '</a>'
                + '<div class="collapse" id="' + inviteItem.invite + '">'
                + '<br>'
                + 'https://obi-web.pltrm.net/invite/' + inviteItem.invite
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

<section>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 col-md-5 col-lg-4 p20 text-center">
                            <h4 class="mt-0 fw-bold fs-5">{$user->getFirstName()} {$user->getLastName()}</h4>
                            <img class="rounded vat" src="{$user->getAvatarLink(144)}">
                        </div>
                        <div class="col-sm-6 col-md-5 col-lg-4  p20">
                            <h4 class="mt-0">Состоит в группах:</h4>
                            <ul>
                                {foreach from=$user->getGroups() item="userGroup"}
                                    <li>{$userGroup->getName()}</li>
                                {/foreach}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li role="presentation" class="nav-item">
                            <button id="home-tab"
                                    class="nav-link"
                                    data-bs-toggle="tab"
                                    data-bs-target="#home"
                                    type="button"
                                    role="tab"
                                    aria-controls="home"
                                    aria-selected="false">Общее
                            </button>
                        </li>
                        <li role="presentation" class="nav-item">
                            <button id="settings-tab"
                                    class="nav-link active"
                                    data-bs-toggle="tab"
                                    data-bs-target="#settings"
                                    type="button"
                                    role="tab"
                                    aria-controls="settings"
                                    aria-selected="true">Настройки
                            </button>
                        </li>
                        {if $user->isAdmin() || $user->isRoot()}
                            <li role="presentation" class="nav-item">
                                <button id="invites-tab"
                                        class="nav-link"
                                        data-bs-toggle="tab"
                                        data-bs-target="#invites"
                                        type="button"
                                        role="tab"
                                        aria-controls="invites"
                                        aria-selected="false">Инвайты
                                </button>
                            </li>
                        {/if}
                        <li role="presentation" class="nav-item">
                            <button id="password-tab"
                                    class="nav-link"
                                    data-bs-toggle="tab"
                                    data-bs-target="#set-password"
                                    type="button"
                                    role="tab"
                                    aria-controls="set-password"
                                    aria-selected="false">Изменить пароль
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade p10" role="tabpanel" id="home" aria-labelledby="home-tab">
                            <h4><strong>JIRA</strong></h4>
                            <p>Открытые задачи</p>
                        </div>
                        <div class="tab-pane fade in p20 show active" id="settings" aria-labelledby="settings-tab">

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group mb-2">
                                        <label class="col-form-label fw-bold" for="accessToken">Access Code</label>
                                        <input class="form-control" type="text" name="accessToken" id="accessToken"
                                               placeholder="Token"
                                               value="{$data.token}"
                                               readonly>
                                    </div>
                                    <div class="form-group d-flex justify-content-end gap-2">
                                        <a href="javascript:void(0);" id="generateAccessToken"
                                           class="btn btn-success">Сгенерировать новый
                                        </a>
                                        <a href="javascript:void(0);"
                                           id="tokenInfoButton"
                                           class="btn btn-info text-white">Инфо
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row pt20">
                                <div class="col-sm-2"></div>
                                <div class="col-sm-8">
                                    <pre id="tokenInfo"></pre>
                                </div>
                            </div>

                        </div>
                        {if $user->isAdmin() || $user->isRoot()}
                            <div class="tab-pane fade p20" role="tabpanel" id="invites" aria-labelledby="invites-tab">

                                <div class="row">
                                    <div class="col-sm-6">
                                        <form role="form" method="post" id="invite-form">
                                            <div class="form-group mb-2">
                                                <label class="mb-1 fw-bold">Email</label>
                                                <input name="item[email]" class="form-control" type="email">
                                            </div>
                                            <div class="form-group">
                                                <label class="mb-1 fw-bold">Группы</label>
                                                {assign var="map" value=$data.inviteEntity->getMapper()->getMap()}
                                                {include file="forms/generate/type_searcheblemultiselect.tpl" fieldName='groups' field=$map.groups item=null}
                                            </div>
                                            <div class="form-group text-end mt-3">
                                                <button class="btn btn-success" type="submit">Пригласить</button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="mb-1">Созданные инвайты</label>
                                        <ul class="list-group" id="inviteList">
                                            {foreach from=$data.invites item="invite"}
                                                <li title="{$invite['invite']}"
                                                    class="list-group-item{if $invite['used']} list-group-item-success{/if}">
                                                    {if $invite['used']}
                                                        {$invite['email']}
                                                    {else}
                                                        <a data-bs-toggle="collapse"
                                                           href="#{$invite['invite']}"
                                                           aria-expanded="false"
                                                           aria-controls="{$invite['invite']}">{$invite['email']}</a>
                                                        <div class="collapse" id="{$invite['invite']}">
                                                            <br>
                                                            https://obi-web.pltrm.net/invite/{$invite['invite']}
                                                        </div>
                                                    {/if}
                                                </li>
                                            {/foreach}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        {/if}
                        <div class="tab-pane fade p20"
                             role="tabpanel"
                             id="set-password"
                             aria-labelledby="password-tab">
                            <div class="col-sm-6">
                                <form action="{$_slim->urlFor('/authex/user/changePassword')}"
                                      method="post"
                                      id="changePasswordForm">
                                    <div class="form-group hidden mb-2" id="passwordErrorMessagePanel">
                                        <div class="card">
                                            <div class="card-header bg-danger bg-opacity-25"
                                                 id="passwordErrorMessage"></div>
                                        </div>
                                    </div>
                                    <div class="clearfix"></div>
                                    <div class="form-group change-password mb-2 d-flex justify-content-end"
                                         id="oldPassword">
                                        <label class="col-form-label text-end col-sm-5 fw-bold">Старый пароль</label>

                                        <div class="col-sm-7 ms-3">
                                            <input type="password" class="form-control" name="password[old]">
                                        </div>
                                    </div>
                                    <div class="form-group change-password mb-2 d-flex justify-content-end"
                                         id="newPassword">
                                        <label class="col-form-label text-end col-sm-5 fw-bold">Новый пароль</label>

                                        <div class="col-sm-7 ms-3">
                                            <input type="password" class="form-control" name="password[new]">
                                        </div>
                                    </div>
                                    <div class="form-group change-password d-flex justify-content-end"
                                         id="confirmPassword">
                                        <label class="col-form-label text-end col-sm-5 fw-bold">Повторите новый
                                            пароль</label>

                                        <div class="col-sm-7 ms-3">
                                            <input type="password" class="form-control" name="password[confirm]">
                                        </div>
                                    </div>

                                    <div class="form-group text-end mt-3">
                                        <div>
                                            <button type="submit" class="btn btn-outline-warning">Изменить пароль
                                            </button>
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