(function () {
    var token = new URLSearchParams(location.hash.slice(1)).get('access');
    history.replaceState(null, '', location.pathname);
    if (!token || !/^[a-f0-9]{64}$/.test(token)) {
        document.getElementById('accessMessage').textContent = 'Open the private booking link from your email, or contact reception.';
        return;
    }
    document.getElementById('accessToken').value = token;
    document.getElementById('accessForm').submit();
})();
