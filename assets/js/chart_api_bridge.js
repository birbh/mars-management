(function(){
    var FETCH_INTERVAL_MS = 5000;
    var FETCH_MIN_GAP_MS = 3500;
    var cache={
        history:null,
        latest:null,
        events:null,
        lastFetchMs:0
    };
    var inflightPromise = null;
    var lastFetchStartMs = 0;
    var readyResolve = null;
    var readyPromise = new Promise(function (resolve) {
        readyResolve = resolve;
    });

    function hasData(){
        return cache.history && cache.latest;
    }
    function runFetch(){
        return Promise.all([
            api_get('../api/telemetry/history.php?limit=12'),
            api_get('../api/telemetry/latest.php'),
            api_get('../api/events/recent.php?limit=10')
        ]).then(function(arr){
            cache.history=arr[0];
            cache.latest=arr[1];
            cache.events=(arr[2] && Array.isArray(arr[2].events)) ? arr[2].events : [];
            cache.lastFetchMs=Date.now();
            if (readyResolve) {
                readyResolve();
                readyResolve = null;
            }
            if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(new CustomEvent('mars_api_bridge_updated', {
                    detail: { lastFetchMs: cache.lastFetchMs }
                }));
            }
        }).catch(function(err){
            console.log('API bridge fetch failed:',err);
        });
    }
    function fetchAll(){
        var now = Date.now();
        if (inflightPromise) {
            return inflightPromise;
        }
        if (now - lastFetchStartMs < FETCH_MIN_GAP_MS) {
            return Promise.resolve();
        }

        lastFetchStartMs = now;
        inflightPromise = runFetch().finally(function(){
            inflightPromise = null;
        });
        return inflightPromise;
    }
    function toStormPayload(){
        if(!hasData()) return null;
        return{
            labels: cache.history.labels||[],
            values:(cache.history.storm && cache.history.storm.values)?cache.history.storm.values:[],
            latest:cache.latest.storm||null
        };

    }
    function toRadiationPayload(){
        if(!hasData()) return null;
        return{
            labels: cache.history.labels||[],
            values:(cache.history.radiation && cache.history.radiation.values)?cache.history.radiation.values:[],
            latest:cache.latest.radiation||null
        };
    }
    function toPowerPayload(){
        if(!hasData()) return null;
        return{
            labels: cache.history.labels||[],
            solar_output:(cache.history.power && cache.history.power.solar_output)?cache.history.power.solar_output:[],
            battery_level: (cache.history.power && cache.history.power.battery_level)?cache.history.power.battery_level:[],
            latest:cache.latest.power||null
        }

    };
    function toHealthValue(){
        if(!hasData()) return null;
        return Number(cache.latest.health || 0);

    }
    function getLatest(){
        return cache.latest || null;
    }
    function getRecentEvents(limit){
        var rows = Array.isArray(cache.events) ? cache.events : [];
        if (typeof limit !== 'number' || limit <= 0) {
            return rows.slice();
        }
        return rows.slice(0, limit);
    }
    function installAdminOverrides(){
        if(typeof window.admin_mock_storm_payload==='function'){
            var oldStorm=window.admin_mock_storm_payload;
             window.admin_mock_storm_payload=function(){
                return toStormPayload()||oldStorm();
             };
        }
        if(typeof window.admin_mock_radiation_payload==='function'){
            var oldRad=window.admin_mock_radiation_payload;
            window.admin_mock_radiation_payload=function(){
                return toRadiationPayload()||oldRad();
            };
        }
        if(typeof window.admin_mock_power_payload==='function'){
            var oldPower=window.admin_mock_power_payload;
            window.admin_mock_power_payload=function(){
                return toPowerPayload()||oldPower();
            };
        }
        if(typeof window.admin_mock_health_value==='function'){
            var oldHealth=window.admin_mock_health_value;
            window.admin_mock_health_value=function(){
                var v=toHealthValue();
                return v===null?oldHealth():v;
            };
        }
    }
    function installAstroOverrides(){
        if(typeof window.astro_mock_storm_payload==='function'){
            var oldStorm=window.astro_mock_storm_payload;
            window.astro_mock_storm_payload=function(){
                return toStormPayload()||oldStorm();
            };
       }
       if(typeof window.astro_mock_radiation_payload==='function'){
        var oldRad=window.astro_mock_radiation_payload;
        window.astro_mock_radiation_payload=function(){
            return toRadiationPayload()||oldRad();
        };
    }
        if(typeof window.astro_mock_power_payload==='function'){
            var oldPower=window.astro_mock_power_payload;
            window.astro_mock_power_payload=function(){
                return toPowerPayload()||oldPower();
            };
        }
        if(typeof window.astro_mock_health_value==='function'){
            var oldHealth=window.astro_mock_health_value;
            window.astro_mock_health_value=function(){
                var v=toHealthValue();
                return v===null?oldHealth():v;
            }
        }

    }
    function installUserOverrides(){
        if(typeof window.user_mock_storm_payload==='function'){
            var oldStorm=window.user_mock_storm_payload;
            window.user_mock_storm_payload=function(){
                return toStormPayload()||oldStorm();
            }
        }
        if(typeof window.user_mock_radiation_payload==='function'){
            var oldRad=window.user_mock_radiation_payload;
            window.user_mock_radiation_payload=function(){
                if(hasData()){
                    return{latest:cache.latest.radiation||null};

                }
                return oldRad();
            };
        }
        if(typeof window.user_mock_health_value==='function'){
            var oldHealth=window.user_mock_health_value;
            window.user_mock_health_value=function(){
                var v=toHealthValue();
                return v===null?oldHealth():v;
            };
        }
    }
    installAdminOverrides();
    installAstroOverrides();
    installUserOverrides();

    // allow rfresh
    window.mars_api_bridge_refresh = fetchAll;
    window.mars_api_bridge_ready = readyPromise;
    window.mars_api_bridge_get_latest = getLatest;
    window.mars_api_bridge_get_events = getRecentEvents;

    fetchAll();
    setInterval(fetchAll,FETCH_INTERVAL_MS);
})();




