/* MCG Biokinetics — admin interactions */
(function () {
    'use strict';

    /* Sidebar ------------------------------------------------------------- */
    var body = document.body;
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            body.classList.toggle('sidebar-open');
        });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            body.classList.remove('sidebar-open');
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            body.classList.remove('sidebar-open');
        }
    });

    /* Flashes ------------------------------------------------------------- */
    document.querySelectorAll('.flash-close').forEach(function (button) {
        button.addEventListener('click', function () {
            var flash = button.closest('.flash');
            if (flash) {
                flash.remove();
            }
        });
    });

    /* Confirm destructive actions ---------------------------------------- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        var message = el.getAttribute('data-confirm') || 'Are you sure?';
        if (el.tagName === 'FORM') {
            el.addEventListener('submit', function (event) {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        } else {
            el.addEventListener('click', function (event) {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        }
    });

    /* Block editor -------------------------------------------------------- */
    var editor = document.querySelector('[data-block-editor]');
    var template = document.getElementById('blockTemplate');

    function applyType(card) {
        var select = card.querySelector('.block-type');
        if (!select) {
            return;
        }
        var type = select.value;
        var textField = card.querySelector('.block-text-field');
        var imageField = card.querySelector('.block-image-field');
        if (textField) {
            textField.hidden = (type === 'image');
        }
        if (imageField) {
            imageField.hidden = (type !== 'image');
        }
        card.setAttribute('data-type', type);
    }

    function bindCard(card) {
        var select = card.querySelector('.block-type');
        if (select) {
            select.addEventListener('change', function () {
                applyType(card);
            });
        }
        var remove = card.querySelector('[data-remove-block]');
        if (remove) {
            remove.addEventListener('click', function () {
                card.remove();
            });
        }
        var up = card.querySelector('[data-move-up]');
        if (up) {
            up.addEventListener('click', function () {
                var previous = card.previousElementSibling;
                if (previous) {
                    card.parentNode.insertBefore(card, previous);
                }
            });
        }
        var down = card.querySelector('[data-move-down]');
        if (down) {
            down.addEventListener('click', function () {
                var next = card.nextElementSibling;
                if (next) {
                    card.parentNode.insertBefore(next, card);
                }
            });
        }
        var file = card.querySelector('input[type="file"]');
        if (file) {
            file.addEventListener('change', function () {
                var thumb = card.querySelector('.block-thumb');
                if (thumb && file.files && file.files[0]) {
                    thumb.src = URL.createObjectURL(file.files[0]);
                    thumb.hidden = false;
                }
            });
        }
        applyType(card);
    }

    if (editor) {
        editor.querySelectorAll('.block-card').forEach(bindCard);
        var addButton = document.querySelector('[data-add-block]');
        if (addButton && template) {
            addButton.addEventListener('click', function () {
                var index = parseInt(editor.getAttribute('data-next-index') || '0', 10);
                var html = template.innerHTML.replace(/__INDEX__/g, String(index));
                editor.setAttribute('data-next-index', String(index + 1));
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                var card = wrapper.firstElementChild;
                if (card) {
                    editor.appendChild(card);
                    bindCard(card);
                    var textarea = card.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                    }
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    }

    /* Gallery category rename toggle ------------------------------------- */
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-copy');
            if (!value) {
                return;
            }
            var done = function () {
                var original = button.innerHTML;
                button.innerHTML = 'Copied';
                window.setTimeout(function () {
                    button.innerHTML = original;
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(function () {});
            }
        });
    });

    /* Check all ----------------------------------------------------------- */
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            var name = master.getAttribute('data-check-all');
            document.querySelectorAll('input[name="' + name + '"]').forEach(function (box) {
                box.checked = master.checked;
            });
        });
    });

    /* Image preview for single-file uploads ------------------------------ */
    document.querySelectorAll('[data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.querySelector(input.getAttribute('data-preview-target'));
            if (target && input.files && input.files[0]) {
                target.src = URL.createObjectURL(input.files[0]);
                target.hidden = false;
            }
        });
    });
}());
