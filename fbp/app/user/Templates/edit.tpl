<form id="edit_form">
	
	<p>{t key="user.login_id"}</p>
	<h5>{$data.login_id}</h5>


	<p>{t key="user.name"}</p>
	<input type="text" name="name" value="{$data.name}">

	<p>{t key="user.email"}</p>
	<input type="text" name="email" value="{$data.email}">
	<p class="error">{$err_email}</p>

	
	<p>{t key="user.type"}</p>
	{html_options id="dropdown_user_type" name="type" options=$user_type_opt selected=$data.type}
	<p class="error">{$err_type}</p>

	<p>{t key="user.status"}</p>
	{html_options name="status" options=$arr_status selected=$data.status}
	
	<div class="permission-row">
	<p>{t key="user.developer"}</p>
	{html_options name="developer_permission" options=$developer_permission_opt selected=$data.developer_permission}
	
	<p>{t key="user.release_backup"}</p>
	{html_options name="data_manager_permission" options=$data_manager_permission_opt selected=$data.data_manager_permission}
	</div>
	
	

</form>

<button class="ajax-link" data-class="user" data-function="edit_exe" data-form="edit_form" data-id="{$data.id}">{t key="common.save"}</button>	

<script>
// type によって権限行の表示/非表示を切り替え
function togglePermissionRows() {
    var typeVal = $('select[name="type"]').val(); // type の値取得

    if (typeVal === '1') {
        $('.permission-row').show();
    } else {
        $('.permission-row').hide();
    }
}

$(function () {
    // 初期表示
    togglePermissionRows();

    // type 変更時に反映
    $('body').on('change', 'select[name="type"]', function () {
        togglePermissionRows();
    });
});

	
</script>
