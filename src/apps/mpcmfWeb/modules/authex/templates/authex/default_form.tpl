<div class="login-panel panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{$title}</h3>
    </div>
    <div class="panel-body">
        <form role="form" method="post">
            {assign var="map" value=$_entity->getMapper()->getNormalizedMap()}
            <fieldset>
                {foreach from=$fields key="localFieldKey" item="realFieldKey"}
                    {assign var="fieldData" value=$map[$realFieldKey]}
                    <div class="form-group{if isset($data.errorFields[$realFieldKey])} has-error{/if}{if isset($data.hiddenFields[$localFieldKey])} hidden{/if}">
                        <label class="control-label" for="item-{$realFieldKey}">{$fieldData['name']}</label>
                        <input
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
                <button type="submit" class="btn btn-lg btn-success btn-block">{$submit}</button>
            </fieldset>
        </form>
    </div>
</div>