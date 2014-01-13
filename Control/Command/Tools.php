<?php

/**
 * flexContent
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/event
 * @copyright 2014 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\flexContent\Control\Command;

use Silex\Application;
use phpManufaktur\flexContent\Control\Configuration;
use phpManufaktur\flexContent\Data\Content\TagType;
use phpManufaktur\flexContent\Data\Content\Tag;

class Tools
{
    protected $app = null;
    protected $TagType = null;
    protected $Tag = null;
    protected static $config = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->TagType = new TagType($app);
        $this->Tag = new Tag($app);

        $Configuration = new Configuration($app);
        self::$config = $Configuration->getConfiguration();
    }

    /**
     * Get the permanent link base URL for the given language
     *
     * @param string $language
     * @return string
     */
    public function getPermalinkBaseURL($language)
    {
        return CMS_URL.str_ireplace('{language}', strtolower($language), self::$config['content']['permalink']['directory']);
    }

    /**
     * Highlight a search result
     *
     * @param string $word
     * @param string reference $content
     * @return string
     */
    public function highlightSearchResult($word, &$content)
    {
        if (!self::$config['search']['result']['highlight']) {
            return $content;
        }
        $replacement = self::$config['search']['result']['replacement'];
        $content = str_ireplace($word, str_ireplace('{word}', $word, $replacement), $content);
        return $content;
    }

    public function linkTags(&$content, $language)
    {
        if (!self::$config['content']['tag']['auto-link']['enabled'] || empty($content)) {
            return $content;
        }

        $link_replacement = self::$config['content']['tag']['auto-link']['replacement']['link'];
        $invalid_replacement = self::$config['content']['tag']['auto-link']['replacement']['invalid'];
        $unassigned_replacement = self::$config['content']['tag']['auto-link']['replacement']['unassigned'];
        $remove_sharp = self::$config['content']['tag']['auto-link']['remove-sharp'];
        $ellipsis = self::$config['content']['tag']['auto-link']['ellipsis'];

        //preg_match_all('/\B#(\w{2,64}(?!")\b)/i', $content, $matches, PREG_SET_ORDER);
        preg_match_all('%(?!<a[^>]*?>)(\B#(\w{2,64}(?!")\b))(?![^<]*?</a>)%i', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $tag_name = str_replace('_', ' ', $match[2]);
            if (false !== ($tag = $this->TagType->selectByName($tag_name, $language))) {
                if ($this->Tag->isAssigned($tag['tag_id'])) {
                    // replace #tag with a link
                    $search = array('{link}','{description}','{tag}');
                    $replace = array(
                        $this->getPermalinkBaseURL($language).'/tag/'.$tag['tag_permalink'],
                        (!empty($tag['tag_description'])) ? $this->app['utils']->Ellipsis($tag['tag_description'], $ellipsis) : $tag['tag_name'],
                        ($remove_sharp) ? $tag['tag_name'] : '#'.$tag['tag_name']
                    );
                    $tag_link = str_ireplace($search, $replace, $link_replacement);
                }
                else {
                    // this #tag is not assigned with any content
                    $tag_link = str_ireplace('{tag}', '#'.$tag_name, $unassigned_replacement);
                }
            }
            else {
                // invalid #tag
                $tag_link = str_ireplace('{tag}', '#'.$tag_name, $invalid_replacement);
            }
            $content = str_replace($match[0], $tag_link, $content);
        }
        return $content;
    }

}
