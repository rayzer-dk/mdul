<?php
$_['heading_title'] = '<span style="color:#0057b7;font-weight:700;">Supplier Sync Parser</span> <span style="display:inline-block;background:#ffd700;color:#0057b7;border-radius:6px;padding:2px 10px;font-size:12px;line-height:1.2;vertical-align:middle;font-weight:700;margin-left:6px;">PRO</span>';
$_['heading_title_text'] = 'Supplier Sync Parser';
$_['text_extension'] = 'Расширения';
$_['text_home'] = 'Главная';
$_['text_edit'] = 'Настройки модуля';
$_['text_enabled'] = 'Включено';
$_['text_disabled'] = 'Выключено';
$_['text_select'] = 'Выберите';
$_['text_none'] = 'Нет данных';
$_['text_yes'] = 'Да';
$_['text_no'] = 'Нет';
$_['text_status'] = 'Статус';
$_['text_author'] = 'Автор';
$_['text_compatibility'] = 'Совместимость';
$_['text_existing'] = 'Существующий';
$_['text_new'] = 'Новый';
$_['text_excluded'] = 'Исключен';
$_['text_manual_mode'] = 'По умолчанию изменения применяются после выбора строк предпросмотра. Для связанных товаров можно отдельно включить автоматическое обновление цены и наличия через cron. Новые товары создаются вручную.';
$_['text_warning_backup'] = 'Перед установкой и запуском синхронизации обязательно сделайте полный backup файлов сайта и базы данных. Модуль может массово изменять цены, наличие и создавать новые товары.';
$_['text_cron_hint'] = 'Для Mirohost ставьте cron не чаще одного раза в 5 минут. Cron обрабатывает очередь пакетами и не должен запускать весь каталог одним процессом.';
$_['text_success_settings'] = 'Настройки модуля сохранены.';
$_['text_success_supplier'] = 'Поставщик сохранен.';
$_['text_success_queue_clear'] = 'Очередь очищена.';
$_['text_success_reviews_clear'] = 'Предпросмотр очищен.';
$_['text_success_logs_clear'] = 'Логи очищены.';
$_['text_success_processing_reset'] = 'Зависшие задачи processing возвращены в pending.';
$_['tab_suppliers'] = 'Поставщики';
$_['tab_rules'] = 'Правила парсинга';
$_['tab_mapping'] = 'Сопоставление';
$_['tab_review'] = 'Предпросмотр';
$_['tab_queue'] = 'Очередь';
$_['tab_new'] = 'Новые товары';
$_['tab_logs'] = 'Логи';
$_['tab_diagnostics'] = 'Диагностика';
$_['tab_settings'] = 'Настройки';
$_['tab_about'] = 'О модуле';
$_['button_save'] = 'Сохранить';
$_['button_cancel'] = 'Отмена';
$_['button_add_supplier'] = 'Добавить поставщика';
$_['button_test'] = 'Проверить';
$_['button_run_queue'] = 'Запустить пакет';
$_['button_clear_queue'] = 'Очистить очередь';
$_['button_reset_processing'] = 'Сбросить processing';
$_['button_clear_logs'] = 'Очистить логи';
$_['button_apply_price'] = 'Обновить цену выбранных';
$_['button_apply_stock'] = 'Обновить наличие выбранных';
$_['button_apply_price_stock'] = 'Обновить цену и наличие';
$_['button_create_selected'] = 'Перенести выбранные в новые товары';
$_['button_skip_selected'] = 'Пропустить выбранные';
$_['button_clear_reviews'] = 'Очистить предпросмотр';
$_['entry_module_status'] = 'Статус модуля';
$_['entry_cron_token'] = 'Cron token';
$_['entry_cron_endpoint'] = 'Cron endpoint';
$_['entry_cron_command'] = 'Cron-команда';
$_['text_copy_cron'] = 'Скопировать cron-команду';
$_['entry_batch_limit'] = 'Лимит пакета';
$_['entry_supplier'] = 'Поставщик';
$_['entry_supplier_name'] = 'Название поставщика';
$_['entry_supplier_status'] = 'Статус поставщика';
$_['entry_base_url'] = 'Базовый URL';
$_['entry_list_urls'] = 'Страницы категорий или товаров';
$_['entry_product_url_xpath'] = 'XPath ссылок товаров в категории';
$_['entry_product_url_attr'] = 'Атрибут ссылки';
$_['entry_next_page_xpath'] = 'XPath следующей страницы';
$_['entry_model_xpath'] = 'XPath кода товара / model';
$_['entry_sku_xpath'] = 'XPath артикула/SKU';
$_['entry_name_xpath'] = 'XPath названия';
$_['entry_price_xpath'] = 'XPath цены';
$_['entry_stock_xpath'] = 'XPath наличия';
$_['entry_category_xpath'] = 'XPath категории/хлебных крошек поставщика';
$_['entry_description_xpath'] = 'XPath описания';
$_['entry_manufacturer_xpath'] = 'XPath производителя';
$_['entry_image_xpath'] = 'XPath главного фото';
$_['entry_additional_images_xpath'] = 'XPath дополнительных фото';
$_['entry_image_attr'] = 'Атрибут фото';
$_['entry_source_language'] = 'Язык поставщика';
$_['entry_target_language'] = 'Язык OpenCart для импорта';
$_['entry_currency'] = 'Валюта';
$_['entry_discount'] = 'Скидка поставщика, %';
$_['entry_markup'] = 'Моя наценка, %';
$_['entry_rounding'] = 'Округление';
$_['entry_category'] = 'Категория новых товаров';
$_['entry_force_new_category'] = 'Категория импорта новых товаров';
$_['entry_stock_status'] = 'Статус при отсутствии';
$_['entry_create_new'] = 'Разрешить создание новых';
$_['entry_create_new_status'] = 'Статус новых товаров';
$_['entry_auto_apply_existing'] = 'Автообновлять найденные';
$_['entry_auto_create_new'] = 'Автосоздавать новые';
$_['entry_update_price'] = 'Разрешить обновлять цену';
$_['entry_update_stock'] = 'Разрешить обновлять наличие';
$_['entry_delay'] = 'Пауза между запросами, мс';
$_['entry_user_agent'] = 'User-Agent';
$_['entry_stock_map'] = 'Карта наличия';
$_['entry_category_map'] = 'Карта категорий поставщика';
$_['entry_test_url'] = 'Тестовый URL товара';
$_['column_supplier'] = 'Поставщик';
$_['column_status'] = 'Статус';
$_['column_url'] = 'URL';
$_['column_product'] = 'Товар';
$_['column_supplier_product'] = 'Товар поставщика';
$_['column_local_product'] = 'Товар у меня';
$_['column_sku'] = 'SKU';
$_['column_price'] = 'Цена';
$_['column_supplier_price'] = 'Цена поставщика';
$_['column_local_price'] = 'Моя цена';
$_['column_new_price'] = 'Новая цена';
$_['column_stock'] = 'Наличие';
$_['column_supplier_stock'] = 'Наличие поставщика';
$_['column_local_stock'] = 'Мое наличие';
$_['column_quantity'] = 'Кол-во';
$_['column_checked'] = 'Проверено';
$_['column_error'] = 'Ошибка';
$_['column_action'] = 'Действие';
$_['column_date'] = 'Дата';
$_['column_level'] = 'Уровень';
$_['column_message'] = 'Сообщение';
$_['column_total'] = 'Всего';
$_['column_pending'] = 'Ожидает';
$_['column_processing'] = 'В работе';
$_['column_done'] = 'Готово';
$_['column_skipped'] = 'Пропущено';
$_['column_type'] = 'Тип';
$_['column_category'] = 'Категория';
$_['column_match'] = 'Совпадение';
$_['help_xpath'] = 'Правила задаются через XPath. Перед массовым запуском обязательно проверьте один товар во вкладке Диагностика. Если сайт поставщика изменит верстку, XPath нужно обновить.';
$_['help_stock_map'] = 'Формат: текст поставщика|количество|stock_status_id. Например: в наличии|99 или нет в наличии|0. Один вариант на строку.';
$_['help_category_map'] = 'Формат: категория поставщика|category_id OpenCart|1 или 0. Значение 1 разрешает импорт, 0 исключает категорию. Например: Запчасти|25|1 или Сувениры|0|0.';
$_['help_existing_safe'] = 'Для существующих товаров модуль по умолчанию обновляет только цену и наличие. Название, описание, фото, SEO URL и метатеги не перезаписываются.';
$_['help_new_disabled'] = 'Безопасный режим: новые товары лучше создавать выключенными, чтобы проверить категории, фото, цену и описание перед публикацией.';
$_['help_review'] = 'Сначала запустите очередь. Модуль сохранит найденные товары в предпросмотр: какой товар найден у поставщика, какой товар найден у вас, текущая цена, новая цена и наличие. После этого выберите строки и примените только нужное действие.';
$_['help_cron'] = 'Для Mirohost запускайте эту команду не чаще одного раза в 5 минут. Секрет передаётся в HTTP-заголовке X-CCP-Cron-Key и не публикуется в URL. Существующие cron-задания с ?token= сохраняются как legacy fallback.';
$_['diagnostics_text'] = 'Проверка тестового URL показывает, какие данные модуль смог получить по текущим XPath-правилам: название, SKU, цену, наличие, категорию, фото и описание.';
$_['error_permission'] = 'У вас нет прав для изменения модуля.';
$_['error_supplier_name'] = 'Укажите название поставщика.';
$_['button_force_price'] = 'Принудительно цену';
$_['button_force_price_stock'] = 'Принудительно цену и наличие';
$_['button_search_product'] = 'Найти';
$_['button_match_product'] = 'Связать';
$_['button_create_new_tab'] = 'Создать выбранные товары в OpenCart';
$_['button_filter_reset'] = 'Сбросить фильтр';
$_['entry_list_category_xpath'] = 'XPath категории на странице списка';
$_['entry_max_price_change'] = 'Макс. изменение цены, %';
$_['entry_filter_status'] = 'Фильтр по статусу';
$_['entry_filter_type'] = 'Фильтр по типу';
$_['entry_filter_change'] = 'Фильтр по изменению';
$_['entry_product_search'] = 'Название, SKU или ID';
$_['entry_manual_product_id'] = 'Выберите товар';
$_['column_delta'] = 'Отклонение';
$_['help_price_warning'] = 'Если новая цена отличается от текущей больше допустимого процента, строка получает статус price_warning. Обычное обновление цены такую строку не применяет. Используйте принудительное действие только после ручной проверки.';
$_['tab_history'] = 'История';
$_['button_clear_history'] = 'Очистить историю';
$_['text_success_history_clear'] = 'История очищена';
$_['help_history'] = 'История фиксирует каждое ручное или автоматическое изменение цены и наличия: было, стало, источник и действие. Это помогает проверить последствия синхронизации и быстро найти ошибочные обновления.';
$_['column_old_price'] = 'Старая цена';
$_['column_new_price_history'] = 'Новая цена';
$_['column_old_quantity'] = 'Старое кол-во';
$_['column_new_quantity'] = 'Новое кол-во';
$_['column_field'] = 'Поле';
$_['column_note'] = 'Примечание';
$_['text_price_changed'] = 'Цена изменилась';
$_['text_stock_changed'] = 'Наличие изменилось';
$_['text_price_stock_changed'] = 'Цена и наличие';
$_['text_same'] = 'Без изменений';
$_['text_rows_shown'] = 'Показано строк';
$_['text_sort_hint'] = 'Нажмите на заголовок колонки для сортировки';
$_['button_only_changed'] = 'Только измененные';
$_['button_toggle_match'] = 'Связать вручную';
$_['entry_filter_text'] = 'Поиск по таблице';
$_['entry_filter_supplier'] = 'Фильтр по поставщику';
$_['entry_filter_stock'] = 'Фильтр по наличию';
$_['column_changed'] = 'Изменение';

$_['button_recover_errors'] = 'Восстановить ошибки';
$_['button_full_update'] = 'Полное обновление';
$_['text_success_errors_recovered'] = 'Ошибки очереди возвращены в ожидание';
$_['text_missing_policy_report'] = 'Только отчет';
$_['text_missing_policy_out'] = 'Поставить нет в наличии';
$_['text_missing_policy_disable'] = 'Выключить товар';
$_['text_missing_policy_zero'] = 'Количество 0';
$_['text_formula_same'] = 'Цена как у поставщика';
$_['text_formula_purchase_markup'] = 'Закупочная + наценка';
$_['text_formula_retail_discount_markup'] = 'Цена поставщика - скидка + наценка';
$_['entry_ean_xpath'] = 'XPath EAN';
$_['entry_upc_xpath'] = 'XPath UPC';
$_['entry_mpn_xpath'] = 'XPath MPN';
$_['entry_attribute_row_xpath'] = 'XPath строки характеристики';
$_['entry_attribute_name_xpath'] = 'XPath названия характеристики';
$_['entry_attribute_value_xpath'] = 'XPath значения характеристики';
$_['entry_option_row_xpath'] = 'XPath строки опции';
$_['entry_option_name_xpath'] = 'XPath названия опции';
$_['entry_option_value_xpath'] = 'XPath значения опции';
$_['entry_meta_description_xpath'] = 'XPath meta description';
$_['entry_meta_keyword_xpath'] = 'XPath meta keywords';
$_['entry_price_formula_mode'] = 'Формула цены';
$_['entry_min_margin'] = 'Мин. маржа, %';
$_['entry_missing_policy'] = 'Если товар пропал';
$_['entry_existing_description_mode'] = 'Описание существующего товара';
$_['entry_import_attributes'] = 'Импорт характеристик';
$_['entry_import_options'] = 'Импорт опций';
$_['entry_fill_all_languages'] = 'Заполнять все языки';
$_['entry_require_test_success'] = 'Требовать успешный тест';
$_['entry_excluded_skus'] = 'Исключенные SKU';
$_['entry_excluded_urls'] = 'Исключенные URL';
$_['entry_excluded_categories'] = 'Исключенные категории';
$_['column_purchase_price'] = 'Закупочная цена';
$_['column_run'] = 'Запуск';
$_['column_created'] = 'Создано';
$_['column_updated'] = 'Обновлено';
$_['help_exclusions'] = 'Каждое значение с новой строки. Исключения блокируют создание или обновление найденных товаров по SKU, URL или категории.';
$_['help_missing_policy'] = 'По умолчанию безопаснее оставлять только отчет. Автоматическое выключение или установка количества 0 включайте только после проверки поставщика.';
$_['help_full_update'] = 'Полное обновление применяет цену и наличие, а описание/характеристики/опции обновляет только по выбранным настройкам поставщика.';

$_['button_stop_queue'] = 'Остановить очередь';
$_['button_resume_queue'] = 'Возобновить очередь';
$_['button_exclude_selected'] = 'Исключить выбранные';
$_['text_queue_stop_active'] = 'Обработка очереди сейчас остановлена вручную. Возобновите ее перед запуском cron или пакетной обработки.';
$_['text_success_queue_stopped'] = 'Очередь остановлена. Ожидающие задания сохранены.';
$_['text_success_queue_resumed'] = 'Очередь возобновлена.';
$_['text_success_excluded'] = 'Выбранные строки добавлены в исключения.';
// v1.0.9
$_['text_server_filter_hint'] = 'Серверная выборка активна: фильтры и лимиты применяются на уровне базы данных, а не только в браузере.';
$_['text_success_cleanup'] = 'Старые данные очищены согласно срокам хранения.';
$_['text_formula_preview'] = 'Проверка формулы цены';
$_['text_category_map_visual'] = 'Визуальная карта категорий поставщика';
$_['text_pagination_summary'] = 'Показаны строки текущей серверной выборки.';
$_['button_run_until_done'] = 'Запустить до завершения';
$_['button_cleanup_old_data'] = 'Очистить старые данные';
$_['button_scan_new_only'] = 'Найти только новые';
$_['entry_new_tax_class_id'] = 'Tax class ID для новых';
$_['entry_new_minimum'] = 'Минимум для новых';
$_['entry_new_subtract'] = 'Вычитать склад';
$_['entry_new_shipping'] = 'Требуется доставка';
$_['entry_new_sort_order'] = 'Сортировка новых';
$_['entry_new_store_id'] = 'Store ID новых';
$_['entry_seo_url_mode'] = 'SEO URL для новых';
$_['entry_min_new_price'] = 'Мин. цена нового товара';
$_['entry_max_new_price'] = 'Макс. цена нового товара';
$_['entry_review_retention'] = 'Хранить предпросмотр, дней';
$_['entry_log_retention'] = 'Хранить логи, дней';
$_['entry_history_retention'] = 'Хранить историю, дней';
// v1.0.9 extended progress
$_['text_eta'] = 'ETA';
$_['text_speed_15m'] = 'Скорость за 15 мин';
$_['text_matched'] = 'Сопоставлено';
$_['text_unmatched'] = 'Не сопоставлено';
$_['text_last_url'] = 'Последний URL';
$_['text_locked'] = 'Lock';
$_['text_remaining'] = 'Осталось';
$_['text_completed'] = 'Завершено';
$_['text_found_preview'] = 'В предпросмотре';
$_['text_new_pending'] = 'Новые ожидают';
$_['text_new_created'] = 'Новые созданы';
$_['button_page_first'] = 'Первая';
$_['button_page_prev'] = 'Назад';
$_['button_page_next'] = 'Вперед';
$_['button_page_last'] = 'Последняя';
$_['button_apply_server_filters'] = 'Применить фильтр';


// v1.1.1 UI/UX
$_['text_ui_ajax_status'] = 'AJAX включен для рабочих действий: тест URL, построение очереди, запуск пакетов, пауза, возобновление, восстановление ошибок, применение выбранных строк, создание новых товаров, очистка логов и истории.';
$_['text_ui_safe_note'] = 'Сохранение основных настроек выполнено стандартным POST OpenCart, а тяжелые операции выполняются через AJAX-пакеты, чтобы не сбрасывать страницу и не запускать весь каталог одним процессом.';
$_['text_ui_admin_ready'] = 'Интерфейс сгруппирован по смыслу: поставщики, правила, предпросмотр, сопоставление, очередь, новые товары, история, логи, диагностика и настройки.';
$_['text_ajax_working'] = 'Выполняется AJAX-запрос...';
$_['text_ajax_done'] = 'Готово';
$_['help_field_base_url'] = 'Главный домен поставщика. Используется для приведения относительных ссылок и изображений к полному URL.';
$_['help_field_list_urls'] = 'Добавьте страницы категорий или списков товаров, по одной ссылке в строке. Для регулярного обновления связанных товаров можно не сканировать все категории каждый раз.';
$_['help_field_currency'] = 'Валюта выбирается только из активных валют OpenCart. Цена поставщика автоматически очищается от пробелов, запятых, точек, символов и названий валют, затем приводится к числовому формату OpenCart через курс валюты.';
$_['help_field_price_formula'] = 'Формула работает с числом после нормализации цены и конвертации в базовую валюту OpenCart: цена поставщика, скидка, наценка, минимальная маржа и округление. Проверьте один товар перед массовым применением.';
$_['help_field_language'] = 'Автоугадывание языка не используется как источник истины. Выберите язык поставщика для контроля и язык OpenCart, куда записывать название, описание, SEO, атрибуты и опции. Если включено заполнение всех языков, тот же текст будет скопирован во все активные языки без перевода.';
$_['help_field_force_new_category'] = 'Если выбрана эта категория, все новые товары поставщика будут создаваться в ней независимо от категории на сайте поставщика. Если настройка пустая, категорию можно выбрать отдельно для каждой строки во вкладке «Новые товары».';
$_['help_field_delay'] = 'Пауза между запросами снижает нагрузку на ваш сайт и сайт поставщика. Для больших каталогов безопаснее 500-1500 мс.';
$_['help_field_create_new'] = 'Для вашего сценария безопаснее создавать новые товары выключенными и публиковать только после проверки.';
$_['help_field_xpath_required'] = 'Критичные XPath: ссылка товара в списке, название, цена и наличие. SKU/EAN/UPC сильно повышают точность сопоставления.';
$_['help_field_exclusions'] = 'Исключения нужны, если вы продаете не весь ассортимент поставщика. Исключенный товар не будет создан или обновлен.';
$_['text_rounding_two'] = '2 знака';
$_['text_rounding_integer'] = 'Целое';
$_['text_rounding_up_integer'] = 'Вверх до целого';
$_['text_rounding_none'] = 'Без округления';
$_['text_no_selected_rows'] = 'Не выбраны строки';
$_['text_done'] = 'Готово';
$_['text_loading'] = 'Загрузка...';
$_['text_ajax_error'] = 'Ошибка AJAX:';
$_['text_created'] = 'Создано';
$_['text_updated'] = 'Обновлено';
$_['text_skipped'] = 'Пропущено';
$_['text_errors'] = 'Ошибки';
$_['text_processed'] = 'Обработано';
$_['text_preview'] = 'Предпросмотр';
$_['text_price_short'] = 'Цена';
$_['text_stock_short'] = 'Наличие';
$_['text_running'] = 'Выполняется...';
$_['text_confirm_force_price'] = 'Принудительное обновление цены может применить подозрительные изменения. Продолжать только после ручной проверки.';
$_['text_confirm_full_update'] = 'Полное обновление может изменить описание, атрибуты или опции согласно настройкам поставщика. Продолжить?';
$_['text_confirm_create_new'] = 'Будут созданы выбранные новые товары. Безопаснее создавать их выключенными для ручной проверки. Продолжить?';
$_['text_confirm_clear'] = 'Это действие очищает данные. Продолжить?';
$_['text_saved_tab_note'] = 'После сохранения модуль возвращается на текущую вкладку.';
$_['error_module_disabled'] = 'Модуль выключен. Включите модуль, сохраните настройки и только после этого запускайте очередь, обновление цен/наличия или создание товаров.';
$_['text_queue_loop_already_running'] = 'Процесс уже запущен в этом окне. Для остановки нажмите «Остановить очередь».';
$_['text_status_pending'] = 'Ожидает';
$_['text_status_processing'] = 'В обработке';
$_['text_status_done'] = 'Готово';
$_['text_status_price_warning'] = 'Предупреждение цены';
$_['text_status_duplicate'] = 'Возможный дубль';
$_['text_status_excluded'] = 'Исключено';
$_['text_status_applied'] = 'Применено';
$_['text_status_created'] = 'Создано';
$_['text_status_skipped'] = 'Пропущено';
$_['text_status_error'] = 'Ошибка';
$_['text_status_linked'] = 'Связано';
$_['text_type_existing'] = 'Существующий';
$_['text_type_new'] = 'Новый';
$_['text_seo_all_languages'] = 'Все языки';
$_['text_seo_main_language'] = 'Только основной язык';
$_['text_mode_keep'] = 'Не менять';
$_['text_mode_update_empty'] = 'Заполнить только пустое';
$_['text_mode_overwrite'] = 'Перезаписать';
$_['text_version'] = 'Версия';
$_['text_license'] = 'Лицензия';
$_['text_license_note'] = 'Лицензирование пока не внедрено. После установки модуль выключен по умолчанию.';
$_['column_opencart_id'] = 'OpenCart ID';
$_['button_auto_detect'] = 'Автоопределить';
$_['button_save_detected_rules'] = 'Сохранить выбранные правила';
$_['button_apply_detected_to_form'] = 'Подставить в поля формы';
$_['text_auto_detect_title'] = 'Автоопределение правил поставщика';
$_['text_auto_detect_intro'] = 'Модуль анализирует одну страницу товара и предлагает возможные XPath-правила. Это помощник, а не автоматическое применение: перед сохранением обязательно проверьте цену, наличие, название и SKU.';
$_['text_auto_detect_url'] = 'URL товара для анализа';
$_['text_auto_detect_confidence'] = 'Уверенность';
$_['text_auto_detect_source'] = 'Источник';
$_['text_auto_detect_value'] = 'Найденное значение';
$_['text_auto_detect_xpath'] = 'XPath';
$_['text_auto_detect_field'] = 'Поле';
$_['text_auto_detect_select'] = 'Выбранное правило';
$_['text_auto_detect_high'] = 'Высокая';
$_['text_auto_detect_medium'] = 'Средняя';
$_['text_auto_detect_low'] = 'Низкая';
$_['text_auto_detect_none'] = 'Не найдено';
$_['text_success_detected_rules_saved'] = 'Выбранные правила поставщика сохранены. Выполните тест URL перед массовым запуском.';
$_['error_supplier_required'] = 'Сначала выберите или сохраните поставщика.';
$_['help_auto_detect'] = 'Лучше вставить реальную карточку товара поставщика. Если найдено несколько цен, выбирайте основную цену товара, а не старую цену, цену доставки или рекомендованные товары.';

// Supplier Sync Parser PRO v1.5.2 UX, diagnostics and localization additions
$_['text_base_currency'] = 'базовая';
$_['text_supplier_next_steps_title'] = 'Что делать дальше:';
$_['text_supplier_next_steps_desc'] = 'сохраните поставщика, откройте Диагностику, вставьте реальную карточку товара, нажмите Автоопределить, подставьте правила в форму, сохраните и выполните Проверить. Только после успешной проверки запускайте очередь.';
$_['text_queue_quick_start'] = 'Быстрый запуск:';
$_['text_queue_mode_scan'] = 'Построить очередь сканирует страницы из поля «Страницы категорий или товаров».';
$_['text_queue_mode_linked'] = 'Проверить связанные товары обновляет уже сопоставленные товары без повторного обхода всех категорий.';
$_['text_queue_mode_new_only'] = 'Найти только новые ищет товары, которых еще нет в OpenCart.';
$_['text_ajax_invalid_json'] = 'Ответ сервера не является чистым JSON. Фрагмент ответа:';
$_['text_ajax_invalid_response'] = 'Некорректный ответ сервера.';
$_['error_test_url_required'] = 'Укажите реальный URL карточки товара поставщика или сохраните его в поле «Страницы категорий или товаров».';
$_['error_diagnostics_failed'] = 'Диагностика не выполнена';
$_['engine_ok'] = 'OK';
$_['engine_done'] = 'Готово';
$_['engine_invalid_url'] = 'Некорректный URL.';
$_['engine_invalid_product_url'] = 'Некорректный URL товара.';
$_['engine_supplier_not_found'] = 'Поставщик не найден.';
$_['engine_cannot_parse_html'] = 'HTML страницы не удалось разобрать.';
$_['engine_product_name_not_found'] = 'Название товара не найдено по текущему XPath.';
$_['engine_product_price_not_found'] = 'Цена товара не найдена по текущему XPath.';
$_['engine_calculated_price_invalid'] = 'Рассчитанная цена продажи некорректна.';
$_['engine_auto_detection_completed'] = 'Автоопределение выполнено. Проверьте найденные правила перед сохранением.';
$_['engine_fetch_failed'] = 'Не удалось получить страницу:';
$_['engine_http_error'] = 'HTTP-ошибка:';
$_['engine_product_price_invalid'] = 'Цена товара некорректна:';
$_['engine_supplier_currency_inactive'] = 'Валюта поставщика не активна в OpenCart:';
$_['field_label_name_xpath'] = 'Название товара';
$_['field_label_price_xpath'] = 'Цена';
$_['field_label_stock_xpath'] = 'Наличие';
$_['field_label_sku_xpath'] = 'Артикул / SKU';
$_['field_label_ean_xpath'] = 'EAN';
$_['field_label_upc_xpath'] = 'UPC';
$_['field_label_mpn_xpath'] = 'MPN';
$_['field_label_manufacturer_xpath'] = 'Производитель / бренд';
$_['field_label_category_xpath'] = 'Категория / хлебные крошки';
$_['field_label_description_xpath'] = 'Описание';
$_['field_label_meta_description_xpath'] = 'Meta description';
$_['field_label_meta_keyword_xpath'] = 'Meta keywords';
$_['field_label_image_xpath'] = 'Главное фото';
$_['field_label_additional_images_xpath'] = 'Дополнительные фото';
$_['field_label_attribute_row_xpath'] = 'Строки характеристик';
$_['field_label_option_row_xpath'] = 'Строки опций';
$_['auto_source_attribute_specification_table_rows'] = 'Строки таблицы характеристик';
$_['auto_source_brand_manufacturer_class'] = 'Класс бренда/производителя';
$_['auto_source_breadcrumb_class'] = 'Класс хлебных крошек';
$_['auto_source_breadcrumb_id'] = 'ID хлебных крошек';
$_['auto_source_description_class'] = 'Класс описания';
$_['auto_source_description_id'] = 'ID описания';
$_['auto_source_ean_gtin_class'] = 'Класс EAN/GTIN';
$_['auto_source_gallery_image_src'] = 'Фото галереи src';
$_['auto_source_gallery_lazy_image'] = 'Ленивое фото галереи';
$_['auto_source_lazy_product_image_data_src'] = 'Ленивое фото товара data-src';
$_['auto_source_mpn_class'] = 'Класс MPN';
$_['auto_source_main_h1_heading'] = 'Главный заголовок H1';
$_['auto_source_meta_description'] = 'Meta description';
$_['auto_source_meta_keywords'] = 'Meta keywords';
$_['auto_source_opengraph_image'] = 'OpenGraph фото';
$_['auto_source_opengraph_title'] = 'OpenGraph title';
$_['auto_source_option_variant_blocks'] = 'Блоки опций/вариантов';
$_['auto_source_product_description_class'] = 'Класс описания товара';
$_['auto_source_product_image_src'] = 'Фото товара src';
$_['auto_source_product_title_class'] = 'Класс названия товара';
$_['auto_source_product_title_id'] = 'ID названия товара';
$_['auto_source_sku_model_class'] = 'Класс SKU/модели';
$_['auto_source_sku_model_id'] = 'ID SKU/модели';
$_['auto_source_select_options_variants'] = 'Опции/варианты select';
$_['auto_source_specification_rows'] = 'Строки спецификаций';
$_['auto_source_stock_availability_class'] = 'Класс наличия';
$_['auto_source_stock_availability_id'] = 'ID наличия';
$_['auto_source_thumbnail_image_src'] = 'Миниатюра src';
$_['auto_source_upc_class'] = 'Класс UPC';
$_['auto_source_visible_price_block'] = 'Видимый блок цены';
$_['auto_source_visible_price_id'] = 'Видимый ID цены';
$_['auto_source_availability_link'] = 'Ссылка availability';
$_['auto_source_product_brand_meta'] = 'Meta product:brand';
$_['auto_source_product_price_amount_meta'] = 'Meta product:price:amount';
$_['auto_source_schema_org_breadcrumblist'] = 'schema.org BreadcrumbList';
$_['auto_source_schema_org_availability_href'] = 'schema.org availability href';
$_['auto_source_schema_org_availability_text'] = 'schema.org availability text';
$_['auto_source_schema_org_brand'] = 'schema.org brand';
$_['auto_source_schema_org_description'] = 'schema.org description';
$_['auto_source_schema_org_gtin'] = 'schema.org gtin';
$_['auto_source_schema_org_gtin12'] = 'schema.org gtin12';
$_['auto_source_schema_org_gtin13'] = 'schema.org gtin13';
$_['auto_source_schema_org_image_content'] = 'schema.org image content';
$_['auto_source_schema_org_image_src'] = 'schema.org image src';
$_['auto_source_schema_org_itemprop_name'] = 'schema.org itemprop=name';
$_['auto_source_schema_org_itemprop_price_content'] = 'schema.org itemprop=price content';
$_['auto_source_schema_org_itemprop_price_text'] = 'schema.org itemprop=price text';
$_['auto_source_schema_org_mpn'] = 'schema.org mpn';
$_['auto_source_schema_org_sku'] = 'schema.org sku';
$_['auto_source_exact_name'] = 'Точное название';
$_['auto_source_missing'] = 'Отсутствует';
$_['auto_source_none'] = 'Нет';
$_['auto_source_product'] = 'Товар';
$_['auto_source_product_model_sku_ean_upc'] = 'Товар: model/SKU/EAN/UPC';
$_['auto_source_product_sku_model_ean_upc'] = 'Товар: SKU/model/EAN/UPC';
$_['auto_source_supplier_sku_link'] = 'Связка по SKU поставщика';
$_['auto_source_supplier_url_link'] = 'Связка по URL поставщика';

$_['text_queue_mode_product_urls'] = 'Добавить URL товаров в очередь подходит, если в поле указаны прямые ссылки на карточки товаров, а не категория.';

$_['engine_module_disabled'] = 'Модуль выключен.';
$_['engine_queue_stopped'] = 'Очередь остановлена вручную.';
$_['engine_linked_queue_created'] = 'Очередь проверки связанных товаров создана.';
$_['engine_product_url_queue_created'] = 'Очередь прямых URL товаров создана.';
$_['engine_list_scan_queue_created'] = 'Очередь сканирования списка создана.';
$_['engine_test_required'] = 'Массовый импорт заблокирован до успешной проверки поставщика.';
$_['engine_missing_name_xpath'] = 'Не указан обязательный XPath названия товара.';
$_['engine_missing_price_xpath'] = 'Не указан обязательный XPath цены товара.';
$_['engine_supplier_urls_empty'] = 'Список URL поставщика пустой.';
$_['engine_missing_policy_stock_status_required'] = 'Для политики отсутствующего товара нужен статус наличия по умолчанию.';

// Supplier Sync Parser PRO v1.5.2 rule wizard and switch UI
$_['button_scan_save_rules'] = 'Сканировать и сохранить лучшие правила';
$_['button_rules_manual_toggle'] = 'Показать ручные XPath-поля';
$_['text_rules_quick_setup_title'] = 'Быстрая настройка по странице товара';
$_['text_rules_quick_setup_intro'] = 'Вручную писать XPath обычно не нужно. Вставьте одну реальную ссылку на карточку товара поставщика, и модуль просканирует страницу, найдет название, цену, наличие, SKU, фото и описание, затем предложит правила. Эта ссылка нужна как образец структуры сайта поставщика.';
$_['text_rules_quick_setup_note'] = 'Это должна быть именно карточка товара, а не категория. После сканирования проверьте найденную цену и название, затем сохраните правила.';
$_['text_rules_manual_title'] = 'Ручные XPath-поля';
$_['text_rules_manual_intro'] = 'Заполняйте вручную только если автоопределение не нашло нужный блок или сайт поставщика имеет нестандартную верстку.';
$_['text_switch_on'] = 'Включить';
$_['text_switch_off'] = 'Выключить';

// v1.5.2 simplified supplier workflow
$_['button_scan_product_page'] = 'Сканировать карточку товара';
$_['button_detect_list_links'] = 'Найти ссылки товаров в категории';
$_['text_rules_product_scan_label'] = '1. Ссылка на карточку товара для определения полей';
$_['text_rules_list_scan_label'] = '2. Ссылка на категорию/список для поиска ссылок товаров';
$_['help_rules_product_scan'] = 'Вставьте одну реальную карточку товара. Модуль найдет название, цену, наличие, SKU, фото и описание.';
$_['help_rules_list_scan'] = 'Нужно только если хотите сканировать категории поставщика. Модуль найдет XPath ссылок товаров на странице списка.';
$_['text_auto_detect_best_hint'] = 'Модуль автоматически выбирает лучший вариант. Если найденное значение неправильное, откройте список в строке поля и выберите другой вариант, затем сохраните выбранные правила.';
$_['button_delete'] = 'Удалить';
$_['button_build_queue'] = 'Сканировать категории';
$_['button_build_product_urls'] = 'Добавить прямые URL товаров';
$_['button_check_linked'] = 'Проверить уже сопоставленные';
$_['text_confirm_delete_supplier'] = 'Удалить поставщика и связанные с ним очередь, предпросмотр, новые товары, историю и логи?';
$_['text_success_supplier_deleted'] = 'Поставщик удален.';
$_['text_success_supplier_status_changed'] = 'Статус поставщика изменен.';
$_['text_queue_scenario_title'] = 'Как запускать:';
$_['text_queue_scenario_direct'] = 'если в поставщике вставлены прямые ссылки на карточки товаров — нажмите «Добавить прямые URL товаров»;';
$_['text_queue_scenario_scan'] = 'если вставлены страницы категорий — сначала в правилах найдите XPath ссылок товаров, затем нажмите «Сканировать категории»;';
$_['text_queue_scenario_linked'] = 'если товары уже сопоставлены — используйте «Проверить уже сопоставленные»;';
$_['text_queue_scenario_run'] = 'после создания очереди нажмите «Запустить пакет» или «Запустить до завершения».';
$_['engine_list_auto_detection_completed'] = 'Ссылки товаров на странице списка найдены. Проверьте найденные URL и сохраните выбранные правила.';
$_['engine_missing_product_url_xpath'] = 'Вы нажали сканирование категории/списка. Для этого нужен XPath ссылок товаров на странице списка. Откройте вкладку «Правила парсинга», вставьте URL категории в поле «Ссылка на категорию/список» и нажмите «Найти ссылки товаров в категории». Если у вас прямые ссылки на карточки товаров, используйте кнопку «Добавить прямые URL товаров».';
$_['field_label_product_url_xpath'] = 'Ссылки товаров в категории';
$_['field_label_next_page_xpath'] = 'Следующая страница категории';

// Supplier Sync Parser PRO v1.5.2 workflow and large data UX
$_['text_check_supplier_saved'] = 'Поставщик сохранен';
$_['text_check_base_url'] = 'Базовый домен';
$_['text_check_product_rules'] = 'Название и цена';
$_['text_check_queue_source'] = 'Источник очереди';
$_['text_ready'] = 'Готово';
$_['text_not_ready'] = 'Нужно заполнить';
$_['text_ready_category_scan'] = 'Скан категории';
$_['text_ready_direct_urls'] = 'Прямые URL';
$_['text_rules_step_product_url'] = 'Вставьте карточку товара';
$_['text_rules_step_choose_values'] = 'Проверьте найденные значения';
$_['text_rules_step_save_rules'] = 'Сохраните выбранные правила';
$_['text_rules_step_test'] = 'Выполните проверку';
$_['text_queue_group_create'] = 'Создать очередь';
$_['text_queue_group_process'] = 'Обработать очередь';
$_['text_queue_group_service'] = 'Обслуживание';
$_['text_queue_group_process_help'] = 'Сначала безопасно найдите ссылки товаров пакетами, затем проверьте товары пакетами. Так можно увидеть, какие URL добавлены, и не запускать сразу тысячи проверок.';
$_['text_queue_group_service_help'] = 'Используйте восстановление ошибок и сброс обработки только если очередь прервалась или зависла.';
$_['text_ajax_table_updated'] = 'Таблица обновлена через AJAX';
$_['text_about_benefit_sync_title'] = 'Контроль цен и наличия';
$_['text_about_benefit_sync'] = 'Модуль помогает регулярно проверять сайты поставщиков и находить изменения цены, наличия и новых товаров.';
$_['text_about_benefit_safe_title'] = 'Безопасное применение';
$_['text_about_benefit_safe'] = 'Данные сначала попадают в предпросмотр. Администратор выбирает строки и действие: цена, наличие, полное обновление или создание нового товара.';
$_['text_about_benefit_mass_title'] = 'Работа с большими каталогами';
$_['text_about_benefit_mass'] = 'Очередь, пакетная обработка, прогресс, фильтры, сортировки, серверная пагинация и выбор 50/100/200/500 строк позволяют работать с тысячами товаров.';
$_['text_about_benefit_lang_title'] = 'Языки OpenCart';
$_['text_about_benefit_lang'] = 'Для поставщика выбирается язык источника и целевой язык OpenCart. Остальные языки не перезаписываются без отдельной настройки.';
$_['text_about_workflow_title'] = 'Последовательность работы';
$_['text_about_step_1'] = 'Создайте поставщика, укажите базовый URL, валюту, язык и страницы категорий или прямые ссылки товаров.';
$_['text_about_step_2'] = 'Во вкладке правил вставьте одну карточку товара, просканируйте ее, проверьте найденные значения и сохраните правила.';
$_['text_about_step_4'] = 'Создайте очередь, обработайте ее пакетами и проверьте результаты в предпросмотре и новых товарах.';
$_['text_about_step_5'] = 'Выберите строки и примените только нужное действие: обновить цену, наличие, цену и наличие, сопоставить товар или создать новый.';
$_['about_text'] = 'Supplier Sync Parser PRO нужен для магазинов OpenCart/ocStore, которые получают товары и цены с сайтов поставщиков без XML, CSV или API. Модуль не меняет каталог сразу: он собирает данные через HTML-парсинг, нормализует цены и наличие, сопоставляет найденные позиции с товарами магазина и показывает результат в безопасном предпросмотре. Это снижает ручную работу, помогает быстрее обновлять тысячи товаров и уменьшает риск случайно испортить цены или карточки.';

// Supplier Sync Parser PRO v1.5.2 supplier scope and domain guard
$_['text_supplier_context_title'] = 'Рабочий поставщик';
$_['text_supplier_context_help'] = 'Все вкладки после «Поставщики» работают только с выбранным поставщиком: свои правила, очередь, предпросмотр, новые товары, история и логи.';
$_['text_supplier_context_required'] = 'Сначала выберите или сохраните поставщика.';
$_['text_domain_policy_title'] = 'Доменная защита';
$_['text_domain_policy_strict'] = 'Только базовый домен поставщика';
$_['text_domain_policy_subdomains'] = 'Базовый домен и его поддомены';
$_['text_domain_policy_extra'] = 'Базовый домен и указанные дополнительные домены';
$_['entry_domain_policy'] = 'Доменная защита';
$_['entry_allowed_hosts'] = 'Дополнительные HTML-домены';
$_['help_domain_policy'] = 'По умолчанию модуль сканирует только базовый домен поставщика и не переходит на чужие сайты. Внешние CDN-картинки могут скачиваться как изображения, но не ставятся в очередь как товары. Дополнительные домены указывайте только осознанно, по одному хосту в строке.';
$_['engine_url_outside_domain'] = 'URL вне разрешенного домена поставщика:';

// Supplier Sync Parser PRO v1.5.2 scan workflow
$_['button_scan_site_all'] = 'Сканировать сайт и найти ссылки товаров';
$_['text_queue_action_scan_list'] = 'Поиск ссылок';
$_['text_queue_action_check_product'] = 'Проверка товара';
$_['text_queue_open_preview_hint'] = 'После обработки найденные товары появятся в предпросмотре, а несопоставленные - во вкладке новых товаров.';
$_['engine_site_scan_queue_created'] = 'Очередь сканирования сайта создана.';

// Supplier Sync Parser PRO v1.5.2 scan and warning fixes
$_['button_scan_sitemap'] = 'Найти товары через sitemap';
$_['engine_sitemap_queue_created'] = 'Очередь товаров из sitemap создана.';
$_['engine_sitemap_no_products'] = 'Sitemap не дал ссылок товаров.';
$_['engine_sitemap_check_hint'] = 'Проверьте доступность sitemap или используйте сканирование категории.';
$_['engine_product_parse_failed'] = 'Ошибка обработки товара';
$_['engine_price_lower_guard'] = 'Расчетная цена ниже минимального ограничения.';
$_['engine_price_higher_guard'] = 'Расчетная цена выше максимального ограничения.';
$_['text_queue_group_create_help'] = 'Если указана категория поставщика, нажмите «Сканировать категории». Если указан весь сайт или главная страница, используйте «Сканировать сайт». Если на сайте есть sitemap, кнопка «Найти товары через sitemap» обычно находит больше товаров. Если вставлены прямые ссылки на карточки, используйте «Добавить прямые URL товаров». Результат всегда сначала попадает в предпросмотр.';
$_['text_about_step_3'] = 'Если нужен сбор всего сайта, сначала попробуйте «Найти товары через sitemap», затем «Сканировать сайт». Для одной категории используйте «Сканировать категории». Для готового списка карточек используйте «Добавить прямые URL товаров».';
// Supplier Sync Parser PRO v1.5.2 usability fixes
$_['entry_stop_queue'] = 'Пауза обработки очереди';
$_['help_queue_pause'] = 'Это ручная пауза для cron и пакетной обработки. Нужна, если нужно временно остановить импорт без удаления найденных заданий. В обычной работе держите выключенной.';
$_['help_price_guards'] = '0 означает без ограничения. Поля мин./макс. цены нужны только как страховка от ошибочной цены поставщика. При срабатывании строка попадает в предпросмотр с предупреждением, а не применяется автоматически.';
$_['text_ui_workflow_title'] = 'Порядок работы с поставщиком';
$_['text_ui_step_supplier_title'] = '1. Поставщик';
$_['text_ui_step_supplier_desc'] = 'Создайте или выберите поставщика, укажите базовый домен, валюту, язык и ссылки категорий или товаров.';
$_['text_ui_step_rules_title'] = '2. Правила';
$_['text_ui_step_rules_desc'] = 'Сканируйте одну карточку товара и сохраните найденные поля: название, цену, наличие, SKU и фото.';
$_['text_ui_step_queue_title'] = '3. Очередь';
$_['text_ui_step_queue_desc'] = 'Запустите поиск товаров через sitemap, категории или прямые URL. Модуль заполнит очередь.';
$_['text_ui_step_scan_title'] = '4. Обработка';
$_['text_ui_step_scan_desc'] = 'Запустите пакет или до завершения. Товары сначала попадут в предпросмотр, а не сразу изменят каталог.';
$_['text_ui_step_process_title'] = '5. Предпросмотр';
$_['text_ui_step_process_desc'] = 'Проверьте найденные товары, сопоставление, цену, наличие и предупреждения.';
$_['text_ui_step_apply_title'] = '6. Применение';
$_['text_ui_step_apply_desc'] = 'Выберите действие для каждой строки или массово: обновить цену, наличие, создать новый товар, пропустить или исключить.';

// Supplier Sync Parser PRO v1.5.2 bulk and large catalog UX
$_['button_retry_selected_queue'] = 'Вернуть выбранные в очередь';
$_['button_delete_selected_queue'] = 'Удалить выбранные из очереди';
$_['button_new_skip_selected'] = 'Пропустить выбранные';
$_['button_new_restore_selected'] = 'Вернуть выбранные в ожидание';
$_['text_bulk_actions'] = 'Массовые действия с выбранными строками';
$_['text_batch_processing_hint'] = 'Для тысяч товаров используйте пакетную обработку: модуль берет небольшую порцию товаров, обновляет полосу прогресса, затем берет следующую порцию. Это безопаснее для хостинга и не должно валить сайт по таймауту.';
$_['text_limit_explain'] = 'Ограничения: одна страница таблицы показывает 50/100/200/500 строк. Очередь может содержать тысячи товаров. Один пакет обработки ограничен до 100 товаров за AJAX-запрос, чтобы не перегружать сервер.';
$_['text_compare_names_hint'] = 'В предпросмотре рядом выводятся товар поставщика и ваш товар OpenCart: так можно сравнить название, SKU, цену поставщика, вашу текущую цену, новую цену и наличие перед применением.';

// Supplier Sync Parser PRO v1.5.2 scan-source and language guard fixes
$_['text_list_url_product_warning'] = 'Это похоже на карточку товара. Для поиска товаров вставьте ссылку на категорию или список товаров.';
$_['engine_category_url_is_product'] = 'Это похоже на карточку товара. Вставьте ссылку на категорию или список товаров для поиска ссылок.';
$_['engine_url_skipped_domain_language'] = 'URL пропущен политикой домена или языка';

// Supplier Sync Parser PRO v1.5.2 safer product import
$_['entry_import_additional_images'] = 'Импорт доп. фото';
$_['help_import_additional_images'] = 'По умолчанию выключено: дополнительные фото часто берутся из похожих товаров, статей или баннеров. Включайте только после проверки XPath фото.';
$_['text_price_extraction_fixed'] = 'Цена очищается от валюты, пробелов, текста и служебных чисел перед расчетом.';

$_['button_run_scan_batch'] = 'Найти ссылки пакетами';
$_['button_run_products_batch'] = 'Проверить товары пакетами';
$_['button_run_products_until_done'] = 'Проверить товары до завершения';
$_['text_found_urls'] = 'Найдено URL';
$_['text_scan_pages_left'] = 'Страниц поиска осталось';
$_['text_products_left'] = 'Товаров на проверку осталось';
// Supplier Sync Parser PRO v1.5.2 queue usability fixes
$_['button_stop_current_process'] = 'Остановить текущий процесс';
$_['button_run_selected_scan'] = 'Найти ссылки из выбранных страниц';
$_['button_run_selected_products'] = 'Проверить найденные товары';
$_['text_queue_category_next_step'] = 'Если в очереди стоит страница категории, нажмите «Найти ссылки из выбранных страниц» или «Сканировать категории». После появления товарных URL нажмите «Проверить найденные товары».';

// Supplier Sync Parser PRO v1.5.2 commercial workflow additions
$_['tab_master'] = 'Мастер запуска';
$_['text_master_title'] = 'Коммерческий мастер запуска';
$_['text_master_intro'] = 'Работайте как в коммерческих парсерах: сначала источник, затем правила, потом поиск ссылок, проверка карточек, предпросмотр и только после этого ручное применение выбранных строк.';
$_['text_master_supplier'] = 'Создайте поставщика, домен, валюту, язык и источники.';
$_['text_master_rules'] = 'Проверьте одну реальную карточку товара и сохраните правила.';
$_['text_master_find_links'] = 'Найти ссылки';
$_['text_master_find_links_help'] = 'Соберите URL товаров из категорий, домена или sitemap. Товары еще не обновляются.';
$_['text_master_check_sample'] = 'Проверить первые 10';
$_['text_master_check_sample_help'] = 'Сначала проверьте малую выборку, чтобы убедиться в цене, SKU, названии и фото.';
$_['text_master_check_all'] = 'Проверить все';
$_['text_master_check_all_help'] = 'После успешной выборки запускайте пакетную проверку всех найденных товаров.';
$_['text_master_apply'] = 'Сравните товар поставщика и ваш товар, выберите строки и примените действие вручную.';
$_['text_master_queue'] = 'Очередь';
$_['text_master_remaining'] = 'Осталось';
$_['text_source_block_title'] = '1. Источники и базовые данные поставщика';
$_['text_source_list_help'] = 'Одна ссылка в строке. Для категории используйте кнопку «Найти ссылки», для прямых карточек — режим прямых URL. Не смешивайте разные языки в одном запуске.';
$_['text_commercial_safe_apply_note'] = 'Сканирование готовит предпросмотр. Создание новых товаров требует выбора строк; cron обновляет связанные товары только по включенным правилам профиля.';

// Supplier Sync Parser PRO v1.5.2 master and queue UX
$_['text_master_create_supplier_title'] = 'Начните с создания поставщика';
$_['text_master_create_supplier_help'] = 'Мастер не требует заранее выбранного поставщика. Сначала создайте поставщика, укажите домен, валюту, язык и источники, затем вернитесь в мастер для проверки правил и очереди.';
$_['button_master_create_supplier'] = 'Создать поставщика';
$_['button_master_edit_supplier'] = 'Открыть поставщика';
$_['text_master_supplier_selected'] = 'Поставщик выбран';
$_['text_master_supplier_selected_help'] = 'Теперь можно проверять правила, искать ссылки товаров и запускать пакетную проверку.';
$_['engine_list_scan_failed'] = 'Ошибка сканирования страницы списка';

$_['text_selected_rows'] = 'Выбрано';

$_['text_category_for_selected'] = 'Категория для выбранных';
$_['button_apply_category_selected'] = 'Применить категорию';
$_['text_category_required'] = 'Выберите категорию для выбранных товаров.';
$_['text_category_applied_to_selected'] = 'Категория применена к выбранным строкам';

// Supplier Sync Parser PRO v1.5.2 fixes
$_['engine_no_selected_rows'] = 'Строки не выбраны';
$_['engine_unknown_apply_mode'] = 'Неизвестный режим применения';
$_['engine_row_excluded_by_rules'] = 'Строка исключена правилами поставщика';
$_['engine_skipped_manually'] = 'Пропущено вручную';
$_['engine_skipped_and_excluded_from_preview'] = 'Пропущено и исключено из предпросмотра';
$_['engine_skipped_and_excluded_manually'] = 'Пропущено и исключено вручную';
$_['engine_new_creation_disabled'] = 'Создание новых товаров отключено для этого поставщика';
$_['engine_supplier_category_not_allowed'] = 'Категория поставщика запрещена правилами';
$_['engine_product_was_not_created'] = 'Товар не был создан';
$_['engine_no_linked_product'] = 'Нет связанного товара OpenCart';
$_['engine_price_warning_force_required'] = 'Предупреждение цены: принудительное обновление используйте только после ручной проверки';
$_['engine_preview_saved_existing'] = 'Предпросмотр сохранен для существующего товара';
$_['engine_preview_saved_new'] = 'Предпросмотр сохранен для нового товара';
$_['engine_waiting_for_review'] = 'Ожидает проверки';
$_['engine_unknown_action'] = 'Неизвестное действие';
$_['engine_existing_linked_skipped_new_only'] = 'Связанный товар пропущен в режиме только новых товаров';
$_['engine_missing_supplier_or_data'] = 'Поставщик или разобранные данные отсутствуют';
$_['engine_created_from_supplier_preview'] = 'Товар создан из предпросмотра поставщика';
$_['engine_updated_existing_product'] = 'Существующий товар обновлен';
$_['engine_no_changes'] = 'Изменений нет';
$_['engine_price_change_guard'] = 'Изменение цены выше разрешенного лимита поставщика';
$_['engine_excluded_by_sku_rule'] = 'Исключено правилом SKU';
$_['engine_excluded_by_url_rule'] = 'Исключено правилом URL';
$_['engine_excluded_by_category_rule'] = 'Исключено правилом категории';
$_['engine_manual_exclusion'] = 'Ручное исключение';
$_['engine_duplicate_found_by'] = 'Возможный дубль найден по';
$_['engine_already_linked_to_product'] = 'Эта строка уже связана с товаром ID';
$_['engine_queue_locked'] = 'Очередь уже обрабатывается процессом';
$_['engine_url_skipped_domain_policy'] = 'URL пропущен политикой домена';
$_['engine_url_skipped_domain_language_policy'] = 'URL пропущен политикой домена или языка';

$_['engine_product_linked_manually'] = 'Товар связан вручную.';
$_['engine_opencart_product_not_found'] = 'Товар OpenCart не найден.';
$_['engine_review_product_required'] = 'Нужны ID строки предпросмотра и ID товара.';
$_['engine_review_row_not_found'] = 'Строка предпросмотра не найдена.';
// Supplier Sync Parser PRO v1.5.2 usability improvements
$_['help_new_seo_url'] = 'URL нового товара формируется автоматически при создании: берется название товара, очищается от лишних слов, транслитерируется в латиницу, приводится к нижнему регистру и записывается в таблицу seo_url. Если такой keyword уже существует, модуль добавляет суффикс -2, -3 и далее. Режим задается в поставщике: все языки, только основной язык или без SEO URL.';
$_['text_match_search_hint'] = 'Если поле пустое, поиск возьмет SKU или название поставщика и предложит товары OpenCart. Можно вручную ввести ID, SKU, модель или часть названия.';
$_['text_match_selected'] = 'Товар связан, строка предпросмотра обновлена.';
$_['text_queue_selected_run_hint'] = 'Эти кнопки теперь обрабатывают именно выбранные строки очереди, а не следующий общий пакет.';

$_['engine_supplier_disabled_or_not_found'] = 'Поставщик отключен или не найден';
$_['engine_url_not_supplier_product'] = 'URL не является товарной страницей поставщика';
$_['engine_added_product_jobs'] = 'Добавлены задания товаров:';

// Supplier Sync Parser PRO v1.5.2 safer matching
$_['text_match_candidates'] = 'Возможные совпадения 90%+';
$_['engine_match_candidates_manual'] = 'Найдены возможные совпадения. Перед созданием нового товара выберите товар вручную или проверьте дубль.';
$_['help_manual_match'] = 'Связывание работает безопасно: точные связи по URL/SKU/модели/EAN/UPC/MPN применяются автоматически только если найден один товар. Похожие названия от 90% не связываются автоматически, а показываются как предложения для ручного выбора.';

// Feed/XML/CSV import
$_['entry_source_type'] = 'Источник данных';
$_['entry_feed_url'] = 'URL XML/YML/CSV-фида';
$_['entry_feed_file'] = 'Загрузить файл фида';
$_['entry_feed_format'] = 'Формат фида';
$_['entry_feed_item_path'] = 'Путь товара';
$_['entry_feed_url_path'] = 'Поле URL';
$_['entry_feed_sku_path'] = 'Поле SKU/артикула';
$_['entry_feed_name_path'] = 'Поле названия';
$_['entry_feed_price_path'] = 'Поле цены';
$_['entry_feed_stock_path'] = 'Поле наличия';
$_['entry_feed_quantity_path'] = 'Поле количества';
$_['entry_feed_category_path'] = 'Поле категории';
$_['entry_feed_description_path'] = 'Поле описания';
$_['entry_feed_manufacturer_path'] = 'Поле производителя';
$_['entry_feed_image_path'] = 'Поле изображения';
$_['entry_feed_ean_path'] = 'Поле EAN';
$_['entry_feed_upc_path'] = 'Поле UPC';
$_['entry_feed_mpn_path'] = 'Поле MPN';
$_['text_source_type_html'] = 'HTML-сканирование сайта';
$_['text_source_type_feed'] = 'Файл / XML / YML / CSV';
$_['text_feed_help'] = 'Можно указать ссылку на XML/YML/CSV или загрузить файл. Фид создает очередь импорта и переносит товары в предпросмотр без автоматического изменения магазина.';
$_['text_feed_mapping_help'] = 'Для XML/YML указывайте имя тега или XPath относительно товара. Для CSV указывайте название колонки. Если поле пустое, модуль попробует стандартные названия.';
$_['text_feed_file_current'] = 'Текущий файл';
$_['button_build_feed_file'] = 'Построить очередь из файла';
$_['button_run_feed_batch'] = 'Обработать файл пакетом';
$_['button_run_feed_until_done'] = 'Обработать файл полностью';
$_['text_queue_action_import_item'] = 'Товар из файла';

// Feed import engine messages
$_['engine_feed_queue_created'] = 'Очередь из файла создана';
$_['engine_feed_preview_saved_existing'] = 'Предпросмотр из файла сохранен для существующего товара';
$_['engine_feed_preview_saved_new'] = 'Предпросмотр из файла сохранен для нового товара';
$_['engine_feed_source_required'] = 'Нужен URL фида или загруженный файл';
$_['engine_feed_source_type_required'] = 'Для этого режима тип источника должен быть Файл/XML/CSV';
$_['engine_csv_no_rows'] = 'В CSV-фиде нет строк товаров';
$_['engine_feed_file_empty'] = 'Загруженный файл фида пустой или не читается';
$_['engine_simplexml_required'] = 'Для XML/YML-фидов требуется PHP-расширение SimpleXML';
$_['engine_feed_item_invalid'] = 'В строке файла нет корректного названия или расчетной цены';
$_['engine_feed_parsed'] = 'Фид разобран:';
$_['engine_cannot_parse_feed_xml'] = 'Не удалось разобрать XML/YML-фид';

$_['text_copy_url'] = 'Копировать ссылку';
$_['text_copied'] = 'Ссылка скопирована';

// Supplier Sync Parser PRO v1.5.2 preview/new product workflow fixes
$_['button_delete_selected_reviews'] = 'Удалить выбранные из предпросмотра';
$_['button_delete_selected_new'] = 'Удалить выбранные из новых товаров';
$_['text_moved_new'] = 'Перенесено в новые товары';
$_['text_deleted'] = 'Удалено';
$_['text_created_product_ids'] = 'Созданные ID товаров';
$_['text_preview_move_to_new_hint'] = 'В предпросмотре кнопка создания не создает товар сразу: она переносит выбранные новые позиции во вкладку «Новые товары» для выбора категории и финальной проверки.';
$_['text_new_create_hint'] = 'В этой вкладке кнопка создания создает выбранные товары в OpenCart. После создания строка получает ID созданного товара.';

// Supplier Sync Parser PRO v1.5.2 new product workflow diagnostics
$_['text_new_creation_disabled_hint'] = 'Создание новых товаров выключено в настройках этого поставщика. Включите «Разрешить создание новых», сохраните поставщика и повторите создание.';
$_['text_error_details'] = 'Подробности ошибки';

// Supplier Sync Parser PRO v1.6.0 new product creation flag save fix
$_['text_success_supplier_new_flags_saved'] = 'Настройки создания новых товаров сохранены.';

// Supplier Sync Parser PRO v1.6.0 feed mapping and import cleanup
$_['button_auto_detect_feed'] = 'Автоопределить поля файла';
$_['button_apply_feed_detected'] = 'Подставить выбранные поля';
$_['text_feed_mapping_title'] = 'Наглядное сопоставление полей файла с полями OpenCart';
$_['text_feed_mapping_intro'] = 'Модуль читает образец XML/YML/CSV, сам предлагает поля для названия, кода товара, SKU, цены, наличия, описания, производителя и фото. Проверьте выбранные значения и при необходимости выберите другой вариант из списка.';
$_['engine_feed_mapping_detected'] = 'Поля файла определены. Проверьте стандартные поля OpenCart перед сохранением.';
$_['engine_feed_no_product_nodes'] = 'В XML/YML-фиде не найдены узлы товаров.';

// Supplier Sync Parser PRO v1.6.0 product/list URL guard
$_['engine_product_url_is_list'] = 'Этот URL похож на категорию или список товаров. Для автоопределения правил товара вставьте реальную карточку товара; для категории используйте поиск ссылок товаров.';

// Supplier Sync Parser PRO v1.6.0 field override
$_['text_detect_xpath_override'] = 'XPath / поле';
$_['help_detect_xpath_override'] = 'Можно вручную заменить XPath, если автоопределение выбрало не тот блок.';

// Availability policy
$_['entry_in_stock_quantity'] = 'Остаток для «в наличии»';
$_['help_in_stock_quantity'] = 'Используется только если поставщик не указал число. Это ваш условный остаток, например 100.';
$_['entry_unknown_stock_policy'] = 'Неизвестное наличие';
$_['text_stock_keep'] = 'Сохранить текущее наличие';
$_['text_stock_zero'] = 'Установить остаток 0';
$_['entry_update_stock_status'] = 'Обновлять статус наличия';
$_['help_update_stock_status'] = 'Независимо от количества. При выключении сохраняется stock_status_id товара.';

$_['entry_new_category_name'] = 'Категория для новых товаров';

$_['help_new_category_name'] = 'Если заполнено, создаётся одна выключенная категория. Выбранная принудительная категория имеет приоритет. Пустое поле сохраняет обычное распределение.';

$_['button_rollback_price_stock'] = 'Вернуть цену / остаток';

$_['confirm_rollback_price_stock'] = 'Вернуть изменённые цену и наличие? Если текущие значения отличаются от результата импорта, операция будет отклонена. Описания и новые товары не затрагиваются.';

$_['text_rollback_success'] = 'Цена и наличие восстановлены.';

$_['text_rollback_conflict'] = 'Товар изменён после импорта. Проверьте текущие значения; автоматический возврат отменён.';

$_['text_rollback_unavailable'] = 'Для этой записи возврат недоступен или уже выполнен.';

$_['text_rollback_failed'] = 'Не удалось выполнить возврат. Изменения отменены.';

$_['entry_match_source'] = 'Идентификатор поставщика';

$_['entry_match_target'] = 'Поле моего товара';

$_['help_match_mapping'] = 'Точное сопоставление. Например: SKU поставщика → MPN магазина. Пустые и неоднозначные значения требуют сверки.';

$_['text_match_auto'] = 'Все идентификаторы: проверка конфликтов';

$_['entry_jan_xpath'] = 'JAN XPath';

$_['entry_isbn_xpath'] = 'ISBN XPath';

$_['entry_cron_enabled'] = 'Обновлять профиль по cron';

$_['entry_cron_interval_minutes'] = 'Интервал проверки, минут';

$_['help_cron_profile'] = 'Cron проверяет связанные товары и применяет разрешенные цену/наличие. Новые товары и предупреждения остаются на сверку. Минимум 5 минут.';
