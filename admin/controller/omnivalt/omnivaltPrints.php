<?php
/*
 * Controller for printing omnivalt docs
 * @bean OmnivaltApi
 * @returns generated pdf Labels, Manifests
 */
class ControllerOmnivaltOmnivaltPrints extends Controller
{
    protected $labelsMix = 4;
    private $omnivaltAPI;

    public function index()
    {
        die('Here is nothing to show');
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

    private function updateOrderStatus($order_id, $order_status_id, $comment)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = '" . (int) $order_status_id . "', `date_modified` = NOW() WHERE `order_id` = '" . (int) $order_id . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . (int) $order_id . "', `order_status_id` = '" . (int) $order_status_id . "', `notify` = '0', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");
    }

    private function setOmnivaOrder($id_order = '', $tracking = '', $label = '')
    {
        $this->sendNotification($id_order);
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
            if ($this->config->get('omnivalt_enable_templates') == 'on') {
                $this->sendNotification();
            }

        };
    }

    private function sendNotification($id_order = '', $tracking = '154233775CE')
    {

        try {
            $order = $this->model_sale_order->getOrder($id_order);

            $mail = new Mail();
            $mail->protocol = $this->config->get('config_mail_protocol');
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
            $subject = $this->config->get('config_name') . ' uzsakymo pasikeitimai' . $tracking;
            $message = $this->config->get('omnivalt_email_template');
            $mail->setTo($order['email']);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
            $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
            $mail->send();

        } catch (Exception $e) {
            $this->log->write('Mail wasn\'t to this .nr. order' . $e);
        }
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
            if ($query->row && $query->row['comment'] != '') {
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
        if (isset($_POST['manifest']) &&
            intval($_POST['manifest']) == $manifest &&
            $_POST['print'] == 'manifest' &&
            !isset($this->request->post['new'])) {

            $manifest++;
            $this->model_setting_setting->editSetting('omniva', array('omniva_manifest' => $manifest));
        }

        switch ($_POST['print']) {
            case 'manifest':
                if (isset($this->request->post['new'])) {
                    print 'Please generate labels first!!!';
                    //exit();
                    break;
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
                //if(is_array($track_numer))
                //$track_numer = array_reverse($track_numer);
                /*print'<pre>';
                var_dump($track_numer);
                print'</pre>';*/
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
                    //$address = new Address($order->id_address_delivery);
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
        </table><br/><br/>';

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
    public function labels2($variable = false)
    {
        $this->labels($variable);
    }

    public function labels($generate = false)
    {
        if (isset($_GET['order_id'])) {
            $_POST['selected'] = array($_GET['order_id']);
        }

        require_once DIR_APPLICATION . 'controller/omnivalt/omnivaltAPI.php';
        $this->omnivaltAPI = new ControllerOmnivaltOmnivaltAPI($this->registry);

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
                        //$status = $this->get_tracking_number($order, $weight);
                        $status = $this->omnivaltAPI->get_tracking_number($order, $weight);

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
                        //$track_numer = 'CE483401345EE';
                        $label_url = '';
                        if (file_exists(DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf')) {
                            $label_url = DIR_DOWNLOAD . 'omnivalt_' . $labels . '.pdf';
                        }

                        if ($label_url == '' || $generate) {
                            $label_status = $this->omnivaltAPI->getShipmentLabels($track_numer, $labels);
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
        if (!isset($this->request->post['labelsCount'])) {
            die('Please notify order!!!');
        }

        $labelsCount = $this->request->post['labelsCount'];
        $omnivaWeight = $this->request->post['omnivaWeight'];
        $cod_available = $this->request->post['cod_available'];
        $cod_value = $this->request->post['cod_value'];
        $delivery_method = $this->request->post['delivery_method'];
        $order_id = $this->request->post['order_id'];

        if ($labelsCount >= 1 && $omnivaWeight > 0
            && $delivery_method && $order_id) {

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
                $this->labels(true);
            } else {
                $this->response->redirect($this->url->link('sale/order/info', 'token=' . $this->session->data['token'] . '&order_id=' . $order_id, true));
            }
        }
    }
}
