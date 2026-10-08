<?php
class ModelExtensionModuleImportProQueue extends Model {
    const VERSION = '3.6.9';
    const BATCH_TABLE = 'import_pro_batch';
    const QUEUE_TABLE = 'import_pro_queue';
    const LOG_TABLE = 'import_pro_queue_log';
    const SUPPLIER_TABLE = 'import_pro_supplier_registry';
    const STORAGE_DIR = 'import_pro_queue';

    private $columnCache = array();
    private $tableCache = array();
    private $languageCache = null;
    private $storeCache = null;
    private $runtimeChecked = false;
    private $currentEntityIsNew = null;

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::BATCH_TABLE . "` (
            `batch_id` varchar(40) NOT NULL,
            `entity_type` varchar(32) NOT NULL,
            `mode` varchar(32) NOT NULL DEFAULT 'upsert',
            `file_name` varchar(255) NOT NULL DEFAULT '',
            `source_path` varchar(500) NOT NULL DEFAULT '',
            `options_json` mediumtext NOT NULL,
            `headers_json` mediumtext NOT NULL,
            `delimiter` varchar(8) NOT NULL DEFAULT ';',
            `encoding` varchar(32) NOT NULL DEFAULT 'UTF-8',
            `has_header` tinyint(1) NOT NULL DEFAULT '1',
            `file_offset` bigint(20) unsigned NOT NULL DEFAULT '0',
            `csv_row_number` int(11) unsigned NOT NULL DEFAULT '0',
            `prepare_status` varchar(32) NOT NULL DEFAULT 'new',
            `status` varchar(32) NOT NULL DEFAULT 'new',
            `total_rows` int(11) unsigned NOT NULL DEFAULT '0',
            `queued_rows` int(11) unsigned NOT NULL DEFAULT '0',
            `skipped_rows` int(11) unsigned NOT NULL DEFAULT '0',
            `error_rows` int(11) unsigned NOT NULL DEFAULT '0',
            `message` text NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`batch_id`),
            KEY `entity_status` (`entity_type`, `status`),
            KEY `prepare_status` (`prepare_status`),
            KEY `date_added` (`date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::QUEUE_TABLE . "` (
            `queue_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `batch_id` varchar(40) NOT NULL,
            `entity_type` varchar(32) NOT NULL,
            `csv_row_number` int(11) unsigned NOT NULL DEFAULT '0',
            `key_field` varchar(64) NOT NULL DEFAULT '',
            `key_value` varchar(255) NOT NULL DEFAULT '',
            `entity_id` int(11) unsigned NOT NULL DEFAULT '0',
            `payload_json` mediumtext NOT NULL,
            `status` varchar(32) NOT NULL DEFAULT 'pending',
            `action` varchar(32) NOT NULL DEFAULT '',
            `message` text NOT NULL,
            `warnings` text NOT NULL,
            `worker_token` varchar(64) NOT NULL DEFAULT '',
            `attempts` smallint(5) unsigned NOT NULL DEFAULT '0',
            `selected` tinyint(1) NOT NULL DEFAULT '1',
            `lease_expires` datetime DEFAULT NULL,
            `heartbeat_at` datetime DEFAULT NULL,
            `date_added` datetime NOT NULL,
            `date_started` datetime DEFAULT NULL,
            `date_finished` datetime DEFAULT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`queue_id`),
            UNIQUE KEY `batch_row` (`batch_id`, `csv_row_number`),
            KEY `batch_status` (`batch_id`, `status`, `queue_id`),
            KEY `worker_token` (`worker_token`),
            KEY `entity_type` (`entity_type`),
            KEY `entity_id` (`entity_id`),
            KEY `key_value` (`key_value`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::LOG_TABLE . "` (
            `log_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `batch_id` varchar(40) NOT NULL,
            `queue_id` int(11) unsigned NOT NULL DEFAULT '0',
            `entity_type` varchar(32) NOT NULL,
            `entity_id` int(11) unsigned NOT NULL DEFAULT '0',
            `status` varchar(32) NOT NULL DEFAULT '',
            `action` varchar(32) NOT NULL DEFAULT '',
            `message` text NOT NULL,
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`log_id`),
            KEY `batch_id` (`batch_id`),
            KEY `queue_id` (`queue_id`),
            KEY `entity_id` (`entity_id`),
            KEY `date_added` (`date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::SUPPLIER_TABLE . "` (
            `supplier_product_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `supplier_code` varchar(64) NOT NULL,
            `key_field` varchar(64) NOT NULL,
            `external_key` varchar(255) NOT NULL,
            `external_key_hash` char(40) NOT NULL,
            `product_id` int(11) unsigned NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT '1',
            `last_seen_batch_id` varchar(40) NOT NULL DEFAULT '',
            `last_seen_at` datetime DEFAULT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`supplier_product_id`),
            UNIQUE KEY `supplier_key` (`supplier_code`, `key_field`, `external_key_hash`),
            UNIQUE KEY `supplier_product` (`supplier_code`, `product_id`),
            KEY `product_id` (`product_id`),
            KEY `supplier_active` (`supplier_code`, `key_field`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->tableCache = array();
        $this->columnCache = array();
        $this->migrateTables();
        $this->ensureStorageDirectory();
    }

    private function ensureInstalled() {
        if ($this->runtimeChecked) {
            return;
        }
        $this->runtimeChecked = true;
        if (!$this->tableExists(self::BATCH_TABLE) || !$this->tableExists(self::QUEUE_TABLE) || !$this->tableExists(self::LOG_TABLE) || !$this->tableExists(self::SUPPLIER_TABLE)) {
            $this->install();
        } else {
            $this->ensureStorageDirectory();
        }
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . self::SUPPLIER_TABLE . "`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . self::LOG_TABLE . "`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . self::QUEUE_TABLE . "`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . self::BATCH_TABLE . "`");
        $this->removeDirectory($this->getStorageDirectory());
    }

    private function migrateTables() {
        $batchColumns = array(
            'source_path' => "varchar(500) NOT NULL DEFAULT '' AFTER `file_name`",
            'headers_json' => "mediumtext NULL AFTER `options_json`",
            'delimiter' => "varchar(8) NOT NULL DEFAULT ';' AFTER `headers_json`",
            'encoding' => "varchar(32) NOT NULL DEFAULT 'UTF-8' AFTER `delimiter`",
            'has_header' => "tinyint(1) NOT NULL DEFAULT '1' AFTER `encoding`",
            'file_offset' => "bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `has_header`",
            'csv_row_number' => "int(11) unsigned NOT NULL DEFAULT '0' AFTER `file_offset`",
            'prepare_status' => "varchar(32) NOT NULL DEFAULT 'ready' AFTER `csv_row_number`",
            'skipped_rows' => "int(11) unsigned NOT NULL DEFAULT '0' AFTER `queued_rows`",
            'error_rows' => "int(11) unsigned NOT NULL DEFAULT '0' AFTER `skipped_rows`",
            'message' => "text NULL AFTER `error_rows`"
        );
        foreach ($batchColumns as $column => $definition) {
            $this->ensureColumn(self::BATCH_TABLE, $column, $definition);
        }

        $queueColumns = array(
            'worker_token' => "varchar(64) NOT NULL DEFAULT '' AFTER `warnings`",
            'attempts' => "smallint(5) unsigned NOT NULL DEFAULT '0' AFTER `worker_token`",
            'selected' => "tinyint(1) NOT NULL DEFAULT '1' AFTER `attempts`",
            'lease_expires' => "datetime NULL DEFAULT NULL AFTER `selected`",
            'heartbeat_at' => "datetime NULL DEFAULT NULL AFTER `lease_expires`",
            'date_modified' => "datetime NULL DEFAULT NULL AFTER `date_finished`"
        );
        foreach ($queueColumns as $column => $definition) {
            $this->ensureColumn(self::QUEUE_TABLE, $column, $definition);
        }
        $this->db->query("DELETE q2 FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` q2 INNER JOIN `" . DB_PREFIX . self::QUEUE_TABLE . "` q1 ON (q1.batch_id=q2.batch_id AND q1.csv_row_number=q2.csv_row_number AND q1.queue_id<q2.queue_id)");
        $this->ensureIndex(self::QUEUE_TABLE, 'batch_row', array('batch_id', 'csv_row_number'), true);

        foreach (array(self::BATCH_TABLE, self::QUEUE_TABLE, self::LOG_TABLE, self::SUPPLIER_TABLE) as $table) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ENGINE=InnoDB");
            $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET prepare_status = 'ready' WHERE prepare_status = ''");
        $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET date_modified = COALESCE(date_finished, date_started, date_added, NOW()) WHERE date_modified = '0000-00-00 00:00:00' OR date_modified IS NULL");
    }

    public function createBatchFromCsv($entityType, $csvPath, $originalName, $options) {
        $this->ensureInstalled();
        $entityType = $this->normalizeEntityType($entityType);
        $batchId = $this->createBatchId();
        $storageDir = $this->ensureStorageDirectory();
        $sourcePath = $storageDir . $batchId . '.csv';

        if (!is_file($csvPath) || !is_readable($csvPath)) {
            return array('error' => 'CSV-файл недоступен для чтения.');
        }

        if (!@copy($csvPath, $sourcePath)) {
            return array('error' => 'Не удалось сохранить CSV в постоянное хранилище модуля.');
        }
        @chmod($sourcePath, 0640);

        $encoding = $this->normalizeEncoding($this->getOption($options, 'encoding', 'UTF-8'));
        $delimiter = $this->resolveDelimiter($this->getOption($options, 'delimiter', 'auto'), $sourcePath);
        $handle = @fopen($sourcePath, 'rb');
        if (!$handle) {
            @unlink($sourcePath);
            return array('error' => 'Не удалось открыть сохранённый CSV-файл.');
        }

        $firstRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($firstRow === false) {
            fclose($handle);
            @unlink($sourcePath);
            return array('error' => 'CSV-файл пустой или повреждён.');
        }

        $firstRow = $this->cleanCsvRow($firstRow, $encoding);
        if (!$this->isValidUtf8Row($firstRow)) {
            fclose($handle);
            @unlink($sourcePath);
            return array('error' => 'CSV содержит некорректную кодировку. Выберите UTF-8 или Windows-1251 в соответствии с файлом.');
        }
        $hasHeader = $this->rowLooksLikeHeader($firstRow) || $this->rowMatchesColumnMap($firstRow, $options);
        $headers = $hasHeader ? $firstRow : $this->getDefaultHeaders($entityType);
        $fileOffset = $hasHeader ? (int)ftell($handle) : 0;
        $csvRowNumber = $hasHeader ? 1 : 0;
        fclose($handle);

        $headerError = $this->validateHeaders($headers);
        if ($headerError !== '') {
            @unlink($sourcePath);
            return array('error' => $headerError);
        }

        $fieldMap = $this->buildFieldMap($headers, $options);
        $mappingError = $this->validateMappedHeaders($entityType, $fieldMap, $options);
        if ($mappingError !== '') {
            @unlink($sourcePath);
            return array('error' => $mappingError);
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . self::BATCH_TABLE . "` SET
            batch_id = '" . $this->db->escape($batchId) . "',
            entity_type = '" . $this->db->escape($entityType) . "',
            mode = '" . $this->db->escape($this->normalizeMode($this->getOption($options, 'mode', 'upsert'))) . "',
            file_name = '" . $this->db->escape($this->sanitizeFileName($originalName)) . "',
            source_path = '" . $this->db->escape($sourcePath) . "',
            options_json = '" . $this->db->escape($this->encodeJson($options)) . "',
            headers_json = '" . $this->db->escape($this->encodeJson($headers)) . "',
            delimiter = '" . $this->db->escape($delimiter === "\t" ? 'tab' : $delimiter) . "',
            encoding = '" . $this->db->escape($encoding) . "',
            has_header = '" . (int)$hasHeader . "',
            file_offset = '" . (int)$fileOffset . "',
            csv_row_number = '" . (int)$csvRowNumber . "',
            prepare_status = 'preparing',
            status = 'preparing',
            message = 'CSV сохранён. Создаётся очередь.',
            date_added = NOW(),
            date_modified = NOW()");

        return array(
            'batch_id' => $batchId,
            'entity_type' => $entityType,
            'prepare_complete' => false,
            'summary' => $this->getBatchSummary($batchId, 50)
        );
    }

    public function prepareQueueBatch($entityType, $batchId, $limit) {
        $this->ensureInstalled();
        $entityType = $this->normalizeEntityType($entityType);
        $batchId = trim((string)$batchId);
        $limit = max(10, min(1000, (int)$limit));
        $batch = $this->getBatch($batchId);

        if (!$batch || $batch['entity_type'] !== $entityType) {
            return array('error' => 'Очередь не найдена или относится к другому разделу.');
        }
        if ($batch['prepare_status'] === 'ready') {
            return array('batch_id' => $batchId, 'prepare_complete' => true, 'prepared_now' => 0, 'summary' => $this->getBatchSummary($batchId, 100));
        }
        if ($batch['status'] === 'cancelled') {
            return array('error' => 'Очередь отменена.');
        }

        $lockName = 'ccp_ci_prepare_' . $batchId;
        if (!$this->acquireNamedLock($lockName)) {
            return array('error' => 'Подготовка этой очереди уже выполняется другим запросом.');
        }

        $handle = null;
        $transactionStarted = false;
        try {
            $sourcePath = $this->validateStoredSourcePath($batch['source_path']);
            if ($sourcePath === '' || !is_file($sourcePath) || !is_readable($sourcePath)) {
                $this->markBatchError($batchId, 'Исходный CSV-файл очереди не найден.');
                return array('error' => 'Исходный CSV-файл очереди не найден.');
            }

            $headers = $this->decodeJson($batch['headers_json']);
            if (!$headers) {
                $this->markBatchError($batchId, 'В очереди отсутствуют заголовки CSV.');
                return array('error' => 'В очереди отсутствуют заголовки CSV.');
            }

            $options = $this->decodeJson($batch['options_json']);
            $delimiter = $batch['delimiter'] === 'tab' ? "\t" : $batch['delimiter'];
            if (!in_array($delimiter, array(';', ',', "\t", '|'), true)) {
                $delimiter = ';';
            }
            $encoding = $this->normalizeEncoding($batch['encoding']);
            $fields = $this->buildFieldMap($headers, $options);
            $handle = @fopen($sourcePath, 'rb');
            if (!$handle) {
                $this->markBatchError($batchId, 'Не удалось открыть CSV при создании очереди.');
                return array('error' => 'Не удалось открыть CSV при создании очереди.');
            }

            if (fseek($handle, (int)$batch['file_offset']) !== 0) {
                $this->markBatchError($batchId, 'Не удалось продолжить чтение CSV с сохранённой позиции.');
                return array('error' => 'Не удалось продолжить чтение CSV с сохранённой позиции.');
            }

            $preparedNow = 0;
            $totalDelta = 0;
            $queuedDelta = 0;
            $skippedDelta = 0;
            $errorDelta = 0;
            $csvRowNumber = (int)$batch['csv_row_number'];
            $eof = false;

            $this->db->query('START TRANSACTION');
            $transactionStarted = true;
            $lockedBatch = $this->db->query("SELECT status FROM `" . DB_PREFIX . self::BATCH_TABLE . "` WHERE batch_id='" . $this->db->escape($batchId) . "' FOR UPDATE");
            if (!$lockedBatch->num_rows || $lockedBatch->row['status'] === 'cancelled') {
                throw new RuntimeException('QUEUE_CANCELLED');
            }

            while ($preparedNow < $limit) {
                $row = fgetcsv($handle, 0, $delimiter, '"', '\\');
                if ($row === false) {
                    $eof = true;
                    break;
                }

                $csvRowNumber++;
                $totalDelta++;
                $preparedNow++;
                $row = $this->cleanCsvRow($row, $encoding);

                if (!$this->isValidUtf8Row($row)) {
                    if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, '', '', 0, array(), 'error', 'Строка содержит некорректную последовательность символов UTF-8.')) { $errorDelta++; }
                    continue;
                }
                if (count($row) > 500) {
                    if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, '', '', 0, array(), 'error', 'Строка содержит более 500 колонок.')) { $errorDelta++; }
                    continue;
                }
                if ($this->rowIsEmpty($row)) {
                    $skippedDelta++;
                    continue;
                }
                if (count($row) > count($headers)) {
                    if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, '', '', 0, array(), 'error', 'В строке больше колонок, чем в заголовке CSV. Проверьте разделитель и кавычки.')) { $errorDelta++; }
                    continue;
                }

                $payload = $this->rowToPayload($entityType, $row, $headers, $fields, $options);
                $key = $this->getQueueKey($entityType, $payload, $options);
                if ($key['value'] === '') {
                    $keyMessage = 'Выбранное ключевое поле ' . $key['field'] . ' отсутствует или пустое. Строка отклонена: резервный поиск полностью запрещён.';
                    if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, $key['field'], '', 0, $payload, 'error', $keyMessage)) { $errorDelta++; }
                    continue;
                }

                if ($entityType !== 'option' && $this->queueKeyExists($batchId, $key['field'], $key['value'])) {
                    if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, $key['field'], $key['value'], 0, $payload, 'error', 'В CSV уже есть другая строка с таким же ключом. Дубли одной сущности в одной партии запрещены.')) { $errorDelta++; }
                    continue;
                }

                $match = $this->findEntityMatch($entityType, $key['field'], $key['value'], $payload, $options);
                if ($match['error'] !== '') {
                    if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, $key['field'], $key['value'], 0, $payload, 'error', $match['error'])) { $errorDelta++; }
                    continue;
                }
                $entityId = (int)$match['id'];
                if ($this->insertQueueItem($batchId, $entityType, $csvRowNumber, $key['field'], $key['value'], $entityId, $payload, 'pending', 'Ожидает обработки.')) { $queuedDelta++; }
            }

            $newOffset = (int)ftell($handle);
            if (feof($handle)) { $eof = true; }

            if ($eof && $entityType === 'product' && (string)$this->getOption($options, 'sync_missing_action', 'none') !== 'none') {
                $missing = $this->enqueueMissingSupplierProducts($batchId, $options, $csvRowNumber);
                $csvRowNumber = (int)$missing['csv_row_number'];
                $totalDelta += (int)$missing['total_added'];
                $queuedDelta += (int)$missing['queued_added'];
                $errorDelta += (int)$missing['error_added'];
            }

            $statusSql = '';
            if ($eof) {
                $statusSql = ", prepare_status = 'ready', status = 'queued', message = 'Очередь подготовлена.'";
            }

            $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET
                file_offset = '" . (int)$newOffset . "',
                csv_row_number = '" . (int)$csvRowNumber . "',
                total_rows = total_rows + '" . (int)$totalDelta . "',
                queued_rows = queued_rows + '" . (int)$queuedDelta . "',
                skipped_rows = skipped_rows + '" . (int)$skippedDelta . "',
                error_rows = error_rows + '" . (int)$errorDelta . "',
                date_modified = NOW()" . $statusSql . "
                WHERE batch_id = '" . $this->db->escape($batchId) . "'");

            $this->db->query('COMMIT');
            $transactionStarted = false;

            if ($eof) {
                @unlink($sourcePath);
                $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET source_path = '' WHERE batch_id = '" . $this->db->escape($batchId) . "'");
                $pending = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id='" . $this->db->escape($batchId) . "' AND status IN ('pending','processing')")->row['total'];
                if ($pending === 0) {
                    $message = $totalDelta === 0 && (int)$batch['total_rows'] === 0 ? 'CSV не содержит строк данных.' : 'Подготовка завершена; строк для выполнения нет.';
                    $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status='finished', message='" . $this->db->escape($message) . "', date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "'");
                }
            }

            return array(
                'batch_id' => $batchId,
                'prepare_complete' => $eof,
                'prepared_now' => $preparedNow,
                'summary' => $this->getBatchSummary($batchId, 100)
            );
        } catch (Throwable $e) {
            if ($transactionStarted) {
                try { $this->db->query('ROLLBACK'); } catch (Throwable $ignored) {}
            }
            if ($e->getMessage() === 'QUEUE_CANCELLED') {
                return array('error' => 'Очередь отменена. Подготовка текущей порции откатилась без добавления строк.');
            }
            $this->log->write('Import Export PRO prepare [' . $batchId . ']: ' . $e->getMessage());
            return array('error' => 'Подготовка очереди прервана. Изменения текущей порции отменены; повторный запуск безопасно продолжит чтение CSV.');
        } finally {
            if (is_resource($handle)) { fclose($handle); }
            $this->releaseNamedLock($lockName);
        }
    }

    public function processQueueBatch($entityType, $batchId, $limit) {
        $this->ensureInstalled();
        $entityType = $this->normalizeEntityType($entityType);
        $batchId = trim((string)$batchId);
        $limit = max(1, min(50, (int)$limit));
        $batch = $this->getBatch($batchId);

        if (!$batch || $batch['entity_type'] !== $entityType) {
            return array('error' => 'Очередь не найдена или относится к другому разделу.');
        }
        if ($batch['prepare_status'] !== 'ready') {
            return array('error' => 'Сначала необходимо завершить создание очереди.');
        }
        if ($batch['status'] === 'cancelled') {
            return array('error' => 'Очередь отменена.');
        }

        $options = $this->decodeJson($batch['options_json']);
        $dryRun = (string)$this->getOption($options, 'dry_run', '1') === '1';
        $leaseMinutes = max(10, min(180, (int)$this->getOption($options, 'lease_minutes', 120)));
        $this->recoverStuckProcessing($batchId, $leaseMinutes);

        $workerToken = $this->createWorkerToken();
        $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET
            status = 'processing',
            worker_token = '" . $this->db->escape($workerToken) . "',
            attempts = attempts + 1,
            message = 'Обрабатывается...',
            date_started = NOW(),
            heartbeat_at = NOW(),
            lease_expires = DATE_ADD(NOW(), INTERVAL " . (int)$leaseMinutes . " MINUTE),
            date_modified = NOW()
            WHERE batch_id = '" . $this->db->escape($batchId) . "'
              AND entity_type = '" . $this->db->escape($entityType) . "'
              AND status = 'pending'
              AND selected = '1'
            ORDER BY queue_id ASC
            LIMIT " . (int)$limit);

        $rows = $this->db->query("SELECT * FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE worker_token = '" . $this->db->escape($workerToken) . "' ORDER BY queue_id ASC")->rows;
        $processed = array();

        if ($rows && $batch['status'] !== 'running') {
            $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status = 'running', message = 'Импорт выполняется.', date_modified = NOW() WHERE batch_id = '" . $this->db->escape($batchId) . "' AND status NOT IN ('cancelled','review')");
        }

        foreach ($rows as $row) {
            $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET heartbeat_at=NOW(), lease_expires=DATE_ADD(NOW(), INTERVAL " . (int)$leaseMinutes . " MINUTE), date_modified=NOW() WHERE worker_token='" . $this->db->escape($workerToken) . "' AND status='processing'");
            $lockIdentity = (int)$row['entity_id'] > 0 ? 'id:' . (int)$row['entity_id'] : 'key:' . (string)$row['key_field'] . ':' . (string)$row['key_value'];
            $rowLockName = 'ccp_ci_' . substr(sha1($entityType . ':' . $lockIdentity), 0, 40);
            if (!$this->acquireNamedLock($rowLockName)) {
                $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET status='pending', worker_token='', message='Запись занята другой очередью; строка будет повторена.', lease_expires=NULL, heartbeat_at=NOW(), date_started=NULL, date_modified=NOW() WHERE queue_id='" . (int)$row['queue_id'] . "' AND worker_token='" . $this->db->escape($workerToken) . "'");
                continue;
            }
            try {
                $result = null;
                $transactionStarted = false;
                try {
                $this->db->query('START TRANSACTION');
                $transactionStarted = true;

                $latestBatch = $this->getBatch($batchId);
                if ($latestBatch && $latestBatch['status'] === 'cancelled') {
                    $result = $this->result('skipped', 'cancel', (int)$row['entity_id'], 'Очередь отменена до начала обработки строки.', array());
                } else {
                    $payload = $this->decodeJson($row['payload_json']);
                    $result = $this->importOne($entityType, $row, $payload, $options);
                    if ($dryRun && $result['status'] === 'dry_run') {
                        $result['status'] = 'review';
                        $result['message'] .= ' Строка ожидает подтверждения администратора.';
                    }
                }

                $warnings = isset($result['warnings']) && is_array($result['warnings']) ? implode("\n", $result['warnings']) : '';
                $selectedAfter = isset($result['selected']) ? ((int)$result['selected'] ? 1 : 0) : (int)$row['selected'];
                $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET
                    status = '" . $this->db->escape($result['status']) . "',
                    action = '" . $this->db->escape($result['action']) . "',
                    entity_id = '" . (int)$result['entity_id'] . "',
                    message = '" . $this->db->escape($result['message']) . "',
                    warnings = '" . $this->db->escape($warnings) . "',
                    selected = '" . (int)$selectedAfter . "',
                    worker_token = '',
                    lease_expires = NULL,
                    heartbeat_at = NOW(),
                    date_finished = NOW(),
                    date_modified = NOW()
                    WHERE queue_id = '" . (int)$row['queue_id'] . "' AND worker_token = '" . $this->db->escape($workerToken) . "'");
                if ((int)$this->db->countAffected() !== 1) {
                    throw new RuntimeException('Строка очереди потеряла lease и не может быть безопасно завершена.');
                }
                $this->insertLog($batchId, (int)$row['queue_id'], $entityType, (int)$result['entity_id'], $result['status'], $result['action'], $result['message']);
                $this->db->query('COMMIT');
                $transactionStarted = false;
            } catch (Throwable $e) {
                if ($transactionStarted) {
                    try { $this->db->query('ROLLBACK'); } catch (Throwable $ignored) {}
                }
                $this->log->write('Import Export PRO [' . $batchId . '/' . (int)$row['queue_id'] . ']: ' . $e->getMessage());
                $result = $this->result('error', 'error', (int)$row['entity_id'], 'Ошибка обработки строки. Транзакция отменена; подробности записаны в журнал OpenCart.', array());
                $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET status='error', action='error', message='" . $this->db->escape($result['message']) . "', worker_token='', lease_expires=NULL, heartbeat_at=NOW(), date_finished=NOW(), date_modified=NOW() WHERE queue_id='" . (int)$row['queue_id'] . "' AND worker_token='" . $this->db->escape($workerToken) . "'");
                $this->insertLog($batchId, (int)$row['queue_id'], $entityType, (int)$row['entity_id'], 'error', 'error', $result['message']);
                }
            } finally {
                $this->releaseNamedLock($rowLockName);
            }

            $processed[] = array(
                'queue_id' => (int)$row['queue_id'],
                'row' => (int)$row['csv_row_number'],
                'key' => $row['key_value'],
                'entity_id' => (int)$result['entity_id'],
                'status' => $result['status'],
                'action' => $result['action'],
                'message' => $result['message']
            );
        }

        $summary = $this->getBatchSummary($batchId, 300);
        if ($summary['counts']['processing_complete']) {
            $latestBatch = $this->getBatch($batchId);
            if ($latestBatch && $latestBatch['status'] === 'cancelled') {
                $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET message = 'Очередь отменена. Уже начатые строки завершены безопасно.', date_modified = NOW() WHERE batch_id = '" . $this->db->escape($batchId) . "'");
            } elseif ($dryRun && $summary['counts']['review'] > 0) {
                $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status = 'review', message = 'Проверка завершена. Выберите строки и примените подтверждённые изменения.', date_modified = NOW() WHERE batch_id = '" . $this->db->escape($batchId) . "'");
            } else {
                $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status = 'finished', message = 'Импорт завершён.', date_modified = NOW() WHERE batch_id = '" . $this->db->escape($batchId) . "'");
            }
            $summary = $this->getBatchSummary($batchId, 300);
        }

        return array(
            'batch_id' => $batchId,
            'processed_now' => count($processed),
            'processed_items' => $processed,
            'summary' => $summary
        );
    }

    public function getBatchSummary($batchId, $detailLimit) {
        $batchId = trim((string)$batchId);
        $detailLimit = max(1, min(1000, (int)$detailLimit));
        $batch = $this->getBatch($batchId);
        if (!$batch) {
            return array('batch_id' => $batchId, 'batch' => array(), 'counts' => $this->emptyCounts(), 'items' => array());
        }

        $counts = $this->emptyCounts();
        $query = $this->db->query("SELECT status, COUNT(*) AS total FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id = '" . $this->db->escape($batchId) . "' GROUP BY status");
        foreach ($query->rows as $row) {
            $status = (string)$row['status'];
            $total = (int)$row['total'];
            $counts['total'] += $total;
            if (array_key_exists($status, $counts)) {
                $counts[$status] += $total;
            }
        }

        $counts['selected'] = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id='" . $this->db->escape($batchId) . "' AND status='review' AND selected='1'")->row['total'];
        $counts['done'] = $counts['created'] + $counts['updated'] + $counts['skipped'] + $counts['deleted'] + $counts['dry_run'] + $counts['review'] + $counts['error'] + $counts['warning'];
        $counts['remaining'] = $counts['pending'] + $counts['processing'];
        if ($batch['prepare_status'] !== 'ready') {
            $prepared = (int)$batch['total_rows'];
            $counts['percent'] = 0;
            $counts['prepare_percent'] = $prepared > 0 ? min(99, 10 + min(89, round(log($prepared + 1, 1.7)))) : 5;
        } else {
            $counts['percent'] = $counts['total'] > 0 ? round(($counts['done'] / $counts['total']) * 100, 2) : 100;
            $counts['prepare_percent'] = 100;
        }
        $counts['processing_complete'] = $batch['prepare_status'] === 'ready' && $counts['remaining'] === 0;
        $counts['finished'] = $counts['processing_complete'];

        $items = array();
        $itemQuery = $this->db->query("SELECT queue_id, entity_type, csv_row_number, key_field, key_value, entity_id, status, action, message, warnings, attempts, selected, date_finished
            FROM `" . DB_PREFIX . self::QUEUE_TABLE . "`
            WHERE batch_id = '" . $this->db->escape($batchId) . "' AND status NOT IN ('pending','processing')
            ORDER BY queue_id DESC LIMIT " . (int)$detailLimit);
        foreach ($itemQuery->rows as $item) {
            $items[] = array(
                'queue_id' => (int)$item['queue_id'],
                'entity_type' => $item['entity_type'],
                'row' => (int)$item['csv_row_number'],
                'key_field' => $item['key_field'],
                'key_value' => $item['key_value'],
                'entity_id' => (int)$item['entity_id'],
                'status' => $item['status'],
                'action' => $item['action'],
                'message' => $item['message'],
                'warnings' => $item['warnings'],
                'attempts' => (int)$item['attempts'],
                'selected' => (int)$item['selected'],
                'date_finished' => $item['date_finished']
            );
        }

        return array(
            'batch_id' => $batchId,
            'batch' => array(
                'entity_type' => $batch['entity_type'],
                'file_name' => $batch['file_name'],
                'mode' => $batch['mode'],
                'prepare_status' => $batch['prepare_status'],
                'status' => $batch['status'],
                'total_rows' => (int)$batch['total_rows'],
                'queued_rows' => (int)$batch['queued_rows'],
                'skipped_rows' => (int)$batch['skipped_rows'],
                'error_rows' => (int)$batch['error_rows'],
                'message' => $batch['message'],
                'date_added' => $batch['date_added'],
                'date_modified' => $batch['date_modified']
            ),
            'counts' => $counts,
            'items' => $items
        );
    }

    public function getBatchResultPage($entityType, $batchId, $status, $search, $page, $limit) {
        $entityType = $this->normalizeEntityType($entityType);
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch['entity_type'] !== $entityType) {
            return array('items' => array(), 'total' => 0, 'page' => 1, 'pages' => 1, 'limit' => 50);
        }
        $allowedStatuses = array('pending','processing','created','updated','skipped','deleted','dry_run','review','error','warning');
        $status = in_array((string)$status, $allowedStatuses, true) ? (string)$status : '';
        $search = trim((string)$search);
        if ($this->textLength($search) > 100) { $search = $this->textSubstr($search, 0, 100); }
        $page = max(1, (int)$page);
        $limit = max(10, min(200, (int)$limit));

        $where = "batch_id='" . $this->db->escape($batchId) . "' AND entity_type='" . $this->db->escape($entityType) . "'";
        if ($status !== '') {
            $where .= " AND status='" . $this->db->escape($status) . "'";
        } else {
            $where .= " AND status NOT IN ('pending','processing')";
        }
        if ($search !== '') {
            $escaped = $this->db->escape($search);
            $where .= " AND (key_value LIKE '%" . $escaped . "%' OR message LIKE '%" . $escaped . "%' OR warnings LIKE '%" . $escaped . "%')";
        }

        $total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE " . $where)->row['total'];
        $pages = max(1, (int)ceil($total / $limit));
        if ($page > $pages) { $page = $pages; }
        $start = ($page - 1) * $limit;
        $rows = $this->db->query("SELECT queue_id, entity_type, csv_row_number, key_field, key_value, entity_id, status, action, message, warnings, attempts, selected, date_finished
            FROM `" . DB_PREFIX . self::QUEUE_TABLE . "`
            WHERE " . $where . "
            ORDER BY queue_id ASC LIMIT " . (int)$start . "," . (int)$limit)->rows;
        $items = array();
        foreach ($rows as $item) {
            $items[] = array(
                'queue_id' => (int)$item['queue_id'],
                'entity_type' => $item['entity_type'],
                'row' => (int)$item['csv_row_number'],
                'key_field' => $item['key_field'],
                'key_value' => $item['key_value'],
                'entity_id' => (int)$item['entity_id'],
                'status' => $item['status'],
                'action' => $item['action'],
                'message' => $item['message'],
                'warnings' => $item['warnings'],
                'attempts' => (int)$item['attempts'],
                'selected' => (int)$item['selected'],
                'date_finished' => $item['date_finished']
            );
        }
        return array('items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'limit' => $limit);
    }

    public function recoverStuckProcessing($batchId, $minutes) {
        $minutes = max(10, min(1440, (int)$minutes));
        $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET
            status = 'pending',
            worker_token = '',
            message = 'Lease завершился; строка безопасно возвращена в очередь после прерванной обработки.',
            date_started = NULL,
            lease_expires = NULL,
            heartbeat_at = NOW(),
            date_modified = NOW()
            WHERE batch_id = '" . $this->db->escape(trim((string)$batchId)) . "'
              AND status = 'processing'
              AND ((lease_expires IS NOT NULL AND lease_expires < NOW()) OR (lease_expires IS NULL AND date_started < DATE_SUB(NOW(), INTERVAL " . (int)$minutes . " MINUTE)))");
        return (int)$this->db->countAffected();
    }

    public function cancelBatch($entityType, $batchId) {
        $entityType = $this->normalizeEntityType($entityType);
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch['entity_type'] !== $entityType) {
            return false;
        }
        $sourcePath = $this->validateStoredSourcePath($batch['source_path']);
        if ($sourcePath !== '' && is_file($sourcePath)) {
            @unlink($sourcePath);
        }
        $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status = 'cancelled', prepare_status = IF(prepare_status='ready','ready','cancelled'), source_path = '', message = 'Очередь отменена администратором. Уже начатый серверный запрос может завершить текущие строки.', date_modified = NOW() WHERE batch_id = '" . $this->db->escape($batchId) . "'");
        $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET status = 'skipped', action = 'cancel', message = 'Очередь отменена до начала обработки строки.', worker_token = '', date_finished = NOW(), date_modified = NOW() WHERE batch_id = '" . $this->db->escape($batchId) . "' AND status = 'pending'");
        return true;
    }

    public function setReviewSelection($entityType, $batchId, $queueIds, $selected, $all) {
        $entityType = $this->normalizeEntityType($entityType);
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch['entity_type'] !== $entityType || $batch['status'] !== 'review') { return 0; }
        $selected = $selected ? 1 : 0;
        $where = "batch_id='" . $this->db->escape($batchId) . "' AND entity_type='" . $this->db->escape($entityType) . "' AND status='review'";
        if (!$all) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$queueIds))));
            if (!$ids) { return 0; }
            $where .= ' AND queue_id IN (' . implode(',', $ids) . ')';
        }
        $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET selected='" . $selected . "', date_modified=NOW() WHERE " . $where);
        return (int)$this->db->countAffected();
    }

    public function applySelectedReview($entityType, $batchId) {
        $entityType = $this->normalizeEntityType($entityType);
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch['entity_type'] !== $entityType || $batch['status'] !== 'review') {
            return array('error' => 'Партия не находится на этапе подтверждения.');
        }
        $selectedCount = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id='" . $this->db->escape($batchId) . "' AND status='review' AND selected='1'")->row['total'];
        if ($selectedCount <= 0) { return array('error' => 'Не выбрана ни одна строка для применения.'); }

        $options = $this->decodeJson($batch['options_json']);
        $options['dry_run'] = '0';
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET status='skipped', action='skip', message='Строка не выбрана администратором после проверки.', date_finished=NOW(), date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "' AND status='review' AND selected='0'");
            $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET status='pending', action='', message='Подтверждено администратором; ожидает реального применения.', warnings='', worker_token='', lease_expires=NULL, heartbeat_at=NULL, date_started=NULL, date_finished=NULL, date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "' AND status='review' AND selected='1'");
            $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET options_json='" . $this->db->escape($this->encodeJson($options)) . "', status='queued', message='Подтверждённые строки поставлены в очередь реального импорта.', date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "'");
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            try { $this->db->query('ROLLBACK'); } catch (Throwable $ignored) {}
            $this->log->write('Import Export PRO apply review [' . $batchId . ']: ' . $e->getMessage());
            return array('error' => 'Не удалось применить выбранные строки. Транзакция отменена.');
        }
        return array('success' => 'Выбранные строки поставлены в очередь.', 'selected' => $selectedCount, 'summary' => $this->getBatchSummary($batchId, 300));
    }

    public function retryErrors($entityType, $batchId) {
        $entityType = $this->normalizeEntityType($entityType);
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch['entity_type'] !== $entityType || $batch['status'] === 'cancelled') { return 0; }
        $this->db->query("UPDATE `" . DB_PREFIX . self::QUEUE_TABLE . "` SET status='pending', selected='1', action='', message='Повторный запуск после ошибки.', warnings='', worker_token='', lease_expires=NULL, heartbeat_at=NULL, date_started=NULL, date_finished=NULL, date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "' AND status='error' AND payload_json<>'{}'");
        $count = (int)$this->db->countAffected();
        if ($count > 0) {
            $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status='queued', message='Ошибочные строки возвращены в очередь.', date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "'");
        }
        return $count;
    }

    public function streamBatchReport($entityType, $batchId, $output, $delimiter) {
        $entityType = $this->normalizeEntityType($entityType);
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch['entity_type'] !== $entityType || !is_resource($output)) { return false; }
        if (!in_array($delimiter, array(';', ',', "\t", '|'), true)) { $delimiter = ';'; }
        $this->writeCsvRow($output, array('queue_id','csv_row','key_field','key_value','entity_id','selected','status','action','attempts','message','warnings','source_data_json','date_finished'), $delimiter);
        $lastId = 0;
        do {
            $rows = $this->db->query("SELECT queue_id,csv_row_number,key_field,key_value,entity_id,selected,status,action,attempts,message,warnings,payload_json,date_finished FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id='" . $this->db->escape($batchId) . "' AND queue_id>'" . (int)$lastId . "' ORDER BY queue_id ASC LIMIT 1000")->rows;
            foreach ($rows as $row) {
                $lastId = (int)$row['queue_id'];
                $this->writeCsvRow($output, array($row['queue_id'],$row['csv_row_number'],$row['key_field'],$row['key_value'],$row['entity_id'],$row['selected'],$row['status'],$row['action'],$row['attempts'],$row['message'],$row['warnings'],$row['payload_json'],$row['date_finished']), $delimiter);
            }
        } while ($rows);
        return true;
    }

    public function getRecentBatches($entityType, $limit) {
        $entityType = $this->normalizeEntityType($entityType);
        $limit = max(1, min(50, (int)$limit));
        $rows = $this->db->query("SELECT batch_id, file_name, mode, prepare_status, status, total_rows, queued_rows, date_added, date_modified FROM `" . DB_PREFIX . self::BATCH_TABLE . "` WHERE entity_type = '" . $this->db->escape($entityType) . "' ORDER BY date_added DESC LIMIT " . (int)$limit)->rows;
        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'batch_id' => $row['batch_id'],
                'file_name' => $row['file_name'],
                'mode' => $row['mode'],
                'prepare_status' => $row['prepare_status'],
                'status' => $row['status'],
                'total_rows' => (int)$row['total_rows'],
                'queued_rows' => (int)$row['queued_rows'],
                'date_added' => $row['date_added'],
                'date_modified' => $row['date_modified']
            );
        }
        return $result;
    }

    public function cleanup($days) {
        $days = max(1, min(3650, (int)$days));
        $oldBatches = $this->db->query("SELECT batch_id, source_path FROM `" . DB_PREFIX . self::BATCH_TABLE . "` WHERE date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY) AND status IN ('finished','cancelled','error','review')")->rows;
        $count = 0;
        foreach ($oldBatches as $batch) {
            $sourcePath = $this->validateStoredSourcePath($batch['source_path']);
            if ($sourcePath !== '' && is_file($sourcePath)) {
                @unlink($sourcePath);
            }
            $this->db->query("DELETE FROM `" . DB_PREFIX . self::LOG_TABLE . "` WHERE batch_id = '" . $this->db->escape($batch['batch_id']) . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id = '" . $this->db->escape($batch['batch_id']) . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . self::BATCH_TABLE . "` WHERE batch_id = '" . $this->db->escape($batch['batch_id']) . "'");
            $count++;
        }
        return $count;
    }

    private function acquireNamedLock($name) {
        $name = substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$name), 0, 64);
        if ($name === '') { return false; }
        try {
            $query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($name) . "', 0) AS acquired");
            return isset($query->row['acquired']) && (int)$query->row['acquired'] === 1;
        } catch (Throwable $e) {
            $this->log->write('Import Export PRO lock: ' . $e->getMessage());
            return false;
        }
    }

    private function releaseNamedLock($name) {
        $name = substr(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$name), 0, 64);
        if ($name === '') { return; }
        try { $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($name) . "')"); } catch (Throwable $ignored) {}
    }

    private function emptyCounts() {
        return array(
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'deleted' => 0,
            'dry_run' => 0,
            'review' => 0,
            'error' => 0,
            'warning' => 0,
            'selected' => 0,
            'done' => 0,
            'remaining' => 0,
            'percent' => 0,
            'prepare_percent' => 0,
            'processing_complete' => false,
            'finished' => false
        );
    }

    private function importOne($entityType, $queueRow, $payload, $options) {
        $mode = $this->normalizeMode($this->getOption($options, 'mode', 'upsert'));
        $dryRun = (string)$this->getOption($options, 'dry_run', '1') === '1';
        if ($entityType === 'product') {
            return $this->importProduct($queueRow, $payload, $options, $mode, $dryRun);
        }
        if ($entityType === 'category') {
            return $this->importCategory($queueRow, $payload, $options, $mode, $dryRun);
        }
        if ($entityType === 'option') {
            return $this->importOption($queueRow, $payload, $options, $mode, $dryRun);
        }
        return $this->importManufacturer($queueRow, $payload, $options, $mode, $dryRun);
    }

    private function importProduct($queueRow, $payload, $options, $mode, $dryRun) {
        $warnings = array();
        $errors = array();
        $productId = (int)$queueRow['entity_id'];
        if (!$productId || !$this->idExists('product', 'product_id', $productId)) {
            $match = $this->findEntityMatch('product', $queueRow['key_field'], $queueRow['key_value'], $payload, $options);
            if ($match['error'] !== '') {
                return $this->result('error', 'skip', 0, $match['error'], $warnings);
            }
            $productId = (int)$match['id'];
        }

        if ((string)$this->payloadGet($payload, array('_SYNC_MISSING_'), '0') === '1') {
            return $this->importMissingSupplierProduct($queueRow, $productId, $options, $dryRun);
        }

        if ($mode === 'update' && !$productId) {
            return $this->result('skipped', 'skip', 0, 'Товар не найден, а выбран режим только обновления.', $warnings);
        }
        if ($mode === 'add' && $productId) {
            $supplierCode = trim((string)$this->getOption($options, 'supplier_code', ''));
            if ($supplierCode === '') {
                return $this->result('skipped', 'skip', $productId, 'Товар уже существует, а выбран режим только добавления.', $warnings);
            }
            if ($dryRun) {
                return $this->result('dry_run', 'link', $productId, 'Тестовый режим: товар не изменяется, но после подтверждения будет зарегистрирован в реестре выбранного поставщика.', $warnings);
            }
            $this->recordSupplierProduct($supplierCode, (string)$queueRow['key_field'], (string)$queueRow['key_value'], $productId, (string)$queueRow['batch_id'], $payload, $options);
            return $this->result('updated', 'link', $productId, 'Товар уже существовал и не изменён; связь с выбранным поставщиком зарегистрирована.', $warnings);
        }
        if ($mode === 'delete') {
            if (!$productId) {
                return $this->result('skipped', 'skip', 0, 'Товар для удаления не найден.', $warnings);
            }
            if ($dryRun) {
                return $this->result('dry_run', 'delete', $productId, 'Тестовый режим: товар и его штатные связи были бы удалены.', $warnings);
            }
            $this->load->model('catalog/product');
            $this->model_catalog_product->deleteProduct($productId);
            $this->db->query("DELETE FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "` WHERE product_id='" . (int)$productId . "'");
            return $this->result('deleted', 'delete', $productId, 'Товар удалён штатной моделью OpenCart вместе со связанными данными.', $warnings);
        }

        $isNew = !$productId;
        $data = $this->buildProductData($productId, $payload, $options, $isNew, $dryRun, $warnings, $errors);
        if ($errors) {
            return $this->result('error', 'skip', $productId, implode(' ', $errors), $warnings);
        }

        if ($dryRun) {
            return $this->result('dry_run', $isNew ? 'create' : 'update', $productId, 'Тестовый режим: товар был бы ' . ($isNew ? 'создан' : 'обновлён') . ' без изменения базы данных.', $warnings);
        }

        $this->load->model('catalog/product');
        if ($isNew) {
            $requestedId = $this->getRequestedImportId($payload, $options, 'product', 'product_id');
            if ($requestedId > 0) {
                $this->insertProductSkeleton($requestedId, $data);
                $productId = $requestedId;
                $this->model_catalog_product->editProduct($productId, $data);
            } else {
                $createdId = $this->model_catalog_product->addProduct($data);
                $productId = (int)$createdId;
                if (!$productId) {
                    $productId = (int)$this->db->getLastId();
                }
            }
            $status = 'created';
            $action = 'create';
            $message = 'Товар создан.';
        } else {
            $this->model_catalog_product->editProduct($productId, $data);
            $status = 'updated';
            $action = 'update';
            $message = 'Товар обновлён.';
        }

        $mainCategoryId = isset($data['_main_category_id']) ? (int)$data['_main_category_id'] : 0;
        $this->applyMainCategory($productId, $mainCategoryId);
        if (array_key_exists('_google_product_category', $data) && $mainCategoryId > 0) {
            $this->saveGoogleProductCategory($mainCategoryId, $data['_google_product_category'], isset($data['product_store'][0]) ? (int)$data['product_store'][0] : 0);
        }
        $supplierCode = trim((string)$this->getOption($options, 'supplier_code', ''));
        if ($supplierCode !== '') {
            $this->recordSupplierProduct($supplierCode, (string)$queueRow['key_field'], (string)$queueRow['key_value'], $productId, (string)$queueRow['batch_id'], $payload, $options);
        }
        $this->cache->delete('product');
        return $this->result($status, $action, $productId, $message, $warnings);
    }

    private function importMissingSupplierProduct($queueRow, $productId, $options, $dryRun) {
        $warnings = array();
        $supplierCode = trim((string)$this->getOption($options, 'supplier_code', ''));
        $missingAction = (string)$this->getOption($options, 'sync_missing_action', 'none');
        if (!preg_match('/^[A-Za-z0-9._-]{2,64}$/', $supplierCode) || !in_array($missingAction, array('disable','zero','delete'), true)) {
            return $this->result('error', 'skip', (int)$productId, 'Параметры синхронизации поставщика недействительны.', $warnings);
        }

        if (!$productId || !$this->idExists('product', 'product_id', (int)$productId)) {
            $this->deactivateSupplierMapping($supplierCode, (int)$productId, true);
            $this->deactivateLegacySupplierMapping($options, (int)$productId);
            return $this->result('skipped', 'skip', 0, 'Товар уже отсутствует в каталоге; устаревшая связь поставщика удалена.', $warnings);
        }

        if (!$this->supplierMappingExists($supplierCode, (string)$queueRow['key_field'], (string)$queueRow['key_value'], (int)$productId)) {
            return $this->result('skipped', 'skip', (int)$productId, 'Связь товара с поставщиком уже изменилась; опасное действие отменено.', $warnings);
        }

        $fresh = $this->db->query("SELECT sp.last_seen_at, sp.last_seen_batch_id, b.date_added AS batch_date
            FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "` sp
            INNER JOIN `" . DB_PREFIX . self::BATCH_TABLE . "` b ON (b.batch_id='" . $this->db->escape((string)$queueRow['batch_id']) . "')
            WHERE sp.supplier_code='" . $this->db->escape($supplierCode) . "'
              AND sp.product_id='" . (int)$productId . "' LIMIT 1");
        if ($fresh->num_rows && $fresh->row['last_seen_at'] && $fresh->row['batch_date'] && strtotime($fresh->row['last_seen_at']) > strtotime($fresh->row['batch_date']) && $fresh->row['last_seen_batch_id'] !== (string)$queueRow['batch_id']) {
            return $this->result('skipped', 'skip', (int)$productId, 'После создания этой очереди товар снова был получен от поставщика; действие отменено.', $warnings);
        }

        $otherSupplierCount = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "`
            WHERE product_id='" . (int)$productId . "'
              AND supplier_code<>'" . $this->db->escape($supplierCode) . "'
              AND is_active='1'")->row['total'];
        if ($otherSupplierCount > 0) {
            $message = 'Товар также закреплён за другим активным поставщиком. Отключение, обнуление остатка или удаление заблокировано, чтобы не повредить общий каталог.';
            if ($dryRun) {
                $result = $this->result('dry_run', 'unlink', (int)$productId, 'Тестовый режим: ' . $message . ' При подтверждении будет отключена только связь с текущим поставщиком.', $warnings);
                $result['selected'] = 0;
                return $result;
            }
            $this->deactivateSupplierMapping($supplierCode, (int)$productId, false);
            $this->deactivateLegacySupplierMapping($options, (int)$productId);
            return $this->result('updated', 'unlink', (int)$productId, $message . ' Связь только с текущим поставщиком отключена.', $warnings);
        }

        $actionLabels = array('disable' => 'отключён', 'zero' => 'обнулён остаток', 'delete' => 'удалён');
        if ($dryRun) {
            $result = $this->result('dry_run', $missingAction, (int)$productId, 'Тестовый режим: отсутствующий у поставщика товар был бы ' . $actionLabels[$missingAction] . '. Строка по умолчанию не выбрана.', $warnings);
            $result['selected'] = 0;
            return $result;
        }

        $this->load->model('catalog/product');
        if ($missingAction === 'delete') {
            $this->model_catalog_product->deleteProduct((int)$productId);
            $this->db->query("DELETE FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "` WHERE product_id='" . (int)$productId . "'");
            if ($this->tableExists('import_pro_supplier_product')) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_supplier_product` WHERE product_id='" . (int)$productId . "'");
            }
            $this->cache->delete('product');
            return $this->result('deleted', 'delete', (int)$productId, 'Товар отсутствует в файле поставщика и удалён после отдельного подтверждения администратора.', $warnings);
        }

        $data = $this->getExistingProductData((int)$productId);
        if ($missingAction === 'disable') {
            $data['status'] = 0;
            $message = 'Товар отсутствует в файле поставщика и отключён после отдельного подтверждения администратора.';
        } else {
            $data['quantity'] = 0;
            $message = 'Товар отсутствует в файле поставщика; остаток обнулён после отдельного подтверждения администратора.';
        }
        $this->model_catalog_product->editProduct((int)$productId, $data);
        $this->deactivateSupplierMapping($supplierCode, (int)$productId, false);
        $this->deactivateLegacySupplierMapping($options, (int)$productId);
        $this->cache->delete('product');
        return $this->result('updated', $missingAction, (int)$productId, $message, $warnings);
    }

    private function importCategory($queueRow, $payload, $options, $mode, $dryRun) {
        $warnings = array();
        $errors = array();
        $categoryId = (int)$queueRow['entity_id'];
        if (!$categoryId || !$this->idExists('category', 'category_id', $categoryId)) {
            $match = $this->findEntityMatch('category', $queueRow['key_field'], $queueRow['key_value'], $payload, $options);
            if ($match['error'] !== '') {
                return $this->result('error', 'skip', 0, $match['error'], $warnings);
            }
            $categoryId = (int)$match['id'];
        }

        if ($mode === 'update' && !$categoryId) {
            return $this->result('skipped', 'skip', 0, 'Категория не найдена, а выбран режим только обновления.', $warnings);
        }
        if ($mode === 'add' && $categoryId) {
            return $this->result('skipped', 'skip', $categoryId, 'Категория уже существует, а выбран режим только добавления.', $warnings);
        }
        if ($mode === 'delete') {
            if (!$categoryId) {
                return $this->result('skipped', 'skip', 0, 'Категория для удаления не найдена.', $warnings);
            }
            $childCount = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "category` WHERE parent_id = '" . (int)$categoryId . "'")->row['total'];
            $deleteChildren = (string)$this->getOption($options, 'delete_category_children', '0') === '1';
            if ($childCount > 0 && !$deleteChildren) {
                return $this->result('error', 'skip', $categoryId, 'Категория содержит дочерние категории. Для рекурсивного удаления необходимо явно включить соответствующую опцию.', $warnings);
            }
            if ($dryRun) {
                return $this->result('dry_run', 'delete', $categoryId, 'Тестовый режим: категория' . ($childCount ? ' и её дочернее дерево' : '') . ' была бы удалена.', $warnings);
            }
            $this->load->model('catalog/category');
            $this->model_catalog_category->deleteCategory($categoryId);
            return $this->result('deleted', 'delete', $categoryId, 'Категория удалена штатной моделью OpenCart.', $warnings);
        }

        $isNew = !$categoryId;
        $data = $this->buildCategoryData($categoryId, $payload, $options, $isNew, $dryRun, $warnings, $errors);
        if ($errors) {
            return $this->result('error', 'skip', $categoryId, implode(' ', $errors), $warnings);
        }

        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : 0;
        if (!$isNew && !$this->isValidCategoryParent($categoryId, $parentId)) {
            return $this->result('error', 'skip', $categoryId, 'Нельзя назначить категорию самой себе или создать циклическую иерархию.', $warnings);
        }

        if ($dryRun) {
            return $this->result('dry_run', $isNew ? 'create' : 'update', $categoryId, 'Тестовый режим: категория была бы ' . ($isNew ? 'создана' : 'обновлена') . ' без изменения базы данных.', $warnings);
        }

        $this->load->model('catalog/category');
        if ($isNew) {
            $requestedId = $this->getRequestedImportId($payload, $options, 'category', 'category_id');
            if ($requestedId > 0) {
                $this->insertCategorySkeleton($requestedId, $data);
                $categoryId = $requestedId;
                $this->model_catalog_category->editCategory($categoryId, $data);
            } else {
                $createdId = $this->model_catalog_category->addCategory($data);
                $categoryId = (int)$createdId;
                if (!$categoryId) {
                    $categoryId = (int)$this->db->getLastId();
                }
            }
            $status = 'created';
            $action = 'create';
            $message = 'Категория создана.';
        } else {
            $this->model_catalog_category->editCategory($categoryId, $data);
            $status = 'updated';
            $action = 'update';
            $message = 'Категория обновлена.';
        }

        $this->rebuildCategorySubtreePaths($categoryId);
        $this->cache->delete('category');
        return $this->result($status, $action, $categoryId, $message, $warnings);
    }

    private function importManufacturer($queueRow, $payload, $options, $mode, $dryRun) {
        $warnings = array();
        $errors = array();
        $manufacturerId = (int)$queueRow['entity_id'];
        if (!$manufacturerId || !$this->idExists('manufacturer', 'manufacturer_id', $manufacturerId)) {
            $match = $this->findEntityMatch('manufacturer', $queueRow['key_field'], $queueRow['key_value'], $payload, $options);
            if ($match['error'] !== '') {
                return $this->result('error', 'skip', 0, $match['error'], $warnings);
            }
            $manufacturerId = (int)$match['id'];
        }

        if ($mode === 'update' && !$manufacturerId) {
            return $this->result('skipped', 'skip', 0, 'Производитель не найден, а выбран режим только обновления.', $warnings);
        }
        if ($mode === 'add' && $manufacturerId) {
            return $this->result('skipped', 'skip', $manufacturerId, 'Производитель уже существует, а выбран режим только добавления.', $warnings);
        }
        if ($mode === 'delete') {
            if (!$manufacturerId) {
                return $this->result('skipped', 'skip', 0, 'Производитель для удаления не найден.', $warnings);
            }
            if ($dryRun) {
                return $this->result('dry_run', 'delete', $manufacturerId, 'Тестовый режим: производитель был бы удалён, а его товары получили бы manufacturer_id=0 штатной логикой OpenCart.', $warnings);
            }
            $affectedProducts = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product` WHERE manufacturer_id='" . (int)$manufacturerId . "'")->row['total'];
            if ($affectedProducts > 0) {
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET manufacturer_id='0', date_modified=NOW() WHERE manufacturer_id='" . (int)$manufacturerId . "'");
            }
            $this->load->model('catalog/manufacturer');
            $this->model_catalog_manufacturer->deleteManufacturer($manufacturerId);
            $this->cache->delete('product');
            if ($affectedProducts > 0) {
                $warnings[] = 'У товаров сброшена удалённая связь с производителем: ' . $affectedProducts . '.';
            }
            return $this->result('deleted', 'delete', $manufacturerId, 'Производитель удалён штатной моделью OpenCart.', $warnings);
        }

        $isNew = !$manufacturerId;
        $data = $this->buildManufacturerData($manufacturerId, $payload, $options, $isNew, $dryRun, $warnings, $errors);
        if ($errors) {
            return $this->result('error', 'skip', $manufacturerId, implode(' ', $errors), $warnings);
        }

        if ($dryRun) {
            return $this->result('dry_run', $isNew ? 'create' : 'update', $manufacturerId, 'Тестовый режим: производитель был бы ' . ($isNew ? 'создан' : 'обновлён') . ' без изменения базы данных.', $warnings);
        }

        $this->load->model('catalog/manufacturer');
        if ($isNew) {
            $requestedId = $this->getRequestedImportId($payload, $options, 'manufacturer', 'manufacturer_id');
            if ($requestedId > 0) {
                $this->insertManufacturerSkeleton($requestedId, $data);
                $manufacturerId = $requestedId;
                $this->model_catalog_manufacturer->editManufacturer($manufacturerId, $data);
            } else {
                $createdId = $this->model_catalog_manufacturer->addManufacturer($data);
                $manufacturerId = (int)$createdId;
                if (!$manufacturerId) {
                    $manufacturerId = (int)$this->db->getLastId();
                }
            }
            $status = 'created';
            $action = 'create';
            $message = 'Производитель создан.';
        } else {
            $this->model_catalog_manufacturer->editManufacturer($manufacturerId, $data);
            $status = 'updated';
            $action = 'update';
            $message = 'Производитель обновлён.';
        }

        $this->cache->delete('manufacturer');
        return $this->result($status, $action, $manufacturerId, $message, $warnings);
    }

    private function buildProductData($productId, $payload, $options, $isNew, $dryRun, &$warnings, &$errors) {
        $this->currentEntityIsNew = (bool)$isNew;
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $ignoreEmpty = (string)$this->getOption($options, 'empty_field', '1') === '1';
        $data = $isNew ? $this->getNewProductDefaults($options) : $this->getExistingProductData($productId);

        $fieldMap = array(
            'model' => array(array('_MODEL_', 'model'), 'string'),
            'sku' => array(array('_SKU_', 'sku'), 'string'),
            'upc' => array(array('_UPC_', 'upc'), 'string'),
            'ean' => array(array('_EAN_', 'ean'), 'string'),
            'jan' => array(array('_JAN_', 'jan'), 'string'),
            'isbn' => array(array('_ISBN_', 'isbn'), 'string'),
            'mpn' => array(array('_MPN_', 'mpn'), 'string'),
            'location' => array(array('_LOCATION_', 'location'), 'string'),
            'quantity' => array(array('_QUANTITY_', 'quantity'), 'int'),
            'minimum' => array(array('_MINIMUM_', 'minimum'), 'int'),
            'subtract' => array(array('_SUBTRACT_', 'subtract'), 'bool'),
            'stock_status_id' => array(array('_STOCK_STATUS_ID_', 'stock_status_id'), 'int'),
            'date_available' => array(array('_DATE_AVAILABLE_', 'date_available'), 'date'),
            'shipping' => array(array('_SHIPPING_', 'shipping'), 'bool'),
            'price' => array(array('_PRICE_', 'price'), 'decimal'),
            'points' => array(array('_POINTS_', 'points'), 'int'),
            'tax_class_id' => array(array('_TAX_CLASS_ID_', 'tax_class_id'), 'int'),
            'weight' => array(array('_WEIGHT_', 'weight'), 'decimal'),
            'weight_class_id' => array(array('_WEIGHT_CLASS_ID_', 'weight_class_id'), 'int'),
            'length' => array(array('_LENGTH_', 'length'), 'decimal'),
            'width' => array(array('_WIDTH_', 'width'), 'decimal'),
            'height' => array(array('_HEIGHT_', 'height'), 'decimal'),
            'length_class_id' => array(array('_LENGTH_CLASS_ID_', 'length_class_id'), 'int'),
            'status' => array(array('_STATUS_', 'status'), 'bool'),
            'noindex' => array(array('_NOINDEX_', 'noindex'), 'bool'),
            'sort_order' => array(array('_SORT_ORDER_', 'sort_order'), 'int')
        );

        foreach ($fieldMap as $field => $definition) {
            $present = $this->payloadValueWithPresence($payload, $definition[0]);
            if (!$present['present']) { continue; }
            $rule = $this->getFieldRule($options, $definition[0], 'overwrite');
            if ($rule === 'preserve') { continue; }
            if ($rule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($data[$field]) ? $data[$field] : null)) { continue; }
            if ($rule === 'clear') {
                $data[$field] = $this->clearValueForType($definition[1]);
                continue;
            }
            if ($present['value'] === '' && ($ignoreEmpty || $field === 'quantity' || $field === 'stock_status_id')) { continue; }
            $value = $this->castValue($present['value'], $definition[1], $field, $errors);
            if ($value !== null) { $data[$field] = $value; }
        }

        $manufacturerIdField = $this->payloadValueWithPresence($payload, array('_MANUFACTURER_ID_', 'manufacturer_id'));
        $manufacturerNameField = $this->payloadValueWithPresence($payload, array('_MANUFACTURER_', 'manufacturer'));
        $manufacturerRule = $this->getFieldRule($options, array('_MANUFACTURER_ID_', '_MANUFACTURER_'), 'overwrite');
        if (($manufacturerIdField['present'] || $manufacturerNameField['present']) && $manufacturerRule !== 'preserve') {
            if ($manufacturerRule === 'fill_empty' && !$isNew && (int)$data['manufacturer_id'] > 0) {
                // Existing manufacturer is retained.
            } elseif ($manufacturerRule === 'clear') {
                $data['manufacturer_id'] = 0;
            } elseif ($manufacturerIdField['value'] === '' && $manufacturerNameField['value'] === '' && $ignoreEmpty) {
                // Preserve the existing manufacturer.
            } else {
                $resolved = $this->resolveManufacturerId($manufacturerIdField['value'], $manufacturerNameField['value'], $options, $dryRun, $warnings, $errors);
                if ($resolved !== null) { $data['manufacturer_id'] = (int)$resolved; }
            }
        }

        $imageField = $this->payloadValueWithPresence($payload, array('_IMAGE_', 'image'));
        $imageRule = $this->getFieldRule($options, array('_IMAGE_', 'image'), 'overwrite');
        if ($imageField['present'] && $imageRule !== 'preserve') {
            if ($imageRule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($data['image']) ? $data['image'] : '')) {
                // Existing image is retained.
            } elseif ($imageRule === 'clear') {
                $data['image'] = '';
            } elseif (!($imageField['value'] === '' && $ignoreEmpty)) {
                $imageResult = $this->prepareImageValue($imageField['value'], $options, $dryRun, $warnings);
                if ($imageResult['ok']) { $data['image'] = $imageResult['value']; }
            }
        }

        $data['product_description'] = $this->mergeLocalizedDescriptions(
            isset($data['product_description']) ? $data['product_description'] : array(),
            $payload,
            $languageId,
            array('name', 'description', 'tag', 'meta_title', 'meta_description', 'meta_keyword', 'meta_h1'),
            $ignoreEmpty,
            $isNew,
            (string)$this->getOption($options, 'copy_default_language', '0') === '1',
            $options
        );

        $selectedDescription = isset($data['product_description'][$languageId]) ? $data['product_description'][$languageId] : array();
        $name = isset($selectedDescription['name']) ? trim((string)$selectedDescription['name']) : '';
        $nameInput = $this->getLocalizedPayloadValues($payload, 'name', $languageId);
        $nameRule = $this->getFieldRule($options, $this->getFieldAliases('name'), 'overwrite');
        if ($isNew && $name === '') {
            $errors[] = 'Для нового товара необходимо заполнить _NAME_ или языковую колонку названия.';
        } elseif ($nameRule !== 'preserve' && isset($nameInput[$languageId]) && !empty($nameInput[$languageId]['present']) && $name === '') {
            $errors[] = 'Название товара нельзя очистить или заменить пустым значением.';
        }
        $modelInput = $this->payloadValueWithPresence($payload, array('_MODEL_', 'model'));
        $modelRule = $this->getFieldRule($options, array('_MODEL_', 'model'), 'overwrite');
        if ($modelInput['present'] && $modelRule !== 'preserve' && trim((string)$data['model']) === '') {
            $errors[] = 'Модель товара нельзя очистить или заменить пустым значением.';
        }
        if ($isNew && trim((string)$data['model']) === '') {
            $fallback = trim((string)$this->payloadGet($payload, array('_SKU_', '_EAN_', '_UPC_'), ''));
            $data['model'] = $fallback !== '' ? $fallback : 'IMPORT-' . strtoupper(substr(sha1($this->encodeJson($payload)), 0, 12));
            $warnings[] = 'Модель товара не указана; создано безопасное служебное значение ' . $data['model'] . '.';
        }

        $storeField = $this->payloadValueWithPresence($payload, array('_STORE_IDS_', '_STORES_', 'store_ids'));
        $storeRule = $this->getFieldRule($options, array('_STORE_IDS_', '_STORES_'), (string)$this->getOption($options, 'replace_store_links', '0') === '1' ? 'replace' : 'overwrite');
        if ($storeRule !== 'preserve' && ($isNew || $storeField['present'] || (string)$this->getOption($options, 'replace_store_links', '0') === '1')) {
            $rawStores = $storeField['present'] ? $storeField['value'] : $this->getOption($options, 'store_ids', '0');
            $newStores = $storeRule === 'clear' ? array() : $this->normalizeStoreIds($rawStores, $warnings);
            if ($storeRule === 'fill_empty' && !$isNew && !empty($data['product_store'])) {
                // Existing store links are retained.
            } elseif ($storeRule === 'merge') {
                $data['product_store'] = array_values(array_unique(array_merge((array)$data['product_store'], $newStores)));
            } else {
                $data['product_store'] = $newStores;
            }
        }

        $categoryResult = $this->resolveProductCategories(
            isset($data['product_category']) ? $data['product_category'] : array(),
            isset($data['_main_category_id']) ? $data['_main_category_id'] : $this->getExistingMainCategoryId($productId),
            $payload,
            $options,
            $isNew,
            $dryRun,
            $warnings,
            $errors
        );
        $data['product_category'] = $categoryResult['categories'];
        $data['_main_category_id'] = $categoryResult['main_category_id'];
        $data['main_category_id'] = $categoryResult['main_category_id'];

        $imagesField = $this->payloadValueWithPresence($payload, array('_IMAGES_', '_ADDITIONAL_IMAGES_', 'images'));
        $imagesRule = $this->getFieldRule($options, array('_IMAGES_', '_ADDITIONAL_IMAGES_'), 'replace');
        if ($imagesField['present'] && $imagesRule !== 'preserve') {
            $newImages = array();
            foreach ($this->splitMulti($imagesField['value'], '|') as $index => $imageValue) {
                $imageResult = $this->prepareImageValue($imageValue, $options, $dryRun, $warnings);
                if ($imageResult['ok'] && $imageResult['value'] !== '') { $newImages[] = array('image' => $imageResult['value'], 'sort_order' => $index); }
            }
            if ($imagesRule === 'clear') {
                $data['product_image'] = array();
            } elseif ($imagesRule === 'fill_empty' && !$isNew && !empty($data['product_image'])) {
                // Existing additional images are retained.
            } elseif ($imagesRule === 'merge') {
                $existingMap = array();
                foreach ((array)$data['product_image'] as $image) { if (!empty($image['image'])) { $existingMap[$image['image']] = $image; } }
                foreach ($newImages as $image) { $existingMap[$image['image']] = $image; }
                $data['product_image'] = array_values($existingMap);
            } elseif ($newImages || !$ignoreEmpty) {
                $data['product_image'] = $newImages;
            }
        }

        $attributesField = $this->payloadValueWithPresence($payload, array('_ATTRIBUTES_', 'attributes'));
        $individualAttributes = $this->extractIndividualAttributes($payload);
        $attributeRule = $this->getFieldRule($options, array('_ATTRIBUTES_'), (string)$this->getOption($options, 'replace_attributes', '0') === '1' ? 'replace' : 'merge');
        if ($ignoreEmpty && $attributeRule !== 'clear') {
            $individualAttributes = array_values(array_filter($individualAttributes, function($attribute) {
                return trim((string)$attribute['text']) !== '';
            }));
        }
        $hasCompactAttributes = $attributesField['present'] && (!$ignoreEmpty || trim((string)$attributesField['value']) !== '');
        $hasAttributeInput = $attributesField['present'] || !empty($individualAttributes);
        if ($attributeRule !== 'preserve' && $hasAttributeInput) {
            if ($attributeRule === 'fill_empty' && !$isNew && !empty($data['product_attribute'])) {
                // Existing attributes are retained.
            } else {
                $data['product_attribute'] = $this->mergeProductAttributes(
                    isset($data['product_attribute']) ? $data['product_attribute'] : array(),
                    $attributeRule === 'clear' ? '' : $attributesField['value'],
                    $languageId,
                    in_array($attributeRule, array('replace','clear'), true),
                    $dryRun,
                    $warnings,
                    $errors,
                    $attributeRule === 'clear' ? array() : $individualAttributes
                );
            }
        }

        $optionsField = $this->payloadValueWithPresence($payload, array('_OPTIONS_', 'options'));
        $optionsRule = $this->getFieldRule($options, array('_OPTIONS_'), 'merge');
        if ($optionsField['present'] && $optionsRule !== 'preserve') {
            if ($optionsRule === 'clear') {
                $data['product_option'] = array();
            } elseif (!($optionsRule === 'fill_empty' && !$isNew && !empty($data['product_option']))) {
                if (!($ignoreEmpty && trim((string)$optionsField['value']) === '')) {
                    $data['product_option'] = $this->mergeCompactProductOptions(
                        isset($data['product_option']) ? $data['product_option'] : array(),
                        (string)$optionsField['value'],
                        $optionsRule,
                        $payload,
                        $options,
                        $dryRun,
                        $warnings,
                        $errors
                    );
                }
            }
        }

        $googleCategoryField = $this->payloadValueWithPresence($payload, array('_GOOGLE_PRODUCT_CATEGORY_', 'google_product_category'));
        $googleCategoryRule = $this->getFieldRule($options, array('_GOOGLE_PRODUCT_CATEGORY_'), 'overwrite');
        if ($googleCategoryField['present'] && $googleCategoryRule !== 'preserve') {
            $currentGoogle = isset($data['_google_product_category']) ? (string)$data['_google_product_category'] : '';
            if ($googleCategoryRule === 'clear') {
                $data['_google_product_category'] = '';
            } elseif (!($googleCategoryRule === 'fill_empty' && trim($currentGoogle) !== '') && !($ignoreEmpty && trim((string)$googleCategoryField['value']) === '')) {
                $data['_google_product_category'] = $this->normalizeGoogleProductCategory((string)$googleCategoryField['value'], $errors);
            }
        }

        $data['product_filter'] = $this->mergeReferenceIdList(
            isset($data['product_filter']) ? $data['product_filter'] : array(),
            $payload,
            array('_FILTER_IDS_', '_PRODUCT_FILTER_IDS_', 'filter_ids'),
            'filter',
            'filter_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['product_download'] = $this->mergeReferenceIdList(
            isset($data['product_download']) ? $data['product_download'] : array(),
            $payload,
            array('_DOWNLOAD_IDS_', '_PRODUCT_DOWNLOAD_IDS_', 'download_ids'),
            'download',
            'download_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['product_related'] = $this->mergeReferenceIdList(
            isset($data['product_related']) ? $data['product_related'] : array(),
            $payload,
            array('_RELATED_PRODUCT_IDS_', '_PRODUCT_RELATED_IDS_', 'related_product_ids'),
            'product',
            'product_id',
            $ignoreEmpty,
            $warnings,
            $productId,
            $options
        );
        $data['product_related_article'] = $this->mergeReferenceIdList(
            isset($data['product_related_article']) ? $data['product_related_article'] : array(),
            $payload,
            array('_RELATED_ARTICLE_IDS_', '_ARTICLE_RELATED_IDS_', 'related_article_ids'),
            'article',
            'article_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['product_layout'] = $this->mergeLayoutMap(
            isset($data['product_layout']) ? $data['product_layout'] : array(),
            $payload,
            array('_LAYOUTS_', '_PRODUCT_LAYOUTS_', 'layouts'),
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['product_discount'] = $this->mergeCommercialCollection(
            'discount',
            isset($data['product_discount']) ? $data['product_discount'] : array(),
            $payload,
            array('_DISCOUNTS_', '_PRODUCT_DISCOUNTS_', 'discounts'),
            $ignoreEmpty,
            $warnings,
            $errors,
            $options
        );
        $data['product_special'] = $this->mergeCommercialCollection(
            'special',
            isset($data['product_special']) ? $data['product_special'] : array(),
            $payload,
            array('_SPECIALS_', '_PRODUCT_SPECIALS_', 'specials'),
            $ignoreEmpty,
            $warnings,
            $errors,
            $options
        );
        $data['product_reward'] = $this->mergeCommercialCollection(
            'reward',
            isset($data['product_reward']) ? $data['product_reward'] : array(),
            $payload,
            array('_REWARDS_', '_PRODUCT_REWARDS_', 'rewards'),
            $ignoreEmpty,
            $warnings,
            $errors,
            $options
        );
        $data['product_recurring'] = $this->mergeCommercialCollection(
            'recurring',
            isset($data['product_recurring']) ? $data['product_recurring'] : array(),
            $payload,
            array('_RECURRING_', '_PRODUCT_RECURRING_', 'recurring'),
            $ignoreEmpty,
            $warnings,
            $errors,
            $options
        );

        $data['product_seo_url'] = $this->mergeSeoUrlsFromPayload(
            isset($data['product_seo_url']) ? $data['product_seo_url'] : array(),
            $payload,
            $languageId,
            isset($data['product_store']) ? $data['product_store'] : array(0),
            $productId ? 'product_id=' . (int)$productId : '',
            $this->getFieldRule($options, array('_SEO_KEYWORD_'), (string)$this->getOption($options, 'replace_seo_url', '0') === '1' ? 'overwrite' : 'fill_empty'),
            $ignoreEmpty,
            $warnings,
            $errors
        );

        $this->validateProductLookupIds($data, $errors);
        $this->validateProductFieldRanges($data, $errors);

        if (!isset($data['product_filter'])) { $data['product_filter'] = array(); }
        if (!isset($data['product_option'])) { $data['product_option'] = array(); }
        if (!isset($data['product_discount'])) { $data['product_discount'] = array(); }
        if (!isset($data['product_special'])) { $data['product_special'] = array(); }
        if (!isset($data['product_image'])) { $data['product_image'] = array(); }
        if (!isset($data['product_download'])) { $data['product_download'] = array(); }
        if (!isset($data['product_related'])) { $data['product_related'] = array(); }
        if (!isset($data['product_related_article'])) { $data['product_related_article'] = array(); }
        if (!isset($data['product_reward'])) { $data['product_reward'] = array(); }
        if (!isset($data['product_layout'])) { $data['product_layout'] = array(); }
        if (!isset($data['product_recurring'])) { $data['product_recurring'] = array(); }

        $this->currentEntityIsNew = null;
        return $data;
    }

    private function buildCategoryData($categoryId, $payload, $options, $isNew, $dryRun, &$warnings, &$errors) {
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $ignoreEmpty = (string)$this->getOption($options, 'empty_field', '1') === '1';
        $data = $isNew ? $this->getNewCategoryDefaults($options) : $this->getExistingCategoryData($categoryId);

        $fieldMap = array(
            'parent_id' => array(array('_PARENT_ID_', 'parent_id'), 'int'),
            'top' => array(array('_TOP_', 'top'), 'bool'),
            'column' => array(array('_COLUMN_', 'column'), 'int'),
            'sort_order' => array(array('_SORT_ORDER_', 'sort_order'), 'int'),
            'status' => array(array('_STATUS_', 'status'), 'bool'),
            'noindex' => array(array('_NOINDEX_', 'noindex'), 'bool')
        );
        foreach ($fieldMap as $field => $definition) {
            $present = $this->payloadValueWithPresence($payload, $definition[0]);
            if (!$present['present']) { continue; }
            $rule = $this->getFieldRule($options, $definition[0], 'overwrite');
            if ($rule === 'preserve') { continue; }
            if ($rule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($data[$field]) ? $data[$field] : null)) { continue; }
            if ($rule === 'clear') { $data[$field] = $this->clearValueForType($definition[1]); continue; }
            if ($present['value'] === '' && $ignoreEmpty) { continue; }
            $value = $this->castValue($present['value'], $definition[1], $field, $errors);
            if ($value !== null) { $data[$field] = $value; }
        }

        $parentPath = $this->payloadValueWithPresence($payload, array('_PARENT_CATEGORY_', '_PARENT_PATH_', 'parent_category'));
        $parentRule = $this->getFieldRule($options, array('_PARENT_CATEGORY_', '_PARENT_PATH_'), 'overwrite');
        if ($parentPath['present'] && $parentRule !== 'preserve') {
            if ($parentRule === 'clear') {
                $data['parent_id'] = 0;
            } elseif (!($parentRule === 'fill_empty' && !$isNew && (int)$data['parent_id'] > 0) && trim((string)$parentPath['value']) !== '') {
                $resolved = $this->ensureCategoryPath($parentPath['value'], $options, $dryRun, $warnings, $errors);
                if ($resolved !== null) {
                    $data['parent_id'] = (int)$resolved;
                }
            }
        }

        $imageField = $this->payloadValueWithPresence($payload, array('_IMAGE_', 'image'));
        $imageRule = $this->getFieldRule($options, array('_IMAGE_', 'image'), 'overwrite');
        if ($imageField['present'] && $imageRule !== 'preserve') {
            if ($imageRule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($data['image']) ? $data['image'] : '')) {
                // Existing image is retained.
            } elseif ($imageRule === 'clear') {
                $data['image'] = '';
            } elseif (!($imageField['value'] === '' && $ignoreEmpty)) {
                $imageResult = $this->prepareImageValue($imageField['value'], $options, $dryRun, $warnings);
                if ($imageResult['ok']) { $data['image'] = $imageResult['value']; }
            }
        }

        $data['category_description'] = $this->mergeLocalizedDescriptions(
            isset($data['category_description']) ? $data['category_description'] : array(),
            $payload,
            $languageId,
            array('name', 'description', 'meta_title', 'meta_description', 'meta_keyword', 'meta_h1'),
            $ignoreEmpty,
            $isNew,
            (string)$this->getOption($options, 'copy_default_language', '0') === '1',
            $options
        );

        $selectedDescription = isset($data['category_description'][$languageId]) ? $data['category_description'][$languageId] : array();
        $selectedCategoryName = trim((string)(isset($selectedDescription['name']) ? $selectedDescription['name'] : ''));
        $categoryNameInput = $this->getLocalizedPayloadValues($payload, 'name', $languageId);
        $categoryNameRule = $this->getFieldRule($options, $this->getFieldAliases('name'), 'overwrite');
        if ($isNew && $selectedCategoryName === '') {
            $errors[] = 'Для новой категории необходимо заполнить _NAME_ или языковую колонку названия.';
        } elseif ($categoryNameRule !== 'preserve' && isset($categoryNameInput[$languageId]) && !empty($categoryNameInput[$languageId]['present']) && $selectedCategoryName === '') {
            $errors[] = 'Название категории нельзя очистить или заменить пустым значением.';
        }

        $storeField = $this->payloadValueWithPresence($payload, array('_STORE_IDS_', '_STORES_', 'store_ids'));
        $storeRule = $this->getFieldRule($options, array('_STORE_IDS_', '_STORES_'), (string)$this->getOption($options, 'replace_store_links', '0') === '1' ? 'replace' : 'overwrite');
        if ($storeRule !== 'preserve' && ($isNew || $storeField['present'] || (string)$this->getOption($options, 'replace_store_links', '0') === '1')) {
            $rawStores = $storeField['present'] ? $storeField['value'] : $this->getOption($options, 'store_ids', '0');
            $newStores = $storeRule === 'clear' ? array() : $this->normalizeStoreIds($rawStores, $warnings);
            if ($storeRule === 'fill_empty' && !$isNew && !empty($data['category_store'])) {
                // Existing store links are retained.
            } elseif ($storeRule === 'merge') {
                $data['category_store'] = array_values(array_unique(array_merge((array)$data['category_store'], $newStores)));
            } else { $data['category_store'] = $newStores; }
        }

        $data['category_filter'] = $this->mergeReferenceIdList(
            isset($data['category_filter']) ? $data['category_filter'] : array(),
            $payload,
            array('_FILTER_IDS_', '_CATEGORY_FILTER_IDS_', 'filter_ids'),
            'filter',
            'filter_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['product_related'] = $this->mergeReferenceIdList(
            isset($data['product_related']) ? $data['product_related'] : array(),
            $payload,
            array('_RELATED_PRODUCT_IDS_', '_PRODUCT_RELATED_IDS_', 'related_product_ids'),
            'product',
            'product_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['article_related'] = $this->mergeReferenceIdList(
            isset($data['article_related']) ? $data['article_related'] : array(),
            $payload,
            array('_RELATED_ARTICLE_IDS_', '_ARTICLE_RELATED_IDS_', 'related_article_ids'),
            'article',
            'article_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['category_layout'] = $this->mergeLayoutMap(
            isset($data['category_layout']) ? $data['category_layout'] : array(),
            $payload,
            array('_LAYOUTS_', '_CATEGORY_LAYOUTS_', 'layouts'),
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );

        $data['category_seo_url'] = $this->mergeSeoUrlsFromPayload(
            isset($data['category_seo_url']) ? $data['category_seo_url'] : array(),
            $payload,
            $languageId,
            isset($data['category_store']) ? $data['category_store'] : array(0),
            $categoryId ? 'category_id=' . (int)$categoryId : '',
            $this->getFieldRule($options, array('_SEO_KEYWORD_'), (string)$this->getOption($options, 'replace_seo_url', '0') === '1' ? 'overwrite' : 'fill_empty'),
            $ignoreEmpty,
            $warnings,
            $errors
        );

        if (!isset($data['category_filter'])) { $data['category_filter'] = array(); }
        if (!isset($data['category_layout'])) { $data['category_layout'] = array(); }
        if (!isset($data['product_related'])) { $data['product_related'] = array(); }
        if (!isset($data['article_related'])) { $data['article_related'] = array(); }
        $this->validateCategoryFieldRanges($data, $categoryId, $isNew, $errors);
        return $data;
    }

    private function buildManufacturerData($manufacturerId, $payload, $options, $isNew, $dryRun, &$warnings, &$errors) {
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $ignoreEmpty = (string)$this->getOption($options, 'empty_field', '1') === '1';
        $data = $isNew ? $this->getNewManufacturerDefaults($options) : $this->getExistingManufacturerData($manufacturerId);

        $manufacturerScalarMap = array(
            'name' => array(array('_NAME_', 'name'), 'string'),
            'sort_order' => array(array('_SORT_ORDER_', 'sort_order'), 'int'),
            'noindex' => array(array('_NOINDEX_', 'noindex'), 'bool')
        );
        foreach ($manufacturerScalarMap as $field => $definition) {
            $present = $this->payloadValueWithPresence($payload, $definition[0]);
            if (!$present['present']) { continue; }
            $rule = $this->getFieldRule($options, $definition[0], 'overwrite');
            if ($rule === 'preserve') { continue; }
            if ($rule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($data[$field]) ? $data[$field] : null)) { continue; }
            if ($rule === 'clear') { $data[$field] = $this->clearValueForType($definition[1]); continue; }
            if ($present['value'] === '' && $ignoreEmpty) { continue; }
            $value = $this->castValue($present['value'], $definition[1], $field, $errors);
            if ($value !== null) { $data[$field] = $field === 'name' ? trim((string)$value) : $value; }
        }
        $imageField = $this->payloadValueWithPresence($payload, array('_IMAGE_', 'image'));
        $imageRule = $this->getFieldRule($options, array('_IMAGE_', 'image'), 'overwrite');
        if ($imageField['present'] && $imageRule !== 'preserve') {
            if ($imageRule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($data['image']) ? $data['image'] : '')) {
                // Existing image is retained.
            } elseif ($imageRule === 'clear') { $data['image'] = ''; }
            elseif (!($imageField['value'] === '' && $ignoreEmpty)) {
                $imageResult = $this->prepareImageValue($imageField['value'], $options, $dryRun, $warnings);
                if ($imageResult['ok']) { $data['image'] = $imageResult['value']; }
            }
        }
        $manufacturerNameInput = $this->payloadValueWithPresence($payload, array('_NAME_', 'name'));
        $manufacturerNameRule = $this->getFieldRule($options, array('_NAME_', 'name'), 'overwrite');
        if ($isNew && trim((string)$data['name']) === '') {
            $errors[] = 'Для нового производителя необходимо заполнить _NAME_.';
        } elseif ($manufacturerNameInput['present'] && $manufacturerNameRule !== 'preserve' && trim((string)$data['name']) === '') {
            $errors[] = 'Название производителя нельзя очистить или заменить пустым значением.';
        }

        $data['manufacturer_description'] = $this->mergeLocalizedDescriptions(
            isset($data['manufacturer_description']) ? $data['manufacturer_description'] : array(),
            $payload,
            $languageId,
            array('description', 'meta_title', 'meta_description', 'meta_keyword', 'meta_h1'),
            $ignoreEmpty,
            $isNew,
            (string)$this->getOption($options, 'copy_default_language', '0') === '1',
            $options
        );

        if ($isNew && !isset($data['manufacturer_description'][$languageId])) {
            $data['manufacturer_description'][$languageId] = $this->emptyDescriptionRow(array('description', 'meta_title', 'meta_description', 'meta_keyword', 'meta_h1'));
        }
        foreach ($data['manufacturer_description'] as $descriptionLanguageId => $description) {
            if (trim((string)(isset($description['meta_title']) ? $description['meta_title'] : '')) === '') {
                $data['manufacturer_description'][$descriptionLanguageId]['meta_title'] = $data['name'];
            }
        }

        $storeField = $this->payloadValueWithPresence($payload, array('_STORE_IDS_', '_STORES_', 'store_ids'));
        $storeRule = $this->getFieldRule($options, array('_STORE_IDS_', '_STORES_'), (string)$this->getOption($options, 'replace_store_links', '0') === '1' ? 'replace' : 'overwrite');
        if ($storeRule !== 'preserve' && ($isNew || $storeField['present'] || (string)$this->getOption($options, 'replace_store_links', '0') === '1')) {
            $rawStores = $storeField['present'] ? $storeField['value'] : $this->getOption($options, 'store_ids', '0');
            $newStores = $storeRule === 'clear' ? array() : $this->normalizeStoreIds($rawStores, $warnings);
            if ($storeRule === 'fill_empty' && !$isNew && !empty($data['manufacturer_store'])) {
                // Existing store links are retained.
            } elseif ($storeRule === 'merge') {
                $data['manufacturer_store'] = array_values(array_unique(array_merge((array)$data['manufacturer_store'], $newStores)));
            } else { $data['manufacturer_store'] = $newStores; }
        }

        $data['manufacturer_layout'] = $this->mergeLayoutMap(
            isset($data['manufacturer_layout']) ? $data['manufacturer_layout'] : array(),
            $payload,
            array('_LAYOUTS_', '_MANUFACTURER_LAYOUTS_', 'layouts'),
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['product_related'] = $this->mergeReferenceIdList(
            isset($data['product_related']) ? $data['product_related'] : array(),
            $payload,
            array('_RELATED_PRODUCT_IDS_', '_PRODUCT_RELATED_IDS_', 'related_product_ids'),
            'product',
            'product_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );
        $data['article_related'] = $this->mergeReferenceIdList(
            isset($data['article_related']) ? $data['article_related'] : array(),
            $payload,
            array('_RELATED_ARTICLE_IDS_', '_ARTICLE_RELATED_IDS_', 'related_article_ids'),
            'article',
            'article_id',
            $ignoreEmpty,
            $warnings,
            0,
            $options
        );

        $data['manufacturer_seo_url'] = $this->mergeSeoUrlsFromPayload(
            isset($data['manufacturer_seo_url']) ? $data['manufacturer_seo_url'] : array(),
            $payload,
            $languageId,
            isset($data['manufacturer_store']) ? $data['manufacturer_store'] : array(0),
            $manufacturerId ? 'manufacturer_id=' . (int)$manufacturerId : '',
            $this->getFieldRule($options, array('_SEO_KEYWORD_'), (string)$this->getOption($options, 'replace_seo_url', '0') === '1' ? 'overwrite' : 'fill_empty'),
            $ignoreEmpty,
            $warnings,
            $errors
        );
        if ((int)$data['sort_order'] < 0) {
            $errors[] = 'Сортировка производителя не может быть отрицательной.';
        }
        if (!in_array((int)$data['noindex'], array(0, 1), true)) {
            $errors[] = 'Поле noindex производителя должно быть 0 или 1.';
        }
        if ($this->textLength((string)$data['name']) > 64) {
            $errors[] = 'Название производителя длиннее 64 символов.';
        }
        if (isset($data['image']) && $this->textLength((string)$data['image']) > 255) {
            $errors[] = 'Путь изображения производителя длиннее 255 символов.';
        }
        $descriptionLimits = array('meta_title' => 255, 'meta_description' => 255, 'meta_keyword' => 255, 'meta_h1' => 255);
        foreach ((array)$data['manufacturer_description'] as $descriptionLanguageId => $description) {
            foreach ($descriptionLimits as $field => $limit) {
                if (isset($description[$field]) && $this->textLength((string)$description[$field]) > $limit) {
                    $errors[] = 'Поле производителя ' . $field . ' для language_id=' . (int)$descriptionLanguageId . ' длиннее ' . $limit . ' символов.';
                }
            }
        }
        return $data;
    }

    private function validateProductLookupIds($data, &$errors) {
        $checks = array(
            'stock_status_id' => array('stock_status', 'stock_status_id', false),
            'tax_class_id' => array('tax_class', 'tax_class_id', true),
            'weight_class_id' => array('weight_class', 'weight_class_id', false),
            'length_class_id' => array('length_class', 'length_class_id', false)
        );
        foreach ($checks as $field => $definition) {
            $value = isset($data[$field]) ? (int)$data[$field] : 0;
            if ($value === 0 && $definition[2]) {
                continue;
            }
            if ($value <= 0 || !$this->recordExists($definition[0], $definition[1], $value)) {
                $errors[] = 'Указано несуществующее значение ' . $field . ': ' . $value . '.';
            }
        }
    }

    private function validateProductFieldRanges($data, &$errors) {
        if ((int)$data['quantity'] < 0) { $errors[] = 'Количество товара не может быть отрицательным.'; }
        if ((int)$data['minimum'] < 1) { $errors[] = 'Минимальное количество товара должно быть не меньше 1.'; }
        foreach (array('price' => 'Цена', 'weight' => 'Вес', 'length' => 'Длина', 'width' => 'Ширина', 'height' => 'Высота') as $field => $label) {
            if ((float)$data[$field] < 0) { $errors[] = $label . ' не может быть отрицательным значением.'; }
        }
        if ((int)$data['points'] < 0) { $errors[] = 'Баллы товара не могут быть отрицательным значением.'; }
        if ((int)$data['sort_order'] < 0) { $errors[] = 'Сортировка товара не может быть отрицательной.'; }
        if (!in_array((int)$data['status'], array(0, 1), true)) { $errors[] = 'Статус товара должен быть 0 или 1.'; }
        if (!in_array((int)$data['noindex'], array(0, 1), true)) { $errors[] = 'Поле noindex товара должно быть 0 или 1.'; }
        if (!in_array((int)$data['subtract'], array(0, 1), true)) { $errors[] = 'Поле вычитания со склада должно быть 0 или 1.'; }
        if (!in_array((int)$data['shipping'], array(0, 1), true)) { $errors[] = 'Поле необходимости доставки должно быть 0 или 1.'; }

        $limits = array('model' => 64, 'sku' => 64, 'upc' => 12, 'ean' => 14, 'jan' => 13, 'isbn' => 17, 'mpn' => 64, 'location' => 128, 'image' => 255);
        foreach ($limits as $field => $limit) {
            if (isset($data[$field]) && $this->textLength((string)$data[$field]) > $limit) {
                $errors[] = 'Поле ' . $field . ' длиннее допустимых ' . $limit . ' символов.';
            }
        }
        $descriptionLimits = array('name' => 255, 'tag' => 255, 'meta_title' => 255, 'meta_description' => 255, 'meta_keyword' => 255, 'meta_h1' => 255);
        foreach ((array)(isset($data['product_description']) ? $data['product_description'] : array()) as $languageId => $description) {
            foreach ($descriptionLimits as $field => $limit) {
                if (isset($description[$field]) && $this->textLength((string)$description[$field]) > $limit) {
                    $errors[] = 'Поле товара ' . $field . ' для language_id=' . (int)$languageId . ' длиннее ' . $limit . ' символов.';
                }
            }
        }
        foreach ((array)(isset($data['product_image']) ? $data['product_image'] : array()) as $image) {
            if (isset($image['image']) && $this->textLength((string)$image['image']) > 255) {
                $errors[] = 'Путь дополнительного изображения длиннее 255 символов.';
            }
        }
    }

    private function validateCategoryFieldRanges($data, $categoryId, $isNew, &$errors) {
        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : 0;
        if ($parentId > 0 && !$this->idExists('category', 'category_id', $parentId)) {
            $errors[] = 'Родительская категория с ID ' . $parentId . ' не существует.';
        }
        if (!$isNew && !$this->isValidCategoryParent((int)$categoryId, $parentId)) {
            $errors[] = 'Нельзя назначить категорию самой себе или создать циклическую иерархию.';
        }
        if ((int)$data['column'] < 1) { $errors[] = 'Количество колонок категории должно быть не меньше 1.'; }
        if ((int)$data['sort_order'] < 0) { $errors[] = 'Сортировка категории не может быть отрицательной.'; }
        if (!in_array((int)$data['top'], array(0, 1), true)) { $errors[] = 'Поле верхнего меню категории должно быть 0 или 1.'; }
        if (!in_array((int)$data['status'], array(0, 1), true)) { $errors[] = 'Статус категории должен быть 0 или 1.'; }
        if (!in_array((int)$data['noindex'], array(0, 1), true)) { $errors[] = 'Поле noindex категории должно быть 0 или 1.'; }
        if (isset($data['image']) && $this->textLength((string)$data['image']) > 255) { $errors[] = 'Путь изображения категории длиннее 255 символов.'; }
        $descriptionLimits = array('name' => 255, 'meta_title' => 255, 'meta_description' => 255, 'meta_keyword' => 255, 'meta_h1' => 255);
        foreach ((array)(isset($data['category_description']) ? $data['category_description'] : array()) as $languageId => $description) {
            foreach ($descriptionLimits as $field => $limit) {
                if (isset($description[$field]) && $this->textLength((string)$description[$field]) > $limit) {
                    $errors[] = 'Поле категории ' . $field . ' для language_id=' . (int)$languageId . ' длиннее ' . $limit . ' символов.';
                }
            }
        }
    }

    private function recordExists($table, $idField, $id) {
        $id = (int)$id;
        if ($id <= 0 || !preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $idField)) {
            return false;
        }
        if (!$this->tableExists($table)) {
            return false;
        }
        $query = $this->db->query("SELECT `" . $idField . "` FROM `" . DB_PREFIX . $table . "` WHERE `" . $idField . "` = '" . $id . "' LIMIT 1");
        return (bool)$query->num_rows;
    }

    private function getNewProductDefaults($options) {
        $warnings = array();
        return array(
            'model' => '', 'sku' => '', 'upc' => '', 'ean' => '', 'jan' => '', 'isbn' => '', 'mpn' => '', 'location' => '',
            'quantity' => 0,
            'minimum' => max(1, (int)$this->getOption($options, 'minimum', 1)),
            'subtract' => (int)$this->getOption($options, 'subtract', 1),
            'stock_status_id' => (int)$this->getOption($options, 'stock_status_id', $this->config->get('config_stock_status_id')),
            'date_available' => date('Y-m-d'),
            'manufacturer_id' => 0,
            'shipping' => (int)$this->getOption($options, 'shipping', 1),
            'price' => 0,
            'points' => 0,
            'tax_class_id' => 0,
            'weight' => 0,
            'weight_class_id' => (int)$this->getOption($options, 'weight_class_id', $this->config->get('config_weight_class_id')),
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'length_class_id' => (int)$this->getOption($options, 'length_class_id', $this->config->get('config_length_class_id')),
            'status' => (int)$this->getOption($options, 'status', 0),
            'noindex' => (int)$this->getOption($options, 'noindex', 0),
            'sort_order' => (int)$this->getOption($options, 'sort_order', 0),
            'image' => '',
            'product_description' => array(),
            'product_store' => $this->normalizeStoreIds($this->getOption($options, 'store_ids', '0'), $warnings),
            'product_attribute' => array(),
            'product_option' => array(),
            'product_discount' => array(),
            'product_special' => array(),
            'product_image' => array(),
            'product_download' => array(),
            'product_category' => array(),
            'product_filter' => array(),
            'product_related' => array(),
            'product_related_article' => array(),
            'product_reward' => array(),
            'product_layout' => array(),
            'product_recurring' => array(),
            'product_seo_url' => array(),
            '_main_category_id' => 0
        );
    }

    private function getExistingProductData($productId) {
        $rowQuery = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$productId . "' LIMIT 1");
        if (!$rowQuery->num_rows) {
            return $this->getNewProductDefaults(array());
        }
        $data = $rowQuery->row;
        if (!isset($data['noindex'])) { $data['noindex'] = 0; }

        $data['product_description'] = array();
        foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
            $languageId = (int)$row['language_id'];
            unset($row['product_id'], $row['language_id']);
            $data['product_description'][$languageId] = $row;
        }

        $data['product_store'] = array();
        foreach ($this->db->query("SELECT store_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
            $data['product_store'][] = (int)$row['store_id'];
        }

        $data['product_category'] = array();
        foreach ($this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$productId . "' ORDER BY category_id")->rows as $row) {
            $data['product_category'][] = (int)$row['category_id'];
        }
        $data['_main_category_id'] = $this->getExistingMainCategoryId($productId);

        $data['product_filter'] = array();
        if ($this->tableExists('product_filter')) {
            foreach ($this->db->query("SELECT filter_id FROM `" . DB_PREFIX . "product_filter` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_filter'][] = (int)$row['filter_id'];
            }
        }

        $data['product_attribute'] = array();
        $attributeRows = $this->db->query("SELECT attribute_id, language_id, text FROM `" . DB_PREFIX . "product_attribute` WHERE product_id = '" . (int)$productId . "' ORDER BY attribute_id, language_id")->rows;
        $attributeMap = array();
        foreach ($attributeRows as $row) {
            $attributeId = (int)$row['attribute_id'];
            if (!isset($attributeMap[$attributeId])) {
                $attributeMap[$attributeId] = array('attribute_id' => $attributeId, 'product_attribute_description' => array());
            }
            $attributeMap[$attributeId]['product_attribute_description'][(int)$row['language_id']] = array('text' => $row['text']);
        }
        $data['product_attribute'] = array_values($attributeMap);

        $data['product_option'] = array();
        $optionRows = $this->db->query("SELECT po.*, o.type FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) WHERE po.product_id = '" . (int)$productId . "' ORDER BY po.product_option_id")->rows;
        foreach ($optionRows as $optionRow) {
            $option = array(
                'product_option_id' => (int)$optionRow['product_option_id'],
                'option_id' => (int)$optionRow['option_id'],
                'value' => isset($optionRow['value']) ? $optionRow['value'] : '',
                'required' => (int)$optionRow['required'],
                'type' => isset($optionRow['type']) ? $optionRow['type'] : '',
                'product_option_value' => array()
            );
            foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_id = '" . (int)$optionRow['product_option_id'] . "' ORDER BY product_option_value_id")->rows as $valueRow) {
                $option['product_option_value'][] = array(
                    'product_option_value_id' => (int)$valueRow['product_option_value_id'],
                    'option_value_id' => (int)$valueRow['option_value_id'],
                    'quantity' => (int)$valueRow['quantity'],
                    'subtract' => (int)$valueRow['subtract'],
                    'price' => $valueRow['price'],
                    'price_prefix' => $valueRow['price_prefix'],
                    'points' => (int)$valueRow['points'],
                    'points_prefix' => $valueRow['points_prefix'],
                    'weight' => $valueRow['weight'],
                    'weight_prefix' => $valueRow['weight_prefix']
                );
            }
            $data['product_option'][] = $option;
        }

        $data['product_discount'] = array();
        if ($this->tableExists('product_discount')) {
            foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int)$productId . "' ORDER BY quantity, priority, price")->rows as $row) {
                unset($row['product_discount_id'], $row['product_id']);
                $data['product_discount'][] = $row;
            }
        }

        $data['product_special'] = array();
        if ($this->tableExists('product_special')) {
            foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int)$productId . "' ORDER BY priority, price")->rows as $row) {
                unset($row['product_special_id'], $row['product_id']);
                $data['product_special'][] = $row;
            }
        }

        $data['product_image'] = array();
        foreach ($this->db->query("SELECT image, sort_order FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$productId . "' ORDER BY sort_order, product_image_id")->rows as $row) {
            $data['product_image'][] = array('image' => $row['image'], 'sort_order' => (int)$row['sort_order']);
        }

        $data['product_download'] = array();
        if ($this->tableExists('product_to_download')) {
            foreach ($this->db->query("SELECT download_id FROM `" . DB_PREFIX . "product_to_download` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_download'][] = (int)$row['download_id'];
            }
        }

        $data['product_related'] = array();
        if ($this->tableExists('product_related')) {
            foreach ($this->db->query("SELECT related_id FROM `" . DB_PREFIX . "product_related` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_related'][] = (int)$row['related_id'];
            }
        }

        $data['product_related_article'] = array();
        if ($this->tableExists('product_related_article')) {
            foreach ($this->db->query("SELECT article_id FROM `" . DB_PREFIX . "product_related_article` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_related_article'][] = (int)$row['article_id'];
            }
        }

        $data['product_reward'] = array();
        if ($this->tableExists('product_reward')) {
            foreach ($this->db->query("SELECT customer_group_id, points FROM `" . DB_PREFIX . "product_reward` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_reward'][(int)$row['customer_group_id']] = array('points' => (int)$row['points']);
            }
        }

        $data['product_layout'] = array();
        if ($this->tableExists('product_to_layout')) {
            foreach ($this->db->query("SELECT store_id, layout_id FROM `" . DB_PREFIX . "product_to_layout` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_layout'][(int)$row['store_id']] = (int)$row['layout_id'];
            }
        }

        $data['product_recurring'] = array();
        if ($this->tableExists('product_recurring')) {
            foreach ($this->db->query("SELECT recurring_id, customer_group_id FROM `" . DB_PREFIX . "product_recurring` WHERE product_id = '" . (int)$productId . "'")->rows as $row) {
                $data['product_recurring'][] = array('recurring_id' => (int)$row['recurring_id'], 'customer_group_id' => (int)$row['customer_group_id']);
            }
        }

        $data['product_seo_url'] = $this->getSeoUrls('product_id=' . (int)$productId);
        return $data;
    }

    private function getProductCommercialData($productId) {
        $productId = (int)$productId;
        $data = array('product_discount'=>array(),'product_special'=>array(),'product_reward'=>array(),'product_recurring'=>array());
        if ($productId <= 0) { return $data; }
        if ($this->tableExists('product_discount')) {
            foreach ($this->db->query("SELECT customer_group_id, quantity, priority, price, date_start, date_end FROM `" . DB_PREFIX . "product_discount` WHERE product_id='" . $productId . "' ORDER BY quantity, priority, price")->rows as $row) {
                $data['product_discount'][] = $row;
            }
        }
        if ($this->tableExists('product_special')) {
            foreach ($this->db->query("SELECT customer_group_id, priority, price, date_start, date_end FROM `" . DB_PREFIX . "product_special` WHERE product_id='" . $productId . "' ORDER BY priority, price")->rows as $row) {
                $data['product_special'][] = $row;
            }
        }
        if ($this->tableExists('product_reward')) {
            foreach ($this->db->query("SELECT customer_group_id, points FROM `" . DB_PREFIX . "product_reward` WHERE product_id='" . $productId . "' ORDER BY customer_group_id")->rows as $row) {
                $data['product_reward'][(int)$row['customer_group_id']] = array('points'=>(int)$row['points']);
            }
        }
        if ($this->tableExists('product_recurring')) {
            foreach ($this->db->query("SELECT recurring_id, customer_group_id FROM `" . DB_PREFIX . "product_recurring` WHERE product_id='" . $productId . "' ORDER BY recurring_id, customer_group_id")->rows as $row) {
                $data['product_recurring'][] = array('recurring_id'=>(int)$row['recurring_id'],'customer_group_id'=>(int)$row['customer_group_id']);
            }
        }
        return $data;
    }

    private function getNewCategoryDefaults($options) {
        $warnings = array();
        return array(
            'image' => '',
            'parent_id' => 0,
            'top' => (int)$this->getOption($options, 'top', 1),
            'column' => max(1, (int)$this->getOption($options, 'column', 1)),
            'sort_order' => (int)$this->getOption($options, 'sort_order', 0),
            'status' => (int)$this->getOption($options, 'status', 1),
            'noindex' => (int)$this->getOption($options, 'noindex', 0),
            'category_description' => array(),
            'category_filter' => array(),
            'category_store' => $this->normalizeStoreIds($this->getOption($options, 'store_ids', '0'), $warnings),
            'category_layout' => array(),
            'category_seo_url' => array(),
            'product_related' => array(),
            'article_related' => array()
        );
    }

    private function getExistingCategoryData($categoryId) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category` WHERE category_id = '" . (int)$categoryId . "' LIMIT 1");
        if (!$query->num_rows) {
            return $this->getNewCategoryDefaults(array());
        }
        $data = $query->row;
        if (!isset($data['noindex'])) { $data['noindex'] = 0; }
        $data['category_description'] = array();
        foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "category_description` WHERE category_id = '" . (int)$categoryId . "'")->rows as $row) {
            $languageId = (int)$row['language_id'];
            unset($row['category_id'], $row['language_id']);
            $data['category_description'][$languageId] = $row;
        }
        $data['category_filter'] = array();
        if ($this->tableExists('category_filter')) {
            foreach ($this->db->query("SELECT filter_id FROM `" . DB_PREFIX . "category_filter` WHERE category_id = '" . (int)$categoryId . "'")->rows as $row) {
                $data['category_filter'][] = (int)$row['filter_id'];
            }
        }
        $data['category_store'] = array();
        foreach ($this->db->query("SELECT store_id FROM `" . DB_PREFIX . "category_to_store` WHERE category_id = '" . (int)$categoryId . "'")->rows as $row) {
            $data['category_store'][] = (int)$row['store_id'];
        }
        $data['category_layout'] = array();
        if ($this->tableExists('category_to_layout')) {
            foreach ($this->db->query("SELECT store_id, layout_id FROM `" . DB_PREFIX . "category_to_layout` WHERE category_id = '" . (int)$categoryId . "'")->rows as $row) {
                $data['category_layout'][(int)$row['store_id']] = (int)$row['layout_id'];
            }
        }
        $data['category_seo_url'] = $this->getSeoUrls('category_id=' . (int)$categoryId);
        $data['product_related'] = array();
        if ($this->tableExists('product_related_wb')) {
            foreach ($this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_related_wb` WHERE category_id='" . (int)$categoryId . "' ORDER BY product_id")->rows as $row) {
                $data['product_related'][] = (int)$row['product_id'];
            }
        }
        $data['article_related'] = array();
        if ($this->tableExists('article_related_wb')) {
            foreach ($this->db->query("SELECT article_id FROM `" . DB_PREFIX . "article_related_wb` WHERE category_id='" . (int)$categoryId . "' ORDER BY article_id")->rows as $row) {
                $data['article_related'][] = (int)$row['article_id'];
            }
        }
        return $data;
    }

    private function getNewManufacturerDefaults($options) {
        $warnings = array();
        return array(
            'name' => '',
            'image' => '',
            'sort_order' => (int)$this->getOption($options, 'sort_order', 0),
            'noindex' => (int)$this->getOption($options, 'noindex', 0),
            'manufacturer_description' => array(),
            'manufacturer_store' => $this->normalizeStoreIds($this->getOption($options, 'store_ids', '0'), $warnings),
            'manufacturer_layout' => array(),
            'manufacturer_seo_url' => array(),
            'product_related' => array(),
            'article_related' => array()
        );
    }

    private function getExistingManufacturerData($manufacturerId) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . (int)$manufacturerId . "' LIMIT 1");
        if (!$query->num_rows) {
            return $this->getNewManufacturerDefaults(array());
        }
        $data = $query->row;
        if (!isset($data['noindex'])) { $data['noindex'] = 0; }
        $data['manufacturer_description'] = array();
        if ($this->tableExists('manufacturer_description')) {
            foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "manufacturer_description` WHERE manufacturer_id='" . (int)$manufacturerId . "'")->rows as $row) {
                $languageId = (int)$row['language_id'];
                unset($row['manufacturer_id'], $row['language_id']);
                $data['manufacturer_description'][$languageId] = $row;
            }
        }
        $data['manufacturer_store'] = array();
        foreach ($this->db->query("SELECT store_id FROM `" . DB_PREFIX . "manufacturer_to_store` WHERE manufacturer_id = '" . (int)$manufacturerId . "'")->rows as $row) {
            $data['manufacturer_store'][] = (int)$row['store_id'];
        }
        $data['manufacturer_layout'] = array();
        if ($this->tableExists('manufacturer_to_layout')) {
            foreach ($this->db->query("SELECT store_id, layout_id FROM `" . DB_PREFIX . "manufacturer_to_layout` WHERE manufacturer_id='" . (int)$manufacturerId . "'")->rows as $row) {
                $data['manufacturer_layout'][(int)$row['store_id']] = (int)$row['layout_id'];
            }
        }
        $data['manufacturer_seo_url'] = $this->getSeoUrls('manufacturer_id=' . (int)$manufacturerId);
        $data['product_related'] = array();
        if ($this->tableExists('product_related_mn')) {
            foreach ($this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_related_mn` WHERE manufacturer_id='" . (int)$manufacturerId . "' ORDER BY product_id")->rows as $row) {
                $data['product_related'][] = (int)$row['product_id'];
            }
        }
        $data['article_related'] = array();
        if ($this->tableExists('article_related_mn')) {
            foreach ($this->db->query("SELECT article_id FROM `" . DB_PREFIX . "article_related_mn` WHERE manufacturer_id='" . (int)$manufacturerId . "' ORDER BY article_id")->rows as $row) {
                $data['article_related'][] = (int)$row['article_id'];
            }
        }
        return $data;
    }

    private function importOption($queueRow, $payload, $options, $mode, $dryRun) {
        $warnings = array();
        $errors = array();
        $productId = (int)$queueRow['entity_id'];
        if (!$productId || !$this->idExists('product', 'product_id', $productId)) {
            $match = $this->findProductMatchForOptionKey($queueRow['key_field'], $queueRow['key_value'], $options);
            if ($match['error'] !== '') {
                return $this->result('error', 'skip', 0, $match['error'], $warnings);
            }
            $productId = (int)$match['id'];
        }
        if (!$productId) {
            return $this->result('error', 'skip', 0, 'Товар для опции не найден. Укажите _PRODUCT_ID_, _PRODUCT_MODEL_, _PRODUCT_SKU_ или _PRODUCT_NAME_.', $warnings);
        }

        $optionIdField = $this->payloadValueWithPresence($payload, array('_OPTION_ID_', 'option_id'));
        $optionNameField = $this->payloadValueWithPresence($payload, array('_OPTION_', '_OPTION_NAME_', 'option', 'option_name'));
        $typeField = $this->payloadValueWithPresence($payload, array('_OPTION_TYPE_', 'option_type'));
        $sortField = $this->payloadValueWithPresence($payload, array('_OPTION_SORT_ORDER_', 'option_sort_order'));
        $requiredField = $this->payloadValueWithPresence($payload, array('_OPTION_REQUIRED_', '_REQUIRED_', 'required'));
        $valueField = $this->payloadValueWithPresence($payload, array('_VALUE_', '_OPTION_TEXT_', '_OPTION_DEFAULT_VALUE_', '_OPTION_VALUE_', 'value'));

        if ((int)$optionIdField['value'] <= 0 && trim((string)$optionNameField['value']) === '') {
            return $this->result('error', 'skip', $productId, 'Не указана глобальная опция: нужна колонка _OPTION_ или _OPTION_ID_.', $warnings);
        }

        $optionType = $typeField['present'] && trim((string)$typeField['value']) !== '' ? strtolower(trim((string)$typeField['value'])) : strtolower((string)$this->getOption($options, 'option_type', 'select'));
        if (!$this->isValidOptionType($optionType)) {
            $errors[] = 'Недопустимый тип опции: ' . $optionType . '.';
        }
        $optionSortOrder = 0;
        if ($sortField['present'] && trim((string)$sortField['value']) !== '') {
            $parsedSort = $this->castValue($sortField['value'], 'int', 'option_sort_order', $errors);
            if ($parsedSort !== null) { $optionSortOrder = $parsedSort; }
        }
        if ($optionSortOrder < 0) { $errors[] = 'Сортировка глобальной опции не может быть отрицательной.'; }
        $requiredValue = null;
        if ($requiredField['present']) {
            $requiredValue = $this->castValue($requiredField['value'], 'bool', 'option_required', $errors);
        }
        if (trim((string)$optionNameField['value']) !== '' && $this->textLength((string)$optionNameField['value']) > 128) {
            $errors[] = 'Название опции длиннее 128 символов.';
        }

        $global = $this->resolveGlobalOption(
            (int)$optionIdField['value'],
            trim((string)$optionNameField['value']),
            $optionType,
            $optionSortOrder,
            $payload,
            $options,
            $mode,
            $dryRun,
            $warnings,
            $errors
        );
        if ($errors) {
            return $this->result('error', 'skip', $productId, implode(' ', $errors), $warnings);
        }
        if (!$global['exists'] && !$global['planned_create']) {
            return $this->result('skipped', 'skip', $productId, 'Глобальная опция не найдена, а выбранный режим не разрешает её создание.', $warnings);
        }

        $optionId = (int)$global['option_id'];
        $actualType = $global['type'];
        $choiceTypes = array('select', 'radio', 'checkbox', 'image');
        $isChoice = in_array($actualType, $choiceTypes, true);
        $entries = $isChoice ? $this->extractOptionValueEntries($payload, $options, $warnings) : array();
        if ($isChoice) {
            $this->validateOptionEntries($entries, $errors);
        }
        if ($errors) {
            return $this->result('error', 'skip', $productId, implode(' ', $errors), $warnings);
        }
        if ($isChoice && !$entries && $mode !== 'delete') {
            return $this->result('error', 'skip', $productId, 'Для типа ' . $actualType . ' необходима колонка _OPTION_VALUE_ или _OPTION_VALUES_.', $warnings);
        }

        $productData = $this->getExistingProductData($productId);
        $productOptionIndex = $this->findProductOptionIndex($productData['product_option'], $optionId);
        $productOptionExists = $productOptionIndex !== null;

        if ($mode === 'update' && !$productOptionExists) {
            return $this->result('skipped', 'skip', $productId, 'У товара нет такой опции, а выбран режим только обновления.', $warnings);
        }

        if ($mode === 'delete') {
            if (!$productOptionExists) {
                return $this->result('skipped', 'delete', $productId, 'У товара нет такой опции, удалять нечего.', $warnings);
            }
            if ($entries) {
                $removed = 0;
                foreach ($entries as $entry) {
                    $optionValue = $this->findExistingOptionValue($optionId, (int)$entry['option_value_id'], $entry['name'], $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id'))));
                    if (is_array($optionValue) && !empty($optionValue['_ambiguous'])) {
                        return $this->result('error', 'skip', $productId, 'Найдено несколько значений опции с одинаковым названием: ' . $entry['name'] . '. Используйте _OPTION_VALUE_ID_.', $warnings);
                    }
                    if (!$optionValue) { continue; }
                    foreach ($productData['product_option'][$productOptionIndex]['product_option_value'] as $index => $productValue) {
                        if ((int)$productValue['option_value_id'] === (int)$optionValue['option_value_id']) {
                            unset($productData['product_option'][$productOptionIndex]['product_option_value'][$index]);
                            $removed++;
                        }
                    }
                }
                $productData['product_option'][$productOptionIndex]['product_option_value'] = array_values($productData['product_option'][$productOptionIndex]['product_option_value']);
                if (!$removed) {
                    return $this->result('skipped', 'delete', $productId, 'Указанные значения не найдены у опции товара.', $warnings);
                }
                $message = 'Удалено значений опции товара: ' . $removed . '.';
            } else {
                unset($productData['product_option'][$productOptionIndex]);
                $productData['product_option'] = array_values($productData['product_option']);
                $message = 'Опция товара удалена полностью.';
            }

            if ($dryRun) {
                return $this->result('dry_run', 'delete', $productId, 'Тестовый режим: ' . $message, $warnings);
            }
            $this->load->model('catalog/product');
            $this->model_catalog_product->editProduct($productId, $productData);
            return $this->result('deleted', 'delete', $productId, $message, $warnings);
        }

        if (!$productOptionExists) {
            $productOption = array(
                'option_id' => $optionId,
                'value' => $valueField['present'] ? (string)$valueField['value'] : '',
                'required' => $requiredValue !== null ? (int)$requiredValue : (int)$this->getOption($options, 'option_required', 0),
                'product_option_value' => array()
            );
            $productData['product_option'][] = $productOption;
            $productOptionIndex = count($productData['product_option']) - 1;
            $createdOptionLink = true;
        } else {
            $createdOptionLink = false;
            $valueRule = $this->getFieldRule($options, array('_VALUE_', '_OPTION_TEXT_', '_OPTION_DEFAULT_VALUE_'), 'overwrite');
            if ($valueField['present'] && $valueRule !== 'preserve') {
                $currentValue = isset($productData['product_option'][$productOptionIndex]['value']) ? $productData['product_option'][$productOptionIndex]['value'] : '';
                if ($valueRule === 'clear') {
                    $productData['product_option'][$productOptionIndex]['value'] = '';
                } elseif (!($valueRule === 'fill_empty' && !$this->isStoredValueEmpty($currentValue))) {
                    $productData['product_option'][$productOptionIndex]['value'] = (string)$valueField['value'];
                }
            }
            $requiredRule = $this->getFieldRule($options, array('_OPTION_REQUIRED_', '_REQUIRED_'), 'overwrite');
            if ($requiredValue !== null && $requiredRule !== 'preserve') {
                $currentRequired = isset($productData['product_option'][$productOptionIndex]['required']) ? (int)$productData['product_option'][$productOptionIndex]['required'] : 0;
                if ($requiredRule === 'clear') {
                    $productData['product_option'][$productOptionIndex]['required'] = 0;
                } elseif (!($requiredRule === 'fill_empty' && $currentRequired !== 0)) {
                    $productData['product_option'][$productOptionIndex]['required'] = (int)$requiredValue;
                }
            }
        }

        $createdValues = 0;
        $updatedValues = 0;
        if ($isChoice) {
            foreach ($entries as $entry) {
                $globalValue = $this->resolveGlobalOptionValue($optionId, $entry, $payload, $options, $mode, $dryRun, $warnings, $errors);
                if ($errors) { break; }
                if (!$globalValue['exists'] && !$globalValue['planned_create']) {
                    $warnings[] = 'Значение опции не найдено и не создано: ' . $entry['name'] . '.';
                    continue;
                }
                $optionValueId = (int)$globalValue['option_value_id'];
                $existingIndex = $optionValueId > 0 ? $this->findProductOptionValueIndex($productData['product_option'][$productOptionIndex]['product_option_value'], $optionValueId) : null;

                if ($mode === 'update' && $existingIndex === null) {
                    $warnings[] = 'Значение не привязано к товару и пропущено в режиме обновления: ' . $entry['name'] . '.';
                    continue;
                }
                if ($mode === 'add' && $existingIndex !== null) {
                    $warnings[] = 'Значение уже есть у товара и пропущено в режиме добавления: ' . $entry['name'] . '.';
                    continue;
                }

                if ($existingIndex === null) {
                    $productData['product_option'][$productOptionIndex]['product_option_value'][] = $this->newProductOptionValueData($optionValueId, $entry, $options);
                    $createdValues++;
                } else {
                    $productData['product_option'][$productOptionIndex]['product_option_value'][$existingIndex] = $this->mergeProductOptionValueData(
                        $productData['product_option'][$productOptionIndex]['product_option_value'][$existingIndex],
                        $entry,
                        $options
                    );
                    $updatedValues++;
                }
            }
        }

        if ($errors) {
            return $this->result('error', 'skip', $productId, implode(' ', $errors), $warnings);
        }
        if ($mode === 'add' && $productOptionExists && !$createdValues) {
            return $this->result('skipped', 'skip', $productId, 'Новых значений для добавления не найдено.', $warnings);
        }

        $message = $createdOptionLink ? 'Опция товара будет создана.' : 'Опция товара будет обновлена.';
        if ($isChoice) {
            $message .= ' Добавлено значений: ' . $createdValues . ', обновлено: ' . $updatedValues . '.';
        }
        if ($dryRun) {
            return $this->result('dry_run', $createdOptionLink ? 'create' : 'update', $productId, 'Тестовый режим: ' . $message, $warnings);
        }

        $this->load->model('catalog/product');
        $this->model_catalog_product->editProduct($productId, $productData);
        $status = $createdOptionLink || $createdValues ? 'created' : 'updated';
        return $this->result($status, $createdOptionLink ? 'create' : 'update', $productId, str_replace('будет ', '', $message), $warnings);
    }

    private function resolveGlobalOption($optionId, $name, $type, $sortOrder, $payload, $options, $mode, $dryRun, &$warnings, &$errors) {
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $existing = false;
        if ($optionId > 0) {
            $existing = $this->getOptionDefinition($optionId);
            if (!$existing) {
                $warnings[] = 'Глобальная опция с ID ' . $optionId . ' не найдена.';
            }
        }
        if (!$existing && $name !== '') {
            $query = $this->db->query("SELECT o.option_id FROM `" . DB_PREFIX . "option` o INNER JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id=o.option_id) WHERE od.language_id='" . (int)$languageId . "' AND od.name='" . $this->db->escape($name) . "' LIMIT 2");
            if ($query->num_rows > 1) {
                $errors[] = 'Найдено несколько глобальных опций с одинаковым названием: ' . $name . '. Используйте _OPTION_ID_.';
                return array('exists' => false, 'planned_create' => false, 'option_id' => 0, 'type' => $type);
            }
            if ($query->num_rows === 1) {
                $existing = $this->getOptionDefinition((int)$query->row['option_id']);
            }
        }

        $allowCreate = (string)$this->getOption($options, 'create_global_options', '1') === '1' && !in_array($mode, array('update', 'delete'), true);
        if (!$existing) {
            if (!$allowCreate) {
                return array('exists' => false, 'planned_create' => false, 'option_id' => 0, 'type' => $type);
            }
            if ($dryRun) {
                return array('exists' => false, 'planned_create' => true, 'option_id' => 0, 'type' => $type);
            }
            $optionData = $this->newOptionDefinitionData($name, $type, $sortOrder, $payload, $languageId, $options);
            $this->load->model('catalog/option');
            $createdId = $this->model_catalog_option->addOption($optionData);
            $optionId = (int)$createdId;
            if (!$optionId) { $optionId = (int)$this->db->getLastId(); }
            return array('exists' => true, 'planned_create' => false, 'option_id' => $optionId, 'type' => $type);
        }

        $optionId = (int)$existing['option_id'];
        $actualType = $existing['type'];
        $updateDefinitions = (string)$this->getOption($options, 'update_option_definitions', '0') === '1';
        $localizedNames = $this->getLocalizedPayloadValues($payload, 'option', $languageId);
        $hasDefinitionChange = $updateDefinitions && ($localizedNames || $this->payloadHasAny($payload, array('_OPTION_NAME_', '_OPTION_TYPE_', '_OPTION_SORT_ORDER_')));
        if ($hasDefinitionChange) {
            $updated = $existing;
            if ($this->payloadHasAny($payload, array('_OPTION_TYPE_'))) {
                if (!$this->isValidOptionType($type)) { $errors[] = 'Недопустимый тип опции: ' . $type . '.'; }
                else { $updated['type'] = $type; $actualType = $type; }
            }
            if ($this->payloadHasAny($payload, array('_OPTION_SORT_ORDER_'))) {
                $updated['sort_order'] = $sortOrder;
            }
            $updated['option_description'] = $this->mergeSimpleLocalizedNames($updated['option_description'], $localizedNames, $name, $languageId);
            if (!$dryRun && !$errors) {
                $this->load->model('catalog/option');
                $this->model_catalog_option->editOption($optionId, $updated);
            }
        }
        return array('exists' => true, 'planned_create' => false, 'option_id' => $optionId, 'type' => $actualType);
    }

    private function resolveGlobalOptionValue($optionId, $entry, $payload, $options, $mode, $dryRun, &$warnings, &$errors) {
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $existing = $this->findExistingOptionValue($optionId, (int)$entry['option_value_id'], $entry['name'], $languageId);
        if (is_array($existing) && !empty($existing['_ambiguous'])) {
            $errors[] = 'Найдено несколько значений опции с одинаковым названием: ' . $entry['name'] . '. Используйте _OPTION_VALUE_ID_.';
            return array('exists' => false, 'planned_create' => false, 'option_value_id' => 0);
        }
        $allowCreate = (string)$this->getOption($options, 'create_option_values', '1') === '1' && $mode !== 'update';
        if (!$existing) {
            if (!$allowCreate) {
                return array('exists' => false, 'planned_create' => false, 'option_value_id' => 0);
            }
            if ($dryRun) {
                return array('exists' => false, 'planned_create' => true, 'option_value_id' => 0);
            }
            $definition = $this->getOptionDefinition($optionId);
            if (!$definition) {
                $errors[] = 'Не удалось загрузить глобальную опцию для создания значения.';
                return array('exists' => false, 'planned_create' => false, 'option_value_id' => 0);
            }
            $definition['option_value'][] = $this->newGlobalOptionValueData($entry, $languageId, $options, $warnings);
            $this->load->model('catalog/option');
            $this->model_catalog_option->editOption($optionId, $definition);
            $existing = $this->findExistingOptionValue($optionId, 0, $entry['name'], $languageId);
            if (is_array($existing) && !empty($existing['_ambiguous'])) {
                $errors[] = 'После создания найдено несколько одинаковых значений опции: ' . $entry['name'] . '.';
                return array('exists' => false, 'planned_create' => false, 'option_value_id' => 0);
            }
            if (!$existing) {
                $errors[] = 'OpenCart не вернул созданное значение опции: ' . $entry['name'] . '.';
                return array('exists' => false, 'planned_create' => false, 'option_value_id' => 0);
            }
            return array('exists' => true, 'planned_create' => false, 'option_value_id' => (int)$existing['option_value_id']);
        }

        $updateDefinitions = (string)$this->getOption($options, 'update_option_definitions', '0') === '1';
        $definitionFieldsPresent = !empty($entry['_present']['name']) || !empty($entry['_present']['image']) || !empty($entry['_present']['sort_order']);
        if ($updateDefinitions && $definitionFieldsPresent) {
            $definition = $this->getOptionDefinition($optionId);
            if ($definition) {
                foreach ($definition['option_value'] as $index => $value) {
                    if ((int)$value['option_value_id'] !== (int)$existing['option_value_id']) { continue; }
                    if (!empty($entry['_present']['image'])) {
                        $imageResult = $this->prepareImageValue($entry['image'], $options, $dryRun, $warnings);
                        if ($imageResult['ok']) { $definition['option_value'][$index]['image'] = $imageResult['value']; }
                    }
                    if (!empty($entry['_present']['sort_order'])) {
                        $definition['option_value'][$index]['sort_order'] = (int)$entry['sort_order'];
                    }
                    if (!empty($entry['_present']['name']) && $entry['name'] !== '') {
                        $definition['option_value'][$index]['option_value_description'][$languageId] = array('name' => $entry['name']);
                    }
                    break;
                }
                if (!$dryRun) {
                    $this->load->model('catalog/option');
                    $this->model_catalog_option->editOption($optionId, $definition);
                }
            }
        }
        return array('exists' => true, 'planned_create' => false, 'option_value_id' => (int)$existing['option_value_id']);
    }

    private function getOptionDefinition($optionId) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "option` WHERE option_id = '" . (int)$optionId . "' LIMIT 1");
        if (!$query->num_rows) { return false; }
        $data = $query->row;
        $data['option_description'] = array();
        foreach ($this->db->query("SELECT language_id, name FROM `" . DB_PREFIX . "option_description` WHERE option_id = '" . (int)$optionId . "'")->rows as $row) {
            $data['option_description'][(int)$row['language_id']] = array('name' => $row['name']);
        }
        $data['option_value'] = array();
        foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "option_value` WHERE option_id = '" . (int)$optionId . "' ORDER BY sort_order, option_value_id")->rows as $valueRow) {
            $value = array(
                'option_value_id' => (int)$valueRow['option_value_id'],
                'image' => $valueRow['image'],
                'sort_order' => (int)$valueRow['sort_order'],
                'option_value_description' => array()
            );
            foreach ($this->db->query("SELECT language_id, name FROM `" . DB_PREFIX . "option_value_description` WHERE option_value_id = '" . (int)$valueRow['option_value_id'] . "'")->rows as $description) {
                $value['option_value_description'][(int)$description['language_id']] = array('name' => $description['name']);
            }
            $data['option_value'][] = $value;
        }
        return $data;
    }

    private function newOptionDefinitionData($name, $type, $sortOrder, $payload, $languageId, $options) {
        $localizedNames = $this->getLocalizedPayloadValues($payload, 'option', $languageId);
        $descriptions = $this->mergeSimpleLocalizedNames(array(), $localizedNames, $name, $languageId);
        if ((string)$this->getOption($options, 'copy_default_language', '0') === '1') {
            foreach ($this->getLanguages() as $id => $language) {
                if (!isset($descriptions[$id])) {
                    $descriptions[$id] = array('name' => $name);
                }
            }
        }
        return array(
            'type' => $type,
            'sort_order' => (int)$sortOrder,
            'option_description' => $descriptions,
            'option_value' => array()
        );
    }

    private function newGlobalOptionValueData($entry, $languageId, $options, &$warnings) {
        $image = '';
        if (!empty($entry['_present']['image'])) {
            $imageResult = $this->prepareImageValue($entry['image'], $options, false, $warnings);
            if ($imageResult['ok']) { $image = $imageResult['value']; }
        }
        $descriptions = array($languageId => array('name' => $entry['name']));
        if ((string)$this->getOption($options, 'copy_default_language', '0') === '1') {
            foreach ($this->getLanguages() as $id => $language) {
                if (!isset($descriptions[$id])) {
                    $descriptions[$id] = array('name' => $entry['name']);
                }
            }
        }
        return array(
            'option_value_id' => 0,
            'image' => $image,
            'sort_order' => !empty($entry['_present']['sort_order']) ? (int)$entry['sort_order'] : 0,
            'option_value_description' => $descriptions
        );
    }

    private function mergeSimpleLocalizedNames($existing, $localizedNames, $fallbackName, $languageId) {
        if (!is_array($existing)) { $existing = array(); }
        foreach ($localizedNames as $id => $value) {
            if ($value['present']) {
                $existing[(int)$id] = array('name' => (string)$value['value']);
            }
        }
        if ($fallbackName !== '' && !isset($existing[$languageId])) {
            $existing[$languageId] = array('name' => $fallbackName);
        }
        return $existing;
    }

    private function findExistingOptionValue($optionId, $optionValueId, $name, $languageId) {
        if ($optionValueId > 0) {
            $query = $this->db->query("SELECT option_value_id, option_id, image, sort_order FROM `" . DB_PREFIX . "option_value` WHERE option_value_id = '" . (int)$optionValueId . "' AND option_id = '" . (int)$optionId . "' LIMIT 1");
            return $query->num_rows ? $query->row : false;
        }
        $name = trim((string)$name);
        if ($name === '') { return false; }
        $query = $this->db->query("SELECT ov.option_value_id, ov.option_id, ov.image, ov.sort_order FROM `" . DB_PREFIX . "option_value` ov INNER JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id=ov.option_value_id) WHERE ov.option_id='" . (int)$optionId . "' AND ovd.language_id='" . (int)$languageId . "' AND ovd.name='" . $this->db->escape($name) . "' LIMIT 2");
        if ($query->num_rows > 1) { return array('_ambiguous' => true); }
        return $query->num_rows === 1 ? $query->row : false;
    }

    private function findProductOptionIndex($productOptions, $optionId) {
        foreach ($productOptions as $index => $option) {
            if ((int)$option['option_id'] === (int)$optionId) { return $index; }
        }
        return null;
    }

    private function findProductOptionValueIndex($values, $optionValueId) {
        foreach ($values as $index => $value) {
            if ((int)$value['option_value_id'] === (int)$optionValueId) { return $index; }
        }
        return null;
    }

    private function mergeCompactProductOptions($existingOptions, $compactValue, $rule, $payload, $options, $dryRun, &$warnings, &$errors) {
        $existingOptions = is_array($existingOptions) ? array_values($existingOptions) : array();
        $rawValue = trim((string)$compactValue);
        if ($rawValue !== '' && ($rawValue[0] === '[' || $rawValue[0] === '{')) {
            $decoded = json_decode($rawValue, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $errors[] = 'Некорректный JSON в поле опций: ' . json_last_error_msg() . '.';
                return $existingOptions;
            }
            if (isset($decoded['options']) && is_array($decoded['options'])) {
                $decoded = $decoded['options'];
            } elseif (isset($decoded['option_id']) || isset($decoded['name']) || isset($decoded['option_name'])) {
                $decoded = array($decoded);
            }
            return $this->mergeStructuredProductOptions($existingOptions, $decoded, $rule, $payload, $options, $dryRun, $warnings, $errors);
        }
        $tokens = preg_split('/[|;\r\n]+/u', $rawValue, -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens) { return in_array($rule, array('replace','clear'), true) ? array() : $existingOptions; }

        $groups = array();
        foreach ($tokens as $position => $token) {
            $parts = array_map('trim', explode(':', (string)$token));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                $errors[] = 'Некорректная компактная опция #' . ($position + 1) . '. Ожидается Опция:Значение[:Цена[:Префикс[:Количество[:Списывать[:Обязательная]]]]].';
                continue;
            }
            $optionName = $parts[0];
            $valueName = $parts[1];
            if ($this->textLength($optionName) > 128 || $this->textLength($valueName) > 128) {
                $errors[] = 'Название опции или значения длиннее 128 символов: ' . $optionName . ' / ' . $valueName . '.';
                continue;
            }
            $entry = array(
                'option_value_id' => 0,
                'name' => $valueName,
                'image' => '',
                'sort_order' => 0,
                'quantity' => isset($parts[4]) && $parts[4] !== '' ? $parts[4] : (int)$this->getOption($options, 'default_option_quantity', 100),
                'subtract' => isset($parts[5]) && $parts[5] !== '' ? $parts[5] : (int)$this->getOption($options, 'default_option_subtract', 0),
                'price' => isset($parts[2]) && $parts[2] !== '' ? str_replace(',', '.', $parts[2]) : 0,
                'price_prefix' => isset($parts[3]) && in_array($parts[3], array('+','-'), true) ? $parts[3] : '+',
                'points' => 0,
                'points_prefix' => '+',
                'weight' => 0,
                'weight_prefix' => '+',
                '_present' => array(
                    'option_value_id' => false, 'name' => true, 'image' => false, 'sort_order' => false,
                    'quantity' => true, 'subtract' => true, 'price' => true, 'price_prefix' => true,
                    'points' => false, 'points_prefix' => false, 'weight' => false, 'weight_prefix' => false
                )
            );
            $required = isset($parts[6]) && $parts[6] !== '' ? $parts[6] : 0;
            $tmpErrors = array();
            $required = $this->castValue($required, 'bool', 'option_required', $tmpErrors);
            if ($tmpErrors) { $errors = array_merge($errors, $tmpErrors); continue; }
            $entryList = array($entry);
            $this->validateOptionEntries($entryList, $tmpErrors);
            $entry = $entryList[0];
            if ($tmpErrors) { $errors = array_merge($errors, $tmpErrors); continue; }
            if (!isset($groups[$optionName])) {
                $groups[$optionName] = array('required' => (int)$required, 'entries' => array());
            }
            $groups[$optionName]['required'] = max($groups[$optionName]['required'], (int)$required);
            $groups[$optionName]['entries'][] = $entry;
        }
        if ($errors) { return $existingOptions; }

        $result = $rule === 'replace' ? array() : $existingOptions;
        foreach ($groups as $optionName => $group) {
            $localPayload = $payload;
            $localPayload[$this->normalizeFieldName('_OPTION_')] = $optionName;
            $global = $this->resolveGlobalOption(0, $optionName, 'select', 0, $localPayload, $options, 'upsert', $dryRun, $warnings, $errors);
            if ($errors) { break; }
            if (!$global['exists']) {
                if ($global['planned_create']) {
                    $warnings[] = 'После подтверждения будет создана глобальная опция: ' . $optionName . '.';
                }
                continue;
            }
            $optionId = (int)$global['option_id'];
            $existingIndex = $this->findProductOptionIndex($result, $optionId);
            if ($existingIndex === null || $rule === 'replace') {
                $productOption = array('option_id' => $optionId, 'value' => '', 'required' => (int)$group['required'], 'product_option_value' => array());
                $result[] = $productOption;
                $existingIndex = count($result) - 1;
            } elseif ($rule === 'overwrite') {
                $result[$existingIndex]['value'] = '';
                $result[$existingIndex]['required'] = (int)$group['required'];
                $result[$existingIndex]['product_option_value'] = array();
            } else {
                $result[$existingIndex]['required'] = max((int)$result[$existingIndex]['required'], (int)$group['required']);
            }

            foreach ($group['entries'] as $entry) {
                $globalValue = $this->resolveGlobalOptionValue($optionId, $entry, $localPayload, $options, 'upsert', $dryRun, $warnings, $errors);
                if ($errors) { break 2; }
                if (!$globalValue['exists']) {
                    if ($globalValue['planned_create']) {
                        $warnings[] = 'После подтверждения будет создано значение опции: ' . $optionName . ' / ' . $entry['name'] . '.';
                    }
                    continue;
                }
                $valueId = (int)$globalValue['option_value_id'];
                $valueIndex = $this->findProductOptionValueIndex($result[$existingIndex]['product_option_value'], $valueId);
                if ($valueIndex === null) {
                    $result[$existingIndex]['product_option_value'][] = $this->newProductOptionValueData($valueId, $entry, $options);
                } else {
                    $result[$existingIndex]['product_option_value'][$valueIndex] = $this->mergeProductOptionValueData($result[$existingIndex]['product_option_value'][$valueIndex], $entry, $options);
                }
            }
        }
        return array_values($result);
    }

    private function mergeStructuredProductOptions($existingOptions, $rows, $rule, $payload, $options, $dryRun, &$warnings, &$errors) {
        if (!is_array($rows)) {
            $errors[] = 'Поле опций должно содержать JSON-массив.';
            return $existingOptions;
        }
        $result = $rule === 'replace' ? array() : array_values($existingOptions);
        foreach ($rows as $position => $row) {
            if (!is_array($row)) {
                $errors[] = 'Опция JSON #' . ((int)$position + 1) . ' должна быть объектом.';
                continue;
            }
            $optionId = isset($row['option_id']) && $row['option_id'] !== '' ? (int)$row['option_id'] : 0;
            $optionName = trim((string)(isset($row['name']) ? $row['name'] : (isset($row['option_name']) ? $row['option_name'] : '')));
            $optionType = strtolower(trim((string)(isset($row['type']) ? $row['type'] : 'select')));
            $sortOrder = isset($row['sort_order']) && $row['sort_order'] !== '' ? (int)$row['sort_order'] : 0;
            if ($optionId <= 0 && $optionName === '') {
                $errors[] = 'Опция JSON #' . ((int)$position + 1) . ' не содержит option_id или name.';
                continue;
            }
            if ($optionName !== '' && $this->textLength($optionName) > 128) {
                $errors[] = 'Название опции JSON #' . ((int)$position + 1) . ' длиннее 128 символов.';
                continue;
            }
            if (!$this->isValidOptionType($optionType)) {
                $errors[] = 'Недопустимый тип опции JSON #' . ((int)$position + 1) . ': ' . $optionType . '.';
                continue;
            }
            $requiredErrors = array();
            $required = $this->castValue(isset($row['required']) ? $row['required'] : 0, 'bool', 'option_required', $requiredErrors);
            if ($requiredErrors) {
                $errors = array_merge($errors, $requiredErrors);
                continue;
            }

            $localPayload = $payload;
            if ($optionName !== '') {
                $localPayload[$this->normalizeFieldName('_OPTION_')] = $optionName;
            }
            $global = $this->resolveGlobalOption($optionId, $optionName, $optionType, $sortOrder, $localPayload, $options, 'upsert', $dryRun, $warnings, $errors);
            if ($errors) { break; }
            if (!$global['exists']) {
                if ($global['planned_create']) {
                    $warnings[] = 'После подтверждения будет создана глобальная опция: ' . ($optionName !== '' ? $optionName : ('ID ' . $optionId)) . '.';
                } else {
                    $errors[] = 'Глобальная опция не найдена и её создание отключено: ' . ($optionName !== '' ? $optionName : ('ID ' . $optionId)) . '.';
                }
                continue;
            }

            $resolvedOptionId = (int)$global['option_id'];
            $actualType = (string)$global['type'];
            $isChoice = in_array($actualType, array('select','radio','checkbox','image'), true);
            $existingIndex = $this->findProductOptionIndex($result, $resolvedOptionId);
            if ($existingIndex === null) {
                $result[] = array(
                    'option_id' => $resolvedOptionId,
                    'value' => '',
                    'required' => (int)$required,
                    'product_option_value' => array()
                );
                $existingIndex = count($result) - 1;
            } elseif (in_array($rule, array('overwrite','replace'), true)) {
                $result[$existingIndex]['value'] = '';
                $result[$existingIndex]['required'] = (int)$required;
                $result[$existingIndex]['product_option_value'] = array();
            } else {
                $result[$existingIndex]['required'] = max((int)$result[$existingIndex]['required'], (int)$required);
            }

            if (!$isChoice) {
                $hasValue = array_key_exists('value', $row);
                if ($hasValue) {
                    $newValue = (string)$row['value'];
                    if (in_array($rule, array('overwrite','replace'), true) || $this->isStoredValueEmpty($result[$existingIndex]['value'])) {
                        $result[$existingIndex]['value'] = $newValue;
                    }
                }
                $result[$existingIndex]['product_option_value'] = array();
                continue;
            }

            $values = isset($row['values']) && is_array($row['values']) ? $row['values'] : (isset($row['product_option_value']) && is_array($row['product_option_value']) ? $row['product_option_value'] : array());
            foreach ($values as $valuePosition => $valueRow) {
                if (!is_array($valueRow)) {
                    $errors[] = 'Значение опции JSON #' . ((int)$position + 1) . '.' . ((int)$valuePosition + 1) . ' должно быть объектом.';
                    continue;
                }
                $entry = array(
                    'option_value_id' => isset($valueRow['option_value_id']) && $valueRow['option_value_id'] !== '' ? (int)$valueRow['option_value_id'] : 0,
                    'name' => trim((string)(isset($valueRow['name']) ? $valueRow['name'] : (isset($valueRow['option_value_name']) ? $valueRow['option_value_name'] : ''))),
                    'image' => isset($valueRow['image']) ? trim((string)$valueRow['image']) : '',
                    'sort_order' => isset($valueRow['sort_order']) && $valueRow['sort_order'] !== '' ? $valueRow['sort_order'] : 0,
                    'quantity' => isset($valueRow['quantity']) && $valueRow['quantity'] !== '' ? $valueRow['quantity'] : (int)$this->getOption($options, 'default_option_quantity', 100),
                    'subtract' => isset($valueRow['subtract']) && $valueRow['subtract'] !== '' ? $valueRow['subtract'] : (int)$this->getOption($options, 'default_option_subtract', 0),
                    'price' => isset($valueRow['price']) && $valueRow['price'] !== '' ? str_replace(',', '.', (string)$valueRow['price']) : 0,
                    'price_prefix' => isset($valueRow['price_prefix']) && $valueRow['price_prefix'] !== '' ? (string)$valueRow['price_prefix'] : '+',
                    'points' => isset($valueRow['points']) && $valueRow['points'] !== '' ? $valueRow['points'] : 0,
                    'points_prefix' => isset($valueRow['points_prefix']) && $valueRow['points_prefix'] !== '' ? (string)$valueRow['points_prefix'] : '+',
                    'weight' => isset($valueRow['weight']) && $valueRow['weight'] !== '' ? str_replace(',', '.', (string)$valueRow['weight']) : 0,
                    'weight_prefix' => isset($valueRow['weight_prefix']) && $valueRow['weight_prefix'] !== '' ? (string)$valueRow['weight_prefix'] : '+',
                    '_present' => array(
                        'option_value_id' => array_key_exists('option_value_id', $valueRow),
                        'name' => array_key_exists('name', $valueRow) || array_key_exists('option_value_name', $valueRow),
                        'image' => array_key_exists('image', $valueRow),
                        'sort_order' => array_key_exists('sort_order', $valueRow),
                        'quantity' => array_key_exists('quantity', $valueRow),
                        'subtract' => array_key_exists('subtract', $valueRow),
                        'price' => array_key_exists('price', $valueRow),
                        'price_prefix' => array_key_exists('price_prefix', $valueRow),
                        'points' => array_key_exists('points', $valueRow),
                        'points_prefix' => array_key_exists('points_prefix', $valueRow),
                        'weight' => array_key_exists('weight', $valueRow),
                        'weight_prefix' => array_key_exists('weight_prefix', $valueRow)
                    )
                );
                if ($entry['option_value_id'] <= 0 && $entry['name'] === '') {
                    $errors[] = 'Значение опции JSON #' . ((int)$position + 1) . '.' . ((int)$valuePosition + 1) . ' не содержит option_value_id или name.';
                    continue;
                }
                if ($entry['name'] !== '' && $this->textLength($entry['name']) > 128) {
                    $errors[] = 'Название значения опции JSON #' . ((int)$position + 1) . '.' . ((int)$valuePosition + 1) . ' длиннее 128 символов.';
                    continue;
                }
                $entryList = array($entry);
                $entryErrors = array();
                $this->validateOptionEntries($entryList, $entryErrors);
                if ($entryErrors) {
                    $errors = array_merge($errors, $entryErrors);
                    continue;
                }
                $entry = $entryList[0];
                $globalValue = $this->resolveGlobalOptionValue($resolvedOptionId, $entry, $localPayload, $options, 'upsert', $dryRun, $warnings, $errors);
                if ($errors) { break 2; }
                if (!$globalValue['exists']) {
                    if ($globalValue['planned_create']) {
                        $warnings[] = 'После подтверждения будет создано значение опции: ' . ($optionName !== '' ? $optionName : ('ID ' . $resolvedOptionId)) . ' / ' . ($entry['name'] !== '' ? $entry['name'] : ('ID ' . $entry['option_value_id'])) . '.';
                    } else {
                        $errors[] = 'Значение глобальной опции не найдено и его создание отключено.';
                    }
                    continue;
                }
                $resolvedValueId = (int)$globalValue['option_value_id'];
                $valueIndex = $this->findProductOptionValueIndex($result[$existingIndex]['product_option_value'], $resolvedValueId);
                if ($valueIndex === null) {
                    $result[$existingIndex]['product_option_value'][] = $this->newProductOptionValueData($resolvedValueId, $entry, $options);
                } else {
                    $result[$existingIndex]['product_option_value'][$valueIndex] = $this->mergeProductOptionValueData($result[$existingIndex]['product_option_value'][$valueIndex], $entry, $options);
                }
            }
        }
        return array_values($result);
    }

    private function normalizeGoogleProductCategory($value, &$errors) {
        $value = trim((string)$value);
        if ($value === '') { return ''; }
        $value = preg_replace('/\s+/', ' ', $value);
        if ($this->textLength($value) > 255) {
            $errors[] = 'Google Product Category длиннее 255 символов.';
            return '';
        }
        if (preg_match('/^\d+\s*[-:>]\s*(.+)$/u', $value, $match)) {
            return trim($match[1]) !== '' ? trim($value) : preg_replace('/\D+/', '', $value);
        }
        return $value;
    }

    private function saveGoogleProductCategory($categoryId, $value, $storeId) {
        $categoryId = (int)$categoryId;
        $storeId = max(0, (int)$storeId);
        $value = trim((string)$value);
        if ($categoryId <= 0) { return; }
        if ($this->tableExists('googleshopping_category') && $this->tableColumnExistsSafe('googleshopping_category', 'google_product_category') && $this->tableColumnExistsSafe('googleshopping_category', 'category_id') && $this->tableColumnExistsSafe('googleshopping_category', 'store_id')) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "googleshopping_category` WHERE category_id='" . $categoryId . "' AND store_id='" . $storeId . "'");
            if ($value !== '') {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "googleshopping_category` SET google_product_category='" . $this->db->escape($value) . "', store_id='" . $storeId . "', category_id='" . $categoryId . "'");
            }
        }
        foreach (array('google_product_category_id','google_product_category') as $column) {
            if ($this->tableColumnExistsSafe('category', $column)) {
                $this->db->query("UPDATE `" . DB_PREFIX . "category` SET `" . $column . "`='" . $this->db->escape($value) . "' WHERE category_id='" . $categoryId . "'");
            }
        }
    }

    private function tableColumnExistsSafe($table, $column) {
        static $cache = array();
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $this->db->escape($table) . "` LIKE '" . $this->db->escape($column) . "'");
            $cache[$key] = $query->num_rows > 0;
        }
        return $cache[$key];
    }

    private function validateOptionEntries(&$entries, &$errors) {
        foreach ($entries as $index => &$entry) {
            $label = !empty($entry['name']) ? (string)$entry['name'] : '#' . ($index + 1);
            if ((int)$entry['option_value_id'] <= 0 && trim((string)$entry['name']) === '') {
                $errors[] = 'Значение опции ' . $label . ' должно содержать название или корректный ID.';
            }
            if (trim((string)$entry['name']) !== '' && $this->textLength((string)$entry['name']) > 128) {
                $errors[] = 'Название значения опции длиннее 128 символов: ' . $label . '.';
            }
            if (!empty($entry['_present']['option_value_id']) && (int)$entry['option_value_id'] <= 0) {
                $errors[] = 'ID значения опции должен быть положительным целым числом: ' . $label . '.';
            }
            foreach (array('quantity', 'points', 'sort_order') as $field) {
                if (empty($entry['_present'][$field])) { continue; }
                $value = $this->castValue($entry[$field], 'int', 'option_' . $field, $errors);
                if ($value !== null) {
                    if ($value < 0) { $errors[] = 'Поле ' . $field . ' значения опции не может быть отрицательным: ' . $label . '.'; }
                    $entry[$field] = $value;
                }
            }
            if (!empty($entry['_present']['subtract'])) {
                $value = $this->castValue($entry['subtract'], 'bool', 'option_subtract', $errors);
                if ($value !== null) { $entry['subtract'] = $value; }
            }
            foreach (array('price', 'weight') as $field) {
                if (empty($entry['_present'][$field])) { continue; }
                $value = $this->castValue($entry[$field], 'decimal', 'option_' . $field, $errors);
                if ($value !== null) {
                    if ($value < 0) { $errors[] = 'Поле ' . $field . ' значения опции не может быть отрицательным: ' . $label . '.'; }
                    $entry[$field] = $value;
                }
            }
            foreach (array('price_prefix', 'points_prefix', 'weight_prefix') as $field) {
                if (empty($entry['_present'][$field])) { continue; }
                $prefix = trim((string)$entry[$field]);
                if (!in_array($prefix, array('+', '-'), true)) {
                    $errors[] = 'Поле ' . $field . ' должно содержать только + или -: ' . $label . '.';
                } else {
                    $entry[$field] = $prefix;
                }
            }
        }
        unset($entry);
    }

    private function newProductOptionValueData($optionValueId, $entry, $options) {
        return array(
            'option_value_id' => (int)$optionValueId,
            'quantity' => !empty($entry['_present']['quantity']) ? (int)$entry['quantity'] : (int)$this->getOption($options, 'default_option_quantity', 100),
            'subtract' => !empty($entry['_present']['subtract']) ? (int)$entry['subtract'] : (int)$this->getOption($options, 'default_option_subtract', 0),
            'price' => !empty($entry['_present']['price']) ? $this->toDecimal($entry['price']) : 0,
            'price_prefix' => !empty($entry['_present']['price_prefix']) ? $this->normalizePrefix($entry['price_prefix'], '+') : $this->normalizePrefix($this->getOption($options, 'default_price_prefix', '+'), '+'),
            'points' => !empty($entry['_present']['points']) ? (int)$entry['points'] : 0,
            'points_prefix' => !empty($entry['_present']['points_prefix']) ? $this->normalizePrefix($entry['points_prefix'], '+') : $this->normalizePrefix($this->getOption($options, 'default_points_prefix', '+'), '+'),
            'weight' => !empty($entry['_present']['weight']) ? $this->toDecimal($entry['weight']) : 0,
            'weight_prefix' => !empty($entry['_present']['weight_prefix']) ? $this->normalizePrefix($entry['weight_prefix'], '+') : $this->normalizePrefix($this->getOption($options, 'default_weight_prefix', '+'), '+')
        );
    }

    private function mergeProductOptionValueData($existing, $entry, $options) {
        $map = array(
            'quantity' => array('int', array('_QUANTITY_', '_OPTION_QUANTITY_')),
            'subtract' => array('int', array('_SUBTRACT_')),
            'price' => array('decimal', array('_PRICE_', '_OPTION_PRICE_')),
            'price_prefix' => array('prefix', array('_PRICE_PREFIX_')),
            'points' => array('int', array('_POINTS_')),
            'points_prefix' => array('prefix', array('_POINTS_PREFIX_')),
            'weight' => array('decimal', array('_WEIGHT_')),
            'weight_prefix' => array('prefix', array('_WEIGHT_PREFIX_'))
        );
        foreach ($map as $field => $definition) {
            if (empty($entry['_present'][$field])) { continue; }
            $type = $definition[0];
            $rule = $this->getFieldRule($options, $definition[1], 'overwrite');
            if ($rule === 'preserve') { continue; }
            $current = isset($existing[$field]) ? $existing[$field] : ($type === 'prefix' ? '+' : 0);
            if ($rule === 'fill_empty' && !$this->isStoredValueEmpty($current)) { continue; }
            if ($rule === 'clear') {
                $existing[$field] = $type === 'prefix' ? '+' : 0;
                continue;
            }
            if ($type === 'int') { $existing[$field] = (int)$entry[$field]; }
            elseif ($type === 'decimal') { $existing[$field] = $this->toDecimal($entry[$field]); }
            else { $existing[$field] = $this->normalizePrefix($entry[$field], '+'); }
        }
        return $existing;
    }

    private function extractOptionValueEntries($payload, $options, &$warnings) {
        $entries = array();
        $compact = $this->payloadValueWithPresence($payload, array('_OPTION_VALUES_', 'option_values'));
        if ($compact['present'] && trim((string)$compact['value']) !== '') {
            foreach (preg_split('/[|\n\r]+/u', (string)$compact['value']) as $token) {
                $entry = $this->parseOptionValueToken($token, $options);
                if ($entry['name'] !== '' || $entry['option_value_id'] > 0) {
                    $entries[] = $entry;
                }
            }
            return $entries;
        }

        $fieldMap = array(
            'option_value_id' => array('_OPTION_VALUE_ID_', 'option_value_id'),
            'name' => array('_OPTION_VALUE_', '_VALUE_NAME_', 'option_value'),
            'quantity' => array('_QUANTITY_', '_OPTION_QUANTITY_', 'quantity'),
            'subtract' => array('_SUBTRACT_', 'subtract'),
            'price' => array('_PRICE_', '_OPTION_PRICE_', 'price'),
            'price_prefix' => array('_PRICE_PREFIX_', 'price_prefix'),
            'points' => array('_POINTS_', 'points'),
            'points_prefix' => array('_POINTS_PREFIX_', 'points_prefix'),
            'weight' => array('_WEIGHT_', 'weight'),
            'weight_prefix' => array('_WEIGHT_PREFIX_', 'weight_prefix'),
            'image' => array('_OPTION_VALUE_IMAGE_', 'option_value_image'),
            'sort_order' => array('_OPTION_VALUE_SORT_ORDER_', 'option_value_sort_order')
        );
        $entry = array('option_value_id' => 0, 'name' => '', 'quantity' => '', 'subtract' => '', 'price' => '', 'price_prefix' => '', 'points' => '', 'points_prefix' => '', 'weight' => '', 'weight_prefix' => '', 'image' => '', 'sort_order' => '', '_present' => array());
        foreach ($fieldMap as $field => $keys) {
            $value = $this->payloadValueWithPresence($payload, $keys);
            $entry['_present'][$field] = $value['present'];
            $entry[$field] = $field === 'option_value_id' ? (int)$value['value'] : $value['value'];
        }
        if ($entry['name'] !== '' || $entry['option_value_id'] > 0) {
            $entries[] = $entry;
        }
        return $entries;
    }

    private function parseOptionValueToken($token, $options) {
        $token = trim((string)$token);
        $entry = array(
            'option_value_id' => 0, 'name' => '', 'quantity' => '', 'subtract' => '', 'price' => '', 'price_prefix' => '',
            'points' => '', 'points_prefix' => '', 'weight' => '', 'weight_prefix' => '', 'image' => '', 'sort_order' => '', '_present' => array()
        );
        foreach (array('option_value_id','name','quantity','subtract','price','price_prefix','points','points_prefix','weight','weight_prefix','image','sort_order') as $field) {
            $entry['_present'][$field] = false;
        }
        if ($token === '') { return $entry; }

        $meta = '';
        if (preg_match('/^(.*?)\{(.*)\}$/u', $token, $match)) {
            $entry['name'] = trim($match[1]);
            $entry['_present']['name'] = true;
            $meta = $match[2];
        } else {
            $entry['name'] = $token;
            $entry['_present']['name'] = true;
        }

        if ($meta !== '') {
            foreach (preg_split('/[;,]+/u', $meta) as $pair) {
                if (strpos($pair, '=') === false) { continue; }
                list($key, $value) = explode('=', $pair, 2);
                $key = strtolower(trim($key));
                $value = trim($value);
                $field = '';
                if (in_array($key, array('id','option_value_id'), true)) { $field = 'option_value_id'; $value = (int)$value; }
                elseif (in_array($key, array('qty','quantity','q'), true)) { $field = 'quantity'; }
                elseif (in_array($key, array('subtract','sub'), true)) { $field = 'subtract'; }
                elseif (in_array($key, array('price','p'), true)) { $field = 'price'; }
                elseif (in_array($key, array('prefix','price_prefix'), true)) { $field = 'price_prefix'; }
                elseif ($key === 'points') { $field = 'points'; }
                elseif ($key === 'points_prefix') { $field = 'points_prefix'; }
                elseif ($key === 'weight') { $field = 'weight'; }
                elseif ($key === 'weight_prefix') { $field = 'weight_prefix'; }
                elseif (in_array($key, array('image','img'), true)) { $field = 'image'; }
                elseif (in_array($key, array('sort','sort_order'), true)) { $field = 'sort_order'; }
                if ($field !== '') {
                    $entry[$field] = $value;
                    $entry['_present'][$field] = true;
                }
            }
        } elseif (strpos($token, ':') !== false) {
            $parts = explode(':', $token);
            $entry['name'] = trim($parts[0]);
            $entry['_present']['name'] = true;
            if (isset($parts[1])) { $entry['quantity'] = trim($parts[1]); $entry['_present']['quantity'] = true; }
            if (isset($parts[2])) {
                $price = trim($parts[2]);
                if ($price !== '' && ($price[0] === '+' || $price[0] === '-')) {
                    $entry['price_prefix'] = $price[0];
                    $entry['_present']['price_prefix'] = true;
                    $price = substr($price, 1);
                }
                $entry['price'] = $price;
                $entry['_present']['price'] = true;
            }
        }
        return $entry;
    }

    private function mergeLocalizedDescriptions($existing, $payload, $defaultLanguageId, $fields, $ignoreEmpty, $isNew, $copyDefault, $options = array()) {
        if (!is_array($existing)) { $existing = array(); }
        $updates = array();
        foreach ($fields as $field) {
            $values = $this->getLocalizedPayloadValues($payload, $field, $defaultLanguageId);
            foreach ($values as $languageId => $value) {
                if (!isset($updates[$languageId])) { $updates[$languageId] = array(); }
                $updates[$languageId][$field] = $value;
            }
        }

        foreach ($updates as $languageId => $fieldValues) {
            if (!isset($existing[$languageId])) {
                $existing[$languageId] = $this->emptyDescriptionRow($fields);
            }
            foreach ($fieldValues as $field => $valueInfo) {
                if (!$valueInfo['present']) { continue; }
                $rule = $this->getFieldRule($options, $this->getFieldAliases($field), 'overwrite');
                if ($rule === 'preserve') { continue; }
                if ($rule === 'fill_empty' && !$isNew && !$this->isStoredValueEmpty(isset($existing[$languageId][$field]) ? $existing[$languageId][$field] : null)) { continue; }
                if ($rule === 'clear') { $existing[$languageId][$field] = ''; continue; }
                if ($valueInfo['value'] === '' && $ignoreEmpty) { continue; }
                if ($rule === 'merge') {
                    $existing[$languageId][$field] = $this->mergeTextValue(isset($existing[$languageId][$field]) ? $existing[$languageId][$field] : '', (string)$valueInfo['value'], $field);
                    continue;
                }
                $existing[$languageId][$field] = (string)$valueInfo['value'];
            }
            if (isset($existing[$languageId]['name']) && trim((string)$existing[$languageId]['name']) !== '' && in_array('meta_title', $fields, true) && trim((string)(isset($existing[$languageId]['meta_title']) ? $existing[$languageId]['meta_title'] : '')) === '') {
                $existing[$languageId]['meta_title'] = $existing[$languageId]['name'];
            }
        }

        if ($isNew && $copyDefault && isset($existing[$defaultLanguageId])) {
            foreach ($this->getLanguages() as $languageId => $language) {
                if (isset($existing[$languageId])) { continue; }
                $existing[$languageId] = $existing[$defaultLanguageId];
            }
        }
        return $existing;
    }

    private function mergeTextValue($current, $incoming, $field) {
        $current = trim((string)$current);
        $incoming = trim((string)$incoming);
        if ($incoming === '') { return $current; }
        if ($current === '') { return $incoming; }
        if ($current === $incoming || strpos($current, $incoming) !== false) { return $current; }
        $separator = in_array((string)$field, array('tag','meta_keyword'), true) ? ', ' : "\n";
        return $current . $separator . $incoming;
    }

    private function emptyDescriptionRow($fields) {
        $row = array();
        foreach ($fields as $field) { $row[$field] = ''; }
        return $row;
    }

    private function getLocalizedPayloadValues($payload, $baseField, $defaultLanguageId) {
        $result = array();
        $baseField = strtolower(trim((string)$baseField));
        $aliases = $this->getFieldAliases($baseField);

        foreach ($aliases as $alias) {
            $normal = $this->normalizeFieldName($alias);
            if (array_key_exists($normal, $payload)) {
                $result[(int)$defaultLanguageId] = array('present' => true, 'value' => (string)$payload[$normal]);
                break;
            }
        }

        $languageMap = $this->getLanguageTokenMap();
        foreach ($payload as $key => $value) {
            if ($key === 'raw' || !is_string($key)) { continue; }
            $upper = strtoupper($key);
            foreach ($aliases as $alias) {
                $aliasUpper = strtoupper(trim($alias, '_'));
                $patterns = array(
                    '/^_' . preg_quote($aliasUpper, '/') . '_LANG(?:UAGE)?[=_-]?([A-Z0-9-]+)_$/',
                    '/^_' . preg_quote($aliasUpper, '/') . '_([0-9]+)_$/',
                    '/^_' . preg_quote($aliasUpper, '/') . '_([A-Z]{2}(?:-[A-Z]{2})?)_$/',
                    '/^' . preg_quote($aliasUpper, '/') . '_LANG(?:UAGE)?[=_-]?([A-Z0-9-]+)$/',
                    '/^' . preg_quote($aliasUpper, '/') . '_([0-9]+)$/',
                    '/^' . preg_quote($aliasUpper, '/') . '_([A-Z]{2}(?:-[A-Z]{2})?)$/'
                );
                foreach ($patterns as $pattern) {
                    if (!preg_match($pattern, $upper, $match)) { continue; }
                    $token = strtoupper($match[1]);
                    if (isset($languageMap[$token])) {
                        $result[(int)$languageMap[$token]] = array('present' => true, 'value' => (string)$value);
                    }
                    break 2;
                }
            }
        }
        return $result;
    }

    private function getFieldAliases($baseField) {
        $map = array(
            'name' => array('_NAME_', 'name'),
            'description' => array('_DESCRIPTION_', 'description'),
            'tag' => array('_TAG_', 'tag'),
            'meta_title' => array('_META_TITLE_', 'meta_title'),
            'meta_description' => array('_META_DESCRIPTION_', 'meta_description'),
            'meta_keyword' => array('_META_KEYWORD_', '_META_KEYWORDS_', 'meta_keyword'),
            'meta_h1' => array('_META_H1_', '_H1_', 'meta_h1'),
            'seo_keyword' => array('_SEO_KEYWORD_', '_KEYWORD_', 'seo_keyword', 'keyword'),
            'option' => array('_OPTION_', '_OPTION_NAME_', 'option', 'option_name'),
            'option_value' => array('_OPTION_VALUE_', '_VALUE_NAME_', 'option_value')
        );
        return isset($map[$baseField]) ? $map[$baseField] : array('_' . strtoupper($baseField) . '_', $baseField);
    }

    private function getLanguageTokenMap() {
        $map = array();
        foreach ($this->getLanguages() as $languageId => $language) {
            $map[(string)$languageId] = (int)$languageId;
            $code = strtoupper(str_replace('_', '-', (string)$language['code']));
            $map[$code] = (int)$languageId;
            $short = strtoupper(substr($code, 0, 2));
            if (!isset($map[$short])) { $map[$short] = (int)$languageId; }
        }
        return $map;
    }

    private function resolveProductCategories($existingCategories, $existingMainCategoryId, $payload, $options, $isNew, $dryRun, &$warnings, &$errors) {
        $ignoreEmpty = (string)$this->getOption($options, 'empty_field', '1') === '1';
        $idField = $this->payloadValueWithPresence($payload, array('_CATEGORY_IDS_', '_CATEGORY_ID_', 'category_ids', 'category_id'));
        $pathField = $this->payloadValueWithPresence($payload, array('_CATEGORY_', '_CATEGORIES_', 'category', 'categories'));
        $mainField = $this->payloadValueWithPresence($payload, array('_MAIN_CATEGORY_', '_MAIN_CATEGORY_ID_', 'main_category'));
        $hasCategoryInput = $idField['present'] || $pathField['present'];
        $categoryRule = $this->getFieldRule($options, array('_CATEGORY_IDS_', '_CATEGORY_ID_', '_CATEGORY_', '_CATEGORIES_'), 'replace');
        $categories = array_values(array_unique(array_map('intval', is_array($existingCategories) ? $existingCategories : array())));
        if ($categoryRule === 'preserve') { $hasCategoryInput = false; }

        if ($hasCategoryInput) {
            if ($categoryRule === 'fill_empty' && !$isNew && $categories) {
                // Existing category links are retained.
            } elseif ($categoryRule === 'clear') {
                $categories = array();
            } elseif ($ignoreEmpty && trim((string)$idField['value']) === '' && trim((string)$pathField['value']) === '') {
                // Preserve current links.
            } else {
                if ($categoryRule !== 'merge') { $categories = array(); }
                foreach ($this->splitMulti($idField['value'], '|') as $idValue) {
                    foreach (preg_split('/[,;\s]+/', $idValue) as $id) {
                        $id = (int)$id;
                        if ($id <= 0) { continue; }
                        if ($this->idExists('category', 'category_id', $id)) {
                            $categories[] = $id;
                        } else {
                            $warnings[] = 'Категория с ID ' . $id . ' не найдена.';
                        }
                    }
                }
                foreach ($this->splitMulti($pathField['value'], '|') as $path) {
                    $categoryId = $this->ensureCategoryPath($path, $options, $dryRun, $warnings, $errors);
                    if ($categoryId > 0) { $categories[] = $categoryId; }
                }
                $categories = array_values(array_unique(array_map('intval', $categories)));
            }
        }

        $mainCategoryId = (int)$existingMainCategoryId;
        $mainRule = $this->getFieldRule($options, array('_MAIN_CATEGORY_', '_MAIN_CATEGORY_ID_'), 'overwrite');
        if ($categoryRule === 'clear') { $mainCategoryId = 0; }
        if ($mainField['present'] && $mainRule !== 'preserve') {
            if ($mainRule === 'clear') {
                $mainCategoryId = 0;
            } elseif (!($mainRule === 'fill_empty' && !$isNew && $mainCategoryId > 0) && trim((string)$mainField['value']) !== '') {
                $mainValue = trim((string)$mainField['value']);
                if (ctype_digit($mainValue) && $this->idExists('category', 'category_id', (int)$mainValue)) {
                    $mainCategoryId = (int)$mainValue;
                } else {
                    $resolved = $this->ensureCategoryPath($mainValue, $options, $dryRun, $warnings, $errors);
                    if ($resolved > 0) { $mainCategoryId = $resolved; }
                }
            }
        } elseif ($hasCategoryInput && $categories && $mainRule !== 'preserve') {
            $mainCategoryId = (int)$categories[0];
        }

        if ($mainCategoryId > 0 && !in_array($mainCategoryId, $categories, true)) {
            $categories[] = $mainCategoryId;
        }
        if ($mainCategoryId <= 0 && $categories && !in_array($mainRule, array('preserve','clear'), true)) {
            $mainCategoryId = (int)$categories[0];
        }
        if ($isNew && !$categories) {
            $warnings[] = 'Новый товар создаётся без категории.';
        }

        return array('categories' => $categories, 'main_category_id' => $mainCategoryId);
    }

    private function ensureCategoryPath($path, $options, $dryRun, &$warnings, &$errors) {
        $path = trim((string)$path);
        if ($path === '') { return 0; }
        $separator = trim((string)$this->getOption($options, 'category_path_separator', '>'));
        if ($separator === '') { $separator = '>'; }
        $parts = $this->splitPath($path, $separator);
        if (!$parts) { return 0; }
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $parentId = 0;

        foreach ($parts as $name) {
            $query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c INNER JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id=c.category_id) WHERE c.parent_id='" . (int)$parentId . "' AND cd.language_id='" . (int)$languageId . "' AND cd.name='" . $this->db->escape($name) . "' LIMIT 2");
            if ($query->num_rows > 1) {
                $errors[] = 'Путь категории неоднозначен: у одного родителя найдено несколько категорий с названием ' . $name . '.';
                return 0;
            }
            if ($query->num_rows === 1) {
                $parentId = (int)$query->row['category_id'];
                continue;
            }

            if ((string)$this->getOption($options, 'create_categories', '1') !== '1') {
                $warnings[] = 'Категория не найдена и создание отключено: ' . $name . '.';
                return 0;
            }
            if ($dryRun) {
                $warnings[] = 'Тестовый режим: отсутствующая категория была бы создана: ' . $name . '.';
                return 0;
            }

            $description = array($languageId => array(
                'name' => $name,
                'description' => '',
                'meta_title' => $name,
                'meta_description' => '',
                'meta_keyword' => '',
                'meta_h1' => $name
            ));
            if ((string)$this->getOption($options, 'copy_default_language', '0') === '1') {
                foreach ($this->getLanguages() as $id => $language) {
                    if (!isset($description[$id])) { $description[$id] = $description[$languageId]; }
                }
            }
            $categoryData = array(
                'image' => '',
                'parent_id' => $parentId,
                'top' => 0,
                'column' => 1,
                'sort_order' => 0,
                'status' => 1,
                'noindex' => 0,
                'category_description' => $description,
                'category_filter' => array(),
                'category_store' => $this->normalizeStoreIds($this->getOption($options, 'store_ids', '0'), $warnings),
                'category_layout' => array(),
                'product_related' => array(),
                'article_related' => array(),
                'category_seo_url' => array()
            );
            $this->load->model('catalog/category');
            $createdId = $this->model_catalog_category->addCategory($categoryData);
            $parentId = (int)$createdId;
            if (!$parentId) { $parentId = (int)$this->db->getLastId(); }
            $this->rebuildCategorySubtreePaths($parentId);
        }
        return $parentId;
    }

    private function resolveManufacturerId($idValue, $name, $options, $dryRun, &$warnings, &$errors) {
        $idValue = (int)$idValue;
        if ($idValue > 0) {
            if ($this->idExists('manufacturer', 'manufacturer_id', $idValue)) { return $idValue; }
            $warnings[] = 'Производитель с ID ' . $idValue . ' не найден.';
            if (trim((string)$name) === '') { return 0; }
        }
        $name = trim((string)$name);
        if ($name === '') { return 0; }
        $query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name='" . $this->db->escape($name) . "' LIMIT 2");
        if ($query->num_rows > 1) {
            $errors[] = 'Найдено несколько производителей с одинаковым названием: ' . $name . '. Используйте _MANUFACTURER_ID_.';
            return null;
        }
        if ($query->num_rows === 1) { return (int)$query->row['manufacturer_id']; }
        if ((string)$this->getOption($options, 'create_manufacturers', '1') !== '1') {
            $warnings[] = 'Производитель не найден и создание отключено: ' . $name . '.';
            return 0;
        }
        if ($dryRun) {
            $warnings[] = 'Тестовый режим: отсутствующий производитель был бы создан: ' . $name . '.';
            return 0;
        }
        $manufacturerDescriptions = array();
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $manufacturerDescriptions[$languageId] = array('description' => '', 'meta_title' => $name, 'meta_description' => '', 'meta_keyword' => '', 'meta_h1' => $name);
        if ((string)$this->getOption($options, 'copy_default_language', '0') === '1') {
            foreach ($this->getLanguages() as $id => $language) { if (!isset($manufacturerDescriptions[$id])) { $manufacturerDescriptions[$id] = $manufacturerDescriptions[$languageId]; } }
        }
        $manufacturerData = array(
            'name' => $name,
            'image' => '',
            'sort_order' => 0,
            'noindex' => 0,
            'manufacturer_description' => $manufacturerDescriptions,
            'manufacturer_store' => $this->normalizeStoreIds($this->getOption($options, 'store_ids', '0'), $warnings),
            'manufacturer_layout' => array(),
            'product_related' => array(),
            'article_related' => array(),
            'manufacturer_seo_url' => array()
        );
        $this->load->model('catalog/manufacturer');
        $createdId = $this->model_catalog_manufacturer->addManufacturer($manufacturerData);
        $id = (int)$createdId;
        if (!$id) { $id = (int)$this->db->getLastId(); }
        return $id;
    }

    private function extractIndividualAttributes($payload) {
        $result = array();
        $raw = isset($payload['raw']) && is_array($payload['raw']) ? $payload['raw'] : array();
        foreach ($raw as $header => $value) {
            $header = trim((string)$header);
            if (!preg_match('/^_ATTRIBUTE_(.+)_$/iu', $header, $match) || strtoupper($header) === '_ATTRIBUTES_') {
                continue;
            }
            $label = trim((string)$match[1]);
            $group = 'Характеристики';
            $name = $label;
            if (strpos($label, ':') !== false) {
                list($group, $name) = array_map('trim', explode(':', $label, 2));
            }
            $result[] = array('group' => $group, 'name' => $name, 'text' => (string)$value);
        }
        return $result;
    }

    private function mergeProductAttributes($existing, $compact, $languageId, $replaceLanguage, $dryRun, &$warnings, &$errors, $individualAttributes = array()) {
        $map = array();
        foreach ($existing as $attribute) {
            $attributeId = (int)$attribute['attribute_id'];
            $map[$attributeId] = $attribute;
            if (!isset($map[$attributeId]['product_attribute_description'])) {
                $map[$attributeId]['product_attribute_description'] = array();
            }
            if ($replaceLanguage) {
                unset($map[$attributeId]['product_attribute_description'][$languageId]);
            }
        }

        foreach (preg_split('/[|\n\r]+/u', (string)$compact) as $token) {
            $token = trim($token);
            if ($token === '') { continue; }
            $position = strpos($token, '=');
            if ($position === false) {
                $warnings[] = 'Атрибут пропущен, ожидается формат Группа:Название=Значение: ' . $token . '.';
                continue;
            }
            $left = trim(substr($token, 0, $position));
            $text = trim(substr($token, $position + 1));
            $groupName = 'Характеристики';
            $attributeName = $left;
            if (strpos($left, ':') !== false) {
                list($groupName, $attributeName) = array_map('trim', explode(':', $left, 2));
            }
            if ($attributeName === '') {
                $warnings[] = 'Атрибут без названия пропущен.';
                continue;
            }
            $attributeId = $this->ensureAttribute($groupName, $attributeName, $languageId, $dryRun, $warnings, $errors);
            if ($attributeId === 0) { continue; }
            if (!isset($map[$attributeId])) {
                $map[$attributeId] = array('attribute_id' => $attributeId, 'product_attribute_description' => array());
            }
            $map[$attributeId]['product_attribute_description'][$languageId] = array('text' => $text);
        }

        foreach ($individualAttributes as $attributeInput) {
            $groupName = trim((string)$attributeInput['group']);
            $attributeName = trim((string)$attributeInput['name']);
            $text = (string)$attributeInput['text'];
            if ($groupName === '') { $groupName = 'Характеристики'; }
            if ($attributeName === '') {
                $warnings[] = 'Колонка атрибута без названия пропущена.';
                continue;
            }
            $attributeId = $this->ensureAttribute($groupName, $attributeName, $languageId, $dryRun, $warnings, $errors);
            if ($attributeId === 0) { continue; }
            if (!isset($map[$attributeId])) {
                $map[$attributeId] = array('attribute_id' => $attributeId, 'product_attribute_description' => array());
            }
            $map[$attributeId]['product_attribute_description'][$languageId] = array('text' => $text);
        }

        foreach ($map as $attributeId => $attribute) {
            if (empty($attribute['product_attribute_description'])) {
                unset($map[$attributeId]);
            }
        }
        return array_values($map);
    }

    private function ensureAttribute($groupName, $attributeName, $languageId, $dryRun, &$warnings, &$errors) {
        $groupQuery = $this->db->query("SELECT ag.attribute_group_id FROM `" . DB_PREFIX . "attribute_group` ag INNER JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (agd.attribute_group_id=ag.attribute_group_id) WHERE agd.language_id='" . (int)$languageId . "' AND agd.name='" . $this->db->escape($groupName) . "' LIMIT 1");
        $groupId = $groupQuery->num_rows ? (int)$groupQuery->row['attribute_group_id'] : 0;
        if (!$groupId) {
            if ($dryRun) {
                $warnings[] = 'Тестовый режим: была бы создана группа атрибутов ' . $groupName . '.';
                return -1 * (int)(hexdec(substr(sha1($groupName . '|' . $attributeName), 0, 6)) + 1);
            }
            $descriptions = array();
            foreach ($this->getLanguages() as $id => $language) { $descriptions[$id] = array('name' => $groupName); }
            $this->load->model('catalog/attribute_group');
            $createdId = $this->model_catalog_attribute_group->addAttributeGroup(array('sort_order' => 0, 'attribute_group_description' => $descriptions));
            $groupId = (int)$createdId;
            if (!$groupId) { $groupId = (int)$this->db->getLastId(); }
        }

        $attributeQuery = $this->db->query("SELECT a.attribute_id FROM `" . DB_PREFIX . "attribute` a INNER JOIN `" . DB_PREFIX . "attribute_description` ad ON (ad.attribute_id=a.attribute_id) WHERE a.attribute_group_id='" . (int)$groupId . "' AND ad.language_id='" . (int)$languageId . "' AND ad.name='" . $this->db->escape($attributeName) . "' LIMIT 1");
        if ($attributeQuery->num_rows) { return (int)$attributeQuery->row['attribute_id']; }
        if ($dryRun) {
            $warnings[] = 'Тестовый режим: был бы создан атрибут ' . $attributeName . '.';
            return -1 * (int)(hexdec(substr(sha1($groupName . '|' . $attributeName), 0, 6)) + 1);
        }

        $descriptions = array();
        foreach ($this->getLanguages() as $id => $language) { $descriptions[$id] = array('name' => $attributeName); }
        $this->load->model('catalog/attribute');
        $createdId = $this->model_catalog_attribute->addAttribute(array('attribute_group_id' => $groupId, 'sort_order' => 0, 'attribute_description' => $descriptions));
        $attributeId = (int)$createdId;
        if (!$attributeId) { $attributeId = (int)$this->db->getLastId(); }
        return $attributeId;
    }

    private function getExistingMainCategoryId($productId) {
        $productId = (int)$productId;
        if ($productId <= 0) { return 0; }
        if ($this->columnExists('product_to_category', 'main_category')) {
            $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id='" . $productId . "' AND main_category='1' LIMIT 1");
            if ($query->num_rows) { return (int)$query->row['category_id']; }
        }
        if ($this->columnExists('product', 'main_category_id')) {
            $query = $this->db->query("SELECT main_category_id FROM `" . DB_PREFIX . "product` WHERE product_id='" . $productId . "' LIMIT 1");
            if ($query->num_rows && (int)$query->row['main_category_id'] > 0) { return (int)$query->row['main_category_id']; }
        }
        $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id='" . $productId . "' ORDER BY category_id ASC LIMIT 1");
        return $query->num_rows ? (int)$query->row['category_id'] : 0;
    }

    private function applyMainCategory($productId, $mainCategoryId) {
        $productId = (int)$productId;
        $mainCategoryId = (int)$mainCategoryId;
        if ($mainCategoryId > 0) {
            $exists = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id='" . $productId . "' AND category_id='" . $mainCategoryId . "' LIMIT 1");
            if (!$exists->num_rows) { $mainCategoryId = 0; }
        }
        if ($this->columnExists('product_to_category', 'main_category')) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product_to_category` SET main_category='0' WHERE product_id='" . $productId . "'");
            if ($mainCategoryId > 0) {
                $this->db->query("UPDATE `" . DB_PREFIX . "product_to_category` SET main_category='1' WHERE product_id='" . $productId . "' AND category_id='" . $mainCategoryId . "'");
            }
        }
        if ($this->columnExists('product', 'main_category_id')) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET main_category_id='" . $mainCategoryId . "' WHERE product_id='" . $productId . "'");
        }
    }

    private function isValidCategoryParent($categoryId, $parentId) {
        $categoryId = (int)$categoryId;
        $parentId = (int)$parentId;
        if ($categoryId <= 0) { return true; }
        if ($categoryId === $parentId) { return false; }
        $visited = array();
        $current = $parentId;
        for ($depth = 0; $current > 0 && $depth < 1000; $depth++) {
            if ($current === $categoryId || isset($visited[$current])) { return false; }
            $visited[$current] = true;
            $query = $this->db->query("SELECT parent_id FROM `" . DB_PREFIX . "category` WHERE category_id='" . (int)$current . "' LIMIT 1");
            if (!$query->num_rows) { return false; }
            $current = (int)$query->row['parent_id'];
        }
        return $current === 0;
    }

    private function rebuildCategorySubtreePaths($rootCategoryId) {
        if (!$this->tableExists('category_path')) { return; }
        $queue = array((int)$rootCategoryId);
        $visited = array();
        $processed = 0;
        while ($queue) {
            $categoryId = array_shift($queue);
            if ($categoryId <= 0 || isset($visited[$categoryId])) { continue; }
            $visited[$categoryId] = true;
            $this->rebuildSingleCategoryPath($categoryId);
            $processed++;
            if ($processed > 50000) {
                throw new RuntimeException('Превышен безопасный предел пересчёта дерева категорий.');
            }
            foreach ($this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE parent_id='" . (int)$categoryId . "'")->rows as $child) {
                $queue[] = (int)$child['category_id'];
            }
        }
    }

    private function rebuildSingleCategoryPath($categoryId) {
        $categoryId = (int)$categoryId;
        $paths = array();
        $visited = array();
        $current = $categoryId;
        for ($depth = 0; $current > 0 && $depth < 1000; $depth++) {
            if (isset($visited[$current])) {
                throw new RuntimeException('Обнаружен цикл в дереве категорий возле ID ' . $current . '.');
            }
            $visited[$current] = true;
            array_unshift($paths, $current);
            $query = $this->db->query("SELECT parent_id FROM `" . DB_PREFIX . "category` WHERE category_id='" . (int)$current . "' LIMIT 1");
            if (!$query->num_rows) {
                throw new RuntimeException('Категория или её родитель не существует: ID ' . $current . '.');
            }
            $current = (int)$query->row['parent_id'];
        }
        if ($current > 0) {
            throw new RuntimeException('Превышена допустимая глубина дерева категорий.');
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id='" . $categoryId . "'");
        foreach ($paths as $level => $pathId) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id='" . $categoryId . "', path_id='" . (int)$pathId . "', level='" . (int)$level . "'");
        }
    }

    private function getSeoUrls($query) {
        $result = array();
        if (!$this->tableExists('seo_url')) { return $result; }
        foreach ($this->db->query("SELECT store_id, language_id, keyword FROM `" . DB_PREFIX . "seo_url` WHERE query='" . $this->db->escape($query) . "'")->rows as $row) {
            $result[(int)$row['store_id']][(int)$row['language_id']] = $row['keyword'];
        }
        return $result;
    }

    private function mergeSeoUrlsFromPayload($existing, $payload, $defaultLanguageId, $storeIds, $currentQuery, $policy, $ignoreEmpty, &$warnings, &$errors) {
        $policy = in_array((string)$policy, array('preserve','overwrite','fill_empty','replace','clear'), true) ? (string)$policy : 'fill_empty';
        if ($policy === 'preserve') { return is_array($existing) ? $existing : array(); }

        $byStore = array();
        $generic = $this->getLocalizedPayloadValues($payload, 'seo_keyword', $defaultLanguageId);
        foreach ((array)$storeIds as $storeId) {
            if ($generic) { $byStore[(int)$storeId] = $generic; }
        }

        $languageMap = $this->getLanguageTokenMap();
        $validStores = $this->getValidStoreMap();
        foreach ($payload as $key => $value) {
            if ($key === 'raw' || !is_string($key)) { continue; }
            $upper = strtoupper($key);
            if (!preg_match('/^_SEO_KEYWORD_STORE[=_-]?(\d+)_LANG(?:UAGE)?[=_-]?([A-Z0-9-]+)_$/', $upper, $match)) { continue; }
            $storeId = (int)$match[1];
            $token = strtoupper($match[2]);
            if (!isset($validStores[$storeId])) {
                $warnings[] = 'Store ID ' . $storeId . ' в SEO-колонке не существует и пропущен.';
                continue;
            }
            if (!isset($languageMap[$token])) {
                $warnings[] = 'Язык ' . $token . ' в SEO-колонке не найден и пропущен.';
                continue;
            }
            $languageId = (int)$languageMap[$token];
            if (!isset($byStore[$storeId])) { $byStore[$storeId] = array(); }
            $byStore[$storeId][$languageId] = array('present' => true, 'value' => (string)$value);
        }

        foreach ($byStore as $storeId => $localizedInput) {
            $existing = $this->mergeSeoUrls($existing, $localizedInput, array((int)$storeId), $currentQuery, $policy, $ignoreEmpty, $warnings, $errors);
        }
        return $existing;
    }

    private function mergeSeoUrls($existing, $localizedInput, $storeIds, $currentQuery, $policy, $ignoreEmpty, &$warnings, &$errors) {
        if (!$this->tableExists('seo_url')) {
            $warnings[] = 'Таблица seo_url отсутствует; SEO URL пропущены.';
            return is_array($existing) ? $existing : array();
        }
        if (!is_array($existing)) { $existing = array(); }
        foreach ($storeIds as $storeId) {
            foreach ($localizedInput as $languageId => $valueInfo) {
                if (!$valueInfo['present']) { continue; }
                $keyword = trim((string)$valueInfo['value']);
                $currentKeyword = isset($existing[$storeId][$languageId]) ? (string)$existing[$storeId][$languageId] : '';

                if ($policy === 'fill_empty' && $currentKeyword !== '') { continue; }
                if ($policy === 'clear') {
                    unset($existing[$storeId][$languageId]);
                    continue;
                }
                if ($keyword === '' && $ignoreEmpty) { continue; }
                if ($keyword === '') {
                    unset($existing[$storeId][$languageId]);
                    continue;
                }

                $validation = $this->validateSeoKeyword($keyword);
                if ($validation !== '') {
                    $errors[] = $validation;
                    continue;
                }
                $sql = "SELECT query FROM `" . DB_PREFIX . "seo_url` WHERE store_id='" . (int)$storeId . "' AND language_id='" . (int)$languageId . "' AND keyword='" . $this->db->escape($keyword) . "'";
                if ($currentQuery !== '') {
                    $sql .= " AND query<>'" . $this->db->escape($currentQuery) . "'";
                }
                $sql .= ' LIMIT 1';
                $conflict = $this->db->query($sql);
                if ($conflict->num_rows) {
                    $errors[] = 'SEO slug уже используется: ' . $keyword . '.';
                    continue;
                }
                if (!isset($existing[$storeId])) { $existing[$storeId] = array(); }
                $existing[$storeId][(int)$languageId] = $keyword;
            }
        }
        return $existing;
    }

    private function validateSeoKeyword($keyword) {
        $keyword = trim((string)$keyword);
        if ($keyword === '') { return ''; }
        if (strlen($keyword) > 255) { return 'SEO slug длиннее 255 байт: ' . $keyword . '.'; }
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $keyword) || strpos($keyword, '/') !== false || preg_match('/\.html?$/i', $keyword)) {
            return 'SEO URL должен содержать только конечный slug без домена, пути, слешей и .html: ' . $keyword . '.';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $keyword)) { return 'SEO slug содержит управляющие символы.'; }
        if (preg_match('/\s/u', $keyword)) { return 'SEO slug не должен содержать пробелы: ' . $keyword . '.'; }
        return '';
    }

    private function prepareImageValue($value, $options, $dryRun, &$warnings) {
        $value = trim((string)$value);
        if ($value === '') { return array('ok' => true, 'value' => ''); }
        if (preg_match('/^https?:\/\//i', $value)) {
            if ((string)$this->getOption($options, 'download_images', '0') !== '1') {
                $warnings[] = 'Удалённый URL изображения пропущен: OpenCart ожидает локальный путь внутри DIR_IMAGE. Включите безопасное скачивание изображений.';
                return array('ok' => false, 'value' => '');
            }
            if ($dryRun) {
                $validation = $this->validateRemoteImageUrl($value);
                if ($validation['ok']) {
                    return array('ok' => true, 'value' => 'catalog/import/dry-run-image.jpg');
                }
                $warnings[] = $validation['error'];
                return array('ok' => false, 'value' => '');
            }
            $download = $this->downloadImageSecure($value, $this->getOption($options, 'image_directory', 'catalog/import'), (int)$this->getOption($options, 'max_image_bytes', 10485760));
            if (!$download['ok']) {
                $warnings[] = $download['error'];
                return array('ok' => false, 'value' => '');
            }
            return array('ok' => true, 'value' => $download['path']);
        }

        $path = str_replace('\\', '/', rawurldecode($value));
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $path) || strpos($path, ':') !== false) {
            $warnings[] = 'Небезопасный путь изображения пропущен: ' . $value . '.';
            return array('ok' => false, 'value' => '');
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, array('jpg','jpeg','png','gif','webp','avif'), true)) {
            $warnings[] = 'Недопустимое расширение изображения: ' . $value . '.';
            return array('ok' => false, 'value' => '');
        }
        if (defined('DIR_IMAGE') && !is_file(DIR_IMAGE . $path)) {
            $warnings[] = 'Локальный файл изображения пока не найден: ' . $path . '.';
        }
        return array('ok' => true, 'value' => $path);
    }

    private function downloadImageSecure($url, $directory, $maxBytes) {
        if (!defined('DIR_IMAGE')) { return array('ok' => false, 'error' => 'DIR_IMAGE не определён.', 'path' => ''); }
        if (!function_exists('curl_init')) { return array('ok' => false, 'error' => 'Для безопасного скачивания изображений требуется расширение cURL.', 'path' => ''); }
        $maxBytes = max(102400, min(52428800, (int)$maxBytes));
        $directory = $this->sanitizeImageDirectory($directory);
        if ($directory === '') { return array('ok' => false, 'error' => 'Недопустимый каталог для изображений.', 'path' => ''); }
        $targetDirectory = rtrim(DIR_IMAGE, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory) . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0755, true)) {
            return array('ok' => false, 'error' => 'Не удалось создать каталог изображений: ' . $directory . '.', 'path' => '');
        }
        if (!is_writable($targetDirectory)) {
            return array('ok' => false, 'error' => 'Каталог изображений недоступен для записи: ' . $directory . '.', 'path' => '');
        }
        $imageRoot = realpath(rtrim(DIR_IMAGE, '/\\'));
        $realTargetDirectory = realpath($targetDirectory);
        if ($imageRoot === false || $realTargetDirectory === false) {
            return array('ok' => false, 'error' => 'Не удалось проверить каталог изображений.', 'path' => '');
        }
        $imageRootNormalized = rtrim(str_replace('\\', '/', $imageRoot), '/') . '/';
        $targetNormalized = rtrim(str_replace('\\', '/', $realTargetDirectory), '/') . '/';
        if (strpos($targetNormalized, $imageRootNormalized) !== 0) {
            return array('ok' => false, 'error' => 'Каталог изображений выходит за пределы DIR_IMAGE.', 'path' => '');
        }

        $currentUrl = $url;
        $body = '';
        $contentType = '';
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $validation = $this->validateRemoteImageUrl($currentUrl);
            if (!$validation['ok']) { return array('ok' => false, 'error' => $validation['error'], 'path' => ''); }
            $body = '';
            $headers = array();
            $tooLarge = false;
            $ch = curl_init($currentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Catalog-Import-PRO/' . self::VERSION);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: image/avif,image/webp,image/png,image/jpeg,image/gif;q=0.9,*/*;q=0.1'));
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            if (!empty($validation['resolve'])) {
                curl_setopt($ch, CURLOPT_RESOLVE, array($validation['resolve']));
            }
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $line) use (&$headers, &$tooLarge, $maxBytes) {
                $length = strlen($line);
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $value = trim(substr($line, $position + 1));
                    $headers[$name] = $value;
                    if ($name === 'content-length' && (int)$value > $maxBytes) { $tooLarge = true; }
                }
                return $length;
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $chunk) use (&$body, &$tooLarge, $maxBytes) {
                if ($tooLarge || strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            });
            $ok = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            unset($ch);

            if ($tooLarge) { return array('ok' => false, 'error' => 'Удалённое изображение превышает лимит ' . $maxBytes . ' байт.', 'path' => ''); }
            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                if ($redirect >= 3) { return array('ok' => false, 'error' => 'Слишком много перенаправлений при скачивании изображения.', 'path' => ''); }
                $currentUrl = $this->resolveRedirectUrl($currentUrl, $headers['location']);
                continue;
            }
            if ($ok === false || $status < 200 || $status >= 300) {
                return array('ok' => false, 'error' => 'Не удалось скачать изображение. HTTP ' . $status . ($curlError ? ': ' . $curlError : '') . '.', 'path' => '');
            }
            break;
        }

        if ($body === '') { return array('ok' => false, 'error' => 'Сервер вернул пустое изображение.', 'path' => ''); }
        $mime = $this->detectImageMime($body, $contentType);
        $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif');
        if (!isset($extensions[$mime]) || !$this->isValidImageBinary($body, $mime)) {
            return array('ok' => false, 'error' => 'Ответ URL не является корректным поддерживаемым изображением. MIME: ' . ($mime ?: 'неизвестен') . '.', 'path' => '');
        }
        $fileName = 'import_' . substr(hash('sha256', $currentUrl . "\0" . $body), 0, 24) . '.' . $extensions[$mime];
        $target = $targetDirectory . $fileName;
        $relative = $directory . '/' . $fileName;
        if (is_file($target) && filesize($target) > 0) {
            return array('ok' => true, 'error' => '', 'path' => $relative);
        }
        $temporary = $target . '.tmp.' . substr(sha1(uniqid('', true)), 0, 8);
        if (@file_put_contents($temporary, $body, LOCK_EX) === false) {
            return array('ok' => false, 'error' => 'Не удалось записать временный файл изображения.', 'path' => '');
        }
        @chmod($temporary, 0644);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            return array('ok' => false, 'error' => 'Не удалось атомарно сохранить изображение.', 'path' => '');
        }
        return array('ok' => true, 'error' => '', 'path' => $relative);
    }

    private function validateRemoteImageUrl($url) {
        $url = trim((string)$url);
        if ($url === '' || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7F]/', $url)) {
            return array('ok' => false, 'error' => 'URL изображения пустой, слишком длинный или содержит недопустимые символы.', 'resolve' => '');
        }
        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return array('ok' => false, 'error' => 'Некорректный URL изображения.', 'resolve' => '');
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, array('http','https'), true)) {
            return array('ok' => false, 'error' => 'Разрешены только HTTP и HTTPS URL изображений.', 'resolve' => '');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return array('ok' => false, 'error' => 'URL изображения с учётными данными запрещён.', 'resolve' => '');
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, array(80,443), true)) {
            return array('ok' => false, 'error' => 'URL изображения использует запрещённый порт.', 'resolve' => '');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === 'localhost' || substr($host, -6) === '.local') {
            return array('ok' => false, 'error' => 'Локальные адреса запрещены для скачивания изображений.', 'resolve' => '');
        }
        $ips = $this->resolveHostIps($host);
        if (!$ips) { return array('ok' => false, 'error' => 'Не удалось определить IP-адрес сервера изображения.', 'resolve' => ''); }
        $selectedIp = '';
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return array('ok' => false, 'error' => 'URL изображения ведёт на частный или служебный IP-адрес.', 'resolve' => '');
            }
            if ($selectedIp === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $selectedIp = $ip; }
        }
        $resolveIp = strpos($selectedIp, ':') !== false ? '[' . $selectedIp . ']' : $selectedIp;
        return array('ok' => true, 'error' => '', 'resolve' => $host . ':' . $port . ':' . $resolveIp);
    }

    private function resolveHostIps($host) {
        if (filter_var($host, FILTER_VALIDATE_IP)) { return array($host); }
        $ips = array();
        $ipv4 = @gethostbynamel($host);
        if (is_array($ipv4)) { $ips = array_merge($ips, $ipv4); }
        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) { if (!empty($record['ipv6'])) { $ips[] = $record['ipv6']; } }
            }
        }
        return array_values(array_unique($ips));
    }

    private function isPublicIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function detectImageMime($body, $headerContentType) {
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->buffer($body);
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $headerContentType = strtolower(trim(strtok((string)$headerContentType, ';')));
            if (strpos($headerContentType, 'image/') === 0) { $mime = $headerContentType; }
        }
        return strtolower($mime);
    }

    private function isValidImageBinary($body, $mime) {
        $length = strlen((string)$body);
        if ($length < 12) { return false; }
        if ($mime === 'image/jpeg') { return substr($body, 0, 3) === "\xFF\xD8\xFF"; }
        if ($mime === 'image/png') { return substr($body, 0, 8) === "\x89PNG\r\n\x1A\n"; }
        if ($mime === 'image/gif') { return in_array(substr($body, 0, 6), array('GIF87a', 'GIF89a'), true); }
        if ($mime === 'image/webp') { return substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP'; }
        if ($mime === 'image/avif') {
            if ($length < 16 || substr($body, 4, 4) !== 'ftyp') { return false; }
            $brands = substr($body, 8, min(56, $length - 8));
            return strpos($brands, 'avif') !== false || strpos($brands, 'avis') !== false;
        }
        return false;
    }

    private function sanitizeImageDirectory($directory) {
        $directory = str_replace('\\', '/', trim((string)$directory));
        $directory = trim($directory, '/');
        if ($directory === '') { $directory = 'catalog/import'; }
        if (strpos($directory, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $directory) || !preg_match('#^catalog(?:/[a-zA-Z0-9_-]+)*$#', $directory)) {
            return '';
        }
        return $directory;
    }

    private function resolveRedirectUrl($baseUrl, $location) {
        $location = trim((string)$location);
        if (preg_match('/^https?:\/\//i', $location)) { return $location; }
        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) { return $location; }
        $port = isset($base['port']) ? ':' . (int)$base['port'] : '';
        if (substr($location, 0, 2) === '//') { return $base['scheme'] . ':' . $location; }
        if (substr($location, 0, 1) === '/') { return $base['scheme'] . '://' . $base['host'] . $port . $location; }
        $path = isset($base['path']) ? $base['path'] : '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $base['scheme'] . '://' . $base['host'] . $port . ($directory ? $directory . '/' : '/') . $location;
    }

    private function rowToPayload($entityType, $row, $headers, $fields, $options) {
        $payload = array('raw' => array());
        $columnCount = count($headers);
        for ($index = 0; $index < $columnCount; $index++) {
            $header = isset($headers[$index]) ? trim((string)$headers[$index]) : '';
            if ($header === '') { continue; }
            $value = isset($row[$index]) ? (string)$row[$index] : '';
            $normalized = isset($fields[$index]) ? $fields[$index] : $this->normalizeFieldName($header);
            if ($normalized === '' || !$this->isProfileFieldAllowed($entityType, $normalized, $options)) { continue; }
            $configuredKey = $this->canonicalKeyField($entityType, $this->getOption($options, 'key_field', '_ID_'));
            if ($this->canonicalKeyField($entityType, $normalized) !== $configuredKey && $this->getFieldRule($options, array($normalized), 'overwrite') === 'preserve') { continue; }
            $payload[$normalized] = $value;
            $payload['raw'][$normalized] = $value;
        }
        return $payload;
    }

    private function buildFieldMap($headers, $options = array()) {
        $map = array();
        $custom = isset($options['column_map']) && is_array($options['column_map']) ? $options['column_map'] : array();
        foreach ($headers as $index => $header) {
            $source = $this->normalizeFieldName($header);
            $lookup = $this->textLower(trim((string)$header));
            $sourceLookup = $this->textLower($source);
            $target = isset($custom[$lookup]) ? $custom[$lookup] : (isset($custom[$sourceLookup]) ? $custom[$sourceLookup] : $source);
            $map[$index] = $this->normalizeFieldName($target);
        }
        return $map;
    }

    private function validateMappedHeaders($entityType, $fields, $options) {
        $entityType = $this->normalizeEntityType($entityType);
        $normalizedFields = array();
        $seen = array();
        foreach ((array)$fields as $field) {
            $field = $this->normalizeFieldName($field);
            if ($field === '') { continue; }
            $lookup = $this->textLower($field);
            if (isset($seen[$lookup])) {
                return 'После сопоставления несколько колонок CSV указывают на одно поле ' . $field . '. Оставьте только одну колонку или измените правила сопоставления.';
            }
            $seen[$lookup] = true;
            $normalizedFields[] = $field;
        }

        $configuredKey = $this->canonicalKeyField($entityType, $this->getOption($options, 'key_field', '_ID_'));
        $hasKey = false;
        foreach ($normalizedFields as $field) {
            if ($this->canonicalKeyField($entityType, $field) === $configuredKey) {
                $hasKey = true;
                break;
            }
        }
        if (!$hasKey) {
            return 'CSV не содержит выбранного ключевого поля ' . $configuredKey . ' после сопоставления колонок. Резервный поиск полностью запрещён.';
        }

        $keyRule = $this->getFieldRule($options, $this->getKeyFieldAliases($entityType, $configuredKey), 'overwrite');
        $mode = $this->normalizeMode($this->getOption($options, 'mode', 'upsert'));
        if ($keyRule === 'clear') {
            return 'Выбранное ключевое поле ' . $configuredKey . ' нельзя очищать: после импорта запись потеряет стабильный идентификатор.';
        }
        if ($keyRule === 'preserve' && in_array($mode, array('add', 'upsert'), true)) {
            return 'Для режима создания поле ' . $configuredKey . ' нельзя помечать «Не трогать»: ключ должен быть записан в новую запись.';
        }

        $restrictedClear = array(
            'product' => array('_NAME_' => 'Название товара', '_MODEL_' => 'Модель товара', '_DATE_AVAILABLE_' => 'Дата доступности товара'),
            'category' => array('_NAME_' => 'Название категории'),
            'manufacturer' => array('_NAME_' => 'Название производителя'),
            'option' => array()
        );
        foreach ($restrictedClear[$entityType] as $field => $label) {
            if ($this->getFieldRule($options, array($field), 'overwrite') === 'clear') {
                return $label . ' является обязательным полем OpenCart и не может использовать политику «Очистить».';
            }
        }

        if ($entityType !== 'product') { return ''; }

        $missingAction = (string)$this->getOption($options, 'sync_missing_action', 'none');
        $supplierCode = trim((string)$this->getOption($options, 'supplier_code', ''));
        if ($supplierCode !== '' && !preg_match('/^[a-z0-9._-]{2,64}$/', $supplierCode)) {
            return 'Код поставщика должен содержать 2–64 строчные латинские буквы, цифры, точку, дефис или подчёркивание.';
        }
        if ($missingAction !== 'none') {
            if ($supplierCode === '') {
                return 'Для обработки отсутствующих товаров укажите постоянный код поставщика.';
            }
            if ($configuredKey === '_NAME_') {
                return 'Синхронизация отсутствующих товаров по названию запрещена. Используйте стабильный ключ SKU, модель, EAN или ID.';
            }
            if ((string)$this->getOption($options, 'sync_missing_confirm', '0') !== '1') {
                return 'Подтвердите, что обработка отсутствующих товаров ограничена выбранным поставщиком.';
            }
            if ((string)$this->getOption($options, 'dry_run', '1') !== '1') {
                return 'Обработка отсутствующих товаров разрешена только через обязательный тестовый запуск и ручное подтверждение строк.';
            }
            $mode = $this->normalizeMode($this->getOption($options, 'mode', 'upsert'));
            if (in_array($mode, array('add','delete'), true)) {
                return 'Обработка отсутствующих товаров недоступна в режимах только добавления и удаления. Используйте обновление или добавление с обновлением.';
            }
        }

        $profile = (string)$this->getOption($options, 'import_profile', 'custom');
        $hasPrice = $this->mappedFieldExists($normalizedFields, array('_PRICE_', 'price'));
        $hasQuantity = $this->mappedFieldExists($normalizedFields, array('_QUANTITY_', 'quantity'));
        $hasStockStatus = $this->mappedFieldExists($normalizedFields, array('_STOCK_STATUS_ID_', 'stock_status_id'));
        if ($profile === 'price_only' && !$hasPrice) {
            return 'Профиль «Только цены» требует колонку _PRICE_ после сопоставления.';
        }
        if ($profile === 'stock_only' && !$hasQuantity && !$hasStockStatus) {
            return 'Профиль «Только остатки» требует колонку _QUANTITY_ или _STOCK_STATUS_ID_ после сопоставления.';
        }
        if ($profile === 'price_stock' && (!$hasPrice || (!$hasQuantity && !$hasStockStatus))) {
            return 'Профиль «Цены и остатки» требует _PRICE_ и хотя бы одну колонку _QUANTITY_ или _STOCK_STATUS_ID_ после сопоставления.';
        }
        return '';
    }

    private function mappedFieldExists($fields, $aliases) {
        $wanted = array();
        foreach ((array)$aliases as $alias) { $wanted[$this->textLower($this->normalizeFieldName($alias))] = true; }
        foreach ((array)$fields as $field) {
            if (isset($wanted[$this->textLower($this->normalizeFieldName($field))])) { return true; }
        }
        return false;
    }

    private function isProfileFieldAllowed($entityType, $field, $options) {
        if ($entityType !== 'product') { return true; }
        $profile = (string)$this->getOption($options, 'import_profile', 'custom');
        if (!in_array($profile, array('price_only', 'stock_only', 'price_stock'), true)) { return true; }
        $field = $this->normalizeFieldName($field);
        $keyField = $this->canonicalKeyField('product', $this->getOption($options, 'key_field', '_SKU_'));
        $allowed = $this->getKeyFieldAliases('product', $keyField);
        if ($profile === 'price_only' || $profile === 'price_stock') {
            $allowed = array_merge($allowed, array('_PRICE_', 'price'));
        }
        if ($profile === 'stock_only' || $profile === 'price_stock') {
            $allowed = array_merge($allowed, array('_QUANTITY_', 'quantity', '_STOCK_STATUS_ID_', 'stock_status_id'));
        }
        foreach ($allowed as $candidate) {
            if ($field === $this->normalizeFieldName($candidate)) { return true; }
        }
        return false;
    }

    private function getFieldRule($options, $keys, $default) {
        $rules = isset($options['field_rules']) && is_array($options['field_rules']) ? $options['field_rules'] : array();
        foreach ((array)$keys as $key) {
            $normalized = $this->textLower($this->normalizeFieldName($key));
            if (isset($rules[$normalized])) {
                $rule = (string)$rules[$normalized];
                if ($rule === 'create_only') {
                    return $this->currentEntityIsNew === false ? 'preserve' : 'overwrite';
                }
                return in_array($rule, array('preserve','overwrite','fill_empty','merge','replace','clear'), true) ? $rule : $default;
            }
        }
        return $default;
    }

    private function isStoredValueEmpty($value) {
        if (is_array($value)) { return count($value) === 0; }
        return $value === null || trim((string)$value) === '';
    }

    private function clearValueForType($type) {
        if (in_array($type, array('int','decimal','bool'), true)) { return 0; }
        return '';
    }

    private function canonicalKeyField($entityType, $field) {
        $entityType = $this->normalizeEntityType($entityType);
        $field = $this->normalizeFieldName($field);
        $lower = strtolower($field);

        $maps = array(
            'product' => array(
                '_id_' => '_ID_', '_product_id_' => '_ID_', 'id' => '_ID_', 'product_id' => '_ID_',
                '_model_' => '_MODEL_', 'model' => '_MODEL_',
                '_sku_' => '_SKU_', 'sku' => '_SKU_',
                '_upc_' => '_UPC_', 'upc' => '_UPC_',
                '_ean_' => '_EAN_', 'ean' => '_EAN_',
                '_jan_' => '_JAN_', 'jan' => '_JAN_',
                '_isbn_' => '_ISBN_', 'isbn' => '_ISBN_',
                '_mpn_' => '_MPN_', 'mpn' => '_MPN_',
                '_name_' => '_NAME_', 'name' => '_NAME_', 'product_name' => '_NAME_'
            ),
            'category' => array(
                '_id_' => '_ID_', '_category_id_' => '_ID_', 'id' => '_ID_', 'category_id' => '_ID_',
                '_category_' => '_CATEGORY_', 'category' => '_CATEGORY_', 'category_path' => '_CATEGORY_', 'path' => '_CATEGORY_',
                '_name_' => '_NAME_', 'name' => '_NAME_', 'category_name' => '_NAME_'
            ),
            'manufacturer' => array(
                '_id_' => '_ID_', '_manufacturer_id_' => '_ID_', 'id' => '_ID_', 'manufacturer_id' => '_ID_',
                '_name_' => '_NAME_', 'name' => '_NAME_', 'manufacturer' => '_NAME_', 'manufacturer_name' => '_NAME_'
            ),
            'option' => array(
                '_product_id_' => '_PRODUCT_ID_', '_id_' => '_PRODUCT_ID_', 'id' => '_PRODUCT_ID_', 'product_id' => '_PRODUCT_ID_',
                '_product_model_' => '_PRODUCT_MODEL_', '_model_' => '_PRODUCT_MODEL_', 'product_model' => '_PRODUCT_MODEL_', 'model' => '_PRODUCT_MODEL_',
                '_product_sku_' => '_PRODUCT_SKU_', '_sku_' => '_PRODUCT_SKU_', 'product_sku' => '_PRODUCT_SKU_', 'sku' => '_PRODUCT_SKU_',
                '_product_name_' => '_PRODUCT_NAME_', '_name_' => '_PRODUCT_NAME_', 'product_name' => '_PRODUCT_NAME_', 'name' => '_PRODUCT_NAME_'
            )
        );

        return isset($maps[$entityType][$lower]) ? $maps[$entityType][$lower] : $field;
    }

    private function getKeyFieldAliases($entityType, $field) {
        $field = $this->canonicalKeyField($entityType, $field);
        $aliases = array(
            'product' => array(
                '_ID_' => array('_ID_', '_PRODUCT_ID_', 'id', 'product_id'),
                '_MODEL_' => array('_MODEL_', 'model'),
                '_SKU_' => array('_SKU_', 'sku'),
                '_UPC_' => array('_UPC_', 'upc'),
                '_EAN_' => array('_EAN_', 'ean'),
                '_JAN_' => array('_JAN_', 'jan'),
                '_ISBN_' => array('_ISBN_', 'isbn'),
                '_MPN_' => array('_MPN_', 'mpn'),
                '_NAME_' => array('_NAME_', 'name', 'product_name')
            ),
            'category' => array(
                '_ID_' => array('_ID_', '_CATEGORY_ID_', 'id', 'category_id'),
                '_CATEGORY_' => array('_CATEGORY_', 'category', 'category_path', 'path'),
                '_NAME_' => array('_NAME_', 'name', 'category_name')
            ),
            'manufacturer' => array(
                '_ID_' => array('_ID_', '_MANUFACTURER_ID_', 'id', 'manufacturer_id'),
                '_NAME_' => array('_NAME_', 'name', 'manufacturer', 'manufacturer_name')
            ),
            'option' => array(
                '_PRODUCT_ID_' => array('_PRODUCT_ID_', '_ID_', 'product_id', 'id'),
                '_PRODUCT_MODEL_' => array('_PRODUCT_MODEL_', '_MODEL_', 'product_model', 'model'),
                '_PRODUCT_SKU_' => array('_PRODUCT_SKU_', '_SKU_', 'product_sku', 'sku'),
                '_PRODUCT_NAME_' => array('_PRODUCT_NAME_', '_NAME_', 'product_name', 'name')
            )
        );
        return isset($aliases[$entityType][$field]) ? $aliases[$entityType][$field] : array($field);
    }

    private function getQueueKey($entityType, $payload, $options) {
        $configured = $this->canonicalKeyField($entityType, $this->getOption($options, 'key_field', '_ID_'));
        $value = trim((string)$this->payloadGet($payload, $this->getKeyFieldAliases($entityType, $configured), ''));
        return array('field' => $configured, 'value' => $value);
    }

    private function findEntityId($entityType, $keyField, $keyValue, $payload, $options) {
        $match = $this->findEntityMatch($entityType, $keyField, $keyValue, $payload, $options);
        return $match['error'] === '' ? (int)$match['id'] : 0;
    }

    private function findEntityMatch($entityType, $keyField, $keyValue, $payload, $options) {
        $keyField = $this->canonicalKeyField($entityType, $keyField);
        $keyValue = trim((string)$keyValue);
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        if ($keyValue === '') { return array('id' => 0, 'error' => ''); }

        if ($entityType === 'option') {
            return $this->findProductMatchForOptionKey($keyField, $keyValue, $options);
        }

        if ($keyField === '_ID_') {
            $table = $entityType;
            $idField = $entityType . '_id';
            $id = (int)$keyValue;
            return array('id' => $this->idExists($table, $idField, $id) ? $id : 0, 'error' => '');
        }

        $sql = '';
        if ($entityType === 'product') {
            $columnMap = array('_MODEL_' => 'model', '_SKU_' => 'sku', '_UPC_' => 'upc', '_EAN_' => 'ean', '_JAN_' => 'jan', '_ISBN_' => 'isbn', '_MPN_' => 'mpn');
            if (isset($columnMap[$keyField])) {
                $column = $columnMap[$keyField];
                $sql = "SELECT product_id AS entity_id FROM `" . DB_PREFIX . "product` WHERE `" . $column . "`='" . $this->db->escape($keyValue) . "' LIMIT 2";
            } elseif ($keyField === '_NAME_') {
                $sql = "SELECT product_id AS entity_id FROM `" . DB_PREFIX . "product_description` WHERE language_id='" . (int)$languageId . "' AND name='" . $this->db->escape($keyValue) . "' LIMIT 2";
            }
        } elseif ($entityType === 'category') {
            if ($keyField === '_CATEGORY_') {
                return $this->findCategoryPathMatch($keyValue, $options);
            }
            if ($keyField === '_NAME_') {
                $sql = "SELECT category_id AS entity_id FROM `" . DB_PREFIX . "category_description` WHERE language_id='" . (int)$languageId . "' AND name='" . $this->db->escape($keyValue) . "' LIMIT 2";
            }
        } elseif ($entityType === 'manufacturer' && $keyField === '_NAME_') {
            $sql = "SELECT manufacturer_id AS entity_id FROM `" . DB_PREFIX . "manufacturer` WHERE name='" . $this->db->escape($keyValue) . "' LIMIT 2";
        }

        if ($sql === '') { return array('id' => 0, 'error' => 'Неподдерживаемое ключевое поле: ' . $keyField . '.'); }
        $query = $this->db->query($sql);
        if ($query->num_rows > 1) {
            return array('id' => 0, 'error' => 'Ключ не уникален и соответствует нескольким записям: ' . $keyValue . '. Используйте ID или уникальный артикул.');
        }
        return array('id' => $query->num_rows ? (int)$query->row['entity_id'] : 0, 'error' => '');
    }

    private function findProductIdForOptionPayload($payload, $options) {
        $pairs = array(
            '_PRODUCT_ID_' => $this->payloadGet($payload, $this->getKeyFieldAliases('option', '_PRODUCT_ID_'), ''),
            '_PRODUCT_MODEL_' => $this->payloadGet($payload, $this->getKeyFieldAliases('option', '_PRODUCT_MODEL_'), ''),
            '_PRODUCT_SKU_' => $this->payloadGet($payload, $this->getKeyFieldAliases('option', '_PRODUCT_SKU_'), ''),
            '_PRODUCT_NAME_' => $this->payloadGet($payload, $this->getKeyFieldAliases('option', '_PRODUCT_NAME_'), '')
        );
        foreach ($pairs as $field => $value) {
            if ($value === '') { continue; }
            $id = $this->findProductIdForOptionKey($field, $value, $options);
            if ($id) { return $id; }
        }
        return 0;
    }

    private function findProductIdForOptionKey($keyField, $keyValue, $options) {
        $match = $this->findProductMatchForOptionKey($keyField, $keyValue, $options);
        return $match['error'] === '' ? (int)$match['id'] : 0;
    }

    private function findProductMatchForOptionKey($keyField, $keyValue, $options) {
        $keyField = $this->canonicalKeyField('option', $keyField);
        $keyValue = trim((string)$keyValue);
        if ($keyValue === '') { return array('id' => 0, 'error' => ''); }
        if ($keyField === '_PRODUCT_ID_') {
            if (!preg_match('/^\d+$/', $keyValue)) {
                return array('id' => 0, 'error' => 'ID товара должен быть положительным целым числом.');
            }
            $id = (int)$keyValue;
            return array('id' => $this->idExists('product', 'product_id', $id) ? $id : 0, 'error' => '');
        }

        $sql = '';
        if ($keyField === '_PRODUCT_MODEL_') {
            $sql = "SELECT product_id AS entity_id FROM `" . DB_PREFIX . "product` WHERE model='" . $this->db->escape($keyValue) . "' LIMIT 2";
        } elseif ($keyField === '_PRODUCT_SKU_') {
            $sql = "SELECT product_id AS entity_id FROM `" . DB_PREFIX . "product` WHERE sku='" . $this->db->escape($keyValue) . "' LIMIT 2";
        } elseif ($keyField === '_PRODUCT_NAME_') {
            $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
            $sql = "SELECT product_id AS entity_id FROM `" . DB_PREFIX . "product_description` WHERE language_id='" . (int)$languageId . "' AND name='" . $this->db->escape($keyValue) . "' LIMIT 2";
        }
        if ($sql === '') {
            return array('id' => 0, 'error' => 'Неподдерживаемое ключевое поле товара для опции: ' . $keyField . '.');
        }
        $query = $this->db->query($sql);
        if ($query->num_rows > 1) {
            return array('id' => 0, 'error' => 'Ключ товара не уникален и соответствует нескольким товарам: ' . $keyValue . '. Используйте _PRODUCT_ID_ или уникальный артикул.');
        }
        return array('id' => $query->num_rows ? (int)$query->row['entity_id'] : 0, 'error' => '');
    }

    private function findCategoryByPath($path, $options) {
        $match = $this->findCategoryPathMatch($path, $options);
        return $match['error'] === '' ? (int)$match['id'] : 0;
    }

    private function findCategoryPathMatch($path, $options) {
        $separator = trim((string)$this->getOption($options, 'category_path_separator', '>'));
        if ($separator === '') { $separator = '>'; }
        $parts = $this->splitPath($path, $separator);
        if (!$parts) { return array('id' => 0, 'error' => ''); }
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $parentId = 0;
        foreach ($parts as $name) {
            $query = $this->db->query("SELECT c.category_id AS entity_id FROM `" . DB_PREFIX . "category` c INNER JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id=c.category_id) WHERE c.parent_id='" . (int)$parentId . "' AND cd.language_id='" . (int)$languageId . "' AND cd.name='" . $this->db->escape($name) . "' LIMIT 2");
            if ($query->num_rows > 1) {
                return array('id' => 0, 'error' => 'Путь категории неоднозначен: на одном уровне найдено несколько категорий с названием «' . $name . '». Используйте _ID_ или устраните дубли.');
            }
            if ($query->num_rows === 0) { return array('id' => 0, 'error' => ''); }
            $parentId = (int)$query->row['entity_id'];
        }
        return array('id' => $parentId, 'error' => '');
    }

    private function enqueueMissingSupplierProducts($batchId, $options, $csvRowNumber) {
        $supplierCode = trim((string)$this->getOption($options, 'supplier_code', ''));
        $action = (string)$this->getOption($options, 'sync_missing_action', 'none');
        $keyField = $this->canonicalKeyField('product', $this->getOption($options, 'key_field', '_SKU_'));
        if (!preg_match('/^[A-Za-z0-9._-]{2,64}$/', $supplierCode) || !in_array($action, array('disable','zero','delete'), true)) {
            return array('total_added' => 0, 'queued_added' => 0, 'error_added' => 0, 'csv_row_number' => (int)$csvRowNumber);
        }

        $currentKeyCount = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::QUEUE_TABLE . "`
            WHERE batch_id='" . $this->db->escape($batchId) . "'
              AND entity_type='product'
              AND key_field='" . $this->db->escape($keyField) . "'
              AND key_value<>''")->row['total'];
        if ($currentKeyCount <= 0) {
            $csvRowNumber++;
            $this->insertQueueItem($batchId, 'product', $csvRowNumber, $keyField, '', 0, array('_SYNC_MISSING_' => '1'), 'error', 'Синхронизация отсутствующих товаров отменена: CSV не содержит ни одной строки с действительным выбранным ключом.', 0);
            return array('total_added' => 1, 'queued_added' => 0, 'error_added' => 1, 'csv_row_number' => (int)$csvRowNumber);
        }

        $this->db->query("DELETE sp FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "` sp LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id=sp.product_id) WHERE p.product_id IS NULL");

        $rows = $this->db->query("SELECT sp.supplier_product_id, sp.product_id, sp.external_key
            FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "` sp
            INNER JOIN `" . DB_PREFIX . "product` p ON (p.product_id=sp.product_id)
            WHERE sp.supplier_code='" . $this->db->escape($supplierCode) . "'
              AND sp.key_field='" . $this->db->escape($keyField) . "'
              AND sp.is_active='1'
              AND NOT EXISTS (
                  SELECT 1 FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` q
                  WHERE q.batch_id='" . $this->db->escape($batchId) . "'
                    AND q.entity_type='product'
                    AND q.key_field='" . $this->db->escape($keyField) . "'
                    AND q.key_value=sp.external_key
              )
            ORDER BY sp.supplier_product_id ASC")->rows;

        $added = 0;
        foreach ($rows as $row) {
            $csvRowNumber++;
            $payload = array(
                '_SYNC_MISSING_' => '1',
                '_SYNC_MISSING_ACTION_' => $action,
                '_SUPPLIER_CODE_' => $supplierCode
            );
            $message = 'Товар поставщика отсутствует в новом CSV. Опасное действие не выбрано автоматически и требует отдельного подтверждения.';
            if ($this->insertQueueItem($batchId, 'product', $csvRowNumber, $keyField, (string)$row['external_key'], (int)$row['product_id'], $payload, 'pending', $message, 1)) {
                $added++;
            }
        }

        return array('total_added' => $added, 'queued_added' => $added, 'error_added' => 0, 'csv_row_number' => (int)$csvRowNumber);
    }

    private function recordSupplierProduct($supplierCode, $keyField, $externalKey, $productId, $batchId, $payload = array(), $options = array()) {
        $supplierCode = trim((string)$supplierCode);
        $keyField = $this->canonicalKeyField('product', $keyField);
        $externalKey = trim((string)$externalKey);
        $productId = (int)$productId;
        if (!preg_match('/^[A-Za-z0-9._-]{2,64}$/', $supplierCode) || $externalKey === '' || $productId <= 0) { return; }

        $externalKey = $this->truncate($externalKey, 255);
        $hash = sha1($externalKey);
        $this->db->query("DELETE FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "`
            WHERE supplier_code='" . $this->db->escape($supplierCode) . "'
              AND (product_id='" . (int)$productId . "' OR (key_field='" . $this->db->escape($keyField) . "' AND external_key_hash='" . $this->db->escape($hash) . "'))");
        $this->db->query("INSERT INTO `" . DB_PREFIX . self::SUPPLIER_TABLE . "` SET
            supplier_code='" . $this->db->escape($supplierCode) . "',
            key_field='" . $this->db->escape($keyField) . "',
            external_key='" . $this->db->escape($externalKey) . "',
            external_key_hash='" . $this->db->escape($hash) . "',
            product_id='" . (int)$productId . "',
            is_active='1',
            last_seen_batch_id='" . $this->db->escape($this->truncate($batchId, 40)) . "',
            last_seen_at=NOW(),
            date_added=NOW(),
            date_modified=NOW()");

        $legacySupplierId = (int)$this->getOption($options, 'legacy_supplier_id', 0);
        if ($legacySupplierId > 0 && $this->tableExists('import_pro_supplier_product')) {
            $legacyExternalKey = trim((string)$this->payloadGet($payload, array('_EXTERNAL_PRODUCT_ID_'), $externalKey));
            if ($legacyExternalKey === '') { $legacyExternalKey = $externalKey; }
            $legacyExternalKey = $this->truncate($legacyExternalKey, 255);
            $supplierSku = trim((string)$this->payloadGet($payload, array('_SUPPLIER_SKU_', '_SKU_'), ''));
            $priceRaw = $this->payloadGet($payload, array('_SUPPLIER_PRICE_', '_PRICE_'), '0');
            $quantityRaw = $this->payloadGet($payload, array('_SUPPLIER_QUANTITY_', '_QUANTITY_'), '0');
            $lastPrice = is_numeric(str_replace(',', '.', (string)$priceRaw)) ? (float)str_replace(',', '.', (string)$priceRaw) : 0.0;
            $lastQuantity = is_numeric((string)$quantityRaw) ? (int)$quantityRaw : 0;
            $hashSource = json_encode(array('price' => $lastPrice, 'quantity' => $lastQuantity, 'key' => $legacyExternalKey, 'sku' => $supplierSku), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $lastHash = sha1((string)$hashSource);
            $this->db->query("REPLACE INTO `" . DB_PREFIX . "import_pro_supplier_product` SET
                supplier_id='" . (int)$legacySupplierId . "',
                product_id='" . (int)$productId . "',
                external_product_id='" . $this->db->escape($legacyExternalKey) . "',
                supplier_sku='" . $this->db->escape($supplierSku) . "',
                last_price='" . (float)$lastPrice . "',
                last_quantity='" . (int)$lastQuantity . "',
                last_hash='" . $this->db->escape($lastHash) . "',
                last_seen=NOW()");
        }
    }

    private function deactivateLegacySupplierMapping($options, $productId) {
        $legacySupplierId = (int)$this->getOption($options, 'legacy_supplier_id', 0);
        $productId = (int)$productId;
        if ($legacySupplierId <= 0 || $productId <= 0 || !$this->tableExists('import_pro_supplier_product')) { return; }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_supplier_product` WHERE supplier_id='" . (int)$legacySupplierId . "' AND product_id='" . (int)$productId . "'");
    }

    private function supplierMappingExists($supplierCode, $keyField, $externalKey, $productId) {
        $hash = sha1(trim((string)$externalKey));
        $query = $this->db->query("SELECT supplier_product_id FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "`
            WHERE supplier_code='" . $this->db->escape(trim((string)$supplierCode)) . "'
              AND key_field='" . $this->db->escape($this->canonicalKeyField('product', $keyField)) . "'
              AND external_key_hash='" . $this->db->escape($hash) . "'
              AND external_key='" . $this->db->escape($this->truncate(trim((string)$externalKey), 255)) . "'
              AND product_id='" . (int)$productId . "'
              AND is_active='1' LIMIT 1");
        return $query->num_rows > 0;
    }

    private function deactivateSupplierMapping($supplierCode, $productId, $delete) {
        if ($delete) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . self::SUPPLIER_TABLE . "` WHERE supplier_code='" . $this->db->escape(trim((string)$supplierCode)) . "' AND product_id='" . (int)$productId . "'");
        } else {
            $this->db->query("UPDATE `" . DB_PREFIX . self::SUPPLIER_TABLE . "` SET is_active='0', date_modified=NOW() WHERE supplier_code='" . $this->db->escape(trim((string)$supplierCode)) . "' AND product_id='" . (int)$productId . "'");
        }
    }

    private function insertQueueItem($batchId, $entityType, $csvRowNumber, $keyField, $keyValue, $entityId, $payload, $status, $message, $selected = 1) {
        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . self::QUEUE_TABLE . "` SET
            batch_id='" . $this->db->escape($batchId) . "',
            entity_type='" . $this->db->escape($entityType) . "',
            csv_row_number='" . (int)$csvRowNumber . "',
            key_field='" . $this->db->escape($keyField) . "',
            key_value='" . $this->db->escape($this->truncate($keyValue, 255)) . "',
            entity_id='" . (int)$entityId . "',
            payload_json='" . $this->db->escape($this->encodeJson($payload)) . "',
            status='" . $this->db->escape($status) . "',
            message='" . $this->db->escape($message) . "',
            warnings='', worker_token='', attempts='0', selected='" . ($selected ? 1 : 0) . "', lease_expires=NULL, heartbeat_at=NULL, date_added=NOW(), date_modified=NOW()");
        return (int)$this->db->countAffected() === 1;
    }

    private function queueKeyExists($batchId, $keyField, $keyValue) {
        if (trim((string)$keyValue) === '') { return false; }
        $query = $this->db->query("SELECT queue_id FROM `" . DB_PREFIX . self::QUEUE_TABLE . "` WHERE batch_id='" . $this->db->escape($batchId) . "' AND key_field='" . $this->db->escape($keyField) . "' AND key_value='" . $this->db->escape($this->truncate($keyValue, 255)) . "' LIMIT 1");
        return $query->num_rows > 0;
    }

    private function insertLog($batchId, $queueId, $entityType, $entityId, $status, $action, $message) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . self::LOG_TABLE . "` SET batch_id='" . $this->db->escape($batchId) . "', queue_id='" . (int)$queueId . "', entity_type='" . $this->db->escape($entityType) . "', entity_id='" . (int)$entityId . "', status='" . $this->db->escape($status) . "', action='" . $this->db->escape($action) . "', message='" . $this->db->escape($message) . "', date_added=NOW()");
    }

    private function getBatch($batchId) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . self::BATCH_TABLE . "` WHERE batch_id='" . $this->db->escape(trim((string)$batchId)) . "' LIMIT 1");
        return $query->num_rows ? $query->row : false;
    }

    private function markBatchError($batchId, $message) {
        $this->db->query("UPDATE `" . DB_PREFIX . self::BATCH_TABLE . "` SET status='error', prepare_status='error', message='" . $this->db->escape($message) . "', date_modified=NOW() WHERE batch_id='" . $this->db->escape($batchId) . "' AND status<>'cancelled'");
    }

    private function result($status, $action, $entityId, $message, $warnings) {
        return array('status' => $status, 'action' => $action, 'entity_id' => (int)$entityId, 'message' => $message, 'warnings' => array_values(array_unique($warnings)));
    }

    private function getRequestedImportId($payload, $options, $table, $idField) {
        if ((string)$this->getOption($options, 'import_id', '0') !== '1') { return 0; }
        $valueRaw = $this->payloadGet($payload, array('_ID_', '_' . strtoupper($idField) . '_', $idField), 0);
        if (!preg_match('/^\d+$/', (string)$valueRaw)) { return 0; }
        $value = (int)$valueRaw;
        if ($value <= 0 || (PHP_INT_SIZE >= 8 && (float)$valueRaw > 4294967295)) {
            throw new RuntimeException('Импортируемый ID выходит за допустимый диапазон UNSIGNED INT.');
        }
        if ($this->idExists($table, $idField, $value)) {
            throw new RuntimeException('Нельзя импортировать ID ' . $value . ': такой первичный ключ уже существует.');
        }
        return $value;
    }

    private function insertProductSkeleton($productId, $data) {
        $fields = array(
            'product_id' => (int)$productId,
            'model' => (string)$data['model'], 'sku' => (string)$data['sku'], 'upc' => (string)$data['upc'], 'ean' => (string)$data['ean'],
            'jan' => (string)$data['jan'], 'isbn' => (string)$data['isbn'], 'mpn' => (string)$data['mpn'], 'location' => (string)$data['location'],
            'quantity' => (int)$data['quantity'], 'stock_status_id' => (int)$data['stock_status_id'], 'image' => (string)$data['image'],
            'manufacturer_id' => (int)$data['manufacturer_id'], 'shipping' => (int)$data['shipping'], 'price' => (float)$data['price'],
            'points' => (int)$data['points'], 'tax_class_id' => (int)$data['tax_class_id'], 'date_available' => (string)$data['date_available'],
            'weight' => (float)$data['weight'], 'weight_class_id' => (int)$data['weight_class_id'], 'length' => (float)$data['length'],
            'width' => (float)$data['width'], 'height' => (float)$data['height'], 'length_class_id' => (int)$data['length_class_id'],
            'subtract' => (int)$data['subtract'], 'minimum' => (int)$data['minimum'], 'sort_order' => (int)$data['sort_order'], 'status' => (int)$data['status'],
            'noindex' => isset($data['noindex']) ? (int)$data['noindex'] : 0
        );
        $this->insertSkeleton('product', 'product_id', $fields, true);
    }

    private function insertCategorySkeleton($categoryId, $data) {
        $fields = array('category_id' => (int)$categoryId, 'image' => (string)$data['image'], 'parent_id' => (int)$data['parent_id'], 'top' => (int)$data['top'], 'column' => (int)$data['column'], 'sort_order' => (int)$data['sort_order'], 'status' => (int)$data['status'], 'noindex' => isset($data['noindex']) ? (int)$data['noindex'] : 0);
        $this->insertSkeleton('category', 'category_id', $fields, true);
    }

    private function insertManufacturerSkeleton($manufacturerId, $data) {
        $fields = array('manufacturer_id' => (int)$manufacturerId, 'name' => (string)$data['name'], 'image' => (string)$data['image'], 'sort_order' => (int)$data['sort_order'], 'noindex' => isset($data['noindex']) ? (int)$data['noindex'] : 0);
        $this->insertSkeleton('manufacturer', 'manufacturer_id', $fields, false);
    }

    private function insertSkeleton($table, $idField, $fields, $dates) {
        $assignments = array();
        foreach ($fields as $field => $value) {
            if (!$this->columnExists($table, $field)) { continue; }
            if (is_int($value) || is_float($value)) { $assignments[] = "`" . $field . "`='" . $this->db->escape((string)$value) . "'"; }
            else { $assignments[] = "`" . $field . "`='" . $this->db->escape($value) . "'"; }
        }
        if ($dates && $this->columnExists($table, 'date_added')) { $assignments[] = '`date_added`=NOW()'; }
        if ($dates && $this->columnExists($table, 'date_modified')) { $assignments[] = '`date_modified`=NOW()'; }
        if (!$assignments) { throw new RuntimeException('Не удалось подготовить строку для импорта ID.'); }
        $this->db->query("INSERT INTO `" . DB_PREFIX . $table . "` SET " . implode(', ', $assignments));
    }

    private function castValue($value, $type, $field, &$errors) {
        $value = trim((string)$value);
        if ($type === 'string') { return $value; }
        if ($type === 'int') {
            if ($value === '') { return 0; }
            if (!preg_match('/^-?\d+$/', $value)) { $errors[] = 'Поле ' . $field . ' должно быть целым числом.'; return null; }
            return (int)$value;
        }
        if ($type === 'bool') {
            if ($value === '') { return 0; }
            $normalized = $this->textLower($value);
            if (in_array($normalized, array('1','true','yes','on','да','так','вкл','enabled'), true)) { return 1; }
            if (in_array($normalized, array('0','false','no','off','нет','ні','выкл','disabled'), true)) { return 0; }
            if (is_numeric($value)) { return (int)((float)$value != 0); }
            $errors[] = 'Поле ' . $field . ' должно иметь логическое значение 0 или 1.';
            return null;
        }
        if ($type === 'decimal') {
            if ($value === '') { return 0.0; }
            $normalized = str_replace(array("\xc2\xa0", ' '), '', $value);
            $normalized = str_replace(',', '.', $normalized);
            if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) { $errors[] = 'Поле ' . $field . ' содержит некорректное число.'; return null; }
            return (float)$normalized;
        }
        if ($type === 'date') {
            if ($value === '') { $errors[] = 'Поле ' . $field . ' не может быть пустым; используйте формат YYYY-MM-DD.'; return null; }
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) { $errors[] = 'Поле ' . $field . ' должно иметь формат YYYY-MM-DD.'; return null; }
            return $value;
        }
        return $value;
    }

    private function normalizePrefix($value, $default) {
        $value = trim((string)$value);
        return in_array($value, array('+','-'), true) ? $value : $default;
    }

    private function mergeCommercialCollection($type, $existing, $payload, $keys, $ignoreEmpty, &$warnings, &$errors, $options = array()) {
        $field = $this->payloadValueWithPresence($payload, $keys);
        $rule = $this->getFieldRule($options, $keys, 'replace');
        $existing = is_array($existing) ? $existing : array();
        if (!$field['present'] || $rule === 'preserve') { return $existing; }
        if ($rule === 'fill_empty' && !empty($existing)) { return $existing; }
        if ($rule === 'clear') { return array(); }
        $raw = trim((string)$field['value']);
        if ($raw === '' && $ignoreEmpty) { return $existing; }
        if ($raw === '') { return array(); }

        $parsed = $this->parseCommercialCollection($type, $raw, $warnings, $errors);
        if ($rule !== 'merge') { return $parsed; }

        if ($type === 'reward') {
            foreach ($parsed as $customerGroupId => $row) { $existing[(int)$customerGroupId] = $row; }
            ksort($existing);
            return $existing;
        }

        $merged = array();
        foreach ($existing as $row) { $merged[$this->commercialCollectionKey($type, $row)] = $row; }
        foreach ($parsed as $row) { $merged[$this->commercialCollectionKey($type, $row)] = $row; }
        return array_values($merged);
    }

    private function parseCommercialCollection($type, $raw, &$warnings, &$errors) {
        $rows = array();
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if ($type === 'reward' && $this->isAssociativeArray($decoded)) {
                foreach ($decoded as $customerGroupId => $value) {
                    if (is_array($value)) {
                        $rows[] = array('customer_group_id' => $customerGroupId, 'points' => isset($value['points']) ? $value['points'] : 0);
                    } else {
                        $rows[] = array('customer_group_id' => $customerGroupId, 'points' => $value);
                    }
                }
            } else {
                foreach ($decoded as $row) { if (is_array($row)) { $rows[] = $row; } }
            }
        } else {
            foreach (preg_split('/\s*\|\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $record) {
                $parts = array_map('trim', explode(':', $record));
                if ($type === 'discount' && count($parts) === 6) {
                    $rows[] = array('customer_group_id'=>$parts[0], 'quantity'=>$parts[1], 'priority'=>$parts[2], 'price'=>$parts[3], 'date_start'=>$parts[4], 'date_end'=>$parts[5]);
                } elseif ($type === 'special' && count($parts) === 5) {
                    $rows[] = array('customer_group_id'=>$parts[0], 'priority'=>$parts[1], 'price'=>$parts[2], 'date_start'=>$parts[3], 'date_end'=>$parts[4]);
                } elseif ($type === 'reward' && count($parts) === 2) {
                    $rows[] = array('customer_group_id'=>$parts[0], 'points'=>$parts[1]);
                } elseif ($type === 'recurring' && count($parts) === 2) {
                    $rows[] = array('recurring_id'=>$parts[0], 'customer_group_id'=>$parts[1]);
                } else {
                    $errors[] = 'Некорректный формат поля ' . $type . ': ' . $record . '.';
                }
            }
        }

        $result = array();
        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeCommercialRow($type, $row, $index + 1, $warnings, $errors);
            if ($normalized === null) { continue; }
            if ($type === 'reward') {
                $result[(int)$normalized['customer_group_id']] = array('points' => (int)$normalized['points']);
            } else {
                $result[$this->commercialCollectionKey($type, $normalized)] = $normalized;
            }
        }
        if ($type === 'reward') { ksort($result); return $result; }
        return array_values($result);
    }

    private function normalizeCommercialRow($type, $row, $position, &$warnings, &$errors) {
        $customerGroupId = $this->normalizeUnsignedInteger(isset($row['customer_group_id']) ? $row['customer_group_id'] : null);
        if ($customerGroupId === null || $customerGroupId <= 0 || !$this->recordExists('customer_group', 'customer_group_id', $customerGroupId)) {
            $shownId = isset($row['customer_group_id']) ? trim((string)$row['customer_group_id']) : '';
            $errors[] = 'Строка ' . $position . ' поля ' . $type . ': группа покупателей ID ' . ($shownId !== '' ? $shownId : 'не указан') . ' не существует или имеет неверный формат.';
            return null;
        }

        if ($type === 'discount') {
            $quantity = $this->normalizeUnsignedInteger(isset($row['quantity']) ? $row['quantity'] : null);
            $priority = $this->normalizeUnsignedInteger(isset($row['priority']) ? $row['priority'] : null);
            $price = $this->normalizeNonNegativeDecimal(isset($row['price']) ? $row['price'] : null);
            $dateStart = $this->normalizeOptionalDate(isset($row['date_start']) ? $row['date_start'] : '0000-00-00');
            $dateEnd = $this->normalizeOptionalDate(isset($row['date_end']) ? $row['date_end'] : '0000-00-00');
            if ($quantity === null || $quantity < 1) { $errors[] = 'Строка ' . $position . ' скидки: количество должно быть целым числом не меньше 1.'; }
            if ($priority === null) { $errors[] = 'Строка ' . $position . ' скидки: приоритет должен быть неотрицательным целым числом.'; }
            if ($price === null) { $errors[] = 'Строка ' . $position . ' скидки: цена должна быть неотрицательным числом.'; }
            if ($dateStart === null || $dateEnd === null) { $errors[] = 'Строка ' . $position . ' скидки: даты должны иметь формат YYYY-MM-DD или 0000-00-00.'; }
            if ($dateStart !== null && $dateEnd !== null && $dateStart !== '0000-00-00' && $dateEnd !== '0000-00-00' && $dateStart > $dateEnd) { $errors[] = 'Строка ' . $position . ' скидки: дата начала позже даты окончания.'; return null; }
            if ($quantity === null || $quantity < 1 || $priority === null || $price === null || $dateStart === null || $dateEnd === null) { return null; }
            return array('customer_group_id'=>$customerGroupId,'quantity'=>$quantity,'priority'=>$priority,'price'=>$price,'date_start'=>$dateStart,'date_end'=>$dateEnd);
        }

        if ($type === 'special') {
            $priority = $this->normalizeUnsignedInteger(isset($row['priority']) ? $row['priority'] : null);
            $price = $this->normalizeNonNegativeDecimal(isset($row['price']) ? $row['price'] : null);
            $dateStart = $this->normalizeOptionalDate(isset($row['date_start']) ? $row['date_start'] : '0000-00-00');
            $dateEnd = $this->normalizeOptionalDate(isset($row['date_end']) ? $row['date_end'] : '0000-00-00');
            if ($priority === null) { $errors[] = 'Строка ' . $position . ' акции: приоритет должен быть неотрицательным целым числом.'; }
            if ($price === null) { $errors[] = 'Строка ' . $position . ' акции: цена должна быть неотрицательным числом.'; }
            if ($dateStart === null || $dateEnd === null) { $errors[] = 'Строка ' . $position . ' акции: даты должны иметь формат YYYY-MM-DD или 0000-00-00.'; }
            if ($dateStart !== null && $dateEnd !== null && $dateStart !== '0000-00-00' && $dateEnd !== '0000-00-00' && $dateStart > $dateEnd) { $errors[] = 'Строка ' . $position . ' акции: дата начала позже даты окончания.'; return null; }
            if ($priority === null || $price === null || $dateStart === null || $dateEnd === null) { return null; }
            return array('customer_group_id'=>$customerGroupId,'priority'=>$priority,'price'=>$price,'date_start'=>$dateStart,'date_end'=>$dateEnd);
        }

        if ($type === 'reward') {
            $points = $this->normalizeUnsignedInteger(isset($row['points']) ? $row['points'] : null);
            if ($points === null) { $errors[] = 'Строка ' . $position . ' бонусов: количество баллов должно быть неотрицательным целым числом.'; return null; }
            return array('customer_group_id'=>$customerGroupId,'points'=>$points);
        }

        $recurringId = $this->normalizeUnsignedInteger(isset($row['recurring_id']) ? $row['recurring_id'] : null);
        if ($recurringId === null || $recurringId <= 0 || !$this->recordExists('recurring', 'recurring_id', $recurringId)) {
            $shownId = isset($row['recurring_id']) ? trim((string)$row['recurring_id']) : '';
            $errors[] = 'Строка ' . $position . ' периодического платежа: схема recurring_id=' . ($shownId !== '' ? $shownId : 'не указана') . ' не существует или имеет неверный формат.';
            return null;
        }
        return array('recurring_id'=>$recurringId,'customer_group_id'=>$customerGroupId);
    }

    private function normalizeUnsignedInteger($value) {
        $value = trim((string)$value);
        if ($value === '' || !preg_match('/^\d+$/', $value)) { return null; }
        if (strlen($value) > 10 || (float)$value > 2147483647) { return null; }
        return (int)$value;
    }

    private function normalizeNonNegativeDecimal($value) {
        $normalized = str_replace(array("\xc2\xa0", ' '), '', trim((string)$value));
        $normalized = str_replace(',', '.', $normalized);
        if ($normalized === '' || !preg_match('/^\d+(?:\.\d+)?$/', $normalized)) { return null; }
        return (float)$normalized;
    }

    private function normalizeOptionalDate($value) {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00') { return '0000-00-00'; }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }

    private function commercialCollectionKey($type, $row) {
        if ($type === 'discount') { return implode(':', array((int)$row['customer_group_id'],(int)$row['quantity'],(int)$row['priority'],(string)$row['date_start'],(string)$row['date_end'])); }
        if ($type === 'special') { return implode(':', array((int)$row['customer_group_id'],(int)$row['priority'],(string)$row['date_start'],(string)$row['date_end'])); }
        if ($type === 'recurring') { return (int)$row['recurring_id'] . ':' . (int)$row['customer_group_id']; }
        return (string)(isset($row['customer_group_id']) ? (int)$row['customer_group_id'] : '');
    }

    private function encodeCommercialCollection($type, $rows) {
        if ($type === 'reward') {
            $normalized = array();
            foreach ((array)$rows as $customerGroupId => $row) {
                $normalized[] = array('customer_group_id'=>(int)$customerGroupId,'points'=>(int)(isset($row['points']) ? $row['points'] : 0));
            }
            return $normalized ? $this->encodeJson($normalized) : '';
        }
        $normalized = array();
        foreach ((array)$rows as $row) {
            if (!is_array($row)) { continue; }
            if ($type === 'discount') {
                $normalized[] = array('customer_group_id'=>(int)$row['customer_group_id'],'quantity'=>(int)$row['quantity'],'priority'=>(int)$row['priority'],'price'=>(float)$row['price'],'date_start'=>(string)$row['date_start'],'date_end'=>(string)$row['date_end']);
            } elseif ($type === 'special') {
                $normalized[] = array('customer_group_id'=>(int)$row['customer_group_id'],'priority'=>(int)$row['priority'],'price'=>(float)$row['price'],'date_start'=>(string)$row['date_start'],'date_end'=>(string)$row['date_end']);
            } elseif ($type === 'recurring') {
                $normalized[] = array('recurring_id'=>(int)$row['recurring_id'],'customer_group_id'=>(int)$row['customer_group_id']);
            }
        }
        return $normalized ? $this->encodeJson($normalized) : '';
    }

    private function isAssociativeArray($array) {
        if (!is_array($array) || $array === array()) { return false; }
        $keys = array_keys($array);
        return $keys !== range(0, count($array) - 1);
    }

    private function mergeReferenceIdList($existing, $payload, $keys, $table, $idField, $ignoreEmpty, &$warnings, $excludeId = 0, $options = array()) {
        $field = $this->payloadValueWithPresence($payload, $keys);
        $existing = array_values(array_unique(array_map('intval', (array)$existing)));
        $rule = $this->getFieldRule($options, $keys, 'replace');
        if (!$field['present'] || $rule === 'preserve') { return $existing; }
        if ($rule === 'fill_empty' && $existing) { return $existing; }
        if ($rule === 'clear') { return array(); }
        $raw = trim((string)$field['value']);
        if ($raw === '' && $ignoreEmpty) { return $existing; }
        if ($raw === '') { return array(); }
        if (!$this->tableExists($table)) {
            $warnings[] = 'Таблица ' . DB_PREFIX . $table . ' отсутствует; переданные связи пропущены.';
            return array_values(array_unique(array_map('intval', (array)$existing)));
        }
        $ids = array();
        foreach (preg_split('/[,;|\s]+/', $raw) as $part) {
            if ($part === '') { continue; }
            if (!preg_match('/^\d+$/', $part)) {
                $warnings[] = 'Некорректный ID связи пропущен: ' . $part . '.';
                continue;
            }
            $id = (int)$part;
            if ($id <= 0 || ($excludeId > 0 && $id === (int)$excludeId)) { continue; }
            if ($this->recordExists($table, $idField, $id)) { $ids[] = $id; }
            else { $warnings[] = 'Связанная запись ' . $table . '.' . $idField . '=' . $id . ' не найдена и пропущена.'; }
        }
        if ($rule === 'merge') { $ids = array_merge($existing, $ids); }
        return array_values(array_unique($ids));
    }

    private function mergeLayoutMap($existing, $payload, $keys, $ignoreEmpty, &$warnings, $unused = 0, $options = array()) {
        $field = $this->payloadValueWithPresence($payload, $keys);
        $existing = is_array($existing) ? $existing : array();
        $rule = $this->getFieldRule($options, $keys, 'replace');
        if (!$field['present'] || $rule === 'preserve') { return $existing; }
        if ($rule === 'fill_empty' && $existing) { return $existing; }
        if ($rule === 'clear') { return array(); }
        $raw = trim((string)$field['value']);
        if ($raw === '' && $ignoreEmpty) { return $existing; }
        if ($raw === '') { return array(); }
        $stores = $this->getValidStoreMap();
        $layouts = array();
        foreach (preg_split('/[|;,]+/', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') { continue; }
            if (!preg_match('/^(\d+)\s*[:=]\s*(\d+)$/', $pair, $match)) {
                $warnings[] = 'Некорректная привязка макета пропущена: ' . $pair . '. Используйте store_id:layout_id.';
                continue;
            }
            $storeId = (int)$match[1];
            $layoutId = (int)$match[2];
            if (!isset($stores[$storeId])) {
                $warnings[] = 'Store ID ' . $storeId . ' для макета не существует и пропущен.';
                continue;
            }
            if ($layoutId <= 0 || !$this->recordExists('layout', 'layout_id', $layoutId)) {
                $warnings[] = 'Layout ID ' . $layoutId . ' не существует и пропущен.';
                continue;
            }
            $layouts[$storeId] = $layoutId;
        }
        if ($rule === 'merge') { $layouts = array_replace($existing, $layouts); }
        ksort($layouts);
        return $layouts;
    }

    private function encodeLayoutMap($layouts) {
        $parts = array();
        foreach ((array)$layouts as $storeId => $layoutId) {
            if ((int)$layoutId > 0) { $parts[] = (int)$storeId . ':' . (int)$layoutId; }
        }
        return implode('|', $parts);
    }

    private function getLayoutMapFromTable($table, $whereField, $whereId) {
        if (!$this->tableExists($table) || !$this->columnExists($table, $whereField)) { return array(); }
        $layouts = array();
        foreach ($this->db->query("SELECT store_id, layout_id FROM `" . DB_PREFIX . $table . "` WHERE `" . $whereField . "`='" . (int)$whereId . "' ORDER BY store_id")->rows as $row) {
            $layouts[(int)$row['store_id']] = (int)$row['layout_id'];
        }
        return $layouts;
    }

    private function getIdListFromTable($table, $selectField, $whereField, $whereId, $orderField = '') {
        if (!$this->tableExists($table)) { return array(); }
        if (!preg_match('/^[a-z0-9_]+$/', $selectField) || !preg_match('/^[a-z0-9_]+$/', $whereField)) { return array(); }
        $sql = "SELECT `" . $selectField . "` FROM `" . DB_PREFIX . $table . "` WHERE `" . $whereField . "`='" . (int)$whereId . "'";
        if ($orderField !== '' && preg_match('/^[a-z0-9_]+$/', $orderField)) { $sql .= " ORDER BY `" . $orderField . "`"; }
        $ids = array();
        foreach ($this->db->query($sql)->rows as $row) { $ids[] = (int)$row[$selectField]; }
        return array_values(array_unique($ids));
    }

    private function normalizeStoreIds($raw, &$warnings) {
        $parts = is_array($raw) ? $raw : preg_split('/[,;|\s]+/', (string)$raw);
        $validStores = $this->getValidStoreMap();
        $ids = array();
        foreach ($parts as $part) {
            if ($part === '') { continue; }
            $id = (int)$part;
            if (isset($validStores[$id])) { $ids[] = $id; }
            else { $warnings[] = 'Store ID ' . $id . ' не существует и пропущен.'; }
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) { $ids = array(0); }
        return $ids;
    }

    private function getValidStoreMap() {
        if ($this->storeCache !== null) { return $this->storeCache; }
        $this->storeCache = array(0 => true);
        if ($this->tableExists('store')) {
            foreach ($this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`")->rows as $row) { $this->storeCache[(int)$row['store_id']] = true; }
        }
        return $this->storeCache;
    }

    private function getLanguages() {
        if ($this->languageCache !== null) { return $this->languageCache; }
        $this->languageCache = array();
        foreach ($this->db->query("SELECT language_id, name, code, status, sort_order FROM `" . DB_PREFIX . "language` ORDER BY sort_order, language_id")->rows as $row) {
            $this->languageCache[(int)$row['language_id']] = $row;
        }
        return $this->languageCache;
    }

    private function getValidLanguageId($languageId) {
        $languageId = (int)$languageId;
        $languages = $this->getLanguages();
        if (isset($languages[$languageId])) { return $languageId; }
        $fallback = (int)$this->config->get('config_language_id');
        if (isset($languages[$fallback])) { return $fallback; }
        return $languages ? (int)array_key_first($languages) : 1;
    }

    private function idExists($table, $idField, $id) {
        $id = (int)$id;
        if ($id <= 0 || !$this->tableExists($table) || !$this->columnExists($table, $idField)) { return false; }
        $query = $this->db->query("SELECT `" . $idField . "` FROM `" . DB_PREFIX . $table . "` WHERE `" . $idField . "`='" . $id . "' LIMIT 1");
        return $query->num_rows > 0;
    }

    private function tableExists($table) {
        if (isset($this->tableCache[$table])) { return $this->tableCache[$table]; }
        if (!preg_match('/^[a-z0-9_]+$/', $table)) { return false; }
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        $this->tableCache[$table] = $query->num_rows > 0;
        return $this->tableCache[$table];
    }

    private function columnExists($table, $column) {
        $key = $table . '.' . $column;
        if (isset($this->columnCache[$key])) { return $this->columnCache[$key]; }
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $column)) { return false; }
        $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $this->db->escape($column) . "'");
        $this->columnCache[$key] = $query->num_rows > 0;
        return $this->columnCache[$key];
    }

    private function ensureColumn($table, $column, $definition) {
        if (!$this->columnExists($table, $column)) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD `" . $column . "` " . $definition);
            $this->columnCache[$table . '.' . $column] = true;
        }
    }

    private function ensureIndex($table, $index, $columns, $unique) {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $index)) { return; }
        $safeColumns = array();
        foreach ((array)$columns as $column) {
            if (!preg_match('/^[a-z0-9_]+$/', $column) || !$this->columnExists($table, $column)) { return; }
            $safeColumns[] = $column;
        }
        if (!$safeColumns) { return; }

        $query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE Key_name='" . $this->db->escape($index) . "'");
        if ($query->num_rows) {
            $rows = $query->rows;
            usort($rows, function($a, $b) { return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index']; });
            $actualColumns = array();
            $actualUnique = true;
            foreach ($rows as $row) {
                $actualColumns[] = (string)$row['Column_name'];
                if ((int)$row['Non_unique'] !== 0) { $actualUnique = false; }
            }
            if ($actualColumns === $safeColumns && $actualUnique === (bool)$unique) { return; }
            $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` DROP INDEX `" . $index . "`");
        }

        $quoted = array_map(function($column) { return '`' . $column . '`'; }, $safeColumns);
        $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD " . ($unique ? 'UNIQUE ' : '') . "KEY `" . $index . "` (" . implode(',', $quoted) . ")");
    }

    public function streamExport($entityType, $options, $output, $delimiter) {
        $entityType = $this->normalizeEntityType($entityType);
        if (!is_resource($output)) { throw new RuntimeException('Поток экспорта недоступен.'); }
        if (!in_array($delimiter, array(';', ',', "\t", '|'), true)) { $delimiter = ';'; }
        if ($entityType === 'product') { $this->streamProductExport($options, $output, $delimiter); return; }
        if ($entityType === 'category') { $this->streamCategoryExport($options, $output, $delimiter); return; }
        if ($entityType === 'manufacturer') { $this->streamManufacturerExport($options, $output, $delimiter); return; }
        $this->streamOptionExport($options, $output, $delimiter);
    }

    private function streamProductExport($options, $output, $delimiter) {
        $languages = $this->getLanguages();
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $header = array(
            '_ID_','_MODEL_','_SKU_','_UPC_','_EAN_','_JAN_','_ISBN_','_MPN_','_LOCATION_','_MANUFACTURER_ID_','_MANUFACTURER_',
            '_PRICE_','_QUANTITY_','_MINIMUM_','_SUBTRACT_','_STOCK_STATUS_ID_','_DATE_AVAILABLE_','_SHIPPING_','_POINTS_','_TAX_CLASS_ID_',
            '_WEIGHT_','_WEIGHT_CLASS_ID_','_LENGTH_','_WIDTH_','_HEIGHT_','_LENGTH_CLASS_ID_','_STATUS_','_NOINDEX_','_SORT_ORDER_',
            '_IMAGE_','_IMAGES_','_CATEGORY_IDS_','_CATEGORY_','_MAIN_CATEGORY_','_ATTRIBUTES_','_STORE_IDS_','_FILTER_IDS_','_DOWNLOAD_IDS_',
            '_RELATED_PRODUCT_IDS_','_RELATED_ARTICLE_IDS_','_LAYOUTS_','_DISCOUNTS_','_SPECIALS_','_REWARDS_','_RECURRING_','_SEO_KEYWORD_'
        );
        foreach ($languages as $id => $language) {
            foreach (array('NAME','DESCRIPTION','TAG','META_TITLE','META_DESCRIPTION','META_KEYWORD','META_H1','SEO_KEYWORD') as $field) {
                $header[] = '_' . $field . '_LANG=' . $id . '_';
            }
        }
        $exportStores = array_keys($this->getValidStoreMap());
        sort($exportStores);
        foreach ($exportStores as $storeId) {
            foreach ($languages as $id => $language) { $header[] = '_SEO_KEYWORD_STORE=' . (int)$storeId . '_LANG=' . (int)$id . '_'; }
        }
        $this->writeCsvRow($output, $header, $delimiter);

        $lastId = 0;
        do {
            $products = $this->db->query("SELECT p.*, m.name AS manufacturer_name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.manufacturer_id=p.manufacturer_id) WHERE p.product_id>'" . (int)$lastId . "' ORDER BY p.product_id ASC LIMIT 200")->rows;
            foreach ($products as $product) {
                $productId = (int)$product['product_id'];
                $lastId = $productId;
                $descriptions = array();
                foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` WHERE product_id='" . $productId . "'")->rows as $description) {
                    $id = (int)$description['language_id'];
                    unset($description['product_id'], $description['language_id']);
                    $descriptions[$id] = $description;
                }
                $images = array();
                foreach ($this->db->query("SELECT image FROM `" . DB_PREFIX . "product_image` WHERE product_id='" . $productId . "' ORDER BY sort_order, product_image_id")->rows as $image) {
                    $images[] = $image['image'];
                }
                $categoryIds = array();
                $categoryPaths = array();
                foreach ($this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id='" . $productId . "' ORDER BY category_id")->rows as $category) {
                    $categoryIds[] = (int)$category['category_id'];
                    $categoryPaths[] = $this->getCategoryPathName((int)$category['category_id'], $languageId, $this->getOption($options, 'category_path_separator', '>'));
                }
                $attributes = array();
                $attributeRows = $this->db->query("SELECT agd.name AS group_name, ad.name AS attribute_name, pa.text FROM `" . DB_PREFIX . "product_attribute` pa INNER JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id=pa.attribute_id) INNER JOIN `" . DB_PREFIX . "attribute_description` ad ON (ad.attribute_id=a.attribute_id AND ad.language_id=pa.language_id) INNER JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (agd.attribute_group_id=a.attribute_group_id AND agd.language_id=pa.language_id) WHERE pa.product_id='" . $productId . "' AND pa.language_id='" . (int)$languageId . "' ORDER BY a.sort_order, ad.name")->rows;
                foreach ($attributeRows as $attribute) {
                    $attributes[] = $attribute['group_name'] . ':' . $attribute['attribute_name'] . '=' . $attribute['text'];
                }
                $stores = $this->getIdListFromTable('product_to_store', 'store_id', 'product_id', $productId, 'store_id');
                $filters = $this->getIdListFromTable('product_filter', 'filter_id', 'product_id', $productId, 'filter_id');
                $downloads = $this->getIdListFromTable('product_to_download', 'download_id', 'product_id', $productId, 'download_id');
                $relatedProducts = $this->getIdListFromTable('product_related', 'related_id', 'product_id', $productId, 'related_id');
                $relatedArticles = $this->getIdListFromTable('product_related_article', 'article_id', 'product_id', $productId, 'article_id');
                $layouts = $this->getLayoutMapFromTable('product_to_layout', 'product_id', $productId);
                $commercialData = $this->getProductCommercialData($productId);
                $mainCategoryId = $this->getExistingMainCategoryId($productId);
                $selectedSeo = $this->getSeoKeywordForExport('product_id=' . $productId, 0, $languageId);
                $row = array(
                    $productId, $product['model'], $product['sku'], $product['upc'], $product['ean'], $product['jan'], $product['isbn'], $product['mpn'], $product['location'],
                    (int)$product['manufacturer_id'], $product['manufacturer_name'], $product['price'], (int)$product['quantity'], (int)$product['minimum'], (int)$product['subtract'],
                    (int)$product['stock_status_id'], $product['date_available'], (int)$product['shipping'], (int)$product['points'], (int)$product['tax_class_id'], $product['weight'],
                    (int)$product['weight_class_id'], $product['length'], $product['width'], $product['height'], (int)$product['length_class_id'], (int)$product['status'],
                    isset($product['noindex']) ? (int)$product['noindex'] : 0, (int)$product['sort_order'], $product['image'], implode('|', $images), implode(',', $categoryIds),
                    implode('|', $categoryPaths), $mainCategoryId > 0 ? $this->getCategoryPathName($mainCategoryId, $languageId, $this->getOption($options, 'category_path_separator', '>')) : '',
                    implode('|', $attributes), implode(',', $stores), implode(',', $filters), implode(',', $downloads), implode(',', $relatedProducts), implode(',', $relatedArticles),
                    $this->encodeLayoutMap($layouts),
                    $this->encodeCommercialCollection('discount', isset($commercialData['product_discount']) ? $commercialData['product_discount'] : array()),
                    $this->encodeCommercialCollection('special', isset($commercialData['product_special']) ? $commercialData['product_special'] : array()),
                    $this->encodeCommercialCollection('reward', isset($commercialData['product_reward']) ? $commercialData['product_reward'] : array()),
                    $this->encodeCommercialCollection('recurring', isset($commercialData['product_recurring']) ? $commercialData['product_recurring'] : array()),
                    $selectedSeo
                );
                foreach ($languages as $id => $language) {
                    $description = isset($descriptions[$id]) ? $descriptions[$id] : array();
                    $row[] = isset($description['name']) ? $description['name'] : '';
                    $row[] = isset($description['description']) ? $description['description'] : '';
                    $row[] = isset($description['tag']) ? $description['tag'] : '';
                    $row[] = isset($description['meta_title']) ? $description['meta_title'] : '';
                    $row[] = isset($description['meta_description']) ? $description['meta_description'] : '';
                    $row[] = isset($description['meta_keyword']) ? $description['meta_keyword'] : '';
                    $row[] = isset($description['meta_h1']) ? $description['meta_h1'] : '';
                    $row[] = $this->getSeoKeywordForExport('product_id=' . $productId, 0, $id);
                }
                foreach ($exportStores as $storeId) {
                    foreach ($languages as $id => $language) { $row[] = $this->getSeoKeywordForExport('product_id=' . $productId, $storeId, $id); }
                }
                $this->writeCsvRow($output, $row, $delimiter);
            }
        } while ($products);
    }

    private function streamCategoryExport($options, $output, $delimiter) {
        $languages = $this->getLanguages();
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $header = array('_ID_','_PARENT_ID_','_CATEGORY_','_IMAGE_','_TOP_','_COLUMN_','_SORT_ORDER_','_STATUS_','_NOINDEX_','_STORE_IDS_','_FILTER_IDS_','_RELATED_PRODUCT_IDS_','_RELATED_ARTICLE_IDS_','_LAYOUTS_','_SEO_KEYWORD_');
        foreach ($languages as $id => $language) {
            foreach (array('NAME','DESCRIPTION','META_TITLE','META_DESCRIPTION','META_KEYWORD','META_H1','SEO_KEYWORD') as $field) {
                $header[] = '_' . $field . '_LANG=' . $id . '_';
            }
        }
        $exportStores = array_keys($this->getValidStoreMap());
        sort($exportStores);
        foreach ($exportStores as $storeId) {
            foreach ($languages as $id => $language) { $header[] = '_SEO_KEYWORD_STORE=' . (int)$storeId . '_LANG=' . (int)$id . '_'; }
        }
        $this->writeCsvRow($output, $header, $delimiter);
        $lastId = 0;
        do {
            $categories = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category` WHERE category_id>'" . (int)$lastId . "' ORDER BY category_id ASC LIMIT 200")->rows;
            foreach ($categories as $category) {
                $categoryId = (int)$category['category_id'];
                $lastId = $categoryId;
                $descriptions = array();
                foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "category_description` WHERE category_id='" . $categoryId . "'")->rows as $description) {
                    $id = (int)$description['language_id'];
                    unset($description['category_id'], $description['language_id']);
                    $descriptions[$id] = $description;
                }
                $stores = $this->getIdListFromTable('category_to_store', 'store_id', 'category_id', $categoryId, 'store_id');
                $filters = $this->getIdListFromTable('category_filter', 'filter_id', 'category_id', $categoryId, 'filter_id');
                $relatedProducts = $this->getIdListFromTable('product_related_wb', 'product_id', 'category_id', $categoryId, 'product_id');
                $relatedArticles = $this->getIdListFromTable('article_related_wb', 'article_id', 'category_id', $categoryId, 'article_id');
                $layouts = $this->getLayoutMapFromTable('category_to_layout', 'category_id', $categoryId);
                $row = array(
                    $categoryId, (int)$category['parent_id'], $this->getCategoryPathName($categoryId, $languageId, $this->getOption($options, 'category_path_separator', '>')),
                    $category['image'], (int)$category['top'], (int)$category['column'], (int)$category['sort_order'], (int)$category['status'],
                    isset($category['noindex']) ? (int)$category['noindex'] : 0, implode(',', $stores), implode(',', $filters), implode(',', $relatedProducts), implode(',', $relatedArticles),
                    $this->encodeLayoutMap($layouts), $this->getSeoKeywordForExport('category_id=' . $categoryId, 0, $languageId)
                );
                foreach ($languages as $id => $language) {
                    $description = isset($descriptions[$id]) ? $descriptions[$id] : array();
                    $row[] = isset($description['name']) ? $description['name'] : '';
                    $row[] = isset($description['description']) ? $description['description'] : '';
                    $row[] = isset($description['meta_title']) ? $description['meta_title'] : '';
                    $row[] = isset($description['meta_description']) ? $description['meta_description'] : '';
                    $row[] = isset($description['meta_keyword']) ? $description['meta_keyword'] : '';
                    $row[] = isset($description['meta_h1']) ? $description['meta_h1'] : '';
                    $row[] = $this->getSeoKeywordForExport('category_id=' . $categoryId, 0, $id);
                }
                foreach ($exportStores as $storeId) {
                    foreach ($languages as $id => $language) { $row[] = $this->getSeoKeywordForExport('category_id=' . $categoryId, $storeId, $id); }
                }
                $this->writeCsvRow($output, $row, $delimiter);
            }
        } while ($categories);
    }

    private function streamManufacturerExport($options, $output, $delimiter) {
        $languages = $this->getLanguages();
        $languageId = $this->getValidLanguageId($this->getOption($options, 'language_id', $this->config->get('config_language_id')));
        $header = array('_ID_','_NAME_','_IMAGE_','_SORT_ORDER_','_NOINDEX_','_STORE_IDS_','_RELATED_PRODUCT_IDS_','_RELATED_ARTICLE_IDS_','_LAYOUTS_','_SEO_KEYWORD_');
        foreach ($languages as $id => $language) {
            foreach (array('DESCRIPTION','META_TITLE','META_DESCRIPTION','META_KEYWORD','META_H1','SEO_KEYWORD') as $field) {
                $header[] = '_' . $field . '_LANG=' . $id . '_';
            }
        }
        $exportStores = array_keys($this->getValidStoreMap());
        sort($exportStores);
        foreach ($exportStores as $storeId) {
            foreach ($languages as $id => $language) { $header[] = '_SEO_KEYWORD_STORE=' . (int)$storeId . '_LANG=' . (int)$id . '_'; }
        }
        $this->writeCsvRow($output, $header, $delimiter);
        $lastId = 0;
        do {
            $manufacturers = $this->db->query("SELECT * FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id>'" . (int)$lastId . "' ORDER BY manufacturer_id ASC LIMIT 500")->rows;
            foreach ($manufacturers as $manufacturer) {
                $manufacturerId = (int)$manufacturer['manufacturer_id'];
                $lastId = $manufacturerId;
                $stores = $this->getIdListFromTable('manufacturer_to_store', 'store_id', 'manufacturer_id', $manufacturerId, 'store_id');
                $relatedProducts = $this->getIdListFromTable('product_related_mn', 'product_id', 'manufacturer_id', $manufacturerId, 'product_id');
                $relatedArticles = $this->getIdListFromTable('article_related_mn', 'article_id', 'manufacturer_id', $manufacturerId, 'article_id');
                $layouts = $this->getLayoutMapFromTable('manufacturer_to_layout', 'manufacturer_id', $manufacturerId);
                $descriptions = array();
                if ($this->tableExists('manufacturer_description')) {
                    foreach ($this->db->query("SELECT * FROM `" . DB_PREFIX . "manufacturer_description` WHERE manufacturer_id='" . $manufacturerId . "'")->rows as $description) {
                        $id = (int)$description['language_id'];
                        unset($description['manufacturer_id'], $description['language_id']);
                        $descriptions[$id] = $description;
                    }
                }
                $row = array(
                    $manufacturerId, $manufacturer['name'], $manufacturer['image'], (int)$manufacturer['sort_order'], isset($manufacturer['noindex']) ? (int)$manufacturer['noindex'] : 0,
                    implode(',', $stores), implode(',', $relatedProducts), implode(',', $relatedArticles), $this->encodeLayoutMap($layouts),
                    $this->getSeoKeywordForExport('manufacturer_id=' . $manufacturerId, 0, $languageId)
                );
                foreach ($languages as $id => $language) {
                    $description = isset($descriptions[$id]) ? $descriptions[$id] : array();
                    $row[] = isset($description['description']) ? $description['description'] : '';
                    $row[] = isset($description['meta_title']) ? $description['meta_title'] : '';
                    $row[] = isset($description['meta_description']) ? $description['meta_description'] : '';
                    $row[] = isset($description['meta_keyword']) ? $description['meta_keyword'] : '';
                    $row[] = isset($description['meta_h1']) ? $description['meta_h1'] : '';
                    $row[] = $this->getSeoKeywordForExport('manufacturer_id=' . $manufacturerId, 0, $id);
                }
                foreach ($exportStores as $storeId) {
                    foreach ($languages as $id => $language) { $row[] = $this->getSeoKeywordForExport('manufacturer_id=' . $manufacturerId, $storeId, $id); }
                }
                $this->writeCsvRow($output, $row, $delimiter);
            }
        } while ($manufacturers);
    }

    private function streamOptionExport($options, $output, $delimiter) {
        $languageId=$this->getValidLanguageId($this->getOption($options,'language_id',$this->config->get('config_language_id')));
        $header=array('_PRODUCT_ID_','_PRODUCT_MODEL_','_PRODUCT_SKU_','_PRODUCT_NAME_','_OPTION_ID_','_OPTION_','_OPTION_TYPE_','_OPTION_SORT_ORDER_','_OPTION_REQUIRED_','_VALUE_','_OPTION_VALUE_ID_','_OPTION_VALUE_','_OPTION_VALUE_IMAGE_','_OPTION_VALUE_SORT_ORDER_','_QUANTITY_','_SUBTRACT_','_PRICE_','_PRICE_PREFIX_','_POINTS_','_POINTS_PREFIX_','_WEIGHT_','_WEIGHT_PREFIX_');
        $this->writeCsvRow($output,$header,$delimiter);
        $lastId=0;
        do{
            $optionsRows=$this->db->query("SELECT po.*, o.type, o.sort_order AS option_sort_order, od.name AS option_name, p.model, p.sku, pd.name AS product_name FROM `".DB_PREFIX."product_option` po INNER JOIN `".DB_PREFIX."option` o ON(o.option_id=po.option_id) LEFT JOIN `".DB_PREFIX."option_description` od ON(od.option_id=o.option_id AND od.language_id='".(int)$languageId."') INNER JOIN `".DB_PREFIX."product` p ON(p.product_id=po.product_id) LEFT JOIN `".DB_PREFIX."product_description` pd ON(pd.product_id=p.product_id AND pd.language_id='".(int)$languageId."') WHERE po.product_option_id>'".(int)$lastId."' ORDER BY po.product_option_id ASC LIMIT 200")->rows;
            foreach($optionsRows as $option){
                $lastId=(int)$option['product_option_id'];
                $choice=in_array($option['type'],array('select','radio','checkbox','image'),true);
                if($choice){
                    $values=$this->db->query("SELECT pov.*, ov.image, ov.sort_order AS option_value_sort_order, ovd.name AS option_value_name FROM `".DB_PREFIX."product_option_value` pov LEFT JOIN `".DB_PREFIX."option_value` ov ON(ov.option_value_id=pov.option_value_id) LEFT JOIN `".DB_PREFIX."option_value_description` ovd ON(ovd.option_value_id=pov.option_value_id AND ovd.language_id='".(int)$languageId."') WHERE pov.product_option_id='".(int)$option['product_option_id']."' ORDER BY pov.product_option_value_id")->rows;
                    foreach($values as $value){$this->writeCsvRow($output,array((int)$option['product_id'],$option['model'],$option['sku'],$option['product_name'],(int)$option['option_id'],$option['option_name'],$option['type'],(int)$option['option_sort_order'],(int)$option['required'],'',(int)$value['option_value_id'],$value['option_value_name'],$value['image'],(int)$value['option_value_sort_order'],(int)$value['quantity'],(int)$value['subtract'],$value['price'],$value['price_prefix'],(int)$value['points'],$value['points_prefix'],$value['weight'],$value['weight_prefix']),$delimiter);}
                }else{
                    $this->writeCsvRow($output,array((int)$option['product_id'],$option['model'],$option['sku'],$option['product_name'],(int)$option['option_id'],$option['option_name'],$option['type'],(int)$option['option_sort_order'],(int)$option['required'],$option['value'],'','','','','','','','','','','',''),$delimiter);
                }
            }
        }while($optionsRows);
    }

    private function writeCsvRow($output,$row,$delimiter){
        $safe=array();foreach($row as $value){$safe[]=$this->guardCsvFormula($value);}fputcsv($output,$safe,$delimiter,'"','\\');
    }
    private function guardCsvFormula($value){$value=(string)$value;if($value!==''&&in_array($value[0],array('=','+','-','@'),true)){return "'".$value;}return $value;}
    private function getSeoKeywordForExport($query,$storeId,$languageId){if(!$this->tableExists('seo_url')){return '';} $q=$this->db->query("SELECT keyword FROM `".DB_PREFIX."seo_url` WHERE query='".$this->db->escape($query)."' AND store_id='".(int)$storeId."' AND language_id='".(int)$languageId."' LIMIT 1");return $q->num_rows?$q->row['keyword']:'';}
    private function getCategoryPathName($categoryId,$languageId,$separator){$separator=(string)$separator;if($separator===''){$separator='>';} $names=array();if($this->tableExists('category_path')){$rows=$this->db->query("SELECT cd.name FROM `".DB_PREFIX."category_path` cp INNER JOIN `".DB_PREFIX."category_description` cd ON(cd.category_id=cp.path_id AND cd.language_id='".(int)$languageId."') WHERE cp.category_id='".(int)$categoryId."' ORDER BY cp.level")->rows;foreach($rows as $row){$names[]=$row['name'];}}if(!$names){$q=$this->db->query("SELECT name FROM `".DB_PREFIX."category_description` WHERE category_id='".(int)$categoryId."' AND language_id='".(int)$languageId."' LIMIT 1");if($q->num_rows){$names[]=$q->row['name'];}}return implode(' '.$separator.' ',$names);}

    private function getDefaultHeaders($entityType) {
        if ($entityType === 'product') { return array('_ID_','_NAME_','_MODEL_','_PRICE_','_QUANTITY_','_CATEGORY_','_MANUFACTURER_'); }
        if ($entityType === 'category') { return array('_ID_','_PARENT_ID_','_NAME_'); }
        if ($entityType === 'option') { return array('_PRODUCT_ID_','_OPTION_','_OPTION_TYPE_','_OPTION_VALUE_','_QUANTITY_','_PRICE_'); }
        return array('_ID_','_NAME_','_IMAGE_','_SORT_ORDER_');
    }

    private function rowMatchesColumnMap($row, $options) {
        $custom = isset($options['column_map']) && is_array($options['column_map']) ? $options['column_map'] : array();
        if (!$custom) { return false; }
        foreach ((array)$row as $cell) {
            $lookup = $this->textLower(trim((string)$cell));
            if ($lookup !== '' && isset($custom[$lookup])) { return true; }
        }
        return false;
    }

    private function rowLooksLikeHeader($row) {
        $recognized = 0;
        $nonEmpty = 0;
        $known = array('id','product_id','category_id','manufacturer_id','name','model','sku','upc','ean','price','quantity','category','categories','manufacturer','option','option_value','product_model','product_sku','product_name','description','status','image','sort_order');
        foreach ($row as $cell) {
            $normalized = $this->normalizeFieldName($cell);
            if ($normalized === '') { continue; }
            $nonEmpty++;
            if (preg_match('/^_[A-Z0-9_=-]+_$/', $normalized)) { return true; }
            if (in_array($normalized, $known, true)) { $recognized++; }
        }
        if ($nonEmpty === 1) { return $recognized === 1; }
        return $recognized >= 2;
    }

    private function validateHeaders($headers) {
        if (!$headers) { return 'CSV не содержит заголовков.'; }
        if (count($headers) > 500) { return 'CSV содержит более 500 колонок.'; }
        $seen = array();
        foreach ($headers as $header) {
            $normalized = $this->normalizeFieldName($header);
            if ($normalized === '') { continue; }
            if ($this->textLength($normalized) > 128 || preg_match('/[\x00-\x1F\x7F]/', $normalized)) {
                return 'CSV содержит недопустимый или слишком длинный заголовок.';
            }
            if (isset($seen[$normalized])) { return 'CSV содержит повторяющийся заголовок: ' . $header . '.'; }
            $seen[$normalized] = true;
        }
        return $seen ? '' : 'CSV не содержит ни одного непустого заголовка.';
    }

    private function cleanCsvRow($row, $encoding) {
        $clean = array();
        foreach ($row as $value) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value);
            if ($encoding !== 'UTF-8') {
                if (function_exists('mb_convert_encoding')) {
                    $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);
                    if ($converted !== false) { $value = $converted; }
                } elseif (function_exists('iconv')) {
                    $converted = @iconv($encoding, 'UTF-8//STRICT', $value);
                    if ($converted !== false) { $value = $converted; }
                }
            }
            $value = trim($value);
            if (strlen($value) > 1 && $value[0] === "'" && in_array($value[1], array('=','+','-','@'), true)) { $value = substr($value, 1); }
            $clean[] = $value;
        }
        return $clean;
    }

    private function isValidUtf8Row($row) {
        foreach ($row as $value) {
            $value = (string)$value;
            if (function_exists('mb_check_encoding')) {
                if (!mb_check_encoding($value, 'UTF-8')) { return false; }
            } elseif (!preg_match('//u', $value)) {
                return false;
            }
        }
        return true;
    }

    private function rowIsEmpty($row) { foreach ($row as $value) { if (trim((string)$value) !== '') { return false; } } return true; }

    private function normalizeFieldName($name) {
        $name = trim((string)$name);
        if ($name === '') { return ''; }
        return substr($name, 0, 1) === '_' ? strtoupper($name) : $this->textLower($name);
    }

    private function payloadValueWithPresence($payload, $keys) {
        foreach ($keys as $key) {
            $normalized = $this->normalizeFieldName($key);
            if (array_key_exists($normalized, $payload)) { return array('present' => true, 'value' => (string)$payload[$normalized]); }
            if (isset($payload['raw']) && is_array($payload['raw']) && array_key_exists($key, $payload['raw'])) { return array('present' => true, 'value' => (string)$payload['raw'][$key]); }
        }
        return array('present' => false, 'value' => '');
    }

    private function payloadHasAny($payload, $keys) {
        foreach ($keys as $key) { if ($this->payloadValueWithPresence($payload, array($key))['present']) { return true; } }
        return false;
    }

    private function payloadGet($payload, $keys, $default = '') {
        $result = $this->payloadValueWithPresence($payload, $keys);
        return $result['present'] && trim((string)$result['value']) !== '' ? trim((string)$result['value']) : $default;
    }

    private function splitMulti($value, $separator) {
        $pattern = '/[' . preg_quote($separator, '/') . '\n\r]+/u';
        $parts = preg_split($pattern, (string)$value);
        $result = array();
        foreach ($parts as $part) { $part = trim($part); if ($part !== '') { $result[] = $part; } }
        return $result;
    }

    private function splitPath($path, $separator) {
        $parts = explode($separator, (string)$path); $result = array();
        foreach ($parts as $part) { $part = trim($part); if ($part !== '') { $result[] = $part; } }
        return $result;
    }

    private function toDecimal($value) { $value = str_replace(array("\xc2\xa0",' '), '', (string)$value); $value = str_replace(',', '.', $value); return (float)$value; }
    private function getOption($options, $key, $default) { return isset($options[$key]) && $options[$key] !== '' ? $options[$key] : $default; }
    private function decodeJson($json) { $data = json_decode((string)$json, true); return is_array($data) ? $data : array(); }
    private function encodeJson($data) { $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); return $json === false ? '{}' : $json; }
    private function normalizeEntityType($type) { return in_array($type, array('product','category','manufacturer','option'), true) ? $type : 'product'; }
    private function normalizeMode($mode) { return in_array($mode, array('upsert','update','add','delete'), true) ? $mode : 'upsert'; }
    private function normalizeEncoding($encoding) { $encoding = strtoupper(str_replace('_','-',trim((string)$encoding))); if (in_array($encoding,array('WINDOWS-1251','CP1251'),true)) { return 'Windows-1251'; } return 'UTF-8'; }
    private function isValidOptionType($type) { return in_array($type, array('select','radio','checkbox','image','text','textarea','file','date','time','datetime'), true); }

    private function resolveDelimiter($delimiter, $path) {
        if ($delimiter === 'tab' || $delimiter === "\t") { return "\t"; }
        if (in_array($delimiter, array(';', ',', '|'), true)) { return $delimiter; }
        $bestDelimiter = ';';
        $bestColumns = 1;
        foreach (array(';', ',', "\t", '|') as $candidate) {
            $handle = @fopen($path, 'rb');
            if (!$handle) { continue; }
            $row = fgetcsv($handle, 0, $candidate, '"', '\\');
            fclose($handle);
            $columns = is_array($row) ? count($row) : 0;
            if ($columns > $bestColumns) {
                $bestColumns = $columns;
                $bestDelimiter = $candidate;
            }
        }
        return $bestDelimiter;
    }

    private function createBatchId() { try { return bin2hex(random_bytes(20)); } catch (Throwable $e) { return sha1(uniqid('', true) . mt_rand()); } }
    private function createWorkerToken() { try { return bin2hex(random_bytes(24)); } catch (Throwable $e) { return sha1(uniqid('', true) . mt_rand()); } }
    private function sanitizeFileName($name) { $name = basename(str_replace('\\','/',(string)$name)); $name = preg_replace('/[^\pL\pN._ -]+/u','_',$name); return $this->truncate($name,255); }
    private function truncate($value,$length){$value=(string)$value;if(function_exists('mb_substr')){return mb_substr($value,0,$length,'UTF-8');}return substr($value,0,$length);}
    private function textLower($value){
        $value=(string)$value;
        if(function_exists('mb_strtolower')){return mb_strtolower($value,'UTF-8');}
        $value=strtr($value,array(
            'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я','І'=>'і','Ї'=>'ї','Є'=>'є','Ґ'=>'ґ'
        ));
        return strtolower($value);
    }
    private function textLength($value){$value=(string)$value;return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);}
    private function textSubstr($value, $start, $length){$value=(string)$value;return function_exists('mb_substr')?mb_substr($value,(int)$start,(int)$length,'UTF-8'):substr($value,(int)$start,(int)$length);}

    private function getStorageDirectory() { return rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . self::STORAGE_DIR . DIRECTORY_SEPARATOR; }
    private function ensureStorageDirectory() {
        $directory = $this->getStorageDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0750, true)) {
            throw new RuntimeException('Не удалось создать постоянный каталог модуля.');
        }
        $storageRoot = realpath(rtrim(DIR_STORAGE, '/\\'));
        $realDirectory = realpath($directory);
        if ($storageRoot === false || $realDirectory === false) {
            throw new RuntimeException('Не удалось проверить постоянный каталог модуля.');
        }
        $root = rtrim(str_replace('\\', '/', $storageRoot), '/') . '/';
        $target = rtrim(str_replace('\\', '/', $realDirectory), '/') . '/';
        if (strpos($target, $root) !== 0) {
            throw new RuntimeException('Постоянный каталог модуля выходит за пределы DIR_STORAGE.');
        }
        return rtrim($realDirectory, '/\\') . DIRECTORY_SEPARATOR;
    }
    private function validateStoredSourcePath($path) {
        $path = (string)$path;
        if ($path === '') { return ''; }
        $base = realpath($this->getStorageDirectory());
        $realPath = realpath($path);
        if ($base === false || $realPath === false || !is_file($realPath)) { return ''; }
        $baseNormalized = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $pathNormalized = str_replace('\\', '/', $realPath);
        return strpos($pathNormalized, $baseNormalized) === 0 ? $realPath : '';
    }
    private function removeDirectory($directory) {
        if (is_link($directory)) { @unlink($directory); return; }
        if (!is_dir($directory)) { return; }
        $items = @scandir($directory);
        if (!is_array($items)) { return; }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) { @unlink($path); }
            elseif (is_dir($path)) { $this->removeDirectory($path); }
            else { @unlink($path); }
        }
        @rmdir($directory);
    }
}
