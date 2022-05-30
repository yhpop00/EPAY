<?php

class sytpay_plugin
{
	static public $info = [
		'name'        => 'sytpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '一扫付', //支付插件显示名称
		'author'      => '一扫付', //支付插件作者
		'link'        => 'https://sytpay.cn/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $ordername, $sitename, $conf;

		if($order['typename'] == 'alipay'){
			$payMethod = '2';
			$payType = '21';
		}elseif($order['typename'] == 'wxpay'){
			$payMethod = '1';
			$payType = '11';
		}elseif($order['typename'] == 'bank'){
			$payMethod = '5';
			$payType = '51';
		}

		$apiurl = 'http://api.sytpay.cn/index.php/Api/Index/createOrder';
		$data = array(
			"orderAmount" => (float)$order['realmoney'],
			"orderId" => TRADE_NO,
			"merchant" => $channel['appid'],
			"payMethod" => $payMethod,
			"payType" => $payType,
			"signType" => "MD5",
			"version" => "1.0",
			"outcome" => "no",
		);

		ksort($data);
		$postString = http_build_query($data);
		$sign = strtoupper(md5($postString.$channel['appkey']));
		$data['sign'] = $sign;
		$data["productName"] = $ordername;
		$data["notifyUrl"] = $conf['localurl'].'pay/notify/'.TRADE_NO.'/';
		$data["returnUrl"] = $siteurl.'pay/return/'.TRADE_NO.'/';
		$data["createTime"] = "".time();

		$jump_url = $apiurl.'?'.http_build_query($data);

		return ['type'=>'jump','url'=>$jump_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		//file_put_contents('logs.txt', $json);
		$arr = json_decode($json,true);
		$signstr = substr($json, strpos($json,'"paramsJson":')+13, -1);
		$jsonBase64 = base64_encode($signstr);
		$jsonBase64Md5 = md5($jsonBase64);
		$sign = strtoupper(md5($channel['appkey'].$jsonBase64Md5));
        if ($sign === $arr['sign']) {
			if($arr['paramsJson']['code'] == '000000'){
				$out_trade_no = daddslashes($arr['paramsJson']['data']['orderId']);
				$trade_no = daddslashes($arr['paramsJson']['data']['outTradeNo']);
				$money = $arr['paramsJson']['data']['orderAmount'];
				if($out_trade_no == TRADE_NO && round($money,2)==round($order['realmoney'],2)){
					processNotify($order, $trade_no);
				}
			}
			return ['type'=>'html','data'=>'success'];
        }else{
			return ['type'=>'html','data'=>'fail'];
		}

	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

}