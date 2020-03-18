<?php
namespace MageApi\ProductFeed\Model;

use MageApi\ProductFeed\Api\ProductFeedInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductFeed implements ProductFeedInterface
{
    /**
     * @param $productcus
     * @return array Custom Attributes of single Product.
     * @api
     */

    public function getCustomAttribute($productcus)
    {
        $objectManager = ObjectManager::getInstance();
        $productFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\ProductFactory');
        $custom=[];
        foreach ($productcus->getCustomAttributes() as $oneattributes) {
            $attri=[];
            if ($oneattributes->getAttributeCode()=='size' || $oneattributes->getAttributeCode()=='color' || $oneattributes->getAttributeCode()=='shades' || $oneattributes->getAttributeCode()=='size_range') {
                $poductReource=$productFactory->create();
                $attribute = $poductReource->getAttribute($oneattributes->getAttributeCode());
                if ($attribute->usesSource()) {
                    $option_Text = $attribute->getSource()->getOptionText($oneattributes->getValue());
                    $attri['attribute_code']=$oneattributes->getAttributeCode();
                    $attri['value']=$option_Text;
                }
            } else {
                $attri['attribute_code']=$oneattributes->getAttributeCode();
                $attri['value']=$oneattributes->getValue();
            }
            $custom[]=$attri;
        }
        return $custom;
    }

    /**
     * @param $images
     * @return array All images of single Product.
     * @api
     */

    public function getImages($images)
    {
        $allimages=[];
        foreach ($images as $child) {
            $allimages[]=$child->getUrl();
        }
        return $allimages;
    }

    /**
     * @param $productcus
     * @return string Product in-stock or out-of-stock.
     * @api
     */

    public function getAvailability($productcus)
    {
        if ($productcus->isSaleable()==true) {
            return 'in stock';
        } else {
            return 'out of stock';
        }
    }

    /**
     * @param $catids
     * @return array Category names of single Product.
     * @api
     */

    public function getCategoryNames($catids)
    {
        $categoryRepository = ObjectManager::getInstance()->get(CategoryRepositoryInterface::class);
        $catname=[];
        foreach ($catids as $categoryId) {
            $singlecat = $categoryRepository->get($categoryId);
            $catname[]=$singlecat->getName();
        }
        return $catname;
    }

    /**
     * @param $product
     * @return array Variants of configurable Product.
     * @api
     */

    public function getConfigurable($product)
    {
        $objectManager =  ObjectManager::getInstance();
        $configProduct = $objectManager->create('Magento\Catalog\Model\Product')->load($product->getId());
        $data = $configProduct->getTypeInstance()->getConfigurableOptions($configProduct);
        $vari=[];
        $varient=[];
        $varmap=[];
        $j=0;
        $code = '';
        foreach ($data as $key => $attr) {
            $opp=[];
            foreach ($attr as $p) {
                $sku=$p['sku'];
                $Prod = $objectManager->create('Magento\Catalog\Model\ProductRepository')->get($sku);
                if ($j==0) {
                    $inner=[];
                    $inner[]=$p['value_index'];
                    $varmap[$Prod->getsku()][0]=$inner;
                    $varmap[$Prod->getsku()][1]=$Prod->getId();
                } elseif ($j>0) {
                    array_push($varmap[$Prod->getsku()][0], $p['value_index']);
                }

                $options=[];
                $code=$p['attribute_code'];
                $options['id'] = $p['value_index'];
                $options['value']=$p['option_title'];

                if (!in_array($p['value_index'], array_column($opp, 'id'))) {
                    $opp[]=$options;
                }
            }
            $varient['option_id']=(int)$key;
            $varient['title']= $code;
            $varient['map']= $opp;
            $vari[]=$varient;
            $j=$j+1;
        }
        return $vari;
    }

    /**
     * Returns Product List
     * @param $name
     * @return array List of all product info for Product Feed.
     * @throws NoSuchEntityException
     * @api
     */

    public function name($name)
    {
        /** @var CategoryRepositoryInterface $categoryRepository */
        $categoryRepository = ObjectManager::getInstance()->get(CategoryRepositoryInterface::class);
        $category = $categoryRepository->get($name);
        /** @var Collection $products */
        $products = $category->getProductCollection()->addAttributeToFilter('status', 1)->addAttributeToFilter('visibility', 4);
        //echo $products->count();
        //echo $products->getSelect();
        //exit;
        $links = [];
        $objectManager =  ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface'); 
        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        /** @var Product $product */
        foreach ($products->getItems() as $product) {
            $productcus = $productRepository->getById($product->getId());
            $images = $productcus->getMediaGalleryImages();
            $url= $productcus->getProductUrl();
            $urlarray=explode('/', $url);
            $slugval=explode('.', end($urlarray));
            $catids = $productcus->getCategoryIds();
            $link=[];
            $link['sku']=$productcus->getSku();
            $link['categories']=$productcus->getCategoryIds();
            $link['categories_names']=$this->getCategoryNames($catids);
            $link['name']= $productcus->getName();
            $link['images']=$this->getImages($images);
            $link['regular_price']=number_format($productcus->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue(), 2, '.', ',');
            $link['final_price']=number_format($productcus->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(), 2, '.', ',');
            $link['currency_code']=$storeManager->getStore()->getCurrentCurrencyCode(); 
            $link['slug']=current($slugval);
            $link['availability']=$this->getAvailability($productcus);
            $link['type']=$productcus->getTypeId();
            $link['id']=$product->getId();
            $link['createdAt']=$productcus->getCreatedAt();
            $link['updatedAt']=$productcus->getUpdatedAt();
            if ($productcus->getTypeId() == "configurable") {
                $link['variants']=$this->getConfigurable($product);
            }
            $link['customAttributes']=$this->getCustomAttribute($productcus);
            $links[] = $link;
        }
        return $links;
    }
}
