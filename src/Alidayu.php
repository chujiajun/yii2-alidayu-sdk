<?php
/**
 * 阿里大鱼SDK
 * User: chocoboxxf
 * Date: 16/4/22
 * Time: 上午11:54
 */
namespace chocoboxxf\Alidayu;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use yii\base\Component;
use yii\base\InvalidConfigException;

class Alidayu extends Component
{
    /**
     * API环境
     */
    const ENV_PRODUCTION = 'production'; // 生产环境
    const ENV_SANDBOX = 'sandbox'; // 测试环境
    const ENV_OVERSEA = 'oversea'; // 海外环境

    /**
     * 使用签名算法
     */
    const SIGN_METHOD_HMAC = 'hmac'; // HMAC-MD5
    const SIGN_METHOD_MD5 = 'md5'; // MD5

    /**
     * 返回结果格式
     */
    const FORMAT_XML = 'xml'; // XML格式
    const FORMAT_JSON = 'json'; // JSON格式

    /**
     * API接口
     */
    const API_ALIBABA_ALIQIN_SECRET_AXB_BIND = 'alibaba.aliqin.secret.axb.bind'; // AXB一次绑定接口
    const API_ALIBABA_ALIQIN_SECRET_AXB_BIND_SECOND = 'alibaba.aliqin.secret.axb.bind.second'; // AXB二次绑定接口
    const API_ALIBABA_ALIQIN_SECRET_AXB_UNBIND = 'alibaba.aliqin.secret.axb.unbind'; // AXB关系解绑接口

    const API_ALIBABA_ALIQIN_FC_SMS_NUM_SEND = 'alibaba.aliqin.fc.sms.num.send'; // 短信发送
    const API_ALIBABA_ALIQIN_FC_SMS_NUM_QUERY = 'alibaba.aliqin.fc.sms.num.query'; // 短信发送记录查询
    const API_ALIBABA_ALIQIN_FC_TTS_NUM_SINGLECALL = 'alibaba.aliqin.fc.tts.num.singlecall'; // 文本转语音通知

    /**
     * API服务器地址
     * @var string
     */
    public $apiUrls = [
        'http' => [
            Alidayu::ENV_SANDBOX => 'http://gw.api.tbsandbox.com/router/rest',
            Alidayu::ENV_PRODUCTION => 'http://gw.api.taobao.com/router/rest',
            Alidayu::ENV_OVERSEA => 'http://api.taobao.com/router/rest',
        ],
        'https' => [
            Alidayu::ENV_SANDBOX => 'https://gw.api.tbsandbox.com/router/rest',
            Alidayu::ENV_PRODUCTION => 'https://eco.taobao.com/router/rest',
            Alidayu::ENV_OVERSEA => 'https://api.taobao.com/router/rest',
        ]
    ];

    /**
     * 淘宝开发平台App Key
     * @var string
     */
    public $appKey;
    /**
     * 淘宝开发平台App Secret
     * @var string
     */
    public $appSecret;
    /**
     * 阿里大鱼提供的第三方合作伙伴使用的KEY
     * @var string
     */
    public $partnerKey;
    /**
     * 返回结果格式
     * @var string
     */
    public $format = Alidayu::FORMAT_JSON;
    /**
     * API版本
     * @var string
     */
    public $appVersion = '2.0';
    /**
     * API是否使用SSL连接
     * @var bool
     */
    public $isSecure = false;
    /**
     * API环境
     * @var string
     */
    public $env = Alidayu::ENV_SANDBOX;
    /**
     * 使用签名算法
     * @var string
     */
    public $signMethod = Alidayu::SIGN_METHOD_MD5;
    /**
     * API Client
     * @var \GuzzleHttp\Client
     */
    public $apiClient;

    public function init()
    {
        parent::init();
        if (!isset($this->appKey)) {
            throw new InvalidConfigException('请先配置App Key');
        }
        if (!isset($this->appSecret)) {
            throw new InvalidConfigException('请先配置App Secret');
        }
        $this->apiClient = new Client([
            'base_uri' => $this->isSecure ? $this->apiUrls['https'][$this->env] :  $this->apiUrls['http'][$this->env],
        ]);
    }

    /**
     * AXB(AXN)一次绑定接口
     * 绑定接口用于实现真实号码和虚拟号码之间的绑定关系，只有在完成绑定以后用户才可以使用虚拟号码的基本通信能力。
     * @param string $phoneA 号码A
     * @param string $phoneB  号码B（AXB模式下必填，AXN模式下任意填写）
     * @param string $endDate 到期自动解绑时间（YYYY-mm-dd HH:MM:SS）
     * @param bool|false $otherCall 第三方是否可拨打X转接到号码A
     * @param bool|false $needRecord 是否需要录音
     * @return mixed
     * @throws GuzzleException
     */
    public function AxbBind($phoneA, $phoneB, $endDate, $otherCall = false, $needRecord = false)
    {
        // 公共参数
        $data = [
            'app_key' => $this->appKey,
            'timestamp' => $this->getTimestamp(),
            'format' => $this->format,
            'v' => $this->appVersion,
            'sign_method' => $this->signMethod,
            'method' => Alidayu::API_ALIBABA_ALIQIN_SECRET_AXB_BIND,
        ];

        // 入参
        $data['partner_key'] = $this->partnerKey;
        $data['phone_no_a'] = $phoneA;
        $data['end_date'] = $endDate;
        $data['enable_other_call'] = $otherCall ? 'true' : 'false';
        $data['need_record'] = $needRecord ? 'true' : 'false';
        $data['phone_no_b'] = $phoneB;

        // 签名
        $signature = $this->getSignature($data, $this->signMethod);
        $data['sign'] = $signature;
        // 请求
        return $this->post('', $data);
    }

    /**
     * AXN二次绑定接口
     * 二次绑定接口用户确认，AX与B之间的关系，在一次绑定接口调用的基础上，再通过调用二次绑定接口，最终形成A与B之间的关系。
     * @param string $subsId 绑定关系ID
     * @param string $phoneB 号码B
     * @param string $endDate 到期自动解绑时间（YYYY-mm-dd HH:MM:SS）
     * @param bool|false $otherCall 第三方是否可拨打X转接到号码A
     * @param bool|false $needRecord 是否需要录音
     * @return mixed
     * @throws GuzzleException
     */
    public function AxbBindSecond($subsId, $phoneB, $endDate, $otherCall = false, $needRecord = false)
    {
        // 公共参数
        $data = [
            'app_key' => $this->appKey,
            'timestamp' => $this->getTimestamp(),
            'format' => $this->format,
            'v' => $this->appVersion,
            'sign_method' => $this->signMethod,
            'method' => Alidayu::API_ALIBABA_ALIQIN_SECRET_AXB_BIND_SECOND,
        ];

        // 入参
        $data['partner_key'] = $this->partnerKey;
        $data['subs_id'] = $subsId;
        $data['phone_no_b'] = $phoneB;
        $data['end_date'] = $endDate;
        $data['enable_other_call'] = $otherCall ? 'true' : 'false';
        $data['need_record'] = $needRecord ? 'true' : 'false';

        // 签名
        $signature = $this->getSignature($data, $this->signMethod);
        $data['sign'] = $signature;
        // 请求
        return $this->post('', $data);
    }

    /**
     * AXB(AXN)关系解绑接口
     * 通过该接口，实现AXB三元关系的解绑，解绑过后，再通过呼叫X，将不能找到A或者B。
     * @param string $subsId 绑定关系ID
     * @return mixed
     * @throws GuzzleException
     */
    public function AxbUnbind($subsId)
    {
        // 公共参数
        $data = [
            'app_key' => $this->appKey,
            'timestamp' => $this->getTimestamp(),
            'format' => $this->format,
            'v' => $this->appVersion,
            'sign_method' => $this->signMethod,
            'method' => Alidayu::API_ALIBABA_ALIQIN_SECRET_AXB_UNBIND,
        ];

        // 入参
        $data['partner_key'] = $this->partnerKey;
        $data['subs_id'] = $subsId;

        // 签名
        $signature = $this->getSignature($data, $this->signMethod);
        $data['sign'] = $signature;
        // 请求
        return $this->post('', $data);
    }

    /**
     * 短信发送
     * @param string $recNum 短信接收号码。支持单个或多个手机号码，群发短信需传入多个号码，以英文逗号分隔，一次调用最多传入200个号码。
     * @param string $templateCode 短信模板ID，传入的模板必须是在阿里大于“管理中心-短信模板管理”中的可用模板。
     * @param string $signName 短信签名，传入的短信签名必须是在阿里大于“管理中心-短信签名管理”中的可用签名。
     * @param string $smsType 短信类型，传入值请填写normal
     * @param array $param 短信模板变量，传参规则{"key":"value"}，key的名字须和申请模板中的变量名一致，多个变量之间以逗号隔开。
     * @param string $extend 公共回传参数，在“消息返回”中会透传回该参数
     * @return mixed
     * @throws GuzzleException
     */
    public function smsSend($recNum, $templateCode, $signName, $smsType = 'normal', $param = [], $extend = '')
    {
        // 公共参数
        $data = [
            'app_key' => $this->appKey,
            'timestamp' => $this->getTimestamp(),
            'format' => $this->format,
            'v' => $this->appVersion,
            'sign_method' => $this->signMethod,
            'method' => Alidayu::API_ALIBABA_ALIQIN_FC_SMS_NUM_SEND,
        ];

        // 入参
        $data['rec_num'] = $recNum;
        $data['sms_template_code'] = $templateCode;
        $data['sms_free_sign_name'] = $signName;
        $data['sms_type'] = $smsType;
        if (count($param) > 0) {
            $data['sms_param'] = json_encode($param);
        }
        if ($extend !== '') {
            $data['extend'] = $extend;
        }

        // 签名
        $signature = $this->getSignature($data, $this->signMethod);
        $data['sign'] = $signature;
        // 请求
        return $this->post('', $data);
    }

    /**
     * 短信发送记录查询
     * @param string $recNum 短信接收号码
     * @param string $queryDate 短信发送日期，支持近30天记录查询，格式yyyyMMdd
     * @param int $currentPage 分页参数,页码
     * @param int $pageSize 分页参数，每页数量。最大值50
     * @param string $bizId 短信发送流水,同发送结果中model值
     * @return mixed
     * @throws GuzzleException
     */
    public function smsQuery($recNum, $queryDate, $currentPage = 1, $pageSize = 50, $bizId = '')
    {
        // 公共参数
        $data = [
            'app_key' => $this->appKey,
            'timestamp' => $this->getTimestamp(),
            'format' => $this->format,
            'v' => $this->appVersion,
            'sign_method' => $this->signMethod,
            'method' => Alidayu::API_ALIBABA_ALIQIN_FC_SMS_NUM_QUERY,
        ];

        // 入参
        $data['rec_num'] = $recNum;
        $data['query_date'] = $queryDate;
        $data['current_page'] = $currentPage;
        $data['page_size'] = $pageSize;
        if ($bizId !== '') {
            $data['biz_id'] = $bizId;
        }

        // 签名
        $signature = $this->getSignature($data, $this->signMethod);
        $data['sign'] = $signature;
        // 请求
        return $this->post('', $data);
    }

    /**
     * 文本转语音通知
     * @param string $calledNum 被叫号码，支持国内手机号与固话号码
     * @param string $ttsCode TTS模板ID，传入的模板必须是在阿里大于“管理中心-语音TTS模板管理”中的可用模板
     * @param string $calledShowNum 被叫号显，传入的显示号码必须是阿里大于“管理中心-号码管理”中申请或购买的号码
     * @param array $param 文本转语音（TTS）模板变量，传参规则{"key"："value"}，key的名字须和TTS模板中的变量名一致
     * @param string $extend 公共回传参数，在“消息返回”中会透传回该参数
     * @return mixed
     * @throws GuzzleException
     */
    public function ttsSingleCall($calledNum, $ttsCode, $calledShowNum, $param = [], $extend = '')
    {
        // 公共参数
        $data = [
            'app_key' => $this->appKey,
            'timestamp' => $this->getTimestamp(),
            'format' => $this->format,
            'v' => $this->appVersion,
            'sign_method' => $this->signMethod,
            'method' => Alidayu::API_ALIBABA_ALIQIN_FC_TTS_NUM_SINGLECALL,
        ];

        // 入参
        $data['called_num'] = $calledNum;
        $data['tts_code'] = $ttsCode;
        $data['called_show_num'] = $calledShowNum;
        if (count($param) > 0) {
            $data['tts_param'] = json_encode($param);
        }
        if ($extend !== '') {
            $data['extend'] = $extend;
        }

        // 签名
        $signature = $this->getSignature($data, $this->signMethod);
        $data['sign'] = $signature;
        // 请求
        return $this->post('', $data);
    }

    /**
     * 生成sign签名
     * @param array $params 入参
     * @param string $method 签名方式：md5或hmac-md5
     * @return string
     */
    public function getSignature($params = [], $method = Alidayu::SIGN_METHOD_HMAC)
    {
        $rawQuery = [];
        ksort($params);
        foreach ($params as $k => $v) {
            $rawQuery[] = sprintf('%s%s', $k, $v);
        }
        $rawText = implode('', $rawQuery);
        $signature = '';
        if ($method === Alidayu::SIGN_METHOD_MD5) {
            $signature = md5($this->appSecret . $rawText . $this->appSecret);
        } elseif ($method === Alidayu::SIGN_METHOD_HMAC) {
            $signature = hash_hmac('md5', $rawText, $this->appSecret);
        }
        return strtoupper($signature);
    }

    /**
     * 获取当前时间
     * @return string
     */
    public function getTimestamp()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * post请求
     * @param string $url 接口相对路径
     * @param array $data 接口传参
     * @param array $headers HTTP Header
     * @return mixed
     * @throws GuzzleException
     */
    public function post($url, $data, $headers = [])
    {
        $request = new Request('POST', $url, $headers);
        $response = $this->apiClient->send(
            $request,
            [
                'form_params' => $data,
            ]
        );
        $result = json_decode($response->getBody(), true);
        return $result;
    }

    /**
     * get请求
     * @param string $url 接口相对路径
     * @param array $data 接口传参
     * @param array $headers HTTP Header
     * @return mixed
     * @throws GuzzleException
     */
    public function get($url, $data, $headers = [])
    {
        $request = new Request('GET', $url, $headers);
        $response = $this->apiClient->send(
            $request,
            [
                'query' => $data,
            ]
        );
        $result = json_decode($response->getBody(), true);
        return $result;
    }
}