<?php
function iamir_net_receives()
{
    global $iamirapidbwc;
    if (!current_user_can('iamir_net_manage')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    $message = null;
    $categories_brands = $iamirapidbwc->get_results("select * , (select category.text from category where category.id = extrafield.category_id) as category, (select brand.name from brand where brand.id = extrafield.brand_id) as brand from extrafield;") ? : [];
    if ($_POST){
        if (isset($_POST['status_save'])) {
            update_option('i_amir_remote_wc_status', $_POST['status']);
        }
        if (isset($_POST['groups_save'])) {
            update_option('i_amir_remote_wc_groups', $_POST['groups']);
            update_option('i_amir_remote_wc_products_import_last_id', 0);
        }
        if (isset($_POST['price_coef_save'])) {
            update_option('i_amir_remote_wc_price_coef', $_POST['price_coef']);
        }
        if (isset($_POST['host_uri_save'])) {
            update_option('i_amir_remote_wc_host_uri', $_POST['host_uri']);
        }
        if (isset($_POST['token_save'])) {
            update_option('i_amir_remote_wc_token', $_POST['token']);
        }
        if (isset($_POST['auth_save'])) {
            update_option('i_amir_remote_wc_username', $_POST['username']);
            update_option('i_amir_remote_wc_password', $_POST['password']);
        }
        if (isset($_POST['db_second_save'])) {
            update_option('i_amir_remote_wc_db_host', $_POST['db_host']);
            update_option('i_amir_remote_wc_db_username', $_POST['db_username']);
            update_option('i_amir_remote_wc_db_password', $_POST['db_password']);
            update_option('i_amir_remote_wc_db_name', $_POST['db_name']);
        }
        if (isset($_POST['products_import_last_save'])) {
            update_option('i_amir_remote_wc_products_import_last_id', $_POST['products_import_last_id']);
            iAmirAPIWCImport::importProducts();
            $message = 'دریافت اطلاعات در صف قرار گرفت.';
        }
        if (isset($_POST['products_dispatch_last_save'])) {
            update_option('i_amir_remote_wc_products_dispatch_last_id', $_POST['products_dispatch_last_id']);
           // iAmirAPIWCImport::dispatchProducts();
            $message = 'درخواست بروزرسانی محصولات ارسال شد.';
        }
        if (isset($_POST['products_update_last_save'])) {
            update_option('i_amir_remote_wc_products_update_last_id', $_POST['products_update_last_id']);
            //iAmirAPIWCImport::updateProducts();
            $message = 'بروزرسانی محصولات در صف قرار گرفت.';
        }
    }
    $purl = null;
    if ($_GET) {
        if (isset($_GET['action']) && $_GET['action'] == "product_update" && isset($_GET['post']) && $_GET['post']) {
            iAmirAPIWCImport::dispatchProduct($_GET['post']);
            $message = 'درخواست بروزرسانی محصول ارسال شد.';
            $purl = admin_url('post.php?post='.$_GET['post'].'&action=edit');
        }
    }
    $groups = get_option("i_amir_remote_wc_groups", []);
    $status = get_option("i_amir_remote_wc_status", 'inactive');
    ?>
    <div class="wrap">

        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php if ($message) { ?>
        <div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">
            <p>
                <strong><?php echo $message; ?></strong>
            </p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">رد کردن این اخطار</span>
            </button>
            <?php
                echo $purl ? '<a href="'.$purl.'" class="button button-primary">
                بازگشت به محصول
            </a>' : '';
            ?>
        </div>
        <?php } ?>
        <form method="post" action="<?php echo esc_html(admin_url('admin.php?page=i_amir_remote_wc')); ?>">
            <table class="form-table" role="presentation">

                <tbody>
                <tr>
                    <th scope="row">
                        <label for="status">وضعیت</label>
                    </th>
                    <td>
                        <select name="status" id="status">
                            <option value="active" <?php echo $status == "active" ? "selected" : null; ?>>فعال</option>
                            <option value="inactive" <?php echo $status == "inactive" ? "selected" : null; ?>>غیرفعال</option>
                        </select>
                        <input type="submit" name="status_save" id="status_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <!--<tr>
                    <th scope="row">
                        <label for="host_uri">آدرس API</label>
                    </th>
                    <td>
                        <input type="text" name="host_uri" id="host_uri" placeholder="آدرس API" value="<?php /*echo get_option('i_amir_remote_wc_host_uri', ""); */?>">
                        <input type="submit" name="host_uri_save" id="host_uri_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>-->
                <tr>
                    <th scope="row">
                        <label for="host_uri">ضریب قیمت</label>
                    </th>
                    <td>
                        <input type="text" name="price_coef" id="price_coef" placeholder="ضریب قیمت" value="<?php echo get_option('i_amir_remote_wc_price_coef', 1); ?>">
                        <input type="submit" name="price_coef_save" id="price_coef_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <!--<tr>
                    <th scope="row">
                        <label for="host_uri_save">اطلاعات ورود</label>
                    </th>
                    <td>
                        <input type="text" name="username" id="username" placeholder="username" value="<?php /*echo get_option('i_amir_remote_wc_username', ""); */?>">
                        <input type="password" name="password" id="password" placeholder="password">
                        <input type="submit" name="auth_save" id="auth_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>-->
                <tr>
                    <th scope="row">
                        <label for="db_second_save">اطلاعات دیتابیس</label>
                    </th>
                    <td>
                        <input type="text" name="db_host" id="db_host" placeholder="db_host" value="<?php echo get_option('i_amir_remote_wc_db_host', ""); ?>">
                        <input type="text" name="db_username" id="db_username" placeholder="db_username" value="<?php echo get_option('i_amir_remote_wc_db_username', ""); ?>">
                        <input type="password" name="db_password" id="db_password" placeholder="db_password">
                        <input type="text" name="db_name" id="db_name" placeholder="db_name" value="<?php echo get_option('i_amir_remote_wc_db_name', ""); ?>">
                        <input type="submit" name="db_second_save" id="db_second_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="products_import_last_id">ایمپورت محصولات</label>
                    </th>
                    <td>
                        <input type="text" name="products_import_last_id" id="products_import_last_id" placeholder="آخرین محصول" value="<?php echo get_option('i_amir_remote_wc_products_import_last_id', 0); ?>">
                        <input type="submit" name="products_import_last_save" id="products_import_last_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="products_import_last_id">بررسی محصولات</label>
                    </th>
                    <td>
                        <input type="text" name="products_dispatch_last_id" id="products_dispatch_last_id" placeholder="آخرین محصول" value="<?php echo get_option('i_amir_remote_wc_products_dispatch_last_id', 0); ?>">
                        <input type="submit" name="products_dispatch_last_save" id="products_dispatch_last_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="products_update_last_id">بروزرسانی محصولات</label>
                    </th>
                    <td>
                        <input type="text" name="products_update_last_id" id="products_update_last_id" placeholder="آخرین محصول" value="<?php echo get_option('i_amir_remote_wc_products_update_last_id', 0); ?>">
                        <input type="submit" name="products_update_last_save" id="products_update_last_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="groups">دسته بندی و برند ها</label>
                    </th>
                    <td>
                        <select name="groups[]" id="groups" multiple>
                            <?php
                                foreach ($categories_brands as $index => $group) {
                                    echo "<option value='$group->id' " . (in_array($group->id, $groups) ? "selected" : "").">{$group->category} - {$group->brand}</option>";
                                }
                            ?>
                        </select>
                        <input type="submit" name="groups_save" id="groups_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>
                <!--<tr>
                    <th scope="row">
                        <label for="token">توکن API</label>
                    </th>
                    <td>
                        <input type="text" name="token" id="token" placeholder="توکن API" value="<?php /*echo get_option('i_amir_remote_wc_token', ""); */?>">
                        <input type="submit" name="token_save" id="token_save" class="button button-primary" value="ذخیره">
                    </td>
                </tr>-->
                </tbody>
            </table>

            <?php
            ?>
        </form>

    </div><!-- .wrap -->
    <?php
}
