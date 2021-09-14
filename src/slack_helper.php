<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 傳送訊息
 * @param  mixed $msg      訊息
 * @param  string $channel  頻道
 * @return boolean
 */
function sendSlackMessage($msg, $channel = '#test')
{
    static $slack;

    // 測試不要推 slack
    if (defined("PHPUNIT_TEST")) {
        return false;
    }

    if ( ! $slack) {
        $slack = new Slack();
    }
    return $slack->sendMessage($msg, $channel);
}

/**
 * Slack Message 傳送
 */
class Slack
{
    /**
     * slack api 位置
     * @var string
     */
    private $api_url = "https://hooks.slack.com/services/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";

    /**
     * 傳送訊息
     * @param  mixed $msg       訊息 (如果是陣列的話表示直接轉成 JSON)
     * @param  string $channel  頻道
     * @return boolean
     */
    public function sendMessage($msg, $channel = '#test')
    {
        if (is_array($msg)) {
            $data = $msg;
        } else if (strpos($msg, ":::ATTACH:::")) {
            $data = $this->parseMessage($msg);
        } else {
            $data = [
                "username"   => "Irs-Notify",
                "icon_emoji" => ":zuvio:",
                "text"       => $msg,
            ];
        }
        if ($channel) {
            $data['channel'] = $channel;
        }
        $response = $this->curlJson($this->api_url, json_encode($data));
        return ($response == 'ok') ? true : false;
    }

    /**
     * CURL 傳送
     * @param  string $url 網址
     * @return string      回應內容
     */
    public function curlJson($url, $json)
    {
        $timeout = 10;
        $curl = curl_init($url);
        if (substr($url, 0, 5) == "https") {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        // 設定內容
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // 設定 header
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);

        $data = curl_exec($curl);

        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($response_code != "200") {
            // echo "false";
            return $data;
        }
        return $data;
    }

    private function parseMessage($msg)
    {
        $tmp = explode(":::ATTACH:::", $msg);
        $title = $tmp[0];
        $description = $tmp[1];

        if (strpos($description, ":::")) {
            $line_tmp = explode(":::NEW_LINE:::", $description);
            $fields = [];
            foreach ($line_tmp as $item) {
                $field_tmp = explode(":::", $item);
                $fields[] = [
                    "title" => $field_tmp[0],
                    "value" => "`{$field_tmp[1]}`",
                    "short" => true,
                ];
            }

            $data = [
                "username"   => "Irs-Notify",
                "icon_emoji" => ":zuvio:",
                "text"       => $title,
                "attachments" => [
                    [
                        "color"     => "#17b003",
                        "fields"    => $fields,
                        "mrkdwn_in" => ["text", "pretext", "fields"],
                    ],
                ]
            ];
        } else {
            $data = [
                "username"   => "Irs-Notify",
                "icon_emoji" => ":zuvio:",
                "text"       => $title,
                "attachments" => [
                    [
                        "color"     => "#17b003",
                        "pretext"   => $description,
                        "mrkdwn_in" => ["text", "pretext", "fields"],
                    ],
                ]
            ];
        }

        return $data;
    }
}
