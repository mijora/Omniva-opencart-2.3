<?php
class ControllerExtensionShippingOmnivalt extends Controller
{
    private $error = array();
    protected $labelsMix = 4;

    public function install()
    {
        $sql = "ALTER TABLE " . DB_PREFIX . "order ADD `labelsCount` INT NOT NULL DEFAULT '1',
                                              ADD `omnivaWeight` FLOAT NOT NULL DEFAULT '1',
                                              ADD `cod_amount` FLOAT DEFAULT 0;";
        $this->db->query($sql);
        $this->load->model('setting/setting');
        $sql2 = "CREATE TABLE " . DB_PREFIX . "order_omniva (id int NOT NULL AUTO_INCREMENT, tracking TEXT, manifest int, labels text, id_order int, PRIMARY KEY (id), UNIQUE (id_order));";
        $this->model_setting_setting->editSetting('omniva', array('omniva_manifest' => 0));
        $this->db->query($sql2);
    }

    public function uninstall()
    {
        $sql = "ALTER TABLE " . DB_PREFIX . "order DROP COLUMN labelsCount,
                                        DROP COLUMN omnivaWeight,
                                        DROP COLUMN cod_amount; ";

        $this->db->query($sql);
        $sql2 = "DROP TABLE " . DB_PREFIX . "order_omniva";
        $this->db->query($sql2);

    }

    public function labelsCount()
    {
        if ($this->request->post['labelsCount'] and $this->request->post['order_id'] and $this->user->hasPermission('modify', 'extension/shipping/omnivalt')) {

            $labelsCount = $this->request->post['labelsCount'];
            $order = $this->request->post['order_id'];

            $labelsCount = $this->request->post['labelsCount'];
            $order = $this->request->post['order_id'];

            $sql = "UPDATE " . DB_PREFIX . "order SET labelsCount = $labelsCount WHERE order_id= $order;";

            $this->db->query($sql);

            //return $this->response->setOutput(json_encode($order.'ok'.$labelsCount));
        }

    }

    public function index()
    {
        $this->load->language('extension/shipping/omnivalt');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('omnivalt', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            if (!empty($this->request->post['download'])) {
                $this->fetchUpdates();
            } else {
                $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true));
            }

        }

        foreach (array('entry_free_price', 'cron_url', 'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_none', 'text_parcel_terminal', 'text_courier', 'text_sorting_center', 'entry_url', 'entry_user', 'entry_password', 'entry_service', 'entry_pickup_type', 'entry_company', 'entry_bankaccount', 'entry_pickupstart', 'entry_pickupfinish', 'entry_cod', 'entry_status', 'entry_sort_order', 'entry_parcel_terminal_price', 'entry_courier_price', 'entry_terminals', 'button_save', 'button_cancel', 'button_download', 'entry_sender_name', 'entry_sender_address', 'entry_sender_city', 'entry_sender_postcode', 'entry_sender_phone', 'entry_sender_country_code') as $key) {
            $data[$key] = $this->language->get($key);
        }

        foreach (array('warning', 'url', 'user', 'password') as $key) {
            if (isset($this->error[$key])) {
                $data['error_' . $key] = $this->error[$key];
            } else {
                $data['error_' . $key] = '';
            }
        }
        $sender_array = array('sender_name', 'sender_address', 'sender_phone',
            'sender_postcode', 'sender_city', 'sender_country_code',
            'sender_phone', 'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee',
            'courier_price', 'courier_pricelv', 'courier_priceee', 'lt_free', 'lv_free', 'ee_free', 'tax_class_id'
        );
        foreach ($sender_array as $key) {
            if (isset($this->error[$key])) {
                $data['error_' . $key] = $this->error[$key];
            } else {
                $data['error_' . $key] = '';
            }
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/shipping/omnivalt', 'token=' . $this->session->data['token'], true),
        );

        $data['action'] = $this->url->link('extension/shipping/omnivalt', 'token=' . $this->session->data['token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true);

        foreach ($sender_array as $key) {
            if (isset($this->request->post['omnivalt_' . $key])) {
                $data['omnivalt_' . $key] = $this->request->post['omnivalt_' . $key];
            } else {
                $data['omnivalt_' . $key] = $this->config->get('omnivalt_' . $key);
            }
        }

        if (isset($this->request->post['omnivalt_url'])) {
            $data['omnivalt_url'] = $this->request->post['omnivalt_url'];
        } else {
            $data['omnivalt_url'] = $this->config->get('omnivalt_url');
        }
        if ($data['omnivalt_url'] == '') {
            $data['omnivalt_url'] = 'https://217.159.234.93';
        }

        if (isset($this->request->post['omnivalt_user'])) {
            $data['omnivalt_user'] = $this->request->post['omnivalt_user'];
        } else {
            $data['omnivalt_user'] = $this->config->get('omnivalt_user');
        }

        if (isset($this->request->post['omnivalt_password'])) {
            $data['omnivalt_password'] = $this->request->post['omnivalt_password'];
        } else {
            $data['omnivalt_password'] = $this->config->get('omnivalt_password');
        }

        if (isset($this->request->post['omnivalt_service'])) {
            $data['omnivalt_service'] = $this->request->post['omnivalt_service'];
        } elseif ($this->config->has('omnivalt_service')) {
            $data['omnivalt_service'] = $this->config->get('omnivalt_service');
        } else {
            $data['omnivalt_service'] = array();
        }

        $data['services'] = array();

        $data['services'][] = array(
            'text' => $this->language->get('text_courier'),
            'value' => 'courier',
        );

        $data['services'][] = array(
            'text' => $this->language->get('text_parcel_terminal'),
            'value' => 'parcel_terminal',
        );

        if (isset($this->request->post['omnivalt_parcel_terminal_price'])) {
            $data['omnivalt_parcel_terminal_price'] = $this->request->post['omnivalt_parcel_terminal_price'];
        } else {
            $data['omnivalt_parcel_terminal_price'] = $this->config->get('omnivalt_parcel_terminal_price');
        }
        if (isset($this->request->post['omnivalt_courier_price'])) {
            $data['omnivalt_courier_price'] = $this->request->post['omnivalt_courier_price'];
        } else {
            $data['omnivalt_courier_price'] = $this->config->get('omnivalt_courier_price');
        }
        //Additions for Latvia
        if (isset($this->request->post['omnivalt_parcel_terminal_pricelv'])) {
            $data['omnivalt_parcel_terminal_pricelv'] = $this->request->post['omnivalt_parcel_terminal_pricelv'];
        } else {
            $data['omnivalt_parcel_terminal_pricelv'] = $this->config->get('omnivalt_parcel_terminal_pricelv');
        }
        if (isset($this->request->post['omnivalt_courier_pricelv'])) {
            $data['omnivalt_courier_pricelv'] = $this->request->post['omnivalt_courier_pricelv'];
        } else {
            $data['omnivalt_courier_pricelv'] = $this->config->get('omnivalt_courier_pricelv');
        }
        //Additions for Estonia
        if (isset($this->request->post['omnivalt_parcel_terminal_priceee'])) {
            $data['omnivalt_parcel_terminal_priceee'] = $this->request->post['omnivalt_parcel_terminal_priceee'];
        } else {
            $data['omnivalt_parcel_terminal_priceee'] = $this->config->get('omnivalt_parcel_terminal_priceee');
        }
        if (isset($this->request->post['omnivalt_courier_priceee'])) {
            $data['omnivalt_courier_priceee'] = $this->request->post['omnivalt_courier_priceee'];
        } else {
            $data['omnivalt_courier_priceee'] = $this->config->get('omnivalt_courier_priceee');
        }

        if (isset($this->request->post['omnivalt_company'])) {
            $data['omnivalt_company'] = $this->request->post['omnivalt_company'];
        } else {
            $data['omnivalt_company'] = $this->config->get('omnivalt_company');
        }

        if (isset($this->request->post['omnivalt_bankaccount'])) {
            $data['omnivalt_bankaccount'] = $this->request->post['omnivalt_bankaccount'];
        } else {
            $data['omnivalt_bankaccount'] = $this->config->get('omnivalt_bankaccount');
        }

        if (isset($this->request->post['omnivalt_pickupstart'])) {
            $data['omnivalt_pickupstart'] = $this->request->post['omnivalt_pickupstart'];
        } else {
            $data['omnivalt_pickupstart'] = $this->config->get('omnivalt_pickupstart');
        }
        if ($data['omnivalt_pickupstart'] == '') {
            $data['omnivalt_pickupstart'] = "8:00";
        }

        if (isset($this->request->post['omnivalt_pickupfinish'])) {
            $data['omnivalt_pickupfinish'] = $this->request->post['omnivalt_pickupfinish'];
        } else {
            $data['omnivalt_pickupfinish'] = $this->config->get('omnivalt_pickupfinish');
        }
        if ($data['omnivalt_pickupfinish'] == '') {
            $data['omnivalt_pickupfinish'] = "17:00";
        }

        if (isset($this->request->post['omnivalt_cod'])) {
            $data['omnivalt_cod'] = $this->request->post['omnivalt_cod'];
        } else {
            $data['omnivalt_cod'] = $this->config->get('omnivalt_cod');
        }

        if (isset($this->request->post['omnivalt_pickup_type'])) {
            $data['omnivalt_pickup_type'] = $this->request->post['omnivalt_pickup_type'];
        } else {
            $data['omnivalt_pickup_type'] = $this->config->get('omnivalt_pickup_type');
        }

        if (isset($this->request->post['omnivalt_status'])) {
            $data['omnivalt_status'] = $this->request->post['omnivalt_status'];
        } else {
            $data['omnivalt_status'] = $this->config->get('omnivalt_status');
        }

        if (isset($this->request->post['omnivalt_sort_order'])) {
            $data['omnivalt_sort_order'] = $this->request->post['omnivalt_sort_order'];
        } else {
            $data['omnivalt_sort_order'] = $this->config->get('omnivalt_sort_order');
        }

        if (isset($this->request->post['omnivalt_lt_free'])) {
			$data['omnivalt_lt_free'] = $this->request->post['omnivalt_lt_free'];
		} else {
			$data['omnivalt_lt_free'] = $this->config->get('omnivalt_lt_free');
        }
        if (isset($this->request->post['omnivalt_lv_free'])) {
                $data['omnivalt_lv_free'] = $this->request->post['omnivalt_lv_free'];
            } else {
                $data['omnivalt_lv_free'] = $this->config->get('omnivalt_lv_free');
        }
        if (isset($this->request->post['omnivalt_ee_free'])) {
			$data['omnivalt_ee_free'] = $this->request->post['omnivalt_ee_free'];
		} else {
			$data['omnivalt_ee_free'] = $this->config->get('omnivalt_ee_free');
		}

		if (isset($this->request->post['omnivalt_sort_order'])) {
			$data['omnivalt_sort_order'] = $this->request->post['omnivalt_sort_order'];
		} else {
			$data['omnivalt_sort_order'] = $this->config->get('omnivalt_sort_order');
		}
        $data['omnivalt_terminals'] = $this->model_setting_setting->getSetting('omnivalt_terminals');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');


        if (isset($this->request->post['omnivalt_class_id'])) {
			$data['omnivalt_tax_class_id'] = $this->request->post['omnivalt_tax_class_id'];
		} else {
			$data['omnivalt_tax_class_id'] = $this->config->get('omnivalt_tax_class_id');
    }
    $data['entry_tax_class'] = "Tax class";

    
    $this->load->model('localisation/tax_class');
    $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();




        $this->response->setOutput($this->load->view('extension/shipping/omnivalt', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/shipping/omnivalt')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['omnivalt_url']) {
            $this->error['url'] = $this->language->get('error_url');
        }

        if (!$this->request->post['omnivalt_user']) {
            $this->error['user'] = $this->language->get('error_user');
        }

        if (!$this->request->post['omnivalt_password']) {
            $this->error['password'] = $this->language->get('error_password');
        }

        foreach (array('tax_class_id', 'sender_name', 'sender_address', 'sender_phone', 'sender_postcode', 'sender_city', 'sender_country_code', 'sender_phone', 'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee', 'courier_price', 'courier_pricelv', 'courier_priceee', 'lt_free', 'lv_free', 'ee_free') as $key) {
            if (!$this->request->post['omnivalt_' . $key]) {
                $this->error[$key] = $this->language->get('error_required');
            }
        }
        return !$this->error;
    }

    private function fetchUpdates()
    {
        $terminals = array();
        $csv = $this->fetchURL('https://www.omniva.ee/locations.csv');
        if (empty($csv)) {
            return;
        }

        $countries = array();
        $countries['LT'] = 1;
        $countries['LV'] = 2;
        $countries['EE'] = 3;
        $cabins = $this->parseCSV($csv, $countries);
        if ($cabins) {
            $terminals = $cabins;
        }

        $this->model_setting_setting->editSetting('omnivalt_terminals', array('omnivalt_terminals_LT' => $terminals));
    }

    private function fetchURL($url)
    {
        $ch = curl_init(trim($url)) or die('cant create curl');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $out = curl_exec($ch) or die(curl_error($ch));
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            die('cannot fetch update from ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ': ' . curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }

        curl_close($ch);
        return $out;
    }

    private function parseCSV($csv, $countries = array())
    {
        $cabins = array();
        if (empty($csv)) {
            return $cabins;
        }

        if (mb_detect_encoding($csv, 'UTF-8, ISO-8859-1') == 'ISO-8859-1') {
            $csv = utf8_encode($csv);
        }

        $rows = str_getcsv($csv, "\n"); #parse the rows, remove first
        $newformat = count(str_getcsv($rows[0], ';')) > 10 ? 1 : 0;
        array_shift($rows);

        foreach ($rows as $row) {
            $cabin = str_getcsv($row, ';');
            # there are lines with all fields empty in estonian file, workaround
            if (count(array_filter($cabin))) {
                if ($newformat) {
                    if (!empty($countries[strtoupper(trim($cabin[3]))])) {
                        # closed ? exists on EE only
                        if (intval($cabin[2])) {
                            continue;
                        }

                        $cabin = array($cabin[1], $cabin[4], trim($cabin[5] . ' ' . ($cabin[8] != 'NULL' ? $cabin[8] : '') . ' ' . ($cabin[10] != 'NULL' ? $cabin[10] : '')), $cabin[0], $cabin[20], $cabin[3]);
                    } else {
                        $cabin = array();
                    }
                }
                if ($cabin) {
                    $cabins[] = $cabin;
                }

            }
        }
        return $cabins;
    }

    private function addHttps($url)
    {
        if (empty($_SERVER['HTTPS'])) {
            return $url;
        } elseif ($_SERVER['HTTPS'] == "on") {
            return str_replace('http://', 'https://', $url);
        } else {
            return $url;
        }
    }

    protected static function getReferenceNumber($order_number)
    {
        $order_number = (string) $order_number;
        $kaal = array(7, 3, 1);
        $sl = $st = strlen($order_number);
        $total = 0;
        while ($sl > 0 and substr($order_number, --$sl, 1) >= '0') {
            $total += substr($order_number, ($st - 1) - $sl, 1) * $kaal[($sl % 3)];
        }
        $kontrollnr = ((ceil(($total / 10)) * 10) - $total);
        return $order_number . $kontrollnr;
    }

    private function get_tracking_number($order, $weight = 1, $packs = 1, $sendType = 'parcel')
    {

        if (stripos($order['shipping_code'], 'omnivalt') === false) {
            return array('error' => 'Not Omnivalt shipping method');
        }

        $terminal_id = 0;
        if (stripos($order['shipping_code'], 'parcel_terminal_') !== false) {
            $terminal_id = str_ireplace('omnivalt.parcel_terminal_', '', $order['shipping_code']);
        }

        $send_method = '';
        if (stripos($order['shipping_code'], 'parcel_terminal') !== false) {
            $send_method = 'pt';
        }

        if (stripos($order['shipping_code'], 'courier') !== false) {
            $send_method = 'c';
        }

        $pickup_method = $this->config->get('omnivalt_pickup_type');
        $service = "";
        switch ($pickup_method . ' ' . $send_method) {
            case 'courier pt':
                $service = "PU";
                break;
            case 'courier c':
                $service = "QH";
                break;
            case 'parcel_terminal c':
                $service = "PK";
                break;
            case 'parcel_terminal pt':
                $service = "PA";
                break;
            case 'sorting_center c':
                $service = "QL";
                break;
            case 'sorting_center pt':
                $service = "PP";
                break;
            default:
                $service = "";
                break;
        }
        $parcel_terminal = "";
        if ($send_method == "pt") {
            $parcel_terminal = 'offloadPostcode="' . $terminal_id . '" ';
        }

        $additionalService = '';
        if ($service == "PA" || $service == "PU" || $service == "PP") {
            $additionalService .= '<option code="ST" />';
        }

        if (($order['payment_code'] == 'cod' || $order['cod_amount'] > 0) && intval($order['cod_amount']) != 888888) {
            $additionalService .= '<option code="BP" />';
            $order['payment_code'] = 'cod';
        } else {
            $order['payment_code'] = 'cod2';
        }

        if ($additionalService) {
            $additionalService = '<add_service>' . $additionalService . '</add_service>';
        }

        if ($order['cod_amount'] > 0) {
            $cod_amount = $order['cod_amount'];
        } else {
            $cod_amount = $order['total'];
        }

        $phones = '';
        if ($order['telephone']) {
            $phones .= '<mobile>' . $order['telephone'] . '</mobile>';
        }

        $pickStart = $this->config->get('omnivalt_pickupstart') ? $this->config->get('omnivalt_pickupstart') : '8:00';
        $pickFinish = $this->config->get('omnivalt_pickupfinish') ? $this->config->get('omnivalt_pickupfinish') : '17:00';
        $pickDay = date('Y-m-d');
        if (time() > strtotime($pickDay . ' ' . $pickFinish)) {
            $pickDay = date('Y-m-d', strtotime($pickDay . "+1 days"));
        }

        $shop_country_iso = $order['shipping_iso_code_2'];
        $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:businessToClientMsgRequest>
                 <partner>' . $this->config->get('omnivalt_user') . '</partner>
                 <interchange msg_type="info11">
                    <header file_id="' . \Date('YmdHms') . '" sender_cd="' . $this->config->get('omnivalt_user') . '" >
                    </header>
                    <item_list>
                      ';

        if ($packs > 1 and $sendType != 'parcel') {
            $assignCount = 'packetUnitIdentificator="' . $order['id'] . '"';
        } else {
            $assignCount = null;
        }
        for ($i = 0; $i < $packs; $i++):
            $postCode = preg_match('/\d+/', $order['shipping_postcode'], $matches); //426r    <address postcode="'.$order['shipping_postcode'].'"
            $postCode = $postCode ? $matches[0] : '';
            $xmlRequest .= '
		                       <item service="' . $service . '" ' . $assignCount . '>
		                          ' . $additionalService . '
		                          <measures weight="' . $weight . '" />
		                          ' . $this->cod($order, ($order['payment_code'] == 'cod'), $cod_amount) . '
		                          <receiverAddressee >
		                             <person_name>' . $order['shipping_firstname'] . ' ' . $order['shipping_lastname'] . '</person_name>
		                            ' . $phones . '
		                             <address postcode="' . $postCode . '" ' . $parcel_terminal . ' deliverypoint="' . ($order['shipping_city'] ? $order['shipping_city'] : $order['shipping_zone']) . '" country="' . $order['shipping_iso_code_2'] . '" street="' . $order['shipping_address_1'] . '" />
		                          </receiverAddressee>
		                          <!--Optional:-->
		                          <returnAddressee>
		                             <person_name>' . $this->config->get('omnivalt_sender_name') . '</person_name>
		                             <!--Optional:-->
		                             <phone>' . $this->config->get('omnivalt_sender_phone') . '</phone>
		                             <address postcode="' . $this->config->get('omnivalt_sender_postcode') . '" deliverypoint="' . $this->config->get('omnivalt_sender_city') . '" country="' . $this->config->get('omnivalt_sender_country_code') . '" street="' . $this->config->get('omnivalt_sender_address') . '" />

		                          </returnAddressee>

		                       </item>';
        endfor;
        $xmlRequest .= '
                    </item_list>
                 </interchange>
              </xsd:businessToClientMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';

        return self::api_request($xmlRequest);
    }
    private function getOrderWeight($order_id)
    {
        $query = $this->db->query("SELECT SUM(IF(wcd.unit ='g',(p.weight/1000),p.weight) * op.quantity) AS weight FROM " . DB_PREFIX . "order_product op LEFT JOIN " . DB_PREFIX . "product p ON op.product_id = p.product_id LEFT JOIN " . DB_PREFIX . "weight_class_description wcd ON wcd.weight_class_id = p.weight_class_id AND wcd.language_id = '" . (int) $this->config->get('config_language_id') . "' WHERE op.order_id = '" . (int) $order_id . "'");
        if ($query->row['weight']) {
            $weight = $query->row['weight'];
        } else {
            $weight = 1;
        }

        return $weight;
    }

    public function api_request($request)
    {
        $barcodes = array();
        $errors = array();
        $url = $this->config->get('omnivalt_url') . '/epmx/services/messagesService.wsdl';

        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: " . strlen($request),
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config->get('omnivalt_user') . ":" . $this->config->get('omnivalt_password'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $xmlResponse = curl_exec($ch);

        if ($xmlResponse === false) {
            $errors[] = curl_error($ch);
        } else {
            $errorTitle = '';
            if (strlen(trim($xmlResponse)) > 0) {

                $xmlResponse = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $xmlResponse);
                $xml = simplexml_load_string($xmlResponse);
                if (!is_object($xml)) {
                    $errors[] = $this->l('Response is in the wrong format');
                }
                if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo)) {
                    foreach ($xml->Body->businessToClientMsgResponse->faultyPacketInfo->barcodeInfo as $data) {
                        $errors[] = $data->clientItemId . ' - ' . $data->barcode . ' - ' . $data->message;
                    }
                }
                if (empty($errors)) {
                    if (is_object($xml) && is_object($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo)) {
                        foreach ($xml->Body->businessToClientMsgResponse->savedPacketInfo->barcodeInfo as $data) {
                            $barcodes[] = (string) $data->barcode;
                        }
                    }
                }

            }
        }
        // }
        if (!empty($errors)) {
            return array('status' => false, 'msg' => implode('. ', $errors));
        } else {
            if (!empty($barcodes)) {
                return array('status' => true, 'barcodes' => $barcodes);
            }

            $errors[] = 'No saved barcodes received';
            return array('status' => false, 'msg' => implode('. ', $errors));
        }
    }
    private function getShipmentLabels($barcodes, $order_id = null)
    {

        $errors = array();
        $barcodeXML = '';
        $xmlRequest = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
           <soapenv:Header/>
           <soapenv:Body>
              <xsd:addrcardMsgRequest>
                 <partner>' . $this->config->get('omnivalt_user') . '</partner>
                 <sendAddressCardTo>response</sendAddressCardTo>
                 <barcodes>
                 <barcode>' . $barcodes . '</barcode>

                 </barcodes>
              </xsd:addrcardMsgRequest>
           </soapenv:Body>
        </soapenv:Envelope>';

        try {
            $url = $this->config->get('omnivalt_url') . '/epmx/services/messagesService.wsdl';
            $headers = array(
                "Content-type: text/xml;charset=\"utf-8\"",
                "Accept: text/xml",
                "Cache-Control: no-cache",
                "Pragma: no-cache",
                "Content-length: " . strlen($xmlRequest),
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_USERPWD, $this->config->get('omnivalt_user') . ":" . $this->config->get('omnivalt_password'));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $xmlResponse = curl_exec($ch);
            $debugData['result'] = $xmlResponse;
        } catch (\Exception $e) {
            $errors[] = $e->getMessage() . ' ' . $e->getCode();
            $xmlResponse = '';
        }
        $xmlResponse = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $xmlResponse);
        $xml = simplexml_load_string($xmlResponse);
        if (!is_object($xml)) {
            $errors[] = self::l('Response is in the wrong format');
        }

        if (is_object($xml) && is_object($xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->barcode)) {
            $shippingLabelContent = (string) $xml->Body->addrcardMsgResponse->successAddressCards->addressCardData->fileData;
            file_put_contents(DIR_DOWNLOAD . 'omnivalt_' . $order_id . '.pdf', base64_decode($shippingLabelContent));

        } else {
            $errors[] = 'No label received from webservice';
        }

        if (!empty($errors)) {
            return array('status' => false, 'msg' => implode('. ', $errors));
        } else {
            if (!empty($barcodes)) {
                return array('status' => true);
            }

            $errors[] = self::l('No saved barcodes received');
            return array('status' => false, 'msg' => implode('. ', $errors));
        }
    }

    public function getTracking($tracking)
    {
        $url = $this->config->get('omnivalt_url') . '/epteavitus/events/from/' . date("c", strtotime("-1 week +1 day")) . '/for-client-code/' . $this->config->get('omnivalt_user');
        $process = curl_init();
        $additionalHeaders = '';
        curl_setopt($process, CURLOPT_URL, $url);
        curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/xml', $additionalHeaders));
        curl_setopt($process, CURLOPT_HEADER, false);
        curl_setopt($process, CURLOPT_USERPWD, $this->config->get('omnivalt_user') . ":" . $this->config->get('omnivalt_password'));
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        $return = curl_exec($process);
        curl_close($process);
        if ($process === false) {
            return false;
        }
        return $this->parseXmlTrackingResponse($tracking, $return);
    }

    public function parseXmlTrackingResponse($trackings, $response)
    {
        $errors = array();
        $resultArr = array();

        if (strlen(trim($response)) > 0) {
            $xml = simplexml_load_string($response);
            if (!is_object($xml)) {
                $errors[] = $this->l('Response is in the wrong format');
            }
            if (is_object($xml) && is_object($xml->event)) {
                foreach ($xml->event as $awbinfo) {
                    $awbinfoData = [];

                    $trackNum = isset($awbinfo->packetCode) ? (string) $awbinfo->packetCode : '';

                    if (!in_array($trackNum, $trackings)) {
                        continue;
                    }

                    $packageProgress = [];
                    if (isset($resultArr[$trackNum]['progressdetail'])) {
                        $packageProgress = $resultArr[$trackNum]['progressdetail'];
                    }

                    $shipmentEventArray = [];
                    $shipmentEventArray['activity'] = $this->getEventCode((string) $awbinfo->eventCode);

                    $shipmentEventArray['deliverydate'] = DateTime::createFromFormat('U', strtotime($awbinfo->eventDate));
                    $shipmentEventArray['deliverylocation'] = $awbinfo->eventSource;
                    $packageProgress[] = $shipmentEventArray;

                    $awbinfoData['progressdetail'] = $packageProgress;

                    $resultArr[$trackNum] = $awbinfoData;
                }
            }
        }

        if (!empty($errors)) {
            return false;
        }
        return $resultArr;
    }

    public function getEventCode($code)
    {
        $tracking = [
            'PACKET_EVENT_IPS_C' => $this->l("Shipment from country of departure"),
            'PACKET_EVENT_FROM_CONTAINER' => $this->l("Arrival to post office"),
            'PACKET_EVENT_IPS_D' => $this->l("Arrival to destination country"),
            'PACKET_EVENT_SAVED' => $this->l("Saving"),
            'PACKET_EVENT_DELIVERY_CANCELLED' => $this->l("Cancelling of delivery"),
            'PACKET_EVENT_IN_POSTOFFICE' => $this->l("Arrival to Omniva"),
            'PACKET_EVENT_IPS_E' => $this->l("Customs clearance"),
            'PACKET_EVENT_DELIVERED' => $this->l("Delivery"),
            'PACKET_EVENT_FROM_WAYBILL_LIST' => $this->l("Arrival to post office"),
            'PACKET_EVENT_IPS_A' => $this->l("Acceptance of packet from client"),
            'PACKET_EVENT_IPS_H' => $this->l("Delivery attempt"),
            'PACKET_EVENT_DELIVERING_TRY' => $this->l("Delivery attempt"),
            'PACKET_EVENT_DELIVERY_CALL' => $this->l("Preliminary calling"),
            'PACKET_EVENT_IPS_G' => $this->l("Arrival to destination post office"),
            'PACKET_EVENT_ON_ROUTE_LIST' => $this->l("Dispatching"),
            'PACKET_EVENT_IN_CONTAINER' => $this->l("Dispatching"),
            'PACKET_EVENT_PICKED_UP_WITH_SCAN' => $this->l("Acceptance of packet from client"),
            'PACKET_EVENT_RETURN' => $this->l("Returning"),
            'PACKET_EVENT_SEND_REC_SMS_NOTIF' => $this->l("SMS to receiver"),
            'PACKET_EVENT_ARRIVED_EXCESS' => $this->l("Arrival to post office"),
            'PACKET_EVENT_IPS_I' => $this->l("Delivery"),
            'PACKET_EVENT_ON_DELIVERY_LIST' => $this->l("Handover to courier"),
            'PACKET_EVENT_PICKED_UP_QUANTITATIVELY' => $this->l("Acceptance of packet from client"),
            'PACKET_EVENT_SEND_REC_EMAIL_NOTIF' => $this->l("E-MAIL to receiver"),
            'PACKET_EVENT_FROM_DELIVERY_LIST' => $this->l("Arrival to post office"),
            'PACKET_EVENT_OPENING_CONTAINER' => $this->l("Arrival to post office"),
            'PACKET_EVENT_REDIRECTION' => $this->l("Redirection"),
            'PACKET_EVENT_IN_DEST_POSTOFFICE' => $this->l("Arrival to receiver's post office"),
            'PACKET_EVENT_STORING' => $this->l("Storing"),
            'PACKET_EVENT_IPS_EDD' => $this->l("Item into sorting centre"),
            'PACKET_EVENT_IPS_EDC' => $this->l("Item returned from customs"),
            'PACKET_EVENT_IPS_EDB' => $this->l("Item presented to customs"),
            'PACKET_EVENT_IPS_EDA' => $this->l("Held at inward OE"),
            'PACKET_STATE_BEING_TRANSPORTED' => $this->l("Being transported"),
            'PACKET_STATE_CANCELLED' => $this->l("Cancelled"),
            'PACKET_STATE_CONFIRMED' => $this->l("Confirmed"),
            'PACKET_STATE_DELETED' => $this->l("Deleted"),
            'PACKET_STATE_DELIVERED' => $this->l("Delivered"),
            'PACKET_STATE_DELIVERED_POSTOFFICE' => $this->l("Arrived at post office"),
            'PACKET_STATE_HANDED_OVER_TO_COURIER' => $this->l("Transmitted to courier"),
            'PACKET_STATE_HANDED_OVER_TO_PO' => $this->l("Re-addressed to post office"),
            'PACKET_STATE_IN_CONTAINER' => $this->l("In container"),
            'PACKET_STATE_IN_WAREHOUSE' => $this->l("At warehouse"),
            'PACKET_STATE_ON_COURIER' => $this->l("At delivery"),
            'PACKET_STATE_ON_HANDOVER_LIST' => $this->l("In transition sheet"),
            'PACKET_STATE_ON_HOLD' => $this->l("Waiting"),
            'PACKET_STATE_REGISTERED' => $this->l("Registered"),
            'PACKET_STATE_SAVED' => $this->l("Saved"),
            'PACKET_STATE_SORTED' => $this->l("Sorted"),
            'PACKET_STATE_UNCONFIRMED' => $this->l("Unconfirmed"),
            'PACKET_STATE_UNCONFIRMED_NO_TARRIF' => $this->l("Unconfirmed (No tariff)"),
            'PACKET_STATE_WAITING_COURIER' => $this->l("Awaiting collection"),
            'PACKET_STATE_WAITING_TRANSPORT' => $this->l("In delivery list"),
            'PACKET_STATE_WAITING_UNARRIVED' => $this->l("Waiting, hasn't arrived"),
            'PACKET_STATE_WRITTEN_OFF' => $this->l("Written off"),
        ];
        if (isset($tracking[$code])) {
            return $tracking[$code];
        }

        return '';
    }

    private function cod($order, $cod = 0, $amount = 0)
    {
        $company = $this->config->get('omnivalt_company');
        $bank_account = $this->config->get('omnivalt_bankaccount');
        $setting_cod = $this->config->get('omnivalt_cod');
        if ($cod) {
            return '<monetary_values>
            <cod_receiver>' . $company . '</cod_receiver>
            <values code="item_value" amount="' . $amount . '"/>
            </monetary_values>
            <account>' . $bank_account . '</account>
            <reference_number>' . self::getReferenceNumber($order['order_id']) . '</reference_number>';
        } else {
            return '';
        }
    }

    private function updateOrderStatus($order_id, $order_status_id, $comment)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = '" . (int) $order_status_id . "', `date_modified` = NOW() WHERE `order_id` = '" . (int) $order_id . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . (int) $order_id . "', `order_status_id` = '" . (int) $order_status_id . "', `notify` = '0', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");
    }

    public function labels()
    {
        if (isset($_GET['order_id'])) {
            $_POST['selected'] = array($_GET['order_id']);
        }

        if (isset($_POST['selected']) && count($_POST['selected'])) {
            $status_id = $this->readyStatus();
            $this->load->model('setting/setting');
            $this->load->model('sale/order');
            require_once DIR_SYSTEM . 'omnivalt_lib/tcpdf/tcpdf.php';
            require_once DIR_SYSTEM . 'omnivalt_lib/fpdi/fpdi.php';
            $errors = array();
            $object = '';
            $pages = 0;
            $pdf = new FPDI();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            foreach ($_POST['selected'] as $order_id) {

                $order = $this->model_sale_order->getOrder($order_id);
                if (!(stripos($order['shipping_code'], 'omnivalt.courier') !== false || stripos($order['shipping_code'], 'omnivalt.parcel_terminal') !== false)) {
                    continue;
                }

                //cicle in case for additional labels
                $count = intval($order['labelsCount']);
                $fullWeight = $this->getOrderWeight($order['order_id']);
                if ($fullWeight == null) {
                    $fullWeight = 0;
                }

                $weight = $fullWeight / $count;

                if ($order['shipping_code'] == 'omnivalt.courier') {
/************ container for courier *********/
                    $pack = $count;
                    $order['id'] = $order_id;
                    $labels = $order_id . '_' . $pack;
                    if ($pack == 1) {
                        $labels = $order_id . '_0';
                    }

//var_dump($labels);
                    //if printed
                    $shortage = 0;
                    $shortageLbl = '';
                    for ($i = 0; $i < $count; $i++) {
                        $searchFor = $order_id . '_' . $i;
                        if (!file_exists(DIR_DOWNLOAD . 'omnivalt_' . $searchFor . '.pdf')) { //echo'filo ner';
                            $shortage += 1;
                            if ($shortageLbl == '') {
                                $shortageLbl = $i;
                            }

                        }
                    }
                    /*print'<pre>';
                    var_dump($this->getOrderTrack($order_id));
                    print'</pre>';
                    exit();*/
                    if ($shortage > 0 && is_integer($shortageLbl)) { //var_dump($shortageLbl);exit();
                        $status = $this->get_tracking_number($order, $weight, $shortage, 'courier');
                        if ($status['status']) {
                            foreach ($status['barcodes'] as $barcode) {
                                $labelPrint = $order_id . '_' . $shortageLbl;
                                $this->getShipmentLabels($barcode, $labelPrint);
                                if (isset($barcode) and $barcode == true) {
                                    //var_dump($barcode);

                                    $this->updateOrderStatus($order_id, $status_id, $barcode);
                                    $this->setOmnivaOrder($order_id, $barcode);
                                }
                            }
                        } else {
                            $errors[] = $order_id . ' - ' . $status['msg'];
                            continue;
                        }
                    }
                    for ($ik = 0; $ik < $count; $ik++) {
                        $labels = $order_id . '_' . $ik;
                        $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                        if (!file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            continue;
                        }

                        $pagecount = $pdf->setSourceFile($label_url);
                        $newPG = array(0, 4, 8, 12, 16, 20, 24, 28, 32);
                        if ($this->labelsMix >= 4) {
                            $pdf->AddPage();
                            $page = 1;
                            $templateId = $pdf->importPage($page);
                            $this->labelsMix = 0;
                        }
                        $tplidx = $pdf->ImportPage(1);
                        if ($this->labelsMix == 0) {
                            $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, true);
                        } else if ($this->labelsMix == 1) {
                            $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, true);
                        } else if ($this->labelsMix == 2) {
                            $pdf->useTemplate($tplidx, 5, 180, 94.5, 108, true);
                        } else if ($this->labelsMix == 3) {

                            $pdf->useTemplate($tplidx, 110, 180, 94.5, 108, true);

                        } else {echo 'Problems with labels count, please, select one order!!!';}
                        $pages++;

                        $this->labelsMix++;
                    }
                } else {
/**************container for parcel terminals ************* */
                    for ($count = 0; $count < intval($order['labelsCount']); $count++) {

                        $pack = $count;
                        $labels = $order_id . '_' . $pack;
                        if ($pack == 1) {
                            $labels = $order_id;
                        }

                        $track_numer = true;
                        if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            $track_numer = false;
                        }

                        if ($track_numer != false) {
                            $status = $this->get_tracking_number($order, $weight);
                            if ($status['status']) {
                                $track_numer = $status['barcodes'][0];
                                if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                                    unlink(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf');
                                }
                            } else {
                                $errors[] = $order_id . ' - ' . $status['msg'];
                                continue;
                            }
                        }
                        $label_url = '';
                        if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                        }
                        if ($label_url == '') {
                            $label_status = $this->getShipmentLabels($track_numer, $labels);
                            if ($label_status['status']) {
                                if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                                    $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                                }
                            } else {
                                $errors[] = $order_id . ' - ' . $label_status['msg'];
                            }
                            if ($label_url == '') {
                                continue;
                            }

                        }
                        //var_dump($this->labelsMix);exit();
                        $pagecount = $pdf->setSourceFile($label_url);
                        $newPG = array(0, 4, 8, 12, 16, 20, 24, 28, 32);
                        if ($this->labelsMix >= 4) {
                            $pdf->AddPage();
                            $page = 1;
                            $templateId = $pdf->importPage($page);
                            $this->labelsMix = 0;
                        }
                        //for ($i = 1; $i <= $pagecount; $i++) {
                        $tplidx = $pdf->ImportPage(1);

                        //if ($x == 1){
                        if ($this->labelsMix == 0) {
                            $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, true);
                            //} else if($x==2){
                        } else if ($this->labelsMix == 1) {
                            $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, true);
                            //} else if($x==3) {
                        } else if ($this->labelsMix == 2) {
                            $pdf->useTemplate($tplidx, 5, 180, 94.5, 108, true);
                        } else if ($this->labelsMix == 3) {

                            $pdf->useTemplate($tplidx, 110, 180, 94.5, 108, true);

                        } else {echo 'Problems with labels count, please, select one order!!!';}
                        $pages++;
                        //}

                        //$x++;
                        //if($ik != $cicleCount -1)
                        $this->labelsMix++;
                        if ($track_numer) {
                            $this->updateOrderStatus($order_id, $status_id, $track_numer);
                            $this->setOmnivaOrder($order_id, $track_numer);

                        }

                    }
/****************************** */
                }
            }
            if ($pages) {
                //header("Content-Type: application/octet-stream");
                //header("Content-Type: application/download");
                $pdf->Output('Omnivalt_labels.pdf'); //echo'kuku';
            } else {
                echo implode('<br/>', $errors);
            }
        } else {
            echo "No orders selected";
            return 0;
        }
    }

    public function setOmnivaOrder($id_order = '', $tracking = '', $label = '')
    {
        $isPrinted = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_omniva WHERE id_order=" . $id_order . ";");
        $manifest = $this->config->get('omniva_manifest');

        if ($isPrinted->num_rows > 0) {
            $trackingArr = json_decode($isPrinted->rows[0]['tracking']);
            if (!is_array($trackingArr)) {
                $trackingArr = array();
            }

            array_unshift($trackingArr, $tracking);

            $this->db->query("UPDATE " . DB_PREFIX . "order_omniva SET tracking='" . json_encode($trackingArr) . "' WHERE id_order=" . $id_order . ";");
        } else if ($isPrinted->num_rows == 0) {
            $tracking = array($tracking);
            $tracking = json_encode($tracking);
            $this->db->query("INSERT INTO " . DB_PREFIX . "order_omniva (tracking, manifest, labels, id_order)
      VALUES ('$tracking','$manifest','$label','$id_order')");
        };
    }

    private function readyStatus()
    {
        $this->load->model('setting/setting');
        $status_id = $this->model_setting_setting->getSettingValue('omnivalt_status_id');
        if ($status_id) {
            $query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order_status` WHERE  `order_status_id` = " . (int) $status_id);
            if (!$query->row) {
                $status_id = false;
            }
        }
        if (!$status_id) {
            $query = $this->db->query("INSERT INTO `" . DB_PREFIX . "order_status` (`language_id`,`name`) VALUES (" . (int) $this->config->get('config_language_id') . ",'Ready for shipping')");
            $status_id = $this->db->getLastId();
            if ($status_id) {
                $this->model_setting_setting->editSetting('omnivalt_status', array('omnivalt_status_id' => $status_id));
            }

        }
        return $status_id;
    }

    private function getShippedStatusId()
    {
        $query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order_status` WHERE  `name` = 'Shipped'");
        if ($query->row) {
            return $query->row['order_status_id'];
        }
        return 0;
    }

    private function shipOrder($order_id)
    {
        $shiped_status_id = $this->getShippedStatusId();
        if ($shiped_status_id) {
            $this->updateOrderStatus($order_id, $shiped_status_id, 'Shipped by Omniva');
        }
    }

    private function getOrderTrack($order_id)
    {
        $status_id = $this->readyStatus();
        if ($status_id) {
            $query = $this->db->query("SELECT comment FROM `" . DB_PREFIX . "order_history` WHERE  `order_status_id` = " . (int) $status_id . " AND `order_id` = " . (int) $order_id . " ORDER BY `order_history_id` DESC");
            if ($query->row && $query->row['comment'] != '')
            {
                return $query->rows;
            }
        }
        return '';
    }
    public function printDocs()
    {
        if (!isset($this->request->post['print'])) {
            exit();
        }

        $this->load->model('setting/setting');
        $manifest = intval($this->config->get('omniva_manifest'));
        if (isset($_POST['manifest']) and intval($_POST['manifest']) == $manifest and $_POST['print'] == 'manifest' and !isset($this->request->post['new'])) {
            $manifest++;
            $this->model_setting_setting->editSetting('omniva', array('omniva_manifest' => $manifest));
        }

        switch ($_POST['print']) {
            case 'manifest':
                if (isset($this->request->post['new'])) {
                    print 'Please generate labels first!!!';
                    exit();
                }
                $this->manifest();
                break;
            case 'labels':
                $this->labels();
                break;
        }
    }

    public function manifest()
    {
        if (isset($_POST['selected']) && count($_POST['selected'])) {
            global $cookie;
            require_once DIR_SYSTEM . 'omnivalt_lib/tcpdf/tcpdf.php';
            $this->load->model('setting/setting');
            $this->load->model('sale/order');
            $this->load->model('catalog/product');
            $object = '';
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $order_table = '';
            $count = 1;
            foreach ($_POST['selected'] as $order_id) {
                $order = $this->model_sale_order->getOrder($order_id);
                $quantity = $this->model_sale_order->getOrderProducts($order_id);
                $productCount = 0;
                $kilos = 0;
                foreach ($quantity as $product) {
                    $plusCount = intval($product['quantity']);
                    $productCount += $plusCount;
                    $product = $this->model_catalog_product->getProduct($product['product_id']);
                    $plusKilo = floatval($product['weight']);
                    $kilos += $plusKilo * $plusCount;
                }

                $productsCnt = intval($order['labelsCount']);
                if ($productsCnt == 0) {
                    $productsCnt = 1;
                }

                $kilos = $kilos / $productsCnt;
                if (!(stripos($order['shipping_code'], 'omnivalt.courier') !== false || stripos($order['shipping_code'], 'omnivalt.parcel_terminal') !== false)) {
                    continue;
                }

                $track_numer = $this->getOrderTrack($order_id);

                if (intval($order['labelsCount']) > count($track_numer) and $track_numer != 0) {
                    $rows = count($track_numer);
                } else if (intval($order['labelsCount']) <= count($track_numer) and $track_numer != 0) {
                    $rows = $order['labelsCount'];
                } else {
                    print 'Please generate labels first';
                    exit;
                }

                for ($i = 0; $i < $rows; $i++) {
                    if ($track_numer[$i] == '') {
                        $status = $this->get_tracking_number($order);
                        if ($status['status']) {
                            $status_id = $this->readyStatus();
                            if ($track_numer) {
                                $this->updateOrderStatus($order_id, $status_id, $track_numer);
                            }
                            if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $order_id . '.pdf')) {
                                unlink(DIR_DOWNLOAD . 'omnivalt_' . $order_id . '.pdf');
                            }
                        } else {
                            continue;
                        }
                    }
                    $order_table .= '<tr><td width = "40" align="right">' . $count . '.</td><td>' . $track_numer[$i]['comment'] . '</td><td width = "60">' . date('Y-m-d') . '</td><td width = "40">1</td><td width = "60">' . $kilos . '</td><td width = "210">' . $order['shipping_firstname'] . ' ' . $order['shipping_lastname'] . ', ' . $order['shipping_address_1'] . ', ' . $order['shipping_postcode'] . ', ' . $order['shipping_city'] . ' ' . $order['shipping_country'] . '</td></tr>';
                    $count++;
                    $this->shipOrder($order_id);
                }
            }
            $pdf->SetFont('freeserif', '', 14);
            $shop_addr = '<table cellspacing="0" cellpadding="1" border="0"><tr><td>' . date('Y-m-d H:i:s') . '</td><td>Siuntėjo adresas:<br/>' . $this->config->get('omnivalt_sender_name') . '<br/>' . $this->config->get('omnivalt_sender_address') . ', ' . $this->config->get('omnivalt_sender_postcode') . '<br/>' . $this->config->get('omnivalt_sender_city') . ', ' . $this->config->get('omnivalt_sender_country_code') . '<br/></td></tr></table>';

            $pdf->writeHTML($shop_addr, true, false, false, false, '');
            $tbl = '
        <table cellspacing="0" cellpadding="4" border="1">
          <thead>
            <tr>
              <th width = "40" align="right">Nr.</th>
              <th>Siuntos numeris</th>
              <th width = "60">Data</th>
              <th width = "40" >Kiekis</th>
              <th width = "60">Svoris (kg)</th>
              <th width = "210">Gavėjo adresas</th>
            </tr>
          </thead>
          <tbody>
            ' . $order_table . '
          </tbody>
        </table><br/><br/>
        ';
            $pdf->SetFont('freeserif', '', 9);
            $pdf->writeHTML($tbl, true, false, false, false, '');
            $pdf->SetFont('freeserif', '', 14);
            $sign = 'Kurjerio vardas, pavardė, parašas ________________________________________________<br/><br/>';
            $sign .= 'Siuntėjo vardas, pavardė, parašas ________________________________________________';
            $pdf->writeHTML($sign, true, false, false, false, '');
            $pdf->Output('Omnivalt_manifest.pdf', 'I');

        } else {
            echo "No orders selected";
            return 0;
        }
    }
    /*************************** */
    public function generateLabel()
    {
        $this->labels2(true);
    }

    public function labels2($generate = false)
    {
        if (isset($_GET['order_id'])) {
            $_POST['selected'] = array($_GET['order_id']);
        }

        if (isset($_POST['selected']) && count($_POST['selected'])) {
            $status_id = $this->readyStatus();
            $this->load->model('setting/setting');
            $this->load->model('sale/order');
            require_once DIR_SYSTEM . 'omnivalt_lib/tcpdf/tcpdf.php';
            require_once DIR_SYSTEM . 'omnivalt_lib/fpdi/fpdi.php';
            $errors = array();
            $object = '';
            $pages = 0;
            $pdf = new FPDI();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            foreach ($_POST['selected'] as $order_id) {

                $order = $this->model_sale_order->getOrder($order_id);
                if (!(stripos($order['shipping_code'], 'omnivalt.courier') !== false || stripos($order['shipping_code'], 'omnivalt.parcel_terminal') !== false)) {
                    continue;
                }

                //cicle in case for additional labels
                $count = intval($order['labelsCount']);
                $fullWeight = $this->getOrderWeight($order['order_id']);
                if (isset($order['omnivaWeight']) && $order['omnivaWeight'] > 0) {
                    $fullWeight = $order['omnivaWeight'];
                }

                if ($fullWeight == null) {
                    $fullWeight = 0;
                }

                $weight = $fullWeight / $count;

                if ($order['shipping_code'] == 'omnivalt.courier') {
/************ container for courier *********/
                    $pack = $count;
                    $order['id'] = $order_id;
                    $labels = $order_id . '_' . $pack;
                    if ($pack == 1) {
                        $labels = $order_id . '_0';
                    }

                    //if printed
                    $shortage = 0;
                    $shortageLbl = '';
                    for ($i = 0; $i < $count; $i++) {
                        $searchFor = $order_id . '_' . $i;
                        if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $searchFor . '.pdf') && $generate) {
                            unlink(DIR_DOWNLOAD . 'omnivalt_' . $searchFor . '.pdf');
                        }

                        if (!file_exists(DIR_DOWNLOAD . 'omnivalt_' . $searchFor . '.pdf') || $generate) {
                            $shortage += 1;
                            if ($shortageLbl == '') {
                                $shortageLbl = $i;
                            }

                        }
                    }
                    if ($shortage > 0 && is_integer($shortageLbl)) { //var_dump($shortageLbl);exit();
                        $status = $this->get_tracking_number($order, $weight, $shortage, 'courier');
                        if ($status['status']) {
                            foreach ($status['barcodes'] as $barcode) {
                                $labelPrint = $order_id . '_' . $shortageLbl;
                                $this->getShipmentLabels($barcode, $labelPrint);
                                if (isset($barcode) and $barcode == true) {

                                    $this->updateOrderStatus($order_id, $status_id, $barcode);
                                    $this->setOmnivaOrder($order_id, $barcode);
                                }
                            }
                        } else {
                            $errors[] = $order_id . ' - ' . $status['msg'];
                            continue;
                        }
                    }
                    for ($ik = 0; $ik < $count; $ik++) {
                        $labels = $order_id . '_' . $ik;
                        $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                        if (!file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            continue;
                        }

                        $pagecount = $pdf->setSourceFile($label_url);
                        $newPG = array(0, 4, 8, 12, 16, 20, 24, 28, 32);
                        if ($this->labelsMix >= 4) {
                            $pdf->AddPage();
                            $page = 1;
                            $templateId = $pdf->importPage($page);
                            $this->labelsMix = 0;
                        }
                        $tplidx = $pdf->ImportPage(1);
                        if ($this->labelsMix == 0) {
                            $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, true);
                        } else if ($this->labelsMix == 1) {
                            $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, true);
                        } else if ($this->labelsMix == 2) {
                            $pdf->useTemplate($tplidx, 5, 140, 94.5, 108, true);
                        } else if ($this->labelsMix == 3) {

                            $pdf->useTemplate($tplidx, 110, 140, 94.5, 108, true);

                        } else {echo 'Problems with labels count, please, select one order!!!';}
                        $pages++;

                        $this->labelsMix++;
                    }
                } else {
/**************container for parcel terminals ************* */
                    for ($count = 0; $count < intval($order['labelsCount']); $count++) {

                        $pack = $count;
                        $labels = $order_id . '_' . $pack;
                        if ($pack == 1) {
                            $labels = $order_id;
                        }

                        $track_numer = true;
                        if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            $track_numer = false;
                        }

                        if ($track_numer != false || $generate) {
                            $status = $this->get_tracking_number($order, $weight);
                            if ($status['status']) {
                                $track_numer = $status['barcodes'][0];
                                if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                                    unlink(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf');
                                }
                            } else {
                                $errors[] = $order_id . ' - ' . $status['msg'];
                                continue;
                            }
                        }
                        $label_url = '';
                        if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                        }
                        if ($label_url == '' || $generate) {
                            $label_status = $this->getShipmentLabels($track_numer, $labels);
                            if ($label_status['status']) {
                                if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                                    $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                                }
                            } else {
                                $errors[] = $order_id . ' - ' . $label_status['msg'];
                            }
                            if ($label_url == '') {
                                continue;
                            }

                        }
                        $pagecount = $pdf->setSourceFile($label_url);
                        $newPG = array(0, 4, 8, 12, 16, 20, 24, 28, 32);
                        if ($this->labelsMix >= 4) {
                            $pdf->AddPage();
                            $page = 1;
                            $templateId = $pdf->importPage($page);
                            $this->labelsMix = 0;
                        }
                        $tplidx = $pdf->ImportPage(1);

                        if ($this->labelsMix == 0) {
                            $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, true);
                        } else if ($this->labelsMix == 1) {
                            $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, true);
                        } else if ($this->labelsMix == 2) {
                            $pdf->useTemplate($tplidx, 5, 140, 94.5, 108, true);
                        } else if ($this->labelsMix == 3) {

                            $pdf->useTemplate($tplidx, 110, 140, 94.5, 108, true);

                        } else {echo 'Problems with labels count, please, select one order!!!';}
                        $pages++;
                        $this->labelsMix++;
                        if ($track_numer) {
                            $this->updateOrderStatus($order_id, $status_id, $track_numer);
                            $this->setOmnivaOrder($order_id, $track_numer);

                        }

                    }
/****************************** */
                }
            }
            if ($pages) {
                //ob_end_flush();
                //ob_flush();
                //flush();
                $pdf->Output('Omnivalt_labels.pdf');
            } else {
                echo implode('<br/>', $errors);
            }
        } else {
            echo "No orders selected";
            return 0;
        }
    }
    public function editLabel()
    {
        $labelsCount = $this->request->post['labelsCount'];
        $omnivaWeight = $this->request->post['omnivaWeight'];
        $cod_available = $this->request->post['cod_available'];
        $cod_value = $this->request->post['cod_value'];
        $delivery_method = $this->request->post['delivery_method'];
        $order_id = $this->request->post['order_id'];

        if ($labelsCount >= 1 && $omnivaWeight > 0 && $delivery_method && $order_id) {
            //echo "$labelsCount  $omnivaWeight  $delivery_method  $order_id  $cod_available  $cod_value";
            $delivery_method = explode('|', $delivery_method);
            $delivery_methodName = $delivery_method[1];
            $delivery_method = $delivery_method[0];
            if ($cod_available == 1 && $cod_value > 0) {
                $sql = "UPDATE " . DB_PREFIX . "order SET labelsCount = $labelsCount ,
              omnivaWeight = $omnivaWeight ,
              shipping_code = '" . $delivery_method . "' ,
              shipping_method = '" . $delivery_methodName . "',
              cod_amount = $cod_value
              WHERE order_id= $order_id;";

                $sql2 = "UPDATE " . DB_PREFIX . "order_total SET title = '" . $delivery_methodName . "' WHERE order_id= $order_id AND code = 'shipping';";
                $this->db->query($sql);
                $this->db->query($sql2);
            } else {
                $sql = "UPDATE " . DB_PREFIX . "order SET labelsCount = $labelsCount ,
        omnivaWeight = $omnivaWeight ,
        shipping_code = '" . $delivery_method . "' ,
        shipping_method = '" . $delivery_methodName . "',
        cod_amount = 888888
        WHERE order_id= $order_id;";

                $sql2 = "UPDATE " . DB_PREFIX . "order_total SET title = '" . $delivery_methodName . "' WHERE order_id= $order_id AND code = 'shipping';";
                $this->db->query($sql);
                $this->db->query($sql2);

            }
            if (isset($this->request->post['generateLabel'])) {
                $this->labels2(true);
            } else {
                $this->response->redirect($this->url->link('sale/order/info', 'token=' . $this->session->data['token'] . '&order_id=' . $order_id, true));
            }

        }

    }
}
