<?php
/**
 * Stech Abandcart Report
 *
 * @category    SSTech
 * @package     SSTech_Abandcart
 * @author      Developer
 */
class SSTech_Abandcart_Model_Report extends Mage_Core_Model_Abstract
{

    private $_enabled = null;
    private $_from_email = null;
    private $_to_email = null;
    private $_template = null;
    private $_days = 7; /* to calculate the number of days*/
    private $_currency = null;
    private $_store_url = null;
    private $_success_count = 0;
    private $_unsuccess_count = 0;
    private $_skin_url = null;
    private $_recepient_email = null;
    private $_recepient_found = null;
    private $_customer_name=null;
    private $_return_cart = null;

	/**
	 * (non-PHPdoc)
	 * @see Mage_Shell_Abstract::_construct()
	 */
	public function _construct() {
        $this->_enabled = Mage::getStoreConfig('sstech_abandcart/sstech_abandcart_settings/report_enabled');
        $this->_from_email = Mage::getStoreConfig('sstech_abandcart/sstech_abandcart_settings/report_from_email');
        $this->_template = Mage::getStoreConfig('sstech_abandcart/sstech_abandcart_settings/report_template');
        $this->_days = Mage::getStoreConfig('sstech_abandcart/sstech_abandcart_settings/report_days');
        $this->_currency = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
        $this->_store_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $this->_skin_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN);
        $this->_recepient_email = Mage::getStoreConfig('sstech_abandcart/sstech_abandcart_settings/report_to_email');
	} // end
	
	/**
	 *  check if email reporting is enabled
	 *
	 *  @return boolean
	 */

    public function isEnabled() {
        return $this->_enabled;
    }
    
    /**
     *  send email for abandoned cart to the shopping cart users
     *
     *  @return String $fresult
     */

    public function sendReport() {
    	
        $html = "";
        $core_helper = Mage::helper('core');
        $image_helper = Mage::helper('catalog/image');
        /* Date Default Function use to calculate the Difference*/
        date_default_timezone_set(date_default_timezone_get());
        $timezone=date_default_timezone_get();
        $datetime = Zend_Date::now();
        $now = $datetime->get('yyyy-MM-dd HH:mm:ss');
        
        $from = strtotime($now)-(60*60*24*30);  // 30 days before today
        $to = strtotime($now)-(60*60*24*$this->_days);
        $from = date('Y-m-d H:i:s', $from);
        $to = date('Y-m-d H:i:s', $to);

        try {
        	$abandon = $this->_getAllAbandonedCart($now , $from);
        	if(empty($abandon)) { return "NOTICE|There is no Abandoned cart present";}
        	$products = Mage::getModel('catalog/product');
        	foreach($abandon as $a){
        		$quotes_item = Mage::getModel('sales/quote_item')->getCollection()->addFieldToFilter('quote_id',$a['entity_id']);
        		$b = $quotes_item->getData();
        		if($this->isRecepient($a['customer_email'])){
	        		foreach($b as $c){
	        			$price = $c['qty']*$c['price'];
	        			if($c['parent_item_id'] == null){
	        				$pcache = $products->loadByAttribute('sku', $c['sku']);
	        				$name = $pcache->getName();
	        				$thumb = $image_helper->init($pcache, 'thumbnail')->resize(60,60);
	        				$eurl = $this->_store_url.$this->_getParent($pcache)->getUrlKey();
	        				$html .= "<tr>";
	        				$html .= "<td><img src='".$thumb."'></td><td><a href='".$eurl."'>".$name."</a></td><td>".$c['sku']."</td><td>".intval($c['qty'])."</td><td>".$core_helper->formatPrice($price, FALSE)."</td>";
	        				$html .= "</tr>";
	        			}
	        		}

	        		$html .= "<tr><td></td><td></td><td></td><td>Subtotal</td><td align='right'>".$core_helper->formatprice($a['subtotal'],false)."</td></tr>";
	        		$html .= $this->_shippingCost($a['subtotal'],$a['grand_total']);
	        		$html .= "<tr><td></td><td></td><td></td><td><b>Grand Total</b></td><td><b>".$core_helper->formatprice($a['grand_total'],false)."</b></td></tr>";
	        		$this->_customer_name = $a['customer_firstname'];
	        		$this->_return_cart = $this->_getReturnCartForm($a['customer_id']);
	        		$res = $this->sendEmail($html,$this->_store_url,$this->_customer_name,$this->_return_cart,$a['customer_email']);
	        		$html=""; //reset html
	        		$this->_success_count = ($res == "YES") ? ($this->_success_count+1) : $this->_success_count; 
	        		$this->_unsuccess_count = ($res == "NO") ? ($this->_unsuccess_count+1) : $this->_unsuccess_count;
        		}
        	}
        	
        	if(!is_null($this->_recepient_found) && $this->_recepient_found == "no")
        	{
        		throw new Exception(' - Provided Recepient does not have abandoned cart');
        	}
        }

        catch (Exception $e)
        {
            return "ERROR|There was a problem sending the email".$e->getMessage();
        }
		
        $fresult ="NOTICE|".$this->_success_count." mails sent successfully ".$this->_unsuccess_count."  were not delivered"; 
        return $fresult;

    } // end
    
    

       
    /**
     * create return to cart form
     *
     *  @param int $id
     *  @return String
     */
    private function _getReturnCartForm($id){
    	
    	$customer = Mage::getModel('customer/customer')->load($id);

    	$form = "";

    	$form .= "<form action='http://www.testvitality4life.com.au/vl4/autologin/' method='post'>";

    	$form .= "<input type='hidden' name='login[username]' value='".$customer->getEmail()."'>";

    	$form .= "<input type='hidden' name='login[password]' value='".$customer->getPasswordHash()."'>";

    	$form .= "<input type='submit' value='Return to your cart'>";

    	$form .= "</form>";
    	
    	return $form;
    }
    
    /**
     *  check if a shipping cost is applied 
     *
     *  @param double $subtotal
     *  @param double $grandtotal
     *  @return String
     */
    
    private function _shippingCost($subtotal , $grandtotal){

    	$shipping = $grandtotal - $subtotal;

    	if($shipping > 0)

    	{

    		return "<tr><td></td><td></td><td></td><td>Shipping Cost</td><td align='right'>".Mage::helper('core')->formatprice($shipping,false)."</td></tr>";

    	} else {

    		return "";

    	}

    }
    
    /**
     *  check if a specific cart user needs to be sent email
     *
     *  @param String $email
     *  @return boolean
     */
    
    private function isRecepient($email){
    	if(is_null($this->_recepient_email) || empty($this->_recepient_email)){
    		return true;
    	}
    	
    	$emails = explode(';',$this->_recepient_email);
    	
    	foreach($emails as $e)
    	{
	    	if($e == $email)
	    	{	
	    		$this->_recepient_found = "yes";
	    		return true;
	    	} else {
	    		$this->_recepient_found = "no";
	    		return false;
	    	}
    	}
    }
    
    /**
     *  get all abandoned cart in given date range
     *
     *  @param datetime $to
     *  @param datetime $from
     *  @return array 
     */
    
    private function _getAllAbandonedCart( $to , $from){
    	$quotes = Mage::getModel('sales/quote')->getCollection();

    	$quotes->addFieldToFilter('updated_at',array('to'=>$to ,'from'=>$from ,  'date'=>true));

    	$quotes->addFieldToFilter('is_active', 1);

    	$quotes->addFieldToFilter('customer_email', array('notnull'=>true));
    	$quotes->addFieldToFilter('items_count',array('gt'=> 0));

    	$quotes->setOrder('entity_id', 'DESC');

    	#$quotes->setPageSize(1);

    	return $quotes->getData();
    }
    
    /**
     *  get parent product of the product 
     *
     *  @param Mage_Catalog_Model_Product $product
     *  @return Mage_Catalog_Model_Product $parent
     */
    
    private function _getParent($product){

    	if($product->getTypeId() == "simple"){

    		$parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());

    		if(!$parentIds)

    			$parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());

    		if(isset($parentIds[0])){

    			$parent = Mage::getModel('catalog/product')->load($parentIds[0]);

    			return $parent;

    		} else {

    			return $product;

    		}

    	}

    }

    /**
     *  send email
     *
     *  @param String $html
     *  @param String $email
     *  @return String YES|NO
     */
   
    private function sendEmail($html ,$url,$name, $returncart,$email) {

        $result = true;

        try {

                // send mail to each recipient
                $mail = Mage::getModel('core/email_template');
                $mail->setDesignConfig(array('area' => 'frontend', 'store' => Mage::app()->getStore()->getId()))
                    ->sendTransactional(
                        $this->_template,
                        $this->_from_email,
                        trim($email),
                        null,
                        array('items'=>$html,'store_url'=>$url,'customer_name'=>$name,'return_cart'=>$returncart));

                $result = $mail->getSentSuccess() ? $result : false;

        }
        catch (Exception $e)
        {
            log($e->getMessage());
            $exception = $e.getMessage();
            throw new Exception($exception);
        } 

        return $result ? "YES" : "NO";

    }

} // end class
