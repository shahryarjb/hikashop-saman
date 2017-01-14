<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Hikashop
 * @subpackage 	trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

if (!class_exists ('checkHack')){
	require_once JPATH_SITE . '/plugins/hikashoppayment/saman/trangell_inputcheck.php';
}

class plgHikashoppaymentSaman extends hikashopPaymentPlugin {
	var $accepted_currencies = array( "IRR" ); 
	var $multiple = true; 
	var $name = 'saman';
	var $pluginConfig = array(
		'samanmerchantId' => array("شناسه مرچند",'input')
	);

	function __construct(&$subject, $config) {	
		return parent::__construct($subject, $config);
	}
	
	function onBeforeOrderCreate(&$order,&$do){
		if(parent::onBeforeOrderCreate($order, $do) === true)
			return true;

	if (empty($this->payment_params->samanmerchantId)) {
			$this->app->enqueueMessage('لطفا تنظیمات پلاگین درگاه سامان را وارد نمایید','error');
			$do = false;
		}
	}
	
	function onAfterOrderConfirm(&$order,&$methods,$method_id) {
		parent::onAfterOrderConfirm($order,$methods,$method_id); 
		$notify_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment='.$this->name.'&tmpl=component&lang='.$this->locale . $this->url_itemid.'&orderid='.$order->order_id;	
		$app	= JFactory::getApplication();
		$Amount = round($order->cart->full_total->prices[0]->price_value_with_tax,5); // Toman 
		$merchantId = $this->payment_params->samanmerchantId;
		$reservationNumber = time();
		$totalAmount = $Amount;
		$callBackUrl  = $notify_url;
		$sendUrl = "https\://sep.shaparak.ir/Payment.aspx";
		$vars['saman'] =  '
			<script>
				var form = document.createElement("form");
				form.setAttribute("method", "POST");
				form.setAttribute("action", "'.$sendUrl.'");
				form.setAttribute("target", "_self");

				var hiddenField1 = document.createElement("input");
				hiddenField1.setAttribute("name", "Amount");
				hiddenField1.setAttribute("value", "'.$totalAmount.'");
				form.appendChild(hiddenField1);
				
				var hiddenField2 = document.createElement("input");
				hiddenField2.setAttribute("name", "MID");
				hiddenField2.setAttribute("value", "'.$merchantId.'");
				form.appendChild(hiddenField2);
				
				var hiddenField3 = document.createElement("input");
				hiddenField3.setAttribute("name", "ResNum");
				hiddenField3.setAttribute("value", "'.$reservationNumber.'");
				form.appendChild(hiddenField3);
				
				var hiddenField4 = document.createElement("input");
				hiddenField4.setAttribute("name", "RedirectURL");
				hiddenField4.setAttribute("value", "'.$callBackUrl.'");
				form.appendChild(hiddenField4);
				
				document.body.appendChild(form);
				form.submit();
				document.body.removeChild(form);
			</script>'
		;
		$this->vars = $vars;
		return $this->showPage('end'); 		
	}

	function onPaymentNotification(&$statuses)	{
		$app	= JFactory::getApplication();		
		$jinput = $app->input;
		$orderId = $jinput->get->get('orderid', '0', 'INT');
		if($orderId != null){
			$Order = $this->getOrder($orderId);
			$this->loadPaymentParams($Order);
			// $mobile = $this->getInfo($Order->order_user_id);
			$return_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id='.$orderId.$this->url_itemid;
			$cancel_url = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id='.$orderId.$this->url_itemid;
			$history = new stdClass();
			$history->amount = round($Order->order_full_price,5);
			//------------------------------------------------------

			$resNum = $jinput->post->get('ResNum', '0', 'INT');
			$trackingCode = $jinput->post->get('TRACENO', '0', 'INT');
			$stateCode = $jinput->post->get('stateCode', '1', 'INT');
			
			$refNum = $jinput->post->get('RefNum', 'empty', 'STRING');
			if (checkHack::strip($refNum) != $refNum )
				$refNum = "illegal";
			$state = $jinput->post->get('State', 'empty', 'STRING');
			if (checkHack::strip($state) != $state )
				$state = "illegal";
			$cardNumber = $jinput->post->get('SecurePan', 'empty', 'STRING'); 
			if (checkHack::strip($cardNumber) != $cardNumber )
				$cardNumber = "illegal";
				
			$price = $history->amount;	
			$merchantId = $this->payment_params->samanmerchantId;
			
			if (
				checkHack::checkNum($resNum) &&
				checkHack::checkNum($trackingCode) &&
				checkHack::checkNum($stateCode) 
			){
				if (isset($state) && ($state == 'OK' || $stateCode == 0)) {
					try {
						$out    = new SoapClient('https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL');
						$resultCode    = $out->VerifyTransaction($refNum, $merchantId);
					
						if ($resultCode == $price) {
							$msg= $this->getGateMsg(1); 
							$history->notified = 1;
							$history->data = 'شماره پیگیری '.  $trackingCode;
							$this->modifyOrder($orderId, 'confirmed', $history, true); 
							$app->redirect($return_url, '<h2>'.$msg.'</h2>'.'<h3>'.'شماره پیگری '. $trackingCode  .'</h3>' , $msgType='Message'); 
						}
						else {
							$msg= $this->getGateMsg($state); 
							$this->modifyOrder($orderId, 'cancelled', false, false); 
							$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
						}
					}
					catch(\SoapFault $e)  {
						$msg= $this->getGateMsg('error'); 
						$this->modifyOrder($orderId, 'cancelled', false, false); 
						$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
					}
				}
				else {
					$msg= $this->getGateMsg($state);
					$this->modifyOrder($orderId, 'cancelled', false, false); 
					$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error');
				}
			}
			else {
				$msg = $this->getGateMsg('hck2'); 
				$this->modifyOrder($orderId, 'cancelled', false, false); 
				$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			}
		}
		else {
			$msg= $this->getGateMsg('notff'); 
			$this->modifyOrder($orderId, 'cancelled', false, false); 
			$app->redirect($cancel_url, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
		}
	}

	public function getGateMsg ($msgId) {
		switch($msgId){
			case '-1': $out=  'خطای داخل شبکه مالی'; break;
			case '-2': $out=  'سپردها برابر نیستند'; break;
			case '-3': $out=  'ورودی های حاوی کاراکترهای غیر مجاز می باشد'; break;
			case '-4': $out=  'کلمه عبور یا کد فروشنده اشتباه است'; break;
			case '-5': $out=  'Database excetion'; break;
			case '-6': $out=  'سند قبلا برگشت کامل یافته است'; break;
			case '-7': $out=  'رسید دیجیتالی تهی است'; break;
			case '-8': $out=  'طول ورودی های بیش از حد مجاز است'; break;
			case '-9': $out=  'وجود کاراکترهای غیر مجاز در مبلغ برگشتی'; break;
			case '-10': $out=  'رسید دیجیتالی حاوی کاراکترهای غیر مجاز است'; break;
			case '-11': $out=  'طول ورودی های کمتر از حد مجاز است'; break;
			case '-12': $out=  'مبلغ برگشت منفی است'; break;
			case '-13': $out=  'مبلغ برگشتی برای برگشت جزیی بیش از مبلغ برگشت نخورده رسید دیجیتالی است'; break;
			case '-14': $out=  'چنین تراکنشی تعریف نشده است'; break;
			case '-15': $out=  'مبلغ برگشتی به صورت اعشاری داده شده است'; break;
			case '-16': $out=  'خطای داخلی سیستم'; break;
			case '-17': $out=  'برگشت زدن جزیی تراکنشی که با کارت بانکی غیر از بانک سامان انجام پذیرفته است'; break;
			case '-18': $out=  'IP Adderess‌ فروشنده نامعتبر'; break;
			case 'Canceled By User': $out=  'تراکنش توسط خریدار کنسل شده است'; break;
			case 'Invalid Amount': $out=  'مبلغ سند برگشتی از مبلغ تراکنش اصلی بیشتر است'; break;
			case 'Invalid Transaction': $out=  'درخواست برگشت یک تراکنش رسیده است . در حالی که تراکنش اصلی پیدا نمی شود.'; break;
			case 'Invalid Card Number': $out=  'شماره کارت اشتباه است'; break;
			case 'No Such Issuer': $out=  'چنین صادر کننده کارتی وجود ندارد'; break;
			case 'Expired Card Pick Up': $out=  'از تاریخ انقضا کارت گذشته است و کارت دیگر معتبر نیست'; break;
			case 'Allowable PIN Tries Exceeded Pick Up': $out=  'رمز (PIN) کارت ۳ بار اشتباه وارد شده است در نتیجه کارت غیر فعال خواهد شد.'; break;
			case 'Incorrect PIN': $out=  'خریدار رمز کارت (PIN) را اشتباه وارده کرده است'; break;
			case 'Exceeds Withdrawal Amount Limit': $out=  'مبلغ بیش از سقف برداشت می باشد'; break;
			case 'Transaction Cannot Be Completed': $out=  'تراکنش تایید شده است ولی امکان سند خوردن وجود ندارد'; break;
			case 'Response Received Too Late': $out=  'تراکنش در شبکه بانکی  timeout خورده است'; break;
			case 'Suspected Fraud Pick Up': $out=  'خریدار فیلد CVV2 یا تاریخ انقضا را اشتباه وارد کرده و یا اصلا وارد نکرده است.'; break;
			case 'No Sufficient Funds': $out=  'موجودی به اندازه کافی در حساب وجود ندارد'; break;
			case 'Issuer Down Slm': $out=  'سیستم کارت بانک صادر کننده در وضعیت عملیاتی نیست'; break;
			case 'TME Error': $out=  'کلیه خطاهای دیگر بانکی که باعث ایجاد چنین خطایی می گردد'; break;
			case '1': $out=  'تراکنش با موفقیت انجام شده است'; break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}

	public function getInfo ($id){
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('address_telephone');
		$query->from($db->qn('#__hikashop_address'));
		$query->where($db->qn('address_user_id') .  '=' . $db->q(intval($id)));
		$db->setQuery((string)$query); 
		$result = $db->Loadresult();
		return $result;
	}
}
