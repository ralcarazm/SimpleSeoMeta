(function () {
    'use strict';

    var injected = false;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function init() {
        insertSimpleSeoMetaAccordion();
    }

    ready(init);

    window.SimpleSeoMetaAdmin = {
        init: init,
        insert: insertSimpleSeoMetaAccordion
    };

    function insertSimpleSeoMetaAccordion() {
        var template = document.getElementById('simple-seo-meta-template');
        if (!template) {
            return false;
        }

        // The hidden template itself contains the accordion and the form fields.
        // Therefore, global checks such as document.getElementById('simple-seo-meta-accordion')
        // would find the template copy and wrongly stop the insertion. Only block insertion
        // when a copy already exists outside the template.
        if (injected || hasLiveSimpleSeoMetaBlock(template)) {
            return false;
        }

        var form = findSimplePagesForm();
        if (!form) {
            return false;
        }

        var accordion = template.querySelector('#simple-seo-meta-accordion') || template.firstElementChild;
        if (!accordion) {
            return false;
        }

        placeAccordionInNormalFlow(form, accordion);
        bindAccordion(accordion);
        injected = true;

        if (template.parentNode) {
            template.parentNode.removeChild(template);
        }

        return true;
    }

    function hasLiveSimpleSeoMetaBlock(template) {
        var nodes = document.querySelectorAll('#simple-seo-meta-accordion, [name="simple_seo_meta_seo_title"]');
        for (var i = 0; i < nodes.length; i++) {
            if (!template.contains(nodes[i])) {
                return true;
            }
        }
        return false;
    }

    function findSimplePagesForm() {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) {
            if (looksLikeSimplePagesForm(forms[i])) {
                return forms[i];
            }
        }

        // Last-resort fallback: on a Simple Pages admin URL, use the largest form.
        // This keeps the plugin working across small differences in Simple Pages markup.
        if (window.location.pathname.toLowerCase().indexOf('simple-pages') !== -1 && forms.length) {
            return findLargestForm(forms);
        }

        return null;
    }

    function looksLikeSimplePagesForm(form) {
        var path = window.location.pathname.toLowerCase();
        var action = (form.getAttribute('action') || '').toLowerCase();
        var urlSuggestsSimplePages = path.indexOf('/admin/simple-pages') !== -1 || path.indexOf('simple-pages') !== -1;
        var actionSuggestsSimplePages = action.indexOf('simple-pages') !== -1;

        var hasTitle = !!form.querySelector('input[name="title"], textarea[name="title"], input#title, textarea#title');
        var hasSlug = !!form.querySelector('input[name="slug"], input#slug');
        var hasText = !!form.querySelector('textarea[name="text"], textarea#text, textarea[name="page_text"], textarea#page_text, textarea[name="body"], textarea#body, [name="use_tiny_mce"]');

        if ((urlSuggestsSimplePages || actionSuggestsSimplePages) && hasTitle && hasSlug) {
            return true;
        }

        if ((urlSuggestsSimplePages || actionSuggestsSimplePages) && hasTitle && hasText) {
            return true;
        }

        return false;
    }

    function findLargestForm(forms) {
        var largest = forms[0];
        var largestCount = largest.querySelectorAll('input, textarea, select, button').length;
        for (var i = 1; i < forms.length; i++) {
            var count = forms[i].querySelectorAll('input, textarea, select, button').length;
            if (count > largestCount) {
                largest = forms[i];
                largestCount = count;
            }
        }
        return largest;
    }

    function placeAccordionInNormalFlow(form, accordion) {
        var title = form.querySelector('input[name="title"], textarea[name="title"], input#title, textarea#title');
        var main = title ? closestElement(title, '#primary') : null;
        if (!main) {
            main = form.querySelector('#primary') || document.getElementById('primary');
        }

        if (main && form.contains(main)) {
            var firstMainChild = findFirstInsertableChild(main);
            if (firstMainChild) {
                main.insertBefore(accordion, firstMainChild);
            } else {
                main.appendChild(accordion);
            }
            return;
        }

        var titleField = title ? closestElement(title, '.field') : null;
        if (titleField && titleField.parentNode) {
            titleField.parentNode.insertBefore(accordion, titleField);
            return;
        }

        var firstFieldset = form.querySelector('fieldset, .field');
        if (firstFieldset && firstFieldset.parentNode) {
            firstFieldset.parentNode.insertBefore(accordion, firstFieldset);
            return;
        }

        var firstFormChild = findFirstInsertableChild(form);
        if (firstFormChild) {
            form.insertBefore(accordion, firstFormChild);
        } else {
            form.appendChild(accordion);
        }
    }

    function bindAccordion(accordion) {
        var toggle = accordion.querySelector('.simple-seo-meta-toggle');
        var panel = accordion.querySelector('.simple-seo-meta-panel');
        var icon = accordion.querySelector('.simple-seo-meta-icon');
        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            var nextExpanded = !expanded;
            toggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
            panel.hidden = !nextExpanded;
            if (icon) {
                icon.textContent = nextExpanded ? '−' : '+';
            }
        });
    }

    function closestElement(node, selector) {
        while (node && node.nodeType === 1) {
            if (node.matches && node.matches(selector)) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    function findFirstInsertableChild(scope) {
        if (!scope || !scope.children) {
            return null;
        }
        for (var i = 0; i < scope.children.length; i++) {
            var child = scope.children[i];
            if (!child.matches || child.matches('input[type="hidden"], script, style')) {
                continue;
            }
            return child;
        }
        return null;
    }
}());
