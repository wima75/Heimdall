(function() {
    let currentIdx = 0;
    let maxIdx = 7;
    let focusMode = false;

    const elInfo   = document.getElementById('bingInfo');
    const elToggle = document.getElementById('bingInfoToggle');
    const elTitle  = document.getElementById('bingTitle');
    const elCopy   = document.getElementById('bingCopyright');
    const elFocus  = document.getElementById('bingFocus');
    const elMain   = document.getElementById('main');
    const elPrev   = document.getElementById('bingPrev');
    const elNext   = document.getElementById('bingNext');
    const elApp    = document.getElementById('app');

    function toggleFocus() {
        focusMode = !focusMode;
        elFocus.classList.toggle('active');

        if (focusMode) {
            elMain.style.display = 'none';
        } else {
            elMain.style.display = '';
        }
    }

    async function load(idx) {
        try {
            const res = await fetch(`/bing-info?idx=${idx}`);
            if (!res.ok) throw new Error('http ' + res.status);
            const data = await res.json();
            maxIdx = data.maxIdx ?? 7;
            elTitle.textContent = data.title || 'Bing Bild des Tages';
            elTitle.href = data.copyrightlink || '#';
            elCopy.textContent = data.copyright || '';
            elPrev.disabled = idx >= maxIdx;
            elNext.disabled = idx <= 0;

            if (elApp && data.imageUrl) {
                elApp.style.backgroundImage = `url('${data.imageUrl}')`;
            }
        } catch (e) {
            elTitle.textContent = 'Info nicht verfügbar';
            elCopy.textContent = '';
        }
    }

    let hideTimer = null;

    function showInfo() {
        clearTimeout(hideTimer);
        elInfo.classList.remove('collapsed');
    }

    function scheduleHide() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            elInfo.classList.add('collapsed');
        }, 200);
    }

    elInfo.addEventListener('mouseenter', showInfo);
    elInfo.addEventListener('mouseleave', scheduleHide);
    elFocus.addEventListener('click', toggleFocus);

    // Fallback für Touch-Geräte
    elToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        elInfo.classList.toggle('collapsed');
    });

    elPrev.addEventListener('click', () => {
        if (currentIdx < maxIdx) { currentIdx++; load(currentIdx); }
    });
    elNext.addEventListener('click', () => {
        if (currentIdx > 0) { currentIdx--; load(currentIdx); }
    });

    load(currentIdx);
})();
