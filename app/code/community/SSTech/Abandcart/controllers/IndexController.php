<?php
/**
 * To autologin via email
 *
 * SSTech Autologin controller
 *
 * @category   SSTech
 * @package    SSTech_Abandcart
 * @author     SSTech
 * */
class SSTech_Abandcart_IndexController extends Mage_Core_Controller_Front_Action{
	
	const EXCEPTION_EMAIL_NOT_CONFIRMED       = 1;
	const EXCEPTION_INVALID_EMAIL_OR_PASSWORD = 2;
	
	public function indexAction(){
		if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('checkout/cart');
            return;
        }
        $session = $this->_getSession();

        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {
                try {
                    $this->login($login['username'], $login['password']);
                } catch (Mage_Core_Exception $e) {
                    switch ($e->getCode()) {
                        case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                            $value = Mage::helper('customer')->getEmailConfirmationUrl($login['username']);
                            $message = Mage::helper('customer')->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', $value);
                            break;
                        case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                            $message = $e->getMessage();
                            break;
                        default:
                            $message = $e->getMessage();
                    }
                    $session->addError($message);
                    $session->setUsername($login['username']);
                } catch (Exception $e) {
                    // Mage::logException($e); // PA DSS violation: this exception log can disclose customer password
                }
            } else {
                $session->addError($this->__('Login and password are required.'));
            }
        }

        $this->_redirect('checkout/cart');
	}
	
	/**
	 * Retrieve customer session model object
	 *
	 * @return Mage_Customer_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('customer/session');
	}

	public function login($username, $password)
	{
		/** @var $customer Mage_Customer_Model_Customer */
		$session = $this->_getSession();
		$customer = Mage::getModel('customer/customer')
		->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
		$customer->loadByEmail($username);
		if ($this->authenticate($username, $password)) {
			$session->setCustomerAsLoggedIn($customer);
			$session->renewSession();
			return true;
		}
		return false;
	}
	
	public function authenticate($login, $password)
	{
		$customer = Mage::getModel('customer/customer');
		$customer->loadByEmail($login);
		if ($customer->getConfirmation() && $customer->isConfirmationRequired()) {
			throw Mage::exception('Mage_Core', Mage::helper('customer')->__('This account is not confirmed.'),
					self::EXCEPTION_EMAIL_NOT_CONFIRMED
			);
		}
		
		if (!$this->validatePassword($login,$password)) {
			throw Mage::exception('Mage_Core', Mage::helper('customer')->__('Invalid login or password.'),
					self::EXCEPTION_INVALID_EMAIL_OR_PASSWORD
			);
		}
		return true;
	}
	
	/**
	 * Validate password with salted hash
	 *
	 * @param string $password
	 * @param string $login
	 * @return boolean
	 */

	public function validatePassword($login,$password)
	{
		$customer = Mage::getModel('customer/customer');
		$customer->loadByEmail($login);
		$hash = $customer->getPasswordHash();
		if (!$hash) {
			return false;
		}
		if($password == $hash)
		{
			return true;
		} else {
			return false;
		}
	}
}