<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2017 Amasty (https://www.amasty.com)
 * @package Amasty_Meta
 */

namespace Amasty\Meta\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const ROBOTS_INDEX_FOLLOW     = 1;
    const ROBOTS_NOINDEX_FOLLOW   = 2;
    const ROBOTS_INDEX_NOFOLLOW   = 3;
    const ROBOTS_NOINDEX_NOFOLLOW = 4;

    const CONFIG_MAX_META_DESCRIPTION = 'ammeta/general/max_meta_description';
    const CONFIG_MAX_META_TITLE       = 'ammeta/general/max_meta_title';

    const DEFAULT_CHARSET = 'utf8';

    /** @var \Amasty\Meta\Model\Config */
    protected $_configByUrl = null;
    protected $_configs = null;

    protected $_cache;

    protected $_entityCollection = array();

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $http;

    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $category;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Catalog\Helper\Data
     */
    protected $catalogHelper;

    protected $taxHelper;

    /**
     * @var \Magento\Directory\Model\PriceCurrency
     */
    protected $PriceCurrency;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    private $context;
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfigInterface;
    /**
     * @var \Magento\Framework\HTTP\Header
     */
    private $header;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\App\Request\Http $http,
        \Magento\Catalog\Model\Category $category,
        \Magento\Catalog\Helper\Data $catalogHelper,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Directory\Model\PriceCurrency $PriceCurrency,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\ObjectManagerInterface $objectManagerInterface
    ) {
        $this->storeManager = $storeManagerInterface;
        $this->http = $http;
        $this->PriceCurrency = $PriceCurrency;
        $this->category = $category;
        $this->scopeConfig = $context->getScopeConfig();
        $this->taxHelper = $taxHelper;
        $this->catalogHelper = $catalogHelper;
        $this->_objectManager = $objectManagerInterface;
        parent::__construct($context);
        $this->context = $context;
        $this->registry = $registry;
        $this->scopeConfigInterface = $context->getScopeConfig();
        $this->header = $context->getHttpHeader();
    }

    /**
     * Get config by url
     *
     * @return \Amasty\Meta\Model\Config
     */
    public function getMetaConfigByUrl($path = '')
    {
        if (is_null($this->_configByUrl)) {
            $storeId = $this->storeManager->getStore()->getId();
            if ($path) {
                $urls = array($path);
            } else {
                $request = new \Zend_Controller_Request_Http();
                $rawPath = $request->setRequestUri()->getRequestUri();

                $pathInfo = $this->http->getOriginalPathInfo();

                $urls = array($pathInfo, $rawPath);
            }

            $om = \Magento\Framework\App\ObjectManager::getInstance();
            $config = $om->get('Amasty\Meta\Model\Config');

            $data = $config->getConfigByUrl($urls, $storeId);
            if (!empty($data)) {
                $data               = $data->getData();
                $this->_configByUrl = array();

                $customUrlMapping = $this->getUrlColumnsMapping();

                foreach ($data as $item) {
                    foreach ($customUrlMapping as $attrCode => $column) {
                        if (! isset($this->_configByUrl[$attrCode])
                            && ! empty($item[$column])
                            && trim($item[$column]) != ''
                        ) {
                            if ($column == 'custom_robots') {
                                foreach ($this->getRobotOptions() as $itemRobot) {
                                    if ($itemRobot['value'] == $item[$column]) {
                                        $item[$column] = $itemRobot['label'];
                                        break;
                                    }
                                }
                            }

                            if ($column == 'custom_meta_description') {
                                $item[$column] = mb_substr(
                                    $item[$column],
                                    0,
                                    $this->getMaxMetaDescriptionLength(),
                                    self::DEFAULT_CHARSET
                                );
                            }

                            if ($column == 'custom_meta_title') {
                                $item[$column] = mb_substr(
                                    $item[$column],
                                    0,
                                    $this->getMaxMetaTitleLength(),
                                    self::DEFAULT_CHARSET
                                );
                            }

                            $this->_configByUrl[$attrCode] = $item[$column];
                        }
                    }
                }
            } else {
                $this->_configByUrl = false;
            }
        }

        return $this->_configByUrl;
    }

    /**
     *  Parses template wth optional parts, uses _parseAttributes
     *
     * @param $tpl
     *
     * @return mixed|string
     */
    public function parse($tpl, $isUrl = false)
    {
        // replase attribute values if possible
        $tpl = $this->_parseAttributes($tpl, $isUrl);

        // handle optional parts
        $tpl = preg_replace_callback(
            '/\[.*?\]/',
            function($m) { if(strpos($m[0], "}") === true) return ""; return substr($m[0],1,-1); },
            $tpl
        );

        // remove non-processed variables
        $tpl = preg_replace('/{([a-z\_\|0-9]+)}/', '', $tpl);

        return $tpl;
    }


    /**
     *  Parses template and insert attribute values
     *
     * @param $tpl
     *
     * @return mixed
     */
    protected function _parseAttributes($tpl, $isUrl)
    {
        $vars = array();
        preg_match_all('/{([a-z\_\|0-9]+)}/', $tpl, $vars);
        if (! $vars[1]) {
            return $tpl;
        }
        $vars = $vars[1];

        foreach ($vars as $codes) {
            $value = '';
            foreach (explode('|', $codes) as $code) {
                foreach ($this->_entityCollection as $object) {
                    $value = $this->_getValue($object, $code, $isUrl);
                    if ($value) {
                        break 2; // we have found the first non-empty occurense.
                    }
                }
            }
            if ($value)
                $tpl = str_replace('{' . $codes . '}', $value, $tpl);
        }

        return $tpl;
    }

    /**
     * Gets attribute value by its code. Support custom params, see manual for details
     *
     * @param $p
     * @param $code
     *
     * @return mixed|null|string
     */
    protected function _getValue($p, $code, $isUrl)
    {
        $value = $code;
        $store = null;

        if ($p instanceof \Magento\Catalog\Model\Product || $p instanceof \Magento\Catalog\Model\Category) {
            $value = $this->_getValueByProduct($p, $code);
            $store = $this->storeManager->getStore($p->getStoreId());
        } else {
            $value = $p->getData($code);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $value = (string)$value;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        // remove tags
        $value = strip_tags($value);
        // remove spases
        $value = preg_replace('/\r?\n/', ' ', $value);
        $value = preg_replace('/\s{2,}/', ' ', $value);

        if ($isUrl) {
            $value = $this->transliterate($value);
            $value = str_replace('+', 'plus', $value);
            $value = preg_replace('#[^\w\s\d-_.~/]#', '', $value);
        } else {
            // convert possible special codes like '<' to safe html codes
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($value);
        }
        // check if price = 0.00
        if ($store && $value === $this->PriceCurrency->convert(0, true, false)) {
            $value = '';
        }

        return $value;
    }

    protected function _getProductCategory($p)
    {
        if ($p instanceof \Magento\Catalog\Model\Category)
            return $p;

        $collection = $p->getCategoryCollection();
        $collection->getSelect()->order('LENGTH(path) DESC');

        $categoryItems = $collection->load()->getIterator();
        $category      = current($categoryItems);
        if ($category) {
            $category = $this->category->load($category->getId());
            return $category;
        }
        return false;
    }

    protected function getCategoryValue($p)
    {
        $separator = (string) $this->scopeConfig->getValue('catalog/seo/title_separator');
        $separator = ' ' . $separator . ' ';
        $title     = array();

        if ($this->scopeConfig->getValue('ammeta/product/no_breadcrumbs')) {
            $categoryIds = $p->getCategoryIds();
            if ($categoryIds) {
                $categoryCollection = $this->category->getCollection();
                $categoryCollection->addIdFilter($categoryIds);
                $categoryCollection->addNameToResult();
                if ($categoryCollection->getSize() > 0) {
                    foreach ($categoryCollection as $cat) {
                        $title[] = $cat->getName();
                    }
                }
            }
            $value = join($separator, $title);
        } else {
            $path = $this->catalogHelper->getBreadcrumbPath();
            foreach ($path as $breadcrumb) {
                $title[] = $breadcrumb['label'];
            }
            array_pop($title);
            $value = join($separator, array_reverse($title));
        }
        return $value;
    }

    protected function _getValueByProduct($p, $code)
    {
        if ($p instanceof \Magento\Catalog\Model\Category)
            return $p->getData($code);

        $store = $this->storeManager->getStore($p->getStoreId());

        switch ($code) {
            case 'category':
                $value    = '';
                $category = $this->_getProductCategory($p);
                if ($category) {
                    $value = $category->getName();
                }
                break;
            case 'parent_category':
                $value    = '';
                /** @var \Magento\Catalog\Model\Category $category */

                $category = $this->_getProductCategory($p);

                if ($category) {
                    $value = $category->getParentCategory()->getName();
                }
                break;
            case 'categories':
                $value = $this->getCategoryValue($p);
                break;
            case 'store_view':
                $value = $store->getName();
                break;
            case 'store':
                $value = $store->getGroup()->getName();
                break;
            case 'website':
                $value = $store->getWebsite()->getName();
                break;
            case 'domain':
                $value = $this->header->getHttpHost();
                break;
            case 'price':
                $value = $this->PriceCurrency->convert($p->getPrice(), true, false);
                break;
            case 'special_price':
                $value = $this->PriceCurrency->convert($p->getData($code), true, false);
                break;
            case 'final_price':
                $value = $this->PriceCurrency->convert($this->taxHelper->getPrice($p, $p->getFinalPrice()), true, false);
                break;
            case 'final_price_incl_tax':
                $value = $this->PriceCurrency->convert($this->taxHelper->getPrice($p, $p->getFinalPrice(), true), true, false
                );
                break;
            case 'startingfrom_price':
                $minimalPrice = $this->_getMinimalPrice($p);
                $value        = $this->PriceCurrency->convert($minimalPrice, true, false);
                break;
            case 'startingto_price':
                $maximalPrice = $this->_getMaximalPrice($p);
                $value        = $this->PriceCurrency->convert($maximalPrice, true, false);
                break;

            case 'current_page':
                $page  = $this->_getRequest()->getParam('p');
                $value = $page < 1 ? NULL : intVal($page);
                break;
            case 'category_ids':
                $value = implode(',', $p->getData($code));
                break;
            case 'custom_design':
                $value = '';
                break;
            default:
                $value = $this->getDefaultValue($p, $code);

        } // end switch

        return $value;
    }

    protected function getDefaultValue($p, $code)
    {
        if (!$code)
            return '';

        $value = $p->getData($code);
        if (is_numeric($value)) {
            // flat enabled
            if ($p->getData($code . '_value')) {
                $value = $p->getData($code . '_value');
            } else {
                $attr = $p->getResource()->getAttribute($code);
                if ($attr) { // type dropdown
                    $attr->setStoreId($p->getStoreId());
                    $optionText = $attr->getSource()->getOptionText($value);
                    $value      = $optionText ? $optionText : $value;
                }
            }
        } elseif(is_array($value)) {
            //что делать если массив?
        } elseif (preg_match('/^[0-9,]+$/', $value)) {
            $attr = $p->getResource()->getAttribute($code);
            if ($attr) {
                $ids   = explode(',', $value);
                $value = '';
                foreach ($ids as $id) {
                    $value .= $attr->getSource()->getOptionText($id) . ', ';
                }
                $value = substr($value, 0, - 2);
            }
        }
        return $value;
    }

    /**
     * Genarates tree of all categories
     *
     * @return array sorted list category_id=>title
     */
    public function getTree($asHash = false)
    {
        $tree   = array();

        $collection = $this->category
            ->getCollection()->addNameToResult();

        $pos = array();
        foreach ($collection as $cat) {
            $path = explode('/', $cat->getPath());
            if ($cat->getLevel()) {
                $tree[$cat->getId()] = array(
                    'label' => str_repeat('--', $cat->getLevel()) . $cat->getName(),
                    'value' => $cat->getId(),
                    'path'  => $path,
                );
            }
            $pos[$cat->getId()] = $cat->getPosition();
        }

        foreach ($tree as $catId => $cat) {
            $order = array();
            foreach ($cat['path'] as $id) {
                if (isset($pos[$id])) {
                    $order[] = $pos[$id];
                }
            }
            $tree[$catId]['order'] = $order;
        }

        usort($tree, array($this, 'compare'));
        if ($asHash) {
            $hash = array();
            foreach ($tree as $v) {
                $hash[$v['value']] = $v['label'];
            }
            $tree = $hash;
        }

        if (! empty($tree)) {
            reset($tree);
            $firstKey = key($tree);
            if ($asHash) {
                $firstElement = current($tree);
                $tree         = array(0 => $firstElement) + $tree;
                unset($tree[$firstKey]);
            } else {
                $tree[$firstKey]['value'] = 1;
            }
        }

        return $tree;
    }

    /**
     * Compares category data. Must be public as used as a callback value
     *
     * @param array $a
     * @param array $b
     *
     * @return int 0, 1 , or -1
     */
    public function compare($a, $b)
    {
        foreach ($a['path'] as $i => $id) {
            if (! isset($b['path'][$i])) {
                // B path is shorther then A, and values before were equal
                return 1;
            }
            if ($id != $b['path'][$i]) {
                // compare category positions at the same level
                $p  = isset($a['order'][$i]) ? $a['order'][$i] : 0;
                $p2 = isset($b['order'][$i]) ? $b['order'][$i] : 0;

                return ($p < $p2) ? - 1 : 1;
            }
        }

        // B path is longer or equal then A, and values before were equal
        return ($a['value'] == $b['value']) ? 0 : - 1;
    }

    protected function _getMinimalPrice($product)
    {
        $minimalPrice = $this->taxHelper->getPrice($product, $product->getMinimalPrice(), true);
        if ($product->isGrouped()) {
            $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
            foreach ($associatedProducts as $item) {
                $temp = $this->taxHelper->getPrice($item, $item->getFinalPrice(), true);
                if (is_null($minimalPrice) || $temp < $minimalPrice) {
                    $minimalPrice = $temp;
                }
            }
        }
        else if ($product->getTypeId() == 'bundle'){
            list($minimalPrice, $maximalPrice) = $product->getPriceModel()->getTotalPrices($product, null, null, false);
        }

        return $minimalPrice;
    }

    protected function _getMaximalPrice($product)
    {
        $maximalPrice = 0;
        if ($product->isGrouped()) {
            $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
            foreach ($associatedProducts as $item) {
                if ($qty = $item->getQty() * 1) {
                    $maximalPrice += $qty * $this->taxHelper->getPrice($item, $item->getFinalPrice(), true);
                } else {
                    $maximalPrice += $this->taxHelper->getPrice($item, $item->getFinalPrice(), true);
                }
            }
        }
        else if ($product->getTypeId() == 'bundle'){
            list($minimalPrice, $maximalPrice) = $product->getPriceModel()->getTotalPrices($product, null, null, false);
        }
        if (! $maximalPrice) {
            $maximalPrice = $this->taxHelper->getPrice($product, $product->getFinalPrice(), true);
        }

        return $maximalPrice;
    }

    public function getRobotOptions()
    {
        return array(
            array('label' => 'INDEX, FOLLOW', 'value' => self::ROBOTS_INDEX_FOLLOW),
            array('label' => 'NOINDEX, FOLLOW', 'value' => self::ROBOTS_NOINDEX_FOLLOW),
            array('label' => 'INDEX, NOFOLLOW', 'value' => self::ROBOTS_INDEX_NOFOLLOW),
            array('label' => 'NOINDEX, NOFOLLOW', 'value' => self::ROBOTS_NOINDEX_NOFOLLOW)
        );
    }

    public function getUrlColumnsMapping()
    {
        return array(
            'meta_title'           => 'custom_meta_title',
            'meta_description'     => 'custom_meta_description',
            'meta_keyword'         => 'custom_meta_keywords',
            'meta_keywords'        => 'custom_meta_keywords',
            'meta_robots'          => 'custom_robots',
            'custom_canonical_url' => 'custom_canonical_url',
            'h1_tag'               => 'custom_h1_tag'
        );
    }

    /**
     * @param $html
     * @param $newText
     *
     * @return $this
     */
    public function replaceH1Tag(&$html, $newText)
    {
        $html = preg_replace('/(\<h1.*?\>).+?(\<\/h1\>)/is', "\${1}" . $newText . '$2', $html);

        return $this;
    }

    /**
     * @param $html
     * @param array $attributes
     *
     * @return bool
     */
    public function replaceImageData(&$html, $attributes = array())
    {
        $domQuery = new \Zend_Dom_Query($html);
        $results  = $domQuery->query('.category-image img');

        if (! count($results)) {
            return false;
        }

        foreach ($results as $result) {
            foreach ($attributes as $tagName => $tagValue) {
                $result->setAttribute($tagName, $tagValue);
            }
            break;
        }

        $html = $results->getDocument()->saveHTML();
    }

    public function getMaxMetaDescriptionLength()
    {
        $value = (int) $this->scopeConfig->getValue(self::CONFIG_MAX_META_DESCRIPTION);

        return $value ? $value : 500;
    }

    public function getMaxMetaTitleLength()
    {
        $value = (int) $this->scopeConfig->getValue(self::CONFIG_MAX_META_TITLE);

        return $value ? $value : 250;
    }

    public function addEntityToCollection($object)
    {
        $this->_entityCollection[] = $object;
        return $this;
    }

    /**
     * @return $this
     */
    public function cleanEntityToCollection()
    {
        $this->_entityCollection = [];

        return $this;
    }

    public function transliterate($string)
    {
        $replace = array(
            "а"=>"a","А"=>"a",
            "б"=>"b","Б"=>"b",
            "в"=>"v","В"=>"v",
            "г"=>"g","Г"=>"g",
            "д"=>"d","Д"=>"d",
            "е"=>"e","Е"=>"e",
            "ж"=>"zh","Ж"=>"zh",
            "з"=>"z","З"=>"z",
            "и"=>"i","И"=>"i",
            "й"=>"y","Й"=>"y",
            "к"=>"k","К"=>"k",
            "л"=>"l","Л"=>"l",
            "м"=>"m","М"=>"m",
            "н"=>"n","Н"=>"n",
            "о"=>"o","О"=>"o",
            "п"=>"p","П"=>"p",
            "р"=>"r","Р"=>"r",
            "с"=>"s","С"=>"s",
            "т"=>"t","Т"=>"t",
            "у"=>"u","У"=>"u",
            "ф"=>"f","Ф"=>"f",
            "х"=>"h","Х"=>"h",
            "ц"=>"c","Ц"=>"c",
            "ч"=>"ch","Ч"=>"ch",
            "ш"=>"sh","Ш"=>"sh",
            "щ"=>"sch","Щ"=>"sch",
            "ъ"=>"","Ъ"=>"",
            "ы"=>"y","Ы"=>"y",
            "ь"=>"","Ь"=>"",
            "э"=>"e","Э"=>"e",
            "ю"=>"yu","Ю"=>"yu",
            "я"=>"ya","Я"=>"ya",
            "і"=>"i","І"=>"i",
            "ї"=>"yi","Ї"=>"yi",
            "є"=>"e","Є"=>"e",
            "Ä"=>"ae","ä"=>"ae",
            "Ü"=>"ue","ü"=>"ue",
            "Ö"=>"oe","ö"=>"oe",
            "ß"=>"ss"
        );

        return iconv("UTF-8", "UTF-8//IGNORE", strtr($string, $replace));
    }

    /**
     * @param $categoryPaths
     * @param $keys
     * @param string $startPrefix
     * @param null $cacheKey
     *
     * @return array
     */
    public function _getConfigData($categoryPaths, $keys, $startPrefix = 'cat_', $cacheKey = null)
    {
        if ($cacheKey && isset($this->_cache[$cacheKey])) {
            return $this->_cache[$cacheKey];
        }

        $configData =  $this->_objectManager->create('Amasty\Meta\Model\Config')->getRecursionConfigData(
            $categoryPaths,
            $this->storeManager->getStore()->getId()
        );

        if (!$configData)
            return array();

        $resultData = array();
        if ($cacheKey) {
            $this->_cache[$cacheKey] = & $resultData;
        }

        foreach ($keys as $keyName => $key) {

            if (is_numeric($keyName))
                $keyName = $key;

            foreach ($configData as $itemConfig) {

                if ($startPrefix == 'cat_')
                    $prefix = '';
                else
                    $prefix = $itemConfig->getOrder() == 0 ? '' : 'sub_';

                $prefix .= $startPrefix;

                if (! isset($resultData[$key]) && ! empty($itemConfig[$prefix . $keyName]) &&
                    trim(! empty($itemConfig[$prefix . $keyName])) != ''
                ) {

                    if ($key == 'meta_description') {
                        $itemConfig[$prefix . $keyName] =
                            mb_substr(
                                $itemConfig[$prefix . $keyName],
                                0,
                                $this->getMaxMetaDescriptionLength(),
                                self::DEFAULT_CHARSET
                            );
                    }

                    if ($key == 'meta_title') {
                        $itemConfig[$prefix . $keyName] =
                            mb_substr($itemConfig[$prefix . $keyName],
                                0,
                                $this->getMaxMetaTitleLength(),
                                self::DEFAULT_CHARSET
                            );
                    }

                    $resultData[$key] = $itemConfig[$prefix . $keyName];
                    break;
                }
            }
        }

        return $resultData;
    }

    public function getReplaceData($code)
    {
        $configFromUrl = $this->getMetaConfigByUrl();

        $forceOverwrite = false;

        $currentCategory = $this->registry->registry('current_category');
        $currentProduct = $this->registry->registry('current_product');

        $currentEntity = false;

        if (isset($currentCategory) && !isset($currentProduct)) {
            $currentEntity = $currentCategory;
            $forceOverwrite = $this->scopeConfigInterface->isSetFlag('ammeta/cat/force');
        } elseif (isset($currentProduct)) {
            $currentEntity = $currentProduct;
            $forceOverwrite = $this->scopeConfigInterface->isSetFlag('ammeta/product/force');
        }

        $replacedData = $this->registry->registry('ammeta_replaced_data');

        if ($currentEntity && trim($currentEntity->getData($code)) != ''
            && !$forceOverwrite
        ) {
            return false;
        }

        $data = '';

        if (! empty($configFromUrl[$code])) {
            $configFromUrl[$code] = $this->parse($configFromUrl[$code]);
            $data = $configFromUrl[$code];
        } else if ($replacedData && isset($replacedData[$code])) {
            $data = $replacedData[$code];
        }

        return $data;

    }

    public function observeProductPage($product)
    {
        if (! $product || !  $this->context->getScopeConfig()->getValue('ammeta/product/enabled')) {
            return false;
        }

        $catPaths = [];

        $categories = $product->getCategoryCollection();

        foreach ($categories as $category)
            $catPaths[] = array_reverse($category->getPathIds());

        // product attribute => template name
        $attributes = array(
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
            'meta_keywords' => 'meta_keywords',
            'short_description' => 'short_description',
            'description' => 'description',
            'h1_tag' => 'h1_tag'
        );

        $configFromUrl = $this->getMetaConfigByUrl();

        $configData = null;

        $resultData = [];

        $forceOverwrite = $this->context->getScopeConfig()->isSetFlag('ammeta/product/force');

        foreach ($attributes as $attrCode) {
            if (!$forceOverwrite && trim($product->getData($attrCode))) {
                continue;
            }

            $configItem = null;
            if (! empty($configFromUrl[$attrCode])) {
                $configItem = $configFromUrl[$attrCode];
            } else {
                if (! $configData) {
                    $configData = $this->_getConfigData($catPaths, $attributes, 'product_', 'pr');
                }

                if (! empty($configData[$attrCode])) {
                    $configItem = $configData[$attrCode];
                }
            }

            if ($configItem) {
                $this->addEntityToCollection($product);
                $tag = $this->parse($configItem);

                $max = (int) $this->scopeConfig->getValue('ammeta/general/max_' . $attrCode);
                if ($max) {
                    $tag = mb_substr(
                        $tag, 0, $max, Data::DEFAULT_CHARSET
                    );
                }
                $resultData[$attrCode] = $tag;
            }
        }

        $this->registry->register('ammeta_replaced_data', $resultData);

        return $resultData;
    }
}
