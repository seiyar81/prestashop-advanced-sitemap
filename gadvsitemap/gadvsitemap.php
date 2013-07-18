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

if (! defined ( '_PS_VERSION_' ))
	exit ();

require_once 'classes/gadvlink.php';
require_once 'classes/github.php';

class gadvsitemap extends Module {
	private $_html = '';
	private $_postErrors = array ();
	private $_nbImages = 0;
	private $_nbLocs = 0;
	
	public function __construct() {
		$this->name = 'gadvsitemap';
		$this->tab = 'seo';
		$this->version = '1.4.5.1';
		$this->author = 'Yriase';
		$this->need_instance = 0;
		
		parent::__construct ();
		
		$this->displayName = $this->l('Prestashop Advanced Sitemap');
		$this->description = $this->l('Generate your sitemap file with advanced options');
		
		if (! defined ( 'GSITEMAP_FILE' ))
			define ( 'GSITEMAP_FILE', dirname ( __FILE__ ) . '/../../sitemap.xml' );
        if (! defined ( 'GROBOTS_FILE' ))
    		define ( 'GROBOTS_FILE', dirname ( __FILE__ ) . '/../../robots.txt' );
	}
	
	public function install() {
		// Install Module
		if (! parent::install () || ! $this->registerHook ( 'addproduct' ) || ! $this->registerHook ( 'updateproduct' ) || ! $this->registerHook ( 'updateProductAttribute' ) || ! $this->registerHook ( 'deleteproduct' ))
			return true;
		
		return true;
	}
	
	public function uninstall() {
		file_put_contents ( GSITEMAP_FILE, '' );
		return parent::uninstall ();
	}
	
	private function _postValidation() {
		file_put_contents ( GSITEMAP_FILE, '' );
		if (! ($fp = fopen ( GSITEMAP_FILE, 'w' )))
			$this->_postErrors [] = $this->l( 'Cannot create' ) . ' ' . realpath ( dirname ( __FILE__ . '/../..' ) ) . '/' . $this->l( 'sitemap.xml file.' );
		else
			fclose ( $fp );
	}
	
	private function getUrlWith($url, $key, $value) {
		if (empty ( $value ))
			return $url;
		if (strpos ( $url, '?' ) !== false)
			return $url . '&' . $key . '=' . $value;
		return $url . '?' . $key . '=' . $value;
	}
	
	private function _postProcess($saveConfig = true) {
		try {
			$langs = array ();
			$gadvlink = new gadvLink ( Tools::getShopDomain ( true, true ) . __PS_BASE_URI__ );
			$langsList = Language::getLanguages ( true );
			if($saveConfig)
			{
				Configuration::updateValue ( 'GSITEMAP_ALL_CMS', ( int ) Tools::getValue ( 'GSITEMAP_ALL_CMS' ) );
				Configuration::updateValue ( 'GSITEMAP_ALL_PRODUCTS', ( int ) Tools::getValue ( 'GSITEMAP_ALL_PRODUCTS' ) );
				Configuration::updateValue ( 'GADVSITEMAP_NOTIFY', ( int ) Tools::getValue ( 'GADVSITEMAP_NOTIFY' ) );
				Configuration::updateValue ( 'GADVSITEMAP_NOTIFY_BING', ( int ) Tools::getValue ( 'GADVSITEMAP_NOTIFY_BING' ) );
				
				foreach ( $langsList as $lang ) {
					Configuration::updateValue ( $lang ['iso_code'], ( int ) Tools::getValue ( $lang ['iso_code'] ) );
					
					if (Tools::getIsset ( $lang ['iso_code'] ) && Tools::getIsset ( $lang ['iso_code'] . '_url' )) {
						Configuration::updateValue ( 'GADVSITEMAP_' . $lang ['iso_code'] . '_URL', Tools::getValue ( $lang ['iso_code'] . '_url' ) );
						$gadvlink->setLangUrl ( $lang ['id_lang'], Tools::getValue ( $lang ['iso_code'] . '_url' ) );
						$langs [] = $lang;
					}
				}
				
				Configuration::updateValue ( 'GADVSITEMAP_SECURE_KEY', ( string ) Tools::getValue ( 'GADVSITEMAP_SECURE_KEY' ) );
			}
			else
			{
				foreach ( $langsList as $lang ) {
					if (Configuration::get ( 'GADVSITEMAP_' . $lang ['iso_code'] . '_URL')) {
						$gadvlink->setLangUrl ( $lang ['id_lang'], Configuration::get ( 'GADVSITEMAP_' . $lang ['iso_code'] . '_URL' ));
						$langs [] = $lang;
					}
				}
			}
            
            if(empty($langs))
            {
                throw new Exception( $this->l('You must select at least one language.') );           
            }
            
			$this->_nbImages = 0;
			
			$xmlString = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
</urlset>
XML;
			
			$xml = new SimpleXMLElement ( $xmlString );
			$lang_list = '';
			if (Configuration::get ( 'PS_REWRITING_SETTINGS' ) && count ( $langs ) > 1) {
				foreach ( $langs as $lang ) {
					if (Configuration::get ( $lang ['iso_code'] )) {
						$langUrl = $gadvlink->getLangUrl ( $lang );
                        if(strlen($langUrl))
                        {
    						$this->_addSitemapNode ( $xml, $langUrl, '1.00', 'daily', date ( 'Y-m-d' ) );
    						$lang_list .= ($lang_list != '') ? ', ' : '';
    						$lang_list .= $lang ['id_lang'];
                        }
					}
				}
			} else
				$this->_addSitemapNode ( $xml, $gadvlink->getBaseUrl (), '1.00', 'daily', date ( 'Y-m-d' ) );
			
			if ($lang_list != '')
				$lang_list = 'and l.id_lang in (' . $lang_list . ')';
			
			$sql_products = '
				SELECT p.id_product, pl.link_rewrite, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') date_upd, pl.id_lang, cl.`link_rewrite` category, ean13, (
					SELECT MIN(level_depth)
					FROM ' . _DB_PREFIX_ . 'product p2
					LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp2 ON p2.id_product = cp2.id_product
					LEFT JOIN ' . _DB_PREFIX_ . 'category c2 ON cp2.id_category = c2.id_category
					WHERE p2.id_product = p.id_product AND p2.`active` = 1 AND c2.`active` = 1) AS level_depth
				FROM ' . _DB_PREFIX_ . 'product p
				LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (p.id_product = pl.id_product)
				LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (p.`id_category_default` = cl.`id_category` AND pl.`id_lang` = cl.`id_lang`)
				LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (pl.id_lang = l.id_lang ' . $lang_list . ')
				WHERE l.`active` = 1 AND p.`active` = 1
				' . (Configuration::get ( 'GSITEMAP_ALL_PRODUCTS' ) ? '' : 'HAVING level_depth IS NOT NULL') . '
				ORDER BY pl.id_product, pl.id_lang ASC';
			
			$products = Db::getInstance ( _PS_USE_SQL_SLAVE_ )->ExecuteS ( $sql_products );
			
			$tmp = null;
			$res = null;
			$done = null;
			
			if (Configuration::get ( 'PS_REWRITING_SETTINGS' )) {
				foreach ( $products as &$product ) {
					if (($priority = 0.7 - ($product ['level_depth'] / 10)) < 0.1)
						$priority = 0.1;
					
					$sql_images = 'SELECT i.id_image, il.legend legend_image 
                                        FROM ' . _DB_PREFIX_ . 'image i
                                        LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . $product ['id_lang'] . ')
                                        WHERE i.id_product = ' . $product ['id_product'];
					$product ['images'] = Db::getInstance ( _PS_USE_SQL_SLAVE_ )->ExecuteS ( $sql_images );
					
					$tmpLink = $gadvlink->getProductLink ( ( int ) ($product ['id_product']), $product ['link_rewrite'], $product ['category'], $product ['ean13'], $product ['id_lang'] );
                    if(strlen($tmpLink))
                    {
    					$sitemap = $this->_addSitemapNode ( $xml, htmlspecialchars ( $tmpLink ), $priority, 'weekly', substr ( $product ['date_upd'], 0, 10 ) );
    					$sitemap = $this->_addSitemapNodeImage ( $sitemap, $product, $gadvlink, $product ['id_lang'] );
                    }
				}
			} else {
				foreach ( $products as &$product ) {
					if (($priority = 0.7 - ($product ['level_depth'] / 10)) < 0.1)
						$priority = 0.1;
					
					$tmpLink = $gadvlink->getProductLink ( ( int ) ($product ['id_product']), $product ['link_rewrite'], $product ['category'], $product ['ean13'], ( int ) ($product ['id_lang']) );
					if(strlen($tmpLink))
                    {
                        $sitemap = $this->_addSitemapNode ( $xml, htmlspecialchars ( $tmpLink ), $priority, 'weekly', substr ( $product ['date_upd'], 0, 10 ) );
					    $sitemap = $this->_addSitemapNodeImage ( $sitemap, $product, $gadvlink, $lang );
                    }
				}
			}
			
			/* Categories Generator */
			if (Configuration::get ( 'PS_REWRITING_SETTINGS' ))
				$categories = Db::getInstance ()->ExecuteS ( '
			SELECT c.id_category, c.level_depth, link_rewrite, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') AS date_upd, cl.id_lang
			FROM ' . _DB_PREFIX_ . 'category c
			LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON c.id_category = cl.id_category
			LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (cl.id_lang = l.id_lang ' . $lang_list . ')
			WHERE l.`active` = 1 AND c.`active` = 1 AND c.id_category != 1
			ORDER BY cl.id_category, cl.id_lang ASC' );
			else
				$categories = Db::getInstance ()->ExecuteS ( 'SELECT c.id_category, c.level_depth, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') AS date_upd
			FROM ' . _DB_PREFIX_ . 'category c
			ORDER BY c.id_category ASC' );
			
			foreach ( $categories as $category ) {
				if (Configuration::get ( 'PS_REWRITING_SETTINGS' )) {
					if (($priority = 0.9 - ($category ['level_depth'] / 10)) < 0.1)
						$priority = 0.1;
					
					$tmpLink = $gadvlink->getCategoryLink ( ( int ) $category ['id_category'], $category ['link_rewrite'], ( int ) $category ['id_lang'] );
					if(strlen($tmpLink))
                    {
                        $this->_addSitemapNode ( $xml, htmlspecialchars ( $tmpLink ), $priority, 'weekly', substr ( $category ['date_upd'], 0, 10 ) );
                    }
				} else {
					if (($priority = 0.9 - ($category ['level_depth'] / 10)) < 0.1)
						$priority = 0.1;
					
					$tmpLink = $gadvlink->getCategoryLink ( ( int ) $category ['id_category'] );
                    if(strlen($tmpLink))
                    {
					    $this->_addSitemapNode ( $xml, htmlspecialchars ( $tmpLink ), $priority, 'weekly', substr ( $category ['date_upd'], 0, 10 ) );
                    }
				}
			}
			
			/* CMS Generator */
			if (Configuration::get ( 'GSITEMAP_ALL_CMS' ) || ! Module::isInstalled ( 'blockcms' ))
				$sql_cms = '
			SELECT DISTINCT ' . (Configuration::get ( 'PS_REWRITING_SETTINGS' ) ? 'cl.id_cms, cl.link_rewrite, cl.id_lang' : 'cl.id_cms') . ' FROM ' . _DB_PREFIX_ . 'cms_lang cl
			LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (cl.id_lang = l.id_lang ' . $lang_list . ')
			WHERE l.`active` = 1
			ORDER BY cl.id_cms, cl.id_lang ASC';
			else if (Module::isInstalled ( 'blockcms' ))
				$sql_cms = '
			SELECT DISTINCT ' . (Configuration::get ( 'PS_REWRITING_SETTINGS' ) ? 'cl.id_cms, cl.link_rewrite, cl.id_lang' : 'cl.id_cms') . ' FROM ' . _DB_PREFIX_ . 'cms_block_page b
			LEFT JOIN ' . _DB_PREFIX_ . 'cms_lang cl ON (b.id_cms = cl.id_cms)
			LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (cl.id_lang = l.id_lang ' . $lang_list . ')
			WHERE l.`active` = 1
			ORDER BY cl.id_cms, cl.id_lang ASC';
			
			$cmss = Db::getInstance ( _PS_USE_SQL_SLAVE_ )->ExecuteS ( $sql_cms );
			foreach ( $cmss as $cms ) {
				if (Configuration::get ( 'PS_REWRITING_SETTINGS' )) {
					$tmpLink = $gadvlink->getCMSLink ( ( int ) $cms ['id_cms'], $cms ['link_rewrite'], false, ( int ) $cms ['id_lang'] );
					if(strlen($tmpLink))
                    {
                        $this->_addSitemapNode ( $xml, $tmpLink, '0.8', 'daily' );
                    }
				} else {
					$tmpLink = $gadvlink->getCMSLink ( ( int ) $cms ['id_cms'] );
                    if(strlen($tmpLink))
                    {
					    $this->_addSitemapNode ( $xml, $tmpLink, '0.8', 'daily' );
                    }
				}
			}
			
			/* Add classic pages (contact, best sales, new products...) */
			$pages = array ('supplier' => false, 'manufacturer' => false, 'new-products' => false, 'prices-drop' => false, 'stores' => false, 'authentication' => true, 'best-sales' => false, 'contact-form' => true );
			
			// Don't show suppliers and manufacturers if they are disallowed
			if (! Module::getInstanceByName ( 'blockmanufacturer' )->id && ! Configuration::get ( 'PS_DISPLAY_SUPPLIERS' ))
				unset ( $pages ['manufacturer'] );
			
			if (! Module::getInstanceByName ( 'blocksupplier' )->id && ! Configuration::get ( 'PS_DISPLAY_SUPPLIERS' ))
				unset ( $pages ['supplier'] );
			
			// Generate nodes for pages
			if (Configuration::get ( 'PS_REWRITING_SETTINGS' ))
				foreach ( $pages as $page => $ssl )
					foreach ( $langs as $lang )
						$this->_addSitemapNode ( $xml, $gadvlink->getPageLink ( $page . '.php', $ssl, $lang ['id_lang'] ), '0.5', 'monthly' );
			else
				foreach ( $pages as $page => $ssl )
					$this->_addSitemapNode ( $xml, $gadvlink->getPageLink ( $page . '.php', $ssl ), '0.5', 'monthly' );
			
			$this->_saveFormattedSitemap ( $xml );
			
			if (Configuration::get ( 'GADVSITEMAP_NOTIFY' )) {
				if (@file_get_contents ( 'http://www.google.com/webmasters/tools/ping?sitemap=http://' . Tools::getHttpHost ( false, true ) . __PS_BASE_URI__ . 'sitemap.xml' ))
					$pinged = true;
				else
					$pinged = false;
			}
			if (Configuration::get ( 'GADVSITEMAP_NOTIFY_BING' )) {
				if (@file_get_contents ( 'http://www.bing.com/webmaster/ping.aspx?siteMap=http://' . Tools::getHttpHost ( false, true ) . __PS_BASE_URI__ . 'sitemap.xml' ))
					$bingPinged = true;
				else
					$bingPinged = false;
			}
			
			$res = file_exists ( GSITEMAP_FILE );
			$this->_html .= '<h3 class="' . ($res ? 'conf confirm' : 'alert error') . '" style="margin-bottom: 20px">';
			$this->_html .= $res ? $this->l('Sitemap file generated') : $this->l('Error while creating sitemap file');
			$this->_html .= '</h3>';
			
			if (Configuration::get ( 'GADVSITEMAP_NOTIFY' )) {
				$this->_html .= '<h3 class="' . ($pinged ? 'conf confirm' : 'alert error') . '" style="margin-bottom: 20px">';
				$this->_html .= $pinged ? $this->l('Google successfully pinged') : $this->l('Error while pinging Google');
				$this->_html .= '</h3>';
			}
			if (Configuration::get ( 'GADVSITEMAP_NOTIFY_BING' )) {
				$this->_html .= '<h3 class="' . ($bingPinged ? 'conf confirm' : 'alert error') . '" style="margin-bottom: 20px">';
				$this->_html .= $bingPinged ? $this->l('Bing successfully pinged') : $this->l('Error while pinging Bing');
				$this->_html .= '</h3>';
			}
		
		} catch ( Exception $e ) {
			$this->_html = '<h3 class="alert error" style="margin-bottom: 20px">';
			$this->_html .= $this->l('An unknown error occured during the sitemap generation.') . '<br />';
			$this->_html .= $e->getMessage ();
			$this->_html .= '</h3>';
		}
	}
	
	private function _saveFormattedSitemap($xml) {
		$dom = new DOMDocument ( '1.0' );
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML ( $xml->asXML () );
		$dom->save ( GSITEMAP_FILE );
	}
	
	private function _addSitemapNode($xml, $loc, $priority, $change_freq, $last_mod = NULL) {
		$this->_nbLocs++;
		$sitemap = $xml->addChild ( 'url' );
		$sitemap->addChild ( 'loc', $loc );
		$sitemap->addChild ( 'priority', number_format ( $priority, 1, '.', '' ) );
		if ($last_mod)
			$sitemap->addChild ( 'lastmod', $last_mod );
		$sitemap->addChild ( 'changefreq', $change_freq );
		return $sitemap;
	}
	
	private function _addSitemapNodeImage($xml, $product, $link, $lang) {
		if (isset ( $product ['images'] ))
			foreach ( $product ['images'] as $img ) {
				$this->_nbImages++;
				$image = $xml->addChild ( 'image', null, 'http://www.google.com/schemas/sitemap-image/1.1' );
				
				if (Configuration::get ( 'PS_REWRITING_SETTINGS' ))
					$tmpLink = $link->getImageLink ( $product ['link_rewrite'], ( int ) $product ['id_product'] . '-' . ( int ) $img ['id_image'], $lang );
				else
					$tmpLink = $link->getImageLink ( $product ['link_rewrite'], ( int ) $product ['id_product'] . '-' . ( int ) $img ['id_image'] );
				
				$image->addChild ( 'loc', $tmpLink, 'http://www.google.com/schemas/sitemap-image/1.1' );
				
				$legend_image = preg_replace ( '/(&+)/i', '&amp;', $img ['legend_image'] );
				$image->addChild ( 'caption', $legend_image, 'http://www.google.com/schemas/sitemap-image/1.1' );
				$image->addChild ( 'title', $legend_image, 'http://www.google.com/schemas/sitemap-image/1.1' );
			}
	}
	
	private function _displaySitemap() {
		if (file_exists ( GSITEMAP_FILE ) && filesize ( GSITEMAP_FILE )) {
			$fp = fopen ( GSITEMAP_FILE, 'r' );
			$fstat = fstat ( $fp );
			fclose ( $fp );
			$xml = simplexml_load_file ( GSITEMAP_FILE );
			
			$nbPages = count ( $xml->url );
			
			$this->_html .= '<div class="gadv_right gadv_bloc"><p>' . $this->l('Your sitemap file is online at the following address:') . '<br />
			<a href="' . Tools::getShopDomain ( true, true ) . __PS_BASE_URI__ . 'sitemap.xml" target="_blank"><b>' . Tools::getShopDomain ( true, true ) . __PS_BASE_URI__ . 'sitemap.xml</b></a></p><br />';
			
			$this->_html .= $this->l('Update:') . ' <b>' . utf8_encode ( strftime ( '%A %d %B %Y %H:%M:%S', $fstat ['mtime'] ) ) . '</b><br />';
			$this->_html .= $this->l('Filesize:') . ' <b>' . number_format ( ($fstat ['size'] * .000001), 3 ) . 'MB</b><br />';
			if (Tools::isSubmit ( 'btnSubmit' ))
            {
                $this->_html .= $this->l('Indexed pages:') . ' <b>' . $this->_nbLocs . '</b><br />';
            	$this->_html .= $this->l('Indexed images:') . ' <b>' . $this->_nbImages . '</b>';
            }
                
            $this->_html .= '</div>';
		}
	}
	
	private function _displayForm() {
		$this->_html .= '<script type="text/javascript">
			$.extend({
			  password: function (length, special) {
			    var iteration = 0;
			    var password = "";
			    var randomNumber;
			    if(special == undefined){
			        var special = false;
			    }
			    while(iteration < length){
			        randomNumber = (Math.floor((Math.random() * 100)) % 94) + 33;
			        if(!special){
			            if ((randomNumber >=33) && (randomNumber <=47)) { continue; }
			            if ((randomNumber >=58) && (randomNumber <=64)) { continue; }
			            if ((randomNumber >=91) && (randomNumber <=96)) { continue; }
			            if ((randomNumber >=123) && (randomNumber <=126)) { continue; }
			        }
			        iteration++;
			        password += String.fromCharCode(randomNumber);
			    }
			    return password;
			  }
			});
			$(document).ready(function() {
				$("#GADVSITEMAP_SECURE_KEY_GENERATE").click(function(){
		            var password = $.password(24,false);
			        $("#GADVSITEMAP_SECURE_KEY").hide().val(password).fadeIn("slow");
			        return false;
			    });
                
                $("#btnSubmit").click(function() {
                    if($("#form_gadvsitemap .lang_checkbox:checked").size() == 0)
                    {   
                        alert("' . $this->l('You must select at least one language.') . '");
                        return false;
                    }
                });
		    });
		</script>';
		
		$this->_html .= '<form id="form_gadvsitemap" action="' . Tools::htmlentitiesUTF8 ( $_SERVER ['REQUEST_URI'] ) . '" method="post">
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GSITEMAP_ALL_PRODUCTS" id="GSITEMAP_ALL_PRODUCTS" style="vertical-align: middle;" value="1" ' . (Configuration::get ( 'GSITEMAP_ALL_PRODUCTS' ) ? 'checked="checked"' : '') . ' /> <label class="t" for="GSITEMAP_ALL_PRODUCTS">' . $this->l('Sitemap also includes products from inactive categories') . '</label>
			</div>
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GSITEMAP_ALL_CMS" id="GSITEMAP_ALL_CMS" style="vertical-align: middle;" value="1" ' . (Configuration::get ( 'GSITEMAP_ALL_CMS' ) ? 'checked="checked"' : '') . ' /> <label class="t" for="GSITEMAP_ALL_CMS">' . $this->l('Sitemap also includes CMS pages which are not in a CMS block') . '</label>
			</div>
			
			<h2>' . $this->l('Search engines pinging') . '</h2>
			
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY" id="GADVSITEMAP_NOTIFY" style="vertical-align: middle;" value="1" ' . (Configuration::get ( 'GADVSITEMAP_NOTIFY' ) ? 'checked="checked"' : '') . ' /> <label class="t" for="GADVSITEMAP_NOTIFY">' . $this->l('Send this sitemap to Google index') . '</label>
			</div>
            <div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY_BING" id="GADVSITEMAP_NOTIFY_BING" style="vertical-align: middle;" value="1" ' . (Configuration::get ( 'GADVSITEMAP_NOTIFY_BING' ) ? 'checked="checked"' : '') . ' /> <label class="t" for="GADVSITEMAP_NOTIFY_BING">' . $this->l('Send this sitemap to Bing index (Yahoo index has moved to Bing)') . '</label>
			</div>
			
			<h2>' . $this->l('Languages') . '</h2>';
		$langs = Language::getLanguages ( true );
		foreach ( $langs as $lang ) 
        {
            $iso_code = $lang ['iso_code'];
			
			if (Configuration::get ( 'PS_REWRITING_SETTINGS' )) 
            {
				$url = Tools::getShopDomain ( true, true ) . __PS_BASE_URI__ . $lang ['iso_code'] . '/';
				if (Configuration::get ( 'GADVSITEMAP_' . $lang ['iso_code'] . '_URL' )) 
                {
					$url = Configuration::get ( 'GADVSITEMAP_' . $lang ['iso_code'] . '_URL' );
				}
			} 
            else
				$url = Tools::getShopDomain ( true, true ) . __PS_BASE_URI__;
			
			$this->_html .= '<div class="gadv_lang_checkbox">
					<input type="checkbox" name="' . $iso_code . '" id="' . $iso_code . '" class="lang_checkbox" style="vertical-align: middle;" value="1" ' . (Configuration::get ( $iso_code ) ? 'checked="checked"' : '') . ' /> 
                    <label class="t" for="' . $iso_code . '"><img src="'._PS_IMG_ .'l/'.$lang['id_lang'].'.jpg" />    '. $lang ['name'] . '</label><br />
					<label class="t">URL : <input type="text" name="' . $iso_code . '_url" id="' . $iso_code . '_url" size="50" value="' . $url . '"/></label>
				</div>';
		}
		
		if (! Configuration::get ( 'PS_REWRITING_SETTINGS' )) {
			$this->_html .= '<h3 class="alert error" style="margin-bottom: 20px">
										' . $this->l('Friendly URLs are required if you want to modify those.') . '
								 </h3>';
		}
		
		$this->_html .= '<h2>' . $this->l('Cron') . '</h2>
				<div style="margin:0 0 20px 0;">
					<label class="t">'. $this->l('Secure key : ').'
						<input type="text" size="40" name="GADVSITEMAP_SECURE_KEY" id="GADVSITEMAP_SECURE_KEY" value="'.Configuration::get ( 'GADVSITEMAP_SECURE_KEY' ).'" />
					</label>
					<button id="GADVSITEMAP_SECURE_KEY_GENERATE">'. $this->l('Generate') .'</button>';
		if (Configuration::get ( 'GADVSITEMAP_CRON_LAST' )) {
			$this->_html .= '<br />' . $this->l('Last runned') . ' : ' . date('Y-m-d H:i:s', Configuration::get ( 'GADVSITEMAP_CRON_LAST' ));
		}
        $url = Tools::getShopDomain ( true, true ) . __PS_BASE_URI__ . 'modules/gadvsitemap/cron.php?mode=cron&secure_key=[GENERATED_SECURE_KEY]';
		$this->_html .= '<br /><span>' . $this->l('Cron endpoint') . ' : ' . $url . '</span>';
		$this->_html .= '</div>';
		
		$this->_html .= '<input name="btnSubmit" class="button" type="submit" id="btnSubmit"
			value="' . ((! file_exists ( GSITEMAP_FILE )) ? $this->l('Generate sitemap file') : $this->l('Update sitemap file')) . '" />
		</form>';
	
	}
	
    private function _displayGitHubSummary()
    {
        $this->_html .= '<div class="gadv_right gadv_bloc gadv_align_right clear">';
        
        $tags = GitHub::getSingleRepoTags('seiyar81', 'prestashop-advanced-sitemap');
        if(!empty($tags))
        {
            $this->_html .= '<h4>'.$this->l('GitHub versions').'</h4>';
            $this->_html .= $this->l('Installed version') . ' : ' . $this->version . '<br /><br />';
            foreach($tags as $tag)
            {
                if(GitHub::compareTag($tag->name, $this->version))
                    $this->_html .= '<span class="gadv_red"><b>NEW</b></span> ';
                    
                $this->_html .= GitHub::formatDownloadLink($tag->name, $tag->zipball_url, $tag->tarball_url) . '<br />';
            }
        }
        
        $commits = GitHub::getSingleRepoLastCommit('seiyar81', 'prestashop-advanced-sitemap', 3);
        if(!empty($commits))
        {
            $this->_html .= '<h4>'.$this->l('Last GitHub commits').'</h4>';
            foreach($commits as $commit)
                $this->_html .= GitHub::formatCommit($commit) . '<br />';
        }
        
        $this->_html .= '</div>';
    }
    
    private function _displayRobots()
    {
        if(!file_exists(GROBOTS_FILE))
        {
            $this->_html .= '<div class="gadv_right gadv_bloc gadv_align_right clear">';
            $this->_html .= $this->l('It seems that you don\'t have any robots.txt file.<br /> You should generate one on the SEO & URLs admin page.');
            $this->_html .= '</div>';   
        }
        else
        {
            // Vérifier la présence du bon fichier sitemap
        }
    }
    
	public function getContent() {
        $this->_html .= '<style>'.file_get_contents(__DIR__ . "/css/gadvsitemap.css").'</style>';
        $this->_html .= '<div id="gadv_main" class="gadv_bloc">';
        
        if (Tools::isSubmit ( 'btnSubmit' )) {
			$this->_postValidation ();
			if (! count ( $this->_postErrors ))
				$this->_postProcess();
			else
				foreach ( $this->_postErrors as $err )
					$this->_html .= '<div class="alert error">' . $err . '</div>';
		}
        
        $this->_displaySitemap ();
        $this->_displayRobots();
        $this->_displayGitHubSummary ();
        
		$this->_html .= '<h1 id="gadv_main_title">' . $this->l('Prestashop Advanced Sitemap') . '</h1>
		' . $this->l('See') . ' <a href="http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=156184&from=40318&rd=1" style="font-weight:bold;text-decoration:underline;" target="_blank">
		' . $this->l('this page') . '</a> ' . $this->l('for more information') . '<br /><br />';
		
		$this->_displayForm();
		
        $this->_html .= '</div>';
        
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
		$this->hookaddproduct( $params );
	}
	
	public function hookupdateProductAttribute($params) 
    {
		$this->hookaddproduct( $params );
	}
	
	public function hookdeleteproduct($params) 
    {
		$this->hookaddproduct( $params );
	}
	
	public function cronTask() 
    {
		Configuration::updateValue ( 'GADVSITEMAP_CRON_LAST', time() );
		$this->_postProcess(false);
	}
    
    public function generateRobots()
    {
        $backup = null;
        if(file_exists(GROBOTS_FILE))
        {
            $backup = file_get_contents(GROBOTS_FILE);
        }
    }
	
}
