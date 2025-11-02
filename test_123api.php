<?php
/**
 * 123云盘客户端API测试脚本
 * 用于测试账号密码登录和客户端API是否正常
 */
include("./includes/common.php");

if(!$islogin2) exit('请先登录管理后台');

echo "<h2>123云盘客户端API测试</h2>";

// 获取配置
$username = $conf['openapi123_username'] ?? '';
$password = $conf['openapi123_password'] ?? '';
$use_client_api = $conf['openapi123_use_client_api'] ?? 0;

echo "<h3>当前配置</h3>";
echo "账号: " . ($username ? htmlspecialchars($username) : '<span style="color:red">未配置</span>') . "<br>";
echo "密码: " . ($password ? '******' : '<span style="color:red">未配置</span>') . "<br>";
echo "优先使用客户端API: " . ($use_client_api ? '<span style="color:green">已启用</span>' : '<span style="color:red">未启用</span>') . "<br>";

if(empty($username) || empty($password)){
    exit('<p style="color:red">请先在后台配置123云盘账号和密码</p>');
}

echo "<hr><h3>测试账号密码登录</h3>";

// 测试登录
$loginUrl = 'https://www.123pan.com/api/user/sign_in';
$payload = json_encode([
    'passport' => $username,
    'password' => $password,
    'remember' => true
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
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($resp, $header_size);
curl_close($ch);

echo "HTTP状态码: " . $status . "<br>";
echo "响应内容: <pre>" . htmlspecialchars($body) . "</pre>";

if($status >= 200 && $status < 300){
    $ret = json_decode($body, true);
    $token = $ret['data']['token'] ?? '';
    if($token){
        echo "<p style='color:green'>✓ 登录成功！Token: " . substr($token, 0, 20) . "...</p>";

        // 测试使用token获取下载链接
        echo "<hr><h3>测试客户端API获取下载链接</h3>";
        echo "<p>请输入要测试的文件ID：</p>";
        echo "<form method='GET'>";
        echo "<input type='text' name='file_id' placeholder='例如：12345' value='" . ($_GET['file_id'] ?? '') . "'>";
        echo "<button type='submit'>测试</button>";
        echo "</form>";

        if(isset($_GET['file_id']) && !empty($_GET['file_id'])){
            $fileId = intval($_GET['file_id']);
            echo "<p>测试文件ID: $fileId</p>";

            // 获取客户端IP
            $clientIp = $clientip ?? $_SERVER['REMOTE_ADDR'] ?? '';
            echo "<p>客户端IP: $clientIp</p>";

            // 第一步：获取文件信息
            echo "<hr><h4>步骤1: 获取文件信息</h4>";
            $infoUrl = 'https://www.123pan.com/api/file/info';
            $infoPayload = json_encode([
                'driveId' => 0,
                'fileId' => $fileId
            ]);

            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $infoUrl);
            curl_setopt($ch1, CURLOPT_POST, true);
            curl_setopt($ch1, CURLOPT_POSTFIELDS, $infoPayload);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_HEADER, true);
            curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, false);
            $headers1 = [
                'Content-Type: application/json',
                'platform: android',
                'app-version: 62',
                'Authorization: Bearer '.$token
            ];
            if(!empty($clientIp)){
                $headers1[] = 'X-Real-IP: '.$clientIp;
                $headers1[] = 'X-Forwarded-For: '.$clientIp;
            }
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers1);

            $resp1 = curl_exec($ch1);
            $status1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            $header_size1 = curl_getinfo($ch1, CURLINFO_HEADER_SIZE);
            $body1 = substr($resp1, $header_size1);
            curl_close($ch1);

            echo "HTTP状态码: " . $status1 . "<br>";
            echo "响应内容: <pre>" . htmlspecialchars($body1) . "</pre>";

            if($status1 >= 200 && $status1 < 300){
                $ret1 = json_decode($body1, true);
                $fileInfo = $ret1['data'] ?? null;
                if($fileInfo){
                    echo "<p style='color:green'>✓ 成功获取文件信息</p>";
                    $fileName = $fileInfo['FileName'] ?? $fileInfo['filename'] ?? '';
                    $etag = $fileInfo['Etag'] ?? $fileInfo['etag'] ?? '';
                    $size = isset($fileInfo['Size']) ? intval($fileInfo['Size']) : (isset($fileInfo['size']) ? intval($fileInfo['size']) : 0);
                    $s3keyFlag = $fileInfo['S3KeyFlag'] ?? $fileInfo['s3KeyFlag'] ?? $fileInfo['s3keyFlag'] ?? '';

                    echo "<ul>";
                    echo "<li>文件名: " . htmlspecialchars($fileName) . "</li>";
                    echo "<li>Etag: " . htmlspecialchars($etag) . "</li>";
                    echo "<li>大小: " . $size . " bytes</li>";
                    echo "<li>S3KeyFlag: " . htmlspecialchars($s3keyFlag) . "</li>";
                    echo "</ul>";

                    if(empty($s3keyFlag)){
                        echo "<p style='color:red'>✗ 缺少s3keyFlag，无法继续</p>";
                        exit;
                    }

                    // 第二步：获取下载链接
                    echo "<hr><h4>步骤2: 获取下载链接</h4>";
                    $downloadUrl = 'https://www.123pan.com/api/file/download_info';
                    $payload = json_encode([
                        'driveId' => 0,
                        'etag' => $etag,
                        'fileId' => $fileId,
                        'fileName' => $fileName,
                        's3keyFlag' => $s3keyFlag,
                        'size' => $size,
                        'type' => 0
                    ]);
                }else{
                    echo "<p style='color:red'>✗ 文件信息获取失败</p>";
                    exit;
                }
            }else{
                echo "<p style='color:red'>✗ 获取文件信息失败</p>";
                exit;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
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
            $status2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size2 = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body2 = substr($resp, $header_size2);
            curl_close($ch);

            echo "HTTP状态码: " . $status2 . "<br>";
            echo "响应内容: <pre>" . htmlspecialchars($body2) . "</pre>";

            if($status2 >= 200 && $status2 < 300){
                $ret2 = json_decode($body2, true);
                $down = $ret2['data']['DownloadUrl'] ?? '';
                if($down){
                    echo "<p style='color:green'>✓ 成功获取下载链接！</p>";

                    // 分析链接参数
                    echo "<h4>链接分析：</h4>";
                    $parsedUrl = parse_url($down);
                    echo "域名: " . ($parsedUrl['host'] ?? '') . "<br>";
                    if(isset($parsedUrl['query'])){
                        parse_str($parsedUrl['query'], $params);
                        echo "参数：<ul>";
                        foreach($params as $k => $v){
                            echo "<li><b>$k</b> = " . htmlspecialchars($v) . "</li>";
                        }
                        echo "</ul>";

                        // 检查关键参数
                        if(isset($params['u_ip'])){
                            echo "<p style='color:green'>✓ 包含u_ip参数: {$params['u_ip']}</p>";
                        }else{
                            echo "<p style='color:orange'>⚠ 未包含u_ip参数</p>";
                        }

                        if(isset($params['ndcp'])){
                            echo "<p style='color:green'>✓ 包含ndcp参数（无域检查策略）</p>";
                        }else{
                            echo "<p style='color:orange'>⚠ 未包含ndcp参数，正在添加...</p>";
                            $separator = '&';
                            $down .= $separator . 'ndcp=1&cache_type=1&auto_redirect=0';
                            echo "<p>修改后链接: <a href='$down' target='_blank'>$down</a></p>";
                        }
                    }

                    echo "<p>原始链接: <a href='$down' target='_blank'>点击测试下载</a></p>";
                }else{
                    echo "<p style='color:red'>✗ 未返回下载链接</p>";
                }
            }else{
                echo "<p style='color:red'>✗ 获取下载链接失败</p>";
            }
        }

    }else{
        echo "<p style='color:red'>✗ 登录失败：未返回token</p>";
    }
}else{
    echo "<p style='color:red'>✗ 登录失败</p>";
}
?>
