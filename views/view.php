<?php

if (!defined('ABSPATH')) {
    exit;
}

include_once FIKEN_PLUGIN_DIR . 'classes/controller.php';

class FikenView
{
    public static function render_view()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access the Fiken settings.', 'fiken'), 403);
        }

        $ctr  = new FikenController();
        $data = $ctr->data;

        $plugin_name             = isset($data['plugin_name'])            ? $data['plugin_name']            : 'Fiken';
        $img_dir                 = isset($data['img_dir'])                ? $data['img_dir']                : '';
        $current_tab             = isset($data['current_tab'])            ? $data['current_tab']            : 'tab-orders';
        $error_warning           = isset($data['error_warning'])          ? $data['error_warning']          : '';
        $success                 = isset($data['success'])                ? $data['success']                : '';
        $warning                 = isset($data['warning'])                ? $data['warning']                : '';
        $companies               = isset($data['companies'])              ? $data['companies']              : array();
        $company_slug            = isset($data['company_slug'])           ? $data['company_slug']           : '';
        $accounts                = isset($data['accounts'])               ? $data['accounts']               : array();
        $list_pay                = isset($data['list_pay'])               ? $data['list_pay']               : array();
        $list_states_wc          = isset($data['list_states_wc'])         ? $data['list_states_wc']         : array();
        $list_sales_kind_fiken   = isset($data['list_sales_kind_fiken'])  ? $data['list_sales_kind_fiken']  : array();
        $list_vat_types_fiken    = isset($data['list_vat_types_fiken'])   ? $data['list_vat_types_fiken']   : array();
        $list_taxes_ps           = isset($data['list_taxes_ps'])          ? $data['list_taxes_ps']          : array();
        $states_fiken            = isset($data['states_fiken'])           ? $data['states_fiken']           : array();
        $selectedAccounts        = isset($data['selectedAccounts'])       ? $data['selectedAccounts']       : array();
        $selectedPayStatuses     = isset($data['selectedPayStatuses'])    ? $data['selectedPayStatuses']    : array();
        $selectedSalesKind       = isset($data['selectedSalesKind'])      ? $data['selectedSalesKind']      : array();
        $selectedVat             = isset($data['selectedVat'])            ? $data['selectedVat']            : array();
        $orders                  = isset($data['orders'])                 ? $data['orders']                 : array();
        $pagination              = isset($data['pagination'])             ? $data['pagination']             : '';
        $sort                    = isset($data['sort'])                   ? $data['sort']                   : 'id';
        $asc_desc                = isset($data['asc_desc'])               ? $data['asc_desc']               : 'DESC';
        $sort_order              = isset($data['sort_order'])             ? $data['sort_order']             : '';
        $sort_customer           = isset($data['sort_customer'])          ? $data['sort_customer']          : '';
        $sort_status             = isset($data['sort_status'])            ? $data['sort_status']            : '';
        $sort_total              = isset($data['sort_total'])             ? $data['sort_total']             : '';
        $sort_date_add           = isset($data['sort_date_add'])          ? $data['sort_date_add']          : '';
        $sort_date_upd           = isset($data['sort_date_upd'])          ? $data['sort_date_upd']          : '';
        $filter_date_start       = isset($data['filter_date_start'])      ? $data['filter_date_start']      : '';
        $filter_date_end         = isset($data['filter_date_end'])        ? $data['filter_date_end']        : '';
        $filter_order_status_id  = isset($data['filter_order_status_id']) ? $data['filter_order_status_id'] : 0;
        $token_configured        = !empty($data['token_configured']);
        $debug_mode              = !empty($data['debug_mode']);
        ?>

        <div id="fiken-wrapper">
            <div class="alert-info">
                <img class="img-logo" src="<?php echo esc_url($img_dir . 'logo.png'); ?>" alt="Fiken">
                <h2><?php echo esc_html($plugin_name); ?></h2>
            </div>

            <div class="panel">
                <div class="alert alert-danger" <?php echo $error_warning ? 'style="display:block"' : 'style="display:none"'; ?>>
                    <img class="alert_img" src="<?php echo esc_url($img_dir . 'error_16x16.png'); ?>" alt="">
                    <span id="alert-danger"><?php echo esc_html($error_warning); ?></span>
                    <button type="button" class="close">&times;</button>
                </div>
                <div class="alert alert-success" <?php echo $success ? 'style="display:block"' : 'style="display:none"'; ?>>
                    <img class="alert_img" src="<?php echo esc_url($img_dir . 'success_16x16.png'); ?>" alt="">
                    <span id="alert-success"><?php echo esc_html($success); ?></span>
                    <button type="button" class="close">&times;</button>
                </div>
                <div class="alert alert-warning" <?php echo $warning ? 'style="display:block"' : 'style="display:none"'; ?>>
                    <img class="alert_img" src="<?php echo esc_url($img_dir . 'warning_16x16.png'); ?>" alt="">
                    <span id="alert-warning"><?php echo esc_html($warning); ?></span>
                    <button type="button" class="close">&times;</button>
                </div>
            </div>

            <div class="panel">
                <ul class="nav-tabs">
                    <li class="tab-header <?php echo $current_tab === 'tab-orders' ? 'active' : ''; ?>" data-content-tab-id="tab-orders"><a href="javascript:void(0);"><?php esc_html_e('Orders', 'fiken'); ?></a></li>
                    <li class="tab-header <?php echo $current_tab === 'tab-settings' ? 'active' : ''; ?>" data-content-tab-id="tab-settings"><a href="javascript:void(0);"><?php esc_html_e('Settings', 'fiken'); ?></a></li>
                </ul>

                <div class="tab-content">
                    <!-- ORDERS TAB -->
                    <div id="tab-orders" class="tab-pane <?php echo $current_tab === 'tab-orders' ? 'active' : ''; ?>">
                        <form method="post" enctype="multipart/form-data" id="form_order" name="form_order">
                            <?php wp_nonce_field('fiken_orders'); ?>
                            <input type="hidden" name="submitOrders" value="">
                            <input type="hidden" value="fiken-plugin-page" name="page">

                            <div class="panel filtering">
                                <table class="form filtering"><tr>
                                    <td><span><?php esc_html_e('Date Start', 'fiken'); ?></span>
                                        <div class="input-group date">
                                            <input id="filter_date_start" type="text" name="filter_date_start" value="<?php echo esc_attr($filter_date_start); ?>" placeholder="YYYY-MM-DD" data-format="YYYY-MM-DD" class="form-control"/>
                                            <span class="input-group-btn"><img id="img_filter_date_start" class="img-input" src="<?php echo esc_url($img_dir . 'day_32x32.png'); ?>" alt=""></span>
                                        </div>
                                    </td>
                                    <td><span><?php esc_html_e('Date End', 'fiken'); ?></span>
                                        <div class="input-group date">
                                            <input id="filter_date_end" type="text" name="filter_date_end" value="<?php echo esc_attr($filter_date_end); ?>" placeholder="YYYY-MM-DD" data-format="YYYY-MM-DD" class="form-control"/>
                                            <span class="input-group-btn"><img id="img_filter_date_end" class="img-input" src="<?php echo esc_url($img_dir . 'day_32x32.png'); ?>" alt=""></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span><?php esc_html_e('Order Status', 'fiken'); ?></span>
                                        <div class="input-group">
                                            <select id="filter_order_status_id" name="filter_order_status_id" class="form-control">
                                                <option value="0" <?php selected((int) $filter_order_status_id, 0); ?>><?php esc_html_e('All statuses', 'fiken'); ?></option>
                                                <?php foreach ($states_fiken as $status) :
                                                    $style = '';
                                                    switch ((string) $status['id_state']) { case '1': $style = 'color:black'; break; case '2': $style = 'color:green'; break; case '3': $style = 'color:red'; break; } ?>
                                                    <option value="<?php echo esc_attr($status['id_state']); ?>" <?php selected((int) $status['id_state'], (int) $filter_order_status_id); ?> <?php echo $style ? 'style="' . esc_attr($style) . '"' : ''; ?>>
                                                        <?php echo esc_html($status['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                    <td><div class="input-group"><button type="button" id="button-filter" class="cmd-filter"><?php esc_html_e('Filter', 'fiken'); ?></button></div></td>
                                </tr></table>
                            </div>

                            <div class="panel">
                                <table class="table table-order">
                                    <thead><tr>
                                        <td class="text-center"><a href="<?php echo esc_url($sort_order); ?>" <?php echo $sort === 'id' ? 'class="' . esc_attr(strtolower($asc_desc)) . '"' : ''; ?>><?php esc_html_e('Order ID', 'fiken'); ?></a></td>
                                        <td class="text-left"><a href="<?php echo esc_url($sort_customer); ?>" <?php echo $sort === 'customer' ? 'class="' . esc_attr(strtolower($asc_desc)) . '"' : ''; ?>><?php esc_html_e('Customer', 'fiken'); ?></a></td>
                                        <td class="text-left"><a href="<?php echo esc_url($sort_status); ?>" <?php echo $sort === 'current_state' ? 'class="' . esc_attr(strtolower($asc_desc)) . '"' : ''; ?>><?php esc_html_e('Status', 'fiken'); ?></a></td>
                                        <td class="text-right"><a href="<?php echo esc_url($sort_total); ?>" <?php echo $sort === 'total' ? 'class="' . esc_attr(strtolower($asc_desc)) . '"' : ''; ?>><?php esc_html_e('Total', 'fiken'); ?></a></td>
                                        <td class="text-left"><a href="<?php echo esc_url($sort_date_add); ?>" <?php echo $sort === 'date_add' ? 'class="' . esc_attr(strtolower($asc_desc)) . '"' : ''; ?>><?php esc_html_e('Date Added', 'fiken'); ?></a></td>
                                        <td class="text-left"><a href="<?php echo esc_url($sort_date_upd); ?>" <?php echo $sort === 'date_upd' ? 'class="' . esc_attr(strtolower($asc_desc)) . '"' : ''; ?>><?php esc_html_e('Date Modified', 'fiken'); ?></a></td>
                                        <td class="text-right">
                                            <a href="javascript:void(0);" class="img_button ajax-loader" style="display: none"></a>
                                            <a id="reg_order_mass" href="javascript:void(0);" title="<?php esc_attr_e('Transfer selected orders', 'fiken'); ?>" class="img_button transfer"></a>
                                            <a id="sel_order_all" href="javascript:void(0);" data-check="0" title="<?php esc_attr_e('Select all', 'fiken'); ?>" class="img_button check_empty"></a>
                                        </td>
                                    </tr></thead>
                                    <tbody>
                                    <?php if (!empty($orders)) : foreach ($orders as $order) :
                                        $rowStyle = '';
                                        if ((int) $order['current_state'] === 2)      { $rowStyle = 'color:green'; }
                                        elseif ((int) $order['current_state'] === 3)  { $rowStyle = 'color:red'; }
                                        ?>
                                        <tr id="order_id_<?php echo (int) $order['id_order']; ?>" <?php echo $rowStyle ? 'style="' . esc_attr($rowStyle) . '"' : ''; ?>>
                                            <td class="text-center"><a target="_blank" rel="noopener" href="<?php echo esc_url($order['view']); ?>"><?php echo (int) $order['id_order']; ?></a></td>
                                            <td class="text-left"><?php echo esc_html($order['customer']); ?></td>
                                            <td class="text-left txtStatus" data-status-id="<?php echo (int) $order['current_state']; ?>"><?php echo esc_html($order['status_name_fiken']); ?></td>
                                            <td class="text-right"><?php echo wp_kses_post($order['total_paid']); ?></td>
                                            <td class="text-left"><?php echo esc_html($order['date_add']); ?></td>
                                            <td class="text-left"><?php echo esc_html($order['date_upd']); ?></td>
                                            <td class="text-right">
                                                <a data-order-id="<?php echo (int) $order['id_order']; ?>" href="javascript:void(0);" title="<?php esc_attr_e('View statuses history', 'fiken'); ?>" class="img_button view_status_history"></a>
                                                <a data-order-id="<?php echo (int) $order['id_order']; ?>" href="javascript:void(0);" title="<?php esc_attr_e('Transfer', 'fiken'); ?>" class="img_button transfer reg_order"></a>
                                                <a data-order-id="<?php echo (int) $order['id_order']; ?>" data-check="0" href="javascript:void(0);" class="img_button check_empty sel_order"></a>
                                            </td>
                                        </tr>
                                        <tr id="status_history_<?php echo (int) $order['id_order']; ?>" style="display: none" class="status_history">
                                            <td colspan="8" align="right">
                                                <table class="table table-history">
                                                    <thead><tr>
                                                        <td class="text-left"><?php esc_html_e('Status', 'fiken'); ?></td>
                                                        <td class="text-left"><?php esc_html_e('Date Modified', 'fiken'); ?></td>
                                                        <td class="text-left"><?php esc_html_e('Message', 'fiken'); ?></td>
                                                    </tr></thead>
                                                    <tbody></tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    <?php endforeach; else : ?>
                                        <tr><td class="text-center" colspan="7"><?php esc_html_e('No orders!', 'fiken'); ?></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="panel pagination"><?php echo wp_kses_post($pagination); ?></div>
                        </form>
                    </div>

                    <!-- SETTINGS TAB -->
                    <div id="tab-settings" class="tab-pane <?php echo $current_tab === 'tab-settings' ? 'active' : ''; ?>">
                        <form method="post" enctype="multipart/form-data" id="form_settings" name="form_settings">
                            <?php wp_nonce_field('fiken_save_settings'); ?>
                            <input type="hidden" name="submitSettings" value="">
                            <input type="hidden" value="fiken-plugin-page" name="page">

                            <div class="panel">
                                <div class="form-group">
                                    <label class="control-label required" for="fiken_token"><?php esc_html_e('Fiken Personal API Token', 'fiken'); ?></label>
                                    <div class="col-two-thirds">
                                        <input type="password" autocomplete="new-password" placeholder="<?php echo $token_configured ? esc_attr__('Token saved — leave blank to keep current', 'fiken') : esc_attr__('Paste your personal API token here', 'fiken'); ?>" class="form-control" id="fiken_token" name="fiken_token" value=""/>
                                        <p><em><?php esc_html_e('Generate a token in Fiken under: Rediger konto → Sikkerhet → Personlige API-nøkler.', 'fiken'); ?> <?php if ($token_configured) : ?><?php esc_html_e('A token is currently saved.', 'fiken'); ?><?php endif; ?></em></p>
                                    </div>
                                </div>
                                <div class="form-group"><label class="control-label"></label><div class="col-two-thirds"><button type="submit" id="cmdRefresh" name="cmdRefresh" class="cmd-refresh"><?php esc_html_e('Refresh', 'fiken'); ?></button></div></div>
                            </div>

                            <div class="panel">
                                <div class="form-group">
                                    <label class="control-label required" for="fiken_company"><?php esc_html_e('Company', 'fiken'); ?></label>
                                    <div class="col-two-thirds">
                                        <select name="fiken_company" id="fiken_company" class="form-control settings_sel">
                                            <?php if (empty($companies)) : ?>
                                                <option value="" style="display:none;" selected><?php esc_html_e('No companies', 'fiken'); ?></option>
                                            <?php elseif (empty($company_slug)) : ?>
                                                <option value="" style="display:none;" selected><?php esc_html_e('Select company:', 'fiken'); ?></option>
                                            <?php endif; ?>
                                            <?php foreach ($companies as $comp) : ?>
                                                <option value="<?php echo esc_attr($comp->getSlug()); ?>" <?php selected($comp->getSlug(), $company_slug); ?>>
                                                    <?php echo esc_html($comp->getName()); ?><?php if ($comp->getOrganizationNumber()) : ?><?php echo ' (' . esc_html($comp->getOrganizationNumber()) . ')'; ?><?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="panel">
                                <div class="form-group">
                                    <label class="control-label required"><?php esc_html_e('Accounts', 'fiken'); ?></label>
                                    <div class="col-two-thirds">
                                        <table class="table table-pay">
                                            <thead><tr><td class="text-left"><?php esc_html_e('Payment method', 'fiken'); ?></td><td class="text-left"><?php esc_html_e('Account', 'fiken'); ?></td><td class="text-left"><?php esc_html_e('Order Status', 'fiken'); ?></td><td class="text-left"><?php esc_html_e('Fiken Sale Type', 'fiken'); ?></td></tr></thead>
                                            <tbody>
                                            <?php if (!empty($list_pay)) : foreach ($list_pay as $pay) :
                                                $fiken_acc_key       = 'fiken_account_'    . $pay['id'];
                                                $fiken_pay_status_key = 'fiken_pay_status_' . $pay['id'];
                                                $fiken_sale_kind_key = 'fiken_sale_kind_'  . $pay['id'];
                                                $sel_account         = isset($selectedAccounts[$fiken_acc_key])        ? $selectedAccounts[$fiken_acc_key]        : '';
                                                $sel_pay_status      = isset($selectedPayStatuses[$fiken_pay_status_key]) ? $selectedPayStatuses[$fiken_pay_status_key] : '';
                                                $sel_sale_kind       = isset($selectedSalesKind[$fiken_sale_kind_key])   ? $selectedSalesKind[$fiken_sale_kind_key]   : '';
                                                ?>
                                                <tr>
                                                    <td class="text-left"><?php echo esc_html(ucfirst($pay['name'])); ?></td>
                                                    <td class="text-left">
                                                        <select name="<?php echo esc_attr($fiken_acc_key); ?>" id="<?php echo esc_attr($fiken_acc_key); ?>" class="form-control settings_sel fiken_account">
                                                            <?php if (empty($accounts)) : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('No accounts', 'fiken'); ?></option>
                                                            <?php elseif ($sel_account === '') : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('Select account:', 'fiken'); ?></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($accounts as $acc) :
                                                                $hidden = ($acc->getCode() === '0' && ($sel_sale_kind === '' || $sel_sale_kind === FikenUtils::CASH_SALE)); ?>
                                                                <option value="<?php echo esc_attr($acc->getCode()); ?>" <?php selected($sel_account, $acc->getCode()); ?> <?php echo $hidden ? 'style="display:none"' : ''; ?>>
                                                                    <?php echo esc_html($acc->getCode() . ' | ' . $acc->getName()); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td class="text-left">
                                                        <select name="<?php echo esc_attr($fiken_pay_status_key); ?>" id="<?php echo esc_attr($fiken_pay_status_key); ?>" class="form-control settings_sel">
                                                            <?php if (empty($list_states_wc)) : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('No statuses', 'fiken'); ?></option>
                                                            <?php elseif ($sel_pay_status === '') : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('Select order status:', 'fiken'); ?></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($list_states_wc as $pay_st) : ?>
                                                                <option value="<?php echo esc_attr($pay_st['id']); ?>" <?php selected($sel_pay_status, $pay_st['id']); ?>><?php echo esc_html($pay_st['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td class="text-left">
                                                        <select name="<?php echo esc_attr($fiken_sale_kind_key); ?>" id="<?php echo esc_attr($fiken_sale_kind_key); ?>" class="form-control settings_sel fiken_sale_kind">
                                                            <?php if (empty($list_sales_kind_fiken)) : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('No sale types', 'fiken'); ?></option>
                                                            <?php elseif ($sel_sale_kind === '') : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('Select sale type:', 'fiken'); ?></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($list_sales_kind_fiken as $s_k) : ?>
                                                                <option value="<?php echo esc_attr($s_k['code']); ?>" <?php selected($sel_sale_kind, $s_k['code']); ?>><?php echo esc_html($s_k['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; else : ?>
                                                <tr><td class="text-center" colspan="4"><?php esc_html_e('No payment plugins installed', 'fiken'); ?></td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="panel">
                                <div class="form-group">
                                    <label class="control-label required"><?php esc_html_e('VAT Mapping', 'fiken'); ?></label>
                                    <div class="col-two-thirds">
                                        <table class="table table-tax">
                                            <thead><tr><td class="text-left"><?php esc_html_e('Tax Class', 'fiken'); ?></td><td class="text-left"><?php esc_html_e('Fiken VAT Type', 'fiken'); ?></td></tr></thead>
                                            <tbody>
                                            <?php if (!empty($list_taxes_ps)) : foreach ($list_taxes_ps as $tax) :
                                                $fiken_vat_key = 'fiken_vat_' . $tax['id'];
                                                $sel_vat       = isset($selectedVat[$fiken_vat_key]) ? $selectedVat[$fiken_vat_key] : '';
                                                ?>
                                                <tr>
                                                    <td class="text-left"><?php echo esc_html($tax['name']); ?></td>
                                                    <td class="text-left">
                                                        <select name="<?php echo esc_attr($fiken_vat_key); ?>" id="<?php echo esc_attr($fiken_vat_key); ?>" class="form-control settings_sel">
                                                            <?php if (empty($list_vat_types_fiken)) : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('No Fiken VAT codes', 'fiken'); ?></option>
                                                            <?php elseif ($sel_vat === '') : ?>
                                                                <option value="" style="display:none;" selected><?php esc_html_e('Select VAT code:', 'fiken'); ?></option>
                                                            <?php endif; ?>
                                                            <?php foreach ($list_vat_types_fiken as $vat) : ?>
                                                                <option value="<?php echo esc_attr($vat['code']); ?>" <?php selected($sel_vat, $vat['code']); ?>><?php echo esc_html($vat['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; else : ?>
                                                <tr><td class="text-center" colspan="2"><?php esc_html_e('No taxes!', 'fiken'); ?></td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="panel">
                                <div class="form-group">
                                    <label class="control-label" for="fiken_debug_mode"><?php esc_html_e('Debug mode', 'fiken'); ?></label>
                                    <div class="col-two-thirds text-center">
                                        <select name="fiken_debug_mode" id="fiken_debug_mode" class="form-control settings_sel">
                                            <option value="0" <?php selected($debug_mode, false); ?>><?php esc_html_e('Off', 'fiken'); ?></option>
                                            <option value="1" <?php selected($debug_mode, true); ?>><?php esc_html_e('On', 'fiken'); ?></option>
                                        </select>
                                        <span><em><?php esc_html_e('Please keep it turned off unless troubleshooting — it produces a large log file.', 'fiken'); ?></em></span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" id="cmdSave" name="cmdSave" class="cmd-save"><?php esc_html_e('Save', 'fiken'); ?></button>
                        </form>
                    </div>
                </div><!-- .tab-content -->
            </div><!-- .panel -->
        </div><!-- #fiken-wrapper -->
        <?php
    }
}
