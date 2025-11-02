<?php
namespace lib\Storage;
use \lib\IStorage;

/**
 * 123存储 OpenAPI 驱动（通用REST风格，需在后台配置Base与Token）
 * 假设REST路径：{base}/file/{name}
 * Header：Authorization: Bearer {token}
 */
class Openapi123 implements IStorage {
    private $config;
    private $errmsg;
    private $filepath = 'file/';
    private $lastName = null;
    private $lastDownUrl = null;

    public function __construct($config){
        $this->config = [
            'base' => rtrim($config['base'] ?? '', '/'),
            'token' => $config['token'] ?? '', // 可选：直接填写现有token
            'public' => rtrim($config['public'] ?? '', '/'),
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'parent' => isset($config['parent']) ? (int)$config['parent'] : 0,
            'keep_name' => !empty($config['keep_name']) ? true : false,
            'duplicate' => isset($config['duplicate']) ? (int)$config['duplicate'] : 1,
            'domain_strategy' => isset($config['domain_strategy']) ? (int)$config['domain_strategy'] : 0,
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'use_client_api' => !empty($config['use_client_api']) ? true : false,
            's3keyflag' => $config['s3keyflag'] ?? '',
            // 性能优化配置
            'cache_metadata' => !empty($config['cache_metadata']) ? true : false,
            'cache_ttl' => isset($config['cache_ttl']) ? (int)$config['cache_ttl'] : 604800,
            'rate_limit' => !empty($config['rate_limit']) ? true : false,
            'rate_max' => isset($config['rate_max']) ? (int)$config['rate_max'] : 60,
            'circuit_breaker' => !empty($config['circuit_breaker']) ? true : false,
            'circuit_threshold' => isset($config['circuit_threshold']) ? (int)$config['circuit_threshold'] : 50,
            'circuit_timeout' => isset($config['circuit_timeout']) ? (int)$config['circuit_timeout'] : 300,
            'random_delay' => !empty($config['random_delay']) ? true : false,
        ];
    }

    private function url($name){
        return $this->config['base'].'/'.$this->filepath.$name;
    }

    private function authHeader(){
        $token = $this->ensureAccessToken();
        return empty($token) ? [] : ['Authorization: Bearer '.$token, 'Platform: open_platform'];
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
        $headers = [];
        $isTokenUrl = (strpos($url, '/api/v1/access_token') !== false);
        if(!$isTokenUrl){
            // 非获取token接口，附带鉴权头
            $headers = array_merge($headers, $this->authHeader());
        }else{
            // 获取token接口也需要Platform头
            $headers[] = 'Platform: open_platform';
        }
        if(isset($opts['headers']) && is_array($opts['headers'])){
            $headers = array_merge($headers, $opts['headers']);
            unset($opts['headers']);
        }
        if(!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        foreach($opts as $k=>$v){ curl_setopt($ch, $k, $v); }
        $resp = curl_exec($ch);
        if($resp === false){
            $this->errmsg = 'curl error: ' . curl_error($ch);
            curl_close($ch);
            return [0, [], ''];
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($resp, $header_size);
        curl_close($ch);
        return [$status, [], $body];
    }

    public function getClient(){ return null; }
    public function errmsg(){ return $this->errmsg; }

    private function logDebug($message, $data = null){
        // 日志文件路径
        $logFile = ROOT.'data/123api_debug.log';
        $logDir = dirname($logFile);
        if(!is_dir($logDir)){
            @mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";
        if($data !== null){
            $logEntry .= "\n" . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        $logEntry .= "\n" . str_repeat('-', 80) . "\n";

        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // ========== 性能优化辅助方法 ==========

    /**
     * 从缓存获取文件元数据
     */
    private function getCachedMetadata($fileId){
        if(!$this->config['cache_metadata']){
            return null;
        }

        global $DB;
        $row = $DB->getRow("SELECT * FROM pre_file_metadata WHERE file_id=:fid LIMIT 1", [':fid' => $fileId]);
        if(!$row){
            $this->logDebug("缓存未命中", ['fileId' => $fileId]);
            return null;
        }

        $cacheTime = intval($row['cache_time']);
        $age = time() - $cacheTime;
        $ttl = $this->config['cache_ttl'];

        if($age > $ttl){
            $this->logDebug("缓存已过期", [
                'fileId' => $fileId,
                'age_seconds' => $age,
                'ttl_seconds' => $ttl
            ]);
            // 删除过期缓存
            $DB->exec("DELETE FROM pre_file_metadata WHERE file_id=:fid", [':fid' => $fileId]);
            return null;
        }

        $this->logDebug("缓存命中", [
            'fileId' => $fileId,
            'age_seconds' => $age,
            'remaining_seconds' => $ttl - $age,
            'filename' => $row['filename']
        ]);

        return [
            'fileName' => $row['filename'],
            'etag' => $row['etag'],
            's3keyFlag' => $row['s3keyflag'],
            'size' => intval($row['size'])
        ];
    }

    /**
     * 缓存文件元数据
     */
    private function setCachedMetadata($fileId, $metadata){
        if(!$this->config['cache_metadata']){
            return;
        }

        global $DB;
        try{
            $DB->exec("REPLACE INTO pre_file_metadata (file_id, filename, etag, s3keyflag, size, cache_time) VALUES (:fid, :fn, :et, :s3k, :sz, :ct)", [
                ':fid' => $fileId,
                ':fn' => $metadata['fileName'],
                ':et' => $metadata['etag'],
                ':s3k' => $metadata['s3keyFlag'],
                ':sz' => $metadata['size'],
                ':ct' => time()
            ]);
            $this->logDebug("元数据已缓存", [
                'fileId' => $fileId,
                'filename' => $metadata['fileName'],
                'ttl_seconds' => $this->config['cache_ttl']
            ]);
        }catch(\Exception $e){
            $this->logDebug("缓存元数据失败", ['error' => $e->getMessage()]);
        }
    }

    /**
     * 随机延迟（模拟真人操作）
     */
    private function randomDelay(){
        if(!$this->config['random_delay']){
            return;
        }

        $delayMs = mt_rand(50, 200);
        $this->logDebug("随机延迟", ['delay_ms' => $delayMs]);
        usleep($delayMs * 1000);
    }

    /**
     * 检查并等待请求频率限制
     * @return bool 是否通过限流检查
     */
    private function checkRateLimit($apiType = 'client_api'){
        if(!$this->config['rate_limit']){
            return true;
        }

        global $DB;
        $minuteKey = date('YmdHi'); // 按分钟分组
        $maxRequests = $this->config['rate_max'];

        try{
            // 获取当前分钟的请求计数
            $row = $DB->getRow("SELECT * FROM pre_api_rate_limit WHERE api_type=:type AND minute_key=:key", [
                ':type' => $apiType,
                ':key' => $minuteKey
            ]);

            if(!$row){
                // 首次请求，创建记录
                $DB->exec("INSERT INTO pre_api_rate_limit (api_type, minute_key, request_count, fail_count, update_time) VALUES (:type, :key, 1, 0, :time)", [
                    ':type' => $apiType,
                    ':key' => $minuteKey,
                    ':time' => time()
                ]);
                $this->logDebug("频率限制：首次请求", ['api_type' => $apiType, 'minute_key' => $minuteKey]);
                return true;
            }

            $requestCount = intval($row['request_count']);
            if($requestCount >= $maxRequests){
                // 超出限制，尝试等待并重试
                $this->logDebug("频率限制：超出限制", [
                    'api_type' => $apiType,
                    'current_count' => $requestCount,
                    'max_requests' => $maxRequests
                ]);

                // 等待最多3次，每次等待100-500ms
                for($i = 0; $i < 3; $i++){
                    $waitMs = mt_rand(100, 500);
                    $this->logDebug("频率限制：等待重试", ['attempt' => $i+1, 'wait_ms' => $waitMs]);
                    usleep($waitMs * 1000);

                    // 重新检查是否已进入下一分钟
                    $newMinuteKey = date('YmdHi');
                    if($newMinuteKey !== $minuteKey){
                        $this->logDebug("频率限制：进入新的时间窗口", ['new_minute_key' => $newMinuteKey]);
                        // 创建新分钟的记录
                        $DB->exec("INSERT INTO pre_api_rate_limit (api_type, minute_key, request_count, fail_count, update_time) VALUES (:type, :key, 1, 0, :time)", [
                            ':type' => $apiType,
                            ':key' => $newMinuteKey,
                            ':time' => time()
                        ]);
                        return true;
                    }
                }

                $this->logDebug("频率限制：等待失败，拒绝请求");
                $this->errmsg = 'API请求频率超限，请稍后再试';
                return false;
            }

            // 未超限，增加计数
            $DB->exec("UPDATE pre_api_rate_limit SET request_count=request_count+1, update_time=:time WHERE api_type=:type AND minute_key=:key", [
                ':type' => $apiType,
                ':key' => $minuteKey,
                ':time' => time()
            ]);

            $this->logDebug("频率限制：通过", [
                'api_type' => $apiType,
                'count' => $requestCount + 1,
                'limit' => $maxRequests,
                'remaining' => $maxRequests - $requestCount - 1
            ]);

            return true;
        }catch(\Exception $e){
            $this->logDebug("频率限制检查失败", ['error' => $e->getMessage()]);
            // 出错时允许通过，避免误伤
            return true;
        }
    }

    /**
     * 记录API失败
     */
    private function recordApiFailure($apiType = 'client_api'){
        if(!$this->config['circuit_breaker']){
            return;
        }

        global $DB;
        $minuteKey = date('YmdHi');

        try{
            $DB->exec("UPDATE pre_api_rate_limit SET fail_count=fail_count+1, update_time=:time WHERE api_type=:type AND minute_key=:key", [
                ':type' => $apiType,
                ':key' => $minuteKey,
                ':time' => time()
            ]);
            $this->logDebug("记录API失败", ['api_type' => $apiType]);
        }catch(\Exception $e){
            $this->logDebug("记录失败计数错误", ['error' => $e->getMessage()]);
        }
    }

    /**
     * 检查熔断器状态
     * @return bool true=熔断开启（禁止请求），false=正常
     */
    private function isCircuitOpen($apiType = 'client_api'){
        if(!$this->config['circuit_breaker']){
            return false;
        }

        global $DB;
        $minuteKey = date('YmdHi');

        try{
            $row = $DB->getRow("SELECT * FROM pre_api_rate_limit WHERE api_type=:type AND minute_key=:key", [
                ':type' => $apiType,
                ':key' => $minuteKey
            ]);

            if(!$row){
                return false;
            }

            // 检查是否处于熔断状态
            if(intval($row['circuit_open']) === 1){
                $circuitOpenTime = intval($row['circuit_open_time']);
                $circuitTimeout = $this->config['circuit_timeout'];
                $elapsed = time() - $circuitOpenTime;

                if($elapsed < $circuitTimeout){
                    $this->logDebug("熔断器开启中", [
                        'api_type' => $apiType,
                        'elapsed_seconds' => $elapsed,
                        'timeout_seconds' => $circuitTimeout,
                        'remaining_seconds' => $circuitTimeout - $elapsed
                    ]);
                    $this->errmsg = '客户端API暂时不可用（熔断保护），使用Web API';
                    return true;
                }else{
                    // 超时，尝试恢复
                    $this->logDebug("熔断器超时，尝试恢复", ['api_type' => $apiType]);
                    $DB->exec("UPDATE pre_api_rate_limit SET circuit_open=0, circuit_open_time=0, fail_count=0 WHERE api_type=:type AND minute_key=:key", [
                        ':type' => $apiType,
                        ':key' => $minuteKey
                    ]);
                    return false;
                }
            }

            // 检查是否需要触发熔断
            $requestCount = intval($row['request_count']);
            $failCount = intval($row['fail_count']);

            if($requestCount > 0){
                $failRate = ($failCount / $requestCount) * 100;
                $threshold = $this->config['circuit_threshold'];

                if($failRate >= $threshold && $failCount >= 10){
                    // 触发熔断
                    $this->logDebug("触发熔断", [
                        'api_type' => $apiType,
                        'fail_rate' => round($failRate, 2) . '%',
                        'threshold' => $threshold . '%',
                        'fail_count' => $failCount,
                        'request_count' => $requestCount
                    ]);

                    $DB->exec("UPDATE pre_api_rate_limit SET circuit_open=1, circuit_open_time=:time WHERE api_type=:type AND minute_key=:key", [
                        ':type' => $apiType,
                        ':key' => $minuteKey,
                        ':time' => time()
                    ]);

                    $this->errmsg = '客户端API失败率过高，已触发熔断保护';
                    return true;
                }
            }

            return false;
        }catch(\Exception $e){
            $this->logDebug("熔断检查失败", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 带重试的API调用包装器
     */
    private function apiCallWithRetry($callable, $maxRetries = 3, $apiType = 'client_api'){
        $attempt = 0;
        $lastError = '';

        while($attempt < $maxRetries){
            $attempt++;
            $this->logDebug("API调用尝试", ['attempt' => $attempt, 'max_retries' => $maxRetries]);

            $result = call_user_func($callable);

            if($result !== false){
                // 成功
                if($attempt > 1){
                    $this->logDebug("重试成功", ['attempt' => $attempt]);
                }
                return $result;
            }

            // 失败
            $lastError = $this->errmsg;
            $this->recordApiFailure($apiType);
            $this->logDebug("API调用失败", [
                'attempt' => $attempt,
                'error' => $lastError
            ]);

            if($attempt < $maxRetries){
                // 指数退避：1s, 2s, 4s
                $waitSeconds = pow(2, $attempt - 1);
                $this->logDebug("等待重试", ['wait_seconds' => $waitSeconds]);
                sleep($waitSeconds);
            }
        }

        // 所有重试都失败
        $this->logDebug("所有重试均失败", ['total_attempts' => $maxRetries, 'last_error' => $lastError]);
        $this->errmsg = $lastError;
        return false;
    }

    private function ensureAccessToken(){
        $this->logDebug(">>> 开始获取Access Token");

        // 优先使用已配置的固定 token
        if(!empty($this->config['token'])){
            $this->logDebug("使用配置的固定token", ['token' => substr($this->config['token'], 0, 20).'...']);
            return $this->config['token'];
        }

        // 尝试从站点配置读取缓存 token
        global $conf;
        $cached = isset($conf['openapi123_at']) ? $conf['openapi123_at'] : '';
        $exp = isset($conf['openapi123_at_exp']) ? intval($conf['openapi123_at_exp']) : 0;
        $now = time();

        if($cached && $exp > $now + 60){
            $remainingTime = $exp - $now;
            $this->logDebug("使用缓存的token", [
                'token' => substr($cached, 0, 20).'...',
                'expire_at' => date('Y-m-d H:i:s', $exp),
                'remaining_seconds' => $remainingTime
            ]);
            return $cached;
        }elseif($cached){
            $this->logDebug("缓存的token即将过期或已过期", [
                'expire_at' => date('Y-m-d H:i:s', $exp),
                'now' => date('Y-m-d H:i:s', $now)
            ]);
        }

        // 如果配置了账号密码且启用客户端API，优先使用账号密码登录
        if($this->config['use_client_api'] && !empty($this->config['username']) && !empty($this->config['password'])){
            $this->logDebug("使用账号密码登录获取token");
            $token = $this->loginWithPassword();
            if($token){
                $this->logDebug("账号密码登录成功", ['token' => substr($token, 0, 20).'...']);
                return $token;
            }else{
                $this->logDebug("账号密码登录失败");
            }
        }else{
            $this->logDebug("未配置账号密码或未启用客户端API");
        }

        // 无缓存或将过期，使用OpenAPI刷新
        if(empty($this->config['client_id']) || empty($this->config['client_secret'])){
            $this->errmsg = 'missing client_id/client_secret or username/password';
            $this->logDebug("缺少认证信息", ['error' => $this->errmsg]);
            return '';
        }

        $this->logDebug("使用OpenAPI client_id/client_secret获取token");
        $url = $this->config['base'].'/api/v1/access_token';
        $payload = json_encode(['clientID'=>$this->config['client_id'], 'clientSecret'=>$this->config['client_secret']]);
        list($st,, $body) = $this->curl('POST', $url, [
            CURLOPT_POSTFIELDS => $payload,
            'headers' => ['Content-Type: application/json']
        ]);
        if($st>=200 && $st<300){
            $ret = json_decode($body, true);
            if(isset($ret['data'])) $ret = $ret['data'];
            $token = $ret['accessToken'] ?? '';
            $expiredAt = $ret['expiredAt'] ?? '';
            $expTs = $expiredAt ? strtotime($expiredAt) : (time()+3600);
            if($token){
                // 缓存到配置
                if(function_exists('saveSetting')){
                    \saveSetting('openapi123_at', $token);
                    \saveSetting('openapi123_at_exp', strval($expTs));
                    // 同步内存中的 $conf
                    $conf['openapi123_at'] = $token;
                    $conf['openapi123_at_exp'] = $expTs;
                }
                $this->logDebug("OpenAPI获取token成功并已缓存", [
                    'token' => substr($token, 0, 20).'...',
                    'expire_at' => date('Y-m-d H:i:s', $expTs)
                ]);
                return $token;
            }
        }
        $this->errmsg = 'get access_token failed http='.$st.' body='.$body;
        $this->logDebug("OpenAPI获取token失败", ['error' => $this->errmsg]);
        return '';
    }

    private function loginWithPassword(){
        // 使用123云盘账号密码登录获取token
        $loginUrl = 'https://www.123pan.com/api/user/sign_in';
        $payload = json_encode([
            'passport' => $this->config['username'],
            'password' => $this->config['password'],
            'remember' => true
        ]);

        $this->logDebug("账号密码登录请求", [
            'url' => $loginUrl,
            'username' => $this->config['username']
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'platform: android',
            'app-version: 62'
        ]);

        $resp = curl_exec($ch);
        if($resp === false){
            $this->errmsg = 'login curl error: ' . curl_error($ch);
            $this->logDebug("登录请求失败", ['error' => $this->errmsg]);
            curl_close($ch);
            return '';
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($resp, $header_size);
        curl_close($ch);

        $this->logDebug("登录响应", [
            'status' => $status,
            'body' => $body
        ]);

        if($status>=200 && $status<300){
            $ret = json_decode($body, true);
            $token = $ret['data']['token'] ?? '';
            if($token){
                // 缓存token（默认7天有效期）
                global $conf;
                $expTs = time() + 7*24*3600;
                if(function_exists('saveSetting')){
                    \saveSetting('openapi123_at', $token);
                    \saveSetting('openapi123_at_exp', strval($expTs));
                    $conf['openapi123_at'] = $token;
                    $conf['openapi123_at_exp'] = $expTs;
                }
                $this->logDebug("登录成功，token已缓存", [
                    'token' => substr($token, 0, 20).'...',
                    'expire_at' => date('Y-m-d H:i:s', $expTs),
                    'cache_days' => 7
                ]);
                return $token;
            }
        }

        $this->errmsg = 'login with password failed http='.$status.' body='.$body;
        $this->logDebug("登录失败", ['error' => $this->errmsg]);
        return '';
    }

    public function exists($name){
        // name 是站内 MD5，实际文件ID在 pre_file.cloud_id
        global $DB;
        $cid = $DB->getColumn("SELECT cloud_id FROM pre_file WHERE hash=:h LIMIT 1", [':h'=>$name]);
        return !empty($cid);
    }

    public function get($name){
        list($st,, $body) = $this->curl('GET', $this->url($name));
        if($st>=200 && $st<300) return $body;
        $this->errmsg = __FUNCTION__.': http '.$st; return false;
    }

    public function downfile($name, $range = false){
        // 站点中转下载：用服务器出口获取 download_info，再从 downloadUrl 流式回传给客户端
        $down = $this->fetchDownloadUrl($name, /*forwardUserIp*/ false);
        if(!$down){ return false; }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $down);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        $headers = [];
        if($range){ $headers[] = 'Range: bytes='.$range[0].'-'.$range[1]; }
        if(!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $fh = fopen('php://output', 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fh);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($ok === false){ $this->errmsg = 'curl error: '.curl_error($ch); }
        curl_close($ch); fclose($fh);
        return ($status>=200 && $status<300) || ($status==206 && $range);
    }

    public function upload($name, $tmpfile, $content_type = null){
        // 按 openapi.md：create -> slice -> upload_complete
        $size = filesize($tmpfile);
        $md5 = md5_file($tmpfile);
        // 1) create
        $createUrl = $this->config['base'].'/upload/v2/file/create';
        $payload = [
            'parentFileID' => $this->config['parent'] ?: 0,
            'filename' => $this->pickFileName($name),
            'etag' => $md5,
            'size' => $size,
        ];
        if(in_array($this->config['duplicate'], [1,2])){ $payload['duplicate'] = $this->config['duplicate']; }
        list($st,, $body) = $this->curl('POST', $createUrl, [
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => array_merge([], ['Content-Type: application/json'])
        ]);
        if(!($st>=200 && $st<300)){
            $this->errmsg = 'create http='.$st; return false;
        }
        $ret = json_decode($body, true);
        if(isset($ret['data'])) $ret = $ret['data'];
        if(isset($ret['reuse']) && $ret['reuse']===true && !empty($ret['fileID'])){
            if(session_status()===PHP_SESSION_NONE) @session_start();
            $_SESSION['openapi123'][$name] = $ret['fileID'];
            return true;
        }
        $preuploadID = $ret['preuploadID'] ?? '';
        $sliceSize = intval($ret['sliceSize'] ?? 0);
        $servers = $ret['servers'] ?? [];
        if(empty($preuploadID) || $sliceSize<=0 || empty($servers)){
            $this->errmsg = 'create invalid response'; return false;
        }
        $server = $this->chooseUploadServer($servers);
        // 2) slice upload
        $fh = fopen($tmpfile, 'rb');
        $total = 0; $sliceNo = 1; $ok = true;
        while($total < $size){
            $len = min($sliceSize, $size - $total);
            $data = fread($fh, $len);
            if($data===false){ $ok=false; break; }
            $sliceMD5 = md5($data);
            $tmp = tempnam(sys_get_temp_dir(), 'sli');
            file_put_contents($tmp, $data);
            $post = [
                'preuploadID' => $preuploadID,
                'sliceNo' => $sliceNo,
                'sliceMD5' => $sliceMD5,
                'slice' => new \CURLFile($tmp, 'application/octet-stream', 'slice_'.$sliceNo)
            ];
            list($ust,, $ubody) = $this->curl('POST', rtrim($server,'/').'/upload/v2/file/slice', [
                CURLOPT_POSTFIELDS => $post,
            ]);
            @unlink($tmp);
            if(!($ust>=200 && $ust<300)){
                $this->errmsg = 'slice http='.$ust; $ok=false; break;
            }
            $sliceNo++;
            $total += $len;
        }
        fclose($fh);
        if(!$ok){ return false; }
        // 3) complete (poll up to 5 times)
        $compUrl = $this->config['base'].'/upload/v2/file/upload_complete';
        $tries=0; $fileID='';
        do{
            list($cst,, $cbody) = $this->curl('POST', $compUrl, [
                CURLOPT_POSTFIELDS => json_encode(['preuploadID'=>$preuploadID]),
                'headers' => array_merge([], ['Content-Type: application/json'])
            ]);
            if(!($cst>=200 && $cst<300)){
                $this->errmsg = 'complete http='.$cst; return false;
            }
            $cret = json_decode($cbody, true);
            if(isset($cret['data'])) $cret = $cret['data'];
            $completed = $cret['completed'] ?? false;
            $fileID = $cret['fileID'] ?? '';
            if($completed && $fileID){ break; }
            $tries++;
            sleep(1);
        }while($tries<5);
        if(!$fileID){ $this->errmsg = 'complete no fileID'; return false; }
        if(session_status()===PHP_SESSION_NONE) @session_start();
        $_SESSION['openapi123'][$name] = $fileID;
        return true;
    }

    private function sanitizeName($s){
        // 123 限制：不可包含 "\/:*?|>< 且不全为空格
        $s = preg_replace('/["\\\\\/:\*\?\|><]/', '', $s);
        $s = trim($s);
        if($s==='') $s = 'file';
        if(strlen($s) > 255) $s = substr($s, 0, 255);
        return $s;
    }
    private function pickFileName($fallback){
        if($this->config['keep_name']){
            if(session_status()===PHP_SESSION_NONE){/*no session*/}
            if(isset($_SESSION['upload']['name']) && $_SESSION['upload']['name']){
                return $this->sanitizeName($_SESSION['upload']['name']);
            }
        }
        return $this->sanitizeName($fallback);
    }
    private function chooseUploadServer($servers){
        // domain_strategy: 0=prefer create.servers then fallback to /upload/v2/file/domain; 1=domain first then fallback
        if($this->config['domain_strategy']===1){
            $d = $this->fetchDomain();
            if(!empty($d)) return $d[0];
            if(!empty($servers)) return $servers[0];
        }else{
            if(!empty($servers)) return $servers[0];
            $d = $this->fetchDomain();
            if(!empty($d)) return $d[0];
        }
        $this->errmsg = 'no upload server available';
        return '';
    }
    private function fetchDomain(){
        $url = $this->config['base'].'/upload/v2/file/domain';
        list($st,, $body) = $this->curl('GET', $url);
        if($st>=200 && $st<300){
            $ret = json_decode($body, true);
            if(isset($ret['data']) && is_array($ret['data'])) return $ret['data'];
        }
        return [];
    }

    public function savefile($name, $tmpfile, $content_type = null){ return $this->upload($name, $tmpfile, $content_type); }

    public function getinfo($name){ return false; }

    public function delete($name){
        // 逻辑删除：移入回收站（不做彻底删除）
        global $DB;
        $cid = $DB->getColumn("SELECT cloud_id FROM pre_file WHERE hash=:h LIMIT 1", [':h'=>$name]);
        if(empty($cid)){ $this->errmsg='no cloud_id'; return false; }
        $trash = $this->config['base'].'/api/v1/file/trash';
        list($st,, $body) = $this->curl('POST', $trash, [
            CURLOPT_POSTFIELDS => json_encode(['fileIDs'=>[(int)$cid]]),
            'headers' => ['Content-Type: application/json']
        ]);
        if($st>=200 && $st<300){ return true; }
        $this->errmsg='trash http='.$st; return false;
    }

    public function getUploadParam($name, $filename, $max_file_size = 0){
        // 未提供直传参数，回退站点中转
        return false;
    }

    public function getDownUrl($name, $filename, $content_type = null){
        $this->logDebug("=== getDownUrl 开始 ===", [
            'fileId' => $name,
            'filename' => $filename,
            'use_client_api' => $this->config['use_client_api'] ? 'YES' : 'NO',
            's3keyflag_configured' => !empty($this->config['s3keyflag']) ? 'YES' : 'NO'
        ]);

        // 根据配置决定是否使用客户端API
        if($this->config['use_client_api']){
            $this->logDebug("使用客户端API获取下载链接");
            // 优先尝试客户端API（无地域限制）
            $url = $this->fetchDownloadUrlClientAPI($name);
            if($url){
                $this->logDebug("客户端API成功返回下载链接", ['url' => $url]);
                return $url;
            }else{
                $this->logDebug("客户端API失败，错误信息", ['error' => $this->errmsg]);
            }
        }else{
            $this->logDebug("配置未启用客户端API，使用Web API");
        }

        // 降级到Web API（可能有地域限制）
        $this->logDebug("使用Web API获取下载链接");
        $url = $this->fetchDownloadUrl($name, /*forwardUserIp*/ true);
        if($url){
            $this->logDebug("Web API成功返回下载链接", ['url' => $url]);
        }else{
            $this->logDebug("Web API失败，错误信息", ['error' => $this->errmsg]);
        }
        return $url ?: false;
    }

    private function fetchDownloadUrlClientAPI($fileId){
        $this->logDebug(">>> fetchDownloadUrlClientAPI 开始", ['fileId' => $fileId]);

        // 检查熔断器状态
        if($this->isCircuitOpen('client_api')){
            $this->logDebug("熔断器开启，跳过客户端API");
            return false;
        }

        // 检查请求频率限制
        if(!$this->checkRateLimit('client_api')){
            $this->logDebug("频率限制阻止，跳过客户端API");
            return false;
        }

        // 随机延迟（模拟真人）
        $this->randomDelay();

        // 带重试机制执行
        return $this->apiCallWithRetry(function() use ($fileId){
            return $this->doFetchDownloadUrlClientAPI($fileId);
        }, 3, 'client_api');
    }

    /**
     * 实际执行客户端API调用（被重试包装器调用）
     */
    private function doFetchDownloadUrlClientAPI($fileId){
        $this->logDebug("开始执行客户端API调用", ['fileId' => $fileId]);

        // 获取token
        $token = $this->ensureAccessToken();
        if(!$token){
            $this->errmsg = '获取token失败';
            $this->logDebug("获取token失败");
            return false;
        }
        $this->logDebug("Token获取成功", ['token' => substr($token, 0, 20).'...']);

        // 获取用户真实IP
        $clientIp = isset($GLOBALS['clientip']) ? $GLOBALS['clientip'] : '';
        if(empty($clientIp)) $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->logDebug("客户端IP", ['ip' => $clientIp]);

        // 尝试从缓存获取文件元数据
        $cached = $this->getCachedMetadata($fileId);
        if($cached !== null){
            // 缓存命中，直接使用缓存数据
            $this->logDebug("使用缓存的元数据", $cached);
            $fileName = $cached['fileName'];
            $etag = $cached['etag'];
            $size = $cached['size'];
            $s3keyFlag = $cached['s3keyFlag'];

            // 如果配置了固定s3keyflag，覆盖缓存值
            if(!empty($this->config['s3keyflag'])){
                $s3keyFlag = $this->config['s3keyflag'];
                $this->logDebug("使用配置的固定s3keyflag覆盖缓存值", ['configured_s3keyflag' => $s3keyFlag]);
            }
        }else{
            // 缓存未命中，调用API获取
            $this->logDebug("步骤1: 开始获取文件信息（API调用）");

            // 随机延迟
            $this->randomDelay();

            $infoUrl = 'https://www.123pan.com/b/api/file/info';
            $infoPayload = json_encode([
                'fileIdList' => [['fileId' => intval($fileId)]]
            ]);

            $this->logDebug("步骤1: 请求文件信息", [
                'url' => $infoUrl,
                'payload' => $infoPayload
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $infoUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $infoPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $headers = [
                'Content-Type: application/json',
                'platform: android',
                'app-version: 62',
                'Authorization: Bearer '.$token
            ];
            if(!empty($clientIp)){
                $headers[] = 'X-Real-IP: '.$clientIp;
                $headers[] = 'X-Forwarded-For: '.$clientIp;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $resp = curl_exec($ch);
            if($resp === false){
                $this->errmsg = 'get file info curl error: '.curl_error($ch);
                curl_close($ch);
                return false;
            }

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($resp, $header_size);
            curl_close($ch);

            $this->logDebug("步骤1: 文件信息响应", [
                'status' => $status,
                'body' => $body
            ]);

            if(!($status>=200 && $status<300)){
                $this->errmsg = 'get file info failed http='.$status.' body='.$body;
                $this->logDebug("步骤1: 失败", ['error' => $this->errmsg]);
                return false;
            }

            $ret = json_decode($body, true);
            $infoList = $ret['data']['infoList'] ?? null;
            if(!$infoList || !is_array($infoList) || count($infoList) == 0){
                $this->errmsg = 'file info not found in response';
                $this->logDebug("步骤1: 解析失败", ['error' => $this->errmsg, 'response' => $ret]);
                return false;
            }
            $fileInfo = $infoList[0];

            // 提取文件信息
            $fileName = $fileInfo['FileName'] ?? $fileInfo['filename'] ?? '';
            $etag = $fileInfo['Etag'] ?? $fileInfo['etag'] ?? '';
            $size = isset($fileInfo['Size']) ? intval($fileInfo['Size']) : (isset($fileInfo['size']) ? intval($fileInfo['size']) : 0);
            $s3keyFlag = $fileInfo['S3KeyFlag'] ?? $fileInfo['s3KeyFlag'] ?? $fileInfo['s3keyFlag'] ?? '';

            // 如果配置了固定s3keyflag，使用配置的值覆盖
            if(!empty($this->config['s3keyflag'])){
                $s3keyFlag = $this->config['s3keyflag'];
                $this->logDebug("使用配置的固定s3keyflag覆盖API返回值", ['configured_s3keyflag' => $s3keyFlag]);
            }

            $this->logDebug("步骤1: 文件信息提取", [
                'fileName' => $fileName,
                'etag' => $etag,
                'size' => $size,
                's3keyFlag' => $s3keyFlag
            ]);

            if(empty($s3keyFlag)){
                $this->errmsg = 'missing s3keyFlag';
                $this->logDebug("步骤1: 缺少s3keyFlag", ['error' => $this->errmsg]);
                return false;
            }

            // 缓存文件元数据
            $this->setCachedMetadata($fileId, [
                'fileName' => $fileName,
                'etag' => $etag,
                's3keyFlag' => $s3keyFlag,
                'size' => $size
            ]);
        }

        // 随机延迟
        $this->randomDelay();

        // 使用文件信息获取下载链接
        $downloadUrl = 'https://www.123pan.com/api/file/download_info';
        $payload = json_encode([
            'driveId' => 0,
            'etag' => $etag,
            'fileId' => intval($fileId),
            'fileName' => $fileName,
            's3keyFlag' => $s3keyFlag,
            'size' => $size,
            'type' => 0
        ]);

        $headers = [
            'Content-Type: application/json',
            'platform: android',
            'app-version: 62',
            'Authorization: Bearer '.$token
        ];
        if(!empty($clientIp)){
            $headers[] = 'X-Real-IP: '.$clientIp;
            $headers[] = 'X-Forwarded-For: '.$clientIp;
        }

        $this->logDebug("步骤2: 请求下载链接", [
            'url' => $downloadUrl,
            'payload' => $payload,
            'headers' => $headers
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);
        if($resp === false){
            $this->errmsg = 'client api curl error: '.curl_error($ch);
            curl_close($ch);
            return false;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($resp, $header_size);
        curl_close($ch);

        $this->logDebug("步骤2: 下载链接响应", [
            'status' => $status,
            'body' => $body
        ]);

        if($status>=200 && $status<300){
            $ret = json_decode($body, true);
            $down = $ret['data']['DownloadUrl'] ?? '';
            if($down){
                $this->logDebug("步骤2: 成功获取下载链接", ['original_url' => $down]);

                // 检查返回的链接是否包含跨域参数
                if(strpos($down, 'ndcp=') === false){
                    $separator = (strpos($down, '?') !== false) ? '&' : '?';
                    $down .= $separator . 'ndcp=1&cache_type=1&auto_redirect=0';
                    $this->logDebug("添加跨域参数", ['final_url' => $down]);
                }

                // 解析URL参数
                $parsedUrl = parse_url($down);
                if(isset($parsedUrl['query'])){
                    parse_str($parsedUrl['query'], $params);
                    $this->logDebug("下载链接参数分析", [
                        'u_ip' => $params['u_ip'] ?? 'NOT SET',
                        'ndcp' => $params['ndcp'] ?? 'NOT SET',
                        'all_params' => array_keys($params)
                    ]);
                }

                return $down;
            }else{
                $this->logDebug("步骤2: 响应中没有DownloadUrl字段");
            }
        }

        $this->errmsg = 'client api download_info failed http='.$status.' body='.$body;
        $this->logDebug("步骤2: 失败", ['error' => $this->errmsg]);
        return false;
    }

    private function fetchDownloadUrl($fileId, $forwardUserIp = true){
        if($this->lastName === $fileId && !empty($this->lastDownUrl)){
            return $this->lastDownUrl;
        }
        $token = $this->ensureAccessToken();
        if(!$token){ return false; }
        $url = $this->config['base'].'/api/v1/file/download_info?fileId='.rawurlencode($fileId);
        // 仅在直链返回时透传用户真实IP；中转下载时不透传，避免地域不一致
        $hdrs = [];
        if($forwardUserIp && isset($GLOBALS['clientip']) && $GLOBALS['clientip']){
            $ip = $GLOBALS['clientip'];
            $hdrs[] = 'X-Forwarded-For: '.$ip;
            $hdrs[] = 'X-Real-IP: '.$ip;
        }
        list($st,, $body) = $this->curl('GET', $url, ['headers'=>$hdrs]);
        if($st>=200 && $st<300){
            $ret = json_decode($body, true);
            if(isset($ret['data'])){
                $data = $ret['data'];
                $down = $data['downloadUrl'] ?? '';
                if($down){
                    $this->lastName = $fileId;
                    $this->lastDownUrl = $down;
                    return $down;
                }
            }
            // 返回非0 code
            if(isset($ret['code']) && $ret['code']!=0){
                $this->errmsg = json_encode($ret, JSON_UNESCAPED_UNICODE);
                return false;
            }
        }else{
            $this->errmsg = 'download_info http='.$st;
        }
        return false;
    }
}
