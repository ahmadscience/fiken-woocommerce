<?php

include_once FIKEN_PLUGIN_DIR . 'classes/controller.php';

class FikenView
{

    public static function render_view()
    {
        /** @var $my_var FikenController */
        $ctr = new FikenController();
        extract($ctr->data);

        ?>
        <script type="text/javascript">
            var admin_fiken_base_url_ajax = '<?php echo esc_js(esc_url_raw($admin_fiken_base_url_ajax)); ?>';
            var admin_fiken_ajax_nonce = '<?php echo esc_js(wp_create_nonce(Fiken::AJAX_NONCE_ACTION)); ?>';
            var admin_fiken_base_url = '<?php echo esc_js(esc_url_raw($admin_fiken_base_url)); ?>';
            var admin_fiken_current_tab = '<?php echo esc_js($current_tab); ?>';
            var admin_fiken_confirm_reg_if_transferred = '<?php echo esc_js(__('The order marked as %s. Are you sure?', 'fiken')); ?>';
            var admin_fiken_err_nothing_selected = '<?php echo esc_js(__('Nothing selected!', 'fiken')); ?>';
            var admin_fiken_order_register_success_mass = '<?php echo esc_js(__('Transfer selected orders successfully completed','fiken')); ?>';
            var admin_fiken_select_account = '<?php echo esc_js(__('Select account :','fiken')); ?>';

        </script>

        <div id="fiken-wrapper">
        <div class="alert-info">
            <img class="img-logo" src="<?php echo esc_url($img_dir); ?>logo.png">

            <h2><?php echo esc_html($plugin_name); ?></h2>
        </div>
        <div class="panel">

            <div
                class="alert alert-danger"  <?php echo isset($error_warning) && $error_warning ? "style='display:block'" : "style='display:none'"; ?>>
                <img class="alert_img" src="<?php echo esc_url($img_dir); ?>error_16x16.png"/>
                 <span id="alert-danger">
                     <?php echo isset($error_warning) && $error_warning ? esc_html($error_warning) : ''; ?>
                 </span>
                <button type="button" class="close">&times;</button>
            </div>

            <div
                class="alert alert-success"  <?php echo isset($success) && $success ? "style='display:block'" : "style='display:none'"; ?>>
                <img class="alert_img" src="<?php echo esc_url($img_dir); ?>success_16x16.png"/>
                    <span id="alert-success">
                    <?php echo (isset($success) && $success) ? esc_html($success) : ''; ?>
                    </span>
                <button type="button" class="close">&times;</button>
            </div>

            <div
                class="alert alert-warning"  <?php echo isset($warning) && $warning ? "style='display:block'" : "style='display:none'"; ?>>
                <img class="alert_img" src="<?php echo esc_url($img_dir); ?>warning_16x16.png"/>
                    <span id="alert-warning">
                    <?php echo (isset($warning) && $warning) ? esc_html($warning) : ''; ?>
                    </span>
                <button type="button" class="close">&times;</button>
            </div>

        </div>
        <!-- panel -->


        <div class="panel">
        <ul class="nav-tabs">
            <li class="tab-header active" data-content-tab-id="tab-orders"><a
                    href="javascript:void(0);"> <?php esc_html_e('Orders', 'fiken'); ?> </a></li>
            <li class="tab-header" data-content-tab-id="tab-settings"><a
                    href="javascript:void(0);"> <?php esc_html_e('Settings', 'fiken'); ?> </a></li>
        </ul>


        <div class="tab-content">

            <!-- ORDERS ------------------------------------------------------------------------------->

            <div id="tab-orders" class="tab-pane active">

                <form method="post" enctype="multipart/form-data" id="form_order" name="form_order">
                    <input type="hidden" name="submitOrders" value="">
                    <input type="hidden" value="fiken-plugin-page" name="page">


                    <div class="panel filtering">
                        <table class="form filtering">
                            <tr>
                                <td><span> <?php esc_html_e('Date Start', 'fiken') ?> </span>

                                    <div class="input-group date">
                                        <input id="filter_date_start" type="text" name="filter_date_start"
                                               value="<?php echo esc_attr($filter_date_start); ?>" placeholder="YYYY-MM-DD"
                                               data-format="YYYY-MM-DD" class="form-control"/>
                                                    <span class="input-group-btn">
                                                    <img id="img_filter_date_start" class="img-input"
                                                         src="<?php echo esc_url($img_dir); ?>day_32x32.png"/>
                                                    </span>
                                    </div>
                                </td>

                                <td><span><?php esc_html_e('Date End', 'fiken') ?></span>

                                    <div class="input-group date">
                                        <input id="filter_date_end" type="text" name="filter_date_end"
                                               value="<?php echo esc_attr($filter_date_end); ?>"
                                               placeholder="YYYY-MM-DD" data-format="YYYY-MM-DD" class="form-control"/>
                                                    <span class="input-group-btn">
                                                    <img id="img_filter_date_end" class="img-input"
                                                         src="<?php echo esc_url($img_dir); ?>day_32x32.png"/>
                                                    </span>
                                    </div>
                                </td>

                                <td>
                                    <span><?php esc_html_e('Order Status', 'fiken') ?></span>

                                    <div class="input-group">
                                        <select id="filter_order_status_id" name="filter_order_status_id" class="form-control">
                                            <option value="0" <?php echo $filter_order_status_id == '0' ? 'selected="selected"' : ''; ?>>
                                                <?php esc_html_e('All statuses', 'fiken'); ?>
                                            </option>
                                            <?php foreach ($states_fiken as $status) { ?>
                                                <option value="<?php echo esc_attr($status['id_state']); ?>"
                                                    <?php echo $status['id_state'] == $filter_order_status_id ? 'selected="selected"' : ''; ?>
                                                    <?php switch ($status['id_state']) {
                                                        case '1':
                                                            echo 'style="color:black"';
                                                            break;
                                                        case '2':
                                                            echo 'style="color:green"';
                                                            break;
                                                        case '3':
                                                            echo 'style="color:red"';
                                                            break;
                                                    }  ?>
                                                    >
                                                    <?php echo esc_html($status['name']); ?> </option>
                                            <?php }; ?>
                                        </select>
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group">
                                        <button type="button" id="button-filter" onclick="filter();"
                                                class="cmd-filter">
                                        <?php esc_html_e('Filter', 'fiken')  ;?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </table>

                    </div>

                    <div class="panel">

                        <table class="table table-order">
                            <thead>
                            <tr>
                                <td class="text-center">
                                    <a href="<?php echo esc_url($sort_order); ?>"
                                    <?php echo $sort === 'posts.id' ? 'class="' . strtolower($asc_desc) . '"' : '' ;?>
                                    >
                                    <?php esc_html_e('Order ID', 'fiken') ;?>
                                    </a>
                                </td>
                                <td class="text-left">
                                    <a href="<?php echo esc_url($sort_customer); ?>"
                                        <?php echo  $sort === 'customer' ? 'class="' . strtolower($asc_desc) . '"' : ''  ;?>
                                        >
                                        <?php esc_html_e('Customer', 'fiken') ;?>
                                    </a>
                                </td>
                                <td class="text-left">
                                    <a href="<?php echo esc_url($sort_status); ?>"
                                        <?php echo  $sort === 'current_state' ? 'class="' . strtolower($asc_desc) . '"' : ''  ;?>
                                        >
                                        <?php esc_html_e('Status', 'fiken') ;?>
                                    </a>
                                </td>
                                <td class="text-right">
                                    <a href="<?php echo esc_url($sort_total); ?>"
                                        <?php echo  $sort === 'total_paid' ? 'class="' . strtolower($asc_desc) . '"' : ''  ;?>
                                        >
                                        <?php esc_html_e('Total', 'fiken') ;?>
                                    </a>
                                </td>
                                <td class="text-left">

                                    <a href="<?php echo esc_url($sort_date_add); ?>"
                                        <?php echo  $sort === 'date_add' ? 'class="' . strtolower($asc_desc) . '"' : ''  ;?>
                                        >
                                        <?php esc_html_e('Date Added', 'fiken') ;?>
                                    </a>
                                </td>
                                <td class="text-left">
                                    <a href="<?php echo esc_url($sort_date_upd); ?>"
                                        <?php echo  $sort === 'date_upd' ? 'class="' . strtolower($asc_desc) . '"' : ''  ;?>
                                        >
                                        <?php esc_html_e('Date Modified', 'fiken') ;?>
                                    </a>
                                </td>
                                <td class="text-right">
                                    <a href="javascript:void(0);" class="img_button ajax-loader"
                                       style="display: none"></a>
                                    <a id="reg_order_mass" href="javascript:void(0);" title="<?php esc_attr_e('Transfer selected orders', 'fiken') ;?>"
                                       class="img_button transfer"></a>
                                    <a id="sel_order_all" href="javascript:void(0);" data-check="0"
                                       title="<?php esc_attr_e('Select all', 'fiken') ;?>"
                                       class="img_button check_empty"></a>
                                </td>
                            </tr>
                            </thead>


                            <tbody>


                            <?php if (isset($orders) && $orders) {  ?>
                            <?php foreach ($orders as $order) {?>

                            <tr id="order_id_<?php echo esc_attr($order['id_order']); ?>"
                            <?php echo $order['current_state'] == '2' ? 'style="color:green"' : ($order['current_state'] == '3' ? 'style="color:red"':'') ;?>
                            >

                            <td class="text-center"><a target="_blank" href="<?php echo esc_url($order['view']); ?>"><?php echo esc_html($order['id_order']); ?> </a></td>
                            <td class="text-left"><?php echo esc_html($order['customer']); ?></td>
                            <td class="text-left txtStatus" data-status-id="<?php echo esc_attr($order['current_state']); ?>"><?php echo esc_html($order['status_name_fiken']); ?></td>
                            <td class="text-right"><?php echo wp_kses_post($order['total_paid']); ?></td>
                            <td class="text-left"><?php echo esc_html($order['date_add']); ?></td>
                            <td class="text-left"><?php echo esc_html($order['date_upd']); ?></td>

                            <td class="text-right">
                                <a data-order-id="<?php echo esc_attr($order['id_order']); ?>"
                                   href="javascript:void(0);" title="<?php esc_attr_e('View statuses history','fiken');?>"
                                   class="img_button view_status_history"></a>

                                <a data-order-id="<?php echo esc_attr($order['id_order']); ?>"
                                   href="javascript:void(0);" title="<?php esc_attr_e('Transfer','fiken') ;?>"
                                   class="img_button transfer reg_order"></a>

                                <a data-order-id="<?php echo esc_attr($order['id_order']); ?>" data-check="0"
                                   href="javascript:void(0);" class="img_button check_empty sel_order"></a>
                            </td>
                            </tr>

                            <tr id="status_history_<?php echo esc_attr($order['id_order']); ?>" style="display: none" class="status_history">
                                <td colspan="8" align="right">
                                    <table class="table table-history">
                                        <thead>
                                        <tr>
                                            <td class="text-left"> <?php esc_html_e('Status','fiken') ;?></td>
                                            <td class="text-left"> <?php esc_html_e('Date Modified','fiken')  ;?>  </td>
                                            <td class="text-left"> <?php esc_html_e('Message','fiken')  ;?> </td>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>

                            <?php  } ?> <!--{/foreach}-->
                            <?php  } else { ?>
                            <tr>
                                <td class="text-center" colspan="7"><?php esc_html_e('No orders!','fiken') ;?></td>
                            </tr>
                            <?php } ;?>
                            </tbody>




                        </table>
                    </div>


                    <div class="panel pagination">
                        <?php echo wp_kses_post($pagination); ?>
                    </div>

                </form>
            </div>

<!-- SETTINGS ------------------------------------------------------------------------------->

            <div id="tab-settings" class="tab-pane ">

            <form method="post" enctype="multipart/form-data" id="form_settings" name="form_settings" >
                <input type="hidden" name="submitSettings" value="">
                <?php wp_nonce_field(Fiken::SETTINGS_NONCE_ACTION, Fiken::SETTINGS_NONCE_NAME); ?>
                <input type="hidden" value="fiken-plugin-page" name="page">


            <!--login & pass-->
            <div class="panel">
                <div class='form-group'>
                    <label class='control-label required'><?php esc_html_e('Login','fiken') ;?></label>

                    <div class='col-two-thirds'>
                        <input type='text' placeholder="<?php esc_attr_e('Login','fiken') ;?>" class='form-control' id='fiken_login' name='fiken_login'
                               value='<?php echo esc_attr($login); ?>'/>
                    </div>
                </div>

                <div class='form-group'>
                    <label class='control-label required'><?php esc_html_e('Password','fiken') ;?></label>

                    <div class='col-two-thirds'>
                        <input type='password' placeholder="<?php echo esc_attr($password_is_set ? __('Saved - leave empty to keep it', 'fiken') : __('Password', 'fiken')); ?>" class='form-control' id='fiken_passw'
                               name='fiken_passw' value='' autocomplete='new-password'/>
                    </div>
                </div>

                <div class='form-group'>
                    <label class='control-label'></label>
                    <div class='col-two-thirds'>
                        <button type="submit" id='cmdRefresh' name='cmdRefresh' class="cmd-refresh"><?php esc_html_e('Refresh','fiken') ;?> </button>
                    </div>
                </div>
            </div>

            <!--Company-->
            <div class="panel">
                <div class='form-group'>
                    <label class='control-label required'><?php esc_html_e('Company','fiken') ;?></label>
                    <div class='col-two-thirds'>
                        <select name="fiken_company" id="fiken_company" class="form-control settings_sel">
                            <?php if (!isset($companies) || !$companies) { ?>
                                <option value="" style='display:none;' selected="selected"><?php esc_html_e('No companies','fiken') ;?></option>
                            <?php } elseif (!isset($company_number) || !$company_number ) {  ?>
                                <option value="" style='display:none;' selected="selected"> <?php esc_html_e('Select company :','fiken') ;?></option>
                            <?php } ;?>
                            <?php if (isset($companies) && $companies) { ?>
                            <?php foreach ($companies as $comp) {?>
                                    <option value="<?php echo esc_attr($comp->organizationNumber); ?>"
                                        <?php echo isset($company_number) && ($comp->organizationNumber == $company_number) ?  'class="selected" selected="selected"' : ''; ?>
                                        ><?php echo esc_html($comp->name); ?></option>
                            <?php } } ;?>
                        </select>
                    </div>
                </div>
            </div>

            <!--Pay methods-->

            <div class="panel">
                <div class='form-group'>
                    <label class='control-label required'><?php esc_html_e('Accounts', 'fiken') ;?></label>
                    <div class='col-two-thirds'>
                        <table class="table table-pay">
                            <thead>
                            <tr>
                                <td class="text-left"><?php esc_html_e('Payment method', 'fiken') ;?></td>
                                <td class="text-left"><?php esc_html_e('Account', 'fiken') ;?></td>
                                <td class="text-left"><?php esc_html_e('Order Status', 'fiken') ;?></td>
                                <td class="text-left"><?php esc_html_e('Fiken Sale Type', 'fiken') ;?></td>
                            </tr>
                            </thead>
                            <tbody>


                            <?php if (isset($list_pay) && $list_pay) {?>
                            <?php foreach ( $list_pay as $pay) {?>
                                    <?php
                                    $fiken_acc_key =   "fiken_account_{$pay['id']}";
                                    $fiken_pay_status_key =   "fiken_pay_status_{$pay['id']}";
                                    $fiken_sale_kind_key =   "fiken_sale_kind_{$pay['id']}";
                                    ;?>

                                <tr>
                                <td class="text-left"><?php echo esc_html(ucfirst($pay['name'])); ?></td>

                                <td class="text-left">
                                    <select name="<?php echo esc_attr($fiken_acc_key); ?>" id="<?php echo esc_attr($fiken_acc_key); ?>" class="form-control settings_sel fiken_account">
                                        <?php if  (!isset($accounts) || !$accounts) { ?>
                                            <option value="" style='display:none;' selected="selected"><?php esc_html_e('No accounts','fiken') ;?></option>
                                        <?php } elseif (!isset($selectedAccounts) || !$selectedAccounts || !isset($selectedAccounts[$fiken_acc_key]) || !$selectedAccounts[$fiken_acc_key]) {  ?>
                                            <option value="" style='display:none;' selected="selected"><?php esc_html_e('Select account :','fiken') ;?> </option>
                                        <?php }  ;?>

                                        <?php if (isset($accounts) &&  $accounts) {   ;?>
                                        <?php foreach ( $accounts as $acc) { ?>
                                                <option value="<?php echo esc_attr($acc->code); ?>"
                                                <?php if  (isset($selectedAccounts)  && isset($selectedAccounts[$fiken_acc_key]) &&  $selectedAccounts[$fiken_acc_key] == $acc->code) {  ?>
                                                class="selected" selected="selected"
                                                <?php }
                                                else if ($acc->code == '0' && (!isset($selectedSalesKind)  || !isset($selectedSalesKind[$fiken_sale_kind_key])  || !$selectedSalesKind[$fiken_sale_kind_key] || $selectedSalesKind[$fiken_sale_kind_key] == FikenUtils::CASH_SALE)) { ?>
                                                style = "display: none"
                                                <?php };?>
                                                >
                                                <?php echo esc_html($acc->code . ' | ' . $acc->name); ?>
                                                </option>
                                        <?php }}; ?>
                                    </select>
                                </td>


                                <td class="text-left">

                                    <select name="<?php echo esc_attr($fiken_pay_status_key); ?>" id="<?php echo esc_attr($fiken_pay_status_key); ?>" class="form-control settings_sel">
                                        <?php if (!isset($list_states_wc) || !$list_states_wc) {?>
                                            <option value="" style='display:none;' selected="selected"> <?php esc_html_e('No statuses','fiken')  ;?></option>
                                        <?php } elseif (!isset($selectedPayStatuses) || !$selectedPayStatuses || !isset($selectedPayStatuses[$fiken_pay_status_key]) || !$selectedPayStatuses[$fiken_pay_status_key]) {?>
                                            <option value="" style='display:none;' selected="selected"> <?php esc_html_e('Select order status :','fiken')  ;?></option>
                                        <?php } ;?>

                                        <?php if (($list_states_wc) && $list_states_wc) { ?>
                                        <?php foreach ($list_states_wc as $pay_st) { ?>
                                             <option value="<?php echo esc_attr($pay_st['id']); ?>"
                                             <?php if (isset($selectedPayStatuses)  && isset($selectedPayStatuses[$fiken_pay_status_key]) &&  $selectedPayStatuses[$fiken_pay_status_key] == $pay_st['id']) {?>
                                             class="selected" selected="selected"
                                             <?php } ;?>
                                            >
                                            <?php echo esc_html($pay_st['name']); ?>
                                            </option>
                                        <?php }};?>
                                    </select>
                                </td>

                                <td class="text-left">

                                <select name="<?php echo esc_attr($fiken_sale_kind_key); ?>" id="<?php echo esc_attr($fiken_sale_kind_key); ?>"
                                            class="form-control settings_sel fiken_sale_kind">

                                    <?php if (!isset($list_sales_kind_fiken) || !$list_sales_kind_fiken) { ?>
                                        <option value="" style='display:none;' selected="selected"><?php esc_html_e('No sale types','fiken') ;?></option>
                                        <?php } elseif (!isset($selectedSalesKind) || !$selectedSalesKind || !isset($selectedSalesKind[$fiken_sale_kind_key]) || !$selectedSalesKind[$fiken_sale_kind_key]) { ?>
                                        <option value="" style='display:none;' selected="selected"><?php esc_html_e('Select sale type :','fiken') ;?> </option>
                                    <?php } ;?>

                                    <?php if (isset($list_sales_kind_fiken) && $list_sales_kind_fiken) {?>
                                    <?php foreach ($list_sales_kind_fiken as $s_k) {?>
                                        <option value="<?php echo esc_attr($s_k['code']); ?>"
                                        <?php if (isset($selectedSalesKind)  && isset($selectedSalesKind[$fiken_sale_kind_key])  &&  $selectedSalesKind[$fiken_sale_kind_key] == $s_k['code']) { ?>
                                             class="selected" selected="selected"
                                        <?php } ;?>
                                        >
                                        <?php echo esc_html($s_k['name']); ?>
                                        </option>
                                    <?php } } ;?>
                                    </select>
                                </td>
                            </tr>

                            <?php } } else {?>
                            <tr>
                                <td class="text-center" colspan="4"><?php esc_html_e('No payment plugins installed','fiken');?></td>
                            </tr>
                            <?php } ;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            <!-- Taxes -->

            <div class="panel">
                <div class='form-group'>
                    <label class='control-label required'><?php esc_html_e('VAT Mapping','fiken') ;?></label>

                    <div class='col-two-thirds'>
                        <table class="table table-tax">
                            <thead>
                            <tr>
                                <td class="text-left"><?php esc_html_e('Tax Class','fiken') ;?></td>
                                <td class="text-left"><?php esc_html_e('Fiken VAT Type','fiken') ;?></td>
                            </tr>
                            </thead>
                            <tbody>

                            <?php if (isset($list_taxes_ps) &&  $list_taxes_ps) { ?>
                            <?php foreach ($list_taxes_ps as  $tax) { ?>
                            <?php  $fiken_vat_key = "fiken_vat_{$tax['id']}";?>

                            <tr>
                                <td class="text-left"><?php echo esc_html($tax['name']); ?></td>
                                <td class="text-left">
                                    <select name="<?php echo esc_attr($fiken_vat_key); ?>" id="<?php echo esc_attr($fiken_vat_key); ?>" class="form-control settings_sel">
                                        <?php if (!isset($list_vat_types_fiken) || !$list_vat_types_fiken) { ?>
                                            <option value="" style='display:none;' selected="selected"> <?php esc_html_e('No Fiken VAT codes','fiken')  ;?></option>
                                        <?php } elseif (!isset($selectedVat) || !$selectedVat || !isset($selectedVat[$fiken_vat_key]) || !$selectedVat[$fiken_vat_key]) { ?>
                                            <option value="" style='display:none;' selected="selected"><?php esc_html_e('Select VAT code :','fiken')  ;?></option>
                                        <?php } ;?>
                                    <?php if (isset($list_vat_types_fiken) && $list_vat_types_fiken) {?>
                                    <?php foreach ($list_vat_types_fiken as $vat) {?>
                                         <option value="<?php echo esc_attr($vat['code']); ?>"
                                        <?php if (isset($selectedVat)  && isset($selectedVat[$fiken_vat_key]) &&  $selectedVat[$fiken_vat_key] == $vat['code']) { ?>
                                            class="selected" selected="selected"
                                        <?php };?>
                                        >
                                        <?php echo esc_html($vat['name']); ?>
                                        </option>
                                     <?php }} ;?>
                                    </select>
                                </td>
                            </tr>
                            <?php } } else {?>
                            <tr>
                                <td class="text-center" colspan="6"><?php esc_html_e('No taxes!','fiken') ;?></td>
                            </tr>
                            <?php } ;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>




                <!--Debug mode-->
                <div class="panel">
                    <div class='form-group'>
                        <label class='control-label'><?php esc_html_e('Debug mode','fiken') ;?></label>
                        <div class='col-two-thirds text-center'>
                            <select name="fiken_debug_mode" id="fiken_debug_mode" class="form-control settings_sel">
                                <option value="0" <?php echo !isset($debug_mode) || (!$debug_mode) ?  'class="selected" selected="selected"' : ''; ?>><?php esc_html_e('Off','fiken')  ;?></option>
                                <option value="1" <?php echo isset($debug_mode) && ($debug_mode) ?  'class="selected" selected="selected"' : ''; ?>><?php esc_html_e('On','fiken')  ;?></option>
                            </select>
                            <span><em><?php esc_html_e('please keep it turned off otherwise you will get a huge log-file','fiken') ;?></em></span>
                        </div>
                    </div>
                </div>

            <button type="submit" id='cmdSave' name='cmdSave' class="cmd-save"><?php esc_html_e('Save','fiken') ;?></button>

            </form>
            </div>

        </div>
        <!-- tab content -->

        </div>
        <!-- panel -->

        </div>
        <!-- final -->

    <?php
    }
}


