<?php
$_['heading_title'] = '<span style="color:#0057b7;font-weight:700;">Supplier Sync Parser</span> <span style="display:inline-block;background:#ffd700;color:#0057b7;border-radius:6px;padding:2px 10px;font-size:12px;line-height:1.2;vertical-align:middle;font-weight:700;margin-left:6px;">PRO</span>';
$_['heading_title_text'] = 'Supplier Sync Parser';
$_['text_extension'] = 'Розширення';
$_['text_home'] = 'Головна';
$_['text_edit'] = 'Налаштування модуля';
$_['text_enabled'] = 'Увімкнено';
$_['text_disabled'] = 'Вимкнено';
$_['text_select'] = 'Оберіть';
$_['text_none'] = 'Немає даних';
$_['text_yes'] = 'Так';
$_['text_no'] = 'Ні';
$_['text_status'] = 'Статус';
$_['text_author'] = 'Автор';
$_['text_compatibility'] = 'Сумісність';
$_['text_existing'] = 'Існуючий';
$_['text_new'] = 'Новий';
$_['text_excluded'] = 'Виключено';
$_['text_manual_mode'] = 'За замовчуванням зміни застосовуються після вибору рядків попереднього перегляду. Для пов’язаних товарів можна окремо ввімкнути автоматичне оновлення ціни й наявності через cron. Нові товари створюються вручну.';
$_['text_warning_backup'] = 'Перед встановленням і запуском синхронізації обов’язково зробіть повний backup файлів сайту та бази даних. Модуль може масово змінювати ціни, наявність і створювати нові товари.';
$_['text_cron_hint'] = 'Для Mirohost ставте cron не частіше одного разу на 5 хвилин. Cron обробляє чергу пакетами і не повинен запускати весь каталог одним процесом.';
$_['text_success_settings'] = 'Налаштування модуля сохранены.';
$_['text_success_supplier'] = 'Постачальник сохранен.';
$_['text_success_queue_clear'] = 'Черга очищена.';
$_['text_success_reviews_clear'] = 'Попередній перегляд очищен.';
$_['text_success_logs_clear'] = 'Логи очищено.';
$_['text_success_processing_reset'] = 'Завислі задачі processing повернено в pending.';
$_['tab_suppliers'] = 'Постачальники';
$_['tab_rules'] = 'Правила парсингу';
$_['tab_mapping'] = 'Зіставлення';
$_['tab_review'] = 'Попередній перегляд';
$_['tab_queue'] = 'Черга';
$_['tab_new'] = 'Нові товари';
$_['tab_logs'] = 'Логи';
$_['tab_diagnostics'] = 'Діагностика';
$_['tab_settings'] = 'Налаштування';
$_['tab_about'] = 'Про модуль';
$_['button_save'] = 'Зберегти';
$_['button_cancel'] = 'Скасувати';
$_['button_add_supplier'] = 'Додати постачальника';
$_['button_test'] = 'Перевірити';
$_['button_run_queue'] = 'Запустити пакет';
$_['button_clear_queue'] = 'Очистити чергу';
$_['button_reset_processing'] = 'Скинути processing';
$_['button_clear_logs'] = 'Очистити логи';
$_['button_apply_price'] = 'Оновити ціну вибраних';
$_['button_apply_stock'] = 'Оновити наявність вибраних';
$_['button_apply_price_stock'] = 'Оновити ціну та наявність';
$_['button_create_selected'] = 'Перенести вибрані до нових товарів';
$_['button_skip_selected'] = 'Пропустити вибрані';
$_['button_clear_reviews'] = 'Очистити попередній перегляд';
$_['entry_module_status'] = 'Статус модуля';
$_['entry_cron_token'] = 'Cron token';
$_['entry_cron_endpoint'] = 'Cron endpoint';
$_['entry_cron_command'] = 'Cron-команда';
$_['text_copy_cron'] = 'Скопіювати cron-команду';
$_['entry_batch_limit'] = 'Ліміт пакета';
$_['entry_supplier'] = 'Постачальник';
$_['entry_supplier_name'] = 'Назва постачальника';
$_['entry_supplier_status'] = 'Статус постачальника';
$_['entry_base_url'] = 'Базовий URL';
$_['entry_list_urls'] = 'Сторінки категорій або товарів';
$_['entry_product_url_xpath'] = 'XPath посилань товарів у категорії';
$_['entry_product_url_attr'] = 'Атрибут посилання';
$_['entry_next_page_xpath'] = 'XPath наступної сторінки';
$_['entry_model_xpath'] = 'XPath коду товару / model';
$_['entry_sku_xpath'] = 'XPath артикулу/SKU';
$_['entry_name_xpath'] = 'XPath назви';
$_['entry_price_xpath'] = 'XPath ціни';
$_['entry_stock_xpath'] = 'XPath наявності';
$_['entry_category_xpath'] = 'XPath категорії/хлібних крихт постачальника';
$_['entry_description_xpath'] = 'XPath опису';
$_['entry_manufacturer_xpath'] = 'XPath виробника';
$_['entry_image_xpath'] = 'XPath головного фото';
$_['entry_additional_images_xpath'] = 'XPath додаткових фото';
$_['entry_image_attr'] = 'Атрибут фото';
$_['entry_source_language'] = 'Мова постачальника';
$_['entry_target_language'] = 'Мова OpenCart для імпорту';
$_['entry_currency'] = 'Валюта';
$_['entry_discount'] = 'Знижка постачальника, %';
$_['entry_markup'] = 'Моя націнка, %';
$_['entry_rounding'] = 'Округлення';
$_['entry_category'] = 'Категорія нових товарів';
$_['entry_force_new_category'] = 'Категорія імпорту нових товарів';
$_['entry_stock_status'] = 'Статус за відсутності';
$_['entry_create_new'] = 'Дозволити створення нових';
$_['entry_create_new_status'] = 'Статус нових товарів';
$_['entry_auto_apply_existing'] = 'Автооновлювати знайдені';
$_['entry_auto_create_new'] = 'Автостворювати нові';
$_['entry_update_price'] = 'Дозволити оновлювати ціну';
$_['entry_update_stock'] = 'Дозволити оновлювати наявність';
$_['entry_delay'] = 'Пауза між запитами, мс';
$_['entry_user_agent'] = 'User-Agent';
$_['entry_stock_map'] = 'Карта наявності';
$_['entry_category_map'] = 'Карта категорій постачальника';
$_['entry_test_url'] = 'Тестовий URL товару';
$_['column_supplier'] = 'Постачальник';
$_['column_status'] = 'Статус';
$_['column_url'] = 'URL';
$_['column_product'] = 'Товар';
$_['column_supplier_product'] = 'Товар постачальника';
$_['column_local_product'] = 'Мій товар';
$_['column_sku'] = 'SKU';
$_['column_price'] = 'Ціна';
$_['column_supplier_price'] = 'Ціна постачальника';
$_['column_local_price'] = 'Моя ціна';
$_['column_new_price'] = 'Нова ціна';
$_['column_stock'] = 'Наявність';
$_['column_supplier_stock'] = 'Наявність постачальника';
$_['column_local_stock'] = 'Моя наявність';
$_['column_quantity'] = 'К-сть';
$_['column_checked'] = 'Перевірено';
$_['column_error'] = 'Помилка';
$_['column_action'] = 'Дія';
$_['column_date'] = 'Такта';
$_['column_level'] = 'Рівень';
$_['column_message'] = 'Повідомлення';
$_['column_total'] = 'Усього';
$_['column_pending'] = 'Очікує';
$_['column_processing'] = 'У роботі';
$_['column_done'] = 'Готово';
$_['column_skipped'] = 'Пропущено';
$_['column_type'] = 'Тип';
$_['column_category'] = 'Категорія';
$_['column_match'] = 'Збіг';
$_['help_xpath'] = 'Правила задаються через XPath. Перед масовим запуском обов’язково перевірте один товар у вкладці Діагностика. Якщо сайт постачальника змінить верстку, XPath потрібно оновити.';
$_['help_stock_map'] = 'Формат: текст постачальника|кількість|stock_status_id. Наприклад: в наявності|99 або немає в наявності|0. Один варіант на рядок.';
$_['help_category_map'] = 'Формат: категорія постачальника|category_id OpenCart|1 або 0. Значення 1 дозволяє імпорт, 0 виключає категорію. Наприклад: Запчастини|25|1 або Сувеніри|0|0.';
$_['help_existing_safe'] = 'Для існуючих товарів модуль за замовчуванням оновлює тільки ціну та наявність. Назва, опис, фото, SEO URL і метатеги не перезаписуються.';
$_['help_new_disabled'] = 'Безпечний режим: нові товари краще створювати вимкненими, щоб перевірити категорії, фото, ціну та опис перед публікацією.';
$_['help_review'] = 'Спочатку запустіть чергу. Модуль збереже знайдені товари у попередній перегляд: який товар знайдено у постачальника, який товар знайдено у вас, поточна ціна, нова ціна та наявність. Після цього виберіть рядки і застосуйте тільки потрібну дію.';
$_['help_cron'] = 'Для Mirohost запускайте цю команду не частіше одного разу на 5 хвилин. Секрет передається в HTTP-заголовку X-CCP-Cron-Key і не публікується в URL. Існуючі cron-завдання з ?token= зберігаються як legacy fallback.';
$_['diagnostics_text'] = 'Перевірка тестового URL показує, які дані модуль зміг отримати за поточними XPath-правилами: назву, SKU, ціну, наявність, категорію, фото та опис.';
$_['error_permission'] = 'У вас немає прав для зміни модуля.';
$_['error_supplier_name'] = 'Вкажіть назву постачальника.';
$_['button_force_price'] = 'Примусово ціну';
$_['button_force_price_stock'] = 'Примусово ціну і наявність';
$_['button_search_product'] = 'Знайти';
$_['button_match_product'] = 'Зв’язати';
$_['button_create_new_tab'] = 'Створити вибрані товари в OpenCart';
$_['button_filter_reset'] = 'Скинути фільтр';
$_['entry_list_category_xpath'] = 'XPath категорії на сторінці списку';
$_['entry_max_price_change'] = 'Макс. зміна ціни, %';
$_['entry_filter_status'] = 'Фільтр за статусом';
$_['entry_filter_type'] = 'Фільтр за типом';
$_['entry_filter_change'] = 'Фільтр за зміною';
$_['entry_product_search'] = 'Назва, SKU або ID';
$_['entry_manual_product_id'] = 'Виберіть товар';
$_['column_delta'] = 'Відхилення';
$_['help_price_warning'] = 'Якщо нова ціна відрізняється від поточної більше допустимого відсотка, рядок отримує статус price_warning. Звичайне оновлення ціни такий рядок не застосовує. Використовуйте примусову дію тільки після ручної перевірки.';
$_['tab_history'] = 'Історія';
$_['button_clear_history'] = 'Очистити історію';
$_['text_success_history_clear'] = 'Історію очищено';
$_['help_history'] = 'Історія фіксує кожну ручну або автоматичну зміну ціни та наявності: було, стало, джерело і дія. Це допомагає перевірити наслідки синхронізації та швидко знайти помилкові оновлення.';
$_['column_old_price'] = 'Стара ціна';
$_['column_new_price_history'] = 'Нова ціна';
$_['column_old_quantity'] = 'Стара к-сть';
$_['column_new_quantity'] = 'Нова к-сть';
$_['column_field'] = 'Поле';
$_['column_note'] = 'Примітка';
$_['text_price_changed'] = 'Ціна змінилася';
$_['text_stock_changed'] = 'Наявність змінилася';
$_['text_price_stock_changed'] = 'Ціна і наявність';
$_['text_same'] = 'Без змін';
$_['text_rows_shown'] = 'Показано рядків';
$_['text_sort_hint'] = 'Натисніть на заголовок колонки для сортування';
$_['button_only_changed'] = 'Тільки змінені';
$_['button_toggle_match'] = 'Зв’язати вручну';
$_['entry_filter_text'] = 'Пошук у таблиці';
$_['entry_filter_supplier'] = 'Фільтр за постачальником';
$_['entry_filter_stock'] = 'Фільтр за наявністю';
$_['column_changed'] = 'Зміна';

$_['button_recover_errors'] = 'Відновити помилки';
$_['button_full_update'] = 'Повне оновлення';
$_['text_success_errors_recovered'] = 'Помилки черги повернено в очікування';
$_['text_missing_policy_report'] = 'Тільки звіт';
$_['text_missing_policy_out'] = 'Поставити немає в наявності';
$_['text_missing_policy_disable'] = 'Вимкнути товар';
$_['text_missing_policy_zero'] = 'Кількість 0';
$_['text_formula_same'] = 'Ціна як у постачальника';
$_['text_formula_purchase_markup'] = 'Закупівельна + націнка';
$_['text_formula_retail_discount_markup'] = 'Ціна постачальника - знижка + націнка';
$_['entry_ean_xpath'] = 'XPath EAN';
$_['entry_upc_xpath'] = 'XPath UPC';
$_['entry_mpn_xpath'] = 'XPath MPN';
$_['entry_attribute_row_xpath'] = 'XPath рядка характеристики';
$_['entry_attribute_name_xpath'] = 'XPath назви характеристики';
$_['entry_attribute_value_xpath'] = 'XPath значення характеристики';
$_['entry_option_row_xpath'] = 'XPath рядка опції';
$_['entry_option_name_xpath'] = 'XPath назви опції';
$_['entry_option_value_xpath'] = 'XPath значення опції';
$_['entry_meta_description_xpath'] = 'XPath meta description';
$_['entry_meta_keyword_xpath'] = 'XPath meta keywords';
$_['entry_price_formula_mode'] = 'Формула ціни';
$_['entry_min_margin'] = 'Мін. маржа, %';
$_['entry_missing_policy'] = 'Якщо товар зник';
$_['entry_existing_description_mode'] = 'Опис існуючого товару';
$_['entry_import_attributes'] = 'Імпорт характеристик';
$_['entry_import_options'] = 'Імпорт опцій';
$_['entry_fill_all_languages'] = 'Заповнювати всі мови';
$_['entry_require_test_success'] = 'Вимагати успішний тест';
$_['entry_excluded_skus'] = 'Виключені SKU';
$_['entry_excluded_urls'] = 'Виключені URL';
$_['entry_excluded_categories'] = 'Виключені категорії';
$_['column_purchase_price'] = 'Закупівельна ціна';
$_['column_run'] = 'Запуск';
$_['column_created'] = 'Створено';
$_['column_updated'] = 'Оновлено';
$_['help_exclusions'] = 'Кожне значення з нового рядка. Виключення блокують створення або оновлення знайдених товарів за SKU, URL або категорією.';
$_['help_missing_policy'] = 'За замовчуванням безпечніше залишати тільки звіт. Автоматичне вимкнення або встановлення кількості 0 вмикайте тільки після перевірки постачальника.';
$_['help_full_update'] = 'Повне оновлення застосовує ціну і наявність, а опис/характеристики/опції оновлює тільки за вибраними налаштуваннями постачальника.';

$_['button_stop_queue'] = 'Зупинити чергу';
$_['button_resume_queue'] = 'Відновити чергу';
$_['button_exclude_selected'] = 'Виключити вибрані';
$_['text_queue_stop_active'] = 'Обробку черги зараз зупинено вручну. Відновіть її перед запуском cron або пакетної обробки.';
$_['text_success_queue_stopped'] = 'Чергу зупинено. Очікувані завдання збережено.';
$_['text_success_queue_resumed'] = 'Чергу відновлено.';
$_['text_success_excluded'] = 'Вибрані рядки додано до виключень.';
// v1.0.9
$_['text_server_filter_hint'] = 'Серверна вибірка активна: фільтри та ліміти застосовуються на рівні бази даних, а не тільки в браузері.';
$_['text_success_cleanup'] = 'Старі дані очищено згідно зі строками зберігання.';
$_['text_formula_preview'] = 'Перевірка формули ціни';
$_['text_category_map_visual'] = 'Візуальна карта категорій постачальника';
$_['text_pagination_summary'] = 'Показано рядки поточної серверної вибірки.';
$_['button_run_until_done'] = 'Запустити до завершення';
$_['button_cleanup_old_data'] = 'Очистити старі дані';
$_['button_scan_new_only'] = 'Знайти тільки нові';
$_['entry_new_tax_class_id'] = 'Tax class ID для нових';
$_['entry_new_minimum'] = 'Мінімум для нових';
$_['entry_new_subtract'] = 'Віднімати склад';
$_['entry_new_shipping'] = 'Потрібна доставка';
$_['entry_new_sort_order'] = 'Сортування нових';
$_['entry_new_store_id'] = 'Store ID нових';
$_['entry_seo_url_mode'] = 'SEO URL для нових';
$_['entry_min_new_price'] = 'Мін. ціна нового товару';
$_['entry_max_new_price'] = 'Макс. ціна нового товару';
$_['entry_review_retention'] = 'Зберігати перегляд, днів';
$_['entry_log_retention'] = 'Зберігати логи, днів';
$_['entry_history_retention'] = 'Зберігати історію, днів';
// v1.0.9 extended progress
$_['text_eta'] = 'ETA';
$_['text_speed_15m'] = 'Швидкість за 15 хв';
$_['text_matched'] = 'Зіставлено';
$_['text_unmatched'] = 'Не зіставлено';
$_['text_last_url'] = 'Останній URL';
$_['text_locked'] = 'Lock';
$_['text_remaining'] = 'Залишилось';
$_['text_completed'] = 'Завершено';
$_['text_found_preview'] = 'У передперегляді';
$_['text_new_pending'] = 'Нові очікують';
$_['text_new_created'] = 'Нові створені';
$_['button_page_first'] = 'Перша';
$_['button_page_prev'] = 'Назад';
$_['button_page_next'] = 'Вперед';
$_['button_page_last'] = 'Остання';
$_['button_apply_server_filters'] = 'Застосувати фільтр';


// v1.1.1 UI/UX
$_['text_ui_ajax_status'] = 'AJAX увімкнено для робочих дій: тест URL, побудова черги, запуск пакетів, пауза, відновлення, відновлення помилок, застосування вибраних рядків, створення нових товарів, очищення логів та історії.';
$_['text_ui_safe_note'] = 'Збереження основних налаштувань виконано стандартним POST OpenCart, а важкі операції виконуються через AJAX-пакети, щоб не скидати сторінку і не запускати весь каталог одним процесом.';
$_['text_ui_admin_ready'] = 'Інтерфейс згруповано за змістом: постачальники, правила, перегляд, зіставлення, черга, нові товари, історія, логи, діагностика і налаштування.';
$_['text_ajax_working'] = 'Виконується AJAX-запит...';
$_['text_ajax_done'] = 'Готово';
$_['help_field_base_url'] = 'Головний домен постачальника. Використовується для перетворення відносних посилань і зображень у повний URL.';
$_['help_field_list_urls'] = 'Додайте сторінки категорій або списків товарів, по одному посиланню в рядку. Для регулярного оновлення пов’язаних товарів не обов’язково щоразу сканувати всі категорії.';
$_['help_field_currency'] = 'Валюта вибирається тільки з активних валют OpenCart. Ціна постачальника автоматично очищається від пробілів, ком, крапок, символів і назв валют, потім приводиться до числового формату OpenCart через курс валюти.';
$_['help_field_price_formula'] = 'Формула працює з числом після нормалізації ціни та конвертації в базову валюту OpenCart: ціна постачальника, знижка, націнка, мінімальна маржа і округлення. Перевірте один товар перед масовим застосуванням.';
$_['help_field_language'] = 'Автовизначення мови не використовується як джерело істини. Виберіть мову постачальника для контролю і мову OpenCart, куди записувати назву, опис, SEO, атрибути та опції. Якщо увімкнено заповнення всіх мов, той самий текст буде скопійований у всі активні мови без перекладу.';
$_['help_field_force_new_category'] = 'Якщо вибрана ця категорія, усі нові товари постачальника будуть створюватися в ній незалежно від категорії на сайті постачальника. Якщо налаштування порожнє, категорію можна вибрати окремо для кожного рядка у вкладці «Нові товари».';
$_['help_field_delay'] = 'Пауза між запитами знижує навантаження на ваш сайт і сайт постачальника. Для великих каталогів безпечніше 500-1500 мс.';
$_['help_field_create_new'] = 'Для вашого сценарію безпечніше створювати нові товари вимкненими і публікувати тільки після перевірки.';
$_['help_field_xpath_required'] = 'Критичні XPath: посилання товару у списку, назва, ціна і наявність. SKU/EAN/UPC значно підвищують точність зіставлення.';
$_['help_field_exclusions'] = 'Виключення потрібні, якщо ви продаєте не весь асортимент постачальника. Виключений товар не буде створений або оновлений.';
$_['text_rounding_two'] = '2 знаки';
$_['text_rounding_integer'] = 'Ціле';
$_['text_rounding_up_integer'] = 'Вгору до цілого';
$_['text_rounding_none'] = 'Без округлення';
$_['text_no_selected_rows'] = 'Не вибрано рядки';
$_['text_done'] = 'Готово';
$_['text_loading'] = 'Завантаження...';
$_['text_ajax_error'] = 'Помилка AJAX:';
$_['text_created'] = 'Створено';
$_['text_updated'] = 'Оновлено';
$_['text_skipped'] = 'Пропущено';
$_['text_errors'] = 'Помилки';
$_['text_processed'] = 'Оброблено';
$_['text_preview'] = 'Перегляд';
$_['text_price_short'] = 'Ціна';
$_['text_stock_short'] = 'Наявність';
$_['text_running'] = 'Виконується...';
$_['text_confirm_force_price'] = 'Примусове оновлення ціни може застосувати підозрілі зміни. Продовжуйте тільки після ручної перевірки.';
$_['text_confirm_full_update'] = 'Повне оновлення може змінити опис, атрибути або опції згідно з налаштуваннями постачальника. Продовжити?';
$_['text_confirm_create_new'] = 'Буде створено вибрані нові товари. Безпечніше створювати їх вимкненими для ручної перевірки. Продовжити?';
$_['text_confirm_clear'] = 'Ця дія очищає дані. Продовжити?';
$_['text_saved_tab_note'] = 'Після збереження модуль повертається на поточну вкладку.';
$_['error_module_disabled'] = 'Модуль вимкнено. Увімкніть модуль, збережіть налаштування і тільки після цього запускайте чергу, оновлення цін/наявності або створення товарів.';
$_['text_queue_loop_already_running'] = 'Процес уже запущено в цьому вікні. Для зупинки натисніть «Зупинити чергу».';
$_['text_status_pending'] = 'Очікує';
$_['text_status_processing'] = 'В обробці';
$_['text_status_done'] = 'Готово';
$_['text_status_price_warning'] = 'Попередження ціни';
$_['text_status_duplicate'] = 'Можливий дубль';
$_['text_status_excluded'] = 'Виключено';
$_['text_status_applied'] = 'Застосовано';
$_['text_status_created'] = 'Створено';
$_['text_status_skipped'] = 'Пропущено';
$_['text_status_error'] = 'Помилка';
$_['text_status_linked'] = 'Зв’язано';
$_['text_type_existing'] = 'Існуючий';
$_['text_type_new'] = 'Новий';
$_['text_seo_all_languages'] = 'Усі мови';
$_['text_seo_main_language'] = 'Тільки основна мова';
$_['text_mode_keep'] = 'Не змінювати';
$_['text_mode_update_empty'] = 'Заповнити тільки порожнє';
$_['text_mode_overwrite'] = 'Перезаписати';
$_['text_version'] = 'Версія';
$_['text_license'] = 'Ліцензія';
$_['text_license_note'] = 'Ліцензування поки не впроваджено. Після встановлення модуль вимкнений за замовчуванням.';
$_['column_opencart_id'] = 'OpenCart ID';
$_['button_auto_detect'] = 'Автовизначити';
$_['button_save_detected_rules'] = 'Зберегти вибрані правила';
$_['button_apply_detected_to_form'] = 'Підставити у поля форми';
$_['text_auto_detect_title'] = 'Автовизначення правил постачальника';
$_['text_auto_detect_intro'] = 'Модуль аналізує одну сторінку товару та пропонує можливі XPath-правила. Це помічник, а не автоматичне застосування: перед збереженням обов’язково перевірте ціну, наявність, назву та SKU.';
$_['text_auto_detect_url'] = 'URL товару для аналізу';
$_['text_auto_detect_confidence'] = 'Впевненість';
$_['text_auto_detect_source'] = 'Джерело';
$_['text_auto_detect_value'] = 'Знайдене значення';
$_['text_auto_detect_xpath'] = 'XPath';
$_['text_auto_detect_field'] = 'Поле';
$_['text_auto_detect_select'] = 'Вибране правило';
$_['text_auto_detect_high'] = 'Висока';
$_['text_auto_detect_medium'] = 'Середня';
$_['text_auto_detect_low'] = 'Низька';
$_['text_auto_detect_none'] = 'Не знайдено';
$_['text_success_detected_rules_saved'] = 'Вибрані правила постачальника збережено. Виконайте тест URL перед масовим запуском.';
$_['error_supplier_required'] = 'Спочатку виберіть або збережіть постачальника.';
$_['help_auto_detect'] = 'Краще вставити реальну картку товару постачальника. Якщо знайдено кілька цін, вибирайте основну ціну товару, а не стару ціну, ціну доставки або рекомендовані товари.';

// Supplier Sync Parser PRO v1.5.2 UX, diagnostics and localization additions
$_['text_base_currency'] = 'базова';
$_['text_supplier_next_steps_title'] = 'Що робити далі:';
$_['text_supplier_next_steps_desc'] = 'збережіть постачальника, відкрийте Діагностику, вставте реальну картку товару, натисніть Автовизначити, підставте правила у форму, збережіть і виконайте Перевірити. Тільки після успішної перевірки запускайте чергу.';
$_['text_queue_quick_start'] = 'Швидкий запуск:';
$_['text_queue_mode_scan'] = 'Побудувати чергу сканує сторінки з поля «Сторінки категорій або товарів».';
$_['text_queue_mode_linked'] = 'Перевірити пов’язані товари оновлює вже зіставлені товари без повторного обходу всіх категорій.';
$_['text_queue_mode_new_only'] = 'Знайти тільки нові шукає товари, яких ще немає в OpenCart.';
$_['text_ajax_invalid_json'] = 'Відповідь сервера не є чистим JSON. Фрагмент відповіді:';
$_['text_ajax_invalid_response'] = 'Некоректна відповідь сервера.';
$_['error_test_url_required'] = 'Вкажіть реальний URL картки товару постачальника або збережіть його в полі «Сторінки категорій або товарів».';
$_['error_diagnostics_failed'] = 'Діагностику не виконано';
$_['engine_ok'] = 'OK';
$_['engine_done'] = 'Готово';
$_['engine_invalid_url'] = 'Некоректний URL.';
$_['engine_invalid_product_url'] = 'Некоректний URL товару.';
$_['engine_supplier_not_found'] = 'Постачальника не знайдено.';
$_['engine_cannot_parse_html'] = 'HTML сторінки не вдалося розібрати.';
$_['engine_product_name_not_found'] = 'Назву товару не знайдено за поточним XPath.';
$_['engine_product_price_not_found'] = 'Ціну товару не знайдено за поточним XPath.';
$_['engine_calculated_price_invalid'] = 'Розрахована ціна продажу некоректна.';
$_['engine_auto_detection_completed'] = 'Автовизначення виконано. Перевірте знайдені правила перед збереженням.';
$_['engine_fetch_failed'] = 'Не вдалося отримати сторінку:';
$_['engine_http_error'] = 'HTTP-помилка:';
$_['engine_product_price_invalid'] = 'Ціна товару некоректна:';
$_['engine_supplier_currency_inactive'] = 'Валюта постачальника не активна в OpenCart:';
$_['field_label_name_xpath'] = 'Назва товару';
$_['field_label_price_xpath'] = 'Ціна';
$_['field_label_stock_xpath'] = 'Наявність';
$_['field_label_sku_xpath'] = 'Артикул / SKU';
$_['field_label_ean_xpath'] = 'EAN';
$_['field_label_upc_xpath'] = 'UPC';
$_['field_label_mpn_xpath'] = 'MPN';
$_['field_label_manufacturer_xpath'] = 'Виробник / бренд';
$_['field_label_category_xpath'] = 'Категорія / хлібні крихти';
$_['field_label_description_xpath'] = 'Опис';
$_['field_label_meta_description_xpath'] = 'Meta description';
$_['field_label_meta_keyword_xpath'] = 'Meta keywords';
$_['field_label_image_xpath'] = 'Головне фото';
$_['field_label_additional_images_xpath'] = 'Додаткові фото';
$_['field_label_attribute_row_xpath'] = 'Рядки характеристик';
$_['field_label_option_row_xpath'] = 'Рядки опцій';
$_['auto_source_attribute_specification_table_rows'] = 'Рядки таблиці характеристик';
$_['auto_source_brand_manufacturer_class'] = 'Клас бренду/виробника';
$_['auto_source_breadcrumb_class'] = 'Клас хлібних крихт';
$_['auto_source_breadcrumb_id'] = 'ID хлібних крихт';
$_['auto_source_description_class'] = 'Клас опису';
$_['auto_source_description_id'] = 'ID опису';
$_['auto_source_ean_gtin_class'] = 'Клас EAN/GTIN';
$_['auto_source_gallery_image_src'] = 'Фото галереї src';
$_['auto_source_gallery_lazy_image'] = 'Ліниве фото галереї';
$_['auto_source_lazy_product_image_data_src'] = 'Ліниве фото товару data-src';
$_['auto_source_mpn_class'] = 'Клас MPN';
$_['auto_source_main_h1_heading'] = 'Головний заголовок H1';
$_['auto_source_meta_description'] = 'Meta description';
$_['auto_source_meta_keywords'] = 'Meta keywords';
$_['auto_source_opengraph_image'] = 'OpenGraph фото';
$_['auto_source_opengraph_title'] = 'OpenGraph title';
$_['auto_source_option_variant_blocks'] = 'Блоки опцій/варіантів';
$_['auto_source_product_description_class'] = 'Клас опису товару';
$_['auto_source_product_image_src'] = 'Фото товару src';
$_['auto_source_product_title_class'] = 'Клас назви товару';
$_['auto_source_product_title_id'] = 'ID назви товару';
$_['auto_source_sku_model_class'] = 'Клас SKU/моделі';
$_['auto_source_sku_model_id'] = 'ID SKU/моделі';
$_['auto_source_select_options_variants'] = 'Опції/варіанти select';
$_['auto_source_specification_rows'] = 'Рядки специфікацій';
$_['auto_source_stock_availability_class'] = 'Клас наявності';
$_['auto_source_stock_availability_id'] = 'ID наявності';
$_['auto_source_thumbnail_image_src'] = 'Мініатюра src';
$_['auto_source_upc_class'] = 'Клас UPC';
$_['auto_source_visible_price_block'] = 'Видимий блок ціни';
$_['auto_source_visible_price_id'] = 'Видимий ID ціни';
$_['auto_source_availability_link'] = 'Посилання availability';
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
$_['auto_source_exact_name'] = 'Точна назва';
$_['auto_source_missing'] = 'Відсутнє';
$_['auto_source_none'] = 'Немає';
$_['auto_source_product'] = 'Товар';
$_['auto_source_product_model_sku_ean_upc'] = 'Товар: model/SKU/EAN/UPC';
$_['auto_source_product_sku_model_ean_upc'] = 'Товар: SKU/model/EAN/UPC';
$_['auto_source_supplier_sku_link'] = 'Зв’язка за SKU постачальника';
$_['auto_source_supplier_url_link'] = 'Зв’язка за URL постачальника';

$_['text_queue_mode_product_urls'] = 'Додати URL товарів у чергу підходить, якщо в полі вказані прямі посилання на картки товарів, а не категорія.';

$_['engine_module_disabled'] = 'Модуль вимкнено.';
$_['engine_queue_stopped'] = 'Чергу зупинено вручну.';
$_['engine_linked_queue_created'] = 'Чергу перевірки пов’язаних товарів створено.';
$_['engine_product_url_queue_created'] = 'Чергу прямих URL товарів створено.';
$_['engine_list_scan_queue_created'] = 'Чергу сканування списку створено.';
$_['engine_test_required'] = 'Масовий імпорт заблоковано до успішної перевірки постачальника.';
$_['engine_missing_name_xpath'] = 'Не вказано обов’язковий XPath назви товару.';
$_['engine_missing_price_xpath'] = 'Не вказано обов’язковий XPath ціни товару.';
$_['engine_supplier_urls_empty'] = 'Список URL постачальника порожній.';
$_['engine_missing_policy_stock_status_required'] = 'Для політики відсутнього товару потрібен статус наявності за замовчуванням.';

// Supplier Sync Parser PRO v1.5.2 rule wizard and switch UI
$_['button_scan_save_rules'] = 'Сканувати і зберегти найкращі правила';
$_['button_rules_manual_toggle'] = 'Показати ручні XPath-поля';
$_['text_rules_quick_setup_title'] = 'Швидке налаштування за сторінкою товару';
$_['text_rules_quick_setup_intro'] = 'Вручну писати XPath зазвичай не потрібно. Вставте одне реальне посилання на картку товару постачальника, і модуль просканує сторінку, знайде назву, ціну, наявність, SKU, фото та опис, потім запропонує правила. Це посилання потрібне як зразок структури сайту постачальника.';
$_['text_rules_quick_setup_note'] = 'Це має бути саме картка товару, а не категорія. Після сканування перевірте знайдену ціну та назву, потім збережіть правила.';
$_['text_rules_manual_title'] = 'Ручні XPath-поля';
$_['text_rules_manual_intro'] = 'Заповнюйте вручну тільки якщо автовизначення не знайшло потрібний блок або сайт постачальника має нестандартну верстку.';
$_['text_switch_on'] = 'Увімкнути';
$_['text_switch_off'] = 'Вимкнути';

// v1.5.2 simplified supplier workflow
$_['button_scan_product_page'] = 'Сканувати картку товару';
$_['button_detect_list_links'] = 'Знайти посилання товарів у категорії';
$_['text_rules_product_scan_label'] = '1. Посилання на картку товару для визначення полів';
$_['text_rules_list_scan_label'] = '2. Посилання на категорію/список для пошуку посилань товарів';
$_['help_rules_product_scan'] = 'Вставте одну реальну картку товару. Модуль знайде назву, ціну, наявність, SKU, фото та опис.';
$_['help_rules_list_scan'] = 'Потрібно тільки якщо хочете сканувати категорії постачальника. Модуль знайде XPath посилань товарів на сторінці списку.';
$_['text_auto_detect_best_hint'] = 'Модуль автоматично вибирає найкращий варіант. Якщо знайдене значення неправильне, відкрийте список у рядку поля та виберіть інший варіант, потім збережіть вибрані правила.';
$_['button_delete'] = 'Видалити';
$_['button_build_queue'] = 'Сканувати категорії';
$_['button_build_product_urls'] = 'Додати прямі URL товарів';
$_['button_check_linked'] = 'Перевірити вже зіставлені';
$_['text_confirm_delete_supplier'] = 'Видалити постачальника та пов’язані з ним чергу, передперегляд, нові товари, історію і логи?';
$_['text_success_supplier_deleted'] = 'Постачальника видалено.';
$_['text_success_supplier_status_changed'] = 'Статус постачальника змінено.';
$_['text_queue_scenario_title'] = 'Як запускати:';
$_['text_queue_scenario_direct'] = 'якщо в постачальнику вставлені прямі посилання на картки товарів — натисніть «Додати прямі URL товарів»;';
$_['text_queue_scenario_scan'] = 'якщо вставлені сторінки категорій — спочатку в правилах знайдіть XPath посилань товарів, потім натисніть «Сканувати категорії»;';
$_['text_queue_scenario_linked'] = 'якщо товари вже зіставлені — використовуйте «Перевірити вже зіставлені»;';
$_['text_queue_scenario_run'] = 'після створення черги натисніть «Запустити пакет» або «Запустити до завершення».';
$_['engine_list_auto_detection_completed'] = 'Посилання товарів на сторінці списку знайдено. Перевірте знайдені URL і збережіть вибрані правила.';
$_['engine_missing_product_url_xpath'] = 'Ви натиснули сканування категорії/списку. Для цього потрібен XPath посилань товарів на сторінці списку. Відкрийте вкладку «Правила парсингу», вставте URL категорії в поле «Посилання на категорію/список» і натисніть «Знайти посилання товарів у категорії». Якщо у вас прямі посилання на картки товарів, використовуйте кнопку «Додати прямі URL товарів».';
$_['field_label_product_url_xpath'] = 'Посилання товарів у категорії';
$_['field_label_next_page_xpath'] = 'Наступна сторінка категорії';

// Supplier Sync Parser PRO v1.5.2 workflow and large data UX
$_['text_check_supplier_saved'] = 'Постачальник збережений';
$_['text_check_base_url'] = 'Базовий домен';
$_['text_check_product_rules'] = 'Назва і ціна';
$_['text_check_queue_source'] = 'Джерело черги';
$_['text_ready'] = 'Готово';
$_['text_not_ready'] = 'Потрібно заповнити';
$_['text_ready_category_scan'] = 'Скан категорії';
$_['text_ready_direct_urls'] = 'Прямі URL';
$_['text_rules_step_product_url'] = 'Вставте картку товару';
$_['text_rules_step_choose_values'] = 'Перевірте знайдені значення';
$_['text_rules_step_save_rules'] = 'Збережіть вибрані правила';
$_['text_rules_step_test'] = 'Виконайте перевірку';
$_['text_queue_group_create'] = 'Створити чергу';
$_['text_queue_group_process'] = 'Обробити чергу';
$_['text_queue_group_service'] = 'Обслуговування';
$_['text_queue_group_process_help'] = 'Спочатку безпечно знайдіть посилання товарів пакетами, потім перевірте товари пакетами. Так можна побачити, які URL додані, і не запускати одразу тисячі перевірок.';
$_['text_queue_group_service_help'] = 'Використовуйте відновлення помилок і скидання обробки лише якщо черга перервалася або зависла.';
$_['text_ajax_table_updated'] = 'Таблицю оновлено через AJAX';
$_['text_about_benefit_sync_title'] = 'Контроль цін і наявності';
$_['text_about_benefit_sync'] = 'Модуль допомагає регулярно перевіряти сайти постачальників і знаходити зміни ціни, наявності та нові товари.';
$_['text_about_benefit_safe_title'] = 'Безпечне застосування';
$_['text_about_benefit_safe'] = 'Дані спочатку потрапляють у попередній перегляд. Адміністратор вибирає рядки і дію: ціна, наявність, повне оновлення або створення нового товару.';
$_['text_about_benefit_mass_title'] = 'Робота з великими каталогами';
$_['text_about_benefit_mass'] = 'Черга, пакетна обробка, прогрес, фільтри, сортування, серверна пагінація і вибір 50/100/200/500 рядків дозволяють працювати з тисячами товарів.';
$_['text_about_benefit_lang_title'] = 'Мови OpenCart';
$_['text_about_benefit_lang'] = 'Для постачальника вибирається мова джерела і цільова мова OpenCart. Інші мови не перезаписуються без окремого налаштування.';
$_['text_about_workflow_title'] = 'Послідовність роботи';
$_['text_about_step_1'] = 'Створіть постачальника, вкажіть базовий URL, валюту, мову і сторінки категорій або прямі посилання товарів.';
$_['text_about_step_2'] = 'У вкладці правил вставте одну картку товару, проскануйте її, перевірте знайдені значення і збережіть правила.';
$_['text_about_step_4'] = 'Створіть чергу, обробіть її пакетами і перевірте результати у попередньому перегляді та нових товарах.';
$_['text_about_step_5'] = 'Виберіть рядки і застосуйте тільки потрібну дію: оновити ціну, наявність, ціну і наявність, зіставити товар або створити новий.';
$_['about_text'] = 'Supplier Sync Parser PRO потрібен для магазинів OpenCart/ocStore, які отримують товари і ціни з сайтів постачальників без XML, CSV або API. Модуль не змінює каталог одразу: він збирає дані через HTML-парсинг, нормалізує ціни і наявність, зіставляє знайдені позиції з товарами магазину і показує результат у безпечному попередньому перегляді. Це зменшує ручну роботу, допомагає швидше оновлювати тисячі товарів і знижує ризик випадково зіпсувати ціни або картки.';

// Supplier Sync Parser PRO v1.5.2 supplier scope and domain guard
$_['text_supplier_context_title'] = 'Робочий постачальник';
$_['text_supplier_context_help'] = 'Усі вкладки після «Постачальники» працюють тільки з вибраним постачальником: свої правила, черга, попередній перегляд, нові товари, історія та логи.';
$_['text_supplier_context_required'] = 'Спочатку виберіть або збережіть постачальника.';
$_['text_domain_policy_title'] = 'Доменний захист';
$_['text_domain_policy_strict'] = 'Тільки базовий домен постачальника';
$_['text_domain_policy_subdomains'] = 'Базовий домен і його піддомени';
$_['text_domain_policy_extra'] = 'Базовий домен і вказані додаткові домени';
$_['entry_domain_policy'] = 'Доменний захист';
$_['entry_allowed_hosts'] = 'Додаткові HTML-домени';
$_['help_domain_policy'] = 'За замовчуванням модуль сканує тільки базовий домен постачальника і не переходить на чужі сайти. Зовнішні CDN-зображення можуть завантажуватися як зображення, але не ставляться в чергу як товари. Додаткові домени вказуйте тільки свідомо, по одному хосту в рядку.';
$_['engine_url_outside_domain'] = 'URL поза дозволеним доменом постачальника:';

// Supplier Sync Parser PRO v1.5.2 scan workflow
$_['button_scan_site_all'] = 'Сканувати сайт і знайти посилання товарів';
$_['text_queue_action_scan_list'] = 'Пошук посилань';
$_['text_queue_action_check_product'] = 'Перевірка товару';
$_['text_queue_open_preview_hint'] = 'Після обробки знайдені товари зʼявляться у попередньому перегляді, а незіставлені - у вкладці нових товарів.';
$_['engine_site_scan_queue_created'] = 'Чергу сканування сайту створено.';

// Supplier Sync Parser PRO v1.5.2 scan and warning fixes
$_['button_scan_sitemap'] = 'Знайти товари через sitemap';
$_['engine_sitemap_queue_created'] = 'Чергу товарів із sitemap створено.';
$_['engine_sitemap_no_products'] = 'Sitemap не дав посилань товарів.';
$_['engine_sitemap_check_hint'] = 'Перевірте доступність sitemap або використайте сканування категорії.';
$_['engine_product_parse_failed'] = 'Помилка обробки товару';
$_['engine_price_lower_guard'] = 'Розрахована ціна нижча за мінімальне обмеження.';
$_['engine_price_higher_guard'] = 'Розрахована ціна вища за максимальне обмеження.';
$_['text_queue_group_create_help'] = 'Якщо вказана категорія постачальника, натисніть «Сканувати категорії». Якщо вказаний весь сайт або головна сторінка, використовуйте «Сканувати сайт». Якщо на сайті є sitemap, кнопка «Знайти товари через sitemap» зазвичай знаходить більше товарів. Якщо вставлені прямі посилання на картки, використовуйте «Додати прямі URL товарів». Результат завжди спочатку потрапляє у попередній перегляд.';
$_['text_about_step_3'] = 'Якщо потрібен збір усього сайту, спочатку спробуйте «Знайти товари через sitemap», потім «Сканувати сайт». Для однієї категорії використовуйте «Сканувати категорії». Для готового списку карток використовуйте «Додати прямі URL товарів».';
// Supplier Sync Parser PRO v1.5.2 usability fixes
$_['entry_stop_queue'] = 'Пауза обробки черги';
$_['help_queue_pause'] = 'Це ручна пауза для cron і пакетної обробки. Потрібна, якщо треба тимчасово зупинити імпорт без видалення знайдених завдань. У звичайній роботі тримайте вимкненою.';
$_['help_price_guards'] = '0 означає без обмеження. Поля мін./макс. ціни потрібні лише як страховка від помилкової ціни постачальника. Якщо обмеження спрацює, рядок потрапить у предпросмотр з попередженням, а не застосовується автоматично.';
$_['text_ui_workflow_title'] = 'Порядок роботи з постачальником';
$_['text_ui_step_supplier_title'] = '1. Постачальник';
$_['text_ui_step_supplier_desc'] = 'Створіть або виберіть постачальника, вкажіть базовий домен, валюту, мову і посилання категорій або товарів.';
$_['text_ui_step_rules_title'] = '2. Правила';
$_['text_ui_step_rules_desc'] = 'Скануйте одну картку товару і збережіть знайдені поля: назву, ціну, наявність, SKU і фото.';
$_['text_ui_step_queue_title'] = '3. Черга';
$_['text_ui_step_queue_desc'] = 'Запустіть пошук товарів через sitemap, категорії або прямі URL. Модуль заповнить чергу.';
$_['text_ui_step_scan_title'] = '4. Обробка';
$_['text_ui_step_scan_desc'] = 'Запустіть пакет або до завершення. Товари спочатку потраплять у предпросмотр, а не одразу змінять каталог.';
$_['text_ui_step_process_title'] = '5. Предпросмотр';
$_['text_ui_step_process_desc'] = 'Перевірте знайдені товари, зіставлення, ціну, наявність і попередження.';
$_['text_ui_step_apply_title'] = '6. Застосування';
$_['text_ui_step_apply_desc'] = 'Виберіть дію для кожного рядка або масово: оновити ціну, наявність, створити новий товар, пропустити або виключити.';

// Supplier Sync Parser PRO v1.5.2 bulk and large catalog UX
$_['button_retry_selected_queue'] = 'Повернути вибрані в чергу';
$_['button_delete_selected_queue'] = 'Видалити вибрані з черги';
$_['button_new_skip_selected'] = 'Пропустити вибрані';
$_['button_new_restore_selected'] = 'Повернути вибрані в очікування';
$_['text_bulk_actions'] = 'Масові дії з вибраними рядками';
$_['text_batch_processing_hint'] = 'Для тисяч товарів використовуйте пакетну обробку: модуль бере невелику порцію товарів, оновлює смугу прогресу, потім бере наступну порцію. Це безпечніше для хостингу і не повинно валити сайт через таймаут.';
$_['text_limit_explain'] = 'Обмеження: одна сторінка таблиці показує 50/100/200/500 рядків. Черга може містити тисячі товарів. Один пакет обробки обмежений до 100 товарів за AJAX-запит, щоб не перевантажувати сервер.';
$_['text_compare_names_hint'] = 'У попередньому перегляді поруч показуються товар постачальника і ваш товар OpenCart: так можна порівняти назву, SKU, ціну постачальника, вашу поточну ціну, нову ціну і наявність перед застосуванням.';

// Supplier Sync Parser PRO v1.5.2 scan-source and language guard fixes
$_['text_list_url_product_warning'] = 'Це схоже на картку товару. Для пошуку товарів вставте посилання на категорію або список товарів.';
$_['engine_category_url_is_product'] = 'Це схоже на картку товару. Вставте посилання на категорію або список товарів для пошуку посилань.';
$_['engine_url_skipped_domain_language'] = 'URL пропущено політикою домену або мови';

// Supplier Sync Parser PRO v1.5.2 safer product import
$_['entry_import_additional_images'] = 'Імпорт дод. фото';
$_['help_import_additional_images'] = 'За замовчуванням вимкнено: додаткові фото часто беруться зі схожих товарів, статей або банерів. Вмикайте тільки після перевірки XPath фото.';
$_['text_price_extraction_fixed'] = 'Ціна очищається від валюти, пробілів, тексту і службових чисел перед розрахунком.';

$_['button_run_scan_batch'] = 'Знайти посилання пакетами';
$_['button_run_products_batch'] = 'Перевірити товари пакетами';
$_['button_run_products_until_done'] = 'Перевірити товари до завершення';
$_['text_found_urls'] = 'Знайдено URL';
$_['text_scan_pages_left'] = 'Сторінок пошуку залишилось';
$_['text_products_left'] = 'Товарів на перевірку залишилось';
// Supplier Sync Parser PRO v1.5.2 queue usability fixes
$_['button_stop_current_process'] = 'Зупинити поточний процес';
$_['button_run_selected_scan'] = 'Знайти посилання з вибраних сторінок';
$_['button_run_selected_products'] = 'Перевірити знайдені товари';
$_['text_queue_category_next_step'] = 'Якщо в черзі є сторінка категорії, натисніть «Знайти посилання з вибраних сторінок» або «Сканувати категорії». Після появи товарних URL натисніть «Перевірити знайдені товари».';

// Supplier Sync Parser PRO v1.5.2 commercial workflow additions
$_['tab_master'] = 'Майстер запуску';
$_['text_master_title'] = 'Комерційний майстер запуску';
$_['text_master_intro'] = 'Працюйте як у комерційних парсерах: спочатку джерело, потім правила, пошук посилань, перевірка карток, попередній перегляд і лише після цього ручне застосування вибраних рядків.';
$_['text_master_supplier'] = 'Створіть постачальника, домен, валюту, мову та джерела.';
$_['text_master_rules'] = 'Перевірте одну реальну картку товару та збережіть правила.';
$_['text_master_find_links'] = 'Знайти посилання';
$_['text_master_find_links_help'] = 'Зберіть URL товарів із категорій, домену або sitemap. Товари ще не оновлюються.';
$_['text_master_check_sample'] = 'Перевірити перші 10';
$_['text_master_check_sample_help'] = 'Спочатку перевірте малу вибірку, щоб упевнитися в ціні, SKU, назві та фото.';
$_['text_master_check_all'] = 'Перевірити всі';
$_['text_master_check_all_help'] = 'Після успішної вибірки запускайте пакетну перевірку всіх знайдених товарів.';
$_['text_master_apply'] = 'Порівняйте товар постачальника і ваш товар, виберіть рядки та застосуйте дію вручну.';
$_['text_master_queue'] = 'Черга';
$_['text_master_remaining'] = 'Залишилось';
$_['text_source_block_title'] = '1. Джерела і базові дані постачальника';
$_['text_source_list_help'] = 'Одне посилання в рядку. Для категорії використовуйте кнопку «Знайти посилання», для прямих карток — режим прямих URL. Не змішуйте різні мови в одному запуску.';
$_['text_commercial_safe_apply_note'] = 'Сканування готує попередній перегляд. Створення нових товарів потребує вибору рядків; cron оновлює пов’язані товари лише за ввімкненими правилами профілю.';

// Supplier Sync Parser PRO v1.5.2 master and queue UX
$_['text_master_create_supplier_title'] = 'Почніть зі створення постачальника';
$_['text_master_create_supplier_help'] = 'Майстер не вимагає заздалегідь вибраного постачальника. Спочатку створіть постачальника, вкажіть домен, валюту, мову та джерела, потім поверніться в майстер для перевірки правил і черги.';
$_['button_master_create_supplier'] = 'Створити постачальника';
$_['button_master_edit_supplier'] = 'Відкрити постачальника';
$_['text_master_supplier_selected'] = 'Постачальника вибрано';
$_['text_master_supplier_selected_help'] = 'Тепер можна перевіряти правила, шукати посилання товарів і запускати пакетну перевірку.';
$_['engine_list_scan_failed'] = 'Помилка сканування сторінки списку';

$_['text_selected_rows'] = 'Вибрано';

$_['text_category_for_selected'] = 'Категорія для вибраних';
$_['button_apply_category_selected'] = 'Застосувати категорію';
$_['text_category_required'] = 'Виберіть категорію для вибраних товарів.';
$_['text_category_applied_to_selected'] = 'Категорію застосовано до вибраних рядків';

// Supplier Sync Parser PRO v1.5.2 fixes
$_['engine_no_selected_rows'] = 'Рядки не вибрано';
$_['engine_unknown_apply_mode'] = 'Невідомий режим застосування';
$_['engine_row_excluded_by_rules'] = 'Рядок виключено правилами постачальника';
$_['engine_skipped_manually'] = 'Пропущено вручну';
$_['engine_skipped_and_excluded_from_preview'] = 'Пропущено і виключено з передперегляду';
$_['engine_skipped_and_excluded_manually'] = 'Пропущено і виключено вручну';
$_['engine_new_creation_disabled'] = 'Створення нових товарів вимкнено для цього постачальника';
$_['engine_supplier_category_not_allowed'] = 'Категорію постачальника заборонено правилами';
$_['engine_product_was_not_created'] = 'Товар не було створено';
$_['engine_no_linked_product'] = 'Немає пов’язаного товару OpenCart';
$_['engine_price_warning_force_required'] = 'Попередження ціни: примусове оновлення використовуйте лише після ручної перевірки';
$_['engine_preview_saved_existing'] = 'Передперегляд збережено для наявного товару';
$_['engine_preview_saved_new'] = 'Передперегляд збережено для нового товару';
$_['engine_waiting_for_review'] = 'Очікує перевірки';
$_['engine_unknown_action'] = 'Невідома дія';
$_['engine_existing_linked_skipped_new_only'] = 'Пов’язаний товар пропущено в режимі лише нових товарів';
$_['engine_missing_supplier_or_data'] = 'Постачальник або розібрані дані відсутні';
$_['engine_created_from_supplier_preview'] = 'Товар створено з передперегляду постачальника';
$_['engine_updated_existing_product'] = 'Наявний товар оновлено';
$_['engine_no_changes'] = 'Змін немає';
$_['engine_price_change_guard'] = 'Зміна ціни вища за дозволений ліміт постачальника';
$_['engine_excluded_by_sku_rule'] = 'Виключено правилом SKU';
$_['engine_excluded_by_url_rule'] = 'Виключено правилом URL';
$_['engine_excluded_by_category_rule'] = 'Виключено правилом категорії';
$_['engine_manual_exclusion'] = 'Ручне виключення';
$_['engine_duplicate_found_by'] = 'Можливий дубль знайдено за';
$_['engine_already_linked_to_product'] = 'Цей рядок уже пов’язано з товаром ID';
$_['engine_queue_locked'] = 'Черга вже обробляється процесом';
$_['engine_url_skipped_domain_policy'] = 'URL пропущено політикою домену';
$_['engine_url_skipped_domain_language_policy'] = 'URL пропущено політикою домену або мови';

$_['engine_product_linked_manually'] = 'Товар зв’язано вручну.';
$_['engine_opencart_product_not_found'] = 'Товар OpenCart не знайдено.';
$_['engine_review_product_required'] = 'Потрібні ID рядка передперегляду та ID товару.';
$_['engine_review_row_not_found'] = 'Рядок передперегляду не знайдено.';
// Supplier Sync Parser PRO v1.5.2 usability improvements
$_['help_new_seo_url'] = 'URL нового товару формується автоматично під час створення: береться назва товару, очищається від зайвих слів, транслітерується латиницею, приводиться до нижнього регістру і записується в таблицю seo_url. Якщо такий keyword уже існує, модуль додає суфікс -2, -3 і далі. Режим задається у постачальнику: усі мови, тільки основна мова або без SEO URL.';
$_['text_match_search_hint'] = 'Якщо поле порожнє, пошук візьме SKU або назву постачальника і запропонує товари OpenCart. Можна вручну ввести ID, SKU, модель або частину назви.';
$_['text_match_selected'] = 'Товар зв’язано, рядок передперегляду оновлено.';
$_['text_queue_selected_run_hint'] = 'Ці кнопки тепер обробляють саме вибрані рядки черги, а не наступний загальний пакет.';

$_['engine_supplier_disabled_or_not_found'] = 'Постачальник вимкнений або не знайдений';
$_['engine_url_not_supplier_product'] = 'URL не є товарною сторінкою постачальника';
$_['engine_added_product_jobs'] = 'Додано завдання товарів:';

// Supplier Sync Parser PRO v1.5.2 safer matching
$_['text_match_candidates'] = 'Можливі збіги 90%+';
$_['engine_match_candidates_manual'] = 'Знайдено можливі збіги. Перед створенням нового товару виберіть товар вручну або перевірте дубль.';
$_['help_manual_match'] = 'Зв’язування працює безпечно: точні зв’язки за URL/SKU/моделлю/EAN/UPC/MPN застосовуються автоматично лише якщо знайдено один товар. Схожі назви від 90% не зв’язуються автоматично, а показуються як пропозиції для ручного вибору.';

// Feed/XML/CSV import
$_['entry_source_type'] = 'Джерело даних';
$_['entry_feed_url'] = 'URL XML/YML/CSV-фіда';
$_['entry_feed_file'] = 'Завантажити файл фіда';
$_['entry_feed_format'] = 'Формат фіда';
$_['entry_feed_item_path'] = 'Шлях товару';
$_['entry_feed_url_path'] = 'Поле URL';
$_['entry_feed_sku_path'] = 'Поле SKU/артикулу';
$_['entry_feed_name_path'] = 'Поле назви';
$_['entry_feed_price_path'] = 'Поле ціни';
$_['entry_feed_stock_path'] = 'Поле наявності';
$_['entry_feed_quantity_path'] = 'Поле кількості';
$_['entry_feed_category_path'] = 'Поле категорії';
$_['entry_feed_description_path'] = 'Поле опису';
$_['entry_feed_manufacturer_path'] = 'Поле виробника';
$_['entry_feed_image_path'] = 'Поле зображення';
$_['entry_feed_ean_path'] = 'Поле EAN';
$_['entry_feed_upc_path'] = 'Поле UPC';
$_['entry_feed_mpn_path'] = 'Поле MPN';
$_['text_source_type_html'] = 'HTML-сканування сайту';
$_['text_source_type_feed'] = 'Файл / XML / YML / CSV';
$_['text_feed_help'] = 'Можна вказати посилання на XML/YML/CSV або завантажити файл. Фід створює чергу імпорту і переносить товари в попередній перегляд без автоматичної зміни магазину.';
$_['text_feed_mapping_help'] = 'Для XML/YML вказуйте назву тега або XPath відносно товару. Для CSV вказуйте назву колонки. Якщо поле порожнє, модуль спробує стандартні назви.';
$_['text_feed_file_current'] = 'Поточний файл';
$_['button_build_feed_file'] = 'Побудувати чергу з файлу';
$_['button_run_feed_batch'] = 'Обробити файл пакетом';
$_['button_run_feed_until_done'] = 'Обробити файл повністю';
$_['text_queue_action_import_item'] = 'Товар із файлу';

// Feed import engine messages
$_['engine_feed_queue_created'] = 'Чергу з файлу створено';
$_['engine_feed_preview_saved_existing'] = 'Попередній перегляд із файлу збережено для наявного товару';
$_['engine_feed_preview_saved_new'] = 'Попередній перегляд із файлу збережено для нового товару';
$_['engine_feed_source_required'] = 'Потрібен URL фіда або завантажений файл';
$_['engine_feed_source_type_required'] = 'Для цього режиму тип джерела має бути Файл/XML/CSV';
$_['engine_csv_no_rows'] = 'У CSV-фіді немає рядків товарів';
$_['engine_feed_file_empty'] = 'Завантажений файл фіда порожній або не читається';
$_['engine_simplexml_required'] = 'Для XML/YML-фідів потрібне PHP-розширення SimpleXML';
$_['engine_feed_item_invalid'] = 'У рядку файлу немає коректної назви або розрахованої ціни';
$_['engine_feed_parsed'] = 'Фід розібрано:';
$_['engine_cannot_parse_feed_xml'] = 'Не вдалося розібрати XML/YML-фід';

$_['text_copy_url'] = 'Копіювати посилання';
$_['text_copied'] = 'Посилання скопійовано';

// Supplier Sync Parser PRO v1.5.2 preview/new product workflow fixes
$_['button_delete_selected_reviews'] = 'Видалити вибрані з перегляду';
$_['button_delete_selected_new'] = 'Видалити вибрані з нових товарів';
$_['text_moved_new'] = 'Перенесено до нових товарів';
$_['text_deleted'] = 'Видалено';
$_['text_created_product_ids'] = 'Створені ID товарів';
$_['text_preview_move_to_new_hint'] = 'У перегляді кнопка створення не створює товар одразу: вона переносить вибрані нові позиції у вкладку «Нові товари» для вибору категорії та фінальної перевірки.';
$_['text_new_create_hint'] = 'У цій вкладці кнопка створення створює вибрані товари в OpenCart. Після створення рядок отримує ID створеного товару.';

// Supplier Sync Parser PRO v1.5.2 new product workflow diagnostics
$_['text_new_creation_disabled_hint'] = 'Створення нових товарів вимкнено в налаштуваннях цього постачальника. Увімкніть «Дозволити створення нових», збережіть постачальника і повторіть створення.';
$_['text_error_details'] = 'Подробиці помилки';

// Supplier Sync Parser PRO v1.6.0 new product creation flag save fix
$_['text_success_supplier_new_flags_saved'] = 'Налаштування створення нових товарів збережено.';

// Supplier Sync Parser PRO v1.6.0 feed mapping and import cleanup
$_['button_auto_detect_feed'] = 'Автовизначити поля файлу';
$_['button_apply_feed_detected'] = 'Підставити вибрані поля';
$_['text_feed_mapping_title'] = 'Наочне зіставлення полів файлу з полями OpenCart';
$_['text_feed_mapping_intro'] = 'Модуль читає зразок XML/YML/CSV, сам пропонує поля для назви, коду товару, SKU, ціни, наявності, опису, виробника і фото. Перевірте вибрані значення і за потреби виберіть інший варіант зі списку.';
$_['engine_feed_mapping_detected'] = 'Поля файлу визначені. Перевірте стандартні поля OpenCart перед збереженням.';
$_['engine_feed_no_product_nodes'] = 'В XML/YML-фіді не знайдено вузли товарів.';

// Supplier Sync Parser PRO v1.6.0 product/list URL guard
$_['engine_product_url_is_list'] = 'Цей URL схожий на категорію або список товарів. Для автовизначення правил товару вставте реальну картку товару; для категорії використовуйте пошук посилань товарів.';

// Supplier Sync Parser PRO v1.6.0 field override
$_['text_detect_xpath_override'] = 'XPath / поле';
$_['help_detect_xpath_override'] = 'Можна вручну замінити XPath, якщо авто визначення вибрало не той блок.';

// Availability policy
$_['entry_in_stock_quantity'] = 'Залишок для «в наявності»';
$_['help_in_stock_quantity'] = 'Лише якщо постачальник не вказав число. Це ваш умовний залишок, наприклад 100.';
$_['entry_unknown_stock_policy'] = 'Невідома наявність';
$_['text_stock_keep'] = 'Зберегти поточну наявність';
$_['text_stock_zero'] = 'Встановити залишок 0';
$_['entry_update_stock_status'] = 'Оновлювати статус наявності';
$_['help_update_stock_status'] = 'Незалежно від кількості. Якщо вимкнено, stock_status_id товару зберігається.';

$_['entry_new_category_name'] = 'Категорія для нових товарів';

$_['help_new_category_name'] = 'Якщо заповнено, створюється одна вимкнена категорія. Примусова категорія має пріоритет. Порожнє поле зберігає звичайний розподіл.';

$_['button_rollback_price_stock'] = 'Повернути ціну / залишок';

$_['confirm_rollback_price_stock'] = 'Повернути змінені ціну та наявність? Якщо поточні значення відрізняються від результату імпорту, операцію буде відхилено. Описи та нові товари не змінюються.';

$_['text_rollback_success'] = 'Ціну та наявність відновлено.';

$_['text_rollback_conflict'] = 'Товар змінено після імпорту. Перевірте поточні значення; автоматичне повернення скасовано.';

$_['text_rollback_unavailable'] = 'Для цього запису повернення недоступне або вже виконане.';

$_['text_rollback_failed'] = 'Не вдалося виконати повернення. Зміни скасовано.';

$_['entry_match_source'] = 'Ідентифікатор постачальника';

$_['entry_match_target'] = 'Поле мого товару';

$_['help_match_mapping'] = 'Точне зіставлення. Наприклад: SKU постачальника → MPN магазину. Порожні й неоднозначні значення потребують перевірки.';

$_['text_match_auto'] = 'Усі ідентифікатори: перевірка конфліктів';

$_['entry_jan_xpath'] = 'JAN XPath';

$_['entry_isbn_xpath'] = 'ISBN XPath';

$_['entry_cron_enabled'] = 'Оновлювати профіль через cron';

$_['entry_cron_interval_minutes'] = 'Інтервал перевірки, хвилин';

$_['help_cron_profile'] = 'Cron перевіряє пов’язані товари та застосовує дозволені ціну/наявність. Нові товари й попередження залишаються на перевірку. Мінімум 5 хвилин.';

$_['text_stock_available'] = 'В наявності';
$_['text_stock_unavailable'] = 'Немає в наявності';
