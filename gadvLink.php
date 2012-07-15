<?php

class gadvLink extends Link
{
	
	private $_baseUrl = '';
	
	private $_langsUrl = array();
	
	
	public function __construct($baseUrl)
	{
		$this->_baseUrl = $baseUrl;
	}
	
	public function getBaseUrl()
	{
		return $this->_baseUrl;
	}
	
	public function getLangUrl($lang)
	{
		if(array_key_exists($lang['id_lang'], $this->_langsUrl))
		{
			return $this->_langsUrl[$lang['id_lang']];
		} 
		else
			return Tools::getShopDomain(true, true).__PS_BASE_URI__.$lang['iso_code'].'/';
	}
	
	public function setLangUrl($id_lang, $url)
	{
		$this->_langsUrl[$id_lang] = $url;
	}
	
	public function getProductLink($id_product, $alias = NULL, $category = NULL, $ean13 = NULL, $id_lang = NULL)
	{
		global $cookie;
		if (is_object($id_product))
		{		
			$link = '';

			$link .= $this->_langsUrl[(int)$id_lang];
			
			if (isset($id_product->category) AND !empty($id_product->category) AND $id_product->category != 'home')
				$link .= $id_product->category.'/';
			else
				$link .= '';

			$link .= (int)$id_product->id.'-';
			if (is_array($id_product->link_rewrite))
				$link.= $id_product->link_rewrite[(int)$cookie->id_lang];
			else 
			 	$link.= $id_product->link_rewrite;
			if ($id_product->ean13)
				$link .='-'.$id_product->ean13;
			else
				$link .= '';

			$link .= '.html';
			
			return $link;
		}
		
		else if ($alias)
		{
			$link = '';
			
			$link .= $this->_langsUrl[(int)$id_lang];
				
			if ($category AND $category != 'home')
				$link .= $category.'/';
			else 
			 	$link .= '';
			 
			$link .= (int)$id_product.'-'.$alias;
			
			if ($ean13) 
				$link .='-'.$ean13;
			else 
				$link .= '';
		
			$link .= '.html';

			return $link;
		}
		
		else
			return $this->_baseUrl.'product.php?id_product='.(int)$id_product;
	}
	
}