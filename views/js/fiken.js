/**
 * Fiken admin JS.
 * Uses the `fiken` global provided by wp_localize_script:
 *   fiken.ajax_url, fiken.ajax_nonce, fiken.base_url, fiken.i18n.*
 */
jQuery(function ($) {

    var i18n = (fiken && fiken.i18n) || {};

    $('#filter_date_start').datepicker({ dateFormat: 'yy-mm-dd' });
    $('#filter_date_end').datepicker({ dateFormat: 'yy-mm-dd' });

    $('#img_filter_date_start').on('click', function () { $('#filter_date_start').datepicker('show'); });
    $('#img_filter_date_end').on('click', function ()   { $('#filter_date_end').datepicker('show'); });

    $('.close').on('click', function () { $(this).parent('div').hide(); });

    $('.tab-header').on('click', function () {
        var tabId = $(this).data('content-tab-id');
        $('.tab-header').removeClass('active');
        $('.tab-pane').removeClass('active');
        $(this).addClass('active');
        $(document.getElementById(tabId)).addClass('active');
    });

    $('#fiken_company').on('change', function () { $('#form_settings').trigger('submit'); });
    $('#cmdRefresh').on('click', function ()     { $('#form_settings').trigger('submit'); });
    $('.settings_sel').on('click', function ()   { $(this).css('color', '#555555'); });
    $('.settings_sel').on('focusout', setSelectColor);
    $('.fiken_sale_kind').on('change', function () { setEmptyAccount($(this)); });

    $('.reg_order').on('click', function () {
        var orderId = $(this).data('order-id');
        var $statusCell = $('#order_id_' + orderId + ' .txtStatus');
        if (String($statusCell.data('status-id')) === '2') {
            var msg = (i18n.confirm_reg_if_transferred || 'The order marked as %s. Are you sure?')
                        .replace('%s', $statusCell.text());
            if (window.confirm(msg)) { regOrder(orderId); }
        } else {
            regOrder(orderId);
        }
    });

    $('.view_status_history').on('click', function () {
        var orderId = $(this).data('order-id');
        if ($('tr#status_history_' + orderId + ':visible').length) {
            $('tr#status_history_' + orderId).fadeOut('slow');
        } else {
            viewStatusHistory(orderId);
        }
    });

    $('#sel_order_all').on('click', function () {
        markControl($(this), -1);
        markControlMass(parseInt($(this).data('check'), 10));
    });
    $('.sel_order').on('click', function () { markControl($(this), -1); });
    $('#reg_order_mass').on('click', function () { regOrderMass(); });
    $('#button-filter').on('click', function () { filter(); });

    setSelectColor();

    function setEmptyAccount($ctrlCurrentSaleKind) {
        var $ctrlSelectAccount = $ctrlCurrentSaleKind.closest('tr').find('.fiken_account');
        if ($ctrlCurrentSaleKind.val() === 'EXTERNAL_INVOICE') {
            $ctrlSelectAccount.find('option[value="0"]').show();
        } else {
            if ($ctrlSelectAccount.find('option:selected').val() === '0') {
                var label = (i18n.select_account || 'Select account:');
                $ctrlSelectAccount.prepend(
                    $('<option>', { value: '', selected: 'selected' })
                        .css('display', 'none')
                        .text(label)
                );
            }
            $ctrlSelectAccount.find('option[value="0"]').hide();
        }
        setSelectColor();
    }

    function setSelectColor() {
        $('.settings_sel').each(function () {
            if ($(this).val() === '') { $(this).css('color', 'red'); }
            else                        { $(this).css('color', 'inherit'); }
        });
    }

    function showAlert(type, mes) {
        $('.alert-danger').hide('fast');
        $('.alert-success').hide('fast');
        if (type === 0)      { $('#alert-success').text(mes); $('.alert-success').show('slow'); }
        else if (type === 1) { $('#alert-danger').text(mes);  $('.alert-danger').show('slow'); }
    }

    function markControlMass(type) {
        $('.sel_order').each(function () { markControl($(this), type); });
    }

    function markControl($ctrl, type) {
        switch (type) {
            case 0:
                $ctrl.data('check', '0').removeClass('check').addClass('check_empty');
                $('#order_id_' + $ctrl.data('order-id')).css('background-color', '');
                break;
            case 1:
                $ctrl.data('check', '1').removeClass('check_empty').addClass('check');
                $('#order_id_' + $ctrl.data('order-id')).css('background-color', '#E6FFE6');
                break;
            case -1:
                markControl($ctrl, String($ctrl.data('check')) === '0' ? 1 : 0);
                break;
        }
    }

    function filter() {
        var params = { page: 'fiken-plugin-page' };
        var s = $('#filter_date_start').val();
        var e = $('#filter_date_end').val();
        var st = $('#filter_order_status_id').val();
        if (s)  { params.filter_date_start      = s; }
        if (e)  { params.filter_date_end        = e; }
        if (st) { params.filter_order_status_id = st; }

        var base = (fiken && fiken.base_url) ? fiken.base_url : window.location.href;
        var i = base.indexOf('?');
        var origin = i >= 0 ? base.substring(0, i) : base;
        window.location.assign(origin + '?' + $.param(params));
    }

    function regOrder(orderId) {
        $('.alert-danger').hide('fast');
        $('.alert-success').hide('fast');
        var $btn = $('#order_id_' + orderId + ' .reg_order');

        return $.ajax({
            url: fiken.ajax_url, type: 'POST', dataType: 'json', cache: false,
            data: { action: 'fiken_register_sale', order_id: orderId, _ajax_nonce: fiken.ajax_nonce },
            beforeSend: function () { $btn.addClass('ajax-loader').prop('disabled', true); },
            complete:   function () { $btn.removeClass('ajax-loader').prop('disabled', false); }
        }).done(function (data) {
            if (!data) { window.alert('TECHNICAL ERROR: no data'); return; }
            if (data.error)        { showAlert(1, data.error); }
            else if (data.success) { showAlert(0, data.success); }
            applyOrderState(orderId, data);
        }).fail(function (xhr) {
            window.alert('TECHNICAL ERROR: ' + (xhr.statusText || 'request failed'));
        });
    }

    function regOrderMass() {
        $('.alert-danger').hide('fast');
        $('.alert-success').hide('fast');

        var ids = [];
        $('.sel_order').each(function () {
            if (String($(this).data('check')) === '1') { ids.push($(this).data('order-id')); }
        });

        if (!ids.length) {
            showAlert(1, i18n.err_nothing_selected || 'Nothing selected!');
            return;
        }

        var errors = [];
        $('.ajax-loader').show();

        // Sequential Promise chain (no async:false). Each request waits for the previous.
        var chain = $.Deferred().resolve().promise();
        ids.forEach(function (orderId) {
            chain = chain.then(function () {
                return regOrderSilent(orderId).catch(function (errMsg) {
                    errors.push('ID: ' + orderId + ' : ' + errMsg);
                });
            });
        });
        chain.always(function () {
            $('.ajax-loader').hide();
            if (errors.length) { showAlert(1, errors.join(' | ')); }
            else               { showAlert(0, i18n.order_register_success_mass || 'Transfer selected orders successfully completed'); }
        });
    }

    function regOrderSilent(orderId) {
        var $btn = $('#order_id_' + orderId + ' .reg_order');
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: fiken.ajax_url, type: 'POST', dataType: 'json', cache: false,
                data: { action: 'fiken_register_sale', order_id: orderId, _ajax_nonce: fiken.ajax_nonce },
                beforeSend: function () { $btn.addClass('ajax-loader').prop('disabled', true); },
                complete:   function () { $btn.removeClass('ajax-loader').prop('disabled', false); }
            }).done(function (data) {
                if (!data) { reject('TECHNICAL ERROR: no data'); return; }
                applyOrderState(orderId, data);
                if (data.error) { reject(data.error); }
                else            { resolve(data); }
            }).fail(function (xhr) {
                reject('TECHNICAL ERROR: ' + (xhr.statusText || 'request failed'));
            });
        });
    }

    function applyOrderState(orderId, data) {
        if (!data || !data.order) { return; }
        var $status = $('#order_id_' + orderId + ' .txtStatus');
        $status.text(data.order.stateName || '');
        $status.data('status-id', data.order.currentState);
        var color = '';
        switch (String(data.order.currentState)) {
            case '2': color = 'green'; break;
            case '3': color = 'red';   break;
        }
        $('#order_id_' + orderId).css('color', color);
    }

    function viewStatusHistory(orderId) {
        var $btn = $('#order_id_' + orderId + ' .view_status_history');
        $.ajax({
            url: fiken.ajax_url, type: 'POST', dataType: 'json', cache: false,
            data: { action: 'fiken_get_order_status_history', order_id: orderId, _ajax_nonce: fiken.ajax_nonce },
            beforeSend: function () { $btn.addClass('ajax-loader').prop('disabled', true); },
            complete:   function () { $btn.removeClass('ajax-loader').prop('disabled', false); }
        }).done(function (data) {
            if (!data) { window.alert('TECHNICAL ERROR: no data'); return; }
            if (data.error) { showAlert(1, data.error); return; }
            var $tbody = $('tr#status_history_' + orderId).find('table > tbody');
            $tbody.empty();
            if (data.length) {
                data.forEach(function (item) {
                    var $tr = $('<tr>');
                    if (String(item.currentState) === '2') { $tr.css('color', 'green'); }
                    if (String(item.currentState) === '3') { $tr.css('color', 'red'); }
                    $tr.append($('<td class="text-left">').text(item.stateName || ''));
                    $tr.append($('<td class="text-left">').text(item.lastUpdate || ''));
                    $tr.append($('<td class="text-left">').text(item.mes || ''));
                    $tbody.append($tr);
                });
                $('tr#status_history_' + orderId).fadeIn('slow');
            }
        }).fail(function (xhr) {
            window.alert('TECHNICAL ERROR: ' + (xhr.statusText || 'request failed'));
        });
    }
});
