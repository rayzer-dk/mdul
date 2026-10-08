<?php
/**
 * Import Pro Professional Commercial UI Diagnostics Production v3.7.0
 *
 * Admin controller. Fixes vs v2.7.0:
 *  - Heading title now ships in two flavours: plain text for <title>/breadcrumbs and
 *    raw HTML for the <h1> badge in the template (was rendered as escaped literal in v2.7.x).
 *  - PHP 7.4–8.5 hardening: all controller actions now use isset() guards on $this->request->post.
 *  - detectFields() now requires the modify permission, not just access, because it can
 *    read the first rows of an arbitrary server file given a source_path.
 *  - source_path is restricted to the upload sandbox (DIR_DOWNLOAD/import_pro/) or a
 *    public URL. Arbitrary absolute server paths are rejected.
 *  - Profile delete cascade is now performed inside the model (deletes related runs).
 */
class ControllerExtensionModuleImportPro extends Controller {
    private $error = array();

    private function adminLink($route, $args = '') {
        return html_entity_decode($this->url->link($route, $args, true), ENT_QUOTES, 'UTF-8');
    }

    public function install() {
        $this->load->model('extension/module/import_pro');
        $this->model_extension_module_import_pro->install();

        $this->load->model('user/user_group');
        if (method_exists($this->model_user_user_group, 'addPermission')) {
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/import_pro');
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/import_pro');
        }
    }

    public function uninstall() {
        $this->load->model('extension/module/import_pro');
        $this->model_extension_module_import_pro->uninstall();

        $this->load->model('user/user_group');
        if (method_exists($this->model_user_user_group, 'removePermission')) {
            $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/module/import_pro');
            $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/module/import_pro');
        }
    }

    public function index() {
        $this->load->language('extension/module/import_pro');

        $heading_html  = $this->language->get('heading_title');
        $heading_plain = trim(html_entity_decode(strip_tags($heading_html), ENT_QUOTES, 'UTF-8'));
        if ($heading_plain === '') {
            $heading_plain = 'Import Export Pro';
        }
        $this->document->setTitle($heading_plain);

        $token = isset($this->session->data['user_token']) ? $this->session->data['user_token'] : '';
        $request_token = isset($this->request->get['user_token']) ? (string)$this->request->get['user_token'] : '';
        $token_ok = ($token !== '' && $request_token !== '' && function_exists('hash_equals') && hash_equals($token, $request_token));

        if (!$token_ok || (!$this->user->hasPermission('access', 'extension/module/import_pro') && !$this->user->hasPermission('modify', 'extension/module/import_pro'))) {
            $data = array();
            $data['heading_title'] = $heading_plain;
            $data['heading_title_html'] = $heading_html;
            $data['error_warning'] = !$token_ok ? $this->language->get('error_token') : $this->language->get('error_permission');
            $data['text_access_denied_title'] = $this->language->get('text_access_denied_title');
            $data['text_access_denied_help'] = $this->language->get('text_access_denied_help');
            $data['button_cancel'] = $this->language->get('button_cancel');
            $data['cancel'] = $this->adminLink('marketplace/extension', 'user_token=' . $token . '&type=module');
            $data['breadcrumbs'] = array(
                array('text' => $this->language->get('text_home'), 'href' => $this->adminLink('common/dashboard', 'user_token=' . $token)),
                array('text' => $heading_plain, 'href' => $this->adminLink('extension/module/import_pro', 'user_token=' . $token))
            );
            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');
            $this->response->setOutput($this->load->view('extension/module/import_pro_denied', $data));
            return;
        }

        $this->load->model('extension/module/import_pro');
        $this->load->model('localisation/language');
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] !== 'POST') && (string)$this->config->get('module_import_pro_version') !== '3.7.0') {
            // Idempotent schema migration for upgrades from an already installed module.
            $this->model_extension_module_import_pro->install();
            $upgrade_settings = $this->model_setting_setting->getSetting('module_import_pro');
            $upgrade_settings['module_import_pro_status'] = 0;
            $upgrade_settings['module_import_pro_version'] = '3.7.0';
            if (empty($upgrade_settings['module_import_pro_cron_token']) || strlen((string)$upgrade_settings['module_import_pro_cron_token']) < 16) {
                $upgrade_settings['module_import_pro_cron_token'] = bin2hex(random_bytes(16));
            }
            $this->model_setting_setting->editSetting('module_import_pro', $upgrade_settings);
            $this->config->set('module_import_pro_status', 0);
            $this->config->set('module_import_pro_version', '3.7.0');
            $this->config->set('module_import_pro_cron_token', $upgrade_settings['module_import_pro_cron_token']);
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateModify()) {
            $allowed_match_fields = array('model', 'sku', 'ean', 'mpn', 'upc', 'jan', 'isbn', 'product_id', 'name');
            $default_match_field = isset($this->request->post['module_import_pro_default_match_field']) ? (string)$this->request->post['module_import_pro_default_match_field'] : 'model';
            if (!in_array($default_match_field, $allowed_match_fields, true)) {
                $default_match_field = 'model';
            }

            $cron_token = isset($this->request->post['module_import_pro_cron_token']) ? preg_replace('~[^a-zA-Z0-9_\-]+~', '', (string)$this->request->post['module_import_pro_cron_token']) : '';
            if ($cron_token === '' || strlen($cron_token) < 16) {
                $cron_token = bin2hex(random_bytes(16));
            }

            $url_timeout = isset($this->request->post['module_import_pro_url_timeout']) ? (int)$this->request->post['module_import_pro_url_timeout'] : 60;
            $url_timeout = max(5, min(300, $url_timeout));

            $image_subdir = isset($this->request->post['module_import_pro_image_subdir']) ? trim((string)$this->request->post['module_import_pro_image_subdir']) : 'catalog/import_pro/';
            $image_subdir = str_replace('\\', '/', $image_subdir);
            $image_subdir = preg_replace('~\.\.+~', '', $image_subdir);
            $image_subdir = preg_replace('~[^a-zA-Z0-9/_\-.]+~', '_', $image_subdir);
            $image_subdir = trim(preg_replace('~/+~', '/', $image_subdir), '/');
            if ($image_subdir === '') {
                $image_subdir = 'catalog/import_pro';
            }
            if (strpos($image_subdir, 'catalog/import_pro') !== 0) {
                $image_subdir = 'catalog/import_pro/' . ltrim($image_subdir, '/');
            }
            $image_subdir = rtrim($image_subdir, '/') . '/';

            $image_limit = isset($this->request->post['module_import_pro_image_limit']) ? (int)$this->request->post['module_import_pro_image_limit'] : 10;
            $image_limit = max(1, min(100, $image_limit));

            $setting = array(
                'module_import_pro_status'              => !empty($this->request->post['module_import_pro_status']) ? 1 : 0,
                'module_import_pro_default_match_field' => $default_match_field,
                'module_import_pro_default_key_field'   => $default_match_field,
                'module_import_pro_cron_token'          => $cron_token,
                'module_import_pro_url_timeout'         => $url_timeout,
                'module_import_pro_image_subdir'        => $image_subdir,
                'module_import_pro_download_images'     => !empty($this->request->post['module_import_pro_download_images']) ? 1 : 0,
                'module_import_pro_image_limit'         => $image_limit,
                'module_import_pro_uninstall_delete_data' => !empty($this->request->post['module_import_pro_uninstall_delete_data']) ? 1 : 0,
                'module_import_pro_version'             => '3.7.0',
            );

            $this->model_setting_setting->editSetting('module_import_pro', $setting);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->adminLink('extension/module/import_pro', 'user_token=' . $token));
        }

        $data = array();
        $data['user_token'] = $token;
        $data['heading_title']      = $heading_plain;
        $data['heading_title_html'] = $heading_html;
        $data['module_name']        = 'Import Export Pro';
        $data['module_version']     = '3.7.0';
        $data['author_name']        = 'CodeCart PRO';
        $data['author_url']         = 'https://codecartpro.com';
        $data['support_url']        = 'https://codecartpro.com';
        $data['update_url']         = 'https://codecartpro.com';

        // All language keys used in the template, loaded once.
        $keys = array(
            'text_edit','text_enabled','text_disabled','text_profiles','text_run','text_settings','text_no_results',
            'entry_status','entry_profile_name','entry_format','entry_match_field','entry_source_type','entry_source_path',
            'entry_default_language','entry_delimiter','entry_enclosure','entry_escape','entry_start_row','entry_sheet_name',
            'entry_run_mode','text_run_mode_full','text_run_mode_price_qty','text_recent_runs',
            'column_profile','column_mode','column_result','column_date_added',
            'entry_root_node','entry_item_node','entry_key_field','entry_field_map','entry_default_category','entry_create_manufacturer',
            'entry_create_categories','entry_update_existing','entry_create_new','entry_update_price','entry_update_quantity',
            'entry_update_images','entry_update_descriptions','entry_update_attributes','entry_update_seo',
            'entry_download_images','entry_image_subdir','entry_image_limit','entry_auto_split_images','entry_image_update_mode','entry_url_timeout','entry_update_specials','entry_update_options','entry_cron_token',
            'button_save','button_cancel','button_add_profile','button_delete','button_preview','button_import',
            'button_dry_run','button_upload','button_save_profile','button_detect_fields','button_quick_test','button_clear_log',
            'column_name','column_format','column_date_modified','help_source_path','help_source_url_example','help_source_file_example',
            'help_field_map','help_mapping_examples','help_json_example','help_formats','help_cron','text_preview','text_log','text_dry_run_ok',
            'text_import_result','text_upload_ok','text_profile_saved','text_profile_deleted',
            'text_source_type_file','text_source_type_url','text_cron_url','button_view_log',
            'text_html_mode','entry_html_table_index','entry_html_header_row',
            'entry_auth_type','entry_auth_username','entry_auth_password','text_auth_none','text_auth_basic',
            'text_export','entry_export_format','entry_export_status_filter','entry_export_category_filter',
            'entry_export_limit','entry_export_offset','entry_export_supplier_filter','button_export',
            'entry_clean_text_fields','entry_clean_description_html','entry_decode_html_entities',
            'entry_strip_invisible_chars','entry_strip_msword_markup','entry_auto_generate_seo_keyword',
            'entry_category_path_mode','help_clean_description_html','help_category_path_mode',
            'text_mirohost_cron_title','text_mirohost_cron_help','text_mirohost_cron_every_30',
            'text_atomic_import_notice','text_import_details','text_compact_ui_notice',
            'tab_settings','tab_profile','tab_fields','tab_rules','tab_run','tab_export','tab_logs','tab_diagnostics','tab_cron','tab_about',
            'text_diagnostics','text_diagnostics_help','button_run_diagnostics','column_check','column_status','column_message',
            'text_diag_ok','text_diag_warn','text_diag_error','text_diag_initial',
            'diag_php_version','diag_curl','diag_ziparchive','diag_simplexml','diag_domdocument','diag_mbstring','diag_download_dir','diag_image_folder','diag_db_table','diag_meta_h1','diag_main_category','diag_cron_token','diag_module_status',
            'diag_msg_php_version','diag_msg_curl_ok','diag_msg_curl_warn','diag_msg_zip_ok','diag_msg_zip_warn','diag_msg_simplexml_ok','diag_msg_simplexml_warn','diag_msg_dom_ok','diag_msg_dom_warn','diag_msg_mbstring_ok','diag_msg_mbstring_warn','diag_msg_download_ok','diag_msg_download_error','diag_msg_image_ok','diag_msg_image_warn','diag_msg_table_ok','diag_msg_table_missing','diag_msg_meta_h1_ok','diag_msg_meta_h1_warn','diag_msg_main_category_ok','diag_msg_main_category_legacy','diag_msg_main_category_warn','diag_msg_cron_ok','diag_msg_cron_warn','diag_msg_module_enabled','diag_msg_module_disabled',
            'error_explain_field_map_json','text_js_session_expired','text_js_profile_failed','text_js_detect_failed','text_js_preview_failed','text_js_import_failed','text_js_upload_failed',
            'text_js_delete_failed','text_js_open_log_failed','text_js_clear_failed','text_js_confirm_delete','text_js_confirm_import',
            'text_js_confirm_clear_logs','text_js_deleted','text_js_diagnostics_failed','column_row','column_product_id','text_processed','text_created','text_updated','text_skipped','text_errors','text_dry_run','text_next_offset','text_run_id',
            'entry_preview_limit','entry_run_limit','entry_run_offset','help_run_limit',
            'entry_category_mode','text_category_mode_file','text_category_mode_force','text_category_mode_both',
            'entry_force_category','entry_force_category_id','entry_import_category_mode','entry_import_category_name','entry_import_product_status_mode','entry_missing_product_action','entry_filter_in_stock_only','entry_filter_status_value',
            'entry_filter_manufacturer_contains','entry_filter_category_contains','entry_fill_missing_languages',
            'help_image_subdir','help_image_limit','help_auto_split_images','help_image_update_mode','help_url_timeout','help_cron_token','help_default_language','help_match_field',
            'help_image_subdir_profile','help_import_category','help_missing_product_action','help_default_category','help_run_mode','help_filter_status','help_filter_text',
            'help_preview_limit','help_force_category','help_auth_basic','help_decode_entities',
            'help_clean_text','help_strip_invisible','help_strip_msword','help_auto_seo','help_fill_languages',
            'help_path_categories','help_create_categories','help_create_manufacturer','help_create_new','help_update_existing',
            'text_export_language','text_export_quick_mode','text_export_quick_full','text_export_quick_test10','text_export_quick_stock','text_export_quick_none',
            'text_export_in_stock_only','text_export_all_categories','text_export_all_manufacturers','text_export_all_suppliers','entry_export_image_format','text_export_image_relative','text_export_image_url',
            'text_export_field_main','text_export_field_stock','text_export_field_seo','text_export_field_media','text_export_field_supplier',
            'text_export_preset_minimal','text_export_preset_standard','text_export_preset_full','text_export_preset_seo','text_export_preset_stock',
            'button_export_reset',
            'text_cron_full','text_cron_test','text_cron_priceqty','text_cron_field_minutes','text_cron_field_hours',
            'text_cron_field_days_month','text_cron_field_months','text_cron_field_days_week','text_cron_field_command',
            'text_about_what','text_about_what_text','text_about_features','text_about_advantages','text_about_adv_1','text_about_adv_2','text_about_adv_3','text_about_adv_4','text_about_adv_5','text_about_adv_6','text_about_adv_7','text_about_adv_8','text_about_adv_9','text_about_adv_10','text_about_adv_11','text_about_adv_12','text_about_safety','text_about_safety_text',
            'text_about_arch','text_about_arch_text','text_about_quickstart','text_about_quickstart_text','text_about_images_title','text_about_images_text','text_about_import_status_title','text_about_import_status_text','text_about_safe_modes_title','text_about_safe_modes_text','text_about_version',
            'text_run_status_initial','text_mapping_legend','text_mapping_status_initial','text_mapping_detected','button_toggle_raw_map','button_check_update','button_upgrade_module','text_author','text_author_site','text_support_site','text_manual_update_help','text_module_disabled_notice','entry_uninstall_delete_data','help_uninstall_delete_data','error_module_disabled','error_post_required','text_access_denied_title','text_access_denied_help',
            'text_field_supplier','text_field_sample',
            'text_mapping_warning_text',
            'text_mapping_warning_numeric',
            'text_field_type_text_short',
            'text_field_type_text_long',
            'text_field_type_date',
            'text_field_type_number',
            'text_field_type_url',
            'text_field_type_image',
            'text_field_type_empty',
            'help_mapping_samples',
            'text_field_samples','text_field_target','text_field_no_import',
            'text_section_status','text_section_images','text_section_cron_token','text_section_source','text_section_lang_auth',
            'text_section_parser','text_section_categories','text_section_updates','text_section_cleaning','text_section_supplier',
            'entry_supplier_mode','entry_supplier_name','entry_supplier_markup_type','entry_supplier_markup_value','entry_supplier_fixed_markup','entry_supplier_price_rounding','entry_supplier_price_stock_source_type','entry_supplier_price_stock_source_path','entry_supplier_auto_xml_params','entry_supplier_copy_attributes_to_languages',
            'text_supplier_markup_none','text_supplier_markup_percent','text_supplier_markup_fixed','text_supplier_markup_percent_fixed','text_supplier_source_same','text_image_update_keep','text_image_update_main','text_image_update_replace','text_image_update_append','text_import_category_none','text_import_category_add','text_import_category_replace','text_product_status_file','text_product_status_enabled','text_product_status_disabled','text_missing_action_none','text_missing_action_disable','text_missing_action_delete','help_supplier_mode','help_supplier_markup','help_supplier_price_stock_source','help_supplier_auto_xml_params','help_supplier_external_id',
            'text_quick_filter_in_stock','text_quick_create_path',
            'placeholder_profile_name','placeholder_source_path','placeholder_image_subdir','placeholder_filter_status',
            'placeholder_filter_text','placeholder_filter_category','placeholder_export_limit','placeholder_export_offset',
            'text_import_progress_title','text_import_progress_running','text_import_progress_finished','text_import_progress_stopped','text_import_progress_total','text_import_progress_success','text_import_progress_remaining','text_import_progress_batch','text_import_progress_offset','text_import_progress_percent','button_stop_import','help_import_progress','help_import_progress_total','help_import_progress_examples','help_run_offset','text_change_preview_title','text_changed_products','text_unchanged_products','text_price_changed','text_quantity_changed','text_status_changed','text_supplier_price_changed','text_special_changed','text_not_found_products','text_new_supplier_positions','text_missing_from_supplier','text_cron_create_blocked','text_change_field','text_change_old','text_change_new','text_no_change_rows','text_preview_limited','text_column_identifier','text_column_reason','text_column_name','text_column_price','text_column_quantity','text_safe_cron_notice',
            'placeholder_root_node','placeholder_item_node','placeholder_sheet_name',
            'text_run_mode_price_only','text_run_mode_quantity_only','text_run_mode_price_stock','text_run_mode_new_only','text_run_mode_existing_only',
            'button_safe_check','button_apply_selected','button_retry_errors','button_download_report','button_recover_queue',
            'text_safe_queue_title','text_safe_queue_help','text_queue_no_batch','text_queue_preparing','text_queue_processing','text_queue_review','text_queue_finished',
            'text_queue_pending','text_queue_processing_status','text_queue_review_status','text_queue_created','text_queue_updated','text_queue_skipped','text_queue_deleted','text_queue_error','text_queue_action','text_queue_attempts','text_queue_selected','text_queue_search','text_queue_filter_all','text_queue_select_visible','text_queue_unselect_visible','text_queue_select_all_review','text_queue_unselect_all_review',
            'text_field_policy_title','text_field_policy_help','text_policy_default','text_policy_preserve','text_policy_overwrite','text_policy_fill_empty','text_policy_merge','text_policy_replace','text_policy_clear',
            'entry_ignore_empty_fields','help_ignore_empty_fields','entry_supplier_code','help_supplier_code','entry_sync_missing_confirm','help_sync_missing_confirm','text_missing_action_zero','text_auth_password_saved','error_safe_apply_confirm','error_safe_delete_confirm'
        );
        foreach ($keys as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['success']       = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

        $data['module_import_pro_status']              = (int)$this->config->get('module_import_pro_status');
        $data['module_import_pro_default_match_field'] = $this->config->get('module_import_pro_default_match_field') ? $this->config->get('module_import_pro_default_match_field') : 'model';
        $data['module_import_pro_default_key_field']   = $this->config->get('module_import_pro_default_key_field') ? $this->config->get('module_import_pro_default_key_field') : 'model';
        $data['entry_in_stock_quantity'] = $this->language->get('entry_in_stock_quantity');
        $data['help_in_stock_quantity'] = $this->language->get('help_in_stock_quantity');
        $data['module_import_pro_cron_token']          = $this->config->get('module_import_pro_cron_token') ? $this->config->get('module_import_pro_cron_token') : bin2hex(random_bytes(16));
        $data['module_import_pro_url_timeout']         = $this->config->get('module_import_pro_url_timeout') ? (int)$this->config->get('module_import_pro_url_timeout') : 60;
        $data['module_import_pro_image_subdir']        = $this->config->get('module_import_pro_image_subdir') ? $this->config->get('module_import_pro_image_subdir') : 'catalog/import_pro/';
        $data['module_import_pro_download_images']     = $this->config->get('module_import_pro_download_images') ? 1 : 0;
        $data['module_import_pro_image_limit']         = $this->config->get('module_import_pro_image_limit') ? (int)$this->config->get('module_import_pro_image_limit') : 10;
        $data['module_import_pro_uninstall_delete_data'] = $this->config->get('module_import_pro_uninstall_delete_data') ? 1 : 0;

        $data['profiles'] = $this->model_extension_module_import_pro->getProfiles();
        $json_flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        $data['profiles_json']      = json_encode($data['profiles'], $json_flags) ?: '[]';
        $data['recent_runs']        = $this->model_extension_module_import_pro->getRecentRuns(20);
        $data['languages']          = $this->model_localisation_language->getLanguages();
        $data['default_language_id'] = (int)$this->config->get('config_language_id');

        $this->load->model('catalog/category');
        $this->load->model('catalog/manufacturer');
        $categories = $this->model_catalog_category->getCategories(array('sort' => 'name', 'order' => 'ASC'));
        foreach ($categories as &$category) {
            if (isset($category['name'])) {
                $category['name'] = $this->normalizeCategorySelectName($category['name']);
            }
        }
        unset($category);
        $data['categories']    = $categories;
        $data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers();
        $data['suppliers']     = $this->model_extension_module_import_pro->getSuppliersForExport();

        $cron_base = HTTPS_CATALOG ? HTTPS_CATALOG : HTTP_CATALOG;
        $data['cron_base_url'] = $cron_base . 'index.php?route=extension/module/import_pro/cronImport&scheduled=1&limit=100&profile_id=';

        $data['save_profile_url']   = $this->adminLink('extension/module/import_pro/saveProfile',   'user_token=' . $token);
        $data['delete_profile_url'] = $this->adminLink('extension/module/import_pro/deleteProfile', 'user_token=' . $token);
        $data['preview_url']        = $this->adminLink('extension/module/import_pro/preview',       'user_token=' . $token);
        $data['upload_url']         = $this->adminLink('extension/module/import_pro/uploadSource',  'user_token=' . $token);
        $data['get_run_url']        = $this->adminLink('extension/module/import_pro/getRun',        'user_token=' . $token);
        $data['export_url']         = $this->adminLink('extension/module/import_pro/exportProducts','user_token=' . $token);
        $data['detect_fields_url']  = $this->adminLink('extension/module/import_pro/detectFields',  'user_token=' . $token);
        $data['clear_log_url']      = $this->adminLink('extension/module/import_pro/clearRunLog',   'user_token=' . $token);
        $data['diagnostic_url']     = $this->adminLink('extension/module/import_pro/diagnostics',   'user_token=' . $token);
        $data['safe_start_url']      = $this->adminLink('extension/module/import_pro/safeStart',      'user_token=' . $token);
        $data['safe_prepare_url']    = $this->adminLink('extension/module/import_pro/safePrepare',    'user_token=' . $token);
        $data['safe_process_url']    = $this->adminLink('extension/module/import_pro/safeProcess',    'user_token=' . $token);
        $data['safe_summary_url']    = $this->adminLink('extension/module/import_pro/safeSummary',    'user_token=' . $token);
        $data['safe_results_url']    = $this->adminLink('extension/module/import_pro/safeResults',    'user_token=' . $token);
        $data['safe_selection_url']  = $this->adminLink('extension/module/import_pro/safeSelection',  'user_token=' . $token);
        $data['safe_apply_url']      = $this->adminLink('extension/module/import_pro/safeApply',      'user_token=' . $token);
        $data['safe_retry_url']      = $this->adminLink('extension/module/import_pro/safeRetry',      'user_token=' . $token);
        $data['safe_cancel_url']     = $this->adminLink('extension/module/import_pro/safeCancel',     'user_token=' . $token);
        $data['safe_recover_url']    = $this->adminLink('extension/module/import_pro/safeRecover',    'user_token=' . $token);
        $data['safe_report_url']     = $this->adminLink('extension/module/import_pro/safeReport',     'user_token=' . $token);

        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'),      'href' => $this->adminLink('common/dashboard', 'user_token=' . $token)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->adminLink('marketplace/extension', 'user_token=' . $token . '&type=module')),
            array('text' => $heading_plain,                         'href' => $this->adminLink('extension/module/import_pro', 'user_token=' . $token))
        );

        $data['action'] = $this->adminLink('extension/module/import_pro', 'user_token=' . $token);
        $data['cancel'] = $this->adminLink('marketplace/extension', 'user_token=' . $token . '&type=module');

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/import_pro', $data));
    }

    private function normalizeCategorySelectName($name) {
        $name = html_entity_decode((string)$name, ENT_QUOTES, 'UTF-8');
        $nbsp = html_entity_decode('&nbsp;', ENT_QUOTES, 'UTF-8');
        $name = str_replace(array($nbsp, "\xC2\xA0"), ' ', $name);
        $name = preg_replace('~\s*>\s*~u', ' > ', $name);
        $name = preg_replace('~[ \t]+~u', ' ', $name);
        return trim($name);
    }

    public function uploadSource() {
        $this->load->language('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        if (!$this->validateModify()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }
        if (!$json && !$this->config->get('module_import_pro_status')) {
            $json['error'] = $this->language->get('error_module_disabled');
        }

        if (!isset($this->request->files['file']) || empty($this->request->files['file']['name'])) {
            $json['error'] = $this->language->get('error_file');
        } elseif (!empty($this->request->files['file']['error'])) {
            $json['error'] = $this->language->get('error_upload') . ' Code: ' . (int)$this->request->files['file']['error'];
        }

        if (!$json) {
            $upload_dir = DIR_DOWNLOAD . 'import_pro/';
            if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true)) {
                $json['error'] = $this->language->get('error_upload') . ' Cannot create upload directory.';
            } elseif (!is_writable($upload_dir)) {
                $json['error'] = $this->language->get('error_upload') . ' Upload directory is not writable.';
            }

            $filename = basename(html_entity_decode($this->request->files['file']['name'], ENT_QUOTES, 'UTF-8'));
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, array('csv', 'xlsx', 'xml', 'yml', 'yaml', 'json', 'html', 'htm', 'txt'), true)) {
                $json['error'] = $this->language->get('error_unsupported_format');
            }

            if (!$json && (!isset($this->request->files['file']['size']) || (int)$this->request->files['file']['size'] <= 0 || (int)$this->request->files['file']['size'] > 104857600)) {
                $json['error'] = $this->language->get('error_upload') . ' Maximum allowed size is 100 MB.';
            }

            if (!$json && !is_uploaded_file($this->request->files['file']['tmp_name'])) {
                $json['error'] = $this->language->get('error_upload');
            }

            if (!$json) {
                $safe = preg_replace('~[^a-zA-Z0-9\.\-_]+~', '_', $filename);
                $target = $upload_dir . time() . '_' . bin2hex(random_bytes(4)) . '_' . $safe;

                if (move_uploaded_file($this->request->files['file']['tmp_name'], $target)) {
                    $json['success'] = $this->language->get('text_upload_ok');
                    $json['path']    = $target;
                } else {
                    $json['error']   = $this->language->get('error_upload');
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function saveProfile() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        if (!$this->validateModify()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }

        if (!$json) {
            try {
                $profile_id = $this->model_extension_module_import_pro->saveProfile($this->request->post);
                $json['success']    = $this->language->get('text_profile_saved');
                $json['profile_id'] = $profile_id;
                $json['profiles']   = $this->model_extension_module_import_pro->getProfiles();
            } catch (Throwable $e) {
                $this->log->write('Import Pro saveProfile error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $json['error'] = $this->explainException($e);
            }
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function deleteProfile() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        if (!$this->validateModify()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }

        $profile_id = isset($this->request->post['profile_id']) ? (int)$this->request->post['profile_id'] : 0;
        if (!$profile_id) {
            $json['error'] = $this->language->get('error_profile');
        }

        if (!$json) {
            $this->model_extension_module_import_pro->deleteProfile($profile_id);
            $json['success']  = $this->language->get('text_profile_deleted');
            $json['profiles'] = $this->model_extension_module_import_pro->getProfiles();
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function preview() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        if (!$this->validateAccess()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }
        if (!$json && !$this->config->get('module_import_pro_status')) {
            $json['error'] = $this->language->get('error_module_disabled');
        }

        $profile_id = isset($this->request->post['profile_id']) ? (int)$this->request->post['profile_id'] : 0;
        if (!$profile_id) {
            $json['error'] = $this->language->get('error_profile');
        }

        if (!$json) {
            try {
                $limit  = isset($this->request->post['limit']) ? max(1, (int)$this->request->post['limit']) : 0;
                $result = $this->model_extension_module_import_pro->previewProfile($profile_id, $limit);
                $json['success']  = true;
                $json['headers']  = $result['headers'];
                $json['rows']     = $result['rows'];
                $json['analysis'] = $result['analysis'];
            } catch (Throwable $e) {
                $json['error'] = $this->explainException($e);
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function runImport() {
        $this->load->language('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        if (!$this->validateModify()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        } else {
            $json['error'] = $this->language->get('error_direct_import_disabled');
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function getRun() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->validateAccess()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }

        $run_id = isset($this->request->get['run_id']) ? (int)$this->request->get['run_id'] : 0;
        if (!$run_id) {
            $json['error'] = $this->language->get('error_run_id');
        }

        if (!$json) {
            $run = $this->model_extension_module_import_pro->getRun($run_id);
            if (!$run) {
                $json['error'] = $this->language->get('error_run_not_found');
            } else {
                $json['success'] = true;
                $json['run']     = $run;
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function clearRunLog() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        if (!$this->validateModify()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }

        if (!$json) {
            try {
                $deleted = $this->model_extension_module_import_pro->clearRunLog(7);
                $json['success'] = true;
                $json['deleted'] = (int)$deleted;
            } catch (Throwable $e) {
                $json['error'] = $this->explainException($e);
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function detectFields() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->requirePost($json)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        // detectFields can read the first rows of an arbitrary file path the admin
        // sends in. We require the modify permission to keep this on a need-to-edit
        // basis, in line with saveProfile/runImport.
        if (!$this->validateModify()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }
        if (!$json && !$this->config->get('module_import_pro_status')) {
            $json['error'] = $this->language->get('error_module_disabled');
        }

        if (!$json) {
            try {
                $result = $this->model_extension_module_import_pro->detectSourceFields($this->request->post);
                $json['success']    = true;
                $json['headers']    = $result['headers'];
                $json['sample_row'] = $result['sample_row'];
                $json['rows']       = $result['rows'];
            } catch (Throwable $e) {
                $json['error'] = $this->explainException($e);
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function safeStart() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();
        if (!$this->requirePost($json)) { return $this->sendJson($json); }
        if (!$this->validateModify()) { $json['error'] = $this->error['warning']; }
        if (!$json && !$this->config->get('module_import_pro_status')) { $json['error'] = $this->language->get('error_module_disabled'); }
        $profile_id = isset($this->request->post['profile_id']) ? (int)$this->request->post['profile_id'] : 0;
        $run_mode = isset($this->request->post['run_mode']) ? (string)$this->request->post['run_mode'] : 'full';
        if (!$profile_id) { $json['error'] = $this->language->get('error_profile'); }
        if (!$json) {
            try {
                $json['success'] = true;
                $json['result'] = $this->model_extension_module_import_pro->createSafeImportBatch($profile_id, $run_mode);
            } catch (Throwable $e) {
                $this->log->write('Import Export PRO safeStart: ' . $e->getMessage());
                $json['error'] = $this->explainException($e);
            }
        }
        return $this->sendJson($json);
    }

    public function safePrepare() {
        return $this->safeQueueAction('prepare');
    }

    public function safeProcess() {
        return $this->safeQueueAction('process');
    }

    public function safeSummary() {
        return $this->safeQueueAction('summary');
    }

    public function safeResults() {
        return $this->safeQueueAction('results');
    }

    public function safeSelection() {
        return $this->safeQueueAction('selection');
    }

    public function safeApply() {
        return $this->safeQueueAction('apply');
    }

    public function safeRetry() {
        return $this->safeQueueAction('retry');
    }

    public function safeCancel() {
        return $this->safeQueueAction('cancel');
    }

    public function safeRecover() {
        return $this->safeQueueAction('recover');
    }

    private function safeQueueAction($action) {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro_queue');
        $json = array();
        if (!$this->requirePost($json)) { return $this->sendJson($json); }
        $read_actions = array('summary','results');
        $allowed = in_array($action, $read_actions, true) ? $this->validateAccess() : $this->validateModify();
        if (!$allowed) { $json['error'] = $this->error['warning']; }
        if (!$json && !$this->config->get('module_import_pro_status')) { $json['error'] = $this->language->get('error_module_disabled'); }
        $batch_id = isset($this->request->post['batch_id']) ? trim((string)$this->request->post['batch_id']) : '';
        if ($batch_id === '' || !preg_match('/^[a-zA-Z0-9_-]{10,40}$/', $batch_id)) { $json['error'] = $this->language->get('error_invalid_batch_id'); }
        if ($json) { return $this->sendJson($json); }

        try {
            if ($action === 'prepare') {
                $limit = isset($this->request->post['limit']) ? max(10, min(1000, (int)$this->request->post['limit'])) : 250;
                $result = $this->model_extension_module_import_pro_queue->prepareQueueBatch('product', $batch_id, $limit);
            } elseif ($action === 'process') {
                $limit = isset($this->request->post['limit']) ? max(1, min(100, (int)$this->request->post['limit'])) : 20;
                $result = $this->model_extension_module_import_pro_queue->processQueueBatch('product', $batch_id, $limit);
            } elseif ($action === 'summary') {
                $result = $this->model_extension_module_import_pro_queue->getBatchSummary($batch_id, 300);
            } elseif ($action === 'results') {
                $status = isset($this->request->post['status']) ? (string)$this->request->post['status'] : '';
                $search = isset($this->request->post['search']) ? (string)$this->request->post['search'] : '';
                $page = isset($this->request->post['page']) ? max(1, (int)$this->request->post['page']) : 1;
                $limit = isset($this->request->post['limit']) ? max(10, min(200, (int)$this->request->post['limit'])) : 50;
                $result = $this->model_extension_module_import_pro_queue->getBatchResultPage('product', $batch_id, $status, $search, $page, $limit);
            } elseif ($action === 'selection') {
                $ids = array();
                if (isset($this->request->post['queue_ids'])) {
                    $raw = $this->request->post['queue_ids'];
                    if (is_string($raw)) { $raw = json_decode($raw, true); }
                    foreach ((array)$raw as $id) { if ((int)$id > 0) { $ids[] = (int)$id; } }
                }
                $selected = !empty($this->request->post['selected']) ? 1 : 0;
                $all = !empty($this->request->post['all']) ? 1 : 0;
                $result = array('updated' => $this->model_extension_module_import_pro_queue->setReviewSelection('product', $batch_id, $ids, $selected, $all));
            } elseif ($action === 'apply') {
                $result = $this->model_extension_module_import_pro_queue->applySelectedReview('product', $batch_id);
            } elseif ($action === 'retry') {
                $result = array('updated' => $this->model_extension_module_import_pro_queue->retryErrors('product', $batch_id));
            } elseif ($action === 'cancel') {
                $result = array('updated' => $this->model_extension_module_import_pro_queue->cancelBatch('product', $batch_id));
            } else {
                $minutes = isset($this->request->post['minutes']) ? max(5, min(1440, (int)$this->request->post['minutes'])) : 30;
                $result = array('updated' => $this->model_extension_module_import_pro_queue->recoverStuckProcessing($batch_id, $minutes));
            }
            if (is_array($result) && !empty($result['error'])) {
                $json['error'] = $result['error'];
            } else {
                $json['success'] = true;
                $json['result'] = $result;
            }
        } catch (Throwable $e) {
            $this->log->write('Import Export PRO safe queue ' . $action . ': ' . $e->getMessage());
            $json['error'] = $this->explainException($e);
        }
        return $this->sendJson($json);
    }

    public function safeReport() {
        $this->load->language('extension/module/import_pro');
        if (!$this->validateToken() || !$this->validateAccess()) {
            $this->response->redirect($this->adminLink('extension/module/import_pro', 'user_token=' . (isset($this->session->data['user_token']) ? $this->session->data['user_token'] : '')));
            return;
        }
        if (!$this->config->get('module_import_pro_status')) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput($this->language->get('error_module_disabled'));
            return;
        }
        $batch_id = isset($this->request->get['batch_id']) ? trim((string)$this->request->get['batch_id']) : '';
        if (!preg_match('/^[a-zA-Z0-9_-]{10,40}$/', $batch_id)) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput($this->language->get('error_invalid_batch_id'));
            return;
        }
        $this->load->model('extension/module/import_pro_queue');
        $filename = 'import_pro_report_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $batch_id) . '.csv';
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        $this->model_extension_module_import_pro_queue->streamBatchReport('product', $batch_id, $output, ';');
        fclose($output);
        exit;
    }

    private function sendJson($json) {
        while (ob_get_level()) { ob_end_clean(); }
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        return null;
    }

    public function diagnostics() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');
        $json = array();

        if (!$this->validateAccess()) {
            $json['error'] = !empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }

        if (!$json) {
            try {
                $json['success'] = true;
                $json['items'] = $this->model_extension_module_import_pro->getDiagnostics();
            } catch (Throwable $e) {
                $this->log->write('Import Pro diagnostics error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                $json['error'] = $this->explainException($e);
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function exportProducts() {
        $this->load->language('extension/module/import_pro');
        $this->load->model('extension/module/import_pro');

        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST') {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $message = $this->language->get('error_post_required');
            $this->response->setOutput($message !== 'error_post_required' ? $message : 'This export action must be sent by POST. Refresh the admin page and try again.');
            return;
        }

        if (!$this->validateAccess()) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput(!empty($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'));
            return;
        }
        if (!$this->config->get('module_import_pro_status')) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput($this->language->get('error_module_disabled'));
            return;
        }

        try {
            $request = !empty($this->request->post) ? $this->request->post : $this->request->get;
            $format  = isset($request['format']) ? strtolower((string)$request['format']) : 'csv';
            if (!in_array($format, array('csv', 'xlsx', 'xml', 'json', 'rozetka_yml', 'epicentr_xml', 'hotline_xml', 'prom_yml', 'price_ua_yml', 'facebook_csv', 'pinterest_csv', 'tiktok_csv'), true)) {
                $format = 'csv';
            }
            $filters = array(
                'status'          => isset($request['status'])          ? $request['status']          : '',
                'category_id'     => isset($request['category_id'])     ? (int)$request['category_id']     : 0,
                'manufacturer_id' => isset($request['manufacturer_id']) ? (int)$request['manufacturer_id'] : 0,
                'supplier_id'     => isset($request['supplier_id'])     ? (int)$request['supplier_id']     : 0,
                'language_id'     => isset($request['language_id'])     ? (int)$request['language_id']     : 0,
                'in_stock_only'   => !empty($request['in_stock_only']) ? 1 : 0,
                'limit'           => isset($request['limit'])           ? (int)$request['limit']           : 0,
                'offset'          => isset($request['offset'])          ? (int)$request['offset']          : 0,
                'fields'          => isset($request['fields'])          ? (array)$request['fields']        : array(),
                'image_format'    => isset($request['image_format'])    ? (string)$request['image_format'] : 'relative',
            );
            if ($format === 'csv') {
                $filename = 'export_products_' . date('Ymd_His') . '.csv';
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('X-Content-Type-Options: nosniff');
                }
                $output = fopen('php://output', 'wb');
                $this->model_extension_module_import_pro->streamProductsCsv($filters, $output);
                fclose($output);
                exit;
            }

            $result = $this->model_extension_module_import_pro->exportProducts($format, $filters);
            $this->response->addHeader('Content-Description: File Transfer');
            $this->response->addHeader('Content-Type: ' . $result['mime'] . '; charset=utf-8');
            $this->response->addHeader('Content-Disposition: attachment; filename="' . basename($result['filename']) . '"');
            $this->response->addHeader('Expires: 0');
            $this->response->addHeader('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            $this->response->addHeader('Pragma: public');
            $this->response->addHeader('Content-Length: ' . strlen($result['content']));
            $this->response->setOutput($result['content']);
        } catch (Throwable $e) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput($this->explainException($e));
        }
    }



    protected function requirePost(&$json) {
        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST') {
            $json['error'] = $this->language->get('error_post_required');
            if ($json['error'] === 'error_post_required') {
                $json['error'] = 'This action must be sent by POST. Refresh the admin page and try again.';
            }
            return false;
        }

        return true;
    }

    protected function explainException(Throwable $e) {
        $message = trim((string)$e->getMessage());
        $technical = $this->language->get('text_error_technical');
        if ($technical === 'text_error_technical') {
            $technical = 'Technical detail:';
        }

        $rules = array(
            'URL source must be a public HTTP/HTTPS address' => 'error_explain_public_url',
            'Source URL must be a public HTTP/HTTPS address' => 'error_explain_public_url',
            'Redirect URL is not allowed' => 'error_explain_redirect_url',
            'Failed to download source from URL' => 'error_explain_download_failed',
            'Empty response from URL source' => 'error_explain_empty_url',
            'Remote file is too large' => 'error_explain_remote_too_large',
            'Too many URL redirects' => 'error_explain_redirects',
            'Profile not found' => 'error_explain_profile_not_found',
            'Another Import Pro run is already active' => 'error_explain_import_lock',
            'Source file not found' => 'error_explain_source_missing',
            'Unsupported source format' => 'error_explain_unsupported_format',
            'Unsupported format' => 'error_explain_unsupported_format',
            'Unsupported export format' => 'error_explain_unsupported_export_format',
            'Unsupported source type' => 'error_explain_source_type',
            'Source path is required' => 'error_explain_source_required',
            'Unsupported file extension' => 'error_explain_file_extension',
            'Unsupported remote source extension' => 'error_explain_file_extension',
            'Source path must not contain traversal sequences' => 'error_explain_source_path_security',
            'File source must live under DIR_DOWNLOAD/import_pro/' => 'error_explain_source_sandbox',
            'Cannot save downloaded source file' => 'error_explain_cannot_save_source',
            'Cannot open CSV file' => 'error_explain_csv_open',
            'JSON source is too large' => 'error_explain_json_too_large',
            'Invalid JSON' => 'error_explain_json_invalid',
            'JSON must contain an array of objects' => 'error_explain_json_array',
            'SimpleXML PHP extension is required for XML import' => 'error_explain_simplexml_xml',
            'SimpleXML PHP extension is required for XLSX' => 'error_explain_simplexml_xlsx',
            'Invalid XML' => 'error_explain_xml_invalid',
            'XML item node not found' => 'error_explain_xml_item_node',
            'DOMDocument is required for HTML import' => 'error_explain_dom_html',
            'Empty HTML source' => 'error_explain_html_empty',
            'No HTML table found' => 'error_explain_html_table',
            'ZipArchive is required for XLSX export' => 'error_explain_zip_xlsx_export',
            'ZipArchive is required for XLSX' => 'error_explain_zip_xlsx',
            'Cannot open XLSX' => 'error_explain_xlsx_open',
            'Worksheet not found in XLSX' => 'error_explain_xlsx_sheet',
            'Missing product name for default language' => 'error_explain_missing_name',
            'Cannot create XLSX archive' => 'error_explain_xlsx_create',
            'Profile name is required' => 'error_explain_profile_name',
            'Unsupported match field' => 'error_explain_match_field',
            'Field map JSON is invalid' => 'error_explain_field_map_json',
            'Supplier name is required when supplier mode is enabled' => 'error_explain_supplier_name',
            'Price/stock source:' => 'error_explain_price_stock_source'
        );

        foreach ($rules as $needle => $key) {
            if (stripos($message, $needle) !== false) {
                $text = $this->language->get($key);
                if ($text !== $key) {
                    return $text . ($message !== '' ? ' ' . $technical . ' ' . $message : '');
                }
            }
        }

        $fallback = $this->language->get('error_explain_generic');
        if ($fallback === 'error_explain_generic') {
            $fallback = 'The operation failed. Check the source file, profile settings and server error log.';
        }

        return $fallback . ($message !== '' ? ' ' . $technical . ' ' . $message : '');
    }

    protected function validateToken() {
        $session_token = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
        $request_token = isset($this->request->get['user_token'])  ? (string)$this->request->get['user_token']  : '';

        if ($session_token === '' || $request_token === '' || !hash_equals($session_token, $request_token)) {
            $this->error['warning'] = $this->language->get('error_token');
        }

        return !$this->error;
    }

    protected function validateModify() {
        $this->validateToken();

        if (!$this->user->hasPermission('modify', 'extension/module/import_pro')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    protected function validateAccess() {
        $this->validateToken();

        if (!$this->user->hasPermission('access', 'extension/module/import_pro') && !$this->user->hasPermission('modify', 'extension/module/import_pro')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
