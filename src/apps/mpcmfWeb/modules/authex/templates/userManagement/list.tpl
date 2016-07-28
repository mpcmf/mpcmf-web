<ul class="pagination">
    <li class="paginate_button previous disabled" tabindex="0"><a href="#">Previous</a></li>
    <li class="paginate_button next" tabindex="0"><a href="#">Next</a></li>
</ul>
<table class="table table-bordered table-condensed table-hover col-xs-12">
    <thead>
    <tr>
        <th></th>
        {assign var="map" value=$data.entity->getMapper()->getMap()}
        {foreach from=$map item='field' key='fieldName'}
            <th title="{$field.name}" style="max-width: 100px; overflow: hidden; text-overflow: ellipsis;">{$field.name}</th>
        {/foreach}
    </tr>
    </thead>
    <tbody>
    {foreach from=$data.items item="item"}
        <tr>
            <td width="10px">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown"
                            aria-expanded="false">
                        <span class="caret"></span>
                        Действия
                    </button>
                    <ul class="dropdown-menu pull-left" role="menu">
                        <li><a href="#">Admin action</a>
                        </li>
                        <li class="divider"></li>
                        <li><a href="#">Basic action</a>
                        </li>
                    </ul>
                </div>
            </td>
            {foreach from=$map key='fieldName' item='field'}
                {if $field.formType == "text"}
                    {assign var="fieldValue" value="{$item->getFieldValue($fieldName)|htmlspecialchars}"}
                {elseif ($field.formType == "datetimepicker" || $field.formType == "timepicker")}
                    {assign var="fieldValue" value="{date('Y-m-d H:i:s', $item->getFieldValue($fieldName))}"}
                {else}
                    {assign var="fieldValue" value="{$item->getFieldValue($fieldName)|json_encode:384|htmlspecialchars}"}
                {/if}
                <td style="max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{$fieldValue}">
                    {$fieldValue}
                </td>
            {/foreach}
        </tr>
    {/foreach}
    </tbody>
</table>

<ul class="pagination">
    <li class="paginate_button previous disabled" tabindex="0"><a href="#">Previous</a></li>
    <li class="paginate_button next" tabindex="0"><a href="#">Next</a></li>
</ul>