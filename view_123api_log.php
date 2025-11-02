<?php
/**
 * 123云盘客户端API日志查看器
 */
include("./includes/common.php");

if(!$islogin2) exit('请先登录管理后台');

$logFile = ROOT.'data/123api_debug.log';

// 处理清空日志
if(isset($_GET['clear'])){
    @unlink($logFile);
    header('Location: view_123api_log.php');
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>123云盘API调试日志</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .header {
            background: #2d2d30;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h2 {
            margin: 0;
            color: #4ec9b0;
        }
        .buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            font-size: 14px;
        }
        .btn-refresh {
            background: #0e639c;
        }
        .btn-clear {
            background: #d16969;
        }
        .btn-test {
            background: #4ec9b0;
        }
        .log-container {
            background: #252526;
            padding: 20px;
            border-radius: 5px;
            max-height: 80vh;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .timestamp {
            color: #608b4e;
            font-weight: bold;
        }
        .log-entry {
            margin-bottom: 10px;
            padding: 10px;
            background: #1e1e1e;
            border-left: 3px solid #4ec9b0;
            border-radius: 3px;
        }
        .error {
            border-left-color: #d16969;
        }
        .success {
            border-left-color: #4ec9b0;
        }
        .info {
            border-left-color: #0e639c;
        }
        .separator {
            color: #3e3e42;
            margin: 5px 0;
        }
        .empty {
            text-align: center;
            padding: 50px;
            color: #858585;
        }
        code {
            background: #1e1e1e;
            padding: 2px 5px;
            border-radius: 3px;
            color: #ce9178;
        }
        .json {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 3px;
            margin: 5px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>📊 123云盘API调试日志</h2>
        <div class="buttons">
            <a href="test_123api.php" class="btn btn-test">测试API</a>
            <a href="?refresh=1" class="btn btn-refresh">刷新</a>
            <a href="?clear=1" class="btn btn-clear" onclick="return confirm('确定要清空日志吗？')">清空日志</a>
        </div>
    </div>

    <div class="log-container">
        <?php
        if(!file_exists($logFile)){
            echo '<div class="empty">暂无日志记录<br><br>访问文件下载页面会自动生成日志</div>';
        }else{
            $logContent = file_get_contents($logFile);
            if(empty(trim($logContent))){
                echo '<div class="empty">日志文件为空</div>';
            }else{
                // 格式化显示
                $lines = explode("\n", $logContent);
                $currentEntry = '';
                $entryType = 'info';

                foreach($lines as $line){
                    if(preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)){
                        // 新的日志条目开始
                        if(!empty($currentEntry)){
                            echo '<div class="log-entry '.$entryType.'">'.$currentEntry.'</div>';
                        }
                        $currentEntry = '<span class="timestamp">['.$matches[1].']</span> '.htmlspecialchars(substr($line, strlen($matches[0])));

                        // 判断类型
                        if(strpos($line, '失败') !== false || strpos($line, 'error') !== false || strpos($line, 'Error') !== false){
                            $entryType = 'error';
                        }elseif(strpos($line, '成功') !== false || strpos($line, 'DownloadUrl') !== false){
                            $entryType = 'success';
                        }else{
                            $entryType = 'info';
                        }
                    }elseif(strpos($line, '--------') !== false){
                        // 分隔符
                        if(!empty($currentEntry)){
                            echo '<div class="log-entry '.$entryType.'">'.$currentEntry.'</div>';
                            $currentEntry = '';
                        }
                    }else{
                        // 继续当前条目
                        if(!empty($line)){
                            $currentEntry .= "\n".htmlspecialchars($line);
                        }
                    }
                }

                // 输出最后一个条目
                if(!empty($currentEntry)){
                    echo '<div class="log-entry '.$entryType.'">'.$currentEntry.'</div>';
                }
            }
        }
        ?>
    </div>

    <script>
        // 自动滚动到底部
        window.addEventListener('load', function(){
            var container = document.querySelector('.log-container');
            container.scrollTop = container.scrollHeight;
        });
    </script>
</body>
</html>
