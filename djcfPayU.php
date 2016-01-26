<?php
/** 
 * @version      1.0
 * @package      DJ Classifieds
 * @subpackage   DJ Classifieds PayU Payment Plugin
 * @copyright    Copyright (C) 2015 Anything & Everything, All rights reserved.
 * @license      http://www.gnu.org/licenses GNU/GPL
 * @autor url    http://netcraft-devops.com
 * @autor email  kenneth@netcraft-devops.com
 * @Developer    Kenneth Onah - kenneth@netcraft-devops.com
 */
defined('_JEXEC') or die('Restricted access');
jimport('joomla.event.plugin');
$lang = JFactory::getLanguage();
$lang->load('plg_djclassifiedspayment_djcfPayU',JPATH_ADMINISTRATOR);

require_once(dirname(__FILE__).'/djcfPayU/lib/PayUAPI.php');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_djclassifieds'.DS.'lib'.DS.'djseo.php');

class plgdjclassifiedspaymentdjcfPayU extends JPlugin
{
	public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        
        $this->loadLanguage('plg_djcfPayU');
        
        $params["plugin_name"] = "djcfPayU";
        $params["logo"] = "payu-logo.png";
        $params['icon'] = 'payu.png';
		$params["payment_method"] = JText::_("PLG_DJCLASSIFIEDSPAYMENT_DJCFPAYU_PAYMENT_METHOD");
        $params["description"] = JText::_("PLG_DJCLASSIFIEDSPAYMENT_DJCFPAYU_PAYMENT_METHOD_DESC");
		$params["test_mode"] = $this->params->get("test_mode", '1');
		$params["store_name"] = $this->params->get("store_name", '3D Sim Store FAuth Off Force On');
		$params["merchant_id"] = $this->params->get("merchant_id", '7');
		$params["soap_username"] = $this->params->get("soap_username", '100032');
		$params["safe_key"] = $this->params->get("safe_key", '{CE62CE80-0EFD-4035-87C1-8824C5C46E7F}');
		$params["soap_password"] = $this->params->get("soap_password", 'PypWWegU');
		$params['payment_methods'] = $this->params->get("payment_methods", 'CREDITCARD, EFT_PRO, WALLET_PAYU');
		$params["secure_3ds"] = $this->params->get("secure_3ds", '0');
		$params["budget_payment"] = $this->params->get("budget_payment", '0');
		$params["transaction_type"] = $this->params->get("transaction_type", 'PAYMENT');
        $params["currency_code"] = $this->params->get("currency_code", "ZAR");

        $this->params = $params;
        
    }

    function onProcessPayment()
    {
        $ptype = JRequest::getVar('ptype','');
        $id = JRequest::getInt('id','0');
        $html="";

            
        if($ptype == $this->params["plugin_name"])
        {
            $action = JRequest::getVar('pactiontype','');
            switch($action)
            {
                case "process" :
                	$html = $this->process($id);
                	break;
                case "notify" :
                	$html = $this->_notify_url();
                	break;
                default :
                	$html =  $this->process($id);
                	break;
            }
        }
        return $html;
    }

	function process($id)
    {
		JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.DS.'tables');		
		jimport( 'joomla.database.table' );
		$db 	= JFactory::getDBO();
		$app 	= JFactory::getApplication();
		$Itemid = JRequest::getInt("Itemid",'0');
		$par 	= JComponentHelper::getParams( 'com_djclassifieds' );
		$user 	= JFactory::getUser();
		$ptype	= JRequest::getVar('ptype');
		$type	= JRequest::getVar('type','');
		$row 	= JTable::getInstance('Payments', 'DJClassifiedsTable');	
		
		 if($type=='prom_top'){        	        	
        	$query ="SELECT i.* FROM #__djcf_items i "
        			."WHERE i.id=".$id." LIMIT 1";
        	$db->setQuery($query);
        	$item = $db->loadObject();
        	if(!isset($item)){
        		$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
        		$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
        	}        						 
        					 
       		$row->item_id = $id;
       		$row->user_id = $user->id;
      		$row->method = $ptype;
       		$row->status = 'Start';
      		$row->ip_address = $_SERVER['REMOTE_ADDR'];
       		$row->price = $par->get('promotion_move_top_price',0);
       		$row->type=2;        	
       		$row->store();

       		$amount = $par->get('promotion_move_top_price',0);
      		$itemname = $item->name;
       		$payment_id = $row->id;
       		$item_cid = '&cid='.$item->cat_id;       	
        }else if($type=='points'){
			$query ="SELECT p.* FROM #__djcf_points p "				   
				   ."WHERE p.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$points = $db->loadObject();
			if(!isset($points)){
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_POINTS_PACKAGE');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
			}			
				$row->item_id = $id;
				$row->user_id = $user->id;
				$row->method = $ptype;
				$row->status = 'Start';
				$row->ip_address = $_SERVER['REMOTE_ADDR'];
				$row->price = $points->price; 
				$row->type=1;
				
				$row->store();		
			
			$amount = $points->price;
			$itemname = $points->name;
			$payment_id = $row->id;
			$item_cid = '';
		}else{
			$query ="SELECT i.*, c.price as c_price FROM #__djcf_items i "
				   ."LEFT JOIN #__djcf_categories c ON c.id=i.cat_id "
				   ."WHERE i.id=".$id." LIMIT 1";
			$db->setQuery($query);
			$item = $db->loadObject();
			//die($item->pay_type);
			if(!isset($item)){
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
				$redirect="index.php?option=com_djclassifieds&view=items&cid=0";
			}
			
				$amount = 0;
				
				if(strstr($item->pay_type, 'cat')){			
					$amount += $item->c_price/100; 
				}
				if(strstr($item->pay_type, 'duration_renew')){			
					$query = "SELECT d.price_renew FROM #__djcf_days d "
					."WHERE d.days=".$item->exp_days;
					$db->setQuery($query);
					$amount += $db->loadResult();
				}else if(strstr($item->pay_type, 'duration')){			
					$query = "SELECT d.price FROM #__djcf_days d "
					."WHERE d.days=".$item->exp_days;
					$db->setQuery($query);
					$amount += $db->loadResult();
				}
				
				$query = "SELECT p.* FROM #__djcf_promotions p "
					."WHERE p.published=1 ORDER BY p.id ";
				$db->setQuery($query);
				$promotions=$db->loadObjectList();
				foreach($promotions as $prom){
					if(strstr($item->pay_type, $prom->name)){	
						$amount += $prom->price; 
					}	
				}
			
				/*$query = 'DELETE FROM #__djcf_payments WHERE item_id= "'.$id.'" ';
				$db->setQuery($query);
				$db->query();
				
				
				$query = 'INSERT INTO #__djcf_payments ( item_id,user_id,method,  status)' .
						' VALUES ( "'.$id.'" ,"'.$user->id.'","'.$ptype.'" ,"Start" )'
						;
				$db->setQuery($query);
				$db->query();*/
				
					$row->item_id = $id;
					$row->user_id = $user->id;
					$row->method = $ptype;
					$row->status = 'Start';
					$row->ip_address = $_SERVER['REMOTE_ADDR'];
					$row->price = $amount;
					$row->type=0;
				
				$row->store();					
			
			$itemname = $item->name;
			$payment_id = $row->id;
			$item_cid = '&cid='.$item->cat_id;
		}

		/* API step 1 START */

		$payment_title = 'Item ID: '.$id.' ('.$itemname.')';
		$payment_reason = $type ? $type : $item->pay_type;
		$currency_code = $this->params['currency_code'];
		
		$notifyURL = JRoute::_(JURI::root().'index.php?option=com_djclassifieds&task=processPayment&ptype='.$this->params["plugin_name"].'&pactiontype=notify&id='.$id.'&cid='.$item->cat_id.'&Itemid='.$Itemid.'&payId='.$payment_id);
		$cancelUrl = JRoute::_(JURI::root().'index.php?option=com_djclassifieds&task=paymentReturn&r=pending&id='.$id.'&cid='.$item->cat_id.'&Itemid='.$Itemid.'&payId='.$payment_id);
		
		$config = array(
			'store_name' 		=> $this->params['store_name'],
			'merchant_id' 		=> $this->params['merchant_id'],
			'safe_key' 			=> $this->params['safe_key'],
			'payment_methods'	=> $this->params['payment_methods'],
			'secure_3ds' 		=> $this->params['secure_3ds'],
			'budget_payment' 	=> $this->params['budget_payment'],
			'transaction_type' 	=> $this->params['transaction_type'],
			'currency_code' 	=> $currency_code,
			'notify_url'		=> $notifyURL,
			'cancel_url'		=> $cancelUrl,
			'return_url'		=> $notifyURL,
			'name'				=> $user->name,
			'email'				=> $user->email,
			'basket_desc'		=> $payment_title . ' ' . $payment_reason,
			'amount'			=> (number_format($amount, 2, '.', '') * 100),
			'ip_addr'			=> $_SERVER['REMOTE_ADDR'],
		);
		
		$payu = new PayUAPI($this->params['soap_username'], $this->params['soap_password'], 
							$this->params['test_mode'], $this->params['safe_key']);
		$response = $payu->configure_payment($config);
		          
		if(!$response['canPay']) {
			die('Error ecountered while processing payment');
		}
		  
		if(isset($response['rpp'])) {
			header("Location: ".$response['rpp']);
		}
		
		/* API step 1 END */
    }
    
    function _notify_url()
    { 
    	$db = JFactory::getDBO();
    	$par = JComponentHelper::getParams('com_djclassifieds');
    	$payment_id	= JRequest::getInt('payId','0');
    	$reference = JRequest::getVar('PayUReference','');
    
    	/* API step 4 START */
    	$payu = new PayUAPI($this->params['soap_username'], $this->params['soap_password'],
    						$this->params['test_mode'], $this->params['safe_key']);
    	
    	$paymentInfo = $payu->get_payment_info($reference);

    	if($paymentInfo == false) {
    		die('Error ecountered while retrieving payment information');
    	}
    	//echo '<pre>';
    	//	var_dump($paymentInfo);
    	//	exit;
    	//echo '</pre>';
    	$status = $paymentInfo['return']['transactionState'];
    	$successful = $paymentInfo['return']['successful'];
    	$message = $paymentInfo['return']['resultMessage'];
    	
    	if($status=='SUCCESSFUL' && $successful) {
    
    		$query = "UPDATE #__djcf_payments SET status='Completed', transaction_id='".$reference."' "
    				."WHERE id=".$payment_id." AND method='".$this->params['plugin_name']."'";
    		$db->setQuery($query);
    		$db->query();
    
    		$this->_paymentSuccess();
    
    	} else {
	
    		$query = "UPDATE #__djcf_payments SET status='Cancelled', transaction_id='".$txn_id."' "
    				."WHERE id=".$payment_id." AND method='".$this->params['plugin_name']."'";
    		$db->setQuery($query);
    		$db->query();
    		
    		$this->_paymentError($message);
    
    	}
    
    	/* API step 4 END */
    
    }
    
    function onPaymentMethodList($val)
    {
    	$type='';
    	if($val['type']){
    		$type='&type='.$val['type'];
    	}
    	$html ='';
    	if ($this->params['soap_username'] != '' && $this->params['soap_password'] != '' 
    		&& $this->params['currency_code'] != '' && $this->params['safe_key'] != '' && $this->params['merchant_id'] != '') {
    		$paymentLogoPath = JURI::root()."plugins/djclassifiedspayment/".$this->params["plugin_name"]."/".$this->params["plugin_name"]."/images/".$this->params["logo"];
    		$form_action = JRoute :: _("index.php?option=com_djclassifieds&task=processPayment&ptype=".$this->params["plugin_name"]."&pactiontype=process&id=".$val["id"].$type, false);
    		$html ='<table cellpadding="5" cellspacing="0" width="100%" border="0">
                <tr>';
    		if($this->params["logo"] != ""){
    			$html .='<td class="td1" width="160" align="center">
                        <img src="'.$paymentLogoPath.'" title="'. $this->params["payment_method"].'"/>
                    </td>';
    		}
    		$html .='<td class="td2">
                        <h2>'.$this->params['payment_method'].'</h2>
                        <p style="text-align:justify;">'.$this->params["description"].'</p>
                    </td>
                    <td class="td3" width="130" align="center">
                        <a class="button" style="text-decoration:none;" href="'.$form_action.'">'.JText::_('COM_DJCLASSIFIEDS_BUY_NOW').'</a>
                    </td>
                </tr>
            </table>';
    	}
    
    	return $html;
    }
    
    function _paymentError($msg)
    {
    	$app = JFactory::getApplication();

    	$redirect=DJClassifiedsSEO::getCategoryRoute('0:all');
    	$redirect = JRoute::_($redirect);
    	$app->redirect($redirect, $msg);
    }
    
    private function _paymentSuccess() 
    {
    	$id     = JRequest::getInt("id",'0');
		$cid    = JRequest::getInt("cid",'0');
		$Itemid = JRequest::getInt("Itemid",'0');
		$payment_id = JRequest::getInt('payId','0');

    	$this->_setPaymentCompleted($payment_id);
    	 
    	$location = JRoute::_(JURI::root().'index.php?option=com_djclassifieds&task=paymentReturn&r=ok&id='.$id.'&cid='.$cid.'&Itemid='.$Itemid.'&payId='.$payment_id);
    
    	header("Location: $location");
    
    }
    
    private function _setPaymentCompleted($id) {
    
    	$db = JFactory::getDBO();
    	$par = JComponentHelper::getParams( 'com_djclassifieds' );
    
    	$query = "SELECT p.*  FROM #__djcf_payments p "
    			."WHERE p.id='".$id."' ";
    	$db->setQuery($query);
    	$payment = $db->loadObject();
    	
    	if($payment){
    
    		if($payment->type==2){
    
    			$date_sort = date("Y-m-d H:i:s");
    			$query = "UPDATE #__djcf_items SET date_sort='".$date_sort."' "
    					."WHERE id=".$payment->item_id." ";
    			$db->setQuery($query);
    			$db->query();
    		}else if($payment->type==1){
    
    			$query = "SELECT p.points  FROM #__djcf_points p WHERE p.id='".$payment->item_id."' ";
    			$db->setQuery($query);
    			$points = $db->loadResult();
    
    			$query = "INSERT INTO #__djcf_users_points (`user_id`,`points`,`description`) "
    					."VALUES ('".$payment->user_id."','".$points."','".JText::_('COM_DJCLASSIFIEDS_POINTS_PACKAGE')." - ".$this->params['payment_method']." <br />".JText::_('COM_DJCLASSIFIEDS_PAYMENT_ID').': '.$payment->id."')";
    			$db->setQuery($query);
    			$db->query();
    		}else{
    
    			$query = "SELECT c.*  FROM #__djcf_items i, #__djcf_categories c "
    					."WHERE i.cat_id=c.id AND i.id='".$payment->item_id."' ";
    			$db->setQuery($query);
    			$cat = $db->loadObject();
    
    			$pub=0;
    			if(($cat->autopublish=='1') || ($cat->autopublish=='0' && $par->get('autopublish')=='1')){
    				$pub = 1;
    			}
    
    			$query = "UPDATE #__djcf_items SET payed=1, pay_type='', published='".$pub."' "
    					."WHERE id=".$payment->item_id." ";
    			$db->setQuery($query);
    			$db->query();
    		}
    	}
    }
}
?>