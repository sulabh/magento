<?php
/**
 * Get debug report button and link in the admin backend
 * 
 * Stech Abandcart Debug Report Block
 *
 * @category   SSTech
 * @package    SSTech_Abandcart
 * @author     SSTech
 * */

class SSTech_Abandcart_Block_Debug_Report extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	/**
	 *  get html element for button
	 *  
	 *  @param Varien_Data_Form_Element_Abstract $element
	 *  @return Mage_Adminhtml_Block_System_Config_Form_Field
	 */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $this->setElement($element);
        return $this->_getAddRowButtonHtml($this->__('Send Email Now'));
    }
    
    /**
     *  get row button html for button
     *
     *  @param Varien_Data_Form_Element_Abstract $element
     *  @return String $html
     */

    protected function _getAddRowButtonHtml($title) {
        $url = Mage::helper('adminhtml')->getUrl("abandcart/debug/sendreport");
        return $this->getLayout()->createBlock('adminhtml/widget_button')
                        ->setType('button')
                        ->setLabel($this->__($title))
                        ->setOnClick("window.location.href='" . $url . "'")
                        ->toHtml();
    }

}
