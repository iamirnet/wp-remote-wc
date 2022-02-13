<?php
add_action('admin_menu', 'iamir_net_manage_admin_menu');
function iamir_net_manage_admin_menu()
{
    add_menu_page('آی امیر - دریافت اطلاعات از API', 'دریافت اطلاعات', 'iamir_net_manage', 'i_amir_remote_wc', '', 'dashicons-admin-tools', 15);
    add_submenu_page('i_amir_remote_wc', 'آی امیر - دریافت اطلاعات از API', 'دریافت اطلاعات', 'iamir_net_manage', 'i_amir_remote_wc', 'iamir_net_receives');

}
