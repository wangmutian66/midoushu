/*高德地图jsApi*/



function mapsamp(lng,lat){
    var lnglatXY = "";
    if(lat!="" &&　lng!=''){
        lnglatXY = [lng,lat]; //已知点坐标
    }


    var map = new AMap.Map('ditu', {
        center:lnglatXY,
        resizeEnable: true,
        zoom: 13,
    });


    marker = new AMap.Marker({
        map: map,
        bubble: true
    });

    /*搜索获取坐标*/
    AMapUI.loadUI(['misc/PoiPicker'], function(PoiPicker) {
        var poiPicker = new PoiPicker({
            input: 'address'
        });

        map.on('click', function(e) {
            marker.setPosition(e.lnglat);
        });
        infoWindow = new AMap.InfoWindow({
            offset: new AMap.Pixel(0, -20)
        });

        poiPicker.on('poiPicked', function(poiResult) {
            source = poiResult.source,
                poi = poiResult.item,
             
                info = {
                    source: source,
                    id: poi.id,
                    name: poi.name,
                    location: poi.location.toString(),
                    address: poi.address
                };


            var lnglat = poi.location.toString().split(",");

            document.getElementById('lat').value = lnglat[1];
            document.getElementById('lng').value = lnglat[0];

            //document.getElementById("coordinate").value = poi.location.toString();
            marker.setMap(map);
            infoWindow.setMap(map);
            marker.setPosition(poi.location);
            infoWindow.setPosition(poi.location);
            //document.getElementById('coordinate').value = info.location;

            // document.getElementById('address').value = info.name;
            infoWindow.open(map, marker.getPosition());
        });
        poiPicker.onCityReady(function() {
            poiPicker.suggest('');
        });
    });

    /*点击获取坐标*/
    AMap.plugin('AMap.Geocoder', function() {
        geocoder = new AMap.Geocoder({
            city: "010" /*城市，默认：“全国”*/
        });
        map.on('click', function(e) {
            marker.setPosition(e.lnglat);
            console.log(e.lnglat.N + ',' + e.lnglat.Q); //126.63734992060517,45.75640599798774
            ////{"latitude":45.80216,"longitude":126.5358,"errMsg":"getLocation:ok"};
            //document.getElementById('coordinate').value = e.lnglat.N + ',' + e.lnglat.Q;

            document.getElementById('lat').value = e.lnglat.Q;
            document.getElementById('lng').value = e.lnglat.N;

            geocoder.getAddress(e.lnglat, function(status, result) {
                if (status == 'complete') {
                    document.getElementById('address').value = result.regeocode.formattedAddress;
                }
            })
        })
    });
}

function regeocoder(coordinate) {  //逆地理编码
    var arr = coordinate.split(',');
    lnglatXY = [arr[0],arr[1]]; //已知点坐标

    var geocoder = new AMap.Geocoder({
        //radius: 1000,
        center:lnglatXY,
        resizeEnable: true,
        zoom: 13,
        //extensions: "all"
    });
    geocoder.getAddress(lnglatXY, function(status, result) {
        if (status === 'complete' && result.info === 'OK') {
            geocoder_CallBack(result);
        }
    });
    marker.setPosition(lnglatXY);
}

function geocoder_CallBack(data) {
    var address = data.regeocode.formattedAddress; //返回地址描述
    console.log('地址'+address);
    document.getElementById("address").value = address;
}




