<?php

/**
 * @category   OpenCart
 * @package    ImportXML Clean PRO v1.4.2
 * @author     CodeCart PRO
 * @link       https://codecartpro.com
 * @copyright  CodeCart PRO
 */

class ModelExtensionFeedNix extends Model {
	private $nix_column_cache = [];
	private $nix_table_cache = [];
	private $nix_allowed_tables = [
		'product', 'category', 'nix_suppliers', 'googleshopping_category',
		'manufacturer_description', 'product_description', 'product_to_category'
	];
	private $nix_allowed_columns = [
		'product' => ['main_category_id', 'nix_supplier_id', 'nix_supplier_product_id', 'nix_supplier_price'],
		'category' => ['google_product_category_id', 'nix_supplier_id', 'nix_supplier_category_id'],
		'manufacturer_description' => ['name', 'description', 'meta_title', 'meta_h1', 'h1', 'meta_description', 'meta_keyword'],
		'product_description' => ['meta_h1', 'h1'],
		'product_to_category' => ['main_category'],
		'googleshopping_category' => ['category_id', 'google_product_category_id', 'google_category_id', 'google_product_category', 'google_category', 'google_taxonomy_id'],
		'nix_suppliers' => []
	];
	private $nix_allowed_indexes = [
		'product' => ['idx_nix_supplier_product'],
		'category' => ['idx_nix_supplier_category']
	];

	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nix_suppliers` (
			`supplier_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(255) NOT NULL,
			`markup` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
			`link_price` TEXT NOT NULL,
			`tags` MEDIUMTEXT NOT NULL,
			`attributes` MEDIUMTEXT NOT NULL,
			PRIMARY KEY (`supplier_id`),
			KEY `idx_name` (`name`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->ensureColumn('product', 'nix_supplier_id', "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `product_id`");
		$this->ensureColumn('product', 'nix_supplier_product_id', "VARCHAR(128) NOT NULL DEFAULT '' AFTER `nix_supplier_id`");
		$this->ensureColumn('product', 'nix_supplier_price', "DECIMAL(15,4) NOT NULL DEFAULT '0.0000' AFTER `nix_supplier_product_id`");
		$this->ensureIndex('product', 'idx_nix_supplier_product', ['nix_supplier_id', 'nix_supplier_product_id']);

		$this->ensureColumn('category', 'nix_supplier_id', "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `category_id`");
		$this->ensureColumn('category', 'nix_supplier_category_id', "INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `nix_supplier_id`");
		$this->ensureIndex('category', 'idx_nix_supplier_category', ['nix_supplier_id', 'nix_supplier_category_id']);
	}
	
	public function uninstall() {
		$delete_data = (int)$this->config->get('feed_nix_delete_data_on_uninstall');
		if (!$delete_data && (int)$this->config->get('module_nix_delete_data_on_uninstall')) {
			$delete_data = 1;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` IN ('nix', 'feed_nix', 'module_nix')");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` IN ('NiceImportXML', 'NiceImportXMLClean', 'ImportXMLCleanCodeCartPro133', 'ImportXMLCleanCodeCartPro134', 'ImportXMLCleanCodeCartPro135', 'ImportXMLCleanCodeCartPro136', 'ImportXMLCleanCodeCartPro137', 'ImportXMLCleanCodeCartPro138', 'ImportXMLCleanCodeCartPro139', 'ImportXMLCleanCodeCartPro1310', 'ImportXMLCleanCodeCartPro1311', 'ImportXMLCleanCodeCartPro1312', 'ImportXMLCleanCodeCartPro1313', 'ImportXMLCleanCodeCartPro1314', 'ImportXMLCleanCodeCartPro1315', 'ImportXMLCleanCodeCartPro1316', 'ImportXMLCleanCodeCartPro1317') OR `name` LIKE '%Nice Import XML%' OR `name` LIKE '%ImportXML Clean%' OR `xml` LIKE '%price_purchasing%' OR `xml` LIKE '%price_rrp%'");

		if ($delete_data) {
			$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "nix_suppliers`");
			$this->dropColumn('product', 'nix_supplier_id');
			$this->dropColumn('product', 'nix_supplier_product_id');
			$this->dropColumn('product', 'nix_supplier_price');
			$this->dropColumn('category', 'nix_supplier_id');
			$this->dropColumn('category', 'nix_supplier_category_id');
		}
	}

	public function cleanupLegacyModification() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` IN ('NiceImportXML', 'NiceImportXMLClean', 'ImportXMLCleanCodeCartPro133', 'ImportXMLCleanCodeCartPro134', 'ImportXMLCleanCodeCartPro135', 'ImportXMLCleanCodeCartPro136', 'ImportXMLCleanCodeCartPro137', 'ImportXMLCleanCodeCartPro138', 'ImportXMLCleanCodeCartPro139', 'ImportXMLCleanCodeCartPro1310', 'ImportXMLCleanCodeCartPro1311', 'ImportXMLCleanCodeCartPro1312', 'ImportXMLCleanCodeCartPro1313', 'ImportXMLCleanCodeCartPro1314', 'ImportXMLCleanCodeCartPro1315', 'ImportXMLCleanCodeCartPro1316', 'ImportXMLCleanCodeCartPro1317') OR (`name` LIKE '%Nice Import XML%' AND (`xml` LIKE '%price_purchasing%' OR `xml` LIKE '%price_rrp%' OR `xml` LIKE '%product_form.twig%'))");
	}

	private function ensureColumn($table, $column, $definition) {
		if (!$this->isAllowedColumn($table, $column)) {
			return false;
		}
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` WHERE `Field` = '" . $this->db->escape($column) . "'");
		if (!$query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD `" . $this->db->escape($column) . "` " . $definition);
		}
	}

	private function dropColumn($table, $column) {
		if (!$this->isAllowedColumn($table, $column)) {
			return false;
		}
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` WHERE `Field` = '" . $this->db->escape($column) . "'");
		if ($query->num_rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` DROP COLUMN `" . $this->db->escape($column) . "`");
		}
	}

	private function ensureIndex($table, $index, array $columns) {
		if (!$this->isAllowedTable($table) || !isset($this->nix_allowed_indexes[$table]) || !in_array($index, $this->nix_allowed_indexes[$table], true)) {
			return false;
		}
		foreach ($columns as $column) {
			if (!$this->isAllowedColumn($table, $column)) {
				return false;
			}
		}
		$query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE `Key_name` = '" . $this->db->escape($index) . "'");
		if (!$query->num_rows) {
			$parts = [];
			foreach ($columns as $column) {
				$parts[] = '`' . $this->db->escape($column) . '`';
			}
			$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD INDEX `" . $this->db->escape($index) . "` (" . implode(',', $parts) . ")");
		}
	}


	public function supplierAdd($data) {
		$sql = "INSERT INTO `" . DB_PREFIX . "nix_suppliers` SET"
      . " `name` = '" . $this->db->escape($data['name']) . "',"
      . " `markup` = '" . (float)$data['markup'] . "',"
      . " `link_price` = '" . $this->db->escape($data['link_price']) . "',"
      . " `tags` = '" . $this->db->escape(json_encode($data['tags'], JSON_UNESCAPED_UNICODE)) . "',"
			. " `attributes` = '" . $this->db->escape(json_encode($data['attributes'], JSON_UNESCAPED_UNICODE)) . "'";

		$this->db->query($sql);
		
		$supplier_id = $this->db->getLastId();
		
		if ($supplier_id) {
			return $supplier_id;
		}
		
		return false;
	}
	
	public function supplierEdit($data) {
		$sql = "UPDATE `" . DB_PREFIX . "nix_suppliers` SET"
      . " `name` = '" . $this->db->escape($data['name']) . "',"
			. " `markup` = '" . (float)$data['markup'] . "',"
      . " `link_price` = '" . $this->db->escape($data['link_price']) . "',"
      . " `tags` = '" . $this->db->escape(json_encode($data['tags'], JSON_UNESCAPED_UNICODE)) . "',"
      . " `attributes` = '" . $this->db->escape(json_encode($data['attributes'], JSON_UNESCAPED_UNICODE)) . "'"
			. " WHERE `supplier_id` = '" . (int)$data['supplier_id'] . "'";

		$query = $this->db->query($sql);

		return $query;
	}
	
	public function supplierMarkup($supplier_id) {
		$sql = "SELECT `markup` FROM `" . DB_PREFIX . "nix_suppliers` WHERE `supplier_id` = '" . (int)$supplier_id . "'";

		$query = $this->db->query($sql);	
		
		if ($query->row) {
			return (float)$query->row['markup'];
		}
		
		return false;
	}
	
	public function supplierList() {
		$sql = "SELECT * FROM `" . DB_PREFIX . "nix_suppliers` ORDER BY `supplier_id` ASC";

		$query = $this->db->query($sql);
		
		$suppliers = [];
		
		if ($query->num_rows > 0) {
			foreach ($query->rows as $row) {
				$suppliers[$row['supplier_id']] = $row;
				$suppliers[$row['supplier_id']]['tags'] = json_decode($row['tags'], true) ?: [];
				$suppliers[$row['supplier_id']]['attributes'] = json_decode($row['attributes'], true) ?: [];
			}
		}

		return $suppliers;
	}
	
	public function supplierGet($supplier_id) {
		$res = [];
		
		$sql = "SELECT * FROM `" . DB_PREFIX . "nix_suppliers` WHERE `supplier_id` = '" . (int)$supplier_id . "'";

		$query = $this->db->query($sql);
		
		
		if ($query->row) {
			$res = $query->row;
			$res['tags'] = json_decode($query->row['tags'], true) ?: [];
			$res['attributes'] = json_decode($query->row['attributes'], true) ?: [];
		}
		
		return $res;
	}
	
	public function supplierDelete($supplier_id) {
		$sql = "DELETE FROM `" . DB_PREFIX . "nix_suppliers` WHERE `supplier_id` = '" . (int)$supplier_id . "'";

		$query = $this->db->query($sql);
		
		return $query;
	}
	
	
	
	
	
	/* ------------------------------------------------------------------------
	  IMPORT
	------------------------------------------------------------------------- */

	/** Создаем новую категорию
	 *
	 */
	public function addCategory($data) {
		$this->stdelog->write(2, 'model->addCategory() is called');

		$language_id = $this->request->post['language_id'];

		//пишем категорию
		$this->db->query("INSERT INTO " . DB_PREFIX . "category SET nix_supplier_id = " . (int)$data['nix_supplier_id'] . ", nix_supplier_category_id = " . (int)$data['nix_supplier_category_id'] . ", parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW(), date_added = NOW()");

		$category_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "category SET image = '" . $this->db->escape($data['image']) . "' WHERE category_id = '" . (int)$category_id . "'");
		}
		
		foreach ($data['category_description'] as $language_id => $value) {
			$sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "'";
			
			if (isset($value['name'])) $sql .= ", name = '" . $this->db->escape($value['name']) . "'";			
			if (isset($value['description'])) $sql .= ", description = '" . $this->db->escape($value['description']) . "'";			
			if (isset($value['meta_title'])) $sql .= ", meta_title = '" . $this->db->escape($value['meta_title']) . "'";			
			if (isset($value['meta_h1']) || isset($value['h1'])) {
				if ($this->session->data['nix']['exist_field_meta_h1']) {
					$sql .= ", meta_h1 = '" . $this->db->escape($value['meta_h1']) . "'";
				} elseif($this->session->data['nix']['exist_field_h1']) {
					$sql .= ", h1 = '" . $this->db->escape($value['h1']) . "'";
				}
			}
			if (isset($value['meta_description'])) $sql .= ", meta_description = '" . $this->db->escape($value['meta_description']) . "'";			
			if (isset($value['meta_keyword'])) $sql .= ", meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'";
	
			$this->db->query($sql);
		}
		
		// MySQL Hierarchical Data Closure Table Pattern
		$level = 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

		foreach ($query->rows as $result) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

			$level++;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");

		if (isset($data['category_filter'])) {
			foreach ($data['category_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_filter SET category_id = '" . (int)$category_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		// Set which layout to use with this category
		if (isset($data['category_layout'])) {
			foreach ($data['category_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_layout SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		if (isset($data['category_seo_url'])) {
			foreach ($data['category_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {						
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape(trim($keyword)) . "'");
					}
				}
			}
		}

		if (!empty($data['google_product_category_id'])) {
			$this->setGoogleProductCategoryIdForCategory($category_id, $data['google_product_category_id']);
		}

		$this->cache->delete('category');
		
		if($this->config->get('config_seo_pro')){		
			$this->cache->delete('seopro');
		}

		return $category_id;
	}

	/** Меняем категорию
	 *
	 */
	public function editCategory($category_id, $data) {
		$language_id = $this->request->post['language_id'];

		$this->db->query("UPDATE " . DB_PREFIX . "category SET parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE category_id = '" . (int)$category_id . "'");

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "category SET image = '" . $this->db->escape($data['image']) . "' WHERE category_id = '" . (int)$category_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "category_description WHERE category_id = '" . (int)$category_id . "'");
		
		foreach ($data['category_description'] as $language_id => $value) {
			$sql = "INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "'";
			
			if (isset($value['name'])) $sql .= ", name = '" . $this->db->escape($value['name']) . "'";			
			if (isset($value['description'])) $sql .= ", description = '" . $this->db->escape($value['description']) . "'";			
			if (isset($value['meta_title'])) $sql .= ", meta_title = '" . $this->db->escape($value['meta_title']) . "'";			
			if (isset($value['meta_h1']) || isset($value['h1'])) {
				if ($this->session->data['nix']['exist_field_meta_h1']) {
					$sql .= ", meta_h1 = '" . $this->db->escape($value['meta_h1']) . "'";
				} elseif($this->session->data['nix']['exist_field_h1']) {
					$sql .= ", h1 = '" . $this->db->escape($value['h1']) . "'";
				}
			}	
			if (isset($value['meta_description'])) $sql .= ", meta_description = '" . $this->db->escape($value['meta_description']) . "'";			
			if (isset($value['meta_keyword'])) $sql .= ", meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'";
	
			$this->db->query($sql);
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE path_id = '" . (int)$category_id . "' ORDER BY level ASC");

		if ($query->rows) {
			foreach ($query->rows as $category_path) {
				// Delete the path below the current one
				$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_path['category_id'] . "' AND level < '" . (int)$category_path['level'] . "'");

				$path = array();

				// Get the nodes new parents
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Get whats left of the nodes current path
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_path['category_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Combine the paths with a new level
				$level = 0;

				foreach ($path as $path_id) {
					$this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_path['category_id'] . "', `path_id` = '" . (int)$path_id . "', level = '" . (int)$level . "'");

					$level++;
				}
			}
		} else {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_id . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', level = '" . (int)$level . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "category_filter WHERE category_id = '" . (int)$category_id . "'");

		if (isset($data['category_filter'])) {
			foreach ($data['category_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_filter SET category_id = '" . (int)$category_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "category_to_store WHERE category_id = '" . (int)$category_id . "'");

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "'");

		if (isset($data['category_layout'])) {
			foreach ($data['category_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_layout SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}
		
		if (isset($data['category_seo_url'])) {
			
			$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'category_id=" . (int)$category_id . "'");
			
			foreach ($data['category_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape(trim($keyword)) . "'");
					}
				}
			}
		}

		if (!empty($data['google_product_category_id'])) {
			$this->setGoogleProductCategoryIdForCategory($category_id, $data['google_product_category_id']);
		}

		$this->cache->delete('category');
		
		if($this->config->get('config_seo_pro')){		
			$this->cache->delete('seopro');
		}
	}

	/** Получаем категорию по названию
	 * @param $name
	 * @return mixed
	 */
	public function getCategory($name) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category` `c` "
			. " LEFT JOIN `" . DB_PREFIX . "category_description` `cd` ON (c.category_id = cd.category_id)"
			. " WHERE `cd`.`language_id` = '" . (int)$this->request->post['language_id'] . "' "
			. " AND `c`.`nix_supplier_id` = '" . (int)$this->request->post['supplier_id'] . "'"
			. " AND `cd`.`name` = '" . $this->db->escape($name) . "'");
		
		return $query->row;
	}

	// PRODUCT
	//------------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------------------------------------------------------------------

	/** Получаем товар по названию
	 * @param $filter
	 * @return mixed
	 */
	public function getProduct($filter) {
		$this->stdelog->write(3, 'model::getProduct() is called');
		
//		$query = $this->db->query("SELECT DISTINCT *, (SELECT keyword FROM " . DB_PREFIX . "url_alias WHERE query = 'product_id=" . (int)$product_id . "' LIMIT 1) AS keyword FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		
		$sql = "SELECT DISTINCT * FROM " . DB_PREFIX . "product p"
			. " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)"
			. " WHERE"
//			. " pd.name = '" . $this->db->escape($filter['name']) . "'"
//			. " AND pd.language_id = '" . (int)$this->request->post['language_id'] . "'"
//			. " AND p.model = '" . $this->db->escape($filter['model']) . "'"
//			. " AND p.sku = '" . $this->db->escape($filter['sku']) . "'"
			. " p.nix_supplier_id = '" . (int)$filter['nix_supplier_id'] . "'"
			. " AND p.nix_supplier_product_id = '" . $this->db->escape((string)$filter['nix_supplier_product_id']) . "'";
		
		$this->stdelog->write(4, $sql, 'model::getProduct() :: $sql');
		
		$query = $this->db->query($sql);
		
		return $query->row;
	}

	/** Создаем новый товар
	 * @param $data
	 * @return mixed
	 */
	public function addProduct($data) {
		$this->stdelog->write(3, 'model::addProduct() is called');
		
		$language_id = $this->request->post['language_id'];

		$sql = "INSERT INTO " . DB_PREFIX . "product SET"
			. " nix_supplier_id = '" . (int)$data['nix_supplier_id'] . "',"
			. " nix_supplier_product_id = '" . $this->db->escape((string)$data['nix_supplier_product_id']) . "',"
			. " nix_supplier_price = '" . (float)($data['nix_supplier_price'] ?? 0) . "',"
			. " model = '" . $this->db->escape($data['model']) . "',"
			. " sku = '" . $this->db->escape($data['sku']) . "',"
			. " upc = '" . $this->db->escape($data['upc']) . "',"
			. " ean = '" . $this->db->escape($data['ean']) . "',"
			. " jan = '" . $this->db->escape($data['jan']) . "',"
			. " isbn = '" . $this->db->escape($data['isbn']) . "',"
			. " mpn = '" . $this->db->escape($data['mpn']) . "',"
			. " location = '" . $this->db->escape($data['location']) . "',"
			. " quantity = '" . (int)$data['quantity'] . "',"
			. " minimum = '" . (int)$data['minimum'] . "',"
			. " subtract = '" . (int)$data['subtract'] . "',"
			. " stock_status_id = '" . (int)$data['stock_status_id'] . "',"
			. " date_available = '" . $this->db->escape($data['date_available']) . "',"
			. " manufacturer_id = '" . (int)$data['manufacturer_id'] . "',"
			. " shipping = '" . (int)$data['shipping'] . "',"
			. " price = '" . (float)$data['price'] . "'," 
			. " points = '" . (int)$data['points'] . "',"
			. " weight = '" . (float) $data['weight'] . "',"
			. " weight_class_id = '" . (int)$data['weight_class_id'] . "',"
			. " length = '" . (float) $data['length'] . "',"
			. " width = '" . (float) $data['width'] . "',"
			. " height = '" . (float) $data['height'] . "',"
			. " length_class_id = '" . (int)$data['length_class_id'] . "',"
			. " status = '" . (int)$data['status'] . "',"
			. " tax_class_id = '" . (int)$data['tax_class_id'] . "',"
			. " sort_order = '" . (int)$data['sort_order'] . "',"
			. " date_added = NOW()";
		
		$this->db->query($sql);
		
		$this->stdelog->write(4, $sql, 'model::addProduct() :: $sql');

		$product_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape($data['image']) . "' WHERE product_id = '" . (int)$product_id . "'");
		}


		// $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($data['name']) . "', description = '" . $this->db->escape($data['description']) . "', tag = '" . $this->db->escape($data['tag']) . "', meta_title = '" . $this->db->escape($data['meta_title']) . "', meta_h1 = '" . $this->db->escape($data['meta_h1']) . "', meta_description = '" . $this->db->escape($data['meta_description']) . "', meta_keyword = '" . $this->db->escape($data['meta_keyword']) . "'");
		
		foreach ($data['product_description'] as $language_id => $value) {
			$sql = "INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "'";
			
			if (isset($value['name'])) $sql .= ", name = '" . $this->db->escape($value['name']) . "'";			
			if (isset($value['description'])) $sql .= ", description = '" . $this->db->escape($value['description']) . "'";			
			if (isset($value['tag'])) $sql .= ", tag = '" . $this->db->escape($value['tag']) . "'";			
			if (isset($value['meta_title'])) $sql .= ", meta_title = '" . $this->db->escape($value['meta_title']) . "'";	
			if (isset($value['meta_h1']) || isset($value['h1'])) {
				if ($this->session->data['nix']['exist_field_meta_h1']) {
					$sql .= ", meta_h1 = '" . $this->db->escape($value['meta_h1']) . "'";
				} elseif($this->session->data['nix']['exist_field_h1']) {
					$sql .= ", h1 = '" . $this->db->escape($value['h1']) . "'";
				}
			}
			if (isset($value['meta_description'])) $sql .= ", meta_description = '" . $this->db->escape($value['meta_description']) . "'";			
			if (isset($value['meta_keyword'])) $sql .= ", meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'";
	
			$this->db->query($sql);
		}

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '0'");

		if (isset($data['product_attribute'])) {
			foreach ($data['product_attribute'] as $product_attribute) {
				if ($product_attribute['attribute_id']) {
					foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
						$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND language_id = '" . (int)$language_id . "'");

						$this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $this->db->escape($product_attribute_description['text']) . "'");
					}
				}
			}
		}

		if (isset($data['product_option'])) {
			foreach ($data['product_option'] as $product_option) {
				if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
					if (isset($product_option['product_option_value'])) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");

						$product_option_id = $this->db->getLastId();

						foreach ($product_option['product_option_value'] as $product_option_value) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float) $product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float) $product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");
						}
					}
				} else {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', value = '" . $this->db->escape($product_option['value']) . "', required = '" . (int)$product_option['required'] . "'");
				}
			}
		}

		if (isset($data['product_discount'])) {
			foreach ($data['product_discount'] as $product_discount) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float) $product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
			}
		}

		if (isset($data['product_special'])) {
			foreach ($data['product_special'] as $product_special) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float) $product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
			}
		}

		if (isset($data['product_image'])) {
			foreach ($data['product_image'] as $product_image) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($product_image['image']) . "', sort_order = '" . (int)$product_image['sort_order'] . "'");
			}
		}

		if (isset($data['product_download'])) {
			foreach ($data['product_download'] as $download_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
			}
		}

		$this->syncProductCategories(
			$product_id,
			isset($data['product_category']) && is_array($data['product_category']) ? $data['product_category'] : [],
			isset($data['main_category_id']) ? (int)$data['main_category_id'] : 0
		);

		if (!empty($data['google_product_category_id'])) {
			$main_category_id = $this->resolveMainCategoryId($product_id, isset($data['product_category']) && is_array($data['product_category']) ? $data['product_category'] : [], isset($data['main_category_id']) ? (int)$data['main_category_id'] : 0);
			$this->setGoogleProductCategoryIdForCategory($main_category_id, $data['google_product_category_id']);
		}

		if (isset($data['product_filter'])) {
			foreach ($data['product_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		if (isset($data['product_related'])) {
			foreach ($data['product_related'] as $related_id) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
			}
		}

		if (isset($data['product_reward'])) {
			foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
				if ((int)$product_reward['points'] > 0) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$product_reward['points'] . "'");
				}
			}
		}

		if (isset($data['product_layout'])) {
			foreach ($data['product_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}
		
		if (isset($data['product_seo_url'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "'");
			
			foreach ($data['product_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$keyword = $product_id . '-' . $keyword;
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape(trim($keyword)) . "'");
					}
				}
			}
		}

		if (isset($data['product_recurring'])) {
			foreach ($data['product_recurring'] as $recurring) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_recurring` SET `product_id` = " . (int)$product_id . ", customer_group_id = " . (int)$recurring['customer_group_id'] . ", `recurring_id` = " . (int)$recurring['recurring_id']);
			}
		}

		$this->cache->delete('product');

		if ($this->config->get('config_seo_pro')) {
			$this->cache->delete('seopro');
		}

		return $product_id;
	}

	/** Обновляем товар
	 * @param $product_id
	 * @param $data
	 * @return string
	 */
	public function editProduct($product_id, $data) {
		$this->stdelog->write(3, 'model::editProduct() is called');
		
		$language_id = $this->request->post['language_id'];

		$sql = "UPDATE " . DB_PREFIX . "product SET"
			. " nix_supplier_price = '" . (float)($data['nix_supplier_price'] ?? 0) . "',"
			. " model = '" . $this->db->escape($data['model']) . "',"
			. " sku = '" . $this->db->escape($data['sku']) . "',"
			. " upc = '" . $this->db->escape($data['upc']) . "',"
			. " ean = '" . $this->db->escape($data['ean']) . "',"
			. " jan = '" . $this->db->escape($data['jan']) . "',"
			. " isbn = '" . $this->db->escape($data['isbn']) . "',"
			. " mpn = '" . $this->db->escape($data['mpn']) . "',"
			. " location = '" . $this->db->escape($data['location']) . "',"
			. " quantity = '" . (int)$data['quantity'] . "',"
			. " minimum = '" . (int)$data['minimum'] . "',"
			. " subtract = '" . (int)$data['subtract'] . "',"
			. " stock_status_id = '" . (int)$data['stock_status_id'] . "',"
			. " date_available = '" . $this->db->escape($data['date_available']) . "',"
			. " manufacturer_id = '" . (int)$data['manufacturer_id'] . "',"
			. " shipping = '" . (int)$data['shipping'] . "',"
			. " price = '" . (float)$data['price'] . "'," 
			. " points = '" . (int)$data['points'] . "',"
			. " weight = '" . (float) $data['weight'] . "',"
			. " weight_class_id = '" . (int)$data['weight_class_id'] . "',"
			. " length = '" . (float) $data['length'] . "',"
			. " width = '" . (float) $data['width'] . "',"
			. " height = '" . (float) $data['height'] . "',"
			. " length_class_id = '" . (int)$data['length_class_id'] . "',"
			. " status = '" . (int)$data['status'] . "',"
			. " tax_class_id = '" . (int)$data['tax_class_id'] . "',"
			. " sort_order = '" . (int)$data['sort_order'] . "',"
			. " date_modified = NOW()"
			. " WHERE product_id = '" . (int)$product_id . "'";
		
		$this->stdelog->write(4, $sql, 'model::editProduct() :: $sql');
		
		$this->db->query($sql);		

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape($data['image']) . "' WHERE product_id = '" . (int)$product_id . "'");
		}


		$this->db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");
		

		//$this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($data['name']) . "', description = '" . $this->db->escape($data['description']) . "', tag = '" . $this->db->escape($data['tag']) . "', meta_title = '" . $this->db->escape($data['meta_title']) . "', meta_h1 = '" . $this->db->escape($data['meta_h1']) . "', meta_description = '" . $this->db->escape($data['meta_description']) . "', meta_keyword = '" . $this->db->escape($data['meta_keyword']) . "'");
		
		foreach ($data['product_description'] as $language_id => $value) {	
			$sql = "INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "'";
			
			if (isset($value['name'])) $sql .= ", name = '" . $this->db->escape($value['name']) . "'";			
			if (isset($value['description'])) $sql .= ", description = '" . $this->db->escape($value['description']) . "'";			
			if (isset($value['tag'])) $sql .= ", tag = '" . $this->db->escape($value['tag']) . "'";			
			if (isset($value['meta_title'])) $sql .= ", meta_title = '" . $this->db->escape($value['meta_title']) . "'";
			if (isset($value['meta_h1']) || isset($value['h1'])) {
				if ($this->session->data['nix']['exist_field_meta_h1']) {
					$sql .= ", meta_h1 = '" . $this->db->escape($value['meta_h1']) . "'";
				} elseif($this->session->data['nix']['exist_field_h1']) {
					$sql .= ", h1 = '" . $this->db->escape($value['h1']) . "'";
				}
			}
			if (isset($value['meta_description'])) $sql .= ", meta_description = '" . $this->db->escape($value['meta_description']) . "'";			
			if (isset($value['meta_keyword'])) $sql .= ", meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'";
	
			$this->db->query($sql);
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '0'");

		if (isset($data['product_attribute'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_attribute'] as $product_attribute) {
				if ($product_attribute['attribute_id']) {
					foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
						$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND language_id = '" . (int)$language_id . "'");

						$this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $this->db->escape($product_attribute_description['text']) . "'");
					}
				}
			}
		}

		if (isset($data['product_option'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_option'] as $product_option) {
				if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
					if (isset($product_option['product_option_value'])) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_option_id = '" . (int)$product_option['product_option_id'] . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");

						$product_option_id = $this->db->getLastId();

						foreach ($product_option['product_option_value'] as $product_option_value) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_value_id = '" . (int)$product_option_value['product_option_value_id'] . "', product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float) $product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float) $product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");
						}
					}
				} else {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_option_id = '" . (int)$product_option['product_option_id'] . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', value = '" . $this->db->escape($product_option['value']) . "', required = '" . (int)$product_option['required'] . "'");
				}
			}
		}

		if (isset($data['product_discount'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_discount'] as $product_discount) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float) $product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
			}
		}

		if (array_key_exists('product_special', $data)) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");

			if (isset($data['product_special']) && is_array($data['product_special'])) {
				foreach ($data['product_special'] as $product_special) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float) $product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
				}
			}
		}

		if (isset($data['product_image'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_image'] as $product_image) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($product_image['image']) . "', sort_order = '" . (int)$product_image['sort_order'] . "'");
			}
		}


		if (isset($data['product_download'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_download'] as $download_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
			}
		}

		if (isset($data['product_category'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

			$this->syncProductCategories(
				$product_id,
				is_array($data['product_category']) ? $data['product_category'] : [],
				isset($data['main_category_id']) ? (int)$data['main_category_id'] : 0
			);
		}

		if (!empty($data['google_product_category_id'])) {
			$main_category_id = $this->resolveMainCategoryId($product_id, isset($data['product_category']) && is_array($data['product_category']) ? $data['product_category'] : [], isset($data['main_category_id']) ? (int)$data['main_category_id'] : 0);
			$this->setGoogleProductCategoryIdForCategory($main_category_id, $data['google_product_category_id']);
		}

		if (isset($data['product_filter'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		if (isset($data['product_related'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int)$product_id . "'");

			foreach ($data['product_related'] as $related_id) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
			}
		}

		if (isset($data['product_reward'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_reward'] as $customer_group_id => $value) {
				if ((int)$value['points'] > 0) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$value['points'] . "'");
				}
			}
		}

		if (isset($data['product_layout'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		if (isset($data['product_seo_url'])) {
			
			$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "'");
			
			foreach ($data['product_seo_url']as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$keyword = $product_id . '-' . $keyword;
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape(trim($keyword)) . "'");
					}
				}
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_recurring` WHERE product_id = " . (int)$product_id);

		if (isset($data['product_recurring'])) {
			foreach ($data['product_recurring'] as $product_recurring) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_recurring` SET `product_id` = " . (int)$product_id . ", customer_group_id = " . (int)$product_recurring['customer_group_id'] . ", `recurring_id` = " . (int)$product_recurring['recurring_id']);
			}
		}

		$this->cache->delete('product');

		if ($this->config->get('config_seo_pro')) {
			$this->cache->delete('seopro');
		}
	}


	private function normalizeCategoryIds(array $category_ids) {
		$result = [];

		foreach ($category_ids as $category_id) {
			$category_id = (int)$category_id;

			if ($category_id > 0) {
				$result[$category_id] = $category_id;
			}
		}

		return array_values($result);
	}

	public function resolveMainCategoryId($product_id = 0, array $category_ids = [], $preferred_main_category_id = 0) {
		$product_id = (int)$product_id;
		$category_ids = $this->normalizeCategoryIds($category_ids);
		$preferred_main_category_id = (int)$preferred_main_category_id;

		if ($product_id > 0 && $this->helperExistFieldMainCategory()) {
			$query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product_id . "' AND `main_category` = '1' LIMIT 1");

			if ($query->num_rows && (int)$query->row['category_id'] > 0) {
				return (int)$query->row['category_id'];
			}
		}

		if ($product_id > 0 && $this->helperExistFieldProductMainCategoryId()) {
			$query = $this->db->query("SELECT `main_category_id` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . (int)$product_id . "' LIMIT 1");

			if ($query->num_rows && (int)$query->row['main_category_id'] > 0) {
				return (int)$query->row['main_category_id'];
			}
		}

		if ($product_id > 0) {
			$query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product_id . "' ORDER BY `category_id` ASC LIMIT 1");

			if ($query->num_rows && (int)$query->row['category_id'] > 0) {
				return (int)$query->row['category_id'];
			}
		}

		if ($preferred_main_category_id > 0) {
			return $preferred_main_category_id;
		}

		if (!empty($category_ids[0])) {
			return (int)$category_ids[0];
		}

		return 0;
	}

	private function syncProductCategories($product_id, array $category_ids, $preferred_main_category_id = 0) {
		$product_id = (int)$product_id;
		$category_ids = $this->normalizeCategoryIds($category_ids);
		$main_category_id = $this->resolveMainCategoryId($product_id, $category_ids, $preferred_main_category_id);

		if ($main_category_id > 0 && !in_array($main_category_id, $category_ids, true)) {
			array_unshift($category_ids, $main_category_id);
			$category_ids = $this->normalizeCategoryIds($category_ids);
		}

		$has_product_to_category_main = $this->helperExistFieldMainCategory();

		foreach ($category_ids as $category_id) {
			if ($has_product_to_category_main) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "', main_category = '" . ((int)$category_id === (int)$main_category_id ? 1 : 0) . "'");
			} else {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
			}
		}

		if ($main_category_id > 0 && $this->helperExistFieldProductMainCategoryId()) {
			$this->db->query("UPDATE " . DB_PREFIX . "product SET main_category_id = '" . (int)$main_category_id . "' WHERE product_id = '" . (int)$product_id . "'");
		}

		return $main_category_id;
	}

	public function setGoogleProductCategoryIdForCategory($category_id, $google_product_category_id) {
		$category_id = (int)$category_id;
		$google_product_category_id = trim((string)$google_product_category_id);

		if ($category_id < 1 || $google_product_category_id === '') {
			return false;
		}

		$updated = false;

		if ($this->columnExists('category', 'google_product_category_id')) {
			$this->db->query("UPDATE `" . DB_PREFIX . "category` SET `google_product_category_id` = '" . $this->db->escape($google_product_category_id) . "' WHERE `category_id` = '" . (int)$category_id . "'");
			$updated = true;
		}

		if ($this->tableExists('googleshopping_category')) {
			$columns = $this->getTableColumns('googleshopping_category');

			if (isset($columns['category_id'])) {
				$google_column = '';

				foreach (['google_product_category_id', 'google_category_id', 'google_product_category', 'google_category', 'google_taxonomy_id'] as $candidate) {
					if (isset($columns[$candidate])) {
						$google_column = $candidate;
						break;
					}
				}

				if ($google_column !== '') {
					$query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "googleshopping_category` WHERE `category_id` = '" . (int)$category_id . "' LIMIT 1");

					if ($query->num_rows) {
						$this->db->query("UPDATE `" . DB_PREFIX . "googleshopping_category` SET `" . $this->db->escape($google_column) . "` = '" . $this->db->escape($google_product_category_id) . "' WHERE `category_id` = '" . (int)$category_id . "'");
					} else {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "googleshopping_category` SET `category_id` = '" . (int)$category_id . "', `" . $this->db->escape($google_column) . "` = '" . $this->db->escape($google_product_category_id) . "'");
					}

					$updated = true;
				}
			}
		}

		return $updated;
	}

	public function getGoogleProductCategoryIdForCategory($category_id) {
		$category_id = (int)$category_id;

		if ($category_id < 1) {
			return '';
		}

		if ($this->columnExists('category', 'google_product_category_id')) {
			$query = $this->db->query("SELECT `google_product_category_id` FROM `" . DB_PREFIX . "category` WHERE `category_id` = '" . (int)$category_id . "' LIMIT 1");

			if ($query->num_rows && trim((string)$query->row['google_product_category_id']) !== '') {
				return trim((string)$query->row['google_product_category_id']);
			}
		}

		if ($this->tableExists('googleshopping_category')) {
			$columns = $this->getTableColumns('googleshopping_category');

			if (isset($columns['category_id'])) {
				foreach (['google_product_category_id', 'google_category_id', 'google_product_category', 'google_category', 'google_taxonomy_id'] as $candidate) {
					if (isset($columns[$candidate])) {
						$query = $this->db->query("SELECT `" . $this->db->escape($candidate) . "` FROM `" . DB_PREFIX . "googleshopping_category` WHERE `category_id` = '" . (int)$category_id . "' LIMIT 1");

						if ($query->num_rows && trim((string)$query->row[$candidate]) !== '') {
							return trim((string)$query->row[$candidate]);
						}

						break;
					}
				}
			}
		}

		return '';
	}

	private function getTableColumns($table) {
		$table = (string)$table;
		if (!$this->isAllowedTable($table)) {
			return [];
		}

		if (isset($this->nix_column_cache[$table]['*'])) {
			return $this->nix_column_cache[$table]['*'];
		}

		$columns = [];

		if ($table !== '' && $this->tableExists($table)) {
			$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "`");

			foreach ($query->rows as $row) {
				$columns[$row['Field']] = true;
			}
		}

		$this->nix_column_cache[$table]['*'] = $columns;

		return $columns;
	}

	public function helperExistGoogleShoppingCategoryMapping() {
		if (!$this->tableExists('googleshopping_category')) {
			return false;
		}

		$columns = $this->getTableColumns('googleshopping_category');

		if (!isset($columns['category_id'])) {
			return false;
		}

		foreach (['google_product_category_id', 'google_category_id', 'google_product_category', 'google_category', 'google_taxonomy_id'] as $candidate) {
			if (isset($columns[$candidate])) {
				return true;
			}
		}

		return false;
	}

	public function updateProductStock($data) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET"
			. " quantity = '" . (int)$data['remains'] . "',"
			. " price = '" . (float)$data['price'] . "',"
			. " nix_supplier_price = '" . (float)($data['nix_supplier_price'] ?? 0) . "',"
			. " stock_status_id = '" . (int)$data['stock_status_id'] . "',"
			. " status = '" . (int)$data['status'] . "',"
			. " date_modified = NOW()"
			. " WHERE nix_supplier_id = '" . (int)$data['nix_supplier_id'] . "'"
			. " AND nix_supplier_product_id = '" . $this->db->escape((string)$data['nix_supplier_product_id']) . "'");

		$this->cache->delete('product');
	}


	public function getProductSpecialPrice($product_id) {
		$query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "' ORDER BY priority ASC, price ASC LIMIT 1");
		return $query->num_rows ? (float)$query->row['price'] : 0.0;
	}

	public function getSupplierProducts($supplier_id) {
		$language_id = (int)$this->config->get('config_language_id');
		$query = $this->db->query("SELECT p.product_id, p.nix_supplier_product_id, p.model, p.sku, p.price, p.quantity, p.status, p.stock_status_id, p.nix_supplier_price, pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "') WHERE p.nix_supplier_id = '" . (int)$supplier_id . "' AND p.nix_supplier_product_id <> '' ORDER BY p.product_id ASC");
		return $query->rows;
	}


	/**
	 * Удаляем товар
	 * @param $product_id
	 */
	public function clearAll() {
		$supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
		if ($supplier_id < 1) {
			return false;
		}

		$product_ids = $this->getIds("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE nix_supplier_id = '" . (int)$supplier_id . "'", 'product_id');
		$category_ids = $this->getIds("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE nix_supplier_id = '" . (int)$supplier_id . "'", 'category_id');

		if ($product_ids) {
			foreach (array_chunk($product_ids, 500) as $chunk) {
				$in = implode(',', array_map('intval', $chunk));
				foreach (['product_attribute','product_description','product_discount','product_filter','product_image','product_option','product_option_value','product_reward','product_special','product_to_category','product_to_download','product_to_layout','product_to_store','product_recurring','coupon_product'] as $table) {
					$this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE product_id IN (" . $in . ")");
				}
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_related` WHERE product_id IN (" . $in . ") OR related_id IN (" . $in . ")");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query IN (" . $this->quoteQueries('product_id=', $chunk) . ")");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product` WHERE product_id IN (" . $in . ")");
			}
		}

		if ($category_ids) {
			foreach (array_chunk($category_ids, 500) as $chunk) {
				$in = implode(',', array_map('intval', $chunk));
				foreach (['category_description','category_filter','category_to_layout','category_to_store'] as $table) {
					if ($table !== 'category_path') {
						$this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE category_id IN (" . $in . ")");
					}
				}
				$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id IN (" . $in . ") OR path_id IN (" . $in . ")");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query IN (" . $this->quoteQueries('category_id=', $chunk) . ")");
				$this->db->query("DELETE FROM `" . DB_PREFIX . "category` WHERE category_id IN (" . $in . ")");
			}
		}
		
		$this->cache->delete('product');
		$this->cache->delete('category');
		return true;
	}

	private function getIds($sql, $field) {
		$query = $this->db->query($sql);
		$ids = [];
		foreach ($query->rows as $row) {
			$ids[] = (int)$row[$field];
		}
		return $ids;
	}

	private function quoteQueries($prefix, array $ids) {
		$items = [];
		foreach ($ids as $id) {
			$items[] = "'" . $this->db->escape($prefix . (int)$id) . "'";
		}
		return implode(',', $items);
	}


	// Производитель
	//------------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------------------------------------------------------------------

	public function getManufacturer($name) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($name) . "'");
		return $query->row;
	}

	public function addManufacturer($data) {
		$language_id = (int)$this->request->post['language_id'];

		$this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer SET name = '" . $this->db->escape($data['name']) . "', sort_order = '" . (int)$data['sort_order'] . "'");

		$manufacturer_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET image = '" . $this->db->escape($data['image']) . "' WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");
		}

		if (!empty($this->session->data['nix']['exist_table_manufacturer_description'])) {
			$sql = "INSERT INTO " . DB_PREFIX . "manufacturer_description SET manufacturer_id = '" . (int)$manufacturer_id . "', language_id = '" . $language_id . "'";

			if ($this->columnExists('manufacturer_description', 'name')) {
				$sql .= ", name = '" . $this->db->escape($data['name']) . "'";
			}

			if ($this->columnExists('manufacturer_description', 'description')) {
				$sql .= ", description = '" . $this->db->escape($data['description']) . "'";
			}

			if ($this->columnExists('manufacturer_description', 'meta_title')) {
				$sql .= ", meta_title = '" . $this->db->escape($data['name']) . "'";
			}

			if ($this->columnExists('manufacturer_description', 'meta_h1')) {
				$sql .= ", meta_h1 = '" . $this->db->escape($data['name']) . "'";
			} elseif ($this->columnExists('manufacturer_description', 'h1')) {
				$sql .= ", h1 = '" . $this->db->escape($data['name']) . "'";
			}

			if ($this->columnExists('manufacturer_description', 'meta_description')) {
				$sql .= ", meta_description = '" . $this->db->escape($data['description']) . "'";
			}

			if ($this->columnExists('manufacturer_description', 'meta_keyword')) {
				$sql .= ", meta_keyword = '" . $this->db->escape($data['meta_keyword']) . "'";
			}

			$this->db->query($sql);
		}

		if (isset($data['manufacturer_store'])) {
			foreach ($data['manufacturer_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer_to_store SET manufacturer_id = '" . (int)$manufacturer_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		if (isset($data['manufacturer_seo_url'])) {
			foreach ($data['manufacturer_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'manufacturer_id=" . (int)$manufacturer_id . "', keyword = '" . $this->db->escape(trim($keyword)) . "'");
					}
				}
			}
		}

		$this->cache->delete('manufacturer');

		return $manufacturer_id;
	}

	// Атрибуты
	//------------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------------------------------------------------------------------

	public function getAttributeGroup($name) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_group_description WHERE name = '" . $this->db->escape($name) . "' AND language_id = '" . (int)$this->request->post['language_id']. "'");

		return $query->row;
	}

	public function getAttribute($name) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE name = '" . $this->db->escape($name) . "' AND language_id = '" . (int)$this->request->post['language_id']. "'");

		return $query->row;
	}

	public function addAttributeGroup($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group SET sort_order = 0");

		$attribute_group_id = $this->db->getLastId();

		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_group_description WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
		
		foreach ($data['attribute_group_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int)$attribute_group_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}
		
		return $attribute_group_id;
	}

	public function addAttribute($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$data['attribute_group_id'] . "'");

		$attribute_id = $this->db->getLastId();

		foreach ($data['attribute_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		return $attribute_id;
	}

	public function helperExistFieldMetaH1() {
		return $this->columnExists('product_description', 'meta_h1');
	}
	
	public function helperExistFieldH1() {
		return $this->columnExists('product_description', 'h1');
	}
	
	public function helperExistFieldMainCategory() {
		return $this->columnExists('product_to_category', 'main_category');
	}
	
	public function helperExistFieldProductMainCategoryId() {
		return $this->columnExists('product', 'main_category_id');
	}

	public function helperExistTableManufacturerDescription() {
		return $this->tableExists('manufacturer_description');
	}
	
	public function exportSettings() {
		$settings = [];
		$query = $this->db->query("SELECT `key`, `value`, `serialized` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = 'feed_nix' ORDER BY `key` ASC");

		foreach ($query->rows as $row) {
			$settings[$row['key']] = !empty($row['serialized']) ? json_decode($row['value'], true) : $row['value'];
		}

		return [
			'module' => 'ImportXML Clean PRO v1.4.2',
			'author' => 'CodeCart PRO',
			'site' => 'https://codecartpro.com',
			'exported_at' => date('c'),
			'settings' => $settings,
			'suppliers' => array_values($this->supplierList())
		];
	}

	public function importSettings(array $data) {
		$allowed_settings = [
			'feed_nix_status',
			'feed_nix_delete_data_on_uninstall',
			'feed_nix_cron_status',
			'feed_nix_cron_token',
			'feed_nix_cron_supplier_id',
			'feed_nix_cron_language_id',
			'feed_nix_cron_mode',
			'feed_nix_cron_update_if_exist',
			'feed_nix_cron_copy_description',
			'feed_nix_cron_copy_attributes',
			'feed_nix_cron_delete_all'
		];

		if (!isset($data['settings']) || !is_array($data['settings']) || !isset($data['suppliers']) || !is_array($data['suppliers'])) {
			return ['success' => false, 'error' => '', 'suppliers' => 0];
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = 'feed_nix'");

		foreach ($allowed_settings as $key) {
			if (!array_key_exists($key, $data['settings'])) {
				continue;
			}

			$value = $data['settings'][$key];
			if (is_array($value)) {
				$value = json_encode($value, JSON_UNESCAPED_UNICODE);
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = 'feed_nix', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape((string)$value) . "', `serialized` = '0'");
		}

		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "nix_suppliers`");
		$count = 0;

		foreach ($data['suppliers'] as $supplier) {
			if (!is_array($supplier)) {
				continue;
			}

			$name = trim((string)($supplier['name'] ?? ''));
			if ($name === '') {
				continue;
			}

			$tags = isset($supplier['tags']) && is_array($supplier['tags']) ? $supplier['tags'] : [];
			$attributes = isset($supplier['attributes']) && is_array($supplier['attributes']) ? $supplier['attributes'] : [];

			$this->supplierAdd([
				'name' => $name,
				'markup' => (float)($supplier['markup'] ?? 0),
				'link_price' => trim((string)($supplier['link_price'] ?? '')),
				'tags' => $tags,
				'attributes' => $attributes
			]);
			$count++;
		}

		return ['success' => true, 'error' => '', 'suppliers' => $count];
	}

	public function resetDefaults($cron_token) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = 'feed_nix'");
		$defaults = [
			'feed_nix_status' => 0,
			'feed_nix_delete_data_on_uninstall' => 0,
			'feed_nix_cron_status' => 0,
			'feed_nix_cron_token' => (string)$cron_token,
			'feed_nix_cron_supplier_id' => 0,
			'feed_nix_cron_language_id' => 0,
			'feed_nix_cron_mode' => 'price_stock',
			'feed_nix_cron_update_if_exist' => 1,
			'feed_nix_cron_copy_description' => 1,
			'feed_nix_cron_copy_attributes' => 1,
			'feed_nix_cron_delete_all' => 0
		];

		foreach ($defaults as $key => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = 'feed_nix', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape((string)$value) . "', `serialized` = '0'");
		}
	}

	public function diagnostics($enabled = false, array $texts = []) {
		$text = function($key, $default) use ($texts) {
			return isset($texts[$key]) && $texts[$key] !== '' ? $texts[$key] : $default;
		};

		$items = [];
		$items[] = ['status' => $enabled ? 'success' : 'warning', 'text' => $enabled ? $text('module_enabled', 'Module enabled') : $text('module_disabled', 'Module disabled: import is blocked')];
		$items[] = ['status' => $this->tableExists('nix_suppliers') ? 'success' : 'danger', 'text' => $text('table_suppliers', 'Supplier profiles table')];
		$items[] = ['status' => $this->columnExists('product', 'nix_supplier_id') ? 'success' : 'danger', 'text' => $text('field_product_supplier_id', 'Field product.nix_supplier_id')];
		$items[] = ['status' => $this->columnExists('product', 'nix_supplier_product_id') ? 'success' : 'danger', 'text' => $text('field_product_supplier_product_id', 'Field product.nix_supplier_product_id')];
		$items[] = ['status' => $this->columnExists('product', 'nix_supplier_price') ? 'success' : 'warning', 'text' => $text('field_product_supplier_price', 'Field product.nix_supplier_price')];
		$items[] = ['status' => function_exists('simplexml_load_file') ? 'success' : 'danger', 'text' => $text('simplexml', 'PHP SimpleXML extension')];
		$items[] = ['status' => is_writable(DIR_CACHE) ? 'success' : 'danger', 'text' => $text('cache_writable', 'Cache directory is writable for temporary XML files')];
		$items[] = ['status' => is_writable(DIR_LOGS) ? 'success' : 'warning', 'text' => $text('logs_writable', 'Logs directory is writable')];
		$items[] = ['status' => $this->helperExistFieldMainCategory() ? 'success' : 'warning', 'text' => $text('main_category_ptc', 'Main category via product_to_category.main_category')];
		$items[] = ['status' => $this->helperExistFieldProductMainCategoryId() ? 'success' : 'warning', 'text' => $text('main_category_product', 'Main category via product.main_category_id')];
		$items[] = ['status' => $this->helperExistGoogleShoppingCategoryMapping() || $this->columnExists('category', 'google_product_category_id') ? 'success' : 'warning', 'text' => $text('google_category', 'Google Product Category ID: category.google_product_category_id or googleshopping_category')];
		$php_supported = version_compare(PHP_VERSION, '7.4.0', '>=') && version_compare(PHP_VERSION, '8.6.0', '<');
		$items[] = ['status' => $php_supported ? 'success' : 'danger', 'text' => sprintf($text('php_version', 'PHP %s; supported range: 7.4–8.5'), PHP_VERSION)];
		return $items;
	}

	public function getLogFiles() {
		$files = [];
		foreach (glob(DIR_LOGS . 'nix_*.log') ?: [] as $file) {
			$files[] = ['name' => basename($file), 'size' => filesize($file), 'date' => date('Y-m-d H:i:s', filemtime($file))];
		}
		return $files;
	}

	public function clearLogs() {
		$deleted = 0;
		foreach (glob(DIR_LOGS . 'nix_*.log') ?: [] as $file) {
			if (is_file($file) && @unlink($file)) {
				$deleted++;
			}
		}
		foreach (glob(DIR_CACHE . 'nix-import-*.xml') ?: [] as $file) {
			if (is_file($file) && @unlink($file)) {
				$deleted++;
			}
		}
		return $deleted;
	}

	private function isAllowedTable($table) {
		return in_array((string)$table, $this->nix_allowed_tables, true);
	}

	private function isAllowedColumn($table, $column) {
		$table = (string)$table;
		$column = (string)$column;
		return isset($this->nix_allowed_columns[$table]) && in_array($column, $this->nix_allowed_columns[$table], true);
	}

	private function tableExists($table) {
		$table = (string)$table;
		if (!$this->isAllowedTable($table)) {
			return false;
		}

		if (!array_key_exists($table, $this->nix_table_cache)) {
			$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . $this->db->escape($table) . "'");
			$this->nix_table_cache[$table] = (bool)$query->num_rows;
		}

		return $this->nix_table_cache[$table];
	}

	private function columnExists($table, $column) {
		$table = (string)$table;
		$column = (string)$column;
		if (!$this->isAllowedColumn($table, $column)) {
			return false;
		}

		if (!isset($this->nix_column_cache[$table][$column])) {
			$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` WHERE `Field` = '" . $this->db->escape($column) . "'");
			$this->nix_column_cache[$table][$column] = (bool)$query->num_rows;
		}

		return $this->nix_column_cache[$table][$column];
	}

}
