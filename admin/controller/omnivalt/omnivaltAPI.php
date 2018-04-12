<?php
/*
 * Controller for interacting between shop and omniva
 * Share data concerned to parcels, calling to get tracking, labels
 * and carrier pick up invition.
 * @OmnivaltApi
 * @ProxyController
 */
class ControllerOmnivaltOmnivaltAPI extends Controller
{
    public function index(){}
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
    public function get_tracking_number($order, $weight = 1, $packs = 1, $sendType = 'parcel')
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

    public function getShipmentLabels($barcodes, $order_id = null)
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
}
