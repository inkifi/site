<?php

namespace Pektsekye\OptionExtended\Model\ResourceModel;

class Template extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

  protected $_selectTypes;


  protected $_objectManager;
  
  public function __construct(
      \Magento\Framework\Model\ResourceModel\Db\Context $context,
      \Magento\Framework\ObjectManagerInterface $objectManager,
      $resourcePrefix = null                     
  ) {
      $this->_objectManager = $objectManager;       
      parent::__construct($context, $resourcePrefix);
  }  


  public function _construct()
  {    
      $this->_init('optionextended_template', 'template_id');

      $this->_selectTypes = array(
	      \Magento\Catalog\Model\Product\Option::OPTION_TYPE_DROP_DOWN => 1,
	      \Magento\Catalog\Model\Product\Option::OPTION_TYPE_RADIO => 1,
	      \Magento\Catalog\Model\Product\Option::OPTION_TYPE_CHECKBOX => 1,
	      \Magento\Catalog\Model\Product\Option::OPTION_TYPE_MULTIPLE => 1,					
      );
  }
  
  
  protected function _initUniqueFields()
  {
      $this->_uniqueFields = array(array(
          'field' => 'code',
          'title' => __('Template with the same code')
      ));
      return $this;
  }
 

  protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
  {
    if (!is_null($object->getProductIds())){
      $this->getConnection()->delete($this->getTable('optionextended_product_template'), 'template_id='. $object->getId());

      foreach ($object->getProductIds() as $id) {
        if (!empty($id)) {
          $this->getConnection()->insert($this->getTable('optionextended_product_template'), array(
            'template_id' => $object->getId(),
            'product_id'  => $id
          ));
        }
      } 
    }     
    return $this;    
  } 


  public function getProductIds($templateId)
  {
      return $this->getConnection()->fetchCol($this->getConnection()->select()
          ->from($this->getTable('optionextended_product_template'), 'product_id')
          ->where("template_id = ?", $templateId)
      );
  }  



  public function getAppliedTemplates($productId)
  {
      return $this->getConnection()->fetchAll($this->getConnection()->select()
          ->from(array('product_template_table'=>$this->getTable('optionextended_product_template')), 'template_id')
          ->join(array('template_table'=>$this->getTable('optionextended_template')), 'template_table.template_id = product_template_table.template_id', 'title')                       
          ->where("product_id = ?", $productId)
          ->order('title')
      );
  } 



  public function getTemplatesAsOptionArray($productId)
  {
      return $this->getConnection()->fetchAll($this->getConnection()->select()
          ->from($this->getTable('optionextended_template'), array('label' => 'title', 'value' => 'template_id'))
          ->order('title')
      );
  }    



  public function updateProductTemplates($productId, $templateIds)
  {
    $this->getConnection()->delete($this->getTable('optionextended_product_template'), 'product_id='. (int) $productId);
 
    $templateIds = $this->getConnection()->fetchCol($this->getConnection()->select()
        ->from($this->getTable('optionextended_template'), 'template_id')
        ->where('template_id IN (?)', $templateIds)
    );               

    foreach ($templateIds as $id) {
      $this->getConnection()->insert($this->getTable('optionextended_product_template'), array(
        'template_id' => $id,
        'product_id'  => $productId
      ));
    } 
   
    return $this;
  }


  public function applyOptionTemplatesToDuplicatedProduct($productId, $newProductId)
  {     
    $table = $this->getTable('optionextended_product_template');
    $sql = "REPLACE INTO `{$table}` 
      SELECT {$newProductId}, `template_id`
      FROM `{$table}` WHERE `product_id`={$productId}";
		$this->getConnection()->query($sql);
  }




  public function getTemplateOptionsCollection($productId, $storeId)
  {

      $templateTable = $this->getTable('optionextended_template');          
      $productTemplateTable = $this->getTable('optionextended_product_template');
      $optionTable = $this->getTable('optionextended_template_option');                    
      $titleTable = $this->getTable('optionextended_template_option_title');
      $priceTable = $this->getTable('optionextended_template_option_price');
	    $noteTable = $this->getTable('optionextended_template_option_note');
	       		       
      $optionRows = $this->getConnection()->fetchAll($this->getConnection()->select()
          ->from(array('template_table'=>$templateTable), array())      
          ->join(array('product_template_table'=>$productTemplateTable), "product_template_table.template_id = template_table.template_id", array())
          ->join(array('option_table'=>$optionTable), 
          "option_table.template_id = template_table.template_id", 
          array('*','id' => 'option_table.option_id', 'option_id' => 'option_table.option_id'))            

          ->join(array('default_title_table'=>$titleTable),
           "default_title_table.option_id=option_table.option_id AND default_title_table.store_id=0", array())            
          ->joinLeft(array('store_title_table'=>$titleTable),
              "store_title_table.option_id=default_title_table.option_id AND store_title_table.store_id={$storeId}",
              array('store_title' => 'title', 'title' => new \Zend_Db_Expr('IFNULL(store_title_table.title, default_title_table.title)')))
              
          ->joinLeft(array('default_price_table' => $priceTable),
              "default_price_table.option_id=option_table.option_id AND default_price_table.store_id=0",array())
          ->joinLeft(array('store_price_table' => $priceTable),
              "store_price_table.option_id=default_price_table.option_id AND store_price_table.store_id={$storeId}",
              array('store_price' => 'price', 'price' => new \Zend_Db_Expr('IFNULL(store_price_table.price, default_price_table.price)'), 'price_type' => new \Zend_Db_Expr('IFNULL(store_price_table.price_type, default_price_table.price_type)')))
              
          ->join(array('default_note_table' => $noteTable),
              "default_note_table.option_id=option_table.option_id AND default_note_table.store_id=0",array())
          ->joinLeft(array('store_note_table' => $noteTable),
              "store_note_table.option_id=default_note_table.option_id AND store_note_table.store_id={$storeId}",
              array('store_note' => 'note', 'note' => new \Zend_Db_Expr('IFNULL(store_note_table.note, default_note_table.note)')))                                
                                  
          ->where("template_table.is_active = 1 AND product_template_table.product_id = ?", $productId)
          ->order('sort_order ASC')
          ->order('title ASC'));

      
      $valueTable = $this->getTable('optionextended_template_value');
      $titleTable = $this->getTable('optionextended_template_value_title');
      $priceTable = $this->getTable('optionextended_template_value_price');
	    $descriptionTable = $this->getTable('optionextended_template_value_description');
	    
      $valueRows = $this->getConnection()->fetchAll($this->getConnection()->select()
          ->from(array('template_table'=>$templateTable), array())      
          ->join(array('product_template_table'=>$productTemplateTable), "product_template_table.template_id = template_table.template_id", array())
          ->join(array('option_table'=>$optionTable), 
            "option_table.template_id = template_table.template_id", array('template_id'))            
          ->join(array('value_table'=>$valueTable),
            "value_table.option_id=option_table.option_id ",
             array('*', 'option_id' => 'value_table.option_id', 'id' => 'value_table.value_id', 'option_type_id' => 'value_table.value_id'))  
            
          ->join(array('default_title_table'=>$titleTable),
           "default_title_table.value_id=value_table.value_id AND default_title_table.store_id=0", array())            
          ->joinLeft(array('store_title_table'=>$titleTable),
              "store_title_table.value_id=default_title_table.value_id AND store_title_table.store_id={$storeId}",
              array('store_title' => 'title', 'title' => new \Zend_Db_Expr('IFNULL(store_title_table.title, default_title_table.title)')))
              
          ->join(array('default_price_table' => $priceTable),
              "default_price_table.value_id=value_table.value_id AND default_price_table.store_id=0",array())
          ->joinLeft(array('store_price_table' => $priceTable),
              "store_price_table.value_id=default_price_table.value_id AND store_price_table.store_id={$storeId}",
              array('store_price' => 'price', 'price' => new \Zend_Db_Expr('IFNULL(store_price_table.price, default_price_table.price)'), 'price_type' => new \Zend_Db_Expr('IFNULL(store_price_table.price_type, default_price_table.price_type)')))
              
          ->join(array('default_description_table' => $descriptionTable),
              "default_description_table.value_id=value_table.value_id AND default_description_table.store_id=0",array())
          ->joinLeft(array('store_description_table' => $descriptionTable),
              "store_description_table.value_id=default_description_table.value_id AND store_description_table.store_id={$storeId}",
              array('store_description' => 'description', 'description' => new \Zend_Db_Expr('IFNULL(store_description_table.description, default_description_table.description)')))                                
                                  
          ->where("template_table.is_active = 1 AND product_template_table.product_id = ?", $productId)
          ->order('sort_order ASC')
          ->order('title ASC'));

      $t = array();
      foreach ($optionRows as $row)
        $t[$row['option_id']] = $row;
          
      $tt = array();
      foreach ($valueRows as $row){
        $item = $this->_objectManager->create('Magento\Catalog\Model\Product\Option\Value');//$this->_objectManager->get
        $tt[$row['option_id']][$row['value_id']] = $item->addData($row);
      }  
        
      $options = array();        
      foreach ($t as $k => $v){
        $item = $this->_objectManager->create('Magento\Catalog\Model\Product\Option');
        $item->setIsTemplateOption(true);        
        $options[$k] = $item->addData($v);
        if (isset($this->_selectTypes[$options[$k]->getType()])){
          if (isset($tt[$k])){
            foreach ($tt[$k] as $vv){
              $vv->setOption($options[$k]);
              $options[$k]->addValue($vv);
            }  
          } else {
            unset($options[$k]);
          }  
        }
      }
               
      return $options;
  }  




  public function getTemplateData($templateId, $storeId)
  {
  
      $optionTable = $this->getTable('optionextended_template_option');                    
      $titleTable = $this->getTable('optionextended_template_option_title');
      $priceTable = $this->getTable('optionextended_template_option_price');
	    $noteTable = $this->getTable('optionextended_template_option_note');
	       
      $optionRows = $this->getConnection()->fetchAll($this->getConnection()->select()
          ->from(array('option_table'=>$optionTable))            

          ->join(array('default_title_table'=>$titleTable),
           "default_title_table.option_id=option_table.option_id AND default_title_table.store_id=0", array())            
          ->joinLeft(array('store_title_table'=>$titleTable),
              "store_title_table.option_id=default_title_table.option_id AND store_title_table.store_id={$storeId}",
              array('store_title' => 'title', 'title' => new \Zend_Db_Expr('IFNULL(store_title_table.title, default_title_table.title)')))
              
          ->joinLeft(array('default_price_table' => $priceTable),
              "default_price_table.option_id=option_table.option_id AND default_price_table.store_id=0",array())
          ->joinLeft(array('store_price_table' => $priceTable),
              "store_price_table.option_id=default_price_table.option_id AND store_price_table.store_id={$storeId}",
              array('store_price' => 'price', 'price' => new \Zend_Db_Expr('IFNULL(store_price_table.price, default_price_table.price)'), 'price_type' => new \Zend_Db_Expr('IFNULL(store_price_table.price_type, default_price_table.price_type)')))
              
          ->join(array('default_note_table' => $noteTable),
              "default_note_table.option_id=option_table.option_id AND default_note_table.store_id=0",array())
          ->joinLeft(array('store_note_table' => $noteTable),
              "store_note_table.option_id=default_note_table.option_id AND store_note_table.store_id={$storeId}",
              array('store_note' => 'note', 'note' => new \Zend_Db_Expr('IFNULL(store_note_table.note, default_note_table.note)')))                                
                                  
          ->where("template_id = ?", $templateId)
          ->order('sort_order ASC')
          ->order('title ASC'));

      
      $valueTable = $this->getTable('optionextended_template_value');
      $titleTable = $this->getTable('optionextended_template_value_title');
      $priceTable = $this->getTable('optionextended_template_value_price');
	    $descriptionTable = $this->getTable('optionextended_template_value_description');
	    
      $valueRows = $this->getConnection()->fetchAll($this->getConnection()->select()
          ->from(array('option_table'=>$optionTable), array())            
          ->join(array('value_table'=>$valueTable),
            "value_table.option_id=option_table.option_id ")  
            
          ->join(array('default_title_table'=>$titleTable),
           "default_title_table.value_id=value_table.value_id AND default_title_table.store_id=0", array())            
          ->joinLeft(array('store_title_table'=>$titleTable),
              "store_title_table.value_id=default_title_table.value_id AND store_title_table.store_id={$storeId}",
              array('store_title' => 'title', 'title' => new \Zend_Db_Expr('IFNULL(store_title_table.title, default_title_table.title)')))
              
          ->join(array('default_price_table' => $priceTable),
              "default_price_table.value_id=value_table.value_id AND default_price_table.store_id=0",array())
          ->joinLeft(array('store_price_table' => $priceTable),
              "store_price_table.value_id=default_price_table.value_id AND store_price_table.store_id={$storeId}",
              array('store_price' => 'price', 'price' => new \Zend_Db_Expr('IFNULL(store_price_table.price, default_price_table.price)'), 'price_type' => new \Zend_Db_Expr('IFNULL(store_price_table.price_type, default_price_table.price_type)')))
              
          ->join(array('default_description_table' => $descriptionTable),
              "default_description_table.value_id=value_table.value_id AND default_description_table.store_id=0",array())
          ->joinLeft(array('store_description_table' => $descriptionTable),
              "store_description_table.value_id=default_description_table.value_id AND store_description_table.store_id={$storeId}",
              array('store_description' => 'description', 'description' => new \Zend_Db_Expr('IFNULL(store_description_table.description, default_description_table.description)')))                                
                                  
          ->where("template_id = ?", $templateId)
          ->order('sort_order ASC')
          ->order('title ASC'));

      $t = array();
      foreach ($optionRows as $row)
        $t[$row['option_id']] = $row;

      $tt = array();
      foreach ($valueRows as $row)
        $tt[$row['option_id']][$row['value_id']] = new \Magento\Framework\DataObject($row);
        
      $options = array();        
      foreach ($t as $k => $v){
        $options[$k] = new \Magento\Framework\DataObject($v);
        if (isset($this->_selectTypes[$options[$k]->getType()])){
          if (isset($tt[$k]))
            $options[$k]->setValues($tt[$k]);
          else
            unset($options[$k]);
        }  
      }
            
      return $options;
  }           



  public function getHasOptions($templateId)
  {
    $result = $this->getConnection()->fetchOne($this->getConnection()->select()
            ->from($this->getTable('optionextended_template_option'), 'option_id')            
            ->where("template_id = ?", $templateId)
            ->limit(1)
          );
    return !empty($result);
  }


   public function duplicate($templateId)
  { 

		  $csT    = $this->getTable('store');
		  
      $oxtT    = $this->getTable('optionextended_template');           		  	     			
      $oxtoT   = $this->getTable('optionextended_template_option');
      $oxtotT  = $this->getTable('optionextended_template_option_title');
      $oxtopT  = $this->getTable('optionextended_template_option_price');
      $oxtonT  = $this->getTable('optionextended_template_option_note');                   
      $oxtvT   = $this->getTable('optionextended_template_value'); 
      $oxtvtT  = $this->getTable('optionextended_template_value_title');
      $oxtvpT  = $this->getTable('optionextended_template_value_price');
      $oxtvdT  = $this->getTable('optionextended_template_value_description');

      $r = $this->getConnection()->fetchRow("SHOW TABLE STATUS LIKE '{$oxtT}'");
      $newTemplateId = $r['Auto_increment'];

      $tResult = $this->getConnection()->fetchRow("
        SELECT *
        FROM `{$oxtT}`
        WHERE template_id={$templateId}    
      ");
      
      $code = 'template-'. $newTemplateId;
      $this->getConnection()->query("INSERT INTO `{$oxtT}` (`template_id`,`title`,`code`,`is_active`) VALUES ({$newTemplateId},{$this->getConnection()->quote($tResult['title'])},'{$code}',{$tResult['is_active']})");

      unset($tResult);
                                 			
      $oResult = $this->getConnection()->fetchAll("
        SELECT option_id,type,is_require,sku,max_characters,file_extension,image_size_x,image_size_y,sort_order,
               row_id,layout,popup,selected_by_default
        FROM `{$oxtoT}`
        WHERE template_id={$templateId}    
      ");
      
      $options = array();
      foreach ($oResult as $r){ 
        $maxCharacters   = !is_null($r['max_characters']) ? $r['max_characters'] : 'NULL';
        $fileExtension   = !is_null($r['file_extension']) ? $this->getConnection()->quote($r['file_extension']) : 'NULL';	          
        $imageSizeX      = (int) $r['image_size_x'];
        $imageSizeY      = (int) $r['image_size_y'];
        $rowId           = !is_null($r['row_id']) ? $r['row_id'] : 'NULL';               
        $options[$r['option_id']]  = "{$rowId},'{$r['type']}',{$r['is_require']},{$this->getConnection()->quote($r['sku'])},{$maxCharacters},{$fileExtension},{$imageSizeX},{$imageSizeY},{$r['sort_order']},'{$r['layout']}','{$r['popup']}','{$r['selected_by_default']}')"; 
      }     
      unset($oResult);
 
	    if (count($options) > 0){
          
        $otResult = $this->getConnection()->fetchAll("
          SELECT ox.option_id,cs.store_id,title,price,price_type,note
          FROM `{$oxtoT}` ox 
          JOIN `{$csT}` cs     
          LEFT JOIN  `{$oxtotT}` oxt 
            ON oxt.option_id = ox.option_id AND oxt.store_id = cs.store_id
          LEFT JOIN  `{$oxtopT}` oxp 
            ON oxp.option_id = ox.option_id AND oxp.store_id = cs.store_id
          LEFT JOIN  `{$oxtonT}` oxn 
            ON oxn.option_id = ox.option_id AND oxn.store_id = cs.store_id                           
          WHERE ox.template_id={$templateId}    
        ");

        $oTitles = array();
        $oPrices = array();
        $oNotes = array();

        foreach ($otResult as $r){
         if (!is_null($r['title']))     
          $oTitles[$r['option_id']][] = array('store_id'=>$r['store_id'], 'title'=>$this->getConnection()->quote($r['title']));
         if (!is_null($r['price']))     
          $oPrices[$r['option_id']][] = array('store_id'=>$r['store_id'], 'price'=>$this->getConnection()->quote($r['price']), 'price_type'=>$r['price_type']); 
         if (!is_null($r['note']))     
          $oNotes[$r['option_id']][] = array('store_id'=>$r['store_id'], 'note'=>$this->getConnection()->quote($r['note']));                      
        }  
        unset($otResult);

        $ovResult = $this->getConnection()->fetchAll("
          SELECT o.option_id,value_id,v.sku,v.sort_order,
                 v.row_id,children,image
          FROM `{$oxtoT}` o                  
          JOIN `{$oxtvT}` v   
            ON v.option_id = o.option_id                                      
          WHERE o.template_id={$templateId} 
        ");
   
        $values = array();      
        foreach ($ovResult as $r)
          $values[$r['option_id']][$r['value_id']] = "{$r['row_id']},{$this->getConnection()->quote($r['sku'])},{$r['sort_order']},{$this->getConnection()->quote($r['children'])},{$this->getConnection()->quote($r['image'])})";           
                       
        unset($ovResult);
        
        $ovtResult = $this->getConnection()->fetchAll("
          SELECT v.value_id,v.option_id,cs.store_id,title,price,price_type,description
          FROM `{$oxtoT}` o             
          JOIN `{$oxtvT}` v   
            ON v.option_id = o.option_id     
          JOIN  `{$csT}` cs        
          LEFT JOIN  `{$oxtvtT}` vt 
            ON vt.value_id = v.value_id AND vt.store_id = cs.store_id
          LEFT JOIN  `{$oxtvpT}` vp 
            ON vp.value_id = v.value_id AND vp.store_id = cs.store_id
          LEFT JOIN  `{$oxtvdT}` vd 
            ON vd.value_id = v.value_id AND vd.store_id = cs.store_id                                                
          WHERE o.template_id={$templateId} 
        ");
        
        $ovTitles = array();
        $ovPrices = array();
        $ovDescriptions = array(); 
            
        foreach ($ovtResult as $r){
         if (!is_null($r['title']))     
          $ovTitles[$r['value_id']][] = "{$r['store_id']},{$this->getConnection()->quote($r['title'])})";
         if (!is_null($r['price']))     
          $ovPrices[$r['value_id']][] = "{$r['store_id']},{$r['price']},'{$r['price_type']}')";
         if (!is_null($r['description']))     
          $ovDescriptions[$r['value_id']][] = "{$r['store_id']},{$this->getConnection()->quote($r['description'])})";                               
        }
        unset($ovtResult); 
              
        $r = $this->getConnection()->fetchRow("SHOW TABLE STATUS LIKE '{$oxtoT}'");
        $nextOptionId = $r['Auto_increment'];
        $r = $this->getConnection()->fetchRow("SHOW TABLE STATUS LIKE '{$oxtvT}'");
        $nextValueId = $r['Auto_increment']; 
             	      
        $toOT=$toOTT=$toOPT=$toONT=$toVT=$toVTT=$toVPT=$toVDT='';     
        $haveOptionPrices = $haveOptionValues = false;
              
        foreach($options as $id => $r){	 
     
          $toOT .= ($toOT != '' ? ',' : '') . "({$nextOptionId},{$newTemplateId},'opt-{$newTemplateId}-{$nextOptionId}',{$r}";            
          foreach ($oTitles[$id] as $k => $v)
            $toOTT .= ($toOTT != '' ? ',' : '') . "({$nextOptionId},{$v['store_id']},{$v['title']})";
          if (isset($oPrices[$id])){
            foreach ($oPrices[$id] as $k => $v)              	      
              $toOPT .= ($toOPT != '' ? ',' : '') . "({$nextOptionId},{$v['store_id']},{$v['price']},'{$v['price_type']}')";
            $haveOptionPrices = true;  
          }        
          foreach ($oNotes[$id] as $k => $v)                                     
            $toONT .= ($toONT != '' ? ',' : '') . "({$nextOptionId},{$v['store_id']},{$v['note']})";
                              
          if (isset($values[$id])){           
            foreach ($values[$id] as $k => $v){	              
              $toVT .= ($toVT != '' ? ',' : '') . "({$nextValueId},{$nextOptionId},{$v}";
              foreach ($ovTitles[$k] as $vv)                
                $toVTT .= ($toVTT != '' ?',' : '')  . "({$nextValueId},{$vv}";                 
              foreach ($ovPrices[$k] as $vv)                   
                $toVPT .= ($toVPT != '' ? ',' : '') . "({$nextValueId},{$vv}";                
              foreach ($ovDescriptions[$k] as $vv)                  
                $toVDT .= ($toVDT != '' ? ',' : '') . "({$nextValueId},{$vv}";                                                             
              $nextValueId++;	    	    		      	  
	          }           	
	          $haveOptionValues = true;
          }
		                    
          $nextOptionId++;	
        }	 
         
        $toOptionTable           = "INSERT INTO `{$oxtoT}`  (`option_id`,`template_id`,`code`,`row_id`,`type`,`is_require`,`sku`,`max_characters`,`file_extension`,`image_size_x`,`image_size_y`,`sort_order`,`layout`,`popup`,`selected_by_default`) VALUES	";      
        $toOptionTitleTable      = "INSERT INTO `{$oxtotT}` (`option_id`,`store_id`,`title`) VALUES	";      
        $toOptionPriceTable      = "INSERT INTO `{$oxtopT}` (`option_id`,`store_id`,`price`,`price_type`) VALUES ";
        $toOptionNoteTable       = "INSERT INTO `{$oxtonT}` (`option_id`,`store_id`,`note`) VALUES ";
        $toValueTable            = "INSERT INTO `{$oxtvT}`  (`value_id`,`option_id`,`row_id`,`sku`,`sort_order`,`children`,`image`) VALUES ";
        $toValueTitleTable       = "INSERT INTO `{$oxtvtT}` (`value_id`,`store_id`,`title`) VALUES ";
        $toValuePriceTable       = "INSERT INTO `{$oxtvpT}` (`value_id`,`store_id`,`price`,`price_type`) VALUES ";
        $toValueDescriptionTable = "INSERT INTO `{$oxtvdT}` (`value_id`,`store_id`,`description`) VALUES ";
                
        $this->getConnection()->query($toOptionTable . $toOT);
        $this->getConnection()->query($toOptionTitleTable . $toOTT);
        if ($haveOptionPrices)	              
          $this->getConnection()->query($toOptionPriceTable . $toOPT);
        $this->getConnection()->query($toOptionNoteTable . $toONT);
                    
        if ($haveOptionValues){         
          $this->getConnection()->query($toValueTable . $toVT);
          $this->getConnection()->query($toValueTitleTable . $toVTT);
          $this->getConnection()->query($toValuePriceTable . $toVPT);
          $this->getConnection()->query($toValueDescriptionTable . $toVDT);
        }				
     
    } 
    
    return $newTemplateId;
 
  }     


  public function getGridProductIds()
  {
      $select = $this->getConnection()
          ->select()
          ->from($this->getTable('optionextended_product_template'), array('template_id', 'product_ids' => 'GROUP_CONCAT(product_id)', 'product_count' =>'COUNT(*)'))
          ->group('template_id');
      return $this->getConnection()->fetchAssoc($select);  
  }

  
  public function getOptionCount($templateId)
  {
      $select = $this->getConnection()
          ->select()
          ->from($this->getTable('optionextended_template_option'), 'COUNT(option_id)')           
          ->where('template_id =?', $templateId)
          ->group('template_id');            
      return $this->getConnection()->fetchOne($select);   

  }

 
  public function getGridOptionCount()
  {
      $select = $this->getConnection()
          ->select()
          ->from($this->getTable('optionextended_template_option'), array('template_id', 'COUNT(option_id)'))
          ->group('template_id');
      return $this->getConnection()->fetchPairs($select);   

  } 


  public function getTemplatesCsv()
  {

    $headers = new \Magento\Framework\DataObject(array(
        'code' => 'code',		    				
        'title' => 'title',
        'is_active' => 'is_active'       
    ));
 
    $template = '"{{code}}","{{title}}","{{is_active}}"';		   
      
    $csv = $headers->toString($template) . "\n"; 				
   
    $data = $this->getConnection()->query("
        SELECT code,title,is_active
        FROM `{$this->getTable('optionextended_template')}`                                                                                       
    ");

    while ($row = $data->fetch()){    
      $rowObject = new \Magento\Framework\DataObject($row);
      $csv .= $rowObject->toString($template) . "\n";					      
    }
    
    return $csv;    
  }    
  
  
  
  public function getTemplateProductsCsv()
  {

    $headers = new \Magento\Framework\DataObject(array(
        'product_sku' => 'product_sku',
        'template_code'	=> 'template_code'      
    ));
 
    $template = '"{{product_sku}}","{{template_code}}"';		   
      
    $csv = $headers->toString($template) . "\n"; 						
   
    $data = $this->getConnection()->query("
        SELECT p.sku as product_sku,oxt.code as template_code 
        FROM `{$this->getTable('optionextended_product_template')}` oxpt
        JOIN `{$this->getTable('catalog_product_entity')}` p
          ON  p.entity_id = oxpt.product_id           
        JOIN `{$this->getTable('optionextended_template')}` oxt 
          ON oxt.template_id = oxpt.template_id
        ORDER BY p.sku,oxt.code                                                                                        
    ");

    while ($row = $data->fetch()){    
      $rowObject = new \Magento\Framework\DataObject($row);
      $csv .= $rowObject->toString($template) . "\n";					      
    }
    
    return $csv;    
  }

}
