/**
 * JavaScript для страницы корзины (/cart)
 * Обрабатывает выбор товаров для способностей "Чутье" Эльфов и "Ярость" Орков.
 * Файл: assets/js/rpg-cart.js
 *
 * Изменения:
 * - Используются локализованные переменные из rpg_cart_settings.
 * - AJAX actions уже должны быть правильными, но проверим.
 */
jQuery(document).ready(function($) {

    // Глобальные настройки из wp_localize_script (переменная rpg_cart_settings)
    const settings = typeof rpg_cart_settings !== 'undefined' ? rpg_cart_settings : {};
    const ajaxUrl = settings.ajax_url || '/wp-admin/admin-ajax.php';
    // Nonce для действий в корзине
    const cartNonce = settings.cart_nonce || ''; // Должен быть 'rpg_cart_ajax_nonce'
    const race = settings.race || ''; // Раса текущего пользователя

    // Текстовые строки
    const textStrings = settings.text || {};
    const errorNetwork = textStrings.error_network || 'Ошибка сети. Пожалуйста, попробуйте еще раз.';
    const errorGeneric = textStrings.error_generic || 'Произошла непредвиденная ошибка.';
    const errorElfSelect = textStrings.confirm_elf_select || 'Выберите хотя бы один товар.';


    // Функция для отображения сообщений в корзине (в .message-box)
    function showCartMessage(message, type = 'info') { // type: 'info', 'success', 'error'
        const $messageBox = $('.woocommerce-cart-form .message-box'); // Ищем внутри формы корзины
        if ($messageBox.length === 0) {
            // Если нет специального .message-box, можно использовать стандартные уведомления WooCommerce
            // или добавить .message-box динамически.
            // Пока выведем в консоль и alert
            console.error("Cart message box not found. Message:", message);
            alert(message);
            return;
        }
        $messageBox.html(message)
            .removeClass('success error info woocommerce-message woocommerce-error woocommerce-info') // Удаляем все классы
            .addClass(type) // Добавляем наш тип
            .addClass(type === 'success' ? 'woocommerce-message' : (type === 'error' ? 'woocommerce-error' : 'woocommerce-info')) // Добавляем классы WC
            .stop(true, true)
            .slideDown(300);

        if (type !== 'info') {
            setTimeout(function() { $messageBox.slideUp(300); }, 5000);
        }
    }

    // Подтверждение выбора для "Чутья" (Эльфы)
    $('#confirm-elf-sense').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $form = $('#elf-sense-form'); // Форма выбора товаров для "Чутья"
        if ($form.length === 0) {
            showCartMessage('Ошибка: Форма выбора для "Чутья" не найдена.', 'error');
            return;
        }
        const $checkedCheckboxes = $form.find('input[name="elf_products[]"]:checked');
        const productIds = $checkedCheckboxes.map(function() {
            return $(this).val();
        }).get();

        if (productIds.length === 0) {
            showCartMessage(errorElfSelect, 'error');
            return;
        }

        // Проверка лимита на количество выбранных товаров (если elf_sense_max_items передается через rpg_cart_settings)
        const maxItems = settings.elf_sense_max_items || 1; // Значение по умолчанию, если не передано
        if (productIds.length > maxItems) {
            showCartMessage( (settings.text?.error_elf_max_items || 'Превышен лимит товаров для "Чутья" (максимум %d).').replace('%d', maxItems) , 'error');
            return;
        }


        $button.prop('disabled', true).text('Подтверждение...');
        $checkedCheckboxes.prop('disabled', true);
        showCartMessage('Подтверждение выбора для "Чутья"...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'select_elf_items', // PHP action
                product_ids: productIds,
                _ajax_nonce: cartNonce // Используем nonce для корзины
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showCartMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        // Перезагружаем страницу, чтобы применилась скидка и форма выбора исчезла
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Подтвердить выбор для "Чутья"');
                        $checkedCheckboxes.prop('disabled', false);
                    }
                } else {
                    showCartMessage(errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $button.prop('disabled', false).text('Подтвердить выбор для "Чутья"');
                    $checkedCheckboxes.prop('disabled', false);
                }
            },
            error: function() {
                showCartMessage(errorNetwork, 'error');
                $button.prop('disabled', false).text('Подтвердить выбор для "Чутья"');
                $checkedCheckboxes.prop('disabled', false);
            }
        });
    });

    // Выбор товара для "Ярости" (Орки)
    // Используем делегирование событий, если кнопки добавляются динамически или их много
    $(document).on('click', '.select-rage-product', function(e) {
        e.preventDefault();
        const $button = $(this);
        const productId = $button.data('product-id');

        if (!productId) {
            showCartMessage(errorGeneric + ' (ID товара не найден)', 'error');
            return;
        }

        // Блокируем ВСЕ кнопки выбора "Ярости", чтобы предотвратить множественные клики
        $('.select-rage-product').prop('disabled', true).text('Выбор...');
        $button.text('Выбран!'); // Помечаем нажатую кнопку
        showCartMessage('Выбор товара для "Ярости"...', 'info');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'select_orc_rage_product', // PHP action
                product_id: productId,
                _ajax_nonce: cartNonce // Используем nonce для корзины
            },
            success: function(response) {
                if (response && typeof response === 'object') {
                    showCartMessage(response.data?.message || errorGeneric, response.success ? 'success' : 'error');
                    if (response.success) {
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        // Разблокируем все кнопки при ошибке
                        $('.select-rage-product').prop('disabled', false).text('Выбрать для "Ярости"');
                    }
                } else {
                    showCartMessage(errorGeneric + ' (Неверный ответ сервера)', 'error');
                    $('.select-rage-product').prop('disabled', false).text('Выбрать для "Ярости"');
                }
            },
            error: function() {
                showCartMessage(errorNetwork, 'error');
                $('.select-rage-product').prop('disabled', false).text('Выбрать для "Ярости"');
            }
        });
    });
});
