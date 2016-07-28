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
                {foreach from=$data.group->getMapper()->getMap() key="fieldName" item="field"}
                    <tr>
                        <td class="col-sm-4">{$field.name}</td>
                        {if isset($data.group)}
                            <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field item=$data.group readonly=true _entity=$data.group->getEntity()}</td>
                        {else}
                            <td>{include file="forms/generate/type_{$field.formType}.tpl" fieldName=$fieldName field=$field readonly=true _entity=$data.group}</td>
                        {/if}
                    </tr>
                {/foreach}
                </tbody>
            </table>
    </div>
</div>
<div class="row">
    <div class="col-lg-offset-2 col-lg-8">
        <table class="table table-bordered table-condensed table-striped">
            <thead>
            <tr>
                <th>Действия</th>
                <th>Имя</th>
            </tr>
            </thead>
            <tbody>
            {assign var="userModelCursor" value=$data.userMapper->getAllBy(['groups' => $data.group->getIdValue()])}
            {assign var="structure" value=$data.userMapper->getModule()->getModuleRoutes()->getStructure()}
            {foreach from=$userModelCursor item="userModel"}
                <tr>
                    <td class="col-sm-1">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-default btn-mini dropdown-toggle"
                                    data-toggle="dropdown">
                                Действия
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu pull-left" role="menu">
                                <li><a href="{$_slim->urlFor('/authex/userManagement/userManagement.userUpdate', [$userModel->getMapper()->getKey() => $userModel->getIdValue()])}">Edit</a>
                                </li>
                                <li><a href="{$_slim->urlFor('/authex/userManagement/userManagement.userGet', [$userModel->getMapper()->getKey() => $userModel->getIdValue()])}">View</a>
                                </li>
                                <li class="divider">Crud</li>
                                {foreach from=$structure[$userModel->getEntityUniqueName()]['actions'] key="routeName" item="routeAction"}
                                    {if $routeAction->getType() != 2}{continue}{/if}
                                    <li>
                                        <a href="{$_slim->urlFor($routeName, [$userModel->getMapper()->getKey() => $userModel->getIdValue()])}">
                                            CRUD: {$routeAction->getName()|htmlspecialchars}
                                        </a>
                                    </li>
                                {/foreach}
                            </ul>
                        </div>
                    </td>
                    <td>
                        {$userModel->getFirstName()} {$userModel->getLastName()}
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>