<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Moonewsmancoupon extends Module
{
    public function __construct()
    {
        $this->name = 'moonewsmancoupon';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'moonia';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Newsman Coupon Generator');
        $this->description = $this->l('Generates single-use coupons for Newsman via webhook.');
    }

    public function install()
    {
        return parent::install() && $this->installConfiguration();
    }

    public function uninstall()
    {
        return $this->uninstallConfiguration() && parent::uninstall();
    }

    protected function installConfiguration()
    {
        // Defaults: EUR, 10 days default expiry, header name x-api-key
        return Configuration::updateValue('NMC_API_KEY', Tools::passwdGen(32))
            && Configuration::updateValue('NMC_HEADER_NAME', 'x-api-key')
            && Configuration::updateValue('NMC_DEFAULT_PREFIX', 'NEWS')
            && Configuration::updateValue('NMC_CODE_LENGTH', 10)
            && Configuration::updateValue('NMC_MAX_BATCH', 1000)
            && Configuration::updateValue('NMC_DEFAULT_EXPIRY_DAYS', 10)
            && Configuration::updateValue('NMC_FORCE_CURRENCY_ISO', 'EUR');
    }

    protected function uninstallConfiguration()
    {
        return Configuration::deleteByName('NMC_API_KEY')
            && Configuration::deleteByName('NMC_HEADER_NAME')
            && Configuration::deleteByName('NMC_DEFAULT_PREFIX')
            && Configuration::deleteByName('NMC_CODE_LENGTH')
            && Configuration::deleteByName('NMC_MAX_BATCH')
            && Configuration::deleteByName('NMC_DEFAULT_EXPIRY_DAYS')
            && Configuration::deleteByName('NMC_FORCE_CURRENCY_ISO');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMoonewsmancoupon')) {
            $apiKey = Tools::getValue('NMC_API_KEY');
            $headerName = Tools::getValue('NMC_HEADER_NAME');
            $prefix = Tools::getValue('NMC_DEFAULT_PREFIX');
            $codeLen = (int) Tools::getValue('NMC_CODE_LENGTH');
            $maxBatch = (int) Tools::getValue('NMC_MAX_BATCH');
            $expiryDays = (int) Tools::getValue('NMC_DEFAULT_EXPIRY_DAYS');
            $currencyIso = Tools::getValue('NMC_FORCE_CURRENCY_ISO');

            Configuration::updateValue('NMC_API_KEY', $apiKey);
            Configuration::updateValue('NMC_HEADER_NAME', $headerName);
            Configuration::updateValue('NMC_DEFAULT_PREFIX', $prefix);
            Configuration::updateValue('NMC_CODE_LENGTH', max(6, min(32, $codeLen)));
            Configuration::updateValue('NMC_MAX_BATCH', max(1, min(10000, $maxBatch)));
            Configuration::updateValue('NMC_DEFAULT_EXPIRY_DAYS', max(1, min(3650, $expiryDays)));
            Configuration::updateValue('NMC_FORCE_CURRENCY_ISO', $currencyIso ?: 'EUR');

            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'NMC_API_KEY' => Configuration::get('NMC_API_KEY'),
            'NMC_HEADER_NAME' => Configuration::get('NMC_HEADER_NAME'),
            'NMC_DEFAULT_PREFIX' => Configuration::get('NMC_DEFAULT_PREFIX'),
            'NMC_CODE_LENGTH' => (int) Configuration::get('NMC_CODE_LENGTH'),
            'NMC_MAX_BATCH' => (int) Configuration::get('NMC_MAX_BATCH'),
            'NMC_DEFAULT_EXPIRY_DAYS' => (int) Configuration::get('NMC_DEFAULT_EXPIRY_DAYS'),
            'NMC_FORCE_CURRENCY_ISO' => Configuration::get('NMC_FORCE_CURRENCY_ISO'),
            'WEBHOOK_URL' => $this->getWebhookUrl(),
        ]);

        $output .= $this->renderForm();
        return $output;
    }

    protected function getWebhookUrl()
    {
        return $this->context->link->getModuleLink($this->name, 'webhook', [], null, null, (int) $this->context->shop->id);
    }

    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Newsman Coupon Generator settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Authorization Header name'),
                        'name' => 'NMC_HEADER_NAME',
                        'required' => true,
                        'hint' => $this->l('Header checked for authentication, e.g., x-api-key'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Authorization Header value (API key)'),
                        'name' => 'NMC_API_KEY',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Default prefix'),
                        'name' => 'NMC_DEFAULT_PREFIX',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Code length'),
                        'name' => 'NMC_CODE_LENGTH',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Max batch size'),
                        'name' => 'NMC_MAX_BATCH',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Default expiry (days, if not provided)'),
                        'name' => 'NMC_DEFAULT_EXPIRY_DAYS',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Force currency ISO code'),
                        'name' => 'NMC_FORCE_CURRENCY_ISO',
                        'hint' => $this->l('Always EUR as per requirement.'),
                    ],
                    [
                        'type' => 'free',
                        'label' => $this->l('Webhook URL'),
                        'name' => 'WEBHOOK_URL',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMoonewsmancoupon';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                'NMC_API_KEY' => Configuration::get('NMC_API_KEY'),
                'NMC_HEADER_NAME' => Configuration::get('NMC_HEADER_NAME'),
                'NMC_DEFAULT_PREFIX' => Configuration::get('NMC_DEFAULT_PREFIX'),
                'NMC_CODE_LENGTH' => (int) Configuration::get('NMC_CODE_LENGTH'),
                'NMC_MAX_BATCH' => (int) Configuration::get('NMC_MAX_BATCH'),
                'NMC_DEFAULT_EXPIRY_DAYS' => (int) Configuration::get('NMC_DEFAULT_EXPIRY_DAYS'),
                'NMC_FORCE_CURRENCY_ISO' => Configuration::get('NMC_FORCE_CURRENCY_ISO'),
                'WEBHOOK_URL' => '<code>' . $this->getWebhookUrl() . '</code>',
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }
}
