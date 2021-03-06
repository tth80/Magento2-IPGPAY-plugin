<?php
/**
 * @copyright Copyright (c) 2017 IPG Group Limited
 * All rights reserved.
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE.txt file for details.
 **/

namespace IPGPAY\IPGPAYMagento2\Controller\Redirect;

use IPGPAY\IPGPAYMagento2\API\Config;
use IPGPAY\IPGPAYMagento2\API\Constants;
use IPGPAY\IPGPAYMagento2\API\Functions;
use IPGPAY\IPGPAYMagento2\API\ParamSigner;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class Index extends Action
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig = null;
    /**
     * @var Registry
     */
    protected $_coreRegistry;
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    protected $_isUsePopup;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param ScopeConfigInterface $scopeConfig
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        ScopeConfigInterface $scopeConfig,
        PageFactory $pageFactory
    )
    {
        parent::__construct($context);
        $this->_coreRegistry = $coreRegistry;
        $this->_scopeConfig = $scopeConfig;
        $this->_resultPageFactory = $pageFactory;
        $this->_isUsePopup = $this->getIPGPAYConfig('use_popup');
    }

    protected function getFormSubmissionParameters($order)
    {
        return $this->mergeFormParameters(
            $this->getCustomerParameters($order),
            $this->getOrderItemsParameters($order),
            $this->getGatewayParameters($order)
        );
    }

    public function execute()
    {
        $order = $this->_getCheckout()->getLastRealOrder();
        $formSubmissionParameters = $this->getFormSubmissionParameters($order);

        $this->_coreRegistry->register('ipgpay_payment_form_data', $formSubmissionParameters);
        $this->_coreRegistry->register('ipgpay_payment_form_url', $this->getPaymentFormUrl());
        $resultPage = $this->_resultPageFactory->create();
        return $resultPage;
    }

    /**
     * @return string
     */
    protected function getPaymentFormUrl()
    {
        return $this->getPaymentFormHost() . '/payment/form/post';
    }

    protected function getPaymentFormHost()
    {
        return preg_replace('#/payment/form/post#i', '', $this->getIPGPAYConfig('payment_form_url'));
    }

    /**
     * Get customer info
     *
     * @param $order
     * @return array
     */
    protected function getCustomerParameters(Order $order)
    {
        $billingAddress = $order->getBillingAddress();
        $billing = [];
        if ($billingAddress) {
            $billing = [
                'customer_first_name' => $billingAddress->getFirstname(),
                'customer_last_name' => $billingAddress->getLastname(),
                'customer_company' => $billingAddress->getCompany(),
                'customer_city' => $billingAddress->getCity(),
                'customer_state' => $billingAddress->getRegion(),
                'customer_postcode' => $billingAddress->getPostcode(),
                'customer_country' => $billingAddress->getCountryId(),
                'customer_email' => $billingAddress->getEmail(),
                'customer_phone' => $billingAddress->getTelephone(),
            ];
            $address = $billingAddress->getStreet();
            if (is_array($address)) {
                $billing['customer_address'] = $address[0];
                $billing['customer_address2'] = isset($address[1]) ? $address[1] : '';
            }
        }
        $shippingAddress = $order->getShippingAddress();
        $shipping = [];
        if ($shippingAddress) {
            $shipping = [
                'shipping_first_name' => $shippingAddress->getFirstname(),
                'shipping_last_name' => $shippingAddress->getLastname(),
                'shipping_company' => $shippingAddress->getCompany(),
                'shipping_city' => $shippingAddress->getCity(),
                'shipping_state' => $shippingAddress->getRegion(),
                'shipping_postcode' => $shippingAddress->getPostcode(),
                'shipping_country' => $shippingAddress->getCountryId(),
                'shipping_email' => $shippingAddress->getEmail(),
                'shipping_phone' => $shippingAddress->getTelephone(),
            ];
            $address = $shippingAddress->getStreet();
            if (is_array($address)) {
                $shipping['shipping_address'] = $address[0];
                $shipping['shipping_address2'] = isset($address[1]) ? $address[1] : '';
            }
        }
        return array_merge($billing, $shipping);
    }

    /**
     * @param Order $order
     * @return array
     */
    protected function getOrderItemsParameters(Order $order)
    {
        $items = [];

        $shippingCost = $order->getShippingAmount();
        $taxCost = $order->getTaxAmount();
        $discount = 0;
        $appliedTaxCost = 0;

        $orderItems = $order->getAllItems();

        $idx = 0;
        foreach ($orderItems as $item) {
            if ($item->getQtyToShip() < 1) {
                continue;
            }

            $product = $item->getProduct();
            $items[] = $this->getItemArray(
                ++$idx,
                $product->getSku(),
                $product->getName(),
                $item->getDescription(),
                $item->getQtyToShip(),
                $item->getIsVirtual() ? '1' : '0',
                $item->getPrice(),
                $order->getOrderCurrencyCode(),
                false,
                $this->getIPGPAYConfig('merchant_rebilling') == '1' ? '1' : '0'
            );

            $discount -= $item->getDiscountAmount();
            $appliedTaxCost += $this->getWeeeAppliedTaxAmount($item);
        }

        // include the Weee module applied tax in the generic tax amount.
        $taxCost += $appliedTaxCost;

        if ($shippingCost > 0) {
            $items[] = $this->getItemArray(++$idx, null, 'Shipping', 'Shipping and handling', '1', '1', $shippingCost, $order->getOrderCurrencyCode());
        }

        if ($taxCost > 0) {
            $items[] = $this->getItemArray(++$idx, null, 'Tax', '', '1', '1', $taxCost, $order->getOrderCurrencyCode());
        }

        if ($discount < 0) {
            $items[] = $this->getItemArray(++$idx, null, 'Discount', '', '1', '1', $discount, $order->getOrderCurrencyCode(), true);
        }
        return $items;
    }

    /**
     * @param Order\Item $item
     * @return float
     */
    protected function getWeeeAppliedTaxAmount($item)
    {
        if (!method_exists($item, 'getWeeeTaxApplied')
            || !method_exists($item, 'getWeeeTaxAppliedAmount')) {
            return 0;
        }
        return $item->getWeeeTaxApplied() ? $item->getWeeeTaxAppliedAmount() : 0;
    }

    /**
     * Get item parameters
     *
     * @param $idx
     * @param null $code
     * @param $name
     * @param $description
     * @param $qty
     * @param $digital
     * @param $price
     * @param $currency
     * @param bool $discount
     * @param bool $rebill
     * @return array
     */
    private function getItemArray($idx, $code = null, $name, $description, $qty, $digital, $price, $currency, $discount = false, $rebill = false)
    {
        if (in_array($currency, Constants::$NonDecimalCurrencies)) {
            $parts = explode('.', $price);
            $price = $parts[0];
        } else {
            $price = sprintf('%.02f', $price);
        }

        $prefix = sprintf('item_%d', $idx);
        $ret = [
            $prefix . '_name' => $name,
            $prefix . '_description' => $description,
            $prefix . '_qty' => $qty,
            $prefix . '_digital' => $digital,
            $prefix . '_unit_price_' . $currency => $price,
        ];

        if (isset($code)) {
            $ret[$prefix . '_code'] = $code;
        }

        if ($discount) {
            $ret[$prefix . '_discount'] = '1';
        }

        if ($rebill) {
            $ret[$prefix . '_rebill'] = Constants::REBILL_TYPE_MERCHANT_MANAGED;
        }

        return $ret;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    /**
     * Get configuration parameters
     *
     * @param Order $order
     * @return array
     */
    protected function getGatewayParameters(Order $order)
    {
        if ($this->_isUsePopup) {
            return [
                'client_id' => $this->getIPGPAYConfig('account_id'),
                'notification_url' => $this->_url->getUrl('ipgpay/notification/handle'),
                'test_transaction' => $this->getIPGPAYConfig('test_mode') == '1' ? '1' : '0',
                'order_reference' => $order->getIncrementId(),
                'order_currency' => $order->getOrderCurrencyCode(),
                'form_id' => $this->getIPGPAYConfig('payment_form_id'),
                'merchant_name' => $this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE),
                'create_customer' => $this->getIPGPAYConfig('create_customers') == '1' ? '1' : '0',
            ];
        } else {
            return [
                'client_id' => $this->getIPGPAYConfig('account_id'),
                'return_url' => $this->_url->getUrl('ipgpay/land/returns'),
                'approval_url' => $this->_url->getUrl('ipgpay/land/success'),
                'decline_url' => $this->_url->getUrl('ipgpay/land/decline'),
                'notification_url' => $this->_url->getUrl('ipgpay/notification/handle'),
                'test_transaction' => $this->getIPGPAYConfig('test_mode') == '1' ? '1' : '0',
                'order_reference' => $order->getIncrementId(),
                'order_currency' => $order->getOrderCurrencyCode(),
                'form_id' => $this->getIPGPAYConfig('payment_form_id'),
                'merchant_name' => $this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE),
                'create_customer' => $this->getIPGPAYConfig('create_customers') == '1' ? '1' : '0',
            ];
        }
    }

    /**
     * Get form fields for redirection to the payment form
     *
     * @param $customerParameters
     * @param $orderItemsParameters
     * @param $gatewayParameters
     * @return array
     */
    protected function mergeFormParameters($customerParameters, $orderItemsParameters, $gatewayParameters)
    {
        $fields = [];
        foreach ($gatewayParameters as $field => $value) {
            $fields[$field] = $value;
        }

        foreach ($customerParameters as $field => $value) {
            $fields[$field] = $value;
        }

        foreach ($orderItemsParameters as $item) {
            foreach ($item as $field => $value) {
                $fields[$field] = $value;
            }
        }

        $signatureLifetime = $this->getIPGPAYConfig('request_expiry');
        if (!$signatureLifetime) {
            $signatureLifetime = Config::DEFAULT_SIGNATURE_LIFETIME;
        }
        if (!$this->_isUsePopup) {
            $fields['PS_EXPIRETIME'] = time() + 3600 * $signatureLifetime;
            $fields['PS_SIGTYPE'] = Config::SIGNATURE_TYPE;
            $fields['PS_SIGNATURE'] = Functions::createSignature($fields, $this->getIPGPAYConfig('secret_key'));
        }
        return $fields;
    }

    /**
     * @var string $key
     * @return string
     */
    protected function getIPGPAYConfig($key)
    {
        return $this->_scopeConfig->getValue("payment/ipgpay_ipgpaymagento2/$key", ScopeInterface::SCOPE_STORE);
    }
}
