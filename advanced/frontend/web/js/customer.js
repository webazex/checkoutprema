(function () {
    'use strict';

    var activeNotice = null;
    var activeNoticeTimer = null;

    function closeNotice() {
        if (activeNoticeTimer) {
            window.clearTimeout(activeNoticeTimer);
            activeNoticeTimer = null;
        }

        if (!activeNotice) {
            return;
        }

        activeNotice.classList.remove('customer-stub-notice--visible');

        var noticeToRemove = activeNotice;
        activeNotice = null;

        window.setTimeout(function () {
            if (noticeToRemove && noticeToRemove.parentNode) {
                noticeToRemove.parentNode.removeChild(noticeToRemove);
            }
        }, 220);
    }

    function showNotice(title, message, closeLabel) {
        closeNotice();

        var notice = document.createElement('div');
        notice.className = 'customer-stub-notice';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');

        var inner = document.createElement('div');
        inner.className = 'customer-stub-notice__inner';

        var titleEl = document.createElement('div');
        titleEl.className = 'customer-stub-notice__title';
        titleEl.textContent = title;

        var messageEl = document.createElement('div');
        messageEl.className = 'customer-stub-notice__message';
        messageEl.textContent = message;

        var closeBtn = document.createElement('button');
        closeBtn.className = 'customer-stub-notice__close';
        closeBtn.type = 'button';
        closeBtn.textContent = closeLabel;
        closeBtn.addEventListener('click', closeNotice);

        inner.appendChild(titleEl);
        inner.appendChild(messageEl);
        inner.appendChild(closeBtn);

        notice.appendChild(inner);
        document.body.appendChild(notice);

        activeNotice = notice;

        window.requestAnimationFrame(function () {
            notice.classList.add('customer-stub-notice--visible');
        });

        activeNoticeTimer = window.setTimeout(closeNotice, 9000);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-customer-login-stub]');

        if (!form) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        showNotice(
            form.getAttribute('data-stub-title') || '',
            form.getAttribute('data-stub-message') || '',
            form.getAttribute('data-stub-close') || 'OK'
        );
    }, true);
})();