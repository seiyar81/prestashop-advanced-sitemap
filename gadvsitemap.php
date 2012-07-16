<?php
/*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 13077 $
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
    exit;

require_once 'gadvLink.php';
	
class gadvsitemap extends Module
{
	private $_html = '';
	private $_postErrors = array();
	private $_nbImages = 0;
	private $_nbLocs = 0;

	public function __construct()
	{
		$this->name = 'gadvsitemap';
		$this->tab = 'seo';
		$this->version = '1.4';
		$this->author = 'Yriase';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('Prestashop Advanced Sitemap');
		$this->description = $this->l('Generate your sitemap file with advanced options');

		if (!defined('GSITEMAP_FILE'))
			define('GSITEMAP_FILE', dirname(__FILE__).'/../../sitemap.xml');
	}

	public function install()
	{
		// Install Module
		if (!parent::install() ||
		    !$this->registerHook('addproduct') ||
		    !$this->registerHook('updateproduct') ||
		    !$this->registerHook('updateProductAttribute') ||
		    !$this->registerHook('deleteproduct'))
			return false;
	}
	
	public function uninstall()
	{
		file_put_contents(GSITEMAP_FILE, '');
		return parent::uninstall();
	}

	private function _postValidation()
	{
		file_put_contents(GSITEMAP_FILE, '');
		if (!($fp = fopen(GSITEMAP_FILE, 'w')))
			$this->_postErrors[] = $this->l('Cannot create').' '.realpath(dirname(__FILE__.'/../..')).'/'.$this->l('sitemap.xml file.');
		else
			fclose($fp);
	}

	private function getUrlWith($url, $key, $value)
	{
		if (empty($value))
			return $url;
		if (strpos($url, '?') !== false)
			return $url.'&'.$key.'='.$value;
		return $url.'?'.$key.'='.$value;
	}

	private function _postProcess()
	{
            try
            {
		Configuration::updateValue('GSITEMAP_ALL_CMS', (int)Tools::getValue('GSITEMAP_ALL_CMS'));
		Configuration::updateValue('GSITEMAP_ALL_PRODUCTS', (int)Tools::getValue('GSITEMAP_ALL_PRODUCTS'));
		Configuration::updateValue('GADVSITEMAP_NOTIFY', (int)Tools::getValue('GADVSITEMAP_NOTIFY'));
                Configuration::updateValue('GADVSITEMAP_NOTIFY_BING', (int)Tools::getValue('GADVSITEMAP_NOTIFY_BING'));
                Configuration::updateValue('GADVSITEMAP_NOTIFY_ASK', (int)Tools::getValue('GADVSITEMAP_NOTIFY_ASK'));
		$gadvlink = new gadvLink(Tools::getShopDomain(true, true).__PS_BASE_URI__);
		$link	  = new Link();
		$langs = Language::getLanguages(true);
		foreach($langs as $lang) 
		{
			Configuration::updateValue($lang['iso_code'], (int)Tools::getValue($lang['iso_code']));
			
			if(Tools::getIsset($lang['iso_code'].'_url'))
			{
				Configuration::updateValue('GADVSITEMAP_'.$lang['iso_code'].'_URL', Tools::getValue($lang['iso_code'].'_url'));
				$gadvlink->setLangUrl($lang['id_lang'], Tools::getValue($lang['iso_code'].'_url'));
			}
		}
		
		$this->_nbImages = 0;

		$xmlString = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
</urlset>
XML;

		$xml = new SimpleXMLElement($xmlString);
		$lang_list = '';
		if (Configuration::get('PS_REWRITING_SETTINGS') && count($langs) > 1) 
		{
			foreach($langs as $lang) 
			{
				if (Configuration::get($lang['iso_code'])) 
				{
					$langUrl = $gadvlink->getLangUrl($lang);
					$this->_addSitemapNode($xml, $langUrl, '1.00', 'daily', date('Y-m-d'));
					$lang_list .= ($lang_list != '' ) ? ', ' : '';
					$lang_list .= $lang['id_lang'];
				}
			}
		}
		else
			$this->_addSitemapNode($xml, $gadvlink->getBaseUrl(), '1.00', 'daily', date('Y-m-d'));
			
		if ($lang_list != '') $lang_list = 'and l.id_lang in ('.$lang_list.')';

		/* Product Generator */
		$products = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
		SELECT p.id_product, pl.link_rewrite, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') date_upd, pl.id_lang, cl.`link_rewrite` category, ean13, i.id_image, il.legend legend_image, (
			SELECT MIN(level_depth)
			FROM '._DB_PREFIX_.'product p2
			LEFT JOIN '._DB_PREFIX_.'category_product cp2 ON p2.id_product = cp2.id_product
			LEFT JOIN '._DB_PREFIX_.'category c2 ON cp2.id_category = c2.id_category
			WHERE p2.id_product = p.id_product AND p2.`active` = 1 AND c2.`active` = 1) AS level_depth
		FROM '._DB_PREFIX_.'product p
		LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (p.id_product = pl.id_product)
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (p.`id_category_default` = cl.`id_category` AND pl.`id_lang` = cl.`id_lang`)
		LEFT JOIN '._DB_PREFIX_.'image i ON p.id_product = i.id_product
		LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND pl.`id_lang` = il.`id_lang`)
		LEFT JOIN '._DB_PREFIX_.'lang l ON (pl.id_lang = l.id_lang '.$lang_list.')
		WHERE l.`active` = 1 AND p.`active` = 1
		'.(Configuration::get('GSITEMAP_ALL_PRODUCTS') ? '' : 'HAVING level_depth IS NOT NULL').'
		ORDER BY pl.id_product, pl.id_lang ASC');

		
		$tmp = null;
		$res = null;
		$done = null;
		foreach ($products as $product)
		{
			if (!isset($done[$product['id_image']]))
			{
				if ($tmp == $product['id_product'])
				   $res[$tmp]['images'] []= array('id_image' => $product['id_image'], 'legend_image' => $product['legend_image']);
				else
				{
					$tmp = $product['id_product'];
					$res[$tmp] = $product;
					unset($res[$tmp]['id_image'], $res[$tmp]['legend_image']);
					$res[$tmp]['images'] []= array('id_image' => $product['id_image'], 'legend_image' => $product['legend_image']);
				}
				$done[$product['id_image']] = true;
			}
		}

		foreach ($res as $product)
		{
			if (Configuration::get('PS_REWRITING_SETTINGS'))
			{
				foreach($langs as $lang) 
				{
					if (($priority = 0.7 - ($product['level_depth'] / 10)) < 0.1)
						$priority = 0.1;
		
					$tmpLink = $gadvlink->getProductLink((int)($product['id_product']), $product['link_rewrite'], $product['category'], $product['ean13'], $lang['id_lang']);
					$sitemap = $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($product['date_upd'], 0, 10));
					$sitemap = $this->_addSitemapNodeImage($sitemap, $product);
				}
			}
			else
			{
				if (($priority = 0.7 - ($product['level_depth'] / 10)) < 0.1)
						$priority = 0.1;
		
					$tmpLink = $link->getProductLink((int)($product['id_product']), $product['link_rewrite'], $product['category'], $product['ean13'], (int)($product['id_lang']));
					$sitemap = $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($product['date_upd'], 0, 10));
					$sitemap = $this->_addSitemapNodeImage($sitemap, $product);
			}
		}

		/* Categories Generator */
		if (Configuration::get('PS_REWRITING_SETTINGS'))
			$categories = Db::getInstance()->ExecuteS('
			SELECT c.id_category, c.level_depth, link_rewrite, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') AS date_upd, cl.id_lang
			FROM '._DB_PREFIX_.'category c
			LEFT JOIN '._DB_PREFIX_.'category_lang cl ON c.id_category = cl.id_category
			LEFT JOIN '._DB_PREFIX_.'lang l ON (cl.id_lang = l.id_lang '.$lang_list.')
			WHERE l.`active` = 1 AND c.`active` = 1 AND c.id_category != 1
			ORDER BY cl.id_category, cl.id_lang ASC');
		else
			$categories = Db::getInstance()->ExecuteS(
			'SELECT c.id_category, c.level_depth, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') AS date_upd
			FROM '._DB_PREFIX_.'category c
			ORDER BY c.id_category ASC');


		foreach($categories as $category)
		{
                    if (Configuration::get('PS_REWRITING_SETTINGS'))
                    {
                        foreach($langs as $lang) 
                        {
                            if (($priority = 0.9 - ($category['level_depth'] / 10)) < 0.1)
                                $priority = 0.1;

                            $tmpLink = $gadvlink->getCategoryLink((int)$category['id_category'], $category['link_rewrite'], (int)$lang['id_lang']);
                            $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($category['date_upd'], 0, 10));
                        }
                    }
                    else
                    {
			if (($priority = 0.9 - ($category['level_depth'] / 10)) < 0.1)
				$priority = 0.1;

			$tmpLink = $link->getCategoryLink((int)$category['id_category']);
			$this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($category['date_upd'], 0, 10));
                    }
		}

		/* CMS Generator */
		if (Configuration::get('GSITEMAP_ALL_CMS') || !Module::isInstalled('blockcms'))
			$sql_cms = '
			SELECT DISTINCT '.(Configuration::get('PS_REWRITING_SETTINGS') ? 'cl.id_cms, cl.link_rewrite, cl.id_lang' : 'cl.id_cms').
			' FROM '._DB_PREFIX_.'cms_lang cl
			LEFT JOIN '._DB_PREFIX_.'lang l ON (cl.id_lang = l.id_lang '.$lang_list.')
			WHERE l.`active` = 1
			ORDER BY cl.id_cms, cl.id_lang ASC';
		else if (Module::isInstalled('blockcms'))
			$sql_cms = '
			SELECT DISTINCT '.(Configuration::get('PS_REWRITING_SETTINGS') ? 'cl.id_cms, cl.link_rewrite, cl.id_lang' : 'cl.id_cms').
			' FROM '._DB_PREFIX_.'cms_block_page b
			LEFT JOIN '._DB_PREFIX_.'cms_lang cl ON (b.id_cms = cl.id_cms)
			LEFT JOIN '._DB_PREFIX_.'lang l ON (cl.id_lang = l.id_lang '.$lang_list.')
			WHERE l.`active` = 1
			ORDER BY cl.id_cms, cl.id_lang ASC';

		$cmss = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_cms);
		foreach($cmss as $cms)
		{
                    if (Configuration::get('PS_REWRITING_SETTINGS'))
                    {
                        foreach($langs as $lang) 
                        {
                            $tmpLink = $gadvlink->getCMSLink((int)$cms['id_cms'], $cms['link_rewrite'], false, (int)$lang['id_lang']);
                            $this->_addSitemapNode($xml, $tmpLink, '0.8', 'daily');
                        }
                    }
                    else
                    {
			$tmpLink = $link->getCMSLink((int)$cms['id_cms']);
			$this->_addSitemapNode($xml, $tmpLink, '0.8', 'daily');
                    }
		}

		/* Add classic pages (contact, best sales, new products...) */
		$pages = array(
			'supplier' => false,
			'manufacturer' => false,
			'new-products' => false,
			'prices-drop' => false,
			'stores' => false,
			'authentication' => true,
			'best-sales' => false,
			'contact-form' => true);

		// Don't show suppliers and manufacturers if they are disallowed
		if (!Module::getInstanceByName('blockmanufacturer')->id && !Configuration::get('PS_DISPLAY_SUPPLIERS'))
			unset($pages['manufacturer']);

		if (!Module::getInstanceByName('blocksupplier')->id && !Configuration::get('PS_DISPLAY_SUPPLIERS'))
			unset($pages['supplier']);

		// Generate nodes for pages
		if (Configuration::get('PS_REWRITING_SETTINGS'))
			foreach ($pages as $page => $ssl)
				foreach($langs as $lang)
					$this->_addSitemapNode($xml, $gadvlink->getPageLink($page.'.php', $ssl, $lang['id_lang']), '0.5', 'monthly');
		else
			foreach($pages as $page => $ssl)
				$this->_addSitemapNode($xml, $link->getPageLink($page.'.php', $ssl), '0.5', 'monthly');

		$this->_saveFormattedSitemap($xml);
		
	    if(Configuration::get('GADVSITEMAP_NOTIFY')) 
	    {
                if(@file_get_contents('http://www.google.com/webmasters/tools/ping?sitemap=http://'.Tools::getHttpHost(false, true).__PS_BASE_URI__.'sitemap.xml'))
                    $pinged = true;
                else $pinged = false;
            }
            if(Configuration::get('GADVSITEMAP_NOTIFY_BING')) 
            {
                if(@file_get_contents('http://www.bing.com/webmaster/ping.aspx?siteMap=http://'.Tools::getHttpHost(false, true).__PS_BASE_URI__.'sitemap.xml'))
                    $bingPinged = true;
                else
                    $bingPinged = false;
            }
            if(Configuration::get('GADVSITEMAP_NOTIFY_ASK')) 
            {
                if(@file_get_contents('http://submissions.ask.com/ping?sitemap=http://'.Tools::getHttpHost(false, true).__PS_BASE_URI__.'sitemap.xml'))
                    $askPinged = true;
                else
                    $askPinged = false;
            }

            $res = file_exists(GSITEMAP_FILE);
            $this->_html .= '<h3 class="'. ($res ? 'conf confirm' : 'alert error') .'" style="margin-bottom: 20px">';
            $this->_html .= $res ? $this->l('Sitemap file generated') : $this->l('Error while creating sitemap file');
            $this->_html .= '</h3>';

            if(Configuration::get('GADVSITEMAP_NOTIFY')) 
            {
                $this->_html .= '<h3 class="'. ($pinged ? 'conf confirm' : 'alert error') .'" style="margin-bottom: 20px">';
                $this->_html .= $pinged ? $this->l('Google successfully pinged') : $this->l('Error while pinging Google');
                $this->_html .= '</h3>';
            }
            if(Configuration::get('GADVSITEMAP_NOTIFY_BING')) 
            {
                $this->_html .= '<h3 class="'. ($bingPinged ? 'conf confirm' : 'alert error') .'" style="margin-bottom: 20px">';
                $this->_html .= $bingPinged ? $this->l('Bing successfully pinged') : $this->l('Error while pinging Bing');
                $this->_html .= '</h3>';
            }
            if(Configuration::get('GADVSITEMAP_NOTIFY_ASK')) 
            {
                    $this->_html .= '<h3 class="'. ($askPinged ? 'conf confirm' : 'alert error') .'" style="margin-bottom: 20px">';
                $this->_html .= $askPinged ? $this->l('Ask successfully pinged') : $this->l('Error while pinging Ask');
                $this->_html .= '</h3>';
            }
            
            }catch(Exception $e)
            {
                $this->_html = '<h3 class="alert error" style="margin-bottom: 20px">';
                $this->_html .= $this->l('An unknown error occured during the sitemap generation.') . '<br />';
                $this->_html .= $e->getMessage();
                $this->_html .= '</h3>';
            }
	}

	private function _saveFormattedSitemap($xml)
	{
		$dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml->asXML());
		$dom->save(GSITEMAP_FILE);
	}
	
	private function _addSitemapNode($xml, $loc, $priority, $change_freq, $last_mod = NULL)
	{
		$this->_nbLocs++;
		$sitemap = $xml->addChild('url');
		$sitemap->addChild('loc', $loc);
		$sitemap->addChild('priority', number_format($priority,1,'.',''));
		if ($last_mod)
			$sitemap->addChild('lastmod', $last_mod);
		$sitemap->addChild('changefreq', $change_freq);
		return $sitemap;
	}

	private function _addSitemapNodeImage($xml, $product)
	{
		foreach ($product['images'] as $img)
		{
			$this->_nbImages++;
			$link = new Link();
			$image = $xml->addChild('image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
			$image->addChild('loc', $link->getImageLink($product['link_rewrite'], (int)$product['id_product'].'-'.(int)$img['id_image']), 'http://www.google.com/schemas/sitemap-image/1.1');

			$legend_image = preg_replace('/(&+)/i', '&amp;', $img['legend_image']);
			$image->addChild('caption', $legend_image, 'http://www.google.com/schemas/sitemap-image/1.1');
			$image->addChild('title', $legend_image, 'http://www.google.com/schemas/sitemap-image/1.1');
		}
	}

	private function _displaySitemap()
	{
		if (file_exists(GSITEMAP_FILE) && filesize(GSITEMAP_FILE))
		{
			$fp = fopen(GSITEMAP_FILE, 'r');
			$fstat = fstat($fp);
			fclose($fp);
			$xml = simplexml_load_file(GSITEMAP_FILE);

			$nbPages = count($xml->url);

			$this->_html .= '<p>'.$this->l('Your Google sitemap file is online at the following address:').'<br />
			<a href="'.Tools::getShopDomain(true, true).__PS_BASE_URI__.'sitemap.xml" target="_blank"><b>'.Tools::getShopDomain(true, true).__PS_BASE_URI__.'sitemap.xml</b></a></p><br />';
			
			$this->_html .= $this->l('Update:').' <b>'.utf8_encode(strftime('%A %d %B %Y %H:%M:%S', $fstat['mtime'])).'</b><br />';
			$this->_html .= $this->l('Filesize:').' <b>'.number_format(($fstat['size']*.000001), 3).'MB</b><br />';
			$this->_html .= $this->l('Indexed pages:').' <b>'.$this->_nbLocs.'</b><br />';
			if (Tools::isSubmit('btnSubmit'))
				$this->_html .= $this->l('Indexed images:').' <b>'.$this->_nbImages.'</b><br /><br />';
			else
				$this->_html .= '<br />';
		}
	}

	private function _displayForm()
	{
		$this->_html .=
		'<form action="'.Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']).'" method="post">
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GSITEMAP_ALL_PRODUCTS" id="GSITEMAP_ALL_PRODUCTS" style="vertical-align: middle;" value="1" '.(Configuration::get('GSITEMAP_ALL_PRODUCTS') ? 'checked="checked"' : '').' /> <label class="t" for="GSITEMAP_ALL_PRODUCTS">'.$this->l('Sitemap also includes products from inactive categories').'</label>
			</div>
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GSITEMAP_ALL_CMS" id="GSITEMAP_ALL_CMS" style="vertical-align: middle;" value="1" '.(Configuration::get('GSITEMAP_ALL_CMS') ? 'checked="checked"' : '').' /> <label class="t" for="GSITEMAP_ALL_CMS">'.$this->l('Sitemap also includes CMS pages which are not in a CMS block').'</label>
			</div>
			
			<h2>'.$this->l('Search engines pinging').'</h2>
			
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY" id="GADVSITEMAP_NOTIFY" style="vertical-align: middle;" value="1" '.(Configuration::get('GADVSITEMAP_NOTIFY') ? 'checked="checked"' : '').' /> <label class="t" for="GADVSITEMAP_NOTIFY">'.$this->l('Send this sitemap to Google index').'</label>
			</div>
            <div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY_BING" id="GADVSITEMAP_NOTIFY_BING" style="vertical-align: middle;" value="1" '.(Configuration::get('GADVSITEMAP_NOTIFY_BING') ? 'checked="checked"' : '').' /> <label class="t" for="GADVSITEMAP_NOTIFY_BING">'.$this->l('Send this sitemap to Bing index (Yahoo index has moved to Bing)').'</label>
			</div>
            <div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY_ASK" id="GADVSITEMAP_NOTIFY_ASK" style="vertical-align: middle;" value="1" '.(Configuration::get('GADVSITEMAP_NOTIFY_ASK') ? 'checked="checked"' : '').' /> <label class="t" for="GADVSITEMAP_NOTIFY_ASK">'.$this->l('Send this sitemap to Ask index').'</label>
			</div>
			
			<h2>'.$this->l('Languages').'</h2>';
			$langs = Language::getLanguages(true);
			foreach($langs as $lang) 
			{
				$iso_code = $lang['iso_code'];
				
				if (Configuration::get('PS_REWRITING_SETTINGS')) 
				{
					$url = Tools::getShopDomain(true, true).__PS_BASE_URI__.$lang['iso_code'] . '/';
					if(Configuration::get('GADVSITEMAP_'.$lang['iso_code'].'_URL'))
					{
						$url = Configuration::get('GADVSITEMAP_'.$lang['iso_code'].'_URL');
					}
				}
				else
					$url = Tools::getShopDomain(true, true).__PS_BASE_URI__;
				
				$this->_html .= 
				'<div style="margin:0 0 20px 0;">
					<input type="checkbox" name="'.$iso_code.'" id="'.$iso_code.'" style="vertical-align: middle;" value="1" '.(Configuration::get($iso_code) ? 'checked="checked"' : '').' /> <label class="t" for="'.$iso_code.'">'.$lang['name'].'</label><br />
					<label class="t">URL : <input type="text" name="'.$iso_code.'_url" id="'.$iso_code.'_url" size="50" value="'.$url.'"/></label>
				</div>';
			}
			
			if (!Configuration::get('PS_REWRITING_SETTINGS') )
			{
				$this->_html .= '<h3 class="alert error" style="margin-bottom: 20px">
										'.$this->l('Friendly URLs are required if you want to modify those.').'
								 </h3>';
			}
			
			$this->_html .= 
			'<input name="btnSubmit" class="button" type="submit"
			value="'.((!file_exists(GSITEMAP_FILE)) ? $this->l('Generate sitemap file') : $this->l('Update sitemap file')).'" />
		</form>';
		
		
	}

	public function getContent()
	{
		$this->_html .= '<h2>'.$this->l('Search Engine Optimization').'</h2>
		'.$this->l('See').' <a href="http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=156184&from=40318&rd=1" style="font-weight:bold;text-decoration:underline;" target="_blank">
		'.$this->l('this page').'</a> '.$this->l('for more information').'<br /><br />';
		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= '<div class="alert error">'.$err.'</div>';
		}

		$this->_displaySitemap();
		$this->_displayForm();

		return $this->_html;
	}
	
	/**
	 * Hooks : addproduct, updateproduct, updateProductAttribute, deleteproduct 
	 */
	public function hookaddproduct($params)
	{
		$this->_postProcess();
	}
	
	public function hookupdateproduct($params)
	{
		$this->hookaddproduct($params);
	}
	
	public function hookupdateProductAttribute($params) 
	{ 
		$this->hookaddproduct($params); 
	}
	
	public function hookdeleteproduct($params) 
	{ 
		$this->hookaddproduct($params); 
	}
}


