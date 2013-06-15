<?php 
/*
* Prestashop Advanced Sitemap
*
*  @author Yriase <postmaster@yriase.fr>
*  @version  1.4.4
*/

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/gadvsitemap.php');

if (isset($_GET['mode']) && $_GET['mode'] == 'cron' && isset($_GET['secure_key']))
{
	Configuration::loadConfiguration();
	$secureKey = Configuration::get('GADVSITEMAP_SECURE_KEY');
	if (!empty($secureKey) && $secureKey === $_GET['secure_key'])
	{
		$gadvsitemap = new gadvsitemap();
		$gadvsitemap->cronTask();
	}
}
