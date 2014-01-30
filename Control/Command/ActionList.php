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
use phpManufaktur\flexContent\Data\Content\Content;
use phpManufaktur\flexContent\Data\Content\Category;
use phpManufaktur\flexContent\Data\Content\Tag;

class ActionList extends Basic
{
    protected static $parameter = null;
    protected static $config = null;
    protected static $language = null;
    protected static $use_iframe = null;
    protected static $list_type = null;

    protected $ContentData = null;
    protected $CategoryData = null;
    protected $TagData = null;
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

        self::$use_iframe = $app['request']->query->get('use_iframe', true);

        self::$language = $this->getCMSlocale();

        $this->ContentData = new Content($app);
        $this->CategoryData = new Category($app);
        $this->TagData = new Tag($app);
        $this->Tools = new Tools($app);
    }

    /**
     * (non-PHPdoc)
     * @see \phpManufaktur\Basic\Control\Pattern\Alert::promptAlert()
     */
    public function promptAlert()
    {
        if (self::$use_iframe) {
            // we can use the default Bootstrap 3 alert response
            return parent::promptAlert();
        }
        else {
            // we must render the iframe free content template
            if (!isset(self::$parameter['load_css'])) {
                self::$parameter['load_css'] = self::$config['kitcommand']['parameter']['action']['view']['load_css'];
            }
            return $this->app['twig']->render($this->app['utils']->getTemplateFile(
                '@phpManufaktur/flexContent/Template', 'command/alert.twig',
                $this->getPreferredTemplateStyle()),
                array(
                    'basic' => $this->getBasicSettings(),
                    'parameter' => self::$parameter
                ));
        }
    }

    /**
     * Generate a list with contents and return the dialog
     *
     * @return string
     */
    protected function showList()
    {
        if (false === ($contents = $this->ContentData->selectContentList(self::$language, self::$parameter['content_limit'],
            self::$parameter['categories'], self::$parameter['categories_exclude'], self::$parameter['content_status'],
            self::$parameter['order_by'], self::$parameter['order_direction']))) {
            $this->setAlert('This list does not contain any contents!');
        }

        if (is_array($contents)) {
            for ($i=0; $i < sizeof($contents); $i++) {
                $contents[$i]['categories'] = $this->CategoryData->selectCategoriesByContentID($contents[$i]['content_id']);
                $contents[$i]['tags'] = $this->TagData->selectTagArrayForContentID($contents[$i]['content_id']);

                // highlight search results?
                if (isset(self::$parameter['highlight']) && is_array(self::$parameter['highlight'])) {
                    foreach (self::$parameter['highlight'] as $highlight) {
                        $this->Tools->highlightSearchResult($highlight, $contents[$i]['teaser']);
                        $this->Tools->highlightSearchResult($highlight, $contents[$i]['content']);
                        $this->Tools->highlightSearchResult($highlight, $contents[$i]['description']);
                    }
                }

                // replace #tags
                $this->Tools->linkTags($contents[$i]['teaser'], self::$language);
                $this->Tools->linkTags($contents[$i]['content'], self::$language);
            }
        }

        if (self::$list_type == 'full') {
            $template = 'command/list.twig';
        }
        else {
            $template = 'command/list.simple.twig';
        }

        return $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/flexContent/Template', $template,
            $this->getPreferredTemplateStyle()),
            array(
                'basic' => $this->getBasicSettings(),
                'config' => self::$config,
                'parameter' => self::$parameter,
                'permalink_base_url' => CMS_URL.str_ireplace('{language}', strtolower(self::$language), self::$config['content']['permalink']['directory']),
                'contents' => $contents
            ));
    }

    /**
     * Controller to handle flexContents as a list independent from categories
     *
     * @param Application $app
     * @return string
     */
    public function ControllerList(Application $app, $list_type='full')
    {
        $this->initParameters($app);
        self::$list_type = $list_type;

        // get the kitCommand parameters
        self::$parameter = $this->getCommandParameters();

        // check the CMS GET parameters
        $GET = $this->getCMSgetParameters();
        if (isset($GET['command']) && ($GET['command'] == 'flexcontent')) {
            // the command and parameters are set as GET from the CMS
            foreach ($GET as $key => $value) {
                if ($key == 'command') {
                    continue;
                }
                self::$parameter[$key] = $value;
            }
            $this->setCommandParameters(self::$parameter);
        }

        // access the default parameters
        if (self::$list_type == 'full') {
            $default_parameter = self::$config['kitcommand']['parameter']['action']['list'];
        }
        else {
            $default_parameter = self::$config['kitcommand']['parameter']['action']['list_simple'];
        }

        // check wether to use the flexcontent.css or not (only needed if self::$parameter['use_iframe'] == false)
        self::$parameter['load_css'] = (isset(self::$parameter['load_css']) && ((self::$parameter['load_css'] == 0) || (strtolower(self::$parameter['load_css']) == 'false'))) ? false : $default_parameter['load_css'];

        // set the title level - default 1 = <h1>
        self::$parameter['title_level'] = (isset(self::$parameter['title_level']) && is_numeric(self::$parameter['title_level'])) ? self::$parameter['title_level'] : $default_parameter['title_level'];

        // show only specified categories?
        if (isset(self::$parameter['categories']) && !empty(self::$parameter['categories'])) {
            if (strpos(self::$parameter['categories'], ',')) {
                $explode = explode(',', self::$parameter['categories']);
                $categories = array();
                foreach ($explode as $item) {
                    $categories[] = trim($item);
                }
                self::$parameter['categories'] = $categories;
            }
            else {
                self::$parameter['categories'] = array(trim(self::$parameter['categories']));
            }
        }
        else {
            self::$parameter['categories'] = $default_parameter['categories'];
        }

        // exclude some specified categories?
        if (isset(self::$parameter['categories_exclude']) && !empty(self::$parameter['categories_exclude'])) {
            if (strpos(self::$parameter['categories_exclude'], ',')) {
                $explode = explode(',', self::$parameter['categories_exclude']);
                $categories = array();
                foreach ($explode as $item) {
                    $categories[] = trim($item);
                }
                self::$parameter['categories_exclude'] = $categories;
            }
            else {
                self::$parameter['categories_exclude'] = array(trim(self::$parameter['categories_exclude']));
            }
        }
        else {
            self::$parameter['categories_exclude'] = $default_parameter['categories_exclude'];
        }

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

        // order by
        self::$parameter['order_by'] = (isset(self::$parameter['order_by'])) ? strtolower(self::$parameter['order_by']) : $default_parameter['order_by'];
        // order direction
        self::$parameter['order_direction'] = (isset(self::$parameter['order_direction'])) ? strtoupper(self::$parameter['order_direction']) : $default_parameter['order_direction'];


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

        // show content description?
        self::$parameter['content_description'] = (isset(self::$parameter['content_description']) && ((self::$parameter['content_description'] == 0) || (strtolower(self::$parameter['content_description']) == 'false'))) ? false : $default_parameter['content_description'];

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

        return $this->showList();
    }
}
