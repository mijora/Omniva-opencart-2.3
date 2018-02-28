<?php
/**
 * Omnivalt extension general controller
 * for settings enable/disable/install module
 * @version 1.1.0 Email/OOP
 * @author mijora.lt
 */
class ControllerExtensionShippingOmnivalt extends Controller
{
    private $error = array();

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

    public function index()
    {
        $this->load->language('extension/shipping/omnivalt');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('omnivalt', $this->request->post);
            if ($this->request->post['omnivalt_enable_templates'] != true) {
                $this->model_setting_setting->editSetting('omnivalt_enable_templates', 0);
            }

            $this->session->data['success'] = $this->language->get('text_success');
            if (!empty($this->request->post['download'])) {
                require_once DIR_CATALOG . 'controller/extension/module/omnivalt.php';
                $updateClass = new ControllerExtensionModuleOmnivalt($this->registry);
                var_dump($updateClass->index());
            } else {
                $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=shipping', true));
            }
        }

        foreach (array('cron_url', 'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_none', 'text_parcel_terminal', 'text_courier', 'text_sorting_center', 'entry_url', 'entry_user', 'entry_password', 'entry_service', 'entry_pickup_type', 'entry_company', 'entry_bankaccount', 'entry_pickupstart', 'entry_pickupfinish', 'entry_cod', 'entry_status', 'entry_sort_order', 'entry_parcel_terminal_price', 'entry_courier_price', 'entry_terminals', 'button_save', 'button_cancel', 'button_download', 'entry_sender_name', 'entry_sender_address', 'entry_sender_city', 'entry_sender_postcode', 'entry_sender_phone', 'entry_sender_country_code') as $key) {
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
            'courier_price', 'courier_pricelv', 'courier_priceee',
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
        $data['omnivalt_terminals'] = $this->model_setting_setting->getSetting('omnivalt_terminals');

        if (isset($this->request->post['omnivalt_email_template'])) {
            $data['omnivalt_email_template'] = $this->request->post['omnivalt_email_template'];
        } else {
            $data['omnivalt_email_template'] = $this->config->get('omnivalt_email_template');
        }
        if (isset($this->request->post['omnivalt_enable_templates'])) {
            $data['omnivalt_enable_templates'] = $this->request->post['omnivalt_enable_templates'];
        } else {
            $data['omnivalt_enable_templates'] = $this->config->get('omnivalt_enable_templates');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

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

        foreach (array('sender_name', 'sender_address', 'sender_phone', 'sender_postcode', 'sender_city', 'sender_country_code', 'sender_phone', 'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee', 'courier_price', 'courier_pricelv', 'courier_priceee') as $key) {
            if (!$this->request->post['omnivalt_' . $key]) {
                $this->error[$key] = $this->language->get('error_required');
            }
        }
        return !$this->error;
    }
}
