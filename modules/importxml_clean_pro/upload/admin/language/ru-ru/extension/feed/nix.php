<?php

/**
 * @category   OpenCart
 * @package    ImportXML Clean PRO v1.4.3
 * @copyright  CodeCart PRO, https://codecartpro.com
 */

// Heading
$_['heading_title'] = '<span style="color:#0057b7;font-weight:700;">ImportXML Clean</span> <span style="display:inline-block;background:#ffd700;color:#0057b7;border-radius:12px;padding:2px 10px;font-size:12px;line-height:1.2;vertical-align:middle;font-weight:700;margin-left:6px;">PRO</span> <span style="display:inline-block;background:#eef3f8;color:#445;border-radius:10px;padding:2px 8px;font-size:11px;line-height:1.2;vertical-align:middle;margin-left:5px;">v1.4.3</span>';

// Text
$_['text_success']	 = 'Настройки модуля обновлены!';
$_['text_edit']			 = 'Настройки модуля';
$_['btn_save']			 = 'Сохранить';
$_['btn_cancel']		 = 'Назад';

$_['text_copyright'] = '<p>Версия: <b>%s</b></p>'
	. '<p>Автор: <a href="https://codecartpro.com" target="_blank" rel="noopener">CodeCart PRO</a></p>';

// Error
$_['error_permission'] = 'У вас нет прав для управления этим модулем!';


// Settings
$_['text_part_settings'] = 'Поставщики и настройки';

$_['text_enabled'] = 'Включено';
$_['text_disabled'] = 'Отключено';
$_['help_status'] = 'Когда модуль отключен, настройки можно редактировать, но импорт заблокирован.';
$_['error_module_disabled'] = 'Модуль отключен. Включите его в настройках модуля и сохраните перед запуском импорта.';
$_['entry_status']			 = 'Статус';
$_['btn_save_settings']	 = 'Сохранить';
$_['btn_add_supplier']	 = 'Добавить профиль';

// Suppliers list
$_['entry_supplier_list']	= 'Профили поставщиков';


// Delete
$_['msg_supplier_delete_success'] = 'Профиль удален';



// Supplier form
$_['supplier_modal_title']			 = 'Настройки профиля';
$_['supplier_fieldset_settings'] = 'Настройки XML-документа';
$_['entry_supplier_name']				 = 'Название профиля';
$_['entry_supplier_link_price']	 = 'Ссылка на XML для обновления цен';
$_['entry_supplier_link']				 = 'Ссылка на XML-документ в интернете';
$_['entry_supplier_markup']			 = 'Наценка (%)';

$_['supplier_fieldset_attributes']				 = 'Атрибуты тегов в XML-документе';
$_['entry_attribute_parent_id']						 = 'Атрибуты для обозначения parent_id категории';
$_['supplier_fieldset_tags']							 = 'Теги XML-документа';
$_['entry_tag_product_name']							 = 'Тег названия';
$_['entry_tag_product_description']				 = 'Тег описания';
$_['entry_tag_product_model']							 = 'Тег модели';
$_['entry_tag_product_sku']								 = 'Тег SKU (артикула)';
$_['entry_tag_product_price_purchasing']	 = 'Тег закупочной цены';
$_['entry_tag_product_price_rrp']					 = 'Тег РРЦ';
$_['entry_tag_product_quantity']					 = 'Тег количества товара';
$_['entry_tag_product_images']						 = 'Теги изображений';
$_['entry_tag_product_category']					 = 'Тег с категорией товара';
$_['entry_tag_product_manufacturer_name']	 = 'Тег с названием производителя';
$_['entry_tag_product_attributes']				 = 'Теги атрибутов';
$_['btn_supplier_modal_save']							 = 'Сохранить профиль';
$_['msg_supplier_success']								 = 'Профиль сохранен';
$_['msg_supplier_error']									 = 'Ошибка! Проверьте все поля формы!';
$_['error_supplier_name_empty']						 = 'Укажите название профиля!';
$_['error_tag_empty']											 = '%s обязателен для заполнения!';

$_['error_supplier_markup_empty']	 = 'Укажите наценку!';
$_['error_attribute_empty']				 = '%s обязателен для заполенения!';



// Import
$_['text_edit_import'] = 'Ручной импорт XML-файла';
$_['text_select_option'] = '-- Выбрать --';

$_['text_part_import'] = 'Ручной импорт';
//$_['entry_primary_language']	 = ' — выбрать основным для этого импорта';
$_['entry_language']	 = ' — выбрать основным для этого импорта';
$_['entry_file'] = 'XML-файл для ручного импорта';
$_['help_file'] = 'Обычный импорт работает через загрузку XML-файла с компьютера. Ссылки поставщика используются отдельно в настройках профиля только для cron-обновления.';
$_['error_file_main_not_saved']= 'XML-файл для главного языка не был корректно сохранен на сайте';
$_['entry_xmllink']						 = 'Ссылка на XML-файл в Интернете';

$_['btn_file']								 = 'Выбрать файл с компьютера';
$_['file_not_choosen']				 = 'Файл не выбран';
$_['xor']											 = 'ИЛИ';
$_['entry_copy_description']	 = 'Копировать описания товаров и категорий в языки, для которых не указан XML-файл';
$_['entry_copy_attributes']    = 'Копировать атрубуты товаров в языки, для которых не указан XML-файл';
$_['help_copy_attributes']     = 'Копировать атрибуты можно только в том случае, если копируются описания';
$_['entry_supplier']					 = 'Поставщик';
$_['error_supplier']					 = 'Выберите Поставщика';
$_['btn_import'] = 'Запустить ручной импорт';
$_['text_import_options']			 = 'Опции импорта';
$_['entry_delete_all']				 = 'Очистить каталог';
$_['help_delete_all']					 = 'Перед началом импорта из базы данных будут удалены все товары, категории, атрибуты и производители';
$_['entry_update_if_exist']    = 'Перезаписать все данные для существующих товаров';
$_['help_update_if_exist']	   = 'Данная опция затрет любые изменения в названии товаров и их описаниях, которые Вы могли сделать. Без этой галочки, товары, которые уже есть в базе, не будут перезаписываться.';

$_['error_warning']				 = 'Ошибка при отправке формы! Изучите все поля на предмет ошибок!';
$_['error_import_fatal']	 = 'Фаил импорта содержит серьезные ошибки';
$_['error_import_no_tags'] = 'Файл импорта не содержит необходимых тегов';


// Import Processing
$_['status_started'] = '<p>Импорт начался. НЕ закрывайте эту страницу до окончания импорта!!</p>';
$_['statistics']		 = '<p>Обработано товаров: <b>%d</b></p>';

$_['statistics_console'] = '<p>Обработано товаров в текущем фоновом запросе: <b>%1$d</b></p>'
	. '<p>Обработано товаров за время текущего импорта <b>%2$d</b></p>';

$_['success_import']  = 'Импорт успешно завершен';
$_['continued_import'] = 'Импорт продолжается...';

$_['import_placeholder_name']  = 'Нет названия';

// CodeCart PRO additions
$_['error_language'] = 'Выберите основной язык импорта';
$_['error_model_or_sku_required'] = 'Укажите тег модели или тег SKU. Достаточно одного из этих полей.';
$_['error_price_tag_required'] = 'Укажите тег закупочной цены или тег РРЦ/цены поставщика. Достаточно одного из этих полей.';
$_['text_diagnostics'] = 'Диагностика';
$_['btn_check_update'] = 'Обновить модуль';
$_['btn_clear_logs'] = 'Очистить логи';
$_['text_update_info'] = 'Текущая версия: %s. Проверка новых версий и лицензии выполняется через сайт автора: %s';
$_['text_logs_cleared'] = 'Удалено файлов логов и временных XML: %d';
$_['text_about_module'] = 'О модуле';
$_['text_author'] = 'Автор';
$_['text_version'] = 'Версия';

$_['error_simplexml_missing'] = 'На сервере отключено PHP-расширение SimpleXML. Без него импорт XML невозможен.';

$_['entry_delete_data_on_uninstall'] = 'Удалять данные модуля при удалении';
$_['help_delete_data_on_uninstall'] = 'По умолчанию выключено. Если включить, при удалении модуля будут удалены профили поставщиков и NIX-поля поставщика в товарах/категориях. Оставьте выключенным, если нужны история, аудит или возможность отката.';
$_['text_confirm_supplier_delete'] = 'Удалить этот профиль поставщика? Действие применяется сразу и не отменяется.';
$_['text_confirm_clear_logs'] = 'Удалить файлы логов NIX и временные XML-файлы?';
$_['error_request_method'] = 'Недопустимый метод запроса. Повторите действие из интерфейса модуля.';
$_['error_supplier_delete_id'] = 'ID профиля поставщика не получен. Обновите страницу и повторите действие.';
$_['error_ajax_failed'] = 'AJAX-запрос не выполнен. Проверьте user_token, права доступа и PHP error log.';
$_['placeholder_supplier_name'] = 'Пример: Основной XML поставщика';
$_['help_supplier_profile_examples'] = 'Пример: название профиля “Основной XML поставщика”, наценка “15”. Наценка применяется к закупочной цене, если задан тег закупочной цены.';
$_['help_supplier_tags_examples'] = 'Примеры тегов: name, model, vendorCode, price, optPrice, quantity, picture, categoryId, vendor, description, param.';

$_['text_confirm_delete_all_import'] = 'Включена опция “Очистить каталог”. Перед импортом могут быть удалены товары и категории этого поставщика. Продолжить?';

// Product card fields and sortable tables
$_['supplier_fieldset_product_card_fields'] = 'Дополнительные поля карточки товара';
$_['help_supplier_product_card_fields'] = 'Эти поля необязательные. Заполняйте только те XML-теги, которые реально есть у поставщика. Значения будут подставлены в стандартные поля карточки товара OpenCart/ocStore.';
$_['entry_tag_product_meta_h1'] = 'Тег HTML H1';
$_['entry_tag_product_meta_title'] = 'Тег Meta Title';
$_['entry_tag_product_meta_description'] = 'Тег Meta Description';
$_['entry_tag_product_meta_keyword'] = 'Тег Meta Keywords';
$_['entry_tag_product_tag'] = 'Тег тегов товара';
$_['entry_tag_product_currency'] = 'Тег валюты';
$_['entry_tag_product_upc'] = 'Тег UPC';
$_['entry_tag_product_ean'] = 'Тег EAN';
$_['entry_tag_product_jan'] = 'Тег JAN';
$_['entry_tag_product_isbn'] = 'Тег ISBN';
$_['entry_tag_product_mpn'] = 'Тег MPN';
$_['entry_tag_product_location'] = 'Тег расположения';
$_['entry_tag_product_minimum'] = 'Тег минимального количества';
$_['entry_tag_product_subtract'] = 'Тег вычитания со склада';
$_['entry_tag_product_stock_status_id'] = 'Тег статуса склада';
$_['entry_tag_product_shipping'] = 'Тег доставки';
$_['entry_tag_product_tax_class_id'] = 'Тег налогового класса';
$_['entry_tag_product_length'] = 'Тег длины';
$_['entry_tag_product_width'] = 'Тег ширины';
$_['entry_tag_product_height'] = 'Тег высоты';
$_['entry_tag_product_length_class_id'] = 'Тег единицы длины';
$_['entry_tag_product_weight'] = 'Тег веса';
$_['entry_tag_product_weight_class_id'] = 'Тег единицы веса';
$_['entry_tag_product_status'] = 'Тег статуса товара';
$_['entry_tag_product_sort_order'] = 'Тег порядка сортировки';
$_['entry_tag_product_google_product_category_id'] = 'Тег Google Product Category ID';
$_['column_supplier_id'] = 'ID';
$_['column_supplier_name'] = 'Поставщик';
$_['column_supplier_markup'] = 'Наценка, %';
$_['text_no_suppliers'] = 'Профили поставщиков еще не созданы';
$_['entry_tag_product_date_available'] = 'Тег даты поступления';
$_['entry_tag_product_points'] = 'Тег баллов';

// Cron
$_['text_extension'] = 'Каналы продвижения';
$_['text_cron_settings'] = 'Дополнительно: cron-обновление';
$_['entry_cron_status'] = 'Включить cron';
$_['entry_cron_token'] = 'Секретный token';
$_['entry_cron_supplier'] = 'Поставщик для cron';
$_['entry_cron_language'] = 'Основной язык cron';
$_['entry_cron_command'] = 'Команда для cron';
$_['entry_cron_url'] = 'URL для ручной проверки';
$_['entry_cron_options'] = 'Опции cron-импорта';
$_['entry_cron_update_if_exist'] = 'Обновлять существующие товары';
$_['entry_cron_copy_description'] = 'Копировать описания в языки без отдельного XML';
$_['entry_cron_copy_attributes'] = 'Копировать атрибуты в языки без отдельного XML';
$_['entry_cron_delete_all'] = 'Очищать каталог перед cron-импортом';
$_['help_cron_status'] = 'Cron запускает обновление по ссылке поставщика из поля “Ссылка на XML для обновления цен”. Не включайте чаще 1 раза в 5 минут. Для больших XML лучше запускать ночью.';
$_['help_cron_token'] = 'Секретный token защищает cron от внешнего запуска. При смене token обязательно обновите команду в панели хостинга.';
$_['help_cron_command'] = 'Для Mirohost используйте эту команду в поле “Команда”. Минуты/часы задаются отдельными полями cron в панели хостинга.';
$_['help_cron_delete_all'] = 'Опасная опция. Работает только в режиме полного cron-импорта. Для регулярного обновления цен и остатков должна быть выключена.';
$_['error_cron_token'] = 'Неверный или пустой cron token.';
$_['error_cron_disabled'] = 'Cron отключен или модуль выключен.';
$_['error_cron_empty_link'] = 'У выбранного поставщика не заполнена ссылка на XML для cron.';
$_['error_cron_download'] = 'Не удалось скачать XML: %s';
$_['error_cron_write_file'] = 'Не удалось записать временный XML-файл: %s';
$_['text_cron_multilang_hint'] = 'Для нескольких XML укажите ссылки построчно: language_id=URL_XML. Одна ссылка используется для основного языка.';
$_['text_cron_performance_warning'] = 'Внимание: cron может быть тяжелой операцией. Безопасный режим по умолчанию обновляет цены, наличие, остатки, статус наличия и статус существующих товаров. Полный cron-импорт может скачивать XML, создавать категории, обновлять изображения, описания и SEO, поэтому не запускайте его в часы пик.';

$_['placeholder_supplier_link_price'] = 'URL XML или language_id=URL_XML';

$_['help_supplier_link_price'] = 'Для cron: одна XML-ссылка или отдельная строка language_id=URL_XML для каждого языка.';

$_['text_quick_xml_tags'] = 'Быстрые примеры XML-тегов';

$_['help_quick_xml_tags'] = 'Сначала поставьте курсор в нужное поле, затем нажмите пример тега, чтобы быстро вставить его в поле.';

$_['btn_generate_token'] = 'Сгенерировать token';

$_['btn_copy_cron_command'] = 'Копировать команду';

$_['btn_copy_cron_url'] = 'Копировать URL';

$_['text_copied'] = 'Скопировано.';

$_['placeholder_cron_token'] = 'Пример: 48 случайных HEX-символов';

$_['error_ajax_failed_detail'] = 'AJAX-запрос не выполнен. HTTP-статус: %s. Ответ сервера: %s';
// CodeCart PRO production additions v1.4.3
$_['btn_export_settings'] = 'Экспорт настроек';
$_['btn_reset_settings'] = 'Сбросить настройки';
$_['help_service_actions'] = 'Экспорт сохраняет настройки и профили поставщиков в JSON. Сброс возвращает системные настройки модуля к безопасным значениям, но не удаляет профили поставщиков.';
$_['text_confirm_reset_settings'] = 'Сбросить настройки модуля к безопасным значениям по умолчанию? Профили поставщиков останутся в базе.';
$_['text_settings_reset'] = 'Настройки модуля сброшены. Модуль выключен, cron выключен, создан новый token.';
$_['diag_module_enabled'] = 'Модуль включен.';
$_['diag_module_disabled'] = 'Модуль выключен: импорт заблокирован до ручного включения и сохранения настроек.';
$_['diag_table_suppliers'] = 'Таблица профилей поставщиков nix_suppliers.';
$_['diag_product_supplier_id'] = 'Поле product.nix_supplier_id для связи товара с поставщиком.';
$_['diag_product_supplier_product_id'] = 'Поле product.nix_supplier_product_id для внешнего ID товара поставщика.';
$_['diag_simplexml'] = 'PHP-расширение SimpleXML для чтения XML-файлов.';
$_['diag_cache_writable'] = 'Папка кеша доступна для временных XML-файлов.';
$_['diag_logs_writable'] = 'Папка логов доступна для записи.';
$_['diag_main_category_ptc'] = 'Главная категория через product_to_category.main_category.';
$_['diag_main_category_product'] = 'Главная категория через product.main_category_id.';
$_['diag_google_product_category'] = 'Google Product Category ID через category.google_product_category_id или googleshopping_category.';
$_['entry_mirohost_minutes'] = 'Mirohost: минуты';
$_['entry_mirohost_hours'] = 'Mirohost: часы';
$_['entry_mirohost_days'] = 'Mirohost: дни месяца';
$_['entry_mirohost_months'] = 'Mirohost: месяцы';
$_['entry_mirohost_weekdays'] = 'Mirohost: дни недели';
$_['help_mirohost_schedule'] = 'Рекомендуемый безопасный пример для Mirohost: минуты */30, часы *, дни месяца *, месяцы *, дни недели *. Не ставьте чаще 1 раза в 5 минут.';
// CodeCart PRO import/export additions v1.4.3
$_['btn_import_settings'] = 'Импорт настроек';
$_['help_import_settings'] = 'Выберите JSON-файл, ранее созданный кнопкой “Экспорт настроек”. Импорт заменит системные настройки и профили поставщиков. Перед импортом сделайте резервную копию.';
$_['error_import_settings_failed'] = 'Не удалось импортировать настройки. Проверьте формат файла и права доступа.';
$_['error_import_settings_file'] = 'Файл настроек не выбран. Выберите JSON-файл экспорта.';
$_['error_import_settings_size'] = 'Файл настроек слишком большой. Максимум 1 МБ.';
$_['error_import_settings_json'] = 'Файл настроек имеет неправильную структуру JSON.';
$_['text_import_settings_success'] = 'Настройки импортированы. Профилей поставщиков: %d.';
$_['text_defaults_restored'] = 'Настройки модуля восстановлены по умолчанию. Модуль выключен, cron выключен, создан новый token.';
$_['diag_field_product_supplier_id'] = 'Поле product.nix_supplier_id для связи товара с поставщиком.';
$_['diag_field_product_supplier_product_id'] = 'Поле product.nix_supplier_product_id для внешнего ID товара поставщика.';
$_['diag_google_category'] = 'Google Product Category ID через category.google_product_category_id или googleshopping_category.';

// CodeCart PRO v1.4.3 UI and warning additions
$_['warning_offer_without_id'] = 'Предупреждение: в XML найден offer без атрибута id. Позиция пропущена.';
$_['warning_offer_missing_tag'] = 'Предупреждение: offer ID %s пропущен, потому что отсутствует обязательный тег %s.';
$_['warning_offer_missing_required_tags'] = 'Предупреждение: offer ID %s пропущен, потому что отсутствуют обязательные теги для полноценного импорта.';
// CodeCart PRO service UI additions v1.4.3
$_['text_service_tools'] = 'Сервисные инструменты';
$_['btn_apply_import_settings'] = 'Применить импорт';

$_['text_default'] = 'Основной магазин';

$_['text_manual_import_primary'] = 'Основной сценарий работы модуля — обычный ручной импорт XML-файла через кнопку “Ручной импорт”. Cron — только дополнительная функция для автоматического обновления цен, наличия, остатков, статуса наличия и статуса товаров по ссылке поставщика.';

$_['entry_cron_mode'] = 'Режим cron';

$_['text_cron_mode_price_stock'] = 'Цены, наличие, остатки и статус существующих товаров';

$_['text_cron_mode_full'] = 'Полный импорт по ссылке поставщика';

$_['help_cron_mode'] = 'Рекомендуемый режим для cron обновляет цены, наличие по available, количество/остатки, stock_status_id и статус существующих товаров. Он не создает новые товары, не перезаписывает описания, изображения, категории и SEO. Полный cron-импорт используйте только осознанно и лучше ночью.';

$_['help_cron_secondary_function'] = 'Cron не заменяет обычный импорт. Сначала выполните ручной импорт XML, проверьте товары, категории и поля, а cron используйте позже для регулярного обновления цен и наличия.';

// CodeCart PRO v1.4.3 supplier UI corrections
$_['text_settings_main'] = 'Основные настройки';
$_['text_supplier_settings'] = 'Профили поставщиков';
$_['text_cron_settings_short'] = 'Cron-обновление';
$_['help_supplier_settings'] = 'Здесь создаются и редактируются профили поставщиков: XML-ссылка для cron, наценка и соответствие XML-тегов полям карточки товара. Без профиля поставщика ручной импорт и cron работать не будут.';
$_['help_supplier_list'] = 'Нажмите “Добавить профиль”, чтобы настроить XML-теги поставщика. Для редактирования нажмите на название поставщика в таблице.';
$_['btn_manage_suppliers'] = 'Настроить поставщиков';
$_['help_import_supplier_select'] = 'Если нужного поставщика нет в списке, сначала создайте профиль поставщика в настройках. В профиле задаются XML-теги товара, цены, наличия, категории, SEO и ссылка для cron.';


// CodeCart PRO v1.4.3 safe supplier import additions
$_['btn_preview_import'] = 'Проверить изменения без записи';
$_['btn_apply_safe_import'] = 'Применить импорт после проверки';
$_['help_safe_import_buttons'] = 'Сначала запустите проверку. Модуль покажет, что будет обновлено, создано, пропущено и какие товары пропали у поставщика. Только после просмотра отчета запускайте применение.';
$_['help_file_formats'] = 'Поддерживаются XML, YML/YAML, CSV/TXT и XLSX. Для CSV/XLSX первая строка должна содержать названия полей: id, name, model, sku/vendorCode, price, optPrice, special_price, quantity, categoryId.';
$_['text_confirm_apply_after_preview'] = 'Применить импорт в базу? Перед этим рекомендуется выполнить проверку изменений без записи.';
$_['text_preview_ready'] = 'Проверка завершена. Запись в базу не выполнялась.';
$_['text_preview_statistics'] = 'Всего строк: %d. Будет обновлено: %d. Новых позиций: %d. Не найдено в магазине: %d. Пропало у поставщика: %d. Изменилась цена: %d. Изменился остаток: %d. Изменился статус: %d. Изменилась закупочная цена: %d. Изменилась акционная цена: %d.';
$_['text_preview_changed_products'] = 'Товары с изменениями';
$_['text_preview_new_products'] = 'Новые позиции поставщика';
$_['text_preview_not_found_products'] = 'Не найдено в магазине для обновления';
$_['text_preview_missing_products'] = 'Товары, которые пропали у поставщика';
$_['text_preview_no_rows'] = 'Нет строк для отображения.';
$_['text_preview_action_update'] = 'Будет обновлен';
$_['text_preview_action_create'] = 'Будет создан';
$_['text_preview_action_skip_not_found'] = 'Пропуск: cron/price_stock не создает товары';
$_['text_preview_action_skip_update_disabled'] = 'Пропуск: обновление существующих выключено';
$_['text_preview_action_missing'] = 'Есть в магазине, отсутствует в файле поставщика';
$_['help_preview_apply_warning'] = 'Отчет показывает первые 200 строк каждого раздела. Счетчики рассчитаны по всему файлу. В режиме cron/price_stock модуль обновляет только цену, остаток, stock_status_id, статус и закупочную цену поставщика у существующих товаров.';
$_['entry_tag_product_special_price'] = 'Тег акционной цены';
$_['help_tag_product_special_price'] = 'Например special_price, special, sale_price, discount_price или oldprice. Если тег есть и цена больше 0, модуль запишет product_special. Если тег есть и значение 0 или пусто, акции товара будут очищены при полном обновлении.';
$_['error_ziparchive_missing'] = 'PHP-расширение ZipArchive не установлено. XLSX-файл прочитать нельзя.';
$_['field_product_supplier_price'] = 'Поле product.nix_supplier_price для закупочной цены поставщика';
$_['text_run_preview_first'] = 'Сначала выполните проверку изменений без записи. После успешного отчета кнопка применения станет доступной.';

$_['error_preview_token'] = 'Ошибка безопасного импорта: сначала выполните проверку изменений без записи и не меняйте файл или настройки перед применением.';

// CodeCart PRO production fixes v1.4.3
$_['error_file'] = 'Выберите файл импорта XML, YML/YAML, CSV/TXT или XLSX.';
$_['error_file_upload_code'] = 'Файл не загружен. Код ошибки PHP upload: %s. Проверьте размер файла и настройки upload_max_filesize/post_max_size.';
$_['error_file_extension'] = 'Недопустимый формат файла. Разрешены только: %s.';
$_['error_file_size'] = 'Файл слишком большой. Максимальный размер для ручного импорта: %s.';
$_['text_about_module_description'] = 'ImportXML Clean импортирует товары, категории, изображения, производителей и атрибуты из XML/YML, CSV и XLSX поставщиков. Перед применением можно выполнить безопасную проверку изменений без записи в базу.';
$_['text_about_module_benefit'] = 'Польза модуля: первичный импорт каталога, безопасное сравнение old/new перед применением, обновление цен, остатков, статуса, закупочной цены поставщика и акционных цен. Cron можно использовать как дополнительную функцию для обновления только существующих товаров без перезаписи описаний, изображений, категорий и SEO.';
$_['diag_ziparchive'] = 'PHP-расширение ZipArchive для чтения XLSX-файлов.';
$_['column_offer_id'] = 'ID поставщика';
$_['column_product_id'] = 'ID товара';
$_['column_name'] = 'Название';
$_['column_model'] = 'Модель';
$_['column_sku'] = 'SKU';
$_['column_price_old'] = 'Цена была';
$_['column_price_new'] = 'Цена будет';
$_['column_quantity_old'] = 'Остаток был';
$_['column_quantity_new'] = 'Остаток будет';
$_['column_status_old'] = 'Статус был';
$_['column_status_new'] = 'Статус будет';
$_['column_action'] = 'Действие';

$_['error_user_token'] = 'Сессия администратора устарела или user_token неверный. Обновите страницу модуля и повторите действие.';

$_['error_preview_expired'] = 'Время безопасного предпросмотра истекло. Снова выполните проверку изменений без записи перед применением импорта.';

$_['error_file_write'] = 'Не удалось записать временный файл импорта: %s. Проверьте права на папку system/storage/cache.';
$_['diag_php_version'] = 'PHP %s; поддерживаемый диапазон: 7.4–8.5';
