<?php

class PreviewModalWidget extends CWidget
{
    //The name the widget will be rendered to, please make sure it is unique!
    public $widgetsJsName = "";
    //The title the widgets modal will have
    public $modalTitle = "Select please";
    //If using the grouped view this should be the key to get the group title
    public $groupTitleKey = "title";
    //If using the grouped view this should be the key of the grouped items array key
    public $groupItemsKey = "items";
    //Display this with the items key in debug mode
    public $debugKeyCheck = "Key: ";
    //The title of the preview window
    public $previewWindowTitle = "Preview";

    //Either a group or an items array must be given
    public $groupStructureArray = [];
    public $itemsArray = [];

    //There should be a value set
    public $value = null;
    //This may be the value also, but oftentimes you'd want this to be an easy to read title
    public $currentSelected = "";

    //This is the option array that gets fed into the javascript.
    public $optionArray = [];

    //If the button should have any extra classes or modifications.
    public $buttonClasses = ['btn-default'];

    //The position the icon has in the modal button
    public $iconPosition = 'back';

    //These are pretty standard and may not need to be changed
    public $closeButton = "Close";
    public $selectButton = "Select";

    //RenderType should either be modal, simple, group-simple or group-modal
    public $renderType;

    public $debug = false;

    /**
     * @todo document me
     *
     * @throws CException
     */
    public function run()
    {
        //clean up the widgets name to be js friendly
        $this->widgetsJsName = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->widgetsJsName);
        $this->registerScripts();
    }

    /**
     * @todo document me
     *
     * @param bool $return
     *
     * @return string|void
     * @throws CException
     */
    public function getModal($return = false)
    {
        if (preg_match("/modal/", $this->renderType)) {
            return $this->render($this->getView(), null, $return);
        }
        echo "";
        return;
    }

    /**
     * @todo document me
     *
     * @param bool $return
     * @return string
     * @throws CException
     */
    public function getButtonOrSelect($return = false)
    {
        if (preg_match("/modal/", $this->renderType)) {
            return $this->render("open_modal_button", null, $return);
        }

        return $this->render($this->view, null, $return);
    }

    /**
     * @todo document me
     *
     * @return string
     */
    public function getView()
    {
        switch ($this->renderType) {
            case 'simple':
                return 'simple_select';
            case 'group-simple':
                return 'simple_grouped_select';
            case 'group-modal':
                return 'grouped_select_modal';
            case 'modal': //fallthrough
            default:
                return 'select_modal';
        }
    }

    /**
     * @todo document me
     *
     * @throws CException
     */
    private function registerScripts()
    {
        $oClientScript = App()->getClientScript();
        $basePath = dirname(__FILE__) . '/assets/';

        //publish Assets
        $sStyleFile = App()->getAssetManager()->publish($basePath.'previewModalWidget.dist.css');
        $sScriptFile = App()->getAssetManager()->publish($basePath.'previewModalWidget.dist.js');
        //register Assets
        $oClientScript->registerCssFile($sStyleFile);
        $oClientScript->registerScriptFile($sScriptFile, CClientScript::POS_BEGIN);
        $oClientScript->registerScript('WIDGETSCRIPT--'.$this->widgetsJsName, '
        var runner_'.$this->widgetsJsName.' = new PreviewModalScript("'.$this->widgetsJsName.'",'
        .json_encode(array_merge($this->optionArray, [
            'value' => $this->value,
            'debugString' => $this->debugKeyCheck,
            'debug' => $this->debug,
            'viewType' => $this->view
            ]))
        .');
        runner_'.$this->widgetsJsName.'.bind();',
        LSYii_ClientScript::POS_POSTSCRIPT);
    }
}
