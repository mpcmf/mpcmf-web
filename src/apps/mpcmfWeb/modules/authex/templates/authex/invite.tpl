{assign var="user" value=$data.user}
{if isset($status) && !$status}
    <h2>Ошибка</h2>
    <div class="bg-warning">{$data.errors|json_encode}</div>
{else}
    <h2>Инвайт</h2>
    <div>
        письмо: <a href="mailto:{$user.email}?subject=SDS: инвайт&body=Привет!%0A%0AДля регистрации в новой системе авторизации SDS сгенерирован персональный инвайт:%0A
        http://auth.sdstream.ru/inviteme/{$data.inviteLink}">Отослать инвайт</a>
        <br>
        ссылка: <a href="http://auth.sdstream.ru/inviteme/{$data.inviteLink}">http://auth.sdstream.ru/inviteme/{$data.inviteLink}</a>
    </div>
    <table class="users">
        <tr>
            <th>Login</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Registration Date</th>
            <th>Last Visit</th>
            <th>Invite Link</th>
            <th>Action</th>
        </tr>
        <tr>
            <td>
                <label>
                    <input type="text" name="user[login]" value="{if isset($user.login)}{$user.login}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="text" name="user[first_name]" value="{if isset($user.first_name)}{$user.first_name}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="text" name="user[last_name]" value="{if isset($user.last_name)}{$user.last_name}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="text" name="user[email]" value="{if isset($user.email)}{$user.email}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="text" name="user[reg_date]" value="{if isset($user.reg_date)}{$user.reg_date}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="text" name="user[last_visit]" value="{if isset($user.last_visit)}{$user.last_visit}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="text" name="user[invite_link]" value="{if isset($user.invite_link)}{$user.invite_link}{/if}" readonly>
                </label>
            </td>
            <td>
                <label>
                    <input type="submit" value="Сохранить" readonly>
                </label>
            </td>
        </tr>
    </table>
{/if}
