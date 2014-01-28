<?php

/*
 * Prestashop Advanced Sitemap
 *
 *  @author Yriase <postmaster@yriase.fr>
 *  @version  1.5
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE 
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR 
 * BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER 
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER 
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 */

if (!defined('_PS_VERSION_'))
    exit();

require_once 'classes/padvlink.php';
require_once 'classes/github.php';

class prestashopadvancedsitemap extends Module {

    private $_html = '';
    private $_postErrors = array();
    private $_nbImages = 0;
    private $_nbLocs = 0;
    private $_imagesTypes = array();

    /**
     * Configuration
     */
    private $_google_url = 'http://www.google.com/webmasters/tools/ping?sitemap=';
    private $_bing_url = 'http://www.bing.com/webmaster/ping.aspx?siteMap=';
    
    /**
     * Multi
     */
    private $_multi_mode = 0;
    private $_multi_count = 0;
    private $_multi_item_count = 0;
    private $_multi_recommended_split = 10;
    
    /**
     * Compression
     */
    private $_compress = 0;
    
    public function __construct() {
        /**
         * Module
         */
        $this->name = 'prestashopadvancedsitemap';
        $this->tab = 'seo';
        $this->version = '1.5';
        $this->author = 'Yriase';
        $this->module_key = 'e45a405ffcd2e4aed2105bd05470ae7a';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Prestashop Advanced Sitemap');
        $this->description = $this->l('Generate your sitemap file with advanced options');

        if (!defined('GSITEMAP_FILE'))
            define('GSITEMAP_FILE', dirname(__FILE__) . '/../../sitemap.xml');
        if (!defined('GSITEMAP_FILE_MULTI'))
            define('GSITEMAP_FILE_MULTI', dirname(__FILE__) . '/../../sitemap_{{count}}.xml');
        if (!defined('GROBOTS_FILE'))
            define('GROBOTS_FILE', dirname(__FILE__) . '/../../robots.txt');
    }

    public function install() {
        // Install Module
        Configuration::updateValue('GADVSITEMAP_MULTI_SITEMAP_COUNT', 10000);

        if (!parent::install() || !$this->registerHook('addproduct') || !$this->registerHook('updateproduct') || !$this->registerHook('updateProductAttribute') || !$this->registerHook('deleteproduct'))
            return true;

        return true;
    }

    public function uninstall() {
        file_put_contents(GSITEMAP_FILE, '');
        return parent::uninstall();
    }

    private function _postValidation() {
        file_put_contents(GSITEMAP_FILE, '');
        if (!($fp = fopen(GSITEMAP_FILE, 'w')))
            $this->_postErrors [] = $this->l('Cannot create') . ' ' . realpath(dirname(__FILE__ . '/../..')) . '/' . $this->l('sitemap.xml file.');
        else
            fclose($fp);
    }

    private function getUrlWith($url, $key, $value) {
        if (empty($value))
            return $url;
        if (strpos($url, '?') !== false)
            return $url . '&' . $key . '=' . $value;
        return $url . '?' . $key . '=' . $value;
    }

    private function _postProcess($saveConfig = true, $generate = true) {
        try {
            $langs = array();
            $padvlink = new padvLink(Tools::getShopDomain(true, true) . __PS_BASE_URI__);
            $langsList = Language::getLanguages(true);
            if ($saveConfig) {
                Configuration::updateValue('GSITEMAP_ALL_CMS', (int) Tools::getValue('GSITEMAP_ALL_CMS'));
                Configuration::updateValue('GSITEMAP_ALL_PRODUCTS', (int) Tools::getValue('GSITEMAP_ALL_PRODUCTS'));
                Configuration::updateValue('GADVSITEMAP_NOTIFY', (int) Tools::getValue('GADVSITEMAP_NOTIFY'));
                Configuration::updateValue('GADVSITEMAP_NOTIFY_BING', (int) Tools::getValue('GADVSITEMAP_NOTIFY_BING'));

                Configuration::updateValue('GADVSITEMAP_MULTI_SITEMAP', (int) Tools::getValue('GADVSITEMAP_MULTI_SITEMAP'));
                Configuration::updateValue('GADVSITEMAP_MULTI_SITEMAP_COUNT', (int) Tools::getValue('GADVSITEMAP_MULTI_SITEMAP_COUNT'));

                Configuration::updateValue('GADVSITEMAP_GZ', (int) Tools::getValue('GADVSITEMAP_GZ'));
                
                $this->_compress = Configuration::get('GADVSITEMAP_GZ');
                $this->_multi_mode = Configuration::get('GADVSITEMAP_MULTI_SITEMAP');
                
                foreach ($langsList as $lang) {
                    Configuration::updateValue($lang ['iso_code'], (int) Tools::getValue($lang ['iso_code']));

                    if (Tools::getIsset($lang ['iso_code']) && Tools::getIsset($lang ['iso_code'] . '_url')) {
                        Configuration::updateValue('GADVSITEMAP_' . $lang ['iso_code'] . '_URL', Tools::getValue($lang ['iso_code'] . '_url'));
                        $padvlink->setLangUrl($lang ['id_lang'], Tools::getValue($lang ['iso_code'] . '_url'));
                        $langs [] = $lang;
                    }
                }

                Configuration::updateValue('GADVSITEMAP_SECURE_KEY', (string) Tools::getValue('GADVSITEMAP_SECURE_KEY'));
            } else {
                foreach ($langsList as $lang) {
                    if (Configuration::get('GADVSITEMAP_' . $lang ['iso_code'] . '_URL')) {
                        $padvlink->setLangUrl($lang ['id_lang'], Configuration::get('GADVSITEMAP_' . $lang ['iso_code'] . '_URL'));
                        $langs [] = $lang;
                    }
                }
            }

            if (empty($langs)) {
                throw new Exception($this->l('You must select at least one language.'));
            }

            if($generate === false) {
                $this->_html .= '<h3 class="conf confirm" style="margin-bottom: 20px">';
                $this->_html .= $this->l('Settings succesfully saved');
                $this->_html .= '</h3>';
                return 0;  
            }
            
            $this->_imagesTypes = ImageType::getImagesTypes('products');

            $this->_nbImages = 0;
            
            $xml = $this->_newSitemap();
            $lang_list = '';
            if (Configuration::get('PS_REWRITING_SETTINGS') && count($langs) > 1) {
                foreach ($langs as $lang) {
                    if (Configuration::get($lang ['iso_code'])) {
                        $langUrl = $padvlink->getLangUrl($lang);
                        if (strlen($langUrl)) {
                            $this->_addSitemapNode($xml, $langUrl, '1.00', 'daily', date('Y-m-d'));
                            $lang_list .= ($lang_list != '') ? ', ' : '';
                            $lang_list .= $lang ['id_lang'];
                        }
                    }
                }
            } else
                $this->_addSitemapNode($xml, $padvlink->getBaseUrl(), '1.00', 'daily', date('Y-m-d'));

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
				' . (Configuration::get('GSITEMAP_ALL_PRODUCTS') ? '' : 'HAVING level_depth IS NOT NULL') . '
				ORDER BY pl.id_product, pl.id_lang ASC';

            $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_products);

            $tmp = null;
            $res = null;
            $done = null;

            if (Configuration::get('PS_REWRITING_SETTINGS')) {
                foreach ($products as &$product) {
                    if (($priority = 0.7 - ($product ['level_depth'] / 10)) < 0.1)
                        $priority = 0.1;

                    $sql_images = 'SELECT i.id_image, il.legend legend_image 
                                        FROM ' . _DB_PREFIX_ . 'image i
                                        LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . $product ['id_lang'] . ')
                                        WHERE i.id_product = ' . $product ['id_product'];
                    $product ['images'] = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_images);

                    $tmpLink = $padvlink->getProductLink((int) ($product ['id_product']), $product ['link_rewrite'], $product ['category'], $product ['ean13'], $product ['id_lang']);
                    if (strlen($tmpLink)) {
                        $sitemap = $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($product ['date_upd'], 0, 10));
                        $sitemap = $this->_addSitemapNodeImage($sitemap, $product, $padvlink, $product ['id_lang']);
                    }
                    
                    $xml = $this->_checkMultiSitemap($xml);
                }
            } else {
                foreach ($products as &$product) {
                    if (($priority = 0.7 - ($product ['level_depth'] / 10)) < 0.1)
                        $priority = 0.1;

                    $tmpLink = $padvlink->getProductLink((int) ($product ['id_product']), $product ['link_rewrite'], $product ['category'], $product ['ean13'], (int) ($product ['id_lang']));
                    if (strlen($tmpLink)) {
                        $sitemap = $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($product ['date_upd'], 0, 10));
                        $sitemap = $this->_addSitemapNodeImage($sitemap, $product, $padvlink, $lang);
                    }
                    
                    $xml = $this->_checkMultiSitemap($xml);
                }
            }

            /* Categories Generator */
            if (Configuration::get('PS_REWRITING_SETTINGS'))
                $categories = Db::getInstance()->ExecuteS('
			SELECT c.id_category, c.level_depth, link_rewrite, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') AS date_upd, cl.id_lang
			FROM ' . _DB_PREFIX_ . 'category c
			LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON c.id_category = cl.id_category
			LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (cl.id_lang = l.id_lang ' . $lang_list . ')
			WHERE l.`active` = 1 AND c.`active` = 1 AND c.id_category != 1
			ORDER BY cl.id_category, cl.id_lang ASC');
            else
                $categories = Db::getInstance()->ExecuteS('SELECT c.id_category, c.level_depth, DATE_FORMAT(IF(date_upd,date_upd,date_add), \'%Y-%m-%d\') AS date_upd
			FROM ' . _DB_PREFIX_ . 'category c
			ORDER BY c.id_category ASC');

            foreach ($categories as $category) {
                if (Configuration::get('PS_REWRITING_SETTINGS')) {
                    if (($priority = 0.9 - ($category ['level_depth'] / 10)) < 0.1)
                        $priority = 0.1;

                    $tmpLink = $padvlink->getCategoryLink((int) $category ['id_category'], $category ['link_rewrite'], (int) $category ['id_lang']);
                    if (strlen($tmpLink)) {
                        $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($category ['date_upd'], 0, 10));
                    }
                    
                    $xml = $this->_checkMultiSitemap($xml);
                } else {
                    if (($priority = 0.9 - ($category ['level_depth'] / 10)) < 0.1)
                        $priority = 0.1;

                    $tmpLink = $padvlink->getCategoryLink((int) $category ['id_category']);
                    if (strlen($tmpLink)) {
                        $this->_addSitemapNode($xml, htmlspecialchars($tmpLink), $priority, 'weekly', substr($category ['date_upd'], 0, 10));
                    }
                    
                    $xml = $this->_checkMultiSitemap($xml);
                }
            }

            /* CMS Generator */
            if (Configuration::get('GSITEMAP_ALL_CMS') || !Module::isInstalled('blockcms'))
                $sql_cms = '
			SELECT DISTINCT ' . (Configuration::get('PS_REWRITING_SETTINGS') ? 'cl.id_cms, cl.link_rewrite, cl.id_lang' : 'cl.id_cms') . ' FROM ' . _DB_PREFIX_ . 'cms_lang cl
			LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (cl.id_lang = l.id_lang ' . $lang_list . ')
			WHERE l.`active` = 1
			ORDER BY cl.id_cms, cl.id_lang ASC';
            else if (Module::isInstalled('blockcms'))
                $sql_cms = '
			SELECT DISTINCT ' . (Configuration::get('PS_REWRITING_SETTINGS') ? 'cl.id_cms, cl.link_rewrite, cl.id_lang' : 'cl.id_cms') . ' FROM ' . _DB_PREFIX_ . 'cms_block_page b
			LEFT JOIN ' . _DB_PREFIX_ . 'cms_lang cl ON (b.id_cms = cl.id_cms)
			LEFT JOIN ' . _DB_PREFIX_ . 'lang l ON (cl.id_lang = l.id_lang ' . $lang_list . ')
			WHERE l.`active` = 1
			ORDER BY cl.id_cms, cl.id_lang ASC';

            $cmss = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_cms);
            foreach ($cmss as $cms) {
                if (Configuration::get('PS_REWRITING_SETTINGS')) {
                    $tmpLink = $padvlink->getCMSLink((int) $cms ['id_cms'], $cms ['link_rewrite'], false, (int) $cms ['id_lang']);
                    if (strlen($tmpLink)) {
                        $this->_addSitemapNode($xml, $tmpLink, '0.8', 'daily');
                    }
                    
                    $xml = $this->_checkMultiSitemap($xml);
                } else {
                    $tmpLink = $padvlink->getCMSLink((int) $cms ['id_cms']);
                    if (strlen($tmpLink)) {
                        $this->_addSitemapNode($xml, $tmpLink, '0.8', 'daily');
                    }
                    
                    $xml = $this->_checkMultiSitemap($xml);
                }
            }

            /* Add classic pages (contact, best sales, new products...) */
            $pages = array('supplier' => false, 'manufacturer' => false, 'new-products' => false, 'prices-drop' => false, 'stores' => false, 'authentication' => true, 'best-sales' => false, 'contact-form' => true);

            // Don't show suppliers and manufacturers if they are disallowed
            if (!Module::getInstanceByName('blockmanufacturer')->id && !Configuration::get('PS_DISPLAY_SUPPLIERS'))
                unset($pages ['manufacturer']);

            if (!Module::getInstanceByName('blocksupplier')->id && !Configuration::get('PS_DISPLAY_SUPPLIERS'))
                unset($pages ['supplier']);

            // Generate nodes for pages
            if (Configuration::get('PS_REWRITING_SETTINGS'))
                foreach ($pages as $page => $ssl)
                    foreach ($langs as $lang) {
                        $this->_addSitemapNode($xml, $padvlink->getPageLink($page . '.php', $ssl, $lang ['id_lang']), '0.5', 'monthly');
                        
                        $xml = $this->_checkMultiSitemap($xml);
                    }
            else
                foreach ($pages as $page => $ssl) {
                    $this->_addSitemapNode($xml, $padvlink->getPageLink($page . '.php', $ssl), '0.5', 'monthly');
                    
                    $xml = $this->_checkMultiSitemap($xml);
                }

            if($this->_multi_mode) {
                $this->_multi_count++;
                
                if($this->_multi_count > 1) {
                    $fileName = str_replace("{{count}}", $this->_multi_count, GSITEMAP_FILE_MULTI);
                    $this->_saveFormattedSitemap($xml, $fileName);     
                
                    $this->_createSitemapIndex();
                } else 
                    $this->_saveFormattedSitemap($xml);
            } else
                $this->_saveFormattedSitemap($xml);

            if (Configuration::get('GADVSITEMAP_NOTIFY')) {
                if (@file_get_contents( $this->_google_url . 'http://' . Tools::getHttpHost(false, true) . __PS_BASE_URI__ . 'sitemap.xml'))
                    $pinged = true;
                else
                    $pinged = false;
            }
            if (Configuration::get('GADVSITEMAP_NOTIFY_BING')) {
                if (@file_get_contents($this->_bing_url . 'http://' . Tools::getHttpHost(false, true) . __PS_BASE_URI__ . 'sitemap.xml'))
                    $bingPinged = true;
                else
                    $bingPinged = false;
            }

            $res = file_exists(GSITEMAP_FILE);
            $this->_html .= '<h3 class="' . ($res ? 'conf confirm' : 'alert error') . '" style="margin-bottom: 20px">';
            $this->_html .= $res ? $this->l('Sitemap file generated') : $this->l('Error while creating sitemap file');
            $this->_html .= '</h3>';

            if (Configuration::get('GADVSITEMAP_NOTIFY')) {
                $this->_html .= '<h3 class="' . ($pinged ? 'conf confirm' : 'alert error') . '" style="margin-bottom: 20px">';
                $this->_html .= $pinged ? $this->l('Google successfully pinged') : $this->l('Error while pinging Google');
                $this->_html .= '</h3>';
            }
            if (Configuration::get('GADVSITEMAP_NOTIFY_BING')) {
                $this->_html .= '<h3 class="' . ($bingPinged ? 'conf confirm' : 'alert error') . '" style="margin-bottom: 20px">';
                $this->_html .= $bingPinged ? $this->l('Bing successfully pinged') : $this->l('Error while pinging Bing');
                $this->_html .= '</h3>';
            }
        } catch (Exception $e) {
            $this->_html = '<h3 class="alert error" style="margin-bottom: 20px">';
            $this->_html .= $this->l('An unknown error occured during the sitemap generation.') . '<br />';
            $this->_html .= $e->getMessage();
            $this->_html .= '</h3>';
        }
    }

    private function _newSitemap() {
        $xmlString = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
</urlset>
XML;

        return new SimpleXMLElement($xmlString);
    }
    
    private function _checkMultiSitemap($xml) {
        // We have reached the maximum url count so we save the current sitemap and create another one
        if($this->_multi_mode && $this->_multi_item_count > Configuration::get("GADVSITEMAP_MULTI_SITEMAP_COUNT")) {
            $this->_multi_item_count = 0;
            $this->_multi_count++;

            $fileName = str_replace("{{count}}", $this->_multi_count, GSITEMAP_FILE_MULTI);

            $this->_saveFormattedSitemap($xml, $fileName);

            return $this->_newSitemap();
        } else {
            return $xml;
        }
    }
    
    private function _createSitemapIndex() {
        $xmlString = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
</sitemapindex>
XML;
        $xml = new SimpleXMLElement($xmlString);
        
        for($i = 1; $i <= $this->_multi_count; $i++) {
            $fileName = 'http://' . Tools::getHttpHost(false, true) . __PS_BASE_URI__ . 'sitemap_'.$i.'.xml';
            
            if($this->_compress)
                $fileName .= ".gz";
            
            $sitemap = $xml->addChild("sitemap");
            $sitemap->addChild("loc", $fileName);
            $sitemap->addChild("lastmod", date("Y-m-d"));
        }
        
        $this->_saveFormattedSitemap($xml);
    }
    
    private function _saveFormattedSitemap($xml, $fileName = null) {
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        if($fileName === null)
            $fileName = GSITEMAP_FILE;
        
        $dom->save($fileName);
        
        if($this->_compress) {
            $gzFileName = $fileName . ".gz";
            
            $zp = gzopen($gzFileName, "w9");
            gzwrite($zp, file_get_contents($fileName));
            gzclose($zp);
        }
    }

    private function _addSitemapNode($xml, $loc, $priority, $change_freq, $last_mod = NULL) {
        $this->_nbLocs++;      
        $sitemap = $xml->addChild('url');
        $sitemap->addChild('loc', $loc);
        $sitemap->addChild('priority', number_format($priority, 1, '.', ''));
        if ($last_mod)
            $sitemap->addChild('lastmod', $last_mod);
        $sitemap->addChild('changefreq', $change_freq);
        
        $this->_multi_item_count++;
        
        return $sitemap;
    }

    private function _addSitemapNodeImage($xml, $product, $link, $lang) {
        if (isset($product ['images']))
            foreach ($product ['images'] as $img) {

                if (!empty($this->_imagesTypes)) {
                    $image = $xml->addChild('image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
                    foreach ($this->_imagesTypes as $imgType) {
                        $this->_nbImages++;

                        if (Configuration::get('PS_REWRITING_SETTINGS'))
                            $tmpLink = $link->getImageLink($product ['link_rewrite'], (int) $product ['id_product'] . '-' . (int) $img ['id_image'], $imgType ['name'], $lang);
                        else
                            $tmpLink = $link->getImageLink($product ['link_rewrite'], (int) $product ['id_product'] . '-' . (int) $img ['id_image'], $imgType ['name']);

                        $image->addChild('loc', $tmpLink, 'http://www.google.com/schemas/sitemap-image/1.1');

                        $legend_image = preg_replace('/(&+)/i', '&amp;', $img ['legend_image']);
                        $image->addChild('caption', $legend_image, 'http://www.google.com/schemas/sitemap-image/1.1');
                        $image->addChild('title', $legend_image, 'http://www.google.com/schemas/sitemap-image/1.1');
                    }
                }
            }
    }

    private function _displaySitemap() {
		$fileName = GSITEMAP_FILE;
		if($this->_compress) 
			$fileName .= ".gz";
	
        if (file_exists($fileName) && filesize($fileName)) {
            $fp = fopen($fileName, 'r');
            $fstat = fstat($fp);
            fclose($fp);
            
            $sitemapUrl = Tools::getShopDomain(true, true) . __PS_BASE_URI__ . 'sitemap.xml';
            if($this->_compress)
                $sitemapUrl .= ".gz";

            $this->_html .= '<div class="gadv_right gadv_bloc"><p>' . $this->l('Your sitemap file is online at the following address:') . '<br />
			<a href="'.$sitemapUrl.'" target="_blank"><b>'.$sitemapUrl.'</b></a></p><br />';

            if($this->_multi_mode && $this->_multi_count > 0) {
                $this->_html .= $this->l('Multiple generated sitemap:') . ' <b>' . $this->_multi_count . '</b><br />';
            }
            $this->_html .= $this->l('Update:') . ' <b>' . utf8_encode(strftime('%A %d %B %Y %H:%M:%S', $fstat ['mtime'])) . '</b><br />';
            $this->_html .= $this->l('Filesize:') . ' <b>' . number_format(($fstat ['size'] * .000001), 4) . 'MB</b><br />';
            if (Tools::isSubmit('btnSubmit')) {
                $this->_html .= $this->l('Indexed pages:') . ' <b>' . $this->_nbLocs . '</b><br />';
                $this->_html .= $this->l('Indexed images:') . ' <b>' . $this->_nbImages . '</b>';
            }

            $this->_html .= '</div>';
        }
    }

    private function _displayForm() {

        $this->_html .= '<form id="form_gadvsitemap" action="' . Tools::htmlentitiesUTF8($_SERVER ['REQUEST_URI']) . '" method="post">
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GSITEMAP_ALL_PRODUCTS" id="GSITEMAP_ALL_PRODUCTS" style="vertical-align: middle;" value="1" ' . (Configuration::get('GSITEMAP_ALL_PRODUCTS') ? 'checked="checked"' : '') . ' /> <label class="t" for="GSITEMAP_ALL_PRODUCTS">' . $this->l('Sitemap also includes products from inactive categories') . '</label>
			</div>
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GSITEMAP_ALL_CMS" id="GSITEMAP_ALL_CMS" style="vertical-align: middle;" value="1" ' . (Configuration::get('GSITEMAP_ALL_CMS') ? 'checked="checked"' : '') . ' /> <label class="t" for="GSITEMAP_ALL_CMS">' . $this->l('Sitemap also includes CMS pages which are not in a CMS block') . '</label>
			</div>
			
			<h2>' . $this->l('Search engines pinging') . '</h2>
			
			<div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY" id="GADVSITEMAP_NOTIFY" style="vertical-align: middle;" value="1" ' . (Configuration::get('GADVSITEMAP_NOTIFY') ? 'checked="checked"' : '') . ' /> <label class="t" for="GADVSITEMAP_NOTIFY">' . $this->l('Send this sitemap to Google index') . '</label>
			</div>
            <div style="margin:0 0 20px 0;">
				<input type="checkbox" name="GADVSITEMAP_NOTIFY_BING" id="GADVSITEMAP_NOTIFY_BING" style="vertical-align: middle;" value="1" ' . (Configuration::get('GADVSITEMAP_NOTIFY_BING') ? 'checked="checked"' : '') . ' /> <label class="t" for="GADVSITEMAP_NOTIFY_BING">' . $this->l('Send this sitemap to Bing index (Yahoo index has moved to Bing)') . '</label>
			</div>
			
			<h2>' . $this->l('Languages') . '</h2>';
        $langs = Language::getLanguages(true);
        foreach ($langs as $lang) {
            $iso_code = $lang ['iso_code'];

            if (Configuration::get('PS_REWRITING_SETTINGS')) {
                $url = Tools::getShopDomain(true, true) . __PS_BASE_URI__ . $lang ['iso_code'] . '/';
                if (Configuration::get('GADVSITEMAP_' . $lang ['iso_code'] . '_URL')) {
                    $url = Configuration::get('GADVSITEMAP_' . $lang ['iso_code'] . '_URL');
                }
            } else
                $url = Tools::getShopDomain(true, true) . __PS_BASE_URI__;

            $this->_html .= '<div class="gadv_lang_checkbox">
					<input type="checkbox" name="' . $iso_code . '" id="' . $iso_code . '" class="lang_checkbox" style="vertical-align: middle;" value="1" ' . (Configuration::get($iso_code) ? 'checked="checked"' : '') . ' /> 
                    <label class="t" for="' . $iso_code . '"><img src="' . _PS_IMG_ . 'l/' . $lang['id_lang'] . '.jpg" />    ' . $lang ['name'] . '</label><br />
					<label class="t">URL : <input type="text" name="' . $iso_code . '_url" id="' . $iso_code . '_url" size="50" value="' . $url . '"/></label>
				</div>';
        }

        if (!Configuration::get('PS_REWRITING_SETTINGS')) {
            $this->_html .= '<h3 class="alert error" style="margin-bottom: 20px">
										' . $this->l('Friendly URLs are required if you want to modify those.') . '
								 </h3>';
        }

        $this->_html .= '<h2>' . $this->l('Cron') . '</h2>
				<div style="margin:0 0 20px 0;">
					<label class="t">' . $this->l('Secure key : ') . '
						<input type="text" size="40" name="GADVSITEMAP_SECURE_KEY" id="GADVSITEMAP_SECURE_KEY" value="' . Configuration::get('GADVSITEMAP_SECURE_KEY') . '" />
					</label>
					<button id="GADVSITEMAP_SECURE_KEY_GENERATE">' . $this->l('Generate') . '</button>';
        if (Configuration::get('GADVSITEMAP_CRON_LAST')) {
            $this->_html .= '<br />' . $this->l('Last runned') . ' : ' . date('Y-m-d H:i:s', Configuration::get('GADVSITEMAP_CRON_LAST'));
        }
        $url = Tools::getShopDomain(true, true) . __PS_BASE_URI__ . 'modules/prestashopadvancedsitemap/cron.php?mode=cron&secure_key=[GENERATED_SECURE_KEY]';
        $this->_html .= '<br /><br /><p class="t">' . $this->l('Cron endpoint') . ' : ' . $url . '</p>';
        $this->_html .= '</div>';

        $this->_html .= '<h2>' . $this->l('Compression') . '</h2>
				<div style="margin:0 0 20px 0;">';
        $this->_html .= '<input type="checkbox" name="GADVSITEMAP_GZ" id="GADVSITEMAP_GZ" class="checkbox" style="vertical-align: middle;" value="1" ' . (Configuration::get("GADVSITEMAP_GZ") ? 'checked="checked"' : '') . ' />
							<label class="t" for="GADVSITEMAP_GZ">' . $this->l('Compress the generated sitemaps.') . '</label><br /><br />';
        
        
        $this->_html .= '<h2>' . $this->l('Multi-sitemap') . '</h2>
				<div style="margin:0 0 20px 0;">';

        $this->_html .= '<input type="checkbox" name="GADVSITEMAP_MULTI_SITEMAP" id="GADVSITEMAP_MULTI_SITEMAP" class="checkbox" style="vertical-align: middle;" value="1" ' . (Configuration::get("GADVSITEMAP_MULTI_SITEMAP") ? 'checked="checked"' : '') . ' />
							<label class="t" for="GADVSITEMAP_MULTI_SITEMAP">' . $this->l('Automatically generate multiple sitemaps.') . '</label><br /><br />';
        $this->_html .= '<label class="t" for="GADVSITEMAP_MULTI_SITEMAP_COUNT">' . $this->l('Split the sitemap every') . '</label>
							<input type="text" name="GADVSITEMAP_MULTI_SITEMAP_COUNT" id="GADVSITEMAP_MULTI_SITEMAP_COUNT" value="' . Configuration::get("GADVSITEMAP_MULTI_SITEMAP_COUNT") . '" />
							<label class="t" for="GADVSITEMAP_MULTI_SITEMAP_COUNT">' . $this->l('urls') . '</label>';
        
        $urlCount = $this->_getEstimatedSitemapLinks();
        $this->_html .= '<p class="t">'.$this->l('The estimated url count for your sitemap is : '). '<b>'. $urlCount .'</b><br />'
                . $this->l('Google allows a single sitemap file to reference up to 50 000 URLs, so a reasonnable split value would be 10 000 (this images are not included).').'<br />'
                . $this->l('If your file is too big for Google (> 50 Mb) you should consider lowering this number.').'</p>';

        $this->_html .= '</div>';


        $this->_html .= '<input name="btnSubmit" class="button" type="submit" id="btnSubmit"
                            value="' . ((!file_exists(GSITEMAP_FILE)) ? $this->l('Generate sitemap file') : $this->l('Update sitemap file')) . '" />
                         <input name="btnSave" class="button" type="submit" id="btnSave"
                            value="' . $this->l('Save settings') . '" />
		</form>';
    }

    private function _displayGitHubSummary() {
        

        $tags = GitHub::getSingleRepoTags('seiyar81', 'prestashop-advanced-sitemap');
        if (!empty($tags)) {
            $this->_html .= '<div class="gadv_right gadv_bloc gadv_align_right clear">';
            $this->_html .= '<h4>' . $this->l('GitHub versions') . '</h4>';
            $this->_html .= $this->l('Installed version') . ' : ' . $this->version . '<br /><br />';
            foreach ($tags as $tag) {
                if (GitHub::compareTag($tag->name, $this->version))
                    $this->_html .= '<span class="gadv_red"><b>NEW</b></span> ';

                $this->_html .= GitHub::formatDownloadLink($tag->name, $tag->zipball_url, $tag->tarball_url) . '<br />';
            }
        }

        $commits = GitHub::getSingleRepoLastCommit('seiyar81', 'prestashop-advanced-sitemap', 3);
        if (!empty($commits)) {
            if(empty($tags))
                $this->_html .= '<div class="gadv_right gadv_bloc gadv_align_right clear">';
            $this->_html .= '<h4>' . $this->l('Last GitHub commits') . '</h4>';
            foreach ($commits as $commit)
                $this->_html .= GitHub::formatCommit($commit) . '<br />';
        }

        if(!empty($tags) || !empty($commits))
            $this->_html .= '</div>';
    }

    private function _displayRobots() {
        if (!file_exists(GROBOTS_FILE)) {
            $this->_html .= '<div class="gadv_right gadv_bloc gadv_align_right clear">';
            $this->_html .= $this->l('It seems that you don\'t have any robots.txt file.<br /> You should generate one on the SEO & URLs admin page.');
            $this->_html .= '</div>';
        } else {
            // Vérifier la présence du bon fichier sitemap
        }
    }

    private function _displayPaypal() {
        $this->_html .= '<div class="gadv_right gadv_bloc gadv_align_right clear">';
        $this->_html .= '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHJwYJKoZIhvcNAQcEoIIHGDCCBxQCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYB45LVF68ijrraEP5DNil8HubtuG8uke9H3kso42jsjQOYs7BURTIDPnaV5gSM0XA095ARUicBN4xyQCN0PjDw0xwwgFyfNmMQybm0Q9Xx9V2WFNsj2YTLRz7APmbuN0DJE21zbMG+ZvUDmJwSmASxT6RYCqXU1qz78WIYx8zjP1zELMAkGBSsOAwIaBQAwgaQGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQIf87fue/vO4eAgYDGnwr4Wziv+QguFgMswy8TH1JxbvWIWYB5wnVM0uCuZxq5HnQqFbHUFis05aktGxhg+fAebe3a43LiVNgAm3wJPy7IY4vo7A96McV2HQvNJ67Z21EWA7ZdAg6mflcQBkeUPw6ARgjKk0PwItwVANhu02SxVXw4gI3mpWC6k9kvAaCCA4cwggODMIIC7KADAgECAgEAMA0GCSqGSIb3DQEBBQUAMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTAeFw0wNDAyMTMxMDEzMTVaFw0zNTAyMTMxMDEzMTVaMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTCBnzANBgkqhkiG9w0BAQEFAAOBjQAwgYkCgYEAwUdO3fxEzEtcnI7ZKZL412XvZPugoni7i7D7prCe0AtaHTc97CYgm7NsAtJyxNLixmhLV8pyIEaiHXWAh8fPKW+R017+EmXrr9EaquPmsVvTywAAE1PMNOKqo2kl4Gxiz9zZqIajOm1fZGWcGS0f5JQ2kBqNbvbg2/Za+GJ/qwUCAwEAAaOB7jCB6zAdBgNVHQ4EFgQUlp98u8ZvF71ZP1LXChvsENZklGswgbsGA1UdIwSBszCBsIAUlp98u8ZvF71ZP1LXChvsENZklGuhgZSkgZEwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tggEAMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEFBQADgYEAgV86VpqAWuXvX6Oro4qJ1tYVIT5DgWpE692Ag422H7yRIr/9j/iKG4Thia/Oflx4TdL+IFJBAyPK9v6zZNZtBgPBynXb048hsP16l2vi0k5Q2JKiPDsEfBhGI+HnxLXEaUWAcVfCsQFvd2A1sxRr67ip5y2wwBelUecP3AjJ+YcxggGaMIIBlgIBATCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwCQYFKw4DAhoFAKBdMBgGCSqGSIb3DQEJAzELBgkqhkiG9w0BBwEwHAYJKoZIhvcNAQkFMQ8XDTE0MDEyODEzMjczNVowIwYJKoZIhvcNAQkEMRYEFPQbbXZcyserYzYuokanf3dGzuw5MA0GCSqGSIb3DQEBAQUABIGAOwXt2FdH1ZxBjExGWBpfJ1BG+aTqK5gKvvJtlAKKtW1+lAchiY9FgMJpL/r9DBSVobpszE+igSKK0VEtg8iR3LQ61XSZw2KmS6yeYjkEKm+xbcvLRryKFJ1c7R6GiLuEbjBDjf3oolgi4DRUIClPv6+j7Wv5NGSKvogv4DytD6k=-----END PKCS7-----
">
<input type="image" src="https://www.paypalobjects.com/fr_FR/FR/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - la solution de paiement en ligne la plus simple et la plus sécurisée !">
<img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
</form>';
        $this->_html .= '</div>';
    }
    
    public function getContent() {
		$this->_compress = Configuration::get('GADVSITEMAP_GZ', false);
        $this->_multi_mode = Configuration::get('GADVSITEMAP_MULTI_SITEMAP', false);
	
        $this->_html .= '<style>' . file_get_contents(__DIR__ . "/css/padvsitemap.css") . '</style>';
        $this->_html .= '<script type="text/javascript">var languageError = "'.$this->l('You must select at least one language.').'";'
                            . file_get_contents(__DIR__ . "/js/padvsitemap.js") . '</script>';
        $this->_html .= '<div id="gadv_main" class="gadv_bloc">';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors as $err)
                    $this->_html .= '<div class="alert error">' . $err . '</div>';
        }
        
        if (Tools::isSubmit('btnSave')) {
            $this->_postValidation();
            if (!count($this->_postErrors))
                $this->_postProcess(true, false);
        }

        $this->_displaySitemap();
        $this->_displayRobots();
        $this->_displayGitHubSummary();
        $this->_displayPaypal();
        
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
    public function hookaddproduct($params) {
        $this->_postProcess();
    }

    public function hookupdateproduct($params) {
        $this->hookaddproduct($params);
    }

    public function hookupdateProductAttribute($params) {
        $this->hookaddproduct($params);
    }

    public function hookdeleteproduct($params) {
        $this->hookaddproduct($params);
    }

    public function cronTask() {
        Configuration::updateValue('GADVSITEMAP_CRON_LAST', time());
        $this->_postProcess(false);
    }

    public function generateRobots() {
        $backup = null;
        if (file_exists(GROBOTS_FILE)) {
            $backup = file_get_contents(GROBOTS_FILE);
        }
    }
    
    private function _getEstimatedSitemapLinks() {
        $sql_products = 'SELECT COUNT(p.id_product) AS nb FROM ' . _DB_PREFIX_ . 'product p';
        $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_products);
        
        $sql_cms = 'SELECT COUNT(DISTINCT cl.id_cms) AS nb FROM ' . _DB_PREFIX_ . 'cms_lang cl';
        $cms = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_cms);
        
        $sql_cat = 'SELECT COUNT(DISTINCT c.id_category) AS nb FROM ' . _DB_PREFIX_ . 'category c';
        $cat = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($sql_cat);
        
        $pages = array('supplier' => false, 'manufacturer' => false, 'new-products' => false, 'prices-drop' => false, 'stores' => false, 'authentication' => true, 'best-sales' => false, 'contact-form' => true);
        // Don't show suppliers and manufacturers if they are disallowed
        if (!Module::getInstanceByName('blockmanufacturer')->id && !Configuration::get('PS_DISPLAY_SUPPLIERS'))
            unset($pages ['manufacturer']);
        if (!Module::getInstanceByName('blocksupplier')->id && !Configuration::get('PS_DISPLAY_SUPPLIERS'))
            unset($pages ['supplier']);
        
        return (((int)$products[0]['nb'] + (int)$cms[0]['nb'] + (int)$cat[0]['nb'] + count($pages)) * count(Language::getLanguages(true)));
    }

}
