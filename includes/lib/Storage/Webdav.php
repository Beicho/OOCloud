<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * Simple WebDAV storage adapter.
 *
 * Notes:
 * - Expects a base URL to a writable WebDAV collection (no trailing slash).
 * - Uses Basic Auth if username/password are provided.
 * - Stores files under a fixed prefix `file/` to keep consistent with other drivers.
 * - Direct-upload (getUploadParam) is not supported for security; returns false.
 */
class Webdav implements IStorage {
    private $config;
    private $errmsg;
    private $filepath = 'file/';

    public function __construct($config){
        // $config keys: base, user, pass
        $this->config = [
            'base' => rtrim($config['base'] ?? '', '/'),
            'user' => $config['user'] ?? '',
            'pass' => $config['pass'] ?? '',
        ];
    }

    public function getClient(){
        return null;
    }

    public function errmsg(){
        return $this->errmsg;
    }

    private function buildUrl($name){
        return $this->config['base'] . '/' . $this->filepath . $name;
    }

    private function curl($method, $url, $opts = []){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if(!empty($this->config['user'])){
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['user'] . ':' . $this->config['pass']);
        }
        foreach($opts as $k=>$v){
            curl_setopt($ch, $k, $v);
        }
        $resp = curl_exec($ch);
        if($resp === false){
            $this->errmsg = 'curl error: ' . curl_error($ch);
            curl_close($ch);
            return [0, [], ''];
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header_str = substr($resp, 0, $header_size);
        $body = substr($resp, $header_size);
        curl_close($ch);
        // Parse headers into array
        $headers = [];
        foreach(explode("\r\n", trim($header_str)) as $line){
            if(strpos($line, ':') !== false){
                list($hk, $hv) = explode(':', $line, 2);
                $headers[strtolower(trim($hk))] = trim($hv);
            }
        }
        return [$status, $headers, $body];
    }

    private function ensurePrefix(){
        // Try to create `file/` collection if not exists
        $url = $this->config['base'] . '/' . rtrim($this->filepath, '/');
        list($status,) = $this->curl('PROPFIND', $url, [CURLOPT_NOBODY => true]);
        if($status >= 200 && $status < 400){
            return true;
        }
        list($mk, ) = $this->curl('MKCOL', $url);
        return ($mk >= 200 && $mk < 300) || $mk == 405; // 405 if already exists
    }

    public function exists($name){
        $url = $this->buildUrl($name);
        list($status,) = $this->curl('HEAD', $url, [CURLOPT_NOBODY => true]);
        if($status >= 200 && $status < 300){
            return true;
        }
        // Fallback to a ranged GET (some servers disallow HEAD)
        list($gstatus,) = $this->curl('GET', $url, [CURLOPT_HTTPHEADER => ['Range: bytes=0-0']]);
        return ($gstatus >= 200 && $gstatus < 300) || $gstatus == 206;
    }

    public function get($name){
        $url = $this->buildUrl($name);
        list($status,, $body) = $this->curl('GET', $url);
        if($status >= 200 && $status < 300){
            return $body;
        }
        $this->errmsg = __FUNCTION__ . ': http ' . $status;
        return false;
    }

    public function downfile($name, $range = false){
        // Stream to output to avoid memory blowup on large files
        $url = $this->buildUrl($name);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        if(!empty($this->config['user'])){
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['user'] . ':' . $this->config['pass']);
        }
        if($range){
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Range: bytes='.$range[0].'-'.$range[1]]);
        }
        $fh = fopen('php://output', 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fh);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($ok === false){
            $this->errmsg = 'curl error: '.curl_error($ch);
        }
        curl_close($ch);
        fclose($fh);
        return ($status >= 200 && $status < 300) || ($status == 206 && $range);
    }

    public function upload($name, $tmpfile, $content_type = null){
        if(!$this->ensurePrefix()){
            $this->errmsg = 'ensure prefix failed';
            return false;
        }
        $url = $this->buildUrl($name);
        $fp = fopen($tmpfile, 'rb');
        $opts = [
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => filesize($tmpfile),
        ];
        $headers = [];
        if($content_type){
            $headers[] = 'Content-Type: '.$content_type;
        }
        if($headers){
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        list($status,) = $this->curl('PUT', $url, $opts);
        fclose($fp);
        if($status >= 200 && $status < 300){
            return true;
        }
        $this->errmsg = __FUNCTION__ . ': http ' . $status;
        return false;
    }

    public function savefile($name, $tmpfile, $content_type = null){
        return $this->upload($name, $tmpfile, $content_type);
    }

    public function getinfo($name){
        $url = $this->buildUrl($name);
        list($status, $headers,) = $this->curl('HEAD', $url, [CURLOPT_NOBODY => true]);
        if($status >= 200 && $status < 300){
            return [
                'length' => isset($headers['content-length']) ? intval($headers['content-length']) : 0,
                'content_type' => $headers['content-type'] ?? null,
            ];
        }
        $this->errmsg = __FUNCTION__ . ': http ' . $status;
        return false;
    }

    public function delete($name){
        $url = $this->buildUrl($name);
        list($status,) = $this->curl('DELETE', $url);
        if($status >= 200 && $status < 300){
            return true;
        }
        $this->errmsg = __FUNCTION__ . ': http ' . $status;
        return false;
    }

    public function getUploadParam($name, $filename, $max_file_size = 0){
        // Not supported for WebDAV to avoid exposing credentials client-side.
        return false;
    }

    public function getDownUrl($name, $filename, $content_type = null){
        // Direct URL (assumes public). Optionally rewrite host via downfile_domain.
        $url = $this->config['base'] . '/' . $this->filepath . $name;
        global $conf;
        if(!empty($conf['downfile_domain'])){
            $arr = parse_url($url);
            $scheme = ($conf['downfile_protocol']==1?'https':'http');
            $url = $scheme.'://'.$conf['downfile_domain'].
                (isset($arr['path'])?$arr['path']:'') .
                (isset($arr['query'])?'?'.$arr['query']:'');
        }
        return $url;
    }
}
