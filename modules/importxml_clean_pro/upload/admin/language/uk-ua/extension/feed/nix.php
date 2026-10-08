<?php

/**
 * @category   OpenCart
 * @package    ImportXML Clean PRO v1.4.2
 * @copyright  CodeCart PRO, https://codecartpro.com
 */

// Heading
$_['heading_title'] = '<span style="color:#0057b7;font-weight:700;">ImportXML Clean</span> <span style="display:inline-block;background:#ffd700;color:#0057b7;border-radius:12px;padding:2px 10px;font-size:12px;line-height:1.2;vertical-align:middle;font-weight:700;margin-left:6px;">PRO</span> <span style="display:inline-block;background:#eef3f8;color:#445;border-radius:10px;padding:2px 8px;font-size:11px;line-height:1.2;vertical-align:middle;margin-left:5px;">v1.4.2</span>';

// Text
$_['text_success']	 = 'Налаштування модуля оновлено!';
$_['text_edit']			 = 'Налаштування модуля';
$_['btn_save']			 = 'Зберегти';
$_['btn_cancel']		 = 'Назад';

$_['text_copyright'] = '<p>Версія: <b>%s</b></p>'
	. '<p>Автор: <a href="https://codecartpro.com" target="_blank" rel="noopener">CodeCart PRO</a></p>';

// Error
$_['error_permission'] = 'У вас немає прав для керування цим модулем!';


// Settings
$_['text_part_settings'] = 'Постачальники і налаштування';

$_['text_enabled'] = 'Увімкнено';
$_['text_disabled'] = 'Вимкнено';
$_['help_status'] = 'Коли модуль вимкнено, налаштування можна редагувати, але імпорт заблоковано.';
$_['error_module_disabled'] = 'Модуль вимкнено. Увімкніть його в налаштуваннях модуля та збережіть перед запуском імпорту.';
$_['entry_status']			 = 'Статус';
$_['btn_save_settings']	 = 'Зберегти';
$_['btn_add_supplier']	 = 'Додати профіль';

// Suppliers list
$_['entry_supplier_list'] = 'Профілі постачальників';


// Delete
$_['msg_supplier_delete_success'] = 'Профіль видалено';



// Supplier form
$_['supplier_modal_title']			 = 'Налаштування профілю';
$_['supplier_fieldset_settings'] = 'Налаштування документа XML';
$_['entry_supplier_name']				 = 'Назва профілю';
$_['entry_supplier_link_price']	 = 'Посилання на XML для оновлення цін';
$_['entry_supplier_link']				 = 'Посилання на XML-документ в інтернеті';
$_['entry_supplier_markup']			 = 'Націнка (%)';

$_['supplier_fieldset_attributes']				 = 'Атрибути тегів у XML-документі';
$_['entry_attribute_parent_id']						 = 'Атрибути для позначення parent_id категорії';
$_['supplier_fieldset_tags']							 = 'Теги XML-документа';
$_['entry_tag_product_name']							 = 'Тег назви';
$_['entry_tag_product_description']				 = 'Тег опису';
$_['entry_tag_product_model']							 = 'Тег моделі';
$_['entry_tag_product_sku']								 = 'Тег SKU (артикула)';
$_['entry_tag_product_price_purchasing']	 = 'Тег закупівельної ціни';
$_['entry_tag_product_price_rrp']					 = 'Тег РРЦ';
$_['entry_tag_product_quantity']					 = 'Тег кількоcті товару';
$_['entry_tag_product_images']						 = 'Теги зображень';
$_['entry_tag_product_category']					 = 'Тег з категорією товару';
$_['entry_tag_product_manufacturer_name']	 = 'Тег з назвою виробника';
$_['entry_tag_product_attributes']				 = 'Теги атрибутів';
$_['btn_supplier_modal_save']							 = 'Зберегти профіль';
$_['msg_supplier_success']								 = 'Профіль збережено';
$_['msg_supplier_error']									 = 'Помилка! Перевірте всі поля форми!';
$_['error_supplier_name_empty']						 = 'Вкажіть назву профілю!';
$_['error_supplier_markup_empty']					 = 'Вкажіть націнку!';

$_['error_tag_empty']				 = '%s є обов\'язковим для заповнення!';
$_['error_attribute_empty']	 = '%s є обов\'язковим для заполонення!';



//Import
$_['text_edit_import'] = 'Ручний імпорт XML-файлу';
$_['text_select_option'] = '-- Вибрати --';

$_['text_part_import'] = 'Ручний імпорт';
//$_['entry_primary_language'] = ' - вибрати основним для цього імпорту';
$_['entry_language']						 = ' - вибрати основним для цього імпорту';
$_['entry_file'] = 'XML-файл для ручного імпорту';
$_['help_file'] = 'Звичайний імпорт працює через завантаження XML-файлу з комп’ютера. Посилання постачальника використовуються окремо в налаштуваннях профілю тільки для cron-оновлення.';
$_['error_file_main_not_saved']	 = 'Файл XML для головної мови не був коректно збережений на сайті';
$_['entry_xmllink']							 = 'Посилання на XML-файл в Інтернеті';

$_['btn_file']							 = 'Вибрати файл із комп\'ютера';
$_['file_not_choosen']			 = 'Файл не вибраний';
$_['xor']										 = 'АБО';
$_['entry_copy_description'] = 'Копіювати описи товарів та категорій у мови, для яких не вказано XML-файл';
$_['entry_copy_attributes']	 = 'Копіювати атрубути товарів у мови, для яких не вказано XML-файл';
$_['help_copy_attributes']	 = 'Копіювати атрибути можна лише в тому випадку, якщо копіюються описи';
$_['entry_supplier']				 = 'Постачальник';
$_['error_supplier']				 = 'Виберіть Постачальника';
$_['btn_import'] = 'Запустити ручний імпорт';
$_['text_import_options']		 = 'Опції імпорту';
$_['entry_delete_all']			 = 'Очистити каталог';
$_['help_delete_all']				 = 'Перед початком імпорту з бази даних будуть видалені всі товари, категорії, атрибути та виробники';
$_['entry_update_if_exist']	 = 'Перезаписати всі дані для існуючих товарів';
$_['help_update_if_exist']	 = 'Дана опція зачепить будь-які зміни в назві товарів та їх описах, які Ви могли зробити. Без цієї галочки, товари, які вже є в базі, не перезаписуватимуться.';

$_['error_warning']				 = 'Помилка при надсиланні форми! Вивчіть усі поля щодо помилок!';
$_['error_import_fatal']	 = 'Файл імпорту містить серйозні помилки';
$_['error_import_no_tags'] = 'Файл імпорту не містить потрібних тегів';


// Import Processing
$_['status_started'] = '<p>Імпорт розпочався. НЕ закривайте цю сторінку до закінчення імпорту!!</p>';
$_['statistics']		 = '<p>Оброблено товарів: <b>%d</b></p>';

$_['statistics_console'] = '<p>Оброблено товарів у поточному фоновому запиті: <b>%1$d</b></p>'
	. '<p>Оброблено товарів за час поточного імпорту <b>%2$d</b></p>';

$_['success_import']	 = 'Імпорт успішно завершено';
$_['continued_import'] = 'Імпорт продовжується...';

$_['import_placeholder_name'] = 'Немає назви';

// CodeCart PRO additions
$_['error_language'] = 'Виберіть основну мову імпорту';
$_['error_model_or_sku_required'] = 'Вкажіть тег моделі або тег SKU. Достатньо одного з цих полів.';
$_['error_price_tag_required'] = 'Вкажіть тег закупівельної ціни або тег РРЦ/ціни постачальника. Достатньо одного з цих полів.';
$_['text_diagnostics'] = 'Діагностика';
$_['btn_check_update'] = 'Оновити модуль';
$_['btn_clear_logs'] = 'Очистити логи';
$_['text_update_info'] = 'Поточна версія: %s. Перевірка нових версій і ліцензії виконується через сайт автора: %s';
$_['text_logs_cleared'] = 'Видалено файлів логів і тимчасових XML: %d';
$_['text_about_module'] = 'Про модуль';
$_['text_author'] = 'Автор';
$_['text_version'] = 'Версія';

$_['error_simplexml_missing'] = 'На сервері вимкнено PHP-розширення SimpleXML. Без нього імпорт XML неможливий.';

$_['entry_delete_data_on_uninstall'] = 'Видаляти дані модуля під час видалення';
$_['help_delete_data_on_uninstall'] = 'За замовчуванням вимкнено. Якщо увімкнути, під час видалення модуля будуть видалені профілі постачальників і NIX-поля постачальника в товарах/категоріях. Залиште вимкненим, якщо потрібні історія, аудит або можливість відкату.';
$_['text_confirm_supplier_delete'] = 'Видалити цей профіль постачальника? Дія застосовується одразу і не скасовується.';
$_['text_confirm_clear_logs'] = 'Видалити файли логів NIX і тимчасові XML-файли?';
$_['error_request_method'] = 'Неприпустимий метод запиту. Повторіть дію з інтерфейсу модуля.';
$_['error_supplier_delete_id'] = 'ID профілю постачальника не отримано. Оновіть сторінку і повторіть дію.';
$_['error_ajax_failed'] = 'AJAX-запит не виконано. Перевірте user_token, права доступу і PHP error log.';
$_['placeholder_supplier_name'] = 'Приклад: Основний XML постачальника';
$_['help_supplier_profile_examples'] = 'Приклад: назва профілю “Основний XML постачальника”, націнка “15”. Націнка застосовується до закупівельної ціни, якщо налаштовано тег закупівельної ціни.';
$_['help_supplier_tags_examples'] = 'Приклади тегів: name, model, vendorCode, price, optPrice, quantity, picture, categoryId, vendor, description, param.';

$_['text_confirm_delete_all_import'] = 'Увімкнено опцію “Очистити каталог”. Перед імпортом можуть бути видалені товари і категорії цього постачальника. Продовжити?';

// Product card fields and sortable tables
$_['supplier_fieldset_product_card_fields'] = 'Додаткові поля картки товару';
$_['help_supplier_product_card_fields'] = 'Ці поля необов’язкові. Заповнюйте лише ті XML-теги, які реально є у постачальника. Значення будуть підставлені у стандартні поля картки товару OpenCart/ocStore.';
$_['entry_tag_product_meta_h1'] = 'Тег HTML H1';
$_['entry_tag_product_meta_title'] = 'Тег Meta Title';
$_['entry_tag_product_meta_description'] = 'Тег Meta Description';
$_['entry_tag_product_meta_keyword'] = 'Тег Meta Keywords';
$_['entry_tag_product_tag'] = 'Тег тегів товару';
$_['entry_tag_product_currency'] = 'Тег валюти';
$_['entry_tag_product_upc'] = 'Тег UPC';
$_['entry_tag_product_ean'] = 'Тег EAN';
$_['entry_tag_product_jan'] = 'Тег JAN';
$_['entry_tag_product_isbn'] = 'Тег ISBN';
$_['entry_tag_product_mpn'] = 'Тег MPN';
$_['entry_tag_product_location'] = 'Тег розташування';
$_['entry_tag_product_minimum'] = 'Тег мінімальної кількості';
$_['entry_tag_product_subtract'] = 'Тег віднімання зі складу';
$_['entry_tag_product_stock_status_id'] = 'Тег статусу складу';
$_['entry_tag_product_shipping'] = 'Тег доставки';
$_['entry_tag_product_tax_class_id'] = 'Тег податкового класу';
$_['entry_tag_product_length'] = 'Тег довжини';
$_['entry_tag_product_width'] = 'Тег ширини';
$_['entry_tag_product_height'] = 'Тег висоти';
$_['entry_tag_product_length_class_id'] = 'Тег одиниці довжини';
$_['entry_tag_product_weight'] = 'Тег ваги';
$_['entry_tag_product_weight_class_id'] = 'Тег одиниці ваги';
$_['entry_tag_product_status'] = 'Тег статусу товару';
$_['entry_tag_product_sort_order'] = 'Тег порядку сортування';
$_['entry_tag_product_google_product_category_id'] = 'Тег Google Product Category ID';
$_['column_supplier_id'] = 'ID';
$_['column_supplier_name'] = 'Постачальник';
$_['column_supplier_markup'] = 'Націнка, %';
$_['text_no_suppliers'] = 'Профілі постачальників ще не створені';
$_['entry_tag_product_date_available'] = 'Тег дати надходження';
$_['entry_tag_product_points'] = 'Тег балів';

// Cron
$_['text_extension'] = 'Канали просування';
$_['text_cron_settings'] = 'Додатково: cron-оновлення';
$_['entry_cron_status'] = 'Увімкнути cron';
$_['entry_cron_token'] = 'Секретний token';
$_['entry_cron_supplier'] = 'Постачальник для cron';
$_['entry_cron_language'] = 'Основна мова cron';
$_['entry_cron_command'] = 'Команда для cron';
$_['entry_cron_url'] = 'URL для ручної перевірки';
$_['entry_cron_options'] = 'Опції cron-імпорту';
$_['entry_cron_update_if_exist'] = 'Оновлювати наявні товари';
$_['entry_cron_copy_description'] = 'Копіювати описи в мови без окремого XML';
$_['entry_cron_copy_attributes'] = 'Копіювати атрибути в мови без окремого XML';
$_['entry_cron_delete_all'] = 'Очищати каталог перед cron-імпортом';
$_['help_cron_status'] = 'Cron запускає оновлення за посиланням постачальника з поля “Посилання на XML для оновлення цін”. Не вмикайте частіше 1 разу на 5 хвилин. Для великих XML краще запускати вночі.';
$_['help_cron_token'] = 'Секретний token захищає cron від зовнішнього запуску. Після зміни token обов’язково оновіть команду в панелі хостингу.';
$_['help_cron_command'] = 'Для Mirohost використовуйте цю команду в полі “Команда”. Хвилини/години задаються окремими полями cron у панелі хостингу.';
$_['help_cron_delete_all'] = 'Небезпечна опція. Працює тільки в режимі повного cron-імпорту. Для регулярного оновлення цін і залишків має бути вимкнена.';
$_['error_cron_token'] = 'Невірний або порожній cron token.';
$_['error_cron_disabled'] = 'Cron вимкнений або модуль вимкнений.';
$_['error_cron_empty_link'] = 'У вибраного постачальника не заповнене посилання на XML для cron.';
$_['error_cron_download'] = 'Не вдалося завантажити XML: %s';
$_['error_cron_write_file'] = 'Не вдалося записати тимчасовий XML-файл: %s';
$_['text_cron_multilang_hint'] = 'Для кількох XML можна вказати посилання в профілі постачальника построково у форматі: language_id=https://example.com/file.xml. Якщо вказане одне посилання, воно використовується для основної мови.';
$_['text_cron_performance_warning'] = 'Увага: cron може бути важкою операцією. Безпечний режим за замовчуванням оновлює ціни, наявність, залишки, статус наявності і статус існуючих товарів. Повний cron-імпорт може завантажувати XML, створювати категорії, оновлювати зображення, описи і SEO, тому не запускайте його в години пікового навантаження.';

$_['placeholder_supplier_link_price'] = 'Приклад: https://example.com/feed.xml або 3=https://example.com/ua.xml';

$_['help_supplier_link_price'] = 'Використовується для cron. Можна вказати одне XML-посилання або посилання за мовами построково, наприклад: 3=https://example.com/ua.xml.';

$_['text_quick_xml_tags'] = 'Швидкі приклади XML-тегів';

$_['help_quick_xml_tags'] = 'Спочатку поставте курсор у потрібне поле, потім натисніть приклад тега, щоб швидко вставити його в поле.';

$_['btn_generate_token'] = 'Згенерувати token';

$_['btn_copy_cron_command'] = 'Копіювати команду';

$_['btn_copy_cron_url'] = 'Копіювати URL';

$_['text_copied'] = 'Скопійовано.';

$_['placeholder_cron_token'] = 'Приклад: 48 випадкових HEX-символів';

$_['error_ajax_failed_detail'] = 'AJAX-запит не виконано. HTTP-статус: %s. Відповідь сервера: %s';
// CodeCart PRO production additions v1.4.2
$_['btn_export_settings'] = 'Експорт налаштувань';
$_['btn_reset_settings'] = 'Скинути налаштування';
$_['help_service_actions'] = 'Експорт зберігає налаштування і профілі постачальників у JSON. Скидання повертає системні налаштування модуля до безпечних значень, але не видаляє профілі постачальників.';
$_['text_confirm_reset_settings'] = 'Скинути налаштування модуля до безпечних значень за замовчуванням? Профілі постачальників залишаться в базі.';
$_['text_settings_reset'] = 'Налаштування модуля скинуто. Модуль вимкнений, cron вимкнений, створено новий token.';
$_['diag_module_enabled'] = 'Модуль увімкнений.';
$_['diag_module_disabled'] = 'Модуль вимкнений: імпорт заблокований до ручного увімкнення і збереження налаштувань.';
$_['diag_table_suppliers'] = 'Таблиця профілів постачальників nix_suppliers.';
$_['diag_product_supplier_id'] = 'Поле product.nix_supplier_id для зв’язку товару з постачальником.';
$_['diag_product_supplier_product_id'] = 'Поле product.nix_supplier_product_id для зовнішнього ID товару постачальника.';
$_['diag_simplexml'] = 'PHP-розширення SimpleXML для читання XML-файлів.';
$_['diag_cache_writable'] = 'Папка кешу доступна для тимчасових XML-файлів.';
$_['diag_logs_writable'] = 'Папка логів доступна для запису.';
$_['diag_main_category_ptc'] = 'Головна категорія через product_to_category.main_category.';
$_['diag_main_category_product'] = 'Головна категорія через product.main_category_id.';
$_['diag_google_product_category'] = 'Google Product Category ID через category.google_product_category_id або googleshopping_category.';
$_['entry_mirohost_minutes'] = 'Mirohost: хвилини';
$_['entry_mirohost_hours'] = 'Mirohost: години';
$_['entry_mirohost_days'] = 'Mirohost: дні місяця';
$_['entry_mirohost_months'] = 'Mirohost: місяці';
$_['entry_mirohost_weekdays'] = 'Mirohost: дні тижня';
$_['help_mirohost_schedule'] = 'Рекомендований безпечний приклад для Mirohost: хвилини */30, години *, дні місяця *, місяці *, дні тижня *. Не ставте частіше 1 разу на 5 хвилин.';
// CodeCart PRO import/export additions v1.4.2
$_['btn_import_settings'] = 'Імпорт налаштувань';
$_['help_import_settings'] = 'Виберіть JSON-файл, раніше створений кнопкою “Експорт налаштувань”. Імпорт замінить системні налаштування і профілі постачальників. Перед імпортом зробіть резервну копію.';
$_['error_import_settings_failed'] = 'Не вдалося імпортувати налаштування. Перевірте формат файлу і права доступу.';
$_['error_import_settings_file'] = 'Файл налаштувань не вибрано. Виберіть JSON-файл експорту.';
$_['error_import_settings_size'] = 'Файл налаштувань занадто великий. Максимум 1 МБ.';
$_['error_import_settings_json'] = 'Файл налаштувань має неправильну структуру JSON.';
$_['text_import_settings_success'] = 'Налаштування імпортовано. Профілів постачальників: %d.';
$_['text_defaults_restored'] = 'Налаштування модуля відновлено за замовчуванням. Модуль вимкнений, cron вимкнений, створено новий token.';
$_['diag_field_product_supplier_id'] = 'Поле product.nix_supplier_id для зв’язку товару з постачальником.';
$_['diag_field_product_supplier_product_id'] = 'Поле product.nix_supplier_product_id для зовнішнього ID товару постачальника.';
$_['diag_google_category'] = 'Google Product Category ID через category.google_product_category_id або googleshopping_category.';

// CodeCart PRO v1.4.2 UI and warning additions
$_['warning_offer_without_id'] = 'Попередження: у XML знайдено offer без атрибута id. Позицію пропущено.';
$_['warning_offer_missing_tag'] = 'Попередження: offer ID %s пропущено, тому що відсутній обов’язковий тег %s.';
$_['warning_offer_missing_required_tags'] = 'Попередження: offer ID %s пропущено, тому що відсутні обов’язкові теги для повного імпорту.';
// CodeCart PRO service UI additions v1.4.2
$_['text_service_tools'] = 'Сервісні інструменти';
$_['btn_apply_import_settings'] = 'Застосувати імпорт';

$_['text_default'] = 'Основний магазин';

$_['text_manual_import_primary'] = 'Основний сценарій роботи модуля — звичайний ручний імпорт XML-файлу через кнопку “Ручний імпорт”. Cron — лише додаткова функція для автоматичного оновлення цін, наявності, залишків, статусу наявності і статусу товарів за посиланням постачальника.';

$_['entry_cron_mode'] = 'Режим cron';

$_['text_cron_mode_price_stock'] = 'Ціни, наявність, залишки і статус існуючих товарів';

$_['text_cron_mode_full'] = 'Повний імпорт за посиланням постачальника';

$_['help_cron_mode'] = 'Рекомендований режим для cron оновлює ціни, наявність за available, кількість/залишки, stock_status_id і статус існуючих товарів. Він не створює нові товари, не перезаписує описи, зображення, категорії та SEO. Повний cron-імпорт використовуйте тільки свідомо і краще вночі.';

$_['help_cron_secondary_function'] = 'Cron не замінює звичайний імпорт. Спочатку виконайте ручний імпорт XML, перевірте товари, категорії та поля, а cron використовуйте пізніше для регулярного оновлення цін і наявності.';

// CodeCart PRO v1.4.2 supplier UI corrections
$_['text_settings_main'] = 'Основні налаштування';
$_['text_supplier_settings'] = 'Профілі постачальників';
$_['text_cron_settings_short'] = 'Cron-оновлення';
$_['help_supplier_settings'] = 'Тут створюються і редагуються профілі постачальників: XML-посилання для cron, націнка та відповідність XML-тегів полям картки товару. Без профілю постачальника ручний імпорт і cron не працюватимуть.';
$_['help_supplier_list'] = 'Натисніть “Додати профіль”, щоб налаштувати XML-теги постачальника. Для редагування натисніть назву постачальника в таблиці.';
$_['btn_manage_suppliers'] = 'Налаштувати постачальників';
$_['help_import_supplier_select'] = 'Якщо потрібного постачальника немає у списку, спочатку створіть профіль постачальника в налаштуваннях. У профілі задаються XML-теги товару, ціни, наявності, категорії, SEO та посилання для cron.';


// CodeCart PRO v1.4.2 safe supplier import additions
$_['btn_preview_import'] = 'Перевірити зміни без запису';
$_['btn_apply_safe_import'] = 'Застосувати імпорт після перевірки';
$_['help_safe_import_buttons'] = 'Спочатку запустіть перевірку. Модуль покаже, що буде оновлено, створено, пропущено і які товари зникли у постачальника. Тільки після перегляду звіту запускайте застосування.';
$_['help_file_formats'] = 'Підтримуються XML, YML/YAML, CSV/TXT і XLSX. Для CSV/XLSX перший рядок має містити назви полів: id, name, model, sku/vendorCode, price, optPrice, special_price, quantity, categoryId.';
$_['text_confirm_apply_after_preview'] = 'Застосувати імпорт у базу? Перед цим рекомендується виконати перевірку змін без запису.';
$_['text_preview_ready'] = 'Перевірку завершено. Запис у базу не виконувався.';
$_['text_preview_statistics'] = 'Усього рядків: %d. Буде оновлено: %d. Нових позицій: %d. Не знайдено в магазині: %d. Зникло у постачальника: %d. Змінилася ціна: %d. Змінився залишок: %d. Змінився статус: %d. Змінилася закупівельна ціна: %d. Змінилася акційна ціна: %d.';
$_['text_preview_changed_products'] = 'Товари зі змінами';
$_['text_preview_new_products'] = 'Нові позиції постачальника';
$_['text_preview_not_found_products'] = 'Не знайдено в магазині для оновлення';
$_['text_preview_missing_products'] = 'Товари, які зникли у постачальника';
$_['text_preview_no_rows'] = 'Немає рядків для відображення.';
$_['text_preview_action_update'] = 'Буде оновлено';
$_['text_preview_action_create'] = 'Буде створено';
$_['text_preview_action_skip_not_found'] = 'Пропуск: cron/price_stock не створює товари';
$_['text_preview_action_skip_update_disabled'] = 'Пропуск: оновлення існуючих вимкнено';
$_['text_preview_action_missing'] = 'Є в магазині, відсутній у файлі постачальника';
$_['help_preview_apply_warning'] = 'Звіт показує перші 200 рядків кожного розділу. Лічильники розраховані по всьому файлу. У режимі cron/price_stock модуль оновлює тільки ціну, залишок, stock_status_id, статус і закупівельну ціну постачальника у існуючих товарів.';
$_['entry_tag_product_special_price'] = 'Тег акційної ціни';
$_['help_tag_product_special_price'] = 'Наприклад special_price, special, sale_price, discount_price або oldprice. Якщо тег є і ціна більша за 0, модуль запише product_special. Якщо тег є і значення 0 або порожнє, акції товару будуть очищені при повному оновленні.';
$_['error_ziparchive_missing'] = 'PHP-розширення ZipArchive не встановлено. XLSX-файл прочитати неможливо.';
$_['field_product_supplier_price'] = 'Поле product.nix_supplier_price для закупівельної ціни постачальника';
$_['text_run_preview_first'] = 'Спочатку виконайте перевірку змін без запису. Після успішного звіту кнопка застосування стане доступною.';

$_['error_preview_token'] = 'Помилка безпечного імпорту: спочатку виконайте перевірку змін без запису і не змінюйте файл або налаштування перед застосуванням.';

// CodeCart PRO production fixes v1.4.2
$_['error_file'] = 'Виберіть файл імпорту XML, YML/YAML, CSV/TXT або XLSX.';
$_['error_file_upload_code'] = 'Файл не завантажено. Код помилки PHP upload: %s. Перевірте розмір файлу та налаштування upload_max_filesize/post_max_size.';
$_['error_file_extension'] = 'Недопустимий формат файлу. Дозволені лише: %s.';
$_['error_file_size'] = 'Файл занадто великий. Максимальний розмір для ручного імпорту: %s.';
$_['text_about_module_description'] = 'ImportXML Clean імпортує товари, категорії, зображення, виробників і атрибути з XML/YML, CSV та XLSX постачальників. Перед застосуванням можна виконати безпечну перевірку змін без запису в базу.';
$_['text_about_module_benefit'] = 'Користь модуля: первинний імпорт каталогу, безпечне порівняння old/new перед застосуванням, оновлення цін, залишків, статусу, закупівельної ціни постачальника та акційних цін. Cron можна використовувати як додаткову функцію для оновлення лише існуючих товарів без перезапису описів, зображень, категорій і SEO.';
$_['diag_ziparchive'] = 'PHP-розширення ZipArchive для читання XLSX-файлів.';
$_['column_offer_id'] = 'ID постачальника';
$_['column_product_id'] = 'ID товару';
$_['column_name'] = 'Назва';
$_['column_model'] = 'Модель';
$_['column_sku'] = 'SKU';
$_['column_price_old'] = 'Ціна була';
$_['column_price_new'] = 'Ціна буде';
$_['column_quantity_old'] = 'Залишок був';
$_['column_quantity_new'] = 'Залишок буде';
$_['column_status_old'] = 'Статус був';
$_['column_status_new'] = 'Статус буде';
$_['column_action'] = 'Дія';

$_['error_user_token'] = 'Сесія адміністратора застаріла або user_token неправильний. Оновіть сторінку модуля та повторіть дію.';

$_['error_preview_expired'] = 'Час безпечного попереднього перегляду минув. Знову виконайте перевірку змін без запису перед застосуванням імпорту.';

$_['error_file_write'] = 'Не вдалося записати тимчасовий файл імпорту: %s. Перевірте права на папку system/storage/cache.';
$_['diag_php_version'] = 'PHP %s; підтримуваний діапазон: 7.4–8.5';
