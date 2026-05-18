// Copy-to-clipboard for the BE module's raw JSON-LD <pre>.
// Activated by `data-enhancely-copy="<targetId>"` on a button element.
(function () {
    'use strict';

    function flashLabel(button, message) {
        var original = button.innerHTML;
        button.innerHTML = message;
        button.disabled = true;
        setTimeout(function () {
            button.innerHTML = original;
            button.disabled = false;
        }, 1500);
    }

    function onClick(button) {
        var targetId = button.getAttribute('data-enhancely-copy');
        var target = targetId ? document.getElementById(targetId) : null;
        if (!target) {
            return;
        }
        var text = target.textContent || '';
        var copiedLabel = button.getAttribute('data-copied-label') || 'Copied!';

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(
                function () { flashLabel(button, copiedLabel); },
                function () { fallbackCopy(target, button, copiedLabel); }
            );
        } else {
            fallbackCopy(target, button, copiedLabel);
        }
    }

    function fallbackCopy(target, button, copiedLabel) {
        var range = document.createRange();
        range.selectNodeContents(target);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        try {
            document.execCommand('copy');
            flashLabel(button, copiedLabel);
        } catch (e) {
            // give up silently
        }
        sel.removeAllRanges();
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-enhancely-copy]');
        if (button) {
            event.preventDefault();
            onClick(button);
        }
    });
})();
