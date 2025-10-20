<?php
class MoonewsmancouponWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_header = false;
    public $display_footer = false;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');

        try {
            $this->checkAuth();
            $params = $this->parseParams();

            $codes = [];
            for ($i = 0; $i < $params['batch_size']; $i++) {
                $codes[] = $this->createCoupon($params);
            }

            die(json_encode(['status' => 1, 'codes' => $codes]));
        } catch (Exception $e) {
            http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
            die(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
        }
    }

    protected function checkAuth()
    {
        $headerName = trim(Configuration::get('NMC_HEADER_NAME'));
        $expected = (string) Configuration::get('NMC_API_KEY');

        // 1) Header-based auth
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $provided = '';
        if ($headerName) {
            foreach ($headers as $k => $v) {
                if (strtolower($k) === strtolower($headerName)) {
                    $provided = (string) $v;
                    break;
                }
            }
        }

        // 2) Fallback: api_key query parameter (as per user note: Newsman docs flow sometimes fails)
        if (!$provided) {
            $provided = (string) Tools::getValue('api_key', '');
        }

        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            throw new Exception('Unauthorized', 401);
        }
    }

    protected function parseParams()
    {
        $type = (int) Tools::getValue('type', -1);
        $value = Tools::getValue('value', null);
        $batch = (int) Tools::getValue('batch_size', 1);
        $prefix = (string) Tools::getValue('prefix', Configuration::get('NMC_DEFAULT_PREFIX'));
        $expire = Tools::getValue('expire_date');
        $minAmount = Tools::getValue('min_amount', null);
        $currencyIso = Configuration::get('NMC_FORCE_CURRENCY_ISO') ?: 'EUR';

        $maxBatch = (int) Configuration::get('NMC_MAX_BATCH');
        if ($batch < 1 || $batch > $maxBatch) {
            throw new Exception('Invalid batch_size', 400);
        }

        if (!in_array($type, [0, 1, 2], true)) {
            throw new Exception('Invalid type. Expected 0=fixed,1=percentage,2=free_shipping', 400);
        }

        if ($type !== 2) {
            if ($value === null || $value === '') {
                throw new Exception('Missing value for percent/fixed type', 400);
            }
            $value = (float) $value;
            if ($type === 1 && ($value <= 0 || $value > 100)) {
                throw new Exception('Percentage value must be within (0,100]', 422);
            }
            if ($type === 0 && $value <= 0) {
                throw new Exception('Fixed value must be > 0', 422);
            }
        } else {
            $value = 0.0;
        }

        $idCurrency = (int) Currency::getIdByIsoCode($currencyIso);
        if (!$idCurrency) {
            throw new Exception('Currency '.$currencyIso.' not found in shop', 422);
        }

        $dateFrom = date('Y-m-d H:i:s');
        if ($expire) {
            $ts = strtotime($expire);
            if ($ts === false) {
                throw new Exception('Invalid expire_date format. Expect YYYY-MM-DD HH:MM', 422);
            }
            $dateTo = date('Y-m-d H:i:00', $ts);
        } else {
            $days = (int) Configuration::get('NMC_DEFAULT_EXPIRY_DAYS');
            $dateTo = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        }

        $minAmountVal = null;
        if ($minAmount !== null && $minAmount !== '') {
            $minAmountVal = (float) $minAmount;
            if ($minAmountVal < 0) {
                throw new Exception('min_amount must be >= 0', 422);
            }
        }

        return [
            'type' => $type,
            'value' => (float) $value,
            'batch_size' => $batch,
            'prefix' => $prefix,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'min_amount' => $minAmountVal,
            'id_currency' => $idCurrency,
        ];
    }

    protected function createCoupon(array $p)
    {
        $code = $this->generateUniqueCode($p['prefix']);

        $rule = new CartRule();
        $rule->active = 1;
        $rule->code = $code;
        $rule->date_from = $p['date_from'];
        $rule->date_to = $p['date_to'];
        $rule->quantity = 1;
        $rule->quantity_per_user = 1;
        $rule->partial_use = 0;
        $rule->cart_rule_restriction = 1;
        $rule->minimum_amount = $p['min_amount'] !== null ? (float) $p['min_amount'] : 0.0;
        $rule->minimum_amount_tax = 0;
        $rule->minimum_amount_currency = $p['id_currency'];

        $rule->free_shipping = 0;
        $rule->reduction_amount = 0.0;
        $rule->reduction_tax = 0;
        $rule->reduction_currency = $p['id_currency'];
        $rule->reduction_percent = 0.0;

        if ((int) $p['type'] === 2) {
            $rule->free_shipping = 1;
        } elseif ((int) $p['type'] === 1) {
            $rule->reduction_percent = (float) $p['value'];
        } else {
            $rule->reduction_amount = (float) $p['value'];
        }

        $name = 'Nuolaida ' . $code;
        $rule->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $rule->name[(int) $lang['id_lang']] = $name;
        }

        $rule->id_customer = 0;
        $rule->highlight = 0;
        $rule->shopRestriction = true;

        if (!$rule->add()) {
            throw new Exception('Failed to create CartRule', 500);
        }

        if (Shop::isFeatureActive()) {
            $rule->associateTo([(int) $this->context->shop->id]);
        }

        return $code;
    }

    protected function generateUniqueCode($prefix)
    {
        $len = (int) Configuration::get('NMC_CODE_LENGTH');
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxRetry = 20;

        for ($i = 0; $i < $maxRetry; $i++) {
            $rand = '';
            for ($j = 0; $j < $len; $j++) {
                $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = trim($prefix) !== '' ? (rtrim($prefix, '-') . '-' . $rand) : $rand;

            if (!CartRule::getIdByCode($code)) {
                return $code;
            }
        }

        throw new Exception('Unable to generate unique code after retries', 500);
    }
}
