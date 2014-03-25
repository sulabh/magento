<?php

/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 *
 *
 * sStech Abandcart Adminhtml Debug Controller
 *
 * @category   SSTech
 * @package    SSTech Abandcart
 * @author     SSTech
 */
class SSTech_Abandcart_Adminhtml_DebugController extends Mage_Adminhtml_Controller_Action
{
	/**
	 * Generate and send instant report for abandoned cart
	 */
    public function sendreportAction()
    {
        $returnVal = Mage::getModel('sstech_abandcart/report')->sendReport();
        $result = explode('|',$returnVal);
        $success = ($result[0] == "NOTICE") ? true : false;
        $msg = $result[1];
        if ($success) {
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__($msg));
        }
        else{
            Mage::getSingleton('adminhtml/session')->addError($this->__($msg));
        }
        $this->_redirectReferer();
    }
}
