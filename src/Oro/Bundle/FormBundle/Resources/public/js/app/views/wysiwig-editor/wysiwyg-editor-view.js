define(function(require) {
    'use strict';

    var WysiwygEditorView;
    var BaseView = require('oroui/js/app/views/base/view');
    var _ = require('underscore');
    var $ = require('tinymce/jquery.tinymce.min');
    var txtHtmlTransformer = require('./txt-html-transformer');
    var LoadingMask = require('oroui/js/app/views/loading-mask-view');

    WysiwygEditorView = BaseView.extend({
        TINYMCE_UI_HEIGHT: 3,
        TEXTAREA_UI_HEIGHT: 22,

        autoRender: true,
        firstRender: true,
        firstQuoteLine: false,

        tinymceConnected: false,
        height: false,
        tinymceInstance: null,

        defaults: {
            enabled: true,
            plugins: ['textcolor', 'code', 'bdesk_photo'],
            menubar: false,
            toolbar: ['undo redo | bold italic underline | forecolor backcolor | bullist numlist | code | bdesk_photo'],
            statusbar: false,
            browser_spellcheck: true
        },

        events: {
            'set-focus': 'setFocus'
        },

        initialize: function(options) {
            options = $.extend(true, {}, this.defaults, options);
            this.enabled = options.enabled;
            this.options = _.omit(options, ['enabled']);
            WysiwygEditorView.__super__.initialize.apply(this, arguments);
        },

        render: function() {
            if (this.tinymceConnected) {
                if (!this.tinymceInstance) {
                    throw new Error('Cannot disable tinyMCE before its instance is created');
                }
                this.tinymceInstance.remove();
                this.tinymceInstance = null;

                // strip tags when disable HTML editing mode
                this.htmlValue = this.$el.val();
                this.strippedValue = txtHtmlTransformer.html2text(this.htmlValue);
                this.$el.val(this.strippedValue);

                this.$el.show();
                this.tinymceConnected = false;
            }
            if (this.enabled) {
                this.connectTinyMCE();
                this.$el.attr('data-focusable', true);
            } else {
                this.$el.removeAttr('data-focusable');
            }
            this.firstRender = false;
            this.trigger('resize');
        },

        connectTinyMCE: function() {
            var $quote;
            var lines;
            var loadingMaskContainer;
            var self = this;
            loadingMaskContainer = this.$el.parents('.ui-dialog');
            if (!loadingMaskContainer.length) {
                loadingMaskContainer = this.$el.parent();
            }
            this.subview('loadingMask', new LoadingMask({
                container: loadingMaskContainer
            }));
            this.subview('loadingMask').show();
            if (!this.firstRender) {
                if (this.htmlValue && this.$el.val() === this.strippedValue) {
                    // if content is not modified, return html representation back
                    this.$el.val(this.htmlValue);
                } else {
                    this.$el.val(txtHtmlTransformer.text2html(this.$el.val()));
                }
            }
            this.renderDeferred = $.Deferred();
            var options = this.options;
            if ($(this.$el).prop('disabled')) {
                options.readonly = true;
            }
            this.$el.tinymce(_.extend({
                'init_instance_callback': function(editor) {
                    /**
                     * fix of https://magecore.atlassian.net/browse/BAP-7130
                     * "WYSWING editor does not work with IE"
                     * Please check if it's still required after tinyMCE update
                     */
                    setTimeout(function() {
                        var focusedElement = $(':focus');
                        editor.focus();
                        focusedElement.focus();
                    }, 0);

                    self.removeSubview('loadingMask');
                    self.tinymceInstance = editor;
                    _.defer(function() {
                        /**
                         * fixes jumping dialog on refresh page
                         * (promise should be resolved in a separate process)
                         */
                        self.renderDeferred.resolve();
                    });
                }
            }, options));
            this.tinymceConnected = true;
            this.firstQuoteLine = false;
            $quote = $('<div>').html(this.$el.val()).find('.quote');
            if ($quote.length > 0) {
                lines = txtHtmlTransformer.html2multiline($quote.html());
                if (lines.length > 0) {
                    this.firstQuoteLine = lines[0];
                }
            }
        },

        setEnabled: function(enabled) {
            if (this.enabled === enabled) {
                return;
            }
            this.enabled = enabled;
            this.render();
        },

        setFocus: function(e) {
            if (this.enabled) {
                this.tinymceInstance.focus();
            }
        },

        getHeight: function() {
            return this.$el.parent().innerHeight();
        },

        getFirstQuoteLine: function() {
            return this.firstQuoteLine;
        },

        setHeight: function(newHeight) {
            var currentToolbarHeight;
            if (this.tinymceConnected) {
                currentToolbarHeight = this.$el.parent().find('.mce-toolbar-grp').outerHeight();
                this.$el.parent().find('iframe').height(newHeight - currentToolbarHeight - this.TINYMCE_UI_HEIGHT);
            } else {
                this.$el.height(newHeight - this.TEXTAREA_UI_HEIGHT);
            }
        },

        dispose: function() {
            if (this.disposed) {
                return;
            }
            if (this.tinymceInstance) {
                this.tinymceInstance.remove();
                this.tinymceInstance = null;
            }
            WysiwygEditorView.__super__.dispose.call(this);
        }
    });

    return WysiwygEditorView;
});
