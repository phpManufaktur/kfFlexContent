<?php

/**
 * flexContent
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/event
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\flexContent\Control\Command;

use phpManufaktur\Basic\Control\kitCommand\Basic;
use Silex\Application;
use phpManufaktur\flexContent\Control\Configuration;
use phpManufaktur\flexContent\Data\Content\Category;
use phpManufaktur\flexContent\Data\Content\Content;
use phpManufaktur\flexContent\Data\Content\Tag;
use phpManufaktur\flexContent\Data\Content\TagType;

class ActionTag extends Basic
{
    protected static $parameter = null;
    protected static $config = null;
    protected static $language = null;

    protected $TagData = null;
    protected $TagTypeData = null;
    protected $CategoryData = null;
    protected $ContentData = null;
    protected $Tools = null;

    protected static $view_array = array('content', 'teaser','none');

    /**
     * (non-PHPdoc)
     * @see \phpManufaktur\Basic\Control\kitCommand\Basic::initParameters()
     */
    protected function initParameters(Application $app, $parameter_id=-1)
    {
        parent::initParameters($app, $parameter_id);

        $Config = new Configuration($app);
        self::$config = $Config->getConfiguration();

        self::$language = $this->getCMSlocale();

        $this->TagTypeData = new TagType($app);
        $this->TagData = new Tag($app);
        $this->CategoryData = new Category($app);
        $this->ContentData = new Content($app);
        $this->Tools = new Tools($app);
    }

    /**
     * (non-PHPdoc)
     * @see \phpManufaktur\Basic\Control\Pattern\Alert::promptAlert()
     */
    public function promptAlert()
    {
        if (!isset(self::$parameter['load_css'])) {
            self::$parameter['load_css'] = self::$config['kitcommand']['parameter']['action']['tag']['load_css'];
        }
        if (!isset(self::$parameter['check_jquery'])) {
            self::$parameter['check_jquery'] = self::$config['kitcommand']['parameter']['action']['tag']['check_jquery'];
        }
        $result = $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/flexContent/Template', 'command/alert.twig',
            $this->getPreferredTemplateStyle()),
            array(
                'basic' => $this->getBasicSettings(),
                'parameter' => self::$parameter
            ));

        $params = array();
        if (self::$parameter['check_jquery']) {
            $params['library'] = 'jquery/jquery/latest/jquery.min.js,bootstrap/latest/js/bootstrap.min.js';
        }
        if (self::$parameter['load_css']) {
            if (isset($params['library'])) {
                $params['library'] .= ',bootstrap/latest/css/bootstrap.min.css';
            }
            else {
                $params['library'] = 'bootstrap/latest/css/bootstrap.min.css';
            }
            $params['css'] = 'flexContent,css/flexcontent.min.css,'.$this->getPreferredTemplateStyle();
        }
        $params['robots'] = 'noindex,follow';
        return $this->app->json(array(
            'parameter' => $params,
            'response' => $result
        ));
    }

    /**
     * Collect the information for the given tag for an overview
     *
     * @return \phpManufaktur\Basic\Control\Pattern\rendered
     */
    protected function showTagID()
    {
        if (false == ($tag_type = $this->TagTypeData->select(self::$parameter['tag_id']))) {
            $this->setAlert('The Tag with the <strong>ID %id%</strong> does not exists for the language <strong>%language%</strong>!',
                array('%id%' => self::$parameter['tag_id'], '%language%' => self::$language),
                self::ALERT_TYPE_DANGER, true, array(__METHOD__, __LINE__));
            return $this->promptAlert();
        }

        // replace #hashtags
        $this->Tools->linkTags($tag_type['tag_description'], self::$language);

        if (false == ($contents = $this->ContentData->selectContentsByTagID(self::$parameter['tag_id'],
            self::$parameter['content_status'], self::$parameter['content_limit']))) {
            $this->setAlert('The tag %tag_name% does not contain any active contents',
                array('%tag_name%' => $tag_type['tag_name']), self::ALERT_TYPE_WARNING,
                array(__METHOD__, __LINE__));
        }

        for ($i=0; $i < sizeof($contents); $i++) {
            if (isset($contents[$i]['content_id'])) {
                $contents[$i]['categories'] = $this->CategoryData->selectCategoriesByContentID($contents[$i]['content_id']);
                $contents[$i]['tags'] = $this->TagData->selectTagArrayForContentID($contents[$i]['content_id']);
            }
        }

        $result = $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/flexContent/Template', 'command/tag.twig',
            $this->getPreferredTemplateStyle()),
            array(
                'basic' => $this->getBasicSettings(),
                'config' => self::$config,
                'parameter' => self::$parameter,
                'permalink_base_url' => CMS_URL.str_ireplace('{language}', strtolower(self::$language), self::$config['content']['permalink']['directory']),
                'tag' => $tag_type,
                'contents' => $contents
            ));

        $params = array();
        if (self::$parameter['check_jquery']) {
            $params['library'] = 'jquery/jquery/latest/jquery.min.js,bootstrap/latest/js/bootstrap.min.js';
        }
        if (self::$parameter['load_css']) {
            $css_files = 'bootstrap/latest/css/bootstrap.min.css,font-awesome/latest/css/font-awesome.min.css';
            if (isset($params['library'])) {
                $params['library'] .= ','.$css_files;
            }
            else {
                $params['library'] = $css_files;
            }
            $params['css'] = 'flexContent,css/flexcontent.min.css,'.$this->getPreferredTemplateStyle();
        }
        $params['robots'] = 'noindex,follow';
        return $this->app->json(array(
            'parameter' => $params,
            'response' => $result
        ));
    }

    /**
     * Return an enumeration for the available tags
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function getTagListing()
    {
        self::$parameter['limit'] = isset(self::$parameter['limit']) ? (int) self::$parameter['limit'] : null;
        self::$parameter['order_by'] = isset(self::$parameter['order_by']) ? strtolower(self::$parameter['order_by']) : 'tag_count';
        self::$parameter['order_direction'] = (isset(self::$parameter['order_direction']) && in_array(strtoupper(self::$parameter['order_direction'], array('ASC', 'DESC')))) ? strtoupper(self::$parameter['order_direction']) : null;
        self::$parameter['mode'] = isset(self::$parameter['mode']) ? strtolower(self::$parameter['mode']) : 'enumeration';
        self::$parameter['size_grid'] = (isset(self::$parameter['size_grid']) && is_integer(self::$parameter['size_grid'])) ? intval(self::$parameter['size_grid']) : 8;
        self::$parameter['size_factor'] = (isset(self::$parameter['size_factor']) && is_integer(self::$parameter['size_factor'])) ? intval(self::$parameter['size_factor']) : 0;

        if (isset(self::$parameter['color_background'])) {
            $color = self::$parameter['color_background'];
            if (strpos($color, '#') === 0) {
                self::$parameter['color_background'] = $color;
            }
            elseif (strpos($color, ',')) {
                if (substr_count($color, ',') == 2) {
                    self::$parameter['color_background'] = $this->app['utils']->rgb2hex($color);
                }
                elseif (substr_count($color, ',') == 3) {
                    $rgba = explode(',', $color);
                    self::$parameter['color_background'] = sprintf('rgba(%d,%d,%d,%f)', $rgba[0], $rgba[1], $rgba[2], $rgba[3]);
                }
                else {
                    self::$parameter['color_background'] = 'rgba(255,255,255,0)';
                }
            }
            else {
                self::$parameter['color_background'] = 'rgba(255,255,255,0)';
            }
        }
        else {
            self::$parameter['color_background'] = 'rgba(255,255,255,0)';
        }

        if (isset(self::$parameter['color_start'])) {
            $color = self::$parameter['color_start'];
            if (strpos($color, '#')) {
                self::$parameter['color_start'] = $color;
            }
            elseif (strpos($color, ',')) {
                if (substr_count($color, ',') == 2) {
                    self::$parameter['color_start'] = $this->app['utils']->rgb2hex($color);
                }
                else {
                    self::$parameter['color_start'] = '#33ffff';
                }
            }
            else {
                self::$parameter['color_start'] = '#33ffff';
            }
        }
        else {
            self::$parameter['color_start'] = '#33ffff';
        }

        if (isset(self::$parameter['color_end'])) {
            $color = self::$parameter['color_end'];
            if (strpos($color, '#')) {
                self::$parameter['color_end'] = $color;
            }
            elseif (strpos($color, ',')) {
                if (substr_count($color, ',') == 2) {
                    self::$parameter['color_end'] = $this->app['utils']->rgb2hex($color);
                }
                else {
                    self::$parameter['color_end'] = '#000';
                }
            }
            else {
                self::$parameter['color_end'] = '#000';
            }
        }
        else {
            self::$parameter['color_end'] = '#000';
        }

        self::$parameter['color_option'] = (isset(self::$parameter['color_option']) && in_array(strtolower(self::$parameter['color_option']), array('gradient', 'random-light', 'random-dark'))) ? strtolower(self::$parameter['color_option']) : 'random-dark';
        self::$parameter['sort'] = (isset(self::$parameter['sort']) && in_array(strtolower(self::$parameter['sort']), array('highest', 'lowest', 'random'))) ? strtolower(self::$parameter['sort']) : 'highest';

        self::$parameter['height'] = isset(self::$parameter['height']) ? intval(self::$parameter['height']) : 400;

        if (false !== ($tags = $this->TagData->selectTags(
            self::$language, self::$parameter['limit'], self::$parameter['order_by'], self::$parameter['order_direction']))) {

            switch (self::$parameter['mode']) {
                case 'cloud':
                    $twig_template = 'command/tag.cloud.twig';
                    break;
                case 'enumeration':
                default:
                    $twig_template = 'command/tag.enumeration.twig';
                    break;
            }

            $result = $this->app['twig']->render($this->app['utils']->getTemplateFile(
                '@phpManufaktur/flexContent/Template', $twig_template,
                $this->getPreferredTemplateStyle()),
                array(
                    'basic' => $this->getBasicSettings(),
                    'config' => self::$config,
                    'parameter' => self::$parameter,
                    'permalink_base_url' => CMS_URL.str_ireplace('{language}', strtolower(self::$language), self::$config['content']['permalink']['directory']),
                    'tags' => $tags
                ));

            $params = array();
            $params['library'] = null;
            if (self::$parameter['check_jquery']) {
                if (self::$config['kitcommand']['libraries']['enabled'] &&
                    !empty(self::$config['kitcommand']['libraries']['jquery'])) {
                    // load all predefined jQuery files for flexContent
                    foreach (self::$config['kitcommand']['libraries']['jquery'] as $library) {
                        if (!empty($params['library'])) {
                            $params['library'] .= ',';
                        }
                        $params['library'] .= $library;
                    }
                }
            }
            if (self::$parameter['load_css']) {
                if (self::$config['kitcommand']['libraries']['enabled'] &&
                    !empty(self::$config['kitcommand']['libraries']['css'])) {
                    // load all predefined CSS files for flexContent
                    foreach (self::$config['kitcommand']['libraries']['css'] as $library) {
                        if (!empty($params['library'])) {
                            $params['library'] .= ',';
                        }
                        // attach to 'library' not to 'css' !!!
                        $params['library'] .= $library;
                    }
                }

                // set the CSS parameter
                $params['css'] = 'flexContent,css/flexcontent.min.css,'.$this->getPreferredTemplateStyle();
            }
            return $this->app->json(array(
                'parameter' => $params,
                'response' => $result
            ));
        }
        else {
            $this->setAlert('There a no tags available for a listing!', array(), self::ALERT_TYPE_INFO);
            return $this->promptAlert();
        }
    }

    /**
     * Controller to handle the TAG overview
     *
     * @param Application $app
     */
    public function ControllerTag(Application $app)
    {
        $this->initParameters($app);

        // get the kitCommand parameters
        self::$parameter = $this->getCommandParameters();

        // check the CMS GET parameters
        $GET = $this->getCMSgetParameters();
        if (isset($GET['command']) && ($GET['command'] == 'flexcontent')) {
            // the command and parameters are set as GET from the CMS
            foreach ($GET as $key => $value) {
                if ($key == 'command') continue;
                self::$parameter[$key] = $value;
            }
            $this->setCommandParameters(self::$parameter);
        }

        // access the default parameters for action -> tag from the configuration
        $default_parameter = self::$config['kitcommand']['parameter']['action']['tag'];

        // the Tag ID is always needed!
        self::$parameter['tag_id'] = isset(self::$parameter['tag_id']) ? self::$parameter['tag_id'] : -1;

        // optional: content ID and category ID
        self::$parameter['content_id'] = isset(self::$parameter['content_id']) ? self::$parameter['content_id'] : -1;
        self::$parameter['category_id'] = isset(self::$parameter['category_id']) ? self::$parameter['category_id'] : -1;

        // limit for the content items
        self::$parameter['content_limit'] = (isset(self::$parameter['content_limit'])) ? intval(self::$parameter['content_limit']) : $default_parameter['content_limit'];

        // expose content items?
        self::$parameter['content_exposed'] = (isset(self::$parameter['content_exposed'])) ? intval(self::$parameter['content_exposed']) : $default_parameter['content_exposed'];
        if (!in_array(self::$parameter['content_exposed'], array(0,1,2,3,4,6,12))) {
            self::$parameter['content_exposed'] = 2;
            $this->setAlert('Please check the parameter content_exposed, allowed values are only 0,1,2,3,4,6 or 12!', array(), self::ALERT_TYPE_WARNING);
        }

        // check wether to use the flexcontent.css or not
        self::$parameter['load_css'] = (isset(self::$parameter['load_css']) && ((self::$parameter['load_css'] == 0) || (strtolower(self::$parameter['load_css']) == 'false'))) ? false : $default_parameter['load_css'];
        // disable the jquery check?
        self::$parameter['check_jquery'] = (isset(self::$parameter['check_jquery']) && ((self::$parameter['check_jquery'] == 0) || (strtolower(self::$parameter['check_jquery']) == 'false'))) ? false : $default_parameter['check_jquery'];

        // set the title level - default 1 = <h1>
        self::$parameter['title_level'] = (isset(self::$parameter['title_level']) && is_numeric(self::$parameter['title_level'])) ? self::$parameter['title_level'] : $default_parameter['title_level'];

        // show the tag name above?
        self::$parameter['tag_name'] = (isset(self::$parameter['tag_name']) && ((self::$parameter['tag_name'] == 0) || (strtolower(self::$parameter['tag_name']) == 'false'))) ? false : $default_parameter['tag_name'];

        // show the tag description?
        self::$parameter['tag_description'] = (isset(self::$parameter['tag_description']) && ((self::$parameter['tag_description'] == 0) || (strtolower(self::$parameter['tag_description']) == 'false'))) ? false : $default_parameter['tag_description'];

        // show the tag image?
        self::$parameter['tag_image'] = (isset(self::$parameter['tag_image']) && ((self::$parameter['tag_image'] == 0) || (strtolower(self::$parameter['tag_image']) == 'false'))) ? false : $default_parameter['tag_image'];

        // maximum size for the tag image
        self::$parameter['tag_image_max_width'] = (isset(self::$parameter['tag_image_max_width'])) ? intval(self::$parameter['tag_image_max_width']) : $default_parameter['tag_image_max_width'];
        self::$parameter['tag_image_max_height'] = (isset(self::$parameter['tag_image_max_height'])) ? intval(self::$parameter['tag_image_max_height']) : $default_parameter['tag_image_max_height'];

        // status for the contents specified?
        if (isset(self::$parameter['content_status']) && !empty(self::$parameter['content_status'])) {
            $status_string = strtoupper(self::$parameter['content_status']);
            if (strpos($status_string, ',')) {
                $explode = explode(',', $status_string);
                $status = array();
                foreach ($explode as $item) {
                    $status[] = trim($item);
                }
                self::$parameter['content_status'] = $status;
            }
            else {
                self::$parameter['content_status'] = array(trim(self::$parameter['content_status']));
            }
        }
        else {
            self::$parameter['content_status'] = $default_parameter['content_status'];
        }

        // limit for the content items
        self::$parameter['content_limit'] = (isset(self::$parameter['content_limit'])) ? intval(self::$parameter['content_limit']) : $default_parameter['content_limit'];

        // show the content image?
        self::$parameter['content_image'] = (isset(self::$parameter['content_image']) && ((self::$parameter['content_image'] == 0) || (strtolower(self::$parameter['content_image']) == 'false'))) ? false : $default_parameter['content_image'];

        // maximum size for the category image
        self::$parameter['content_image_max_width'] = (isset(self::$parameter['content_image_max_width'])) ? intval(self::$parameter['content_image_max_width']) : $default_parameter['content_image_max_width'];
        self::$parameter['content_image_max_height'] = (isset(self::$parameter['content_image_max_height'])) ? intval(self::$parameter['content_image_max_height']) : $default_parameter['content_image_max_height'];

        // maximum size for the SMALL category image
        self::$parameter['content_image_small_max_width'] = (isset(self::$parameter['content_image_small_max_width'])) ? intval(self::$parameter['content_image_small_max_width']) : $default_parameter['content_image_small_max_width'];
        self::$parameter['content_image_small_max_height'] = (isset(self::$parameter['content_image_small_max_height'])) ? intval(self::$parameter['content_image_small_max_height']) : $default_parameter['content_image_small_max_height'];

        // show content title?
        self::$parameter['content_title'] = (isset(self::$parameter['content_title']) && ((self::$parameter['content_title'] == 0) || (strtolower(self::$parameter['content_title']) == 'false'))) ? false : $default_parameter['content_title'];

        // show content description?
        self::$parameter['content_description'] = (isset(self::$parameter['content_description']) && ((self::$parameter['content_description'] == 0) || (strtolower(self::$parameter['content_description']) == 'false'))) ? false : $default_parameter['content_description'];

        // show content teaser?
        //self::$parameter['content_teaser'] = (isset(self::$parameter['content_teaser']) && ((self::$parameter['content_teaser'] == 0) || (strtolower(self::$parameter['content_teaser']) == 'false'))) ? false : $default_parameter['content_teaser'];

        // show content description?
        self::$parameter['content_description'] = (isset(self::$parameter['content_description']) && ((self::$parameter['content_description'] == 0) || (strtolower(self::$parameter['content_description']) == 'false'))) ? false : $default_parameter['content_description'];

        // show content content?
        //self::$parameter['content_content'] = (isset(self::$parameter['content_content']) && ((self::$parameter['content_content'] == 1) || (strtolower(self::$parameter['content_content']) == 'true'))) ? true : $default_parameter['content_content'];

        self::$parameter['content_view'] = (isset(self::$parameter['content_view'])) ? strtolower(self::$parameter['content_view']) : $default_parameter['content_view'];

        if (!in_array(self::$parameter['content_view'], self::$view_array)) {
            // unknown value for the view[] parameter
            $this->setAlert('The parameter <code>%parameter%[%value%]</code> for the kitCommand <code>~~ %command% ~~</code> is unknown, '.
                'please check the parameter and the given value!',
                array('%parameter%' => 'content_view', '%value%' => self::$parameter['content_view'], '%command%' => 'flexContent'), self::ALERT_TYPE_DANGER,
                true, array(__METHOD__, __LINE__));
            return $this->promptAlert();
        }
        // show content tags?
        self::$parameter['content_tags'] = (isset(self::$parameter['content_tags']) && ((self::$parameter['content_tags'] == 0) || (strtolower(self::$parameter['content_tags']) == 'false'))) ? false : $default_parameter['content_tags'];

        // show content categories?
        self::$parameter['content_categories'] = (isset(self::$parameter['content_categories']) && ((self::$parameter['content_categories'] == 1) || (strtolower(self::$parameter['content_categories']) == 'true'))) ? true : $default_parameter['content_categories'];

        // show content author?
        self::$parameter['content_author'] = (isset(self::$parameter['content_author']) && ((self::$parameter['content_author'] == 0) || (strtolower(self::$parameter['content_author']) == 'false'))) ? false : $default_parameter['content_author'];

        // show content date?
        self::$parameter['content_date'] = (isset(self::$parameter['content_date']) && ((self::$parameter['content_date'] == 0) || (strtolower(self::$parameter['content_date']) == 'false'))) ? false : $default_parameter['content_date'];

        self::$parameter['type'] = (isset(self::$parameter['type'])) ? strtolower(self::$parameter['type']) : 'contents';

        if (self::$parameter['type'] !== 'contents') {
            return $this->getTagListing();
        }

        if (self::$parameter['tag_id'] > 0) {
            return $this->showTagID();
        }

        // Ooops ...
        $this->setAlert('Fatal error: Missing the Tag ID!', array(), self::ALERT_TYPE_DANGER, true, array(__METHOD__, __LINE__));
        return $this->promptAlert();
    }
}
