<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Emageshop\Agreements\Model;

use Magento\Checkout\Model\Cart;
use Magento\CheckoutAgreements\Api\CheckoutAgreementsListInterface;
use Magento\CheckoutAgreements\Api\CheckoutAgreementsRepositoryInterface;
use Magento\CheckoutAgreements\Model\AgreementsProvider;
use Magento\CheckoutAgreements\Model\Api\SearchCriteria\ActiveStoreAgreementsFilter;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Address\Config;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Escaper;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration provider for GiftMessage rendering on "Shipping Method" step of checkout.
 */
class AgreementsConfigProvider extends \Magento\CheckoutAgreements\Model\AgreementsConfigProvider
{

    private $checkoutAgreementsList;

    private Config $_addressConfig;

    private $activeStoreAgreementsFilter;

    private Cart $_cart;

    private Session $_customerSession;

    private CustomerRepositoryInterface $_customerRepository;

    private Data $_priceHelper;

    private AddressFactory $_addressFactory;

    private AgreementsFactory $_agreementsFactory;

    private RemoteAddress $_remoteAddress;

    private array $_content_params = [];

    public function __construct(
        ScopeConfigInterface $scopeConfiguration,
        CheckoutAgreementsRepositoryInterface $checkoutAgreementsRepository,
        Escaper $escaper,
        CheckoutAgreementsListInterface $checkoutAgreementsList,
        ActiveStoreAgreementsFilter $activeStoreAgreementsFilter,
        CustomerRepositoryInterface $customerRepository,
        Session $customerSession,
        Config $addressConfig,
        Data $priceHelper,
        AddressFactory $addressFactory,
        Cart $cart,
        AgreementsFactory $agreementsFactory,
        RemoteAddress $remoteAddress
    ) {
        parent::__construct(
            $scopeConfiguration,
            $checkoutAgreementsRepository,
            $escaper,
            $checkoutAgreementsList,
            $activeStoreAgreementsFilter
        );

        $this->_addressConfig      = $addressConfig;
        $this->_addressFactory     = $addressFactory;
        $this->_customerRepository = $customerRepository;
        $this->_customerSession    = $customerSession;
        $this->_cart               = $cart;
        $this->_agreementsFactory  = $agreementsFactory;
        $this->_priceHelper        = $priceHelper;
        $this->_remoteAddress      = $remoteAddress;

        $this->setParams($customerSession->getCustomer()->getId());

        $this->checkoutAgreementsList      = $checkoutAgreementsList ?: ObjectManager::getInstance()->get(
            CheckoutAgreementsListInterface::class
        );
        $this->activeStoreAgreementsFilter = $activeStoreAgreementsFilter ?: ObjectManager::getInstance()->get(
            ActiveStoreAgreementsFilter::class
        );
    }


    /**
     * Returns agreements config.
     *
     * @return array
     */
    protected function getAgreementsConfig()
    {
        $agreementConfiguration = [];
        $isAgreementsEnabled    = $this->scopeConfiguration->isSetFlag(
            AgreementsProvider::PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );

        $agreementsList                      = $this->checkoutAgreementsList->getList($this->activeStoreAgreementsFilter->buildSearchCriteria());
        $agreementConfiguration['isEnabled'] = (bool)($isAgreementsEnabled && count($agreementsList) > 0);

        foreach ($agreementsList as $agreement) {
            $content = $this->generateContent($agreement->getContent());
            $this->saveAgreement($content);
            $agreementConfiguration['agreements'][] = [
                'content'       => $agreement->getIsHtml() ? $content : nl2br($this->escaper->escapeHtml($content)),
                'checkboxText'  => $this->escaper->escapeHtml($agreement->getCheckboxText()),
                'mode'          => $agreement->getMode(),
                'agreementId'   => $agreement->getAgreementId(),
                'contentHeight' => $agreement->getContentHeight()
            ];
        }

        return $agreementConfiguration;
    }

    private function saveAgreement(&$content)
    {
        $quoteId = $this->_cart->getQuote()->getId();

        $agreementsFactory = $this->_agreementsFactory->create();
        $agreementsFactory = $agreementsFactory->load($quoteId, 'quote_id');
        if (!$agreementsFactory->getId()) {
            $agreementsFactory = $this->_agreementsFactory->create();
            $agreementDate     = date('d.m.Y H:i:s');
            $agreementCode     = md5($this->_customerSession->getCustomer()->getEmail() . $agreementDate);

            $agreementsFactory->setAgreementCode($agreementCode);
            $agreementsFactory->setCreatedAt($agreementDate);
        }

        $agreementsFactory->setCustomerId($this->_customerSession->getCustomer()->getId());
        $agreementsFactory->setQuoteId($quoteId);
        $agreementsFactory->setAgreementContent($content);
        $agreementsFactory->save();
    }

    /**
     * @param $items
     * @return string
     */
    private function createBasketItemsTable($items): string
    {
        $table = <<<HTML
            <table style="border:1px solid #000000;width:100%">
                <thead>
                    <tr>
                        <th>Ürün Kodu ve Adı</th>
                        <th>Adet</th>
                        <th>Birim Fiyatı</th>
                        <th>Toplam Tutar</th>
                    </tr>
                </thead>
                <tbody>
            HTML;

        $rowTotal = 0;
        foreach ($items as $item) {
            $rowTotal += $item->getRowTotal();
            $table    .= <<<HTML
                <tr>
                    <td>{$item->getName()}</td>
                    <td>{$item->getQty()}</td>
                    <td>{$this->_priceHelper->currency($item->getPrice(), true, false)}</td>
                    <td>{$this->_priceHelper->currency($item->getRowTotal(), true, false)}</td>
                </tr>
           HTML;
        }

        $table .= <<<HTML
                </tbody>
            </table>
            HTML;

        return $table;
    }

    private function generateContent($content)
    {
        foreach ($this->_content_params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    $content = str_replace("#" . $key . "_" . $key2 . "#", $value2, $content);
                }
            } else {
                $content = str_replace("#$key#", $value, $content);
            }
        }

        return $content;
    }

    private function setParams($customerId)
    {
        $quote           = $this->_cart->getQuote();
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();

        $subTotal   = $this->_cart->getQuote()->getSubtotal();
        $grandTotal = $this->_cart->getQuote()->getGrandTotal();
        $allItems   = $this->_cart->getQuote()->getAllItems();

        $objectManager    = \Magento\Framework\App\ObjectManager::getInstance();
        $storeInformation = $objectManager->create('Magento\Store\Model\Information');
        $store            = $objectManager->create('Magento\Store\Model\Store');
        $storeInfo        = $storeInformation->getStoreInformationObject($store);

        $this->_content_params = [
            'BUYER_EMAIL'            => $this->_customerSession->getCustomer()->getEmail(),
            'BUYER_NAME'             => $this->_customerSession->getCustomer()->getName(),
            'BUYER_TELEPHONE'        => $shippingAddress->getTelephone(),
            'SHIPPING_METHOD'        => $shippingAddress->getShippingMethod(),
            'SHIPPING_AMOUNT'        => $this->_priceHelper->currency($shippingAddress->getShippingAmount(),
                true, false),
            'BUYER_SHIPPING_ADDRESS' => $this->removePhoneFromAddress($this->_addressConfig->getFormatByCode('html')->getRenderer()->renderArray($shippingAddress)),
            'BUYER_BILLING_ADDRESS'  => $this->removePhoneFromAddress($this->_addressConfig->getFormatByCode('html')->getRenderer()->renderArray($billingAddress)),
            'PAYMENT_METHOD'         => $quote->getPayment()->getMethodInstance("method_title")->getTitle(),
            'BASKET_ITEMS'           => $this->createBasketItemsTable($allItems),
            'SUB_TOTAL'              => $this->_priceHelper->currency($subTotal, true, false),
            'GRAND_TOTAL'            => $this->_priceHelper->currency($grandTotal, true, false),
        ];

        foreach ($storeInfo->getData() as $key => $val){
            $this->_content_params["STORE_" . strtoupper($key)] = $val;
        }
    }

    private function removePhoneFromAddress($address)
    {
        return preg_replace('/T:.*a>/i', '', $address);
    }
}