const message = document.getElementById('message');
if (message) {
    setTimeout(() => {
        message.style.opacity = '0';
        setTimeout(() => message.style.display = 'none', 500);
    }, 3000);
};

document.addEventListener('change', function (e) {
    if (e.target && e.target.tagName === 'SELECT') {
        e.target.blur();
    }
});
