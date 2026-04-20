{$_application->setTitle("{$_entity->getEntityName()} / Управление пользователями")}

{include file="crud/_page_title.tpl" title="Управление пользователями <strong>{$_entity->getEntityName()}</strong>"}

<style type="text/css">
    button.btn.btn-mini {
        padding: 1px;
    }
</style>
<div class="row">
    <div class="col-lg-5">
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-25">
                Пользователи
            </div>
            <div class="card-body">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Login</th>
                        <th>Имя</th>
                    </tr>
                    </thead>
                    <tbody>
                    {assign var="structure" value=$data.user->getModule()->getModuleRoutes()->getStructure()}
                    {foreach from=$data.user->getMapper()->getAllBy() item="user"}
                        <tr>
                            <td class="col-sm-1">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-light btn-mini dropdown-toggle"
                                            data-bs-toggle="dropdown">Действия
                                        <span class="bi bi-caret-down-fill"></span>
                                    </button>
                                    <ul class="dropdown-menu float-start" role="menu">
                                        <li class="text-no-wrap dropdown-item p-0">
                                            <a class="d-block py-2 px-3"
                                               href="{$_slim->urlFor('/authex/userManagement/userManagement.userUpdate', [$user->getMapper()->getKey() => $user->getIdValue()])}">Edit</a>
                                        </li>
                                        <li class="text-no-wrap dropdown-item p-0">
                                            <a class="d-block py-2 px-3" href="{$_slim->urlFor
                                            ('/authex/userManagement/userManagement.userGet', [$user->getMapper()->getKey() => $user->getIdValue()])}">View</a>
                                        </li>
                                        <li class="dropdown-divider">Crud</li>
                                        {foreach from=$structure[$user->getEntityUniqueName()]['actions'] key="routeName" item="routeAction"}
                                            {if $routeAction->getType() != 2}{continue}{/if}
                                            <li class="text-no-wrap dropdown-item p-0">
                                                <a class="d-block py-2 px-3" href="{$_slim->urlFor($routeName,
                                                [$user->getMapper()->getKey()=> $user->getIdValue()])}">CRUD: {$routeAction->getName()|htmlspecialchars}</a>
                                            </li>
                                        {/foreach}
                                    </ul>
                                </div>
                            </td>
                            <td>
                                {$user->getLogin()}
                            </td>
                            <td>
                                {$user->getFirstName()} {$user->getLastName()}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-25">
                Группы (только с участниками)
            </div>
            <div class="card-body">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Имя</th>
                        <th>Участников</th>
                    </tr>
                    </thead>
                    <tbody>
                    {assign var="structure" value=$data.group->getModule()->getModuleRoutes()->getStructure()}
                    {foreach from=$data.group->getMapper()->getAllBy() item="group"}
                        {assign var="groupCount" value=$user->getMapper()->getAllBy(['groups' => $group->getIdvalue()])->count()}
                        {if $groupCount == 0}{continue}{/if}
                        <tr>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-light btn-mini dropdown-toggle"
                                            data-bs-toggle="dropdown">
                                        Действия
                                        <span class="bi bi-caret-down-fill"></span>
                                    </button>
                                    <ul class="dropdown-menu float-start" role="menu">
                                        <li class="text-nowrap dropdown-item">
                                            <a href="{$_slim->urlFor('/authex/userManagement/userManagement.groupUpdate', [$group->getMapper()->getKey() => $group->getIdValue()])}">
                                                Edit
                                            </a>
                                        </li>
                                        <li class="text-nowrap dropdown-item">
                                            <a href="{$_slim->urlFor('/authex/userManagement/userManagement.groupGet', [$group->getMapper()->getKey() => $group->getIdValue()])}">
                                                View
                                            </a>
                                        </li>
                                        <li class="dropdown-divider">Crud</li>
                                        {foreach from=$structure[$group->getEntityUniqueName()]['actions'] key="routeName" item="routeAction"}
                                            {if $routeAction->getType() != 2}{continue}{/if}
                                            <li class="text-nowrap dropdown-item">
                                                <a href="{$_slim->urlFor($routeName, [$group->getMapper()->getKey() => $group->getIdValue()])}">
                                                    CRUD: {$routeAction->getName()|htmlspecialchars}
                                                </a>
                                            </li>
                                        {/foreach}
                                    </ul>
                                </div>
                            </td>
                            <td>
                                {$group->getName()}
                            </td>
                            <td>
                                {$groupCount}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>