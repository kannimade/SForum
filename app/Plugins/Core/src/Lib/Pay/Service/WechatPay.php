<?php

namespace App\Plugins\Core\src\Lib\Pay\Service;

use App\Plugins\Core\src\Models\PayOrder;
use Yansongda\Pay\Pay;

class WechatPay
{
    /**
     * 金额转分 -> 倍数
     * @var int | float
     */
    private int|float $amount_multiple = 100;


    /**
     * 计算实际金额
     * @param string|int $amount
     * @param bool $dividing
     * @return float|int
     */
    protected function calculate_amount(string|int $amount, bool $dividing=false): float|int
    {
        if(!is_numeric($amount)){
            return 0;
        }
        if($dividing===true){
            return $amount/$this->amount_multiple;
        }
        return $amount*$this->amount_multiple;
    }

    /**
     * 支付配置
     * @return array
     */
    public function config(): array
    {
        return [
            'wechat' => [
                'default' => [
                    // 必填-商户号，服务商模式下为服务商商户号
                    'mch_id' => pay()->get_options('wechat_mch_id'),
                    // 必填-商户秘钥
                    'mch_secret_key' => pay()->get_options('wechat_mch_secret_key'),
                    // 必填-商户私钥 字符串或路径
                    'mch_secret_cert' => pay()->get_options('wechat_mch_secret_cert'),
                    // 必填-商户公钥证书路径
                    'mch_public_cert_path' => pay()->get_options('wechat_mch_public_cert_path'),
                    // 必填
                    'notify_url' => pay()->get_options('wechat_notify_url',url('/api/pay/wechat/notify')),
                    // 必填 微信公众号id
                    'mp_app_id' => pay()->get_options('wechat_mp_app_id'),
                    // 选填-默认为正常模式。可选为： MODE_NORMAL, MODE_SERVICE
                    'mode' => Pay::MODE_NORMAL,
                ]
            ],
            'logger' => [
                'enable' => env('PAY_LOG_ENABLE',false),
                'file' => BASE_PATH.'/runtime/logs/pay.log',
                'level' => 'debug', // 建议生产环境等级调整为 info，开发环境为 debug
                'type' => 'single', // optional, 可选 daily.
                'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
            ],
            'http' => [ // optional
                'timeout' => 5.0,
                'connect_timeout' => 5.0,
                // 更多配置项请参考 [Guzzle](https://guzzle-cn.readthedocs.io/zh_CN/latest/request-options.html)
            ],
        ];

    }

    /**
     * 支付服务
     * @return \Yansongda\Pay\Provider\Wechat
     */
    public function pay()
    {
        return Pay::wechat(array_merge($this->config(), ['_force' => true]));
    }

    /**
     * 创建订单
     * @param PayOrder $order
     * @return array|\Yansongda\Supports\Collection
     */
    public function create($order){
        $create_order = [
            'out_trade_no' => (string)$order->id,
            'description' => $order->title,
            'amount' => [
                'total' => $this->calculate_amount($order->amount),
            ],
        ];
        $result = $this->pay()->scan($create_order);
        return Json_Api(200,true,['msg' => '订单创建成功!','url' => $result->code_url]);
    }

    /**
     * 支付回调
     * @param $request
     * @return array|bool|\Psr\Http\Message\ResponseInterface
     * @throws \SleekDB\Exceptions\IOException
     * @throws \SleekDB\Exceptions\IdNotAllowedException
     * @throws \SleekDB\Exceptions\InvalidArgumentException
     * @throws \SleekDB\Exceptions\JsonException
     * @throws \Yansongda\Pay\Exception\ContainerException
     * @throws \Yansongda\Pay\Exception\InvalidParamsException
     */
    public function notify($request): bool|array|\Psr\Http\Message\ResponseInterface
    {
        $result = $this->pay()->callback($request)->toArray();
        //admin_log()->insert('Pay','WechatPay','回调结果',$result);
        $notify_result = pay()->notify(
            $result['resource']['ciphertext']['out_trade_no'],
            $result['resource']['ciphertext']['trade_state_desc'],
            $result['resource']['ciphertext']['transaction_id'],
            $this->calculate_amount($result['resource']['ciphertext']['amount']['payer_total'],true),
            $result['resource']['ciphertext'],
            $this->calculate_amount($result['resource']['ciphertext']['amount']['total'],true),
        );
        if($notify_result===true){
            return  true;
        }
        admin_log()->insert('Pay','WechatPay','支付回调失败!',$notify_result);
        return $notify_result;
    }
}