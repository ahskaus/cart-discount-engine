  var Discount = {
    STORE_WIDE : 1,
    CATEGORY_WIDE : 2,
    BOGO : 4,
    GROUP_WISE : 8,
    FINAL : 16,
    ALL : 0xFF

  };

  var CouponConsts = {
    STORE_PERC : 1,
    STORE_DOLLAR : 2,
    SORT_ASCX : 1,
    SORT_DESCX : 2,
    SALE : 1,
    DISCOUNT : 2,
    COUPON : 3
  };

  var RawCoupon = { //pr = pre, ps = post, sw = storewide, cw = categorywide, bg = bogo, sl = sale
    PR_SW_SL : Discount.STORE_WIDE*10+CouponConsts.SALE,
    PR_CW_SL : Discount.CATEGORY_WIDE*10+CouponConsts.SALE,
    PR_SW : Discount.STORE_WIDE*10+CouponConsts.DISCOUNT,
    PR_CW : Discount.CATEGORY_WIDE*10+CouponConsts.DISCOUNT,
    PR_BG : Discount.BOGO*10+CouponConsts.DISCOUNT,
    PS_SW : Discount.STORE_WIDE*10+CouponConsts.COUPON,
    PS_CW : Discount.CATEGORY_WIDE*10+CouponConsts.COUPON, 
    PS_BG : Discount.BOGO*10+CouponConsts.COUPON
  };

  var Raw = RawCoupon;

  var rawSequence = [Raw.PR_SW_SL, Raw.PR_CW_SL, Raw.PR_CW, Raw.PS_CW, Raw.PR_BG, Raw.PS_BG, Raw.PR_SW, Raw.PS_SW];

  var CouponExclude = {};
  CouponExclude[Raw.PR_SW_SL] = [Raw.PR_SW_SL, Raw.PR_CW_SL, Raw.PR_CW, Raw.PS_CW];
  CouponExclude[Raw.PR_CW_SL] = [];
  CouponExclude[Raw.PR_SW] = [];
  CouponExclude[Raw.PR_CW] = [];
  CouponExclude[Raw.PR_BG] = [];
  CouponExclude[Raw.PS_SW] = [];
  CouponExclude[Raw.PS_CW] = []; 
  CouponExclude[Raw.PS_BG] = [];


  var CART_SHELL = {
    categories: {
      collections: {},
      tags: {},
      metas: {},
      type: {},
      vendor: {}
    },
    products: {},
    variants: {},
    coupons: {
      pre: [],
      post: []
    },
    cost: 0,
    weight: 0,
    quantity: 0,
    corrections: {},
    applies: [],
    canUse: Discount.ALL
  };


  function Apply(description, value){
    this.description = description;
    this.value = value;
  }


  var Coupons = {
    categories : ["collections", "tags", "metas", "vendor", "type"],
    pre : [],
    loaded: false,

    store: function(cart, params, rawType, discountType){ //storewide
      var softCart = cart.getCart();
      var initPrice = softCart.cost;
      if(params.type==CouponConsts.STORE_PERC){
        if(rawType!=RawCoupon.PR_SW_SL){
          if(softCart.cost>=(params.min*100))
            softCart.cost = Math.round(softCart.cost*(1-(params.off/100)));
            cart.insertApply(rawType,params,initPrice,softCart.cost);
        } else if(softCart.cost>=(params.min*100)){
          for(var product in softCart.products)
            for(var variant in softCart.products[product].variants)
              softCart.products[product].variants[variant].cost = 
                Math.round(softCart.products[product].variants[variant].cost*(1-(params.off/100)));
          cart.insertApply(rawType,params,initPrice,cart.updateCartCost());
        }
      } else if(params.type==CouponConsts.STORE_DOLLAR){
        if(initPrice>=(params.min*100))
          softCart.cost -= (initPrice<(params.off*100))?initPrice:(params.off*100);
          cart.insertApply(rawType,params,initPrice,softCart.cost);
      }
    },

    category: function(cart, params, rawType, discountType){ //categorywide
      var initPrice = cart.getCart().cost;
      var variants = [];
      var done = {};
      for(var category in params.categories)
        for (var i = 0; i < params.categories[category].length; i++){
            var stuff = cart.byCategory(category,params.categories[category][i],done,discountType);
            variants = variants.concat(stuff);
        }
      var tempCost = 0;
      for (var j = 0; j < variants.length; j++) {
        tempCost += variants[j].ref.totalCost;
        if(tempCost>=(params.min*100)) break;
      };
      if(tempCost<(params.min*100)) return;
      for (var j = 0; j < variants.length; j++) {
        if(params.type==CouponConsts.STORE_PERC){
          variants[j].ref.cost = Math.round(variants[j].ref.cost*(1-(params.off/100)));
        } else if(params.type==CouponConsts.STORE_DOLLAR){
          variants[j].ref.cost = (variants[j].ref.cost<(params.off*100))?0:(variants[j].ref.cost-(params.off*100));
        }
      };
      cart.insertApply(rawType,params,initPrice,cart.updateCartCost());
    },

    bogo: function(cart, params, rawType, discountType){ //and variations
      var softCart = cart.getCart();
      var initPrice = softCart.cost;
      softCart.corrections[params.name] = 0;
      var bo = [];var go = [];
      var used = [];
      var done = {};
      for(var category in params.bo.categories)
        for (var i = 0; i < params.bo.categories[category].length;
          bo = bo.concat(cart.byCategoryVarN(category,params.bo.categories[category][i++],done,discountType)));
      done = {};
      for(var category in params.go.categories)
        for (var i = 0; i < params.go.categories[category].length;
          go = go.concat(cart.byCategoryVarN(category,params.go.categories[category][i++],done,discountType)));
      bo = mergeSort(bo, Coupons.sortAsc);
      go = mergeSort(go, Coupons.sortDesc);
      var innerUsed = [];var shiftedBo;var shiftedGo;
      outer:
      while(bo.length>=params.bo.items&&go.length>=params.go.items){
        innerUsed = [];shiftedGo = [];
        for (var i = 0; i < params.bo.items; i++) {
          if(!(shiftedBo = bo.shift()))break outer;
          this.deleteFirstVariant(go, shiftedBo.id);
          innerUsed.push(shiftedBo);
        }
        for (var i = 0, tempGo; i < params.go.items; i++) {
          if(!(tempGo = go.shift()))break outer;
          shiftedGo.push(tempGo);
          innerUsed.push(tempGo);
        }
        if(params.type==CouponConsts.STORE_PERC){
          for (var i = 0; i < shiftedGo.length; i++){
            softCart.corrections[params.name] = 
              Math.round(softCart.corrections[params.name]+(shiftedGo[i].ref.cost*(params.off/100)));
            this.deleteFirstVariant(bo, shiftedGo[i].id);
          }
        } else if(params.type==CouponConsts.STORE_DOLLAR){
          var tempCost = 0;
          for (var i = 0; i < shiftedGo.length; i++){
            tempCost += shiftedGo[i].ref.cost;
            this.deleteFirstVariant(bo, shiftedGo[i].id);
          }
          softCart.corrections[params.name] = 
            Math.round(softCart.corrections[params.name]+(tempCost<(params.off*100)?tempCost:(params.off*100)));
        }
        used = used.concat(innerUsed);
      }
      for (var i=used.length-1;i>=0;i--)
        used[i].ref.remainingQuantity--;
      cart.insertApply(rawType,params,initPrice,cart.updateCartCost());
    },


    bosf: function(cart, params, rawType, discountType){ //buy X, shipping free
      cart.weight = 0;
    },

    apply: function(cart){
      cart.getCart().applies = [];
      coupons = cart.getCart().coupons.pre.concat(cart.getCart().coupons.post);
      for (var j=0; j<rawSequence.length;j++) {
        for (var i=0;i<coupons.length;i++) {
          if(coupons[i].raw_type!=rawSequence[j])continue;
          switch(coupons[i].type){
            case Discount.STORE_WIDE:
              this.store(cart,JSON.parse(coupons[i].params),coupons[i].raw_type,Discount.STORE_WIDE);
              break;
            case Discount.CATEGORY_WIDE:
              this.category(cart,JSON.parse(coupons[i].params),coupons[i].raw_type,Discount.CATEGORY_WIDE);
              break;
            case Discount.BOGO:
              this.bogo(cart,JSON.parse(coupons[i].params),coupons[i].raw_type,Discount.BOGO|Discount.CATEGORY_WIDE);
              break;
            case Discount.GROUP_WISE:
              break;
            default:
          }
          coupons.splice(i,1);
          var exclude = CouponExclude[rawSequence[j]];
          for(var k=0;k<exclude.length;k++){
            for(var l=0;l<coupons.length;l++){
              if(coupons[l].raw_type!=exclude[k])continue;
              coupons.splice(l,1);
              l--;
            }
          }
          i=-1;
        }
      }
      return cart;
    },

    deleteFirstVariant: function(variants, id){
      for (var i = 0; i < variants.length; i++)
        if(variants[i].id == id) return variants.splice(i,1);
    }
