<?php
/*
* Prestashop Advanced Sitemap
*
*  @author Yriase <postmaster@yriase.fr>
*  @version  1.4.5.1
*
* THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE 
* INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR 
* BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER 
* RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER 
* TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
*
*/

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
		if (is_object($id_product))
		{		
			$link = '';

			$link .= $this->_langsUrl[(int)$id_lang];
			
			/*if (isset($id_product->category) AND !empty($id_product->category) AND $id_product->category != 'home')
				$link .= $id_product->category.'/';
			else
				$link .= '';*/
                        $link .= $category . '/';

			$link .= (int)$id_product->id.'-';
			if (is_array($id_product->link_rewrite))
				$link.= $id_product->link_rewrite[(int)$id_lang];
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
        
        public function getCategoryLink($id_category, $alias = NULL, $id_lang = NULL)
	{
		if (is_object($id_category))
			return ($this->_langsUrl[(int)$id_lang] . (int)($id_category->id).'-'.$id_category->link_rewrite);
		if ($alias)
			return ($this->_langsUrl[(int)$id_lang] . (int)($id_category).'-'.$alias);
                
		return $this->_baseUrl.'category.php?id_category='.(int)($id_category);
	}
        
        public function getCMSLink($cms, $alias = null, $ssl = false, $id_lang = NULL)
	{
		if (is_object($cms))
		{
			return ( $this->_langsUrl[(int)$id_lang] . 'content/'.(int)($cms->id).'-'.$cms->link_rewrite);
		}
		
		if ($alias)
			return ($this->_langsUrl[(int)$id_lang] . 'content/'.(int)($cms).'-'.$alias);
                
		return  $this->_baseUrl.'cms.php?id_cms='.(int)($cms);
	}
        
        public function getPageLink($filename, $ssl = false, $id_lang = NULL)
	{
            global $cookie;
            if ($id_lang == NULL)
                    $id_lang = (int)($cookie->id_lang);

            if (array_key_exists($filename.'_'.$id_lang, self::$cache['page']) AND !empty(self::$cache['page'][$filename.'_'.$id_lang]))
                    $uri_path = self::$cache['page'][$filename.'_'.$id_lang];
            else
            {
                $url_rewrite = '';
                if ($filename != 'index.php')
                {
                        $pagename = substr($filename, 0, -4);
                        $url_rewrite = Db::getInstance()->getValue('
                        SELECT url_rewrite
                        FROM `'._DB_PREFIX_.'meta` m
                        LEFT JOIN `'._DB_PREFIX_.'meta_lang` ml ON (m.id_meta = ml.id_meta)
                        WHERE id_lang = '.(int)($id_lang).' AND `page` = \''.pSQL($pagename).'\'');
                        $uri_path = $this->_langsUrl[(int)$id_lang].($url_rewrite ? $url_rewrite : $filename);
                }
                else
                        $uri_path = $this->_langsUrl[(int)$id_lang];


                self::$cache['page'][$filename.'_'.$id_lang] = $uri_path;
            }
            return ltrim($uri_path, '/');
	}
        
        public function getImageLink($name, $ids, $id_lang, $type = NULL)
	{
		global $protocol_content;
        $uri_path = '';
		// legacy mode or default image
		if ((Configuration::get('PS_LEGACY_IMAGES') 
			&& (file_exists(_PS_PROD_IMG_DIR_.$ids.($type ? '-'.$type : '').'.jpg')))
			|| strpos($ids, 'default') !== false)
		{
            $uri_path = $this->_langsUrl[(int)$id_lang] . $ids.($type ? '-'.$type : '').'/'.$name.'.jpg';
		}else
		{
			// if ids if of the form id_product-id_image, we want to extract the id_image part
			$split_ids = explode('-', $ids);
			$id_image = (isset($split_ids[1]) ? $split_ids[1] : $split_ids[0]);
			
            $uri_path = $this->_langsUrl[(int)$id_lang] . $id_image.($type ? '-'.$type : '').'/'.$name.'.jpg';
		}
		
		return $uri_path;
	}
	
}