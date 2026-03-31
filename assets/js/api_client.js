function api_get(url){
    return fetch(url,{
        credentials:'same-origin'
    }).then(function(res){
        if(!res.ok)
            throw new Error("HTTP"+res.status);
        return res.json();
    }).then(function(json){
        if(!json.success)
            throw new Error(json.error||'API error');
        return json.data;
    });
}

function api_send(url,method,payload){
    return fetch(url,{
        method: method,
        credentials: 'same-origin',
        headers:{
            'Content-Type':'application/json'
        },
        body:JSON.stringify(payload||{})
    }).then(function(res){
        if(!res.ok){
            throw new Error("HTTP"+res.status);

        }
        return res.json();
    }).then(function(json){
        if(!json.success){
            throw new Error(json.error||'API error');
        }
        return json.data;
    });
}


