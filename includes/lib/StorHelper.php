<?php

namespace lib;

class StorHelper
{
    private static function getConfig($storage){
        global $conf;
        switch($storage){
            case 'local':
                return $conf['filepath'];
                break;
            case 'sae':
            case 'ace':
                return $conf['storagename'];
                break;
            case 'oss':
                return ['accessKeyId' => $conf['oss_ak'], 'accessKeySecret' => $conf['oss_sk'], 'endpoint' => $conf['oss_endpoint'], 'bucket' => $conf['oss_bucket']];
                break;
            case 'qcloud':
                return ['secretId' => $conf['qcloud_id'], 'secretKey' => $conf['qcloud_key'], 'region' => $conf['qcloud_region'], 'bucket' => $conf['qcloud_bucket']];
                break;
            case 'obs':
                return ['accessKey' => $conf['obs_ak'], 'secretKey' => $conf['obs_sk'], 'endpoint' => $conf['obs_endpoint'], 'bucket' => $conf['obs_bucket']];
            case 'upyun':
                return ['operatorName' => $conf['upyun_user'], 'operatorPwd' => $conf['upyun_pwd'], 'serviceName' => $conf['upyun_name']];
            case 'qiniu':
                return ['accessKey' => $conf['qiniu_ak'], 'secretKey' => $conf['qiniu_sk'], 'bucket' => $conf['qiniu_bucket'], 'domain' => $conf['qiniu_domain']];
                break;
            case 'openapi123':
                return [
                    'base' => $conf['openapi123_base'],
                    'token' => $conf['openapi123_token'],
                    'public' => $conf['openapi123_public'],
                    'client_id' => $conf['openapi123_client_id'],
                    'client_secret' => $conf['openapi123_client_secret'],
                    'parent' => isset($conf['openapi123_parent'])?$conf['openapi123_parent']:0,
                    'keep_name' => isset($conf['openapi123_keep_name'])?$conf['openapi123_keep_name']:0,
                    'duplicate' => isset($conf['openapi123_duplicate'])?$conf['openapi123_duplicate']:1,
                    'domain_strategy' => isset($conf['openapi123_domain_strategy'])?$conf['openapi123_domain_strategy']:0,
                    'username' => isset($conf['openapi123_username'])?$conf['openapi123_username']:'',
                    'password' => isset($conf['openapi123_password'])?$conf['openapi123_password']:'',
                    'use_client_api' => isset($conf['openapi123_use_client_api'])?$conf['openapi123_use_client_api']:1,
                    's3keyflag' => isset($conf['openapi123_s3keyflag'])?$conf['openapi123_s3keyflag']:'',
                    // 性能优化配置
                    'cache_metadata' => isset($conf['openapi123_cache_metadata'])?$conf['openapi123_cache_metadata']:1,
                    'cache_ttl' => isset($conf['openapi123_cache_ttl'])?$conf['openapi123_cache_ttl']:604800,
                    'rate_limit' => isset($conf['openapi123_rate_limit'])?$conf['openapi123_rate_limit']:1,
                    'rate_max' => isset($conf['openapi123_rate_max'])?$conf['openapi123_rate_max']:60,
                    'circuit_breaker' => isset($conf['openapi123_circuit_breaker'])?$conf['openapi123_circuit_breaker']:1,
                    'circuit_threshold' => isset($conf['openapi123_circuit_threshold'])?$conf['openapi123_circuit_threshold']:50,
                    'circuit_timeout' => isset($conf['openapi123_circuit_timeout'])?$conf['openapi123_circuit_timeout']:300,
                    'random_delay' => isset($conf['openapi123_random_delay'])?$conf['openapi123_random_delay']:1,
                ];
            case 'webdav':
                return ['base' => $conf['webdav_base'], 'user' => $conf['webdav_user'], 'pass' => $conf['webdav_pass']];
            default:
                break;
        }
    }

    public static function getModel($storage)
    {
        $class = "\\lib\\Storage\\".ucwords($storage);
        $config = self::getConfig($storage);
        if(class_exists($class)){
            $model = new $class($config);
            return $model;
        }
        return false;
    }

    //判断是否可以直接链接
    public static function is_cloud(){
        global $conf;
        $is_cloud = false;
        if(in_array($conf['storage'], ['oss','qcloud','obs','upyun','qiniu','webdav','openapi123'])) $is_cloud = true;
        return $is_cloud;
    }

    //判断是否可以断点续传
    public static function is_range(){
        global $conf;
        $is_range = false;
        if(in_array($conf['storage'], ['local','oss','qcloud','obs','qiniu','webdav','openapi123'])) $is_range = true;
        return $is_range;
    }
}
