<?php
namespace Boxalino\Intelligence\Block\Product;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\DataObject\IdentityInterface;
use Boxalino\Intelligence\Helper\Data;
use Magento\Framework\Session\SessionManager;
use Magento\Catalog\Model\Product\ProductList\Toolbar;
use Magento\UrlRewrite\Helper\UrlRewrite;

/**
 * Class BxListProducts
 * @package Boxalino\Intelligence\Block\Product
 */
class BxListProducts extends ListProduct{
    
    /**
     * @var int
     */
    public static $number = 0;

    /**
     * @var \Boxalino\Intelligence\Helper\P13n\Adapter
     */
    protected $p13nHelper;

    /**
     * @var mixed
     */
    protected $queries;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;

    /**
     * @var Data
     */
    protected $bxHelperData;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Catalog\Helper\Category
     */
    protected $categoryHelper;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var \Magento\Catalog\Block\Category\View
     */
    protected $categoryViewBlock;

    /**
     * @var \Boxalino\Intelligence\Model\Collection
     */
    protected $bxListCollection;

    /**
     * @var \Magento\Framework\App\Action\Action
     */
    protected $_response;

    /**
     * BxListProducts constructor.
     * @param Context $context
     * @param \Magento\Framework\Data\Helper\PostHelper $postDataHelper
     * @param \Magento\Catalog\Model\Layer\Resolver $layerResolver
     * @param CategoryRepositoryInterface $categoryRepository
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param Data $bxHelperData
     * @param \Boxalino\Intelligence\Helper\P13n\Adapter $p13nHelper
     * @param \Magento\Framework\App\Action\Context $actionContext
     * @param \Magento\Framework\UrlFactory $urlFactory
     * @param \Magento\Catalog\Helper\Category $categoryHelper
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Catalog\Block\Category\View $categoryViewBlock
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Data\Helper\PostHelper $postDataHelper,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        CategoryRepositoryInterface $categoryRepository, 
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Boxalino\Intelligence\Helper\Data $bxHelperData,
        \Boxalino\Intelligence\Helper\P13n\Adapter $p13nHelper,
        \Magento\Framework\App\Action\Context $actionContext,
        \Magento\Framework\UrlFactory $urlFactory,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Block\Category\View $categoryViewBlock,
        array $data = []
    )
    {
        $this->_response = $actionContext->getResponse();
        $this->categoryViewBlock = $categoryViewBlock;
        $this->categoryFactory = $categoryFactory;
        $this->categoryHelper = $categoryHelper;
        $this->bxHelperData = $bxHelperData;
        $this->p13nHelper = $p13nHelper;
        $this->_url = $actionContext->getUrl();
        $this->_objectManager = $actionContext->getObjectManager();
        parent::__construct($context, $postDataHelper, $layerResolver, $categoryRepository, $urlHelper, $data);
    }

    /**
     * @return AbstractCollection|mixed
     */
    public function _getProductCollection()
    {
        
        try{
            if(count($this->bxListCollection) && !$this->p13nHelper->areThereSubPhrases()){
                return $this->bxListCollection;
            }

            $layer = $this->getLayer();
            $categoryLayer = $layer instanceof \Magento\Catalog\Model\Layer\Category;
            $searchLayer = $layer instanceof \Magento\Catalog\Model\Layer\Search;
            $contentMode = $categoryLayer ? $this->categoryViewBlock->isContentMode() : false;

            if(!$this->bxHelperData->isSearchEnabled() || $contentMode){
                return parent::_getProductCollection();
            }

            if($categoryLayer || $searchLayer){

                if(!$this->bxHelperData->isNavigationEnabled() && $categoryLayer){
                    return parent::_getProductCollection();
                }

                if($this->getRequest()->getParam('bx_category_id') && $categoryLayer) {
                    $selectedCategory = $this->categoryFactory->create()->load($this->getRequest()->getParam('bx_category_id'));

                    if($selectedCategory->getLevel() != 1){
                        $url = $selectedCategory->getUrl();
                        $bxParams = $this->checkForBxParams($this->getRequest()->getParams());
                        $this->_response->setRedirect($url . $bxParams);
                    }
                }

                $entity_ids = array();
                if($searchLayer) {
                    if ($this->p13nHelper->areThereSubPhrases()) {
                        $this->queries = $this->p13nHelper->getSubPhrasesQueries();
                        $entity_ids = $this->p13nHelper->getSubPhraseEntitiesIds($this->queries[self::$number]);
                        $entity_ids = array_slice($entity_ids,0,$this->bxHelperData->getSubPhrasesLimit());
                    }
                }

                if(empty($entity_ids)){
                    $entity_ids = $this->p13nHelper->getEntitiesIds();
                }

                // Added check if there are any entity ids, otherwise force empty result
                if ((count($entity_ids) == 0)) {
                    $entity_ids = array(0);
                }
                
                return $this->_setupCollection($entity_ids);
            }else{
                return parent::_getProductCollection();
            }
        }catch(\Exception $e){
            return parent::_getProductCollection();
        }
    }

    /**
     * @param $entity_ids
     * @return \Boxalino\Intelligence\Model\Collection
     */
    protected function _setupCollection($entity_ids){
        
        $list = $this->_objectManager->create('\\Boxalino\\Intelligence\\Model\\Collection');
        $list->setStoreId($this->_storeManager->getStore()->getId())
            ->addFieldToFilter('entity_id', $entity_ids)->addAttributeToSelect('*');
        $list->getSelect()->order(new \Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $entity_ids).')'));
        $list->load();

        $list->setCurBxPage($this->getToolbarBlock()->getCurrentPage());
        $limit = $this->getRequest()->getParam('product_list_limit') ? $this->getRequest()->getParam('product_list_limit') : $this->getToolbarBlock()->getDefaultPerPageValue();
        $totalHitCount = $this->p13nHelper->getTotalHitCount();

        if((ceil($totalHitCount / $limit) < $list->getCurPage()) && $this->getRequest()->getParam('p')){

            $url = $this->_url->getCurrentUrl();
            $url = preg_replace('/(\&|\?)p=+(\d|\z)/','$1p=1',$url);
            $this->_response->setRedirect($url);
        }

        $lastPage = ceil($totalHitCount /$limit);
        $list->setLastBxPage($lastPage);
        $list->setBxTotal($totalHitCount);
        $this->bxListCollection = $list;
        return $list;
    }
    
    /**
     * @param $params
     * @return string
     */
    private function checkForBxParams($params){
        
        $paramString = '';
        $first = true;
        foreach($params as $key => $param){
            if(preg_match("/^bx_/",$key)){
                if(preg_match("/^bx_category_id/",$key)){
                    continue;
                }
                if($first) {
                    $paramString = '?';
                    $first = false;
                }else{
                    $paramString = $paramString . "&";
                }
                $paramString = $paramString . $key . '=' . $param;
            }
        }
        return $paramString;
    }

    /**
     * @return $this
     */
    protected function _beforeToHtml(){
        
        $toolbar = $this->getToolbarBlock();

        // called prepare sortable parameters

        $collection = $this->_getProductCollection();

        // use sortable parameters
        $orders = $this->getAvailableOrders();
        if ($orders) {
            $toolbar->setAvailableOrders($orders);
        }

        $toolbar->setDefaultOrder('relevance');

        $dir = $this->getDefaultDirection();
        if ($dir) {
            $toolbar->setDefaultDirection($dir);
        }
        
        $modes = $this->getModes();
        if ($modes) {
            $toolbar->setModes($modes);
        }

        // set collection to toolbar and apply sort
        $toolbar->setCollection($collection);
        $this->setChild('toolbar', $toolbar);
        $this->_eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $this->_getProductCollection()]
        );

        $this->_getProductCollection()->load();
        return parent::_beforeToHtml();
    }
}