<?php
ini_set('memory_limit', '1024M');
header("Content-Type:application/json;charset=utf-8");
date_default_timezone_set('Asia/Shanghai');
header("Access-Control-Allow-Origin: *");
require 'vendor/autoload.php';
use Fukuball\Jieba\Jieba;
Jieba::init();
function sendPostLw($requestUrl) {
    $curlHandler = curl_init();
    curl_setopt($curlHandler, CURLOPT_URL, $requestUrl);
    curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
    $headerArray = array();
    $headerArray[] = "Host: sg.search.yahoo.com";
    $headerArray[] = "Connection: keep-alive";
    $headerArray[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:107.0) Gecko/20100101 Firefox/107.0";
    $headerArray[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8";
    $headerArray[] = "Accept-Language: zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2";
    curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $headerArray);
    $requestResult = curl_exec($curlHandler);
    
    if (curl_errno($curlHandler)) {
        $errorContent = array(
            'code' => "500",
            'msg' => "访问出错"
        );
        print_r(json_encode($errorContent, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        exit();
    }
    curl_close($curlHandler);
    return $requestResult;
}
function calculate_cost($num_tokens){
    $cost_per_token = 0.000003;
    $total_cost = $num_tokens * $cost_per_token;
    return $total_cost;
}
function calculate_costs($num_tokens){
    $cost_per_token = 0.000004;
    $total_cost = $num_tokens * $cost_per_token;
    return $total_cost;
}
function sendPostJson($jsonStr,$key)
{
    $timeout = 120;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, $timeout);
    $headers = array();
    $headers[] = "Host: api.openai.com";
    $headers[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:107.0) Gecko/20100101 Firefox/107.0";
    $headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8";
    $headers[] = "Accept-Language: zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2";
    $headers[] = "Content-Type: application/json;charset=utf-8";
    $headers[] = "Authorization: Bearer ".$key;
    curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $content=array(
            'code' => "500",
            'msg' => "访问出错");
        print_r(json_encode($content,JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        exit();
    }
    curl_close($ch);
    return $result;
}
function getCurrentDate(){
    $currentTimestamp = time();
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
    $date->setTimestamp($currentTimestamp);
    $formattedTime = $date->format('Y-m-d\TH:i:sP');
    return $formattedTime;
}
@$instructionMessage = $_REQUEST['message'];
@$key = $_REQUEST['key'];
if(empty($instructionMessage)){
    $content=array(
        'code' => "203",
        'msg' => "请输入待回答的问题");
    print_r(json_encode($content,JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
else{
    $seg_list = Jieba::cut($instructionMessage);
    $seg_list_result = implode(" ",$seg_list);
    $message_encode = urlencode($seg_list_result);
    $SendUrl = "https://sg.search.yahoo.com/search?p=$message_encode&ei=UTF-8";
    $requestData = sendPostLw($SendUrl);
    preg_match_all("/aria-label=\"(.*?)\"/",$requestData,$titleMatches);
    preg_match_all("/<span class=\" fc-falcon\">(.*?)<\/span>/",$requestData,$contentMatches);
    preg_match_all("/class=\"d-ib p-abs t-0 l-0 fz-14 lh-20 fc-obsidian wr-bw ls-n pb-4\"><span>(.*?)<\/span><span>/",$requestData,$urlMatches);
    $searchUrls = $urlMatches[1];
    $searchTitles = $titleMatches[1];
    $searchContents = $contentMatches[1];
    $aggregateArray = array();
    for($i=0; $i<3; $i++){
        $index = $i+1;
        $singleContent = $searchContents[$i];
        $singleTitle = $searchTitles[$i];
        $singleUrl = $searchUrls[$i];
        $singleContentCleaned = strip_tags($singleContent);
        $singleString = "NUMBER:$index\nURL:$singleUrl\nTITLE:$singleTitle\nCONTENT:$singleContent";
        array_push($aggregateArray, $singleString);
    }
    $compiledContents = implode("\n\n", $aggregateArray);
    $currentDate = getCurrentDate();
    $replyInstruction = "I will give you a question or an instruction. Your objective is to answer my question or fulfill my instruction.\n\nMy question or instruction is: $instructionMessage\n\nFor your reference, today's date is $currentDate.\n\nIt's possible that the question or instruction, or just a portion of it, requires relevant information from the internet to give a satisfactory answer or complete the task. Therefore, provided below is the necessary information obtained from the internet, which sets the context for addressing the question or fulfilling the instruction. You will write a comprehensive reply to the given question or instruction. If the provided information from the internet results refers to multiple subjects with the same name, write separate answers for each subject:\n\"\"\"\n$compiledContents\n\"\"\"\nReply in 中文";
    if (array_key_exists('key', $_GET)) {
        $keys = $key;
    }
    else{
        $keys = "这里输入你的Key";
    }
    $postdata = array(
        'model' => "gpt-3.5-turbo-16k-0613",
        'messages' => array(
            array(
            'role' => "user",
            'content' => "$replyInstruction"
            )
        ),
        'temperature' => 0.7
    );
    $jsonStr = json_encode($postdata);
    $data = sendPostJson($jsonStr,$keys);
    $jsondata = json_decode($data,true);
    $choices = $jsondata['choices'][0]['message']['content'];
    if(empty($choices)){
        $content = array(
            'code' => "201",
            'msg' => "获取问答失败");
        print_r(json_encode($content,JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    else{
        $models = $jsondata['model'];
        $tokens = $jsondata['usage']['prompt_tokens'];
        $tokensco = $jsondata['usage']['completion_tokens'];
        $money = calculate_cost($tokens);
        $moneyw = calculate_costs($tokensco);
        $result_money = $money + $moneyw;
        $mm_money = sprintf("%.6f", $result_money);
        $content = array(
            'code' => "200",
            'msg' => "获取成功",
            'model' => "$models",
            'total_money' => $mm_money,
            'message' => "$instructionMessage",
            'answer' => "$choices"
        );
        print_r(json_encode($content,JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        exit();
    }
}
?>
