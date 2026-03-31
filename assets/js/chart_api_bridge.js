(function(){
    var cache={
        history:null,
        latest:null,
        lastFetchMs:0
    };
    function hasData(){
        return cache.history && cache.latest;
    }
    function fetchAll(){
        return Promise.all([
            api_get('../api/telemetry/history.php?limit=12'),
            api_get('../api/telemetry/latest.php')
        ]).then(function(arr){
            cache.history=arr[0];
            cache.latest=arr[1];
            cache.lastFetchMs=Date.now();
        }).catch(function(err){
            console.log('API bridge fetch failed:',err);
        });
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

    })