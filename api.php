<?

class IikoApi
{
    private static $login = '----';
    private static $password = 'PI1yFaKFCGvvJKi';
    private static $loginAPI = 'demoDelivery';
    private static $url = 'https://iiko.biz:9900';
    private static $ibID = 1;
    private static $ibSkuID = 2;

    public static function curl_get($url, array $get = NULL, array $options = array(), $jsonData = false)
    {


        $defaults = array(
            CURLOPT_URL => $url . (strpos($url, "?") === FALSE ? "?" : "") . http_build_query($get),

            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_DNS_USE_GLOBAL_CACHE => false,
            CURLOPT_SSL_VERIFYHOST => 0, //unsafe, but the fastest solution for the error " SSL certificate problem, verify that the CA cert is OK"
            CURLOPT_SSL_VERIFYPEER => 0, //unsafe, but the fastest solution for the error " SSL certificate problem, verify that the CA cert is OK"
        );
        $ch = curl_init();
        if (!empty($jsonData)):
            $jsonDataEncoded = json_encode($jsonData);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        endif;
        curl_setopt_array($ch, ($options + $defaults));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);


        if (!$result = curl_exec($ch)) {
            trigger_error(curl_error($ch));
        }

        curl_close($ch);
        return $result;
    }

    private static function GetAccesKey()
    {

        $url = "https://iiko.biz:9900/api/0/auth/access_token";
        $arrAcces = array(
            'user_id' => self::$loginAPI,
            'user_secret' => self::$password,
        );

        //Запрос на токен который мне нужно использовать в следующем запросе
        $access_token = self::curl_get($url, $arrAcces);

        $access_token = trim($access_token, '"');

        //Запрос на данные организации
        $getOrganizationInfo = self::curl_get("https://iiko.biz:9900/api/0//organization/list", ["access_token" => $access_token]);
        $getOrganizationInfo = json_decode($getOrganizationInfo);

        print_r2($getOrganizationInfo);
        //Получить id организации
        $getId = $getOrganizationInfo[0]->id;

        return array('ID' => $getId, 'TOKEN' => $access_token, 'ORGANIZATION' => $getId);

    }

    public static function GetCatalog()
    {
        $arAcces = self::GetAccesKey();
        //Получить меню
        $getMenu = self::curl_get("https://iiko.biz:9900/api/0/nomenclature/" . $arAcces['ID'] . "?access_token=" . $arAcces['TOKEN']);
        return json_decode($getMenu, true);
    }

    public static function UpdateCatalog()
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        \Bitrix\Main\Loader::includeModule('sale');
        //https://docs.google.com/document/d/1pRQNIn46GH1LVqzBUY5TdIIUuSCOl-A_xeCBbogd2bE/edit#
        $arResult['SECTION'] = self::GetSection();
        $arResult['ELEMENT'] = self::GetElement();
        $arResult['SKU'] = self::GetSKU();

        $arCatalog = self::GetCatalog();

        /*foreach ($arCatalog['productCategories'] as $item)
        {
            $arResult['SECTION'][$item['id']] = self::UpdateSection($item, $arResult['SECTION'][$item['id']]['ID']);
        }

        //определяем раздел для groups
        foreach ($arCatalog['products'] as $key => $item)
        {
            $arParentGroup[$item['parentGroup']] = $item['productCategoryId'];
        }*/
        $isPizza = false;
        foreach ($arCatalog['groups'] as $item) {

            if ($item['name'] == 'Пицца' or $item['id'] == '3716e40c-087f-4560-93c3-1d92487fa197') {
                //print_r2(array('add section', $item['name']));
                $arResult['SECTION'][$item['id']] = self::UpdateSection($item, $arResult['SECTION'][$item['id']]);
                $isPizza = true;
                continue;
            } elseif ($item['parentGroup'] == false) {
                $isPizza = false;
            }

            if ($isPizza && $item['parentGroup'] != false) {
                $parentSection = $arResult['SECTION'][$item['parentGroup']];
                $item['section'] = $parentSection;
                //print_r2($item);
                $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]);
                //print_r2(array('add element', $item['name']));
            } else {
                $parentSection = $arResult['SECTION'][$item['parentGroup']];
                $arResult['SECTION'][$item['id']] = self::UpdateSection($item, $arResult['SECTION'][$item['id']], $parentSection);
                //print_r2(array('add section', $item['name']));
            }
            //print_r2($arParentGroup[$item['id']]);
            //$ar['section'] = $arResult['SECTION'][$item['id']];
            // $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]['ID']);
        }

        foreach ($arCatalog['products'] as $item) {

            if ($arResult['ELEMENT'][$item['parentGroup']])//основной элемент создан в пицце//добавляем тольклько sku
            {

                $item['CML2_LINK'] = $arResult['ELEMENT'][$item['parentGroup']];
                //print_r2(array('add sku', $item['name'], $item['CML2_LINK']));
                $arResult['SKU'][$item['id']] = self::UpdateSKU($item, $arResult['SKU'][$item['id']]);
            } else {
                $parentSection = $arResult['SECTION'][$item['parentGroup']];
                $item['section'] = $parentSection;

                $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]);
                $item['CML2_LINK'] = $arResult['ELEMENT'][$item['id']];
                $arResult['SKU'][$item['id']] = self::UpdateSKU($item, $arResult['SKU'][$item['id']]);

            }
            //$ar['section'] = $arResult['SECTION'][$item['id']];
            // $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]['ID']);
        }
    }

    public static function GetSection()
    {
        $arResult = array();
        $ob = CIBlockSection::GetList(array(), array('IBLOCK_ID' => self::$ibID));
        while ($ar = $ob->GetNext()) {
            $arResult[$ar['XML_ID']] = $ar['ID'];
        }
        return $arResult;
    }

    public static function GetElement()
    {
        $arResult = array();
        $ob = CIBlockElement::GetList(array(), array('IBLOCK_ID' => self::$ibID));
        while ($ar = $ob->GetNext()) {
            $arResult[$ar['XML_ID']] = $ar['ID'];
        }
        return $arResult;
    }

    public static function GetSKU()
    {
        $arResult = array();
        $ob = CIBlockElement::GetList(
            array(),
            array('IBLOCK_ID' => self::$ibSkuID),
            false,
            false,
            array('ID', 'NAME', 'XML_ID', 'PROPERTY_CML2_LINK', 'IBLOCK_ID')
        );
        while ($ar = $ob->GetNext()) {
            $arResult[$ar['XML_ID']] = $ar['ID'];
        }
        return $arResult;
    }

    public static function UpdateSection($ar, $ID, $parent = 0)
    {
        $bs = new CIBlockSection;
        $ar['name'] = trim(str_replace('модиф', '', $ar['name']));
        $arTransParams = array(
            "max_len" => 100,
            "change_case" => 'L', // 'L' - toLower, 'U' - toUpper, false - do not change
            "replace_space" => '-',
            "replace_other" => '-',
            "delete_repeat_replace" => true
        );

        $transName = CUtil::translit($ar["name"], "ru", $arTransParams);
        $arFields = Array(
            "CODE" => $transName,
            "ACTIVE" => "Y",
            "IBLOCK_ID" => self::$ibID,
            "NAME" => $ar['name'],
            "SORT" => intval($ar['order']),
            "XML_ID" => $ar['id'],
        );
        if ($parent > 0) {
            $arFields["IBLOCK_SECTION_ID"] = $parent;
        }

        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $res = $bs->Update($ID, $arFields);
        } else {
            $ID = $bs->Add($arFields);
        }
        return $ID;
    }

    public static function UpdateElement($ar, $ID)
    {
        $el = new CIBlockElement;
        $ar['name'] = trim(str_replace('модиф', '', $ar['name']));
        $arFields = Array(
            "ACTIVE" => "Y",
            "IBLOCK_ID" => self::$ibID,
            "NAME" => $ar['name'],
            "SORT" => $ar['order'],
            "XML_ID" => $ar['id'],
            "IBLOCK_SECTION_ID" => $ar['section'],
            "PREVIEW_TEXT" => $ar['description'],
        );
        if (!empty($ar['images'][0]['imageUrl']))
            $arFields['DETAIL_PICTURE'] = CFile::MakeFileArray($ar['images'][0]['imageUrl']);

        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $el->Update($ID, $arFields);
        } else {
            $ID = $el->Add($arFields);
        }
        return $ID;
    }

    public static function UpdateSKU($ar, $ID)
    {
        $ar['name'] = trim(str_replace('модиф', '', $ar['name']));
        $el = new CIBlockElement;
        $PROP = array();
        $PROP['CML2_LINK'] = $ar['CML2_LINK'];
        if (strpos($ar['name'], 25)) {
            $PROP['SIZE'] = 1;
        } elseif (strpos($ar['name'], 30)) {
            $PROP['SIZE'] = 2;
        } elseif (strpos($ar['name'], 35)) {
            $PROP['SIZE'] = 3;
        }

        $arFields = Array(
            "ACTIVE" => "Y",
            "IBLOCK_ID" => self::$ibSkuID,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => $ar['name'],
            "SORT" => $ar['order'],
            "XML_ID" => $ar['id'],
            "CODE" => $ar['code'],
            "PREVIEW_TEXT" => $ar['description'],
        );
        if (!empty($ar['images'][0]['imageUrl']))
            $arFields['DETAIL_PICTURE'] = CFile::MakeFileArray($ar['images'][0]['imageUrl']);

        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $el->Update($ID, $arFields);
            self::UpdatePrice($ID, $ar['price']);
        } else {
            $ID = $el->Add($arFields);
            self::UpdatePrice($ID, $ar['price']);
        }
        return $ID;
    }

    public static function UpdatePrice($BXID, $price)
    {
        $db_res = CPrice::GetListEx(array(), array("PRODUCT_ID" => $BXID, "CATALOG_GROUP_ID" => 1));
        if ($ar_res = $db_res->Fetch()) {
            //print_r2($ar_res);
            $arFields = Array(
                "PRODUCT_ID" => $BXID,
                "CATALOG_GROUP_ID" => 1,
                "PRICE" => $price,
                "CURRENCY" => "KZT"
            );
            CPrice::Update($ar_res['ID'], $arFields);
            //CCatalogProduct::Update($BXID, array('QUANTITY' => 1));
        } else {
            $arFields = Array(
                "PRODUCT_ID" => $BXID,
                "CATALOG_GROUP_ID" => 1,
                "PRICE" => $price,
                "CURRENCY" => "KZT"
            );
            //print_r2($arFields);
            CPrice::Add($arFields);

            CCatalogProduct::Add(
                array(
                    "ID" => $BXID,
                    "QUANTITY" => 1
                )
            );
        }
    }


    public static function GetDiscount()
    {
        $arAcces = self::GetAccesKey();
        $getMenu = self::curl_get("https://iiko.biz:9900/api/0/deliverySettings/deliveryDiscounts?access_token=" . $arAcces['TOKEN'] . "&organization=" . $arAcces['ORGANIZATION']);
        return json_decode($getMenu, true);
    }

    public static function GetPaymentTypes()
    {
        $arAcces = self::GetAccesKey();
        $getMenu = self::curl_get("https://iiko.biz:9900/api/0/rmsSettings/getPaymentTypes?access_token=" . $arAcces['TOKEN'] . "&organization=" . $arAcces['ORGANIZATION']);
        return json_decode($getMenu, true);
    }

    public static function CreateOrder($orderId)
    {
        $arAcces = self::GetAccesKey();

        \Bitrix\Main\Loader::includeModule('sale');
        \Bitrix\Main\Loader::includeModule('iblock');
        $order = Bitrix\Sale\Order::load($orderId);

        $arResult = array( //массив для примера заполнения
            "organization" => $arAcces['ORGANIZATION'],
            "customer" => array(
                //"id" => "c588cc06-d168-e8f7-5725-f80821e55afc",
                "name" => "Нет на сайте",
                "phone" => ""
            ),
            "order" => array(
                //"id" => "bc7382f1-fe93-4f34-639e-4e9e65f76772",
                //"date" => "2020-05-24 10:29:54",
                "phone" => "",
                "isSelfService" => "false",
                "items" => array(/*array("id" => "a44dcab4-89ef-469a-8299-6f71e8838e0a",
                        "name" => "Солянка",
                        "amount" => 5,
                        "code" => "0001",
                        "sum" => 400
                    ),
                    array("id" => "a44dcab4-89ef-469a-8299-6f71e8838e0a",
                        "name" => "Солянка",
                        "amount" => 5,
                        "code" => "0001",
                        "sum" => 400
                    ),*/

                ),

                "address" => array(
                    "city" => "",
                    "street" => "",
                    "home" => "0",
                    "housing" => "0",
                    "apartment" => "0",
                    "comment" => ""
                ),

                /*"paymentItems" => array(
                    "sum" => $order->getPrice(),
                    "paymentType" => array(
                        "id" => "09322f46-578a-d210-add7-eec222a08871",
                        "code" => "CASH",
                        "name" => "Наличные",
                        "comment" => null,
                        "combinable" => true,
                        "externalRevision" => 1722109,
                        "applicableMarketingCampaigns" => null,
                        "deleted" => false
                    ),
                    "additionalData" => null,
                    "isProcessedExternally" => false, //Признак ПРОВЕДЕННОГО платежа
                    "isPreliminary" => false, //Признак предоплаты
                    "isExternal" => true //Всегда true для оплаты с сайта
                )*/
            )
        );

        // $arPay = self::GetPaymentTypes();
        //$arResult['order']['paymentItems']['paymentType'] = $arPay['paymentTypes'][0];


        $propertyCollection = $order->getPropertyCollection();
        $ar = $propertyCollection->getArray();
        foreach ($ar['properties'] as $propertyValue) {
            $arProp[$propertyValue['CODE']] = $propertyValue['VALUE'][0];
        }
        //print_r2($arProp);
        $paymentIds = $order->getPaymentSystemId(); // массив id способов оплат
        $deliveryIds = $order->getDeliverySystemId(); // массив id способов доставки

        if (empty($arProp['HOUSE']))
            $arProp['HOUSE'] = 0;


        if ($deliveryIds[0] == 2) //Самовывоз
        {
            $arResult['order']['address']['city'] = $arProp['CITY'];
            $arResult['order']['address']['street'] = $arProp['STREET'];
            $arResult['order']['address']['home'] = $arProp['HOUSE'];
            $arResult['customer']['phone'] = $arProp['PHONE'];
            $arResult['order']['phone'] = $arProp['PHONE'];

            $arResult['order']['comment'] =  '';
            if (!empty($arProp['TIME'])) {
                $arResult['order']['comment'] .= 'Время самовывоза ' . $arProp['TIME'];
            }
        } else //доставка
        {
            $arResult['order']['isSelfService'] = true;
            $arResult['order']['address']['city'] = $arProp['CITY'];
            $arResult['order']['address']['street'] = $arProp['STREET'];
            $arResult['order']['address']['home'] = $arProp['HOUSE'];
            $arResult['order']['address']['apartment'] = $arProp['FLAT'];
            $arResult['order']['comment'] .= '
            Подъезд ' . $arProp['PORCH'] . '
            Код двери ' . $arProp['DOOR_CODE'] . '
            Этаж ' . $arProp['FLOOR'] . '
            Время доставки ' . $arProp['TIME_DELIVERY'] . '
            ';

        }


        $basket = $order->getBasket();
        $basketItems = $basket->getBasketItems(); // массив объектов Sale\BasketItem
        foreach ($basket as $basketItem) {
            //echo $basketItem->getField('NAME') . ' - ' . $basketItem->getQuantity() . '<br />';
            $arEl = CIBlockElement::GetByID($basketItem->getProductId())->Fetch();
            $arResult['order']['items'][] = array(
                "id" => $arEl['XML_ID'],
                "name" => $arEl['NAME'],
                "amount" => $basketItem->getQuantity(),
                "code" => $arEl['CODE'],
                "sum" => $basketItem->getFinalPrice()
            );

        }

        //print_r2($arResult);

        $result = self::curl_get("https://iiko.biz:9900/api/0/orders/add?access_token=" . $arAcces['TOKEN'] . "&organization=" . $arAcces['ORGANIZATION'], NULL,array(),  $arResult);
        //print_r2(json_decode($result, true));

    }
}

?>
