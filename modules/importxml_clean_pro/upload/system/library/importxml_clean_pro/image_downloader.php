<?php
/** CodeCart PRO: bounded image downloads with verified public destinations. */
class ImportxmlCleanProImageDownloader {
    private const MIME_EXTENSIONS = array('image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp');

    public function download($url, $directory, $baseName) {
        if (!function_exists('curl_init') || !function_exists('getimagesizefromstring')) { return ''; }
        for ($redirect = 0; $redirect <= 5; $redirect++) {
            $destination = $this->destination($url);
            if (!$destination) { return ''; }
            $body = '';
            $location = '';
            $ch = curl_init($url);
            $address = strpos($destination['ip'], ':') !== false ? '['.$destination['ip'].']' : $destination['ip'];
            curl_setopt_array($ch, array(
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_RETURNTRANSFER=>false,
                CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>20,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_PROXY=>'',
                CURLOPT_RESOLVE=>array($destination['host'].':'.$destination['port'].':'.$address),
                CURLOPT_USERAGENT=>'CodeCart PRO ImportXML image downloader',
                CURLOPT_HEADERFUNCTION=>static function ($handle, $header) use (&$location) {
                    if (stripos($header, 'Location:') === 0) { $location = trim(substr($header, 9)); }
                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION=>static function ($handle, $chunk) use (&$body) {
                    if (strlen($body) + strlen($chunk) > 10 * 1024 * 1024) { return 0; }
                    $body .= $chunk;
                    return strlen($chunk);
                }
            ));
            $ok = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($ok === false) { return ''; }
            if (in_array($code, array(301, 302, 303, 307, 308), true)) {
                $url = $this->redirectUrl($url, $location);
                if ($url === '') { return ''; }
                continue;
            }
            if ($code !== 200) { return ''; }
            $extension = $this->imageExtension($body);
            if ($extension === '') { return ''; }
            $directory = rtrim($directory, '/\\');
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { return ''; }
            $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
            $filename = substr($baseName ?: 'image', 0, 120).'-'.substr(hash('sha256', $body), 0, 16).'.'.$extension;
            $target = $directory.DIRECTORY_SEPARATOR.$filename;
            if (is_file($target)) { return $filename; }
            $temp = tempnam($directory, '.ccp-image-');
            if ($temp === false) { return ''; }
            $written = file_put_contents($temp, $body, LOCK_EX);
            if ($written !== strlen($body) || !rename($temp, $target)) { @unlink($temp); return ''; }
            return $filename;
        }
        return '';
    }

    public function imageExtension($body) {
        $info = @getimagesizefromstring($body);
        if (!$info || empty($info['mime']) || !isset(self::MIME_EXTENSIONS[$info['mime']])) { return ''; }
        // Avoid decompression bombs even when compressed download fits the byte limit.
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > 20000 || $info[1] > 20000 || $info[0] * $info[1] > 40000000) { return ''; }
        return self::MIME_EXTENSIONS[$info['mime']];
    }

    public function destination($url) {
        $parts = parse_url((string)$url);
        if (!$parts || empty($parts['host']) || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true) || isset($parts['user']) || isset($parts['pass'])) { return false; }
        $port = isset($parts['port']) ? (int)$parts['port'] : (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        if (!in_array($port, array(80, 443), true)) { return false; }
        $host = trim(strtolower($parts['host']), '[]');
        $ips = array();
        if (filter_var($host, FILTER_VALIDATE_IP)) { $ips[] = $host; }
        elseif (preg_match('/^[a-z0-9.-]+$/D', $host)) {
            foreach ((array)@dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
                if (isset($record['ip'])) { $ips[] = $record['ip']; }
                if (isset($record['ipv6'])) { $ips[] = $record['ipv6']; }
            }
        }
        if (!$ips) { return false; }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || stripos($ip, '::ffff:') === 0 || $ip === '169.254.169.254') { return false; }
        }
        return array('host'=>$host, 'port'=>$port, 'ip'=>$ips[0]);
    }

    private function redirectUrl($base, $location) {
        if ($location === '' || preg_match('/[\r\n]/', $location)) { return ''; }
        if (preg_match('#^https?://#i', $location)) { return $location; }
        $parts = parse_url($base);
        if (substr($location, 0, 2) === '//') { return $parts['scheme'].':'.$location; }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) { return ''; }
        $authority = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        return $authority.(substr($location, 0, 1) === '/' ? $location : rtrim(dirname(isset($parts['path']) ? $parts['path'] : '/'), '/').'/'.$location);
    }
}
