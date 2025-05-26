/**
 * JavaScript для страницы персонажа (/my-account/character)
 * Обрабатывает активацию/деактивацию купонов и способностей.
 * Файл: assets/js/rpg-account.js
 *
 * Изменения:
 * - AJAX action 'use_coupon' изменен на 'use_rpg_coupon'.
 * - AJAX вызовы для Dokan купонов переведены с fetch на jQuery.ajax для использования nonce
 * и существующих PHP обработчиков (DokanAJAXHandler).
 * - Уточнены имена AJAX actions для Dokan купонов.
 * - Используются локализованные переменные из rpg_settings.
 */
jQuery(document).ready(function($) {

    // Глобальные настройки из wp_localize_script (переменная rpg_settings)
    const settings = typeof rpg_settings !== 'undefined' ? rpg_settings : {};
    const ajaxUrl = settings.ajax_url || '/wp-admin/admin-ajax.php';
    // Nonce для большинства RPG действий на странице аккаунта
    const nonce = settings.nonce || ''; // Должен быть 'rpg_ajax_nonce'

    // Текстовые строки для сообщений
    const textStrings = settings.text || {};
    const errorNetwork = textStrings.error_network || 'Ошибка сети. Пожалуйста, попробуйте еще раз.';
    const errorGeneric = textStrings.error_generic || 'Произошла непредвиденная ошибка.';
    const abilityActivatedText = textStrings.ability_activated || 'Способность активирована!';
    const abilitySelectItemText = textStrings.ability_select_item || 'Выберите товар(ы) в корзине для применения способности.';
    const abilityAlreadyUsedText = textStrings.ability_already_used || 'Способность уже активирована на этой неделе.';
    const abilityLevelLowText = textStrings.ability_level_low || 'Способность доступна с более высокого уровня.';
    const confirmDeactivateText = textStrings.confirm_deactivate || 'Вы уверены, что хотите деактивировать этот купон?';


    // Функция для отображения сообщений
    function showGlobalMessage(message, type = 'info') { // type: 'info', 'success', 'error'
        const $messageBox = $('.rpg-message-box.глобальное-сообщение');
        if ($messageBox.length === 0) {
            console.error("Global message box not found. Message:", message);
            alert(message); // Fallback
            return;
        }
        $messageBox.html(message)
            .removeClass('success error info')
            .addClass(type)
            .stop(true, true)
            .slideDown(300);

        if (type !== 'info') {
            setTimeout(function() { $messageBox.slideUp(300); }, 5000);
        }
    }
    function showSpecificMessage(selector, message, type = 'info') {
        const $messageBox = $(selector);
        if ($messageBox.length === 0) {
            showGlobalMessage(message, type); // Fallback to global if specific not found
            return;
        }
         $messageBox.html(message)
            .removeClass('success error info')
            .addClass(type)
            .stop(true, true)
            .slideDown(300);
        if (type !== 'info') {
            setTimeout(function() { $messageBox.slideUp(300); }, 5000);
        }
    }


    // Активация RPG купонов
    $('.activate-rpg-coupon').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const index = $button.data('index');

        if (typeof index === 'undefined') {
            showSpecificMessage('.rpg-message-box.rpg-coupons-msg', errorGeneric, 'error');
            return;
        }
        $button.prop('disabled', true).text('Активация...');
        showSpecificMessage('.rpg-message-box.rpg-coupons-msg', 'Активация купона...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'use_rpg_coupon', // ИЗМЕНЕНО с 'use_coupon'
                index: index,
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showSpecificMessage('.rpg-message-box.rpg-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Активировать');
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.rpg-coupons-msg', errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $button.prop('disabled', false).text('Активировать');
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.rpg-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text('Активировать');
            }
        });
    });

    // Активация "Чутья" (Эльфы) - первая стадия
    $('#activate-elf-sense').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        $button.prop('disabled', true).text('Активация...');
        showGlobalMessage('Активация "Чутья"...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'activate_elf_sense', // PHP action для установки pending флага
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showGlobalMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        // Можно не перезагружать, а изменить UI, если это только установка флага
                        // Например, изменить текст кнопки или показать сообщение о выборе в корзине.
                        // Если PHP возвращает сообщение о необходимости выбора в корзине, это хорошо.
                        $button.text(abilitySelectItemText).prop('disabled', true); // Обновляем текст и оставляем неактивной
                         // Или перезагружаем, если так проще:
                         setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $button.prop('disabled', false).text('Активировать "Чутье"');
                    }
                } else {
                    showGlobalMessage(errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $button.prop('disabled', false).text('Активировать "Чутье"');
                }
            },
            error: function() {
                showGlobalMessage(errorNetwork, 'error');
                $button.prop('disabled', false).text('Активировать "Чутье"');
            }
        });
    });

    // Активация "Ярости" (Орки) - первая стадия
    $('#activate-orc-rage').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        $button.prop('disabled', true).text('Активация...');
        showGlobalMessage('Активация "Ярости"...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'activate_orc_rage', // PHP action для установки pending флага
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showGlobalMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        $button.text(abilitySelectItemText).prop('disabled', true);
                        // Или перезагружаем:
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $button.prop('disabled', false).text('Активировать "Ярость"');
                    }
                } else {
                    showGlobalMessage(errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $button.prop('disabled', false).text('Активировать "Ярость"');
                }
            },
            error: function() {
                showGlobalMessage(errorNetwork, 'error');
                $button.prop('disabled', false).text('Активировать "Ярость"');
            }
        });
    });

    // --- DOKAN Купоны Продавцов (используем jQuery.ajax и admin-ajax.php) ---

    // Взять купон Dokan (со страницы магазина, если этот скрипт там тоже используется)
    // Если эта кнопка только на странице аккаунта, то $(document).on(...) может быть избыточен.
    // Но для динамически добавляемых элементов это хорошая практика.
    $(document).on('click', '.rpg-take-dokan-coupon-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const couponId = $button.data('coupon-id');
        if (!couponId) {
            console.error('RPG Dokan: Coupon ID not found for "take" button.');
            return;
        }
        $button.prop('disabled', true).text('Обработка...');
        let $msgBox = $button.siblings('.rpg-dokan-coupon-message');
        if ($msgBox.length === 0) {
            $button.after('<div class="rpg-dokan-coupon-message" style="font-size:0.9em; margin-top:5px; display:none;"></div>');
            $msgBox = $button.siblings('.rpg-dokan-coupon-message');
        }
        $msgBox.hide().removeClass('success error info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_take_dokan_coupon', // Наш PHP action
                coupon_id: couponId,
                _ajax_nonce: nonce // Общий nonce для страницы аккаунта
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    $msgBox.text(response.data?.message || errorGeneric)
                           .addClass(response.success ? 'success' : 'error').slideDown();
                    if (response.success) {
                        $button.text('Уже в инвентаре'); // Кнопка останется неактивной
                    } else {
                        $button.prop('disabled', false).text('Взять купон');
                    }
                } else {
                    $msgBox.text(errorGeneric + ' (Неверный ответ)').addClass('error').slideDown();
                    $button.prop('disabled', false).text('Взять купон');
                }
            },
            error: function() {
                $msgBox.text(errorNetwork).addClass('error').slideDown();
                $button.prop('disabled', false).text('Взять купон');
            }
        });
    });

    // Добавить купон Dokan по коду
    $('#rpg-add-dokan-coupon-by-code-btn').on('click', function() {
        const $button = $(this);
        const couponCode = $('#dokan-coupon-code-input').val().trim();
        if (!couponCode) {
            showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Пожалуйста, введите код купона.', 'error');
            return;
        }
        $button.prop('disabled', true).text('Добавление...');
        showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Добавление купона...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_add_dokan_coupon_by_code', // Наш PHP action
                coupon_code: couponCode,
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        $('#dokan-coupon-code-input').val('');
                        // Перезагрузка страницы для обновления списка купонов Dokan
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Добавить в инвентарь');
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text('Добавить в инвентарь');
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text('Добавить в инвентарь');
            }
        });
    });

    // Активировать купон Dokan из инвентаря
    $(document).on('click', '.activate-dokan-coupon', function() {
        const $button = $(this);
        const couponId = $button.closest('.dokan-coupon-item').data('coupon-id');
        if (typeof couponId === 'undefined' || !couponId) {
            showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Не удалось определить ID купона для активации.', 'error');
            return;
        }
        const originalButtonText = $button.data('original-text') || 'Активировать';
        $button.prop('disabled', true).text('Активация...');
        showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Активация купона продавца...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_activate_dokan_coupon_from_inventory', // Наш PHP action
                dokan_coupon_id_to_activate: couponId,
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text(originalButtonText);
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text(originalButtonText);
            }
        });
    });

    // Обновить статус купонов Dokan
    $('#rpg-refresh-dokan-coupons-status').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Обновление...');
        showSpecificMessage('.rpg-message-box.dokan-coupons-msg', 'Проверка статуса купонов продавцов...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'rpg_refresh_vendor_coupons_status', // Наш PHP action
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object' && typeof response.success !== 'undefined') {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success && response.data?.reload_page) {
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $button.prop('disabled', false).text('Обновить статус списка');
                    }
                } else {
                    showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text('Обновить статус списка');
                }
            },
            error: function() {
                showSpecificMessage('.rpg-message-box.dokan-coupons-msg', errorNetwork, 'error');
                $button.prop('disabled', false).text('Обновить статус списка');
            }
        });
    });

    // Деактивация активного купона (RPG или Dokan)
    $(document).on('click', '.rpg-deactivate-coupon-btn', function() {
        const $button = $(this);
        const couponType = $button.data('coupon-type'); // 'rpg_item', 'rpg_cart', 'dokan_vendor'

        if (!couponType) {
            showGlobalMessage('Не удалось определить тип купона для деактивации.', 'error');
            return;
        }
        if (!confirm(confirmDeactivateText)) { // Используем локализованный текст
            return;
        }

        $button.prop('disabled', true).text('Деактивация...');
        showGlobalMessage('Деактивация купона...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'deactivate_rpg_coupon', // Наш PHP action (был rpg_deactivate_coupon)
                coupon_type: couponType,
                _ajax_nonce: nonce
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showGlobalMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Вернуть в инвентарь');
                    }
                } else {
                    showGlobalMessage(errorGeneric + ' (Неверный ответ)', 'error');
                    $button.prop('disabled', false).text('Вернуть в инвентарь');
                }
            },
            error: function() {
                showGlobalMessage(errorNetwork, 'error');
                $button.prop('disabled', false).text('Вернуть в инвентарь');
            }
        });
    });
});
