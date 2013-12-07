<?php

/**
 * flexContent
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/flexContent
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\flexContent\Control\Backend;

use Silex\Application;
use phpManufaktur\flexContent\Data\Content\Content as ContentData;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use phpManufaktur\flexContent\Data\Content\Tag;
use phpManufaktur\flexContent\Data\Content\TagType;

class ContentEdit extends Backend
{
    protected $ContentData = null;
    protected static $content_id = null;
    protected $TagData = null;
    protected $TagTypeData = null;

    /**
     * (non-PHPdoc)
     * @see \phpManufaktur\flexContent\Control\Backend\Backend::initialize()
     */
    protected function initialize(Application $app)
    {
        parent::initialize($app);
        self::$content_id = -1;
        $this->ContentData = new ContentData($app);
        $this->TagData = new Tag($app);
        $this->TagTypeData = new TagType($app);
    }

    /**
     * Create the form.factory form for flexContent
     *
     * @param array $data
     */
    protected function getContentForm($data=array())
    {
        if (!isset($data['publish_from']) || ($data['publish_from'] == '0000-00-00 00:00:00')) {
            $dt = Carbon::create();
            $dt->addHours(self::$config['content']['field']['publish_from']['add']['hours']);
            $publish_from = $dt->toDateTimeString();
        }
        else {
            $publish_from = $data['publish_from'];
        }

        if (!isset($data['breaking_to']) || ($data['breaking_to'] == '0000-00-00 00:00:00')) {
            $dt = Carbon::createFromFormat('Y-m-d H:i:s', $publish_from);
            $dt->addHours(self::$config['content']['field']['breaking_to']['add']['hours']);
            $breaking_to = $dt->toDateTimeString();
        }
        else {
            $breaking_to = $data['breaking_to'];
        }

        if (!isset($data['archive_from']) || ($data['archive_from'] == '0000-00-00 00:00:00')) {
            $dt = Carbon::createFromFormat('Y-m-d H:i:s', $publish_from);
            $dt->endOfDay();
            $dt->addDays(self::$config['content']['field']['archive_from']['add']['days']);
            $archive_from = $dt->toDateTimeString();
        }
        else {
            $archive_from = $data['archive_from'];
        }

        $form = $this->app['form.factory']->createBuilder('form')
        ->add('content_id', 'hidden', array(
            'data' => isset($data['content_id']) ? $data['content_id'] : -1
        ))
        ->add('title', 'text', array(
            'data' => isset($data['title']) ? $data['title'] : '',
            'required' => self::$config['content']['field']['title']['required']
        ))
        ->add('description', 'textarea', array(
            'data' => isset($data['description']) ? $data['description'] : '',
            'required' => self::$config['content']['field']['description']['required']
        ))
        ->add('keywords', 'textarea', array(
            'data' => isset($data['keywords']) ? $data['keywords'] : '',
            'required' => self::$config['content']['field']['keywords']['required']
        ))
        ->add('permalink', 'text', array(
            'data' => isset($data['permalink']) ? $data['permalink'] : '',
            'required' => self::$config['content']['field']['permalink']['required']
        ))
        ->add('publish_from', 'text', array(
            'attr' => array('class' => 'publish_from'),
            'required' => self::$config['content']['field']['publish_from']['required'],
            'data' => date($this->app['translator']->trans('DATETIME_FORMAT'), strtotime($publish_from)),
        ))
        ->add('breaking_to', 'text', array(
            'attr' => array('class' => 'breaking_to'),
            'required' => self::$config['content']['field']['breaking_to']['required'],
            'data' => date($this->app['translator']->trans('DATETIME_FORMAT'), strtotime($breaking_to)),
        ))
        ->add('archive_from', 'text', array(
            'attr' => array('class' => 'archive_from'),
            'required' => self::$config['content']['field']['archive_from']['required'],
            'data' => date($this->app['translator']->trans('DATETIME_FORMAT'), strtotime($archive_from)),
        ))
        ->add('teaser', 'textarea', array(
            'data' => isset($data['teaser']) ? $data['teaser'] : '',
            'required' => self::$config['content']['field']['teaser']['required']
        ))
        ->add('content', 'textarea', array(
            'data' => isset($data['content']) ? $data['content'] : '',
            'required' => self::$config['content']['field']['content']['required']
        ))
        ->add('status', 'choice', array(
            'choices' => $this->ContentData->getStatusTypeValuesForForm(),
            'empty_value' => '- please select -',
            'expanded' => false,
            'required' => self::$config['content']['field']['status']['required'],
            'data' => isset($data['status']) ? $data['status'] : 'UNPUBLISHED'
        ))
        ->add('permalink', 'text', array(
            'required' => self::$config['content']['field']['permalink']['required'],
            'data' => isset($data['permalink']) ? $data['permalink'] : ''
        ))
        ->add('permalink_url', 'hidden', array(
            'data' => CMS_URL.self::$config['content']['permalink']['directory'].'/'
        ))
        ->add('redirect_url', 'text', array(
            'required' => self::$config['content']['field']['redirect_url']['required'],
            'data' => isset($data['redirect_url']) ? $data['redirect_url'] : ''
        ))
        ->add('teaser_image', 'hidden', array(
            'data' => isset($data['teaser_image']) ? $data['teaser_image'] : ''
        ))
        ;
        return $form->getForm();
    }

    /**
     * Check the submitted form, create a new record or update an existing
     *
     * @param array reference $data
     * @return boolean
     */
    protected function checkContentForm(&$data=array())
    {
        // get the form
        $form = $this->getContentForm();
        // get the requested data
        $form->bind($this->app['request']);

        if ($form->isValid()) {
            // the form is valid
            $content = $form->getData();
            $data = array();

            self::$content_id = $content['content_id'];
            $data['content_id'] = self::$content_id;

            $checked = true;

            // check the fields
            foreach (self::$config['content']['field'] as $name => $property) {
                switch ($name) {
                    case 'title':
                        if (!$property['required']) {
                            // the title must be always set!
                            $this->setMessage('The title is always needed and con not switched off, please check the configuration!');
                        }
                        if ((strlen($content[$name]) < $property['length']['minimum']) ||
                        (strlen($content[$name]) > $property['length']['maximum'])) {
                            $this->setMessage('The title should have a length between %minimum% and %maximum% characters (actual: %length%).',
                                array('%minimum%' => $property['length']['minimum'],
                                    '%maximum%' => $property['length']['maximum'], '%length%' => strlen($content[$name])));
                            $checked = false;
                        }
                        $data[$name] = !is_null($content[$name]) ? $content[$name] : '';
                        break;
                    case 'description':
                        if ($property['required']) {
                            if ((strlen($content[$name]) < $property['length']['minimum']) ||
                                (strlen($content[$name]) > $property['length']['maximum'])) {
                                $this->setMessage('The description should have a length between %minimum% and %maximum% characters (actual: %length%).',
                                    array('%minimum%' => $property['length']['minimum'],
                                        '%maximum%' => $property['length']['maximum'], '%length%' => strlen($content[$name])));
                                $checked = false;
                            }
                        }
                        $data[$name] = !is_null($content[$name]) ? $content[$name] : '';
                        break;
                    case 'keywords':
                        if ($property['required']) {
                            $separator = ($property['separator'] == 'comma') ? ',' : ' ';
                            if (false === strpos($content[$name], $separator)) {
                                $this->setMessage('Please define keywords for the content');
                                $data[$name] = $content[$name];
                                $checked = false;
                            }
                            else {
                                $explode = explode($separator, $content[$name]);
                                $keywords = array();
                                foreach ($explode as $item) {
                                    $keyword = strtolower(trim($item));
                                    if (!empty($keyword)) {
                                        $keywords[] = $keyword;
                                    }
                                }
                                if ((count($keywords) < $property['words']['minimum']) ||
                                    (count($keywords) > $property['words']['maximum'])) {
                                    $this->setMessage('Please define between %minimum% and %maximum% keywords, actual: %count%',
                                        array('%minimum%' => $property['words']['minimum'],
                                            '%maximum' => $property['words']['maximum'], '%count%' => count($keywords)));
                                    $checked = false;
                                }
                                $data[$name] = implode($separator, $keywords);
                            }
                        }
                        else {
                            $data[$name] = !is_null($content[$name]) ? $content[$name] : '';
                        }
                        break;
                    case 'permalink':
                        if (!$property['required']) {
                            // the 'required' flag for the permanent link can not switched off
                            $this->setMessage('The permanent link is always needed and can not switched off, please check the configuration!');
                        }

                        // @todo: permanent link integration
                        $data[$name] = !is_null($content[$name]) ? $content[$name] : '';
                        break;
                    case 'redirect_url':

                        // @todo: check the URL!
                        $data[$name] = !is_null($content[$name]) ? $content[$name] : '';

                        break;
                    case 'publish_from':
                        if (!$property['required']) {
                            // publish_from is always needed!
                            $this->setMessage("The 'publish from' field is always needed and can not switched off, please check the configuration!");
                        }
                        if (empty($content[$name])) {
                            // if field is empty set the actual date/time
                            $dt = Carbon::create();
                            $dt->addHours($property['add']['hours']);
                            $content[$name] = date($this->app['translator']->trans('DATETIME_FORMAT'), $dt->getTimestamp());
                        }
                        // convert the date/time string
                        $dt = Carbon::createFromFormat($this->app['translator']->trans('DATETIME_FORMAT'), $content[$name]);
                        $data[$name] = $dt->toDateTimeString();
                        break;
                    case 'breaking_to':
                        // ignore property 'required'!
                        if (!isset($data['publish_from'])) {
                            // problem: publish_from must defined first!
                            $this->setMessage("Problem: '%first%' must be defined before '%second%', please check the configuration file!",
                                array('%first%' => 'publish_from', '%second%' => 'breaking_to'));
                            $checked = false;
                            break;
                        }
                        if (empty($content[$name])) {
                            // if field is empty create date/time as configured
                            $dt = Carbon::createFromFormat('Y-m-d H:i:s', $data['publish_from']);
                            $dt->addHours($property['add']['hours']);
                            $content[$name] = date($this->app['translator']->trans('DATETIME_FORMAT'), $dt->getTimestamp());
                        }
                        // convert the date/time string
                        $dt = Carbon::createFromFormat($this->app['translator']->trans('DATETIME_FORMAT'), $content[$name]);
                        $data[$name] = $dt->toDateTimeString();
                        break;
                    case 'archive_from':
                        // ignore property 'required'!
                        if (!isset($data['publish_from'])) {
                            // problem: publish_from must defined first!
                            $this->setMessage("Problem: '%first%' must be defined before '%second%', please check the configuration file!",
                                array('%first%' => 'publish_from', '%second%' => 'archive_from'));
                            $checked = false;
                            break;
                        }
                        if (empty($content[$name])) {
                            // if field is empty create date/time as configured
                            $dt = Carbon::createFromFormat('Y-m-d H:i:s', $data['publish_from']);
                            $dt->endOfDay();
                            $dt->addDays($property['add']['days']);
                            $content[$name] = date($this->app['translator']->trans('DATETIME_FORMAT'), $dt->getTimestamp());
                        }
                        // convert the date/time string
                        $dt = Carbon::createFromFormat($this->app['translator']->trans('DATETIME_FORMAT'), $content[$name]);
                        $data[$name] = $dt->toDateTimeString();
                        break;
                    case 'teaser':
                    case 'content':
                        if ($property['required'] && empty($content[$name])) {
                            $this->setMessage('The field %name% can not be empty!',
                                array('%name%' => $this->app['translator']->trans($name)));
                            $checked = false;
                        }
                        $data[$name] = !is_null($content[$name]) ? $content[$name] : '';
                        break;
                    case 'status':
                        // ignore property 'required'!
                        $values = $this->app['db.utils']->getEnumValues(FRAMEWORK_TABLE_PREFIX.'flexcontent_content', 'status');
                        if (!in_array($content[$name], $values)) {
                            $this->setMessage('Please check the status, the value %value% is invalid!',
                                array('%value%' => $content[$name]));
                            $checked = false;
                        }
                        $data[$name] = $content[$name];
                }
            }

            // additional checks
            if (empty($data['teaser']) && empty($data['content'])) {
                $this->setMessage('At least must it exists some text within the teaser or the content, at the moment the Teaser and the Content are empty!');
                $checked = false;
            }

            if ($checked) {
                if (self::$content_id < 1) {
                    // insert a new record
                    $this->ContentData->insert($data, self::$content_id);
                    $this->setMessage('Successfull created a new flexContent record with the ID %id%.',
                        array('%id%' => self::$content_id));
                    // important: set the content_id also in the $data array!
                    $data['content_id'] = self::$content_id;
                }
                else {
                    // update an existing record
                    $this->ContentData->update($data, self::$content_id);
                    $this->setMessage('Succesfull updated the flexContent record with the ID %id%',
                        array('%id%' => self::$content_id));
                }

                // check the TAGs
                $this->checkContentTags();
                return true;
            }
        }
        else {
            // general error (timeout, CSFR ...)
            $this->setMessage('The form is not valid, please check your input and try again!');
        }

        // always check the TAGs
        $this->checkContentTags();
        return false;
    }

    protected function checkContentTags()
    {
        if (null !== ($tags = $this->app['request']->get('tag'))) {
            $position = 1;
            $tag_ids = array();
            foreach($tags as $key => $value) {
                if(preg_match('/([0-9]*)-?(a|d)?$/', $key, $keyparts) === 1) {
                    if(isset($keyparts[2])) {
                        switch($keyparts[2]) {
                            case 'a':
                                // check the key
                                if (false === ($name = $this->TagTypeData->selectNameByID($keyparts[1]))) {
                                    throw new \Exception('The Tag Type with the ID '.$keyparts[1].' does not exists!');
                                }
                                if ($name != $value) {
                                    // the TAG name was changed
                                    $data = array(
                                        'tag_name' => $value
                                    );
                                    // update the TAG TYPE record
                                    $this->TagTypeData->update($keyparts[1], $data);
                                    $this->setMessage('The tag %old% was changed to %new%. This update will affect all contents.',
                                        array('%old%' => $name, '%new%' => $value));
                                }
                                // add the TAG to the tag table
                                $data = array(
                                    'tag_id' => $keyparts[1],
                                    'position' => $position,
                                    'content_id' => self::$content_id
                                );
                                if (false === ($id = $this->TagData->selectIDbyTagIDandContentID($keyparts[1], self::$content_id))) {
                                    // insert a new TAG record
                                    $this->TagData->insert($data, $id);
                                    $this->setMessage('Associated the tag %tag% to this flexContent.',
                                        array('%tag%' => $value));
                                }
                                else {
                                    // update an existing TAG record
                                    $this->TagData->update($id, $data);
                                }

                                $tag_ids[] = $id;
                                $position++;

                                break;
                            case 'd':
                                // delete the Tag
                                $this->TagTypeData->delete($keyparts[1]);

                                $this->setMessage('The tag %tag% was successfull deleted and removed from all content.',
                                    array('%tag%' => $value));
                                break;
                        }
                    }
                    else {
                        // insert a new key
                        $tag_id = -1;
                        $data = array(
                            'tag_name' => $value
                        );
                        // create a new TAG ID
                        $this->TagTypeData->insert($data, $tag_id);

                        // add a new TAG record
                        $data = array(
                            'tag_id' => $tag_id,
                            'position' => $position,
                            'content_id' => self::$content_id
                        );
                        $id = -1;
                        $this->TagData->insert($data, $id);

                        $tag_ids[] = $id;
                        $position++;

                        $this->setMessage('Created the new tag %tag% and attached it to this content.',
                            array('%tag%' => $value));
                    }
                }
            }

            $checks = $this->TagData->selectByContentID(self::$content_id);
            foreach ($checks as $check) {
                if (!in_array($check['id'], $tag_ids)) {
                    // delete this record
                    $this->TagData->delete($check['id']);
                    $tag_name = $this->TagTypeData->selectNameByID($check['tag_id']);
                    $this->setMessage('The tag %tag% is no longer associated with this content.',
                        array('%tag%' => $tag_name));
                }
            }
        }
    }

    /**
     * Render the form and return the complete dialog
     *
     * @param Form Factory $form
     */
    protected function renderContentForm($form)
    {
        return $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/flexContent/Template', 'backend/edit.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('edit'),
                'message' => $this->getMessage(),
                'form' => $form->createView(),
                'config' => self::$config,
                'tags' => $this->TagData->getSimpleTagArrayForContentID(self::$content_id)
            ));
    }

    /**
     * Controller to create or edit contents
     *
     * @param Application $app
     * @param integer $content_id
     */
    public function ControllerEdit(Application $app, $content_id=null)
    {
        $this->initialize($app);

        if (!is_null($content_id)) {
            self::$content_id = $content_id;
        }

        $data = array();
        if ((self::$content_id > 0) && (false === ($data = $this->ContentData->select(self::$content_id)))) {
            $this->setMessage('The flexContent record with the ID %id% does not exists!',
                array('%id%' => self::$content_id));
        }

        $form = $this->getContentForm($data);
        return $this->renderContentForm($form);
    }

    /**
     * Controller executed when the form was submitted
     *
     * @param Application $app
     * @return string
     */
    public function ControllerEditCheck(Application $app)
    {
        $this->initialize($app);

        $data = array();
        // check the form
        $this->checkContentForm($data);
        // get the form
        $form = $this->getContentForm($data);
        // return the form with results
        return $this->renderContentForm($form);
    }

    /**
     * Controller to select a image
     *
     * @param Application $app
     */
    public function ControllerImage(Application $app)
    {
        $this->initialize($app);

        // check the form data and set self::$contact_id
        $data = array();
        if (!$this->checkContentForm($data)) {
            // the check fails - show the form again
            $form = $this->getContentForm($data);
            return $this->renderContentForm($form);
        }

        // grant that the directory exists
        $app['filesystem']->mkdir(FRAMEWORK_PATH.self::$config['content']['images']['directory']['select']);

        // exec the MediaBrowser
        $subRequest = Request::create('/admin/mediabrowser', 'GET', array(
            'usage' => self::$usage,
            'start' => self::$config['content']['images']['directory']['start'],
            'redirect' => '/admin/flexcontent/edit/image/check/id/'.self::$content_id,
            'mode' => 'public',
            'directory' => self::$config['content']['images']['directory']['select']
        ));
        return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Controller check the submitted image
     *
     * @param Application $app
     * @param integer $content_id
     * @return string
     */
    public function ControllerImageCheck(Application $app, $content_id)
    {
        $this->initialize($app);

        self::$content_id = $content_id;

        // get the selected image
        if (null == ($image = $app['request']->get('file'))) {
            $this->setMessage('There was no image selected.');
        }
        else {
            // udate the flexContent record
            $data = array(
                'teaser_image' => $image
            );
            $this->ContentData->update($data, self::$content_id);
            $this->setMessage('The image %image% was successfull inserted.',
                array('%image%' => basename($image)));
        }

        if (false === ($data = $this->ContentData->select(self::$content_id))) {
            $this->setMessage('The flexContent record with the ID %id% does not exists!',
                array('%id%' => self::$content_id));
        }
        $form = $this->getContentForm($data);
        return $this->renderContentForm($form);
    }
}
