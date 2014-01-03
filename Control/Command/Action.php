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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use phpManufaktur\flexContent\Control\Configuration;

class Action extends Basic
{
    protected static $config = null;

    protected function initParameters(Application $app, $parameter_id=-1)
    {
        parent::initParameters($app, $parameter_id);

        $ConfigurationData = new Configuration($app);
        self::$config = $ConfigurationData->getConfiguration();
    }

    public function controllerAction(Application $app)
    {
        $this->initParameters($app);
        // get the kitCommand parameters
        $parameter = $this->getCommandParameters();

        // access the default parameters for action -> view from the configuration
        $default_parameter = self::$config['kitcommand']['parameter']['action']['view'];

        // use a iframe to show the content?
        $parameter['use_iframe'] = $app['request']->query->get('use_iframe', true);
        // check wether to use the flexcontent.css or not (only needed if self::$parameter['use_iframe'] == false)
        $parameter['css'] = (isset($parameter['css']) && (($parameter['css'] == 0) || (strtolower($parameter['css']) == 'false'))) ? false : $default_parameter['css'];

        // check the CMS GET parameters
        $GET = $this->getCMSgetParameters();
        if (isset($GET['command']) && ($GET['command'] == 'flexcontent')) {
            // the command and parameters are set as GET from the CMS
            foreach ($GET as $key => $value) {
                if ($key == 'command') continue;
                $parameter[$key] = $value;
            }
            $this->setCommandParameters($parameter);
        }

        if (!isset($parameter['action'])) {
            // there is no 'mode' parameter set, so we show the "Welcome" page
            $subRequest = Request::create('/basic/help/flexcontent/welcome', 'GET');
            return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        }

        switch (strtolower($parameter['action'])) {
            case 'view':
                $View = new ActionView();
                return $View->controllerView($app);
            case 'category':
                $Category = new ActionCategory();
                return $Category->ControllerCategory($app);
            case 'tag':
                $Tag = new ActionTag();
                return $Tag->ControllerTag($app);
            default:
                $this->setAlert('The parameter <code>%parameter%[%value%]</code> for the kitCommand <code>~~ %command% ~~</code> is unknown, please check the parameter and the given value!',
                    array('%parameter%' => 'action', '%value%' => $parameter['action'], '%command%' => 'flexContent'), self::ALERT_TYPE_DANGER);
                if ($parameter['use_iframe']) {
                    // we can use the default Bootstrap 3 alert response
                    return $this->promptAlert();
                }
                else {
                    // we must render the iframe free content template
                    return $this->app['twig']->render($this->app['utils']->getTemplateFile(
                        '@phpManufaktur/flexContent/Template', 'command/alert.twig',
                        $this->getPreferredTemplateStyle()),
                        array(
                            'basic' => $this->getBasicSettings(),
                            'parameter' => $parameter
                        ));
                }
        }


    }
}