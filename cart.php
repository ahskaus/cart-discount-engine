<?

	$discount = json_decode('{"STORE_WIDE":1,"CATEGORY_WIDE":2,"BOGO":4,"GROUP_WISE":8,"FINAL":16,"ALL":255}', true);
	$couponConsts = json_decode('{"STORE_PERC":1,"STORE_DOLLAR":2,"SORT_ASCX":1,"SORT_DESCX":2,"SALE":1,"DISCOUNT":2,"COUPON":3}', true);
	$rawCoupon = json_decode('{"PR_SW_SL":11,"PR_CW_SL":21,"PR_SW":12,"PR_CW":22,"PR_BG":42,"PS_SW":13,"PS_CW":23,"PS_BG":43}', true);
	$rawSequence = json_decode('[11,21,22,23,42,43,12,13]', true);
	$couponExclude = json_decode('{"11":[11,21,22,23],"12":[],"13":[],"21":[],"22":[],"23":[],"42":[],"43":[]}', true);
		
	$cart = json_decode('{"categories":{"collections":{},"tags":{},"metas":{},"type":{},"vendor":{}},"products":{},"variants":{},"coupons":{"pre":[],"post":[]},"cost":0,"weight":0,"quantity":0,"corrections":{},"canUse":255}', true);
    
    $categories = array("collections", "tags", "metas", "vendor", "type"); 


	function product($quantity, $collections, $tags, $metas, $vendor, $type){
		global $discount;
		$ret = array();
	    $ret['collections'] = $collections;
	    $ret['tags'] = $tags;
	    $ret['metas'] = $metas;
	    $ret['vendor'] = $vendor;
	    $ret['type'] = $type;
	    $ret['quantity'] = $quantity+0;
	    $ret['canUse'] = $discount['ALL'];
	    $ret['variants'] = array();
	    $ret['totalCost'] = null;

	    return $ret;
	}

	function variant($quantity, $cost, $weight){
		global $discount;
		$ret = array();
	    $ret['quantity'] = $quantity+0;
	    $ret['remainingQuantity'] = $quantity+0;
	    $ret['cost'] = $cost;
	    $ret['weight'] = $weight;
	    $ret['canUse'] = $discount['ALL'];
	    $ret['totalCost'] = null;

	    return $ret;
	}

	function varPrice($id, $price, &$ref){
		$ret = array();
	    $ret['id'] = $id;
	    $ret['price'] = $price;
	    $ret['ref'] = &$ref;

	    return $ret;
	}

	function insertVariant($product, $variant, $quantity){
		global $cart;
		$prod = &$cart['products'][$product['id']];
		if($prod){
			$vari = &$prod['variants'][$variant['shadow_id']];
			if($vari){
				$vari['quantity'] += $quantity+0;
			}else{
				$product['variants'][$variant['shadow_id']] = variant($quantity, $variant['price'], $variant['weight']);
			}
			$prod['quantity'] += $quantity+0;
		}else{
			$prod = product($quantity, $product['collections'], $product['tags'], $product['metas'],
					$product['vendor'], $product['type']);
			$prod['variants'][$variant['shadow_id']] = variant($quantity, $variant['price'], $variant['weight']);
			addProductToCategories($prod, $product['id']);
			$cart['products'][$product['id']] = $prod;
			$cart['variants'][$variant['shadow_id']] = $product['id'];
		}
	}

	function addProductToCategories($product, $id){
		global $categories;
		for ($i=0;$i<count($categories);$i++)
			addProductToCategory($categories[$i], $product[$categories[$i]], $id);
    }

    function addProductToCategory($name, $list, $id){
    	global $cart;
    	if(is_string($list)){
    		if(!$cart['categories'][$name][$list])
    			$cart['categories'][$name][$list] = array();
			array_push($cart['categories'][$name][$list], $id);

    	}else{
			for ($i=0;$i<count($list);$i++){ 
				if(!$cart['categories'][$name][$list[$i]])
					$cart['categories'][$name][$list[$i]] = array();
				array_push($cart['categories'][$name][$list[$i]], $id);
    		}
    	}
   	}

    function indexOfCoupon($code, &$coupons){
		for ($i=0;$i<count($coupons);$i++)
			if($coupons[$i]['code']==$code) return $i+1;
	    return 0;
    }

	function addPost($coupon){
		global $cart;
		if(indexOfCoupon($coupon['code'],$cart['coupons']['post'])) return false;
		array_push($cart['coupons']['post'], $coupon); return true;
   	}

	function addPre($coupon){
		global $cart;
		if(indexOfCoupon($coupon['code'],$cart['coupons']['pre'])) return false;
		array_push($cart['coupons']['pre'], $coupon); return true;
   	}

    function byCategory($category, $name, &$exclude, $discountType){
		global $cart;
		$products = $cart['categories'][$category][$name]?$cart['categories'][$category][$name]:array();
      	$returnee = array(); $variants = null;
      	for ($i=0;$i<count($products);$i++){
	        if($exclude[$products[$i]]||(!($exclude[$products[$i]]=true)))continue; //check out for shashkay
	        else {
	        	$variants = $cart['products'][$products[$i]]['variants'];
	        	foreach($variants as $varId => $variant){
            		if(($variants['canUse']&$discountType)==$discountType)
	                	array_push($returnee, varPrice($varId, $variant['cost'], $variant));
	        	}
	        }
      	}
      	return $returnee;
    }

    function byCategoryVarN($category, $name, &$exclude, $discountType){
		global $cart;
		$products = $cart['categories'][$category][$name]?$cart['categories'][$category][$name]:array();
      	$returnee = array(); $variants = null;
      	for ($i=0;$i<count($products);$i++){
	        if($exclude[$products[$i]]||(!($exclude[$products[$i]]=true)))continue; //check out for shashkay
	        else {
	        	$variants = $cart['products'][$products[$i]]['variants'];
	        	foreach($variants as $varId => $variant){
	            	if(($variants['canUse']&$discountType)==$discountType){
		                for ($j=0;$j<$variant['remainingQuantity'];$j++)
	    	            	array_push($returnee, varPrice($varId, $variant['cost'], $variant));
	            	}
	        	}
	        }
      	}
      	return $returnee;
    }

    function updateProduct(&$product){
		updateProductQuantity($product);
		updateProductCost($product);
    }

    function updateProductQuantity(&$product){
		$product['quantity'] = 0;
    	foreach($product['variants'] as $varId => $variant)
			$product['quantity'] += $variant['quantity'];
    }

    function updateProductCost(&$product){
		$product['totalCost'] = 0;
    	foreach($product['variants'] as $varId => &$variant)
        	$product['totalCost'] += updateVariantCost($variant);
      	return $product['totalCost'];
    }

    function updateVariantCost(&$variant) {
    	global $cart;
    	return ($variant['totalCost'] = $variant['quantity'] * $variant['cost']);
    }

	function updateCartCost(){
		global $cart;
		$cart['cost'] = 0;
		foreach ($cart['products'] as $prodId => &$product)
			$cart['cost'] += updateProductCost($product);
		foreach ($cart['corrections'] as $couponId => $correction)
			$cart['cost'] -= $correction;
		$cart['cost'] = $cart['cost'] < 0 ? 0 : $cart['cost'];
		return $cart['cost'];
	}

    function updateCartQuantity(){
    	global $cart;
		$cart['quantity'] = 0;
		foreach ($cart['products'] as $prodId => $product)
			$cart['quantity'] += $product['quantity'];
		return $cart['quantity'];
    }

    function updateCartWeight(){
    	global $cart;
		$cart['weight'] = 0;
		foreach ($cart['products'] as $prodId => $product){
			foreach ($product['variants'] as $varId => $variant){
				$cart['weight']  += $variant['weight']*$variant['quantity'];
			}
		}
		return $cart['weight'];
    }

    function apply(){
	
		global $cart;    	
    	global $rawSequence;
    	global $discount;
    	global $couponExclude;

    	$coupons = array_values(array_merge($cart['coupons']['pre'], $cart['coupons']['post']));

		for ($j=0; $j<count($rawSequence);$j++) {
			for ($i=0;$i<count($coupons);$i++) {
					if($coupons[$i]['raw_type']!=$rawSequence[$j])continue;
					switch($coupons[$i]['type']){
					case $discount['STORE_WIDE']:
						store($cart,json_decode($coupons[$i]['params'],true),$coupons[$i]['raw_type'],$discount['STORE_WIDE']);
						break;
					case $discount['CATEGORY_WIDE']:
						category($cart,json_decode($coupons[$i]['params'],true),$discount['CATEGORY_WIDE']);
						break;
					case $discount['BOGO']:
						bogo($cart,json_decode($coupons[$i]['params'],true),$discount['BOGO']|$discount['CATEGORY_WIDE']);
						break;
					case $discount['GROUP_WISE']:
						break;
					default:
				}
				array_splice($coupons,$i,1);
				$exclude = $couponExclude[$rawSequence[$j]];
				for($k=0;$k<count($exclude);$k++){
					for($l=0;$l<count($coupons);$l++){
						if($coupons[$l]['raw_type']!=$exclude[$k])continue;
						array_splice($coupons,$i,1);
						$l--;
					}
				}
				$i=-1;
			}
		}
		return $cart;
    }

    function store(&$cart, $params, $rawType, $discountType){ //storewide
    	global $couponConsts;
    	global $rawCoupon;

    	if($params['type']==$couponConsts['STORE_PERC']){
    		if($rawType!=$rawCoupon['PR_SW_SL']){
          		if($cart['cost']>=($params['min']*100)){
	            	$cart['cost'] = round($cart['cost']*(1-($params['off']/100)));
	            }
        	}else if($cart['cost']>=($params['min']*100)){
        		foreach($cart['products'] as $prodId => &$product){
       				foreach($product['variants'] as $varId => &$variant){
              			$variant['cost'] = round($variant['cost']*(1-($params['off']/100)));
              		}
              	}
		    	updateCartCost();
            }
    	} else if($params['type']==$couponConsts['STORE_DOLLAR']){
	        if($cart['cost']>=($params['min']*100)){
	        	$cart['cost'] -= ($cart['cost']<($params['off']*100))?$cart['cost']:($params['off']*100);
	        }
    	}
    }

    function category(&$cart, $params, $discountType){ //categorywide

    	$variants = array();
		$done = array();
		foreach($params['categories'] as $catName => $category){
			for ($i=0;$i<count($category);$i++){
			    $stuff = byCategory($catName, $category[$i], $done, $discountType);
			    $variants = array_values(array_merge($variants, $stuff));
			}
		}

      	$tempCost = 0;
		for ($j=0;$j<count($variants);$j++) {
			$tempCost += $variants[$j]['ref']['totalCost'];
			if($tempCost>=($params['min']*100)) break;
		}
		if($tempCost<($params['min']*100)) return;

		for ($j=0;$j<count($variants);$j++) {
	    	if($params['type']==$couponConsts['STORE_PERC']){
				$variants[$j]['ref']['cost'] = round($variants[$j]['ref']['cost']*(1-($params['off']/100)));
	    	} else if($params['type']==$couponConsts['STORE_DOLLAR']){
				$variants[$j]['ref']['cost'] = ($variants[$j]['ref']['cost']<($params['off']*100))?0:
											   ($variants[$j]['ref']['cost']-($params['off']*100));
	    	}
		}
		updateCartCost();
    }

	function bogo(&$cart, $params, $discountType){ //and variations

		$cart['corrections'][$params['name']] = 0;
		$bo = array();$go = array();
		$used = array();
		$done = array();
		foreach($params['bo']['categories'] as $catName => $category){
			for ($i=0;$i<count($category);$i++){
			    $stuff = byCategoryVarN($catName, $category[$i], $done, $discountType);
			    $bo = array_values(array_merge($bo, $stuff));
			}
		}
		$done = array();
		foreach($params['go']['categories'] as $catName => $category){
			for ($i=0;$i<count($category);$i++){
			    $stuff = byCategoryVarN($catName, $category[$i], $done, $discountType);
			    $go = array_values(array_merge($go, $stuff));
			}
		}
		$bo = usort($bo, "sortAsc");
		$go = usort($go, "sortDesc");
	    $innerUsed = array();
	    while(count($bo)>=$params['bo']['items']&&count($go)>=$params['go']['items']){
	        $innerUsed = array();$shiftedGo = array();
	        for ($i=0;$i<$params['bo']['items'];$i++) {
		        $shiftedBo = $bo[0];
		        if(!$shiftedBo)break 2;
		        array_shift($bo);
		        deleteFirstVariant($go, $shiftedBo['id']);
		        $innerUsed[] = $shiftedBo;
	        }
	        for ($i=0;$i<$params['go']['items'];$i++) {
		        $tempGo = $go[0];
		        if(!$tempGo)break 2;
		        array_shift($go);
          		$shiftedGo[] = $tempGo;
          		$innerUsed[] = $tempGo;
        	}
	        if($params['type']==$couponConsts['STORE_PERC']){
	        	for ($i=0;$i<count($shiftedGo);$i++){
	            	$cart['corrections'][$params['name']] = 
	            		round($cart['corrections'][$params['name']]+($shiftedGo[$i]['ref']['cost']*($params['off']/100)));
	            	deleteFirstVariant($bo, $shiftedGo[$i]['id']);
	          	}
	        } else if($params['type']==$couponConsts['STORE_DOLLAR']){
	          	$tempCost = 0;
	          	for ($i=0;$i<count($shiftedGo);$i++){
	            	$tempCost += $shiftedGo[$i]['ref']['cost'];
	            	deleteFirstVariant($bo, $shiftedGo[$i]['id']);
	          	}
	          	$cart['corrections'][$params['name']] = 
	          		round($cart['corrections'][$params['name']]+($tempCost<($params['off']*100)?$tempCost:($params['off']*100)));
	        }
	        $used = array_values(array_merge($used, $innerUsed));
    	}
		for ($i=count($used)-1;$i>=0;$i--)
			$used[$i]['ref']['remainingQuantity']--;
		updateCartCost();
    }

    function deleteFirstVariant(&$variants, $id){
      for ($i=0;$i<count($variants);$i++)
        if($variants[$i]['id']==$id) return array_splice($variants,$i,1);
    }


    function sortAsc(&$left, &$right){
    	if($left['cost']<$right['cost'])return -1;else if($left['cost']==$right['cost'])return 0;return 1;
    }

    function sortDesc(&$left, &$right){
    	if($left['cost']>$right['cost'])return -1;else if($left['cost']==$right['cost'])return 0;return 1;
    }

    function insertVariants($variants, $products){
    	for ($i=0;$i<count($variants);$i++){
    			insertVariant($products[$variants[$i]['product_id']], $variants[$i], $variants[$i]['quantity']);
    		}	
		updateCartCost();
	    updateCartQuantity();
	    updateCartWeight();
    }

?>
