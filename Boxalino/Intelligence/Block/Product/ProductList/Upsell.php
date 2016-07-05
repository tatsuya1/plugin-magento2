<?php
namespace Boxalino\Intelligence\Block\Product\ProductList;
use Magento\Catalog\Block\Product\ProductList\Upsell as  MageUpsell;

/**
 * Class Upsell
 * @package Boxalino\Intelligence\Block\Product\ProductList
 */
class Upsell extends MageUpsell
{
    /**
     * @var string
     */
    protected $scopeStore = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

    /**
     * @var \Boxalino\Intelligence\Helper\P13n\Adapter
     */
    protected $p13nHelper;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Link\Product\CollectionFactory
     */
    protected $factory;

    /**
     * @var \Boxalino\Intelligence\Helper\Data
     */
    protected $bxHelperData;

    /**
     * Upsell constructor.
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Magento\Checkout\Model\ResourceModel\Cart $checkoutCart
     * @param \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Boxalino\Intelligence\Helper\Data $bxHelperData
     * @param \Magento\Catalog\Model\ResourceModel\Product\Link\Product\CollectionFactory $factory
     * @param \Boxalino\Intelligence\Helper\P13n\Adapter $p13nHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Checkout\Model\ResourceModel\Cart $checkoutCart,
        \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Module\Manager $moduleManager,
        \Boxalino\Intelligence\Helper\Data $bxHelperData,
        \Magento\Catalog\Model\ResourceModel\Product\Link\Product\CollectionFactory $factory,
        \Boxalino\Intelligence\Helper\P13n\Adapter $p13nHelper,
        array $data = []
    )
    {
        $this->bxHelperData = $bxHelperData;
        $this->p13nHelper = $p13nHelper;
        $this->factory = $factory;
        parent::__construct($context, $checkoutCart, $catalogProductVisibility, $checkoutSession, $moduleManager, $data);
    }

    /**
     * @return $this
     */
    protected function _prepareData()
    {
        if($this->bxHelperData->isUpsellEnabled()){
            $products = array($this->_coreRegistry->registry('product'));
            $config = $this->_scopeConfig->getValue('bxRecommendations/upsell',$this->scopeStore);
            $choiceId = (isset($config['widget']) && $config['widget'] != "") ? $config['widget'] : 'related';

            if(!$config['enabled']){
                return parent::_prepareData();
            }

            $recommendations = $this->p13nHelper->getRecommendation(
                'product',
                $choiceId,
                $config['min'],
                $config['max'],
                $products
            );

            $entity_ids =  $recommendations;

            $this->_itemCollection = $this->factory->create()
                ->addFieldToFilter('entity_id', $entity_ids)->addAttributeToSelect('*');

            $this->_itemCollection->setVisibility($this->_catalogProductVisibility->getVisibleInCatalogIds());

            $this->_itemCollection->load();

            /**
             * Updating collection with desired items
             */
//        $this->_eventManager->dispatch(
//            'catalog_product_upsell',
//            ['product' => $products, 'collection' => $this->_itemCollection, 'limit' => $config['max']]
//        );

            foreach ($this->_itemCollection as $product) {
                $product->setDoNotUseCategoryId(true);
            }

            return $this;
        }
        return parent::_prepareData();
    }
    
    public function getItems()
    {
        if (is_null($this->_items)) {
            $this->_items = $this->getItemCollection()->getItems();
        }
        return $this->_items;
    }

}