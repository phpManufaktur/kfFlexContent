<?php

/**
 * flexContent
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/flexContent
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\flexContent\Control\Admin;

use Silex\Application;
use phpManufaktur\flexContent\Control\Configuration;
use phpManufaktur\Basic\Control\Pattern\Alert;

class Admin extends Alert
{

    protected static $usage = null;
    protected static $usage_param = null;
    protected static $config = null;

    /**
     * Initialize the class with the needed parameters
     *
     * @param Application $app
     */
    protected function initialize(Application $app)
    {
        parent::initialize($app);

        $cms = $this->app['request']->get('usage');
        self::$usage = is_null($cms) ? 'framework' : $cms;
        self::$usage_param = (self::$usage != 'framework') ? '?usage='.self::$usage : '';
        // set the locale from the CMS locale
        if (self::$usage != 'framework') {
            $app['translator']->setLocale($this->app['session']->get('CMS_LOCALE', 'en'));
        }
        $Configuration = new Configuration($app);
        self::$config = $Configuration->getConfiguration();
    }

    /**
     * Get the toolbar for all backend dialogs
     *
     * @param string $active dialog
     * @return array
     */
    public function getToolbar($active) {
        $toolbar_array = array(
            'list' => array(
                'name' => 'list',
                'text' => 'List',
                'hint' => 'List of all flexContent articles',
                'link' => FRAMEWORK_URL.'/admin/flexcontent/list'.self::$usage_param,
                'active' => ($active == 'list')
            ),
            'edit' => array(
                'name' => 'edit',
                'text' => 'Edit',
                'hint' => 'Create or edit a flexContent article',
                'link' => FRAMEWORK_URL.'/admin/flexcontent/edit'.self::$usage_param,
                'active' => ($active == 'edit')
            ),
            'tags' => array(
                'name' => 'tags',
                'text' => 'Hashtags',
                'hint' => 'Create or edit hashtags',
                'link' => FRAMEWORK_URL.'/admin/flexcontent/tag/list'.self::$usage_param,
                'active' => ($active == 'tags')
            ),
            'categories' => array(
                'name' => 'categories',
                'text' => 'Categories',
                'hint' => 'Create or edit categories',
                'link' => FRAMEWORK_URL.'/admin/flexcontent/category/list'.self::$usage_param,
                'active' => ($active == 'categories')
            ),
            'import' => array(
                'name' => 'import',
                'text' => 'Import',
                'hint' => 'Import WYSIWYG and Blog contents',
                'link' => FRAMEWORK_URL.'/admin/flexcontent/import/list'.self::$usage_param,
                'active' => ($active == 'import')
            ),
            'about' => array(
                'name' => 'about',
                'text' => 'About',
                'hint' => 'Information about the flexContent extension',
                'link' => FRAMEWORK_URL.'/admin/flexcontent/about'.self::$usage_param,
                'active' => ($active == 'about')
                ),
        );

        if (!self::$config['admin']['import']['enabled']) {
            // show the import only, if enabled!
            unset($toolbar_array['import']);
        }
        return $toolbar_array;
    }
 }
