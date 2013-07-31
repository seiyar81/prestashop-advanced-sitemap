<?php
/*
* Prestashop Advanced Sitemap
*
*  @author Yriase <postmaster@yriase.fr>
*  @version  1.4.5.2
*
* THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE 
* INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR 
* BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER 
* RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER 
* TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
*
*/

include(dirname(__FILE__).'/../../config/defines.inc.php');
include(dirname(__FILE__).'/prestashopadvancedsitemap.php');
require(_PS_ADMIN_DIR_.'/functions.php');

if (isset($_GET['secure_key']))
{
	Configuration::loadConfiguration();
	$secureKey = Configuration::get('GADVSITEMAP_SECURE_KEY');
	if (!empty($secureKey) && $secureKey === $_GET['secure_key'])
	{
		$gadvsitemap = new prestashopadvancedsitemap();
        if(isset($_GET['mode']) && $_GET['mode'] == 'cron')
		    $gadvsitemap->cronTask();
        else if(isset($_GET['mode']) && $_GET['mode'] == 'robots')
            $gadvsitemap->generateRobots();
	}
}
