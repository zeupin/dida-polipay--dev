<?php
/**
 * Dida Framework  -- A Rapid Development Framework
 * Copyright (c) Zeupin LLC. (http://zeupin.com)
 *
 * Licensed under The MIT License.
 * Redistributions of files must retain the above copyright notice.
 */

namespace Dida\Polipay;

/**
 * PoliAPI
 */
class PoliAPI
{
    /**
     * 版本号
     */
    const VERSION = '20181108';

    /**
     * polipay的商家账号
     *
     * @var array 包含如下数据
     * [
     * "MerchantName"  => 'YOUR_COMPANY_NAME',
     * "MerchantCode"  => 'SS123456789',
     * "AuthenticationCode" => 'abcdef&hijk^1234',
     * "CurrencyCode"  => 'NZD',
     * "CountryCode"   => 'NZ',
     * "MerchantHomepageURL" => '',
     * "SuccessURL" => '',
     * "FailureURL" => '',
     * "CancellationURL" => '',
     * "NotificationURL" => '',
     * "Timeout" => 900,
     * ]
     */
    protected $conf = [];


    /**
     * 构造函数,必须提供Polipay的配置参数
     *
     * @param array $conf
     */
    public function __construct(array $conf)
    {
        $this->conf = $conf;
    }


    /**
     * 检查$data数组是否含有全部的keys字段
     *
     * @param array $data
     * @param array $keys
     *
     * @return array  成功返回[],失败返回missing的字段列表
     */
    public function required(array $data, array $keys)
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                $missing[] = $key;
            }
        }
        return $missing;
    }


    /**
     * 启动交易
     *
     * @param array $data 交易数据
     * [
     * "Amount"         => 1.23,    // 两位小数
     * "CurrencyCode"   => "NZD",   // 币种
     * "MerchantReference"  => "订单号", // 商家端的交易唯一参考号,一般就是订单号
     * "MerchantData"       => "商家的附加数据", // (可选)一般放交易时间,用户id等数据
     * ]
     *
     * @return array|null  成功返回正常的数组,失败返回null
     *
     * 典型的返回数据
     * {
     * "Success": true,
     * "NavigateURL": "https://txn.apac.paywithpoli.com/?Token=uo3K8YA7vCojXjA1yuQ3txqX4s26gQSh",
     * "ErrorCode": 0,
     * "ErrorMessage": null,
     * "TransactionRefNo": "996117408041"
     * }
     */
    public function initiateTransaction(array $data)
    {
        // 缺省设置
        $default = [
            "MerchantHomepageURL" => $this->conf["MerchantHomepageURL"],
            "SuccessURL"          => $this->conf["SuccessURL"],
            "FailureURL"          => $this->conf["FailureURL"],
            "CancellationURL"     => $this->conf["CancellationURL"],
            "NotificationURL"     => $this->conf["NotificationURL"],
        ];

        // 检查必填字段是否缺失
        $required_fieldds = ["Amount", "CurrencyCode", "MerchantReference"];
        $missing = $this->required($data, $required_fields);
        if ($missing) {
            $missing = implode(",", $missing);
            return [-1, "缺少必填字段{$missing}", null];
        }

        // 构造交易数据
        $txdata = array_merge($default, $data);
        $jsondata = json_encode($txdata, JSON_UNESCAPED_UNICODE);

        // 准备curl
        $url = "https://poliapi.apac.paywithpoli.com/api/v2/Transaction/Initiate";
        $ch = $this->curlInit($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsondata);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // 查询
        $result = curl_exec($ch);
        curl_close($ch);

        // 解析成数组
        $response = json_decode($result, true);

        // 返回
        return $response;
    }


    /**
     * 初始化curl
     *
     * 做了2件事:
     * 1. 附加好https的证书,polipay的所有API都要求是https方式访问
     * 2. 加上了商户的身份认证头
     *
     * @param init $url
     *
     * @return object 返回初始化好的$ch
     */
    protected function curlInit($url = null)
    {
        // 请求头
        $header = [];

        // 加身份认证头
        $auth = base64_encode($this->conf["MerchantCode"] . ':' . $this->conf["AuthenticationCode"]);
        $header[] = "Authorization: Basic {$auth}";

        // 指定内容类型为json
        $header[] = 'Content-Type: application/json';

        // 开始设置$ch
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cacert.pem");         // HTTPS CA客户端证书
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // 返回初始化好的$ch
        return $ch;
    }
}
