{include file="crud/_page_title.tpl" title="Управление пользователями <strong>{$_entity->getEntityName()}</strong>"}

{if !isset($status)}
    <div class="row">
        <div class="col-lg-offset-2 col-lg-8">
            <form method="post">
                <table class="table table-bordered table-condensed table-striped">
                    <thead>
                    <tr>
                        <th>Поле</th>
                        <th>Значение</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$data.group->getMapper()->getMap() key="fieldName" item="field"}
                        <tr>
                            <td class="col-sm-4">{$field.name}</td>
                            {if isset($data.group)}
                                <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field item=$data.group _entity=$data.group->getEntity()}</td>
                            {else}
                                <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field _entity=$data.group}</td>
                            {/if}
                        </tr>
                    {/foreach}
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