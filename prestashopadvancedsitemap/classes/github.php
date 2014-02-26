<?php 
/**
* Prestashop Advanced Sitemap
*
*  @author Yriase <postmaster@yriase.fr>
*  @version  1.5.1
*
* THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE 
* INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR 
* BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER 
* RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER 
* TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
*
*/

class GitHub
{
    static private $_apiBaseUrl = 'https://api.github.com';
    static private $_apiSingleRepoUrl = '/repos/$$OWNER$$/$$REPO$$';
    static private $_apiSingleRepoCommitsUrl = '/repos/$$OWNER$$/$$REPO$$/commits';
    static private $_apiSingleRepoTagsUrl = '/repos/$$OWNER$$/$$REPO$$/tags';
    
    static public function getSingleRepoLastCommit($user, $repo, $amount = 1)
    {
        $commits = array();
        try
        {
            $url = self::$_apiBaseUrl . self::populateUrl( array('$$OWNER$$' => $user, '$$REPO$$' => $repo ), self::$_apiSingleRepoCommitsUrl  );
            $content = json_decode(file_get_contents($url));
            if(is_array($content))
            {
                $current = 0;
                foreach($content as $commit)
                {
                    if($current >= $amount)
                        return $commits;
                    
                    $commits[] = $commit;
                    
                    ++$current;
                }
            }
        }
        catch(Exception $e)
        {
            echo 'Exception : ' . $e->getMessage();
        }
        return $commits;
    }
    
    static public function getSingleRepoTags($user, $repo)
    {
        $tags = array();
        try
        {
            $url = self::$_apiBaseUrl . self::populateUrl( array('$$OWNER$$' => $user, '$$REPO$$' => $repo ), self::$_apiSingleRepoTagsUrl  );
            $content = json_decode(file_get_contents($url));
            if(is_array($content))
            {
                foreach($content as $tag)
                {
                    $tags[] = $tag;
                }
            }
        }
        catch(Exception $e)
        {
            echo 'Exception : ' . $e->getMessage();
        }
        return $tags;
    }
    
    static public function compareTag($tagName, $version)
    {
        $tagName = str_replace('v', '', $tagName);
        return version_compare($tagName, $version, '>');
    }
    
    static public function formatDownloadLink($name, $zip, $tar)
    {
        return '<span class="gh_download_link">'.$name.' : <a href="'.$zip.'">ZIP</a> / <a href="'.$tar.'">TAR</a></span>';
    }
    
    static public function formatCommit($commit)
    {
        return '<span class="gh_commit">'.str_replace('-','<br />-',$commit->commit->message).'</span>';
    }
    
    static private function populateUrl( array $values, $url )
    {
        foreach($values as $key => $val)
            $url = str_replace($key, $val, $url);
        return $url;
    }
}
