{if isset($refererModel)}
<p>{$refererModel->getFullName()} приглашает Вас присоединиться!</p>
{/if}

<p>Для того, что бы зарегистрироваться, необходимо перейти по ссылке:</p>

<p><a href="{$inviteLink}">{$inviteLink}</a></p>

<p>Если Вы считаете, что письмо было получено по ошибке, просто проигнорируйте его.</p>

<p>С уважением,
команда SDS</p>