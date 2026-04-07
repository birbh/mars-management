(function() {
    const TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
    let logoutTimer = setTimeout(triggerLogout, TIMEOUT_MS);

    function triggerLogout() {
        window.location.href = '../login.php?reason=session_expired';
    }

    function resetTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(triggerLogout, TIMEOUT_MS);
    }

    // Reset the timer whenever the user interacts with the page
    ['click', 'mousemove', 'keydown', 'scroll'].forEach(event => {
        window.addEventListener(event, resetTimer);
    });
})();
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
        return res.json().catch(function () {
            return {};
        }).then(function (json) {
            if(!res.ok){
                throw new Error((json && json.error) ? json.error : ("HTTP"+res.status));
            }
            return json;
        });
    }).then(function(json){
        if(!json.success){
            throw new Error(json.error||'API error');
        }
        return json.data;
    });
}




