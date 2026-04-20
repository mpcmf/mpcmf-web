<div class="login-panel card">
    <div class="card-header">
        <h3 class="card-title">{$title}</h3>
    </div>
    <div class="card-body">
        <form id="login_form" role="form" method="post">
            {assign var="map" value=$_entity->getMapper()->getNormalizedMap()}
            <fieldset>
                {foreach from=$fields key="localFieldKey" item="realFieldKey" name='loginForm'}
                    {assign var="fieldData" value=$map[$realFieldKey]}
                    <div class="form-group{if isset($data.errorFields[$realFieldKey])} has-error{/if}{if isset($data.hiddenFields[$localFieldKey])} hidden{/if}">
                        <label class="col-form-label" for="item-{$realFieldKey}">{$fieldData['name']}</label>
                        <input {if $smarty.foreach.loginForm.first}autofocus{/if}
                               name="item[{$realFieldKey}]"
                               id="item-{$realFieldKey}"
                               type="{if $localFieldKey === 'password'}password{else}text{/if}"
                               class="form-control"
                                {if isset($data.readonlyFields[$realFieldKey]) && $data.readonlyFields[$realFieldKey]}
                                    readonly
                                {/if}
                               placeholder="{$fieldData['description']|htmlspecialchars}"
                                {if $localFieldKey !== 'password' && isset($data.item[$realFieldKey])}
                                    value="{$data.item[$realFieldKey]|htmlspecialchars}"
                                {/if}
                        >
                        {if isset($data.errorFields[$realFieldKey])}
                            {foreach from=$data.errorFields[$realFieldKey] item="errorText"}
                                <p class="text-info">{$errorText|htmlspecialchars}</p>
                            {/foreach}
                        {/if}
                        {if isset($data.hiddenFields[$localFieldKey])}{/if}
                    </div>
                {/foreach}
                {if isset($forgot_pass)}
                    <span class="small forgot_pass"><a href="{$_application->getUrl('/authex/user/passwordRecovery')}">Забыли пароль?</a></span>
                    <span class="small float-end"><a href="/">Вернуться на главную</a></span>
                {/if}
                <button type="submit" class="btn btn-lg btn-success mt-2">{$submit}</button>
            </fieldset>
        </form>
    </div>
</div>

<script>
  $('#login_form').on('submit', function (e) {
    var time = new Date();
    localStorage.setItem('authorizationTime', time.getTime());
  });
  $(window).bind('storage', function (e) {
    if(localStorage.getItem('authorizationTime')) {
      setTimeout(function () {
        location.reload();
      }, 5000);
    }
    ;
  });
</script>