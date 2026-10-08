<?php
/** Persistent batches over an immutable source snapshot. Caller performs module/auth guards. */
class ImportProCronRunner {
    private $db;
    private $directory;
    public function __construct($db, $directory) {
        $this->db = $db;
        $this->directory = rtrim($directory, '/\\');
    }
    private function writeAtomic($path, $content) {
        $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temp, $content, LOCK_EX) === false) { throw new RuntimeException('Cannot save cron state'); }
        if (!rename($temp, $path)) { @unlink($temp); throw new RuntimeException('Cannot replace cron state'); }
    }
    public function run($profile, $mode, $dry_run, $limit, $prepare, $apply) {
        if (!in_array($mode,array('price_only','quantity_only','price_stock'),true)) { throw new InvalidArgumentException('Invalid cron mode'); }
        $id = (int)$profile['profile_id'];
        if ($id <= 0) { throw new InvalidArgumentException('Invalid cron profile'); }
        $mutex = 'ccp_import_cron_' . $id;
        $lock = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($mutex) . "', 0) AS acquired");
        if (empty($lock->row['acquired'])) { throw new RuntimeException('This import profile is already running'); }
        try {
            if (!is_dir($this->directory) && !mkdir($this->directory,0750,true) && !is_dir($this->directory)) { throw new RuntimeException('Cannot create permanent cron storage'); }
            $key = 'profile_' . $id . '_' . $mode . ($dry_run ? '_preview' : '_apply');
            $state_path = $this->directory . '/' . $key . '.json';
            $snapshot = $this->directory . '/' . $key . '.source';
            $state = is_file($state_path) ? json_decode(file_get_contents($state_path),true) : array();
            if (!is_array($state)) { throw new RuntimeException('Cron state is damaged; review it before restarting'); }
            $fingerprint = hash('sha256',json_encode($profile));
            if (empty($state['active']) || !isset($state['profile_hash']) || $state['profile_hash'] !== $fingerprint) {
                $source = $prepare($profile);
                if (!is_file($source) || !is_readable($source)) { throw new RuntimeException('Cron source is unavailable'); }
                $temp = $snapshot . '.' . bin2hex(random_bytes(6)) . '.tmp';
                if (!copy($source,$temp)) { @unlink($temp); throw new RuntimeException('Cannot create price file snapshot'); }
                if (!rename($temp,$snapshot)) { @unlink($temp); throw new RuntimeException('Cannot save price file snapshot'); }
                $state = array('active'=>true,'offset'=>0,'profile_hash'=>$fingerprint,'source_hash'=>hash_file('sha256',$snapshot),'started_at'=>date('Y-m-d H:i:s'),'processed'=>0,'errors'=>0);
                $this->writeAtomic($state_path,json_encode($state));
            }
            if (!is_file($snapshot) || !hash_equals($state['source_hash'],hash_file('sha256',$snapshot))) { throw new RuntimeException('Price file snapshot changed; batch was not applied'); }
            $result = $apply($snapshot,(int)$state['offset'],max(1,min(1000,(int)$limit)),$state['started_at']);
            if (!is_array($result) || !isset($result['has_more'],$result['next_offset'])) { throw new RuntimeException('Invalid cron batch result'); }
            $state['offset'] = !empty($result['has_more']) ? (int)$result['next_offset'] : 0;
            $state['active'] = !empty($result['has_more']);
            $state['processed'] += isset($result['processed']) ? (int)$result['processed'] : 0;
            $state['errors'] += isset($result['errors']) ? (int)$result['errors'] : 0;
            $state['updated_at'] = date('Y-m-d H:i:s');
            $this->writeAtomic($state_path,json_encode($state));
            if (!$state['active']) { @unlink($snapshot); }
            $result['cycle_processed'] = $state['processed'];
            $result['cycle_errors'] = $state['errors'];
            $result['scheduled'] = true;
            return $result;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($mutex) . "')");
        }
    }
}
