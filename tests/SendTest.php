<?php
class SendTest
    public function __construct()
    {
        parent::__construct();
        $this->load->helper("slack");

        // 禁止非 Command Line 模式使用
        if ( ! $this->input->is_cli_request()) {
            show_404();
        }
    }

    /**
     * 送 slack 訊息
     * @param  string $channel slack 頻道
     * @param  string $msg     訊息 (不帶第二個參數的話走導管模式)
     */
    public function index($channel = "#test", $msg = "")
    {
        if ($msg) {
            // 帶參數模式
            sendSlackMessage($msg, $channel);
        } else {
            // 導管模式
            $msg = $this->getStdinMessage();
            if ($msg) {
                sendSlackMessage($msg, $channel);
            } else {
                echo "no message to send.";
            }
        }
    }

    /**
     * gulp 通知
     */
    public function gulpNotify()
    {
        // 根目錄
        $root_path = $this->pathClear(__DIR__ . "/../../Users");

        // views_release
        $view_files = $this->getDirFiles(__DIR__  . "/../../views_release");
        $view_output = "";
        $view_count = count($view_files);
        foreach ($view_files as $i => $file) {
            $path = str_replace($root_path, '', $file['file']);
            $sn = $i + 1;
            $view_output .= "*{$sn}*. `{$file['mtime']}`: {$path}\n";
        }

        // js_release
        $js_files = $this->getDirFiles(__DIR__  . "/../../../public/js_release");
        $js_output = "";
        $js_count = count($js_files);
        foreach ($js_files as $i => $file) {
            $path = str_replace($root_path, '', $file['file']);
            $sn = $i + 1;
            $js_output .= "*{$sn}*. `{$file['mtime']}`: {$path}\n";
        }

        // css_release
        $css_files = $this->getDirFiles(__DIR__  . "/../../../public/css_release");
        $css_output = "";
        $css_count = count($css_files);
        foreach ($css_files as $i => $file) {
            $path = str_replace($root_path, '', $file['file']);
            $sn = $i + 1;
            $css_output .= "*{$sn}*. `{$file['mtime']}`: {$path}\n";
        }

        // 發 slack 訊息
        $hostname = gethostname();
        $title = "*[{$hostname}]* - *Gulp 更新* - " . date("Y-m-d");
        $description = "*Views* 檔案共 `{$view_count}` 個, *JavaScript* 檔案共 `{$js_count}` 個。, *CSS* 檔案共 `{$css_count}` 個。";
        $data = [
            "username"   => "Irs-Notify",
            "icon_emoji" => ":zuvio:",
            "text"       => $title,
            "attachments" => [
                [
                    "color"     => "#20bbfb",
                    "text"      => $description,
                    "mrkdwn_in" => ["text", "pretext"],
                ],
            ]
        ];
        $channel = $this->getReleaseNotifyChannel();
        sendSlackMessage($data, $channel);

        // 記入 log
        log_message('error', "{$title}, msg = {$description}");
    }

    /**
     * 上線通知 (Slack + Log)
     * @param  string $branch         目前的 branch
     * @param  string $current_hash   更新前的 commit hash
     * @param  string $update_hash    更新後的 commit hash
     */
    public function onlineNotify($branch = "", $current_hash = "", $update_hash = "")
    {
        if ( ! $branch || ! $current_hash || ! $update_hash) {
            echo "沒有傳遞 current_branch, current_hash 及 update_hash 參數！";
            exit;
        }

        // 切換頻道
        $channel = $this->getReleaseNotifyChannel();

        // 清除線上 php cache
        $this->load->helper("php_cache");
        $clear_php_cache = clearPHPCache();

        // 取得根目錄
        $base_dir = $this->pathClear(__DIR__ . "/../../Users");

        $hostname = gethostname();
        $status = exec("git log {$current_hash}..{$update_hash} --pretty=format:\"\`%h\` - %s - %an\"", $logs);
        $title = "*[{$hostname}]* - *程式更新* - " . date("Y-m-d");
        $log_count = count($logs);
        $sub_title  = "Git Branch `{$branch}`, 版本由 `{$current_hash}` 更新至 `{$update_hash}`";
        $sub_title .= ", 程式路徑: `{$base_dir}`";
        $sub_title .= ", 共 *{$log_count}* 個 Commit";
        if ($clear_php_cache) {
            $sub_title .= "\n線上 PHP Cache ({$clear_php_cache}) 已清除。";
        }

        echo "{$title}\n, {$sub_title}\n, 此次更新的 Commit => " . print_r($logs, TRUE);

        // 沒有 commit log 的話
        if ($current_hash == $update_hash || ! $logs) {
            sendSlackMessage([
                "username"   => "Irs-Notify",
                "icon_emoji" => ":zuvio:",
                "text"       => $title,
                "attachments" => [
                    [
                        "color"     => "#17b003",
                        "pretext"   => $sub_title,
                        "text"      => "*查無 Git 更新記錄！*",
                        "mrkdwn_in" => ["text", "pretext"],
                    ],
                ]
            ], $channel);

            // 記入 log
            log_message('error', "{$title}, msg = " . json_encode([
                'sub_title' => $sub_title,
                'logs'      => "*查無 Git 更新記錄！",
            ], JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE));
            exit;
        }

        // 發 slack 訊息
        $data = [
            "username"   => "Irs-Notify",
            "icon_emoji" => ":zuvio:",
            "text"       => $title,
            "attachments" => [
                [
                    "color"     => "#17b003",
                    "pretext"   => $sub_title,
                    "text"      => implode("\n", $logs),
                    "mrkdwn_in" => ["text", "pretext"],
                ],
            ]
        ];
        sendSlackMessage($data, $channel);

        // 記入 log
        log_message('error', "{$title}, msg = " . json_encode([
            'sub_title' => $sub_title,
            'logs'      => $logs,
        ], JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE));
    }

    /**
     * 取得目錄下的所有檔案(含子目錄)
     * @param  string $path 資料夾路徑
     * @return array
     */
    public function getDirFiles($path)
    {
        if ( ! is_dir($path)) {
            ErrorStack::push("{$path} not exists!");
            return [];
        }

        $paths = [$path];
        $files = [];
        while (count($paths)) {
            $path = array_pop($paths);
            $dir = opendir($path);
            while ($f = readdir($dir)) {
                if ($f == '.' || $f == '..') {
                    continue;
                }

                $item = "$path/$f";
                if (is_dir($item)) {
                    $paths[] = $item;
                } else {
                    $mtime = date("m-d H:i", filemtime($item));
                    $files[] = [
                        'file' => $this->pathClear($item),
                        'mtime' => $mtime,
                    ];
                }
            }
        }
        return $files;
    }

    /**
     * 路徑的正規化
     * @param  string $path 路徑
     * @return string
     */
    private function pathClear($path)
    {
        $tmp = explode("/", $path);
        $result_tmp = [];
        foreach ($tmp as $dir_name) {
            if ($dir_name == '..') {
                array_pop($result_tmp);
            } else {
                $result_tmp[] = $dir_name;
            }
        }
        return implode("/", $result_tmp);
    }

    /**
     * 取得導管的訊息!!!
     * @return string
     */
    private function getStdinMessage()
    {
        $handle = fopen('php://stdin', 'r');
        $buffer = "";
        while(!feof($handle)) {
            $buffer .= fgets($handle);
        }
        fclose($handle);
        return $buffer;
    }

    /**
     * 取得通知的頻道
     * @return string
     */
    private function getReleaseNotifyChannel()
    {
        if ($this->config->item("slack_release_notify_channel")) {
            return $this->config->item("slack_release_notify_channel");
        } else {
            return '#test';
        }
    }
}

/* End of file tools/slack_sender.php */
/* Location: ./application/controllers/tools/slack_sender.php */
