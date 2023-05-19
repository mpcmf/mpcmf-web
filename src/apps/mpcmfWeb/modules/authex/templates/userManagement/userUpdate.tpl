{include file="crud/_page_title.tpl" title="Управление пользователями <strong>{$_entity->getEntityName()}</strong>"}
<script type="application/javascript" async>
  $(document).ready(function () {
    var generateTokenButton = $('#generateAccessToken');
    generateTokenButton.click(function () {
      generateTokenButton.addClass('disabled');
      $.ajax({
        url: '{$_slim->urlFor('/authex/userManagement/userManagement.userTokenUpdate', [$data.user->getMapper()->getKey() => $data.user->getIdValue()])}',
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
  });
</script>

{if !isset($status)}
    <div class="row">
        <div class="offset-lg-2 col-lg-8">
            <form method="post">
                <table class="table table-bordered table-sm table-striped">
                    <thead>
                    <tr>
                        <th>Поле</th>
                        <th>Значение</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$data.user->getMapper()->getMap() key="fieldName" item="field"}
                        <tr>
                            <td class="col-sm-4">{$field.name}</td>
                            {if isset($data.user)}
                                <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field item=$data.user _entity=$data.user->getEntity()}</td>
                            {else}
                                <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field _entity=$data.user}</td>
                            {/if}
                        </tr>
                    {/foreach}
                    <tr>
                        <td>Токен</td>
                        <td>
                            <div class="col-11 pl-0">
                                {if isset($data.userToken)}
                                    <input class="form-control"
                                           type="text"
                                           id="accessToken"
                                           value="{$data.userToken->getToken()}"
                                           readonly>
                                {else}
                                    <input class="form-control" type="text" id="accessToken" readonly>
                                {/if}
                            </div>
                            <button type="button"
                                    class="btn btn-warning text-white btn-circle"
                                    id="generateAccessToken">
                                <i
                                        class="fa fa-refresh"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            {include file="forms/generate/type_submit.tpl"}
                        </td>
                    </tr>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
{/if}