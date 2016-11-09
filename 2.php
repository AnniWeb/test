<?
set_time_limit(0); 
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

define('SEND_EVENT', 'SEND_WISHLIST');//почтовое событие,отвечающее за рассылку

function sendWishlist () {
	CModule::includeModule("sale");

	$user_products = array();
	$user_orders = array();
	$user_names = array();
	global $DB;
	$date = date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time()-60*60*24*30);

	// Все отложенные товары, которые были добавлены в корзину в течении 30 дней
	$db = CSaleBasket::GetList(
		array(),
		array(
			"LID" => SITE_ID,
			"ORDER_ID" => "NULL",
			"!USER_ID" => false,
			'MODULE' => 'catalog',
			">=DATE_INSERT" => $date,
			'DELAY' => 'Y'
		),
		false,
		false,
		array("PRODUCT_ID", "QUANTITY", "PRICE", 'USER_ID', 'NAME', 'DETAIL_PAGE_URL')
		//array()
	);
	while ($arItems = $db->Fetch()) {
		$user_products[$arItems['USER_ID']][$arItems['PRODUCT_ID']] = $arItems;
	}

	// Список заказов за последние 30 дней
	$db = CSaleOrder::GetList(
		array(), 
		array(
			"USER_ID" => array_keys($user_products),
			"LID" => SITE_ID,
			">=DATE_INSERT" => $date,
		), 
		false,
		false,
		array('ID', 'USER_ID')
	);
	while ($arOrder = $db->Fetch()) {
		$user_orders[] = $arOrder['ID'];
	}

	// Получение уже заказавыемых товаров, данными пользователями
	$db = CSaleBasket::GetList(
		array(),
		array(
			"LID" => SITE_ID,
			"ORDER_ID" => $user_orders,
			'MODULE' => 'catalog',
		),
		false,
		false,
		array("PRODUCT_ID", 'USER_ID')
		//array()
	);
	while ($arItems = $db->Fetch()) {
		if (isset($user_products[$arItems['USER_ID']][$arItems['PRODUCT_ID']]))
			unset($user_products[$arItems['USER_ID']][$arItems['PRODUCT_ID']]);
	}

	//Получение имени и почты пользователей
	$order = array('sort' => 'asc');
	$tmp = 'sort';
	$db = CUser::GetList(
		$order, 
		$tmp,
		array('ID' => array_keys($user_products)),
		array('LAST_NAME', 'NAME', 'ID', 'EMAIL')
	);
	while ($arUser = $db->Fetch()) {
		$user_names[$arUser['ID']] = array(
			'NAME' => "{$arUser['NAME']} {$arUser['LAST_NAME']}",
			'EMAIL' => $arUser['EMAIL']
		);
	}

	// Рассылка
	foreach ($user_products as $user_id => $products) {
		if (empty($products))
			continue;
		$products_str = '';
		foreach($products as $product){
			$products_str .= "<a href='{$product['DETAIL_PAGE_URL']}'>{$product['NAME']}</a><br />";
		}
		
		$arFields = array(
			'EMAIL' => $user_names[$user_id]['EMAIL'],
			'NAME' => $user_names[$user_id]['NAME'],
			'PRODUCTS' => $products_str,
		);
		CEvent::Send('SEND_EVENT', SITE_ID, $arFields);
	}
	return 'sendWishlist();';
}

send_wishlist();