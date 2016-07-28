{include file="crud/_page_title.tpl" title="Управление пользователями <strong>{$_entity->getEntityName()}</strong>"}

<div class="row">
    <div class="col-lg-offset-2 col-lg-8">
            <table class="table table-bordered table-condensed table-striped">
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
                            <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field item=$data.user readonly=true _entity=$data.user->getEntity()}</td>
                        {else}
                            <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field readonly=true _entity=$data.user}</td>
                        {/if}
                    </tr>
                {/foreach}
                {if isset($data.userToken)}
                    <tr>
                        <td>Токен</td>
                        <td>{include file="forms/generate/type_text.tpl" fieldName='token' item=$data.userToken readonly=true _entity=$data.userToken->getEntity()}</td>
                    </tr>
                {/if}
                </tbody>
            </table>
    </div>
</div>